import Metal
import MetalKit
import simd
import CoreGraphics

final class InkRenderer: NSObject, MTKViewDelegate {
    enum SelectionHandle { case topLeft, topRight, bottomLeft, bottomRight }
    private struct Uniforms { var viewportSize: SIMD2<Float> }
    private struct StoredStroke: Equatable { var id: UUID; var points: [InkPoint]; var style: PenStyle; var rotation: Float = 0 }
    private struct HistoryState: Equatable {
        var strokes: [StoredStroke]
        var objects: [GraphicObject]
        var selection: [Int]
        var objectSelection: [UUID]
    }

    private let device: any MTLDevice
    private let commandQueue: any MTLCommandQueue
    private let pipelineState: any MTLRenderPipelineState
    private var committedStrokes: [StoredStroke] = []
    private var objectStore = GraphicObjectStore()
    private var activeStroke: [InkPoint] = []
    private var penStyle = PenStyle()
    private(set) var selectedStrokeIndices: [Int] = []
    private(set) var selectedObjectIDs: [UUID] = []
    private var customRotationCenter: SIMD2<Float>?
    private var backgroundColor = SIMD4<Float>(1,1,1,1)
    private var displayInverted = false
    private var backgroundPattern = 0
    private var panOffset = SIMD2<Float>(0,0)
    private var zoomScale: Float = 1
    private var undoStack: [HistoryState] = []
    private var redoStack: [HistoryState] = []
    private var transactionStart: HistoryState?

    init?(device: any MTLDevice) {
        guard let queue = device.makeCommandQueue(),
              let library = try? device.makeDefaultLibrary(bundle: Bundle.module),
              let vf = library.makeFunction(name: "inkVertex"),
              let ff = library.makeFunction(name: "inkFragment") else { return nil }
        let descriptor = MTLRenderPipelineDescriptor()
        descriptor.vertexFunction = vf
        descriptor.fragmentFunction = ff
        descriptor.colorAttachments[0].pixelFormat = .bgra8Unorm
        descriptor.colorAttachments[0].isBlendingEnabled = true
        descriptor.colorAttachments[0].sourceRGBBlendFactor = .sourceAlpha
        descriptor.colorAttachments[0].destinationRGBBlendFactor = .oneMinusSourceAlpha
        descriptor.colorAttachments[0].sourceAlphaBlendFactor = .sourceAlpha
        descriptor.colorAttachments[0].destinationAlphaBlendFactor = .oneMinusSourceAlpha
        guard let pipeline = try? device.makeRenderPipelineState(descriptor: descriptor) else { return nil }
        self.device = device
        self.commandQueue = queue
        self.pipelineState = pipeline
        super.init()
    }

    var canUndo: Bool { !undoStack.isEmpty }
    var canRedo: Bool { !redoStack.isEmpty }
    var hasSelection: Bool { !selectedStrokeIndices.isEmpty || !selectedObjectIDs.isEmpty }
    var selectionCount: Int { selectedStrokeIndices.count + selectedObjectIDs.count }
    var selectedStrokeIndex: Int? { selectedObjectIDs.isEmpty && selectedStrokeIndices.count == 1 ? selectedStrokeIndices[0] : nil }
    var zoomPercent: Int { Int((zoomScale * 100).rounded()) }
    var selectedRotationDegrees: Double {
        if selectedObjectIDs.count == 1, let id = selectedObjectIDs.first, let object = objectStore.object(with: id) {
            return Double(object.transform.rotation * 180 / .pi)
        }
        guard let i = selectedStrokeIndex, committedStrokes.indices.contains(i) else { return 0 }
        return Double(committedStrokes[i].rotation * 180 / .pi)
    }

    func setPenStyle(_ style: PenStyle) { penStyle = style; rebuildGeometry() }
    func setBackgroundColor(_ color: SIMD4<Float>) { backgroundColor = color; rebuildGeometry() }
    func setDisplayInverted(_ value: Bool) { displayInverted = value; rebuildGeometry() }
    func setBackgroundPattern(_ value: Int) { backgroundPattern = value; rebuildGeometry() }
    func setStroke(_ points: [InkPoint]) { activeStroke = points; rebuildGeometry() }

    func canvasPoint(from point: SIMD2<Float>) -> SIMD2<Float> { (point - panOffset) / zoomScale }
    func viewPoint(from point: SIMD2<Float>) -> SIMD2<Float> { point * zoomScale + panOffset }
    func pan(by delta: SIMD2<Float>) { panOffset += delta; rebuildGeometry() }
    func zoom(by factor: Float, around point: SIMD2<Float>) {
        let c = canvasPoint(from: point)
        zoomScale = min(max(zoomScale * factor, 0.25), 4)
        panOffset = point - c * zoomScale
        rebuildGeometry()
    }
    func resetZoom(centeredIn size: CGSize) { zoomScale = 1; panOffset = SIMD2(Float(size.width/2), Float(size.height/2)); rebuildGeometry() }

    func exportPageState() -> CanvasPageState {
        CanvasPageState(strokes: committedStrokes.map { CanvasStroke(id:$0.id, points:$0.points, style:$0.style, rotation:$0.rotation) }, objects: objectStore.exportObjects())
    }
    func importPageState(_ state: CanvasPageState) {
        committedStrokes = state.strokes.map { StoredStroke(id:$0.id, points:$0.points, style:$0.style, rotation:$0.rotation) }
        objectStore.importObjects(state.objects)
        activeStroke.removeAll(); clearSelectionInternal(); undoStack.removeAll(); redoStack.removeAll(); transactionStart = nil; rebuildGeometry()
    }

    private func captureState() -> HistoryState { HistoryState(strokes:committedStrokes, objects:objectStore.exportObjects(), selection:selectedStrokeIndices, objectSelection:selectedObjectIDs) }
    private func restore(_ state: HistoryState) { committedStrokes=state.strokes; objectStore.importObjects(state.objects); selectedStrokeIndices=state.selection.filter{committedStrokes.indices.contains($0)}; selectedObjectIDs=state.objectSelection.filter{objectStore.object(with:$0) != nil}; customRotationCenter=nil; rebuildGeometry() }
    private func recordMutation() { guard transactionStart == nil else { return }; undoStack.append(captureState()); redoStack.removeAll() }
    func beginHistoryTransaction() { if transactionStart == nil { transactionStart = captureState() } }
    func endHistoryTransaction() { guard let before=transactionStart else { return }; transactionStart=nil; let after=captureState(); if before != after { undoStack.append(before); redoStack.removeAll() } }
    func undo() { guard let s=undoStack.popLast() else { return }; redoStack.append(captureState()); restore(s) }
    func redo() { guard let s=redoStack.popLast() else { return }; undoStack.append(captureState()); restore(s) }

    func commitStroke(_ points:[InkPoint]) { guard points.count >= 2 else { activeStroke.removeAll(); rebuildGeometry(); return }; recordMutation(); committedStrokes.append(StoredStroke(id:UUID(),points:points,style:penStyle)); clearSelectionInternal(); activeStroke.removeAll(); rebuildGeometry() }
    func commitLine(from start:SIMD2<Float>, to end:SIMD2<Float>) {
        recordMutation()
        _ = objectStore.addLine(from:CGPoint(x:CGFloat(start.x),y:CGFloat(start.y)),to:CGPoint(x:CGFloat(end.x),y:CGFloat(end.y)),style:GraphicObjectStyle.from(penStyle))
        clearSelectionInternal(); activeStroke.removeAll(); rebuildGeometry()
    }

    @discardableResult func selectObject(at point:SIMD2<Float>, tolerance:Float=10) -> Bool {
        let p=canvasPoint(from:point)
        guard let id=objectStore.hitTest(at:CGPoint(x:CGFloat(p.x),y:CGFloat(p.y)),tolerance:CGFloat(tolerance/zoomScale)) else { clearSelection(); return false }
        selectedObjectIDs=[id]; selectedStrokeIndices=[]; customRotationCenter=nil; rebuildGeometry(); return true
    }
    @discardableResult func toggleObject(at point:SIMD2<Float>, tolerance:Float=10) -> Bool { selectObject(at:point,tolerance:tolerance) }
    @discardableResult func selectObjects(in viewRect:CGRect, fullyContained:Bool=false) -> Int {
        let a=canvasPoint(from:SIMD2(Float(viewRect.minX),Float(viewRect.minY))), b=canvasPoint(from:SIMD2(Float(viewRect.maxX),Float(viewRect.maxY)))
        let r=CGRect(x:CGFloat(min(a.x,b.x)),y:CGFloat(min(a.y,b.y)),width:CGFloat(abs(a.x-b.x)),height:CGFloat(abs(a.y-b.y)))
        selectedObjectIDs=objectStore.objectsIntersecting(r,fullyContained:fullyContained); selectedStrokeIndices=[]; rebuildGeometry(); return selectedObjectIDs.count
    }
    @discardableResult func selectStroke(at point:SIMD2<Float>, tolerance:Float=10) -> Bool {
        let p=canvasPoint(from:point); var hit:Int?; var best=tolerance/zoomScale
        for i in committedStrokes.indices.reversed() {
            let pts=committedStrokes[i].points
            for j in 0..<max(0,pts.count-1) {
                let a=SIMD2(pts[j].x,pts[j].y), b=SIMD2(pts[j+1].x,pts[j+1].y)
                let d=segmentDistance(p,a,b); if d <= best { best=d; hit=i; break }
            }
        }
        selectedStrokeIndices=hit.map{[$0]} ?? []; selectedObjectIDs=[]; rebuildGeometry(); return hit != nil
    }
    @discardableResult func selectStrokes(in viewRect:CGRect, fullyContained:Bool=false) -> Int {
        let a=canvasPoint(from:SIMD2(Float(viewRect.minX),Float(viewRect.minY))), b=canvasPoint(from:SIMD2(Float(viewRect.maxX),Float(viewRect.maxY)))
        let r=CGRect(x:CGFloat(min(a.x,b.x)),y:CGFloat(min(a.y,b.y)),width:CGFloat(abs(a.x-b.x)),height:CGFloat(abs(a.y-b.y)))
        selectedStrokeIndices=committedStrokes.indices.filter{ i in guard let b=bounds(committedStrokes[i].points) else{return false}; return fullyContained ? r.contains(b) : r.intersects(b)}; selectedObjectIDs=[]; rebuildGeometry(); return selectedStrokeIndices.count
    }
    func clearSelection(){ clearSelectionInternal(); rebuildGeometry() }
    private func clearSelectionInternal(){ selectedStrokeIndices=[]; selectedObjectIDs=[]; customRotationCenter=nil }

    func selectionBounds()->CGRect? {
        var r:CGRect?
        for i in selectedStrokeIndices { if let b=bounds(committedStrokes[i].points){r=r?.union(b) ?? b} }
        for id in selectedObjectIDs { if let b=objectStore.bounds(of:id){r=r?.union(b) ?? b} }
        return r?.insetBy(dx:-8,dy:-8)
    }
    func selectionBoundsInView()->CGRect? { guard let r=selectionBounds() else{return nil}; let a=viewPoint(from:SIMD2(Float(r.minX),Float(r.minY))), b=viewPoint(from:SIMD2(Float(r.maxX),Float(r.maxY))); return CGRect(x:CGFloat(min(a.x,b.x)),y:CGFloat(min(a.y,b.y)),width:CGFloat(abs(a.x-b.x)),height:CGFloat(abs(a.y-b.y))) }
    func selectionCenter()->SIMD2<Float>? { guard let r=selectionBounds() else{return nil}; return SIMD2(Float(r.midX),Float(r.midY)) }
    func selectionHandle(at point:SIMD2<Float>, tolerance:Float=10)->SelectionHandle? {
        guard let r=selectionBounds() else{return nil}; let p=canvasPoint(from:point),t=tolerance/zoomScale
        let h:[(SelectionHandle,SIMD2<Float>)]=[(.topLeft,SIMD2(Float(r.minX),Float(r.minY))),(.topRight,SIMD2(Float(r.maxX),Float(r.minY))),(.bottomLeft,SIMD2(Float(r.minX),Float(r.maxY))),(.bottomRight,SIMD2(Float(r.maxX),Float(r.maxY)))]
        return h.first{simd_distance(p,$0.1)<=t}?.0
    }
    func rotationHandle(at point:SIMD2<Float>, tolerance:Float=12)->Bool { guard let r=selectionBounds() else{return false}; let p=canvasPoint(from:point); return simd_distance(p,SIMD2(Float(r.midX),Float(r.minY-28))) <= tolerance/zoomScale }
    func rotationCenterHandle(at point:SIMD2<Float>, tolerance:Float=12)->Bool { guard let c=rotationCenterViewPoint(), customRotationCenter != nil else{return false}; return simd_distance(point,c)<=tolerance }
    func rotationCenterViewPoint()->SIMD2<Float>? { guard let c=customRotationCenter ?? selectionCenter() else{return nil}; return viewPoint(from:c) }
    func setRotationCenter(to point:SIMD2<Float>){customRotationCenter=canvasPoint(from:point); rebuildGeometry()}

    func moveSelected(by delta:SIMD2<Float>) {
        if !selectedObjectIDs.isEmpty { for id in selectedObjectIDs { guard let o=objectStore.object(with:id) else{continue}; _=objectStore.transform(id:id,position:CGPoint(x:o.transform.position.x+CGFloat(delta.x),y:o.transform.position.y+CGFloat(delta.y))) } }
        else { for i in selectedStrokeIndices { committedStrokes[i].points=committedStrokes[i].points.map{InkPoint(x:$0.x+delta.x,y:$0.y+delta.y,pressure:$0.pressure)} } }
        rebuildGeometry()
    }
    func scaleSelected(by factor:Float) {
        guard factor > 0, let c=selectionCenter() else{return}
        if !selectedObjectIDs.isEmpty { for id in selectedObjectIDs { guard let o=objectStore.object(with:id) else{continue}; _=objectStore.transform(id:id,scale:CGSize(width:o.transform.scale.width*CGFloat(factor),height:o.transform.scale.height*CGFloat(factor))) } }
        else { for i in selectedStrokeIndices { committedStrokes[i].points=committedStrokes[i].points.map{InkPoint(x:c.x+($0.x-c.x)*factor,y:c.y+($0.y-c.y)*factor,pressure:$0.pressure)} } }
        rebuildGeometry()
    }
    func reflectSelected(horizontal:Bool) {
        guard let c=selectionCenter() else{return}
        if !selectedObjectIDs.isEmpty { for id in selectedObjectIDs { guard let o=objectStore.object(with:id) else{continue}; let s=o.transform.scale; let ns=CGSize(width:horizontal ? -s.width : s.width,height:horizontal ? s.height : -s.height); _=objectStore.transform(id:id,scale:ns) } }
        else { for i in selectedStrokeIndices { committedStrokes[i].points=committedStrokes[i].points.map{InkPoint(x:horizontal ? c.x-($0.x-c.x):$0.x,y:horizontal ? $0.y:c.y-($0.y-c.y),pressure:$0.pressure)} } }
        rebuildGeometry()
    }
    func rotateSelected(to point:SIMD2<Float>, from previous:SIMD2<Float>) {
        guard let center=rotationCenterViewPoint() else{return}; let a=atan2(previous.y-center.y,previous.x-center.x), b=atan2(point.y-center.y,point.x-center.x), delta=b-a
        rotateSelected(by:delta,center:center); rebuildGeometry()
    }
    private func rotateSelected(by delta:Float, center viewCenter:SIMD2<Float>) {
        let c=canvasPoint(from:viewCenter)
        if !selectedObjectIDs.isEmpty { for id in selectedObjectIDs { guard let o=objectStore.object(with:id) else{continue}; _=objectStore.transform(id:id,rotation:o.transform.rotation+CGFloat(delta)) } }
        else { for i in selectedStrokeIndices { committedStrokes[i].points=committedStrokes[i].points.map{ p in let x=p.x-c.x,y=p.y-c.y,co=cos(delta),si=sin(delta); return InkPoint(x:c.x+x*co-y*si,y:c.y+x*si+y*co,pressure:p.pressure) } } }
    }
    func setSelectedRotationDegrees(_ degrees:Double) {
        let target=CGFloat(degrees * .pi / 180)
        if !selectedObjectIDs.isEmpty { for id in selectedObjectIDs { guard let o=objectStore.object(with:id) else{continue}; _=objectStore.transform(id:id,rotation:target) } }
        else if selectedStrokeIndices.count == 1, let i=selectedStrokeIndices.first { committedStrokes[i].rotation=Float(target) }
        rebuildGeometry()
    }
    func resizeSelected(handle:SelectionHandle,to point:SIMD2<Float>) {
        guard let r=selectionBounds() else{return}; let p=canvasPoint(from:point); let anchor:SIMD2<Float>
        switch handle {case .topLeft:anchor=SIMD2(Float(r.maxX),Float(r.maxY));case .topRight:anchor=SIMD2(Float(r.minX),Float(r.maxY));case .bottomLeft:anchor=SIMD2(Float(r.maxX),Float(r.minY));case .bottomRight:anchor=SIMD2(Float(r.minX),Float(r.minY))}
        let sx=max(abs(p.x-anchor.x),1)/max(Float(r.width),1), sy=max(abs(p.y-anchor.y),1)/max(Float(r.height),1)
        if !selectedObjectIDs.isEmpty { let f=Float((sx+sy)*0.5); scaleSelected(by:f) } else { for i in selectedStrokeIndices { committedStrokes[i].points=committedStrokes[i].points.map{InkPoint(x:anchor.x+($0.x-anchor.x)*sx,y:anchor.y+($0.y-anchor.y)*sy,pressure:$0.pressure)} }; rebuildGeometry() }
    }
    func deleteSelected(){ if !selectedObjectIDs.isEmpty { recordMutation(); for id in selectedObjectIDs{objectStore.remove(id:id)}; clearSelectionInternal() } else if !selectedStrokeIndices.isEmpty { recordMutation(); committedStrokes=committedStrokes.enumerated().filter{!selectedStrokeIndices.contains($0.offset)}.map{$0.element}; clearSelectionInternal() }; rebuildGeometry() }
    func eraseObjectsByScribble(_ path:[SIMD2<Float>],tolerance:Float=12)->Bool { let p=path.map{let q=canvasPoint(from:$0);return CGPoint(x:CGFloat(q.x),y:CGFloat(q.y))}; let ids=objectStore.eraseByScribble(p,tolerance:CGFloat(tolerance/zoomScale)); guard !ids.isEmpty else{return false}; selectedObjectIDs.removeAll{ids.contains($0)}; rebuildGeometry(); return true }
    @discardableResult func lineEndpoint(at point:SIMD2<Float>,tolerance:Float=14)->(id:UUID,endpoint:Int)? { let p=canvasPoint(from:point); guard let hit=objectStore.nearestLineEndpoint(to:CGPoint(x:CGFloat(p.x),y:CGFloat(p.y)),tolerance:CGFloat(tolerance/zoomScale)) else{return nil}; return(hit.id,hit.endpoint) }
    @discardableResult func moveSelectedLineEndpoint(id:UUID,endpoint:Int,to point:SIMD2<Float>)->Bool { let p=canvasPoint(from:point); guard objectStore.moveLineEndpoint(id:id,endpoint:endpoint,to:CGPoint(x:CGFloat(p.x),y:CGFloat(p.y))) else{return false}; rebuildGeometry(); return true }

    private func rebuildGeometry(){ }

    func draw(in view: MTKView) {
        guard let pass=view.currentRenderPassDescriptor, let drawable=view.currentDrawable, let commandBuffer=commandQueue.makeCommandBuffer(), let encoder=commandBuffer.makeRenderCommandEncoder(descriptor:pass) else{return}
        encoder.setRenderPipelineState(pipelineState)
        var uniforms=Uniforms(viewportSize:SIMD2(Float(max(view.drawableSize.width,1)),Float(max(view.drawableSize.height,1))))
        encoder.setVertexBytes(&uniforms,length:MemoryLayout<Uniforms>.stride,index:1)
        var vertices:[InkVertex]=[]
        for stroke in committedStrokes { appendStroke(stroke.points,style:stroke.style,rotation:stroke.rotation,to:&vertices) }
        if !activeStroke.isEmpty { appendStroke(activeStroke,style:penStyle,rotation:0,to:&vertices) }
        for object in objectStore.objects { appendObject(object,to:&vertices) }
        if !vertices.isEmpty { encoder.setVertexBytes(vertices,length:MemoryLayout<InkVertex>.stride*vertices.count,index:0); encoder.drawPrimitives(type:.triangle,vertexStart:0,vertexCount:vertices.count) }
        encoder.endEncoding(); commandBuffer.present(drawable); commandBuffer.commit()
    }
    func mtkView(_ view:MTKView,drawableSizeWillChange size:CGSize){ }

    private func color(_ c:RGBAColor, opacity:CGFloat=1)->SIMD4<Float>{SIMD4(Float(c.red),Float(c.green),Float(c.blue),Float(c.alpha*opacity))}
    private func worldToView(_ p:CGPoint)->SIMD2<Float>{viewPoint(from:SIMD2(Float(p.x),Float(p.y)))}
    private func appendStroke(_ points:[InkPoint],style:PenStyle,rotation:Float,to out:inout[InkVertex]) {
        guard points.count >= 2 else{return}; let center=points.reduce(SIMD2<Float>(0,0)){ $0+SIMD2($1.x,$1.y) } / Float(points.count); let co=cos(rotation),si=sin(rotation); let col=color(style.color,opacity:style.opacity)
        for i in 0..<(points.count-1) {
            let p=points[i],q=points[i+1]; let w0=Float(style.width(for:CGFloat(p.pressure))),w1=Float(style.width(for:CGFloat(q.pressure))); var a=SIMD2(p.x,p.y),b=SIMD2(q.x,q.y); if rotation != 0 { a=SIMD2(center.x+(a.x-center.x)*co-(a.y-center.y)*si,center.y+(a.x-center.x)*si+(a.y-center.y)*co); b=SIMD2(center.x+(b.x-center.x)*co-(b.y-center.y)*si,center.y+(b.x-center.x)*si+(b.y-center.y)*co) }; let d=b-a; let l=max(simd_length(d),0.001); let n=SIMD2(-d.y/l,d.x/l); let va=worldToView(CGPoint(x:CGFloat(a.x+n.x*w0),y:CGFloat(a.y+n.y*w0))), vb=worldToView(CGPoint(x:CGFloat(a.x-n.x*w0),y:CGFloat(a.y-n.y*w0))), vc=worldToView(CGPoint(x:CGFloat(b.x+n.x*w1),y:CGFloat(b.y+n.y*w1))), vd=worldToView(CGPoint(x:CGFloat(b.x-n.x*w1),y:CGFloat(b.y-n.y*w1))); out += [InkVertex(position:va,color:col),InkVertex(position:vb,color:col),InkVertex(position:vc,color:col),InkVertex(position:vc,color:col),InkVertex(position:vb,color:col),InkVertex(position:vd,color:col)]
        }
    }
    private func appendObject(_ object:GraphicObject,to out:inout[InkVertex]) {
        let p=objectStore.transformedPoints(of:object); guard p.count >= 2 else{return}; let col=color(object.style.strokeColor,opacity:object.style.opacity); let width=max(Float(object.style.strokeWidth),1)
        for i in 0..<(p.count-1) { appendSegment(p[i],p[i+1],width:width,color:col,to:&out) }
        if object.kind == .polygon && p.count >= 3 { appendSegment(p[p.count-1],p[0],width:width,color:col,to:&out) }
    }
    private func appendSegment(_ a:CGPoint,_ b:CGPoint,width:Float,color:SIMD4<Float>,to out:inout[InkVertex]) { let va=worldToView(a),vb=worldToView(b),d=vb-va,l=max(simd_length(d),0.001),n=SIMD2(-d.y/l,d.x/l)*(width*0.5); out += [InkVertex(position:va+n,color:color),InkVertex(position:va-n,color:color),InkVertex(position:vb+n,color:color),InkVertex(position:vb+n,color:color),InkVertex(position:va-n,color:color),InkVertex(position:vb-n,color:color)] }

    private func bounds(_ points:[InkPoint])->CGRect? { guard let first=points.first else{return nil}; var r=CGRect(x:CGFloat(first.x),y:CGFloat(first.y),width:0,height:0); for p in points.dropFirst(){r=r.union(CGRect(x:CGFloat(p.x),y:CGFloat(p.y),width:0,height:0))}; return r }
    private func segmentDistance(_ p:SIMD2<Float>,_ a:SIMD2<Float>,_ b:SIMD2<Float>)->Float { let d=b-a, l2=simd_length_squared(d); if l2 == 0{return simd_distance(p,a)}; let t=max(0,min(1,simd_dot(p-a,d)/l2)); return simd_distance(p,a+t*d) }
}
