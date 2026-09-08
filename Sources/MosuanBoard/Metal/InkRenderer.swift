import Metal
import MetalKit
import simd

final class InkRenderer: NSObject, MTKViewDelegate {
    enum SelectionHandle { case topLeft, topRight, bottomLeft, bottomRight }
    private struct Uniforms { var viewportSize: SIMD2<Float> }
    private struct StoredStroke: Equatable { var id: UUID; var points: [InkPoint]; var style: PenStyle; var rotation: Float = 0 }
    private struct HistoryState { var strokes: [StoredStroke]; var objects: [GraphicObject]; var selection: [Int]; var objectSelection: [UUID] }

    private let device: any MTLDevice
    private let commandQueue: any MTLCommandQueue
    private let pipelineState: any MTLRenderPipelineState
    private var committedStrokes: [StoredStroke] = []
    private var objectStore = GraphicObjectStore()
    private var activeStroke: [InkPoint] = []
    private var vertices: [InkVertex] = []
    private var vertexBuffer: (any MTLBuffer)?
    private var uniformBuffer: (any MTLBuffer)?
    private var penStyle = PenStyle()
    private(set) var selectedStrokeIndices: [Int] = []
    private(set) var selectedObjectIDs: [UUID] = []
    private var customRotationCenter: SIMD2<Float>?
    private var backgroundColor = SIMD4<Float>(1, 1, 1, 1)
    private var displayInverted = false
    private var backgroundPattern = 0
    private var panOffset = SIMD2<Float>(0, 0)
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
            return Double(object.transform.rotation) * 180 / Double.pi
        }
        guard let index = selectedStrokeIndex, committedStrokes.indices.contains(index) else { return 0 }
        return Double(committedStrokes[index].rotation) * 180 / Double.pi
    }

    func setPenStyle(_ style: PenStyle) { penStyle = style; rebuildGeometry() }
    func setBackgroundColor(_ color: SIMD4<Float>) { backgroundColor = color; rebuildGeometry() }
    func setDisplayInverted(_ value: Bool) { displayInverted = value; rebuildGeometry() }
    func setBackgroundPattern(_ value: Int) { backgroundPattern = value; rebuildGeometry() }
    func setStroke(_ points: [InkPoint]) { activeStroke = points; rebuildGeometry() }
    func canvasPoint(from point: SIMD2<Float>) -> SIMD2<Float> { (point - panOffset) / zoomScale }
    func viewPoint(from point: SIMD2<Float>) -> SIMD2<Float> { point * zoomScale + panOffset }
    func pan(by delta: SIMD2<Float>) { panOffset += delta; rebuildGeometry() }
    func zoom(by factor: Float, around point: SIMD2<Float>) { let c = canvasPoint(from: point); zoomScale = min(max(zoomScale * factor, 0.25), 4); panOffset = point - c * zoomScale; rebuildGeometry() }
    func resetZoom(centeredIn size: CGSize) { zoomScale = 1; panOffset = SIMD2(Float(size.width / 2), Float(size.height / 2)); rebuildGeometry() }

    func exportPageState() -> CanvasPageState { CanvasPageState(strokes: committedStrokes.map { CanvasStroke(id: $0.id, points: $0.points, style: $0.style, rotation: $0.rotation) }, objects: objectStore.exportObjects()) }
    func importPageState(_ state: CanvasPageState) { committedStrokes = state.strokes.map { StoredStroke(id: $0.id, points: $0.points, style: $0.style, rotation: $0.rotation) }; objectStore.importObjects(state.objects); activeStroke = []; selectedStrokeIndices = []; selectedObjectIDs = []; customRotationCenter = nil; undoStack = []; redoStack = []; transactionStart = nil; rebuildGeometry() }
    func beginHistoryTransaction() { if transactionStart == nil { transactionStart = captureState() } }
    func endHistoryTransaction() { guard let before = transactionStart else { return }; transactionStart = nil; let after = captureState(); if before.strokes != after.strokes || before.objects != after.objects || before.selection != after.selection || before.objectSelection != after.objectSelection { undoStack.append(before); redoStack.removeAll() } }
    private func recordMutation() { guard transactionStart == nil else { return }; undoStack.append(captureState()); redoStack.removeAll() }
    private func captureState() -> HistoryState { HistoryState(strokes: committedStrokes, objects: objectStore.exportObjects(), selection: selectedStrokeIndices, objectSelection: selectedObjectIDs) }
    private func restore(_ state: HistoryState) { committedStrokes = state.strokes; objectStore.importObjects(state.objects); selectedStrokeIndices = state.selection.filter { committedStrokes.indices.contains($0) }; selectedObjectIDs = state.objectSelection.filter { objectStore.object(with: $0) != nil }; customRotationCenter = nil; rebuildGeometry() }
    func undo() { guard let state = undoStack.popLast() else { return }; redoStack.append(captureState()); restore(state) }
    func redo() { guard let state = redoStack.popLast() else { return }; undoStack.append(captureState()); restore(state) }

    func commitStroke(_ points: [InkPoint]) { guard points.count >= 2 else { activeStroke = []; rebuildGeometry(); return }; recordMutation(); committedStrokes.append(StoredStroke(id: UUID(), points: points, style: penStyle)); selectedStrokeIndices = []; selectedObjectIDs = []; activeStroke = []; rebuildGeometry() }
    func commitLine(from start: SIMD2<Float>, to end: SIMD2<Float>) { recordMutation(); let style = GraphicObject.Style(strokeColor: penStyle.color, strokeWidth: penStyle.width, opacity: penStyle.opacity, lineStyle: penStyle.lineStyle, fillEnabled: false, fillColor: .black, fillOpacity: 0); _ = objectStore.addLine(from: CGPoint(x: CGFloat(start.x), y: CGFloat(start.y)), to: CGPoint(x: CGFloat(end.x), y: CGFloat(end.y)), style: style); selectedStrokeIndices = []; selectedObjectIDs = []; activeStroke = []; rebuildGeometry() }
    func commitPolygon(points: [CGPoint]) { guard points.count >= 3 else { return }; recordMutation(); let style = GraphicObject.Style(strokeColor: penStyle.color, strokeWidth: penStyle.width, opacity: penStyle.opacity, lineStyle: penStyle.lineStyle, fillEnabled: false, fillColor: .black, fillOpacity: 0); _ = objectStore.addPolygon(points: points, style: style); selectedStrokeIndices = []; selectedObjectIDs = []; activeStroke = []; rebuildGeometry() }

    @discardableResult func selectObject(at point: SIMD2<Float>, tolerance: Float = 10) -> Bool { let p = canvasPoint(from: point); guard let id = objectStore.hitTest(at: CGPoint(x: CGFloat(p.x), y: CGFloat(p.y)), tolerance: CGFloat(tolerance / zoomScale)) else { clearSelection(); return false }; selectedObjectIDs = [id]; selectedStrokeIndices = []; customRotationCenter = nil; rebuildGeometry(); return true }
    @discardableResult func toggleObject(at point: SIMD2<Float>, tolerance: Float = 10) -> Bool { let p = canvasPoint(from: point); guard let id = objectStore.hitTest(at: CGPoint(x: CGFloat(p.x), y: CGFloat(p.y)), tolerance: CGFloat(tolerance / zoomScale)) else { return false }; if selectedObjectIDs.contains(id) { selectedObjectIDs.removeAll { $0 == id } } else { selectedObjectIDs.append(id) }; selectedStrokeIndices = []; rebuildGeometry(); return true }
    @discardableResult func selectObjects(in viewRect: CGRect, fullyContained: Bool = false) -> Int { let a = canvasPoint(from: SIMD2(Float(viewRect.minX), Float(viewRect.minY))); let b = canvasPoint(from: SIMD2(Float(viewRect.maxX), Float(viewRect.maxY))); let r = CGRect(x: CGFloat(min(a.x,b.x)), y: CGFloat(min(a.y,b.y)), width: CGFloat(abs(a.x-b.x)), height: CGFloat(abs(a.y-b.y))); selectedObjectIDs = objectStore.objectsIntersecting(r, fullyContained: fullyContained); selectedStrokeIndices = []; rebuildGeometry(); return selectedObjectIDs.count }
    @discardableResult func lineEndpoint(at point: SIMD2<Float>, tolerance: Float = 14) -> (id: UUID, endpoint: Int)? { let p = canvasPoint(from: point); guard let hit = objectStore.nearestLineEndpoint(to: CGPoint(x: CGFloat(p.x), y: CGFloat(p.y)), tolerance: CGFloat(tolerance / zoomScale)) else { return nil }; return (hit.id, hit.endpoint) }
    @discardableResult func moveSelectedLineEndpoint(id: UUID, endpoint: Int, to point: SIMD2<Float>) -> Bool { let p = canvasPoint(from: point); guard objectStore.moveLineEndpoint(id: id, endpoint: endpoint, to: CGPoint(x: CGFloat(p.x), y: CGFloat(p.y))) else { return false }; rebuildGeometry(); return true }
    @discardableResult func polygonVertex(at point: SIMD2<Float>, tolerance: Float = 14) -> (id: UUID, vertexIndex: Int)? {
        guard selectedObjectIDs.count == 1,
              let selectedID = selectedObjectIDs.first,
              let object = objectStore.object(with: selectedID),
              object.kind == .polygon else { return nil }
        let p = canvasPoint(from: point)
        guard let hit = objectStore.nearestPolygonVertex(to: CGPoint(x: CGFloat(p.x), y: CGFloat(p.y)), tolerance: CGFloat(tolerance / zoomScale)) else { return nil }
        guard hit.id == selectedID else { return nil }
        return (hit.id, hit.index)
    }
    @discardableResult func moveSelectedPolygonVertex(id: UUID, vertexIndex: Int, to point: SIMD2<Float>) -> Bool {
        guard selectedObjectIDs.count == 1, selectedObjectIDs.first == id else { return false }
        let p = canvasPoint(from: point)
        guard objectStore.movePolygonVertex(id: id, index: vertexIndex, to: CGPoint(x: CGFloat(p.x), y: CGFloat(p.y))) else { return false }
        rebuildGeometry()
        return true
    }
    func deleteSelectedObjects() { guard !selectedObjectIDs.isEmpty else { return }; recordMutation(); for id in selectedObjectIDs { objectStore.remove(id: id) }; selectedObjectIDs = []; rebuildGeometry() }
    @discardableResult func eraseObjectsByScribble(_ path: [SIMD2<Float>], tolerance: Float = 12) -> Bool { let p = path.map { let q = canvasPoint(from: $0); return CGPoint(x: CGFloat(q.x), y: CGFloat(q.y)) }; let ids = objectStore.eraseByScribble(p, tolerance: CGFloat(tolerance / zoomScale)); guard !ids.isEmpty else { return false }; selectedObjectIDs.removeAll { ids.contains($0) }; rebuildGeometry(); return true }

    @discardableResult func selectStroke(at point: SIMD2<Float>, tolerance: Float = 10) -> Bool { let p = canvasPoint(from: point); var hit: Int?; var best = tolerance / zoomScale; for i in committedStrokes.indices.reversed() { let s = committedStrokes[i].points; guard s.count > 1 else { continue }; for j in 0..<(s.count-1) { let d = distance(p, SIMD2(s[j].x,s[j].y), SIMD2(s[j+1].x,s[j+1].y)); if d <= best { best = d; hit = i; break } } }; selectedStrokeIndices = hit.map { [$0] } ?? []; selectedObjectIDs = []; rebuildGeometry(); return hit != nil }
    @discardableResult func toggleStroke(at point: SIMD2<Float>, tolerance: Float = 10) -> Bool { selectStroke(at: point, tolerance: tolerance) }
    @discardableResult func selectStrokes(in viewRect: CGRect, fullyContained: Bool = false) -> Int { let a = canvasPoint(from: SIMD2(Float(viewRect.minX),Float(viewRect.minY))); let b = canvasPoint(from: SIMD2(Float(viewRect.maxX),Float(viewRect.maxY))); let r = CGRect(x: CGFloat(min(a.x,b.x)), y: CGFloat(min(a.y,b.y)), width: CGFloat(abs(a.x-b.x)), height: CGFloat(abs(a.y-b.y))); let result = committedStrokes.indices.filter { guard let b = bounds(committedStrokes[$0].points) else { return false }; return fullyContained ? r.contains(b) : r.intersects(b) }; selectedStrokeIndices = Array(result); selectedObjectIDs = []; rebuildGeometry(); return result.count }

    /// Selects both structured objects and freehand strokes touched by a closed lasso.
    /// The lasso itself is supplied in view coordinates; content is tested in canvas coordinates.
    @discardableResult func selectLasso(in viewPoints: [SIMD2<Float>]) -> Int {
        guard viewPoints.count >= 3 else { clearSelection(); return 0 }
        let lasso = viewPoints.map { p in
            let c = canvasPoint(from: p)
            return CGPoint(x: CGFloat(c.x), y: CGFloat(c.y))
        }
        selectedObjectIDs = objectStore.objectsIntersectingLasso(lasso)
        selectedStrokeIndices = committedStrokes.indices.filter { strokeIntersectsLasso(committedStrokes[$0].points, lasso) }
        customRotationCenter = nil
        rebuildGeometry()
        return selectionCount
    }

    func clearSelection() { selectedStrokeIndices = []; selectedObjectIDs = []; customRotationCenter = nil; rebuildGeometry() }

    func selectionBounds() -> CGRect? { var result: CGRect?; for i in selectedStrokeIndices { if let b = bounds(committedStrokes[i].points) { result = result?.union(b) ?? b } }; for id in selectedObjectIDs { if let b = objectStore.bounds(of: id) { result = result?.union(b) ?? b } }; return result?.insetBy(dx: -8, dy: -8) }
    func selectionBoundsInView() -> CGRect? { guard let r = selectionBounds() else { return nil }; let a = viewPoint(from: SIMD2(Float(r.minX),Float(r.minY))); let b = viewPoint(from: SIMD2(Float(r.maxX),Float(r.maxY))); return CGRect(x: CGFloat(min(a.x,b.x)), y: CGFloat(min(a.y,b.y)), width: CGFloat(abs(b.x-a.x)), height: CGFloat(abs(b.y-a.y))) }
    func selectionCenter() -> SIMD2<Float>? { guard let r = selectionBounds() else { return nil }; return SIMD2(Float(r.midX), Float(r.midY)) }
    func setRotationCenter(to point: SIMD2<Float>) { customRotationCenter = canvasPoint(from: point); rebuildGeometry() }
    func rotationCenterViewPoint() -> SIMD2<Float>? { guard let c = customRotationCenter ?? selectionCenter() else { return nil }; return viewPoint(from: c) }
    func selectionHandle(at point: SIMD2<Float>, tolerance: Float = 10) -> SelectionHandle? { guard let r = selectionBounds() else { return nil }; let p = canvasPoint(from: point); let t = tolerance / zoomScale; let h:[(SelectionHandle,SIMD2<Float>)] = [(.topLeft,SIMD2(Float(r.minX),Float(r.minY))),(.topRight,SIMD2(Float(r.maxX),Float(r.minY))),(.bottomLeft,SIMD2(Float(r.minX),Float(r.maxY))),(.bottomRight,SIMD2(Float(r.maxX),Float(r.maxY)))]; return h.first { simd_distance(p,$0.1) <= t }?.0 }
    func rotationHandle(at point: SIMD2<Float>, tolerance: Float = 12) -> Bool { guard let r = selectionBounds() else { return false }; let p = canvasPoint(from: point); return simd_distance(p,SIMD2(Float(r.midX),Float(r.minY-28))) <= tolerance / zoomScale }
    func rotationCenterHandle(at point: SIMD2<Float>, tolerance: Float = 12) -> Bool { guard customRotationCenter != nil, let c = rotationCenterViewPoint() else { return false }; return simd_distance(point,c) <= tolerance }

    func resizeSelected(handle: SelectionHandle, to point: SIMD2<Float>) { guard !selectedStrokeIndices.isEmpty, selectedObjectIDs.isEmpty, let r = selectionBounds() else { return }; let p = canvasPoint(from: point); let anchor:SIMD2<Float>; switch handle { case .topLeft: anchor=SIMD2(Float(r.maxX),Float(r.maxY)); case .topRight: anchor=SIMD2(Float(r.minX),Float(r.maxY)); case .bottomLeft: anchor=SIMD2(Float(r.maxX),Float(r.minY)); case .bottomRight: anchor=SIMD2(Float(r.minX),Float(r.minY)) }; let sx=max(abs(p.x-anchor.x),1)/max(Float(r.width),1); let sy=max(abs(p.y-anchor.y),1)/max(Float(r.height),1); for i in selectedStrokeIndices { committedStrokes[i].points=committedStrokes[i].points.map { InkPoint(x:anchor.x+($0.x-anchor.x)*sx,y:anchor.y+($0.y-anchor.y)*sy,pressure:$0.pressure) } }; rebuildGeometry() }
    func scaleSelected(by factor: Float) { guard factor > 0, let c = selectionCenter() else { return }; if !selectedObjectIDs.isEmpty { for id in selectedObjectIDs { guard let o=objectStore.object(with:id) else { continue }; let s=o.transform.scale; _=objectStore.transform(id:id,scale:CGSize(width:s.width*CGFloat(factor),height:s.height*CGFloat(factor))) } } else { for i in selectedStrokeIndices { committedStrokes[i].points=committedStrokes[i].points.map { InkPoint(x:c.x+($0.x-c.x)*factor,y:c.y+($0.y-c.y)*factor,pressure:$0.pressure) } } }; rebuildGeometry() }
    func moveSelected(by delta: SIMD2<Float>) { if !selectedObjectIDs.isEmpty { for id in selectedObjectIDs { guard let o=objectStore.object(with:id) else { continue }; let p=o.transform.position; _=objectStore.transform(id:id,position:CGPoint(x:p.x+CGFloat(delta.x),y:p.y+CGFloat(delta.y))) } } else { for i in selectedStrokeIndices { committedStrokes[i].points=committedStrokes[i].points.map { InkPoint(x:$0.x+delta.x,y:$0.y+delta.y,pressure:$0.pressure) } } }; rebuildGeometry() }
    func rotateSelected(to point: SIMD2<Float>, from previous: SIMD2<Float>) { guard let c=customRotationCenter ?? selectionCenter() else { return }; let p=canvasPoint(from:point),q=canvasPoint(from:previous); rotateSelected(by:atan2(p.y-c.y,p.x-c.x)-atan2(q.y-c.y,q.x-c.x),center:c) }
    func setSelectedRotationDegrees(_ degrees: Double) { guard let c=selectionCenter() else { return }; rotateSelected(by:Float(degrees-selectedRotationDegrees)*Float.pi/180,center:c) }
    private func rotateSelected(by d:Float,center c:SIMD2<Float>) { if !selectedObjectIDs.isEmpty { for id in selectedObjectIDs { guard let o=objectStore.object(with:id) else { continue }; _=objectStore.transform(id:id,rotation:o.transform.rotation+CGFloat(d)) } } else { let co=cos(d),si=sin(d); for i in selectedStrokeIndices { committedStrokes[i].points=committedStrokes[i].points.map { let v=SIMD2($0.x,$0.y)-c; return InkPoint(x:v.x*co-v.y*si+c.x,y:v.x*si+v.y*co+c.y,pressure:$0.pressure) }; committedStrokes[i].rotation += d } }; rebuildGeometry() }
    func reflectSelected(horizontal: Bool) { guard let c=selectionCenter() else { return }; if !selectedObjectIDs.isEmpty { for id in selectedObjectIDs { guard let o=objectStore.object(with:id) else { continue }; let s=o.transform.scale; _=objectStore.transform(id:id,scale:CGSize(width:horizontal ? -s.width:s.width,height:horizontal ? s.height:-s.height)) } } else { for i in selectedStrokeIndices { committedStrokes[i].points=committedStrokes[i].points.map { horizontal ? InkPoint(x:2*c.x-$0.x,y:$0.y,pressure:$0.pressure) : InkPoint(x:$0.x,y:2*c.y-$0.y,pressure:$0.pressure) } } }; rebuildGeometry() }
    func deleteSelected() { if !selectedObjectIDs.isEmpty { deleteSelectedObjects(); return }; guard !selectedStrokeIndices.isEmpty else { return }; recordMutation(); for i in selectedStrokeIndices.sorted(by:>) { if committedStrokes.indices.contains(i) { committedStrokes.remove(at:i) } }; selectedStrokeIndices=[]; rebuildGeometry() }

    func mtkView(_ view: MTKView, drawableSizeWillChange size: CGSize) { updateUniformBuffer(for:size) }
    func draw(in view: MTKView) { guard let pass=view.currentRenderPassDescriptor,let drawable=view.currentDrawable,let cb=commandQueue.makeCommandBuffer(),let encoder=cb.makeRenderCommandEncoder(descriptor:pass) else { return }; pass.colorAttachments[0].clearColor=MTLClearColor(red:Double(backgroundColor.x),green:Double(backgroundColor.y),blue:Double(backgroundColor.z),alpha:1); updateUniformBuffer(for:view.drawableSize); encoder.setRenderPipelineState(pipelineState); if let b=vertexBuffer { encoder.setVertexBuffer(b,offset:0,index:0) }; if let b=uniformBuffer { encoder.setVertexBuffer(b,offset:0,index:1) }; if !vertices.isEmpty { encoder.drawPrimitives(type:.triangle,vertexStart:0,vertexCount:vertices.count) }; encoder.endEncoding(); cb.present(drawable); cb.commit() }

    private func rebuildGeometry() { var out:[InkVertex]=[]; appendBackground(to:&out); for s in committedStrokes { appendStroke(s.points,style:s.style,to:&out) }; appendObjects(to:&out); if let r=selectionBounds() { appendSelection(r,to:&out) }; if !activeStroke.isEmpty { appendStroke(activeStroke,style:penStyle,to:&out) }; vertices=out; vertexBuffer=vertices.isEmpty ? nil : device.makeBuffer(bytes:vertices,length:vertices.count*MemoryLayout<InkVertex>.stride,options:.storageModeShared) }
    private func appendObjects(to out:inout[InkVertex]) { for o in objectStore.objects { let p=objectStore.transformedPoints(of:o); guard p.count>=2 else { continue }; let color=metalColor(o.style); if o.kind == .line || o.kind == .arrow { appendLine(viewPoint(from:SIMD2(Float(p[0].x),Float(p[0].y))),viewPoint(from:SIMD2(Float(p[1].x),Float(p[1].y))),width:Float(max(0.5,o.style.strokeWidth)),color:color,to:&out) } else if o.kind == .polygon { let limit = p.count; if limit >= 2 { for i in 0..<limit { let j=(i+1)%limit; appendLine(viewPoint(from:SIMD2(Float(p[i].x),Float(p[i].y))),viewPoint(from:SIMD2(Float(p[j].x),Float(p[j].y))),width:Float(max(0.5,o.style.strokeWidth)),color:color,to:&out) } } } } }
    private func appendBackground(to out:inout[InkVertex]) { guard backgroundPattern != 0 else { return }; let color=SIMD4<Float>(0.82,0.84,0.88,0.55); let step:Float=backgroundPattern == 3 ? 24:32; let e:Float=2000; if backgroundPattern == 1 { var y:Float = -e; while y<=e { appendLine(viewPoint(from:SIMD2(-e,y)),viewPoint(from:SIMD2(e,y)),width:0.55,color:color,to:&out); y+=step } } else { var x:Float = -e; while x<=e { appendLine(viewPoint(from:SIMD2(x,-e)),viewPoint(from:SIMD2(x,e)),width:0.45,color:color,to:&out); x+=step }; var y:Float = -e; while y<=e { appendLine(viewPoint(from:SIMD2(-e,y)),viewPoint(from:SIMD2(e,y)),width:0.45,color:color,to:&out); y+=step } } }
    private func appendStroke(_ s:[InkPoint],style:PenStyle,to out:inout[InkVertex]) { guard !s.isEmpty else { return }; let color=metalColor(style); if s.count>1 { for i in 0..<(s.count-1) { let p=s[i],q=s[i+1],dx=q.x-p.x,dy=q.y-p.y,l=max(sqrt(dx*dx+dy*dy),0.001),nx=-dy/l,ny=dx/l,w0=strokeWidth(p.pressure,style),w1=strokeWidth(q.pressure,style); let a=viewPoint(from:SIMD2(p.x+nx*w0,p.y+ny*w0)),b=viewPoint(from:SIMD2(p.x-nx*w0,p.y-ny*w0)),c=viewPoint(from:SIMD2(q.x+nx*w1,q.y+ny*w1)),d=viewPoint(from:SIMD2(q.x-nx*w1,q.y-ny*w1)); triangle(a,b,c,color:color,to:&out); triangle(c,b,d,color:color,to:&out) } }; for p in s { disk(viewPoint(from:SIMD2(p.x,p.y)), strokeWidth(p.pressure,style), color, to:&out) } }
    private func appendSelection(_ r:CGRect,to out:inout[InkVertex]) { let c=SIMD4<Float>(0.1,0.45,1,0.75),a=viewPoint(from:SIMD2(Float(r.minX),Float(r.minY))),b=viewPoint(from:SIMD2(Float(r.maxX),Float(r.maxY))),p0=SIMD2(a.x,a.y),p1=SIMD2(b.x,a.y),p2=SIMD2(b.x,b.y),p3=SIMD2(a.x,b.y); appendLine(p0,p1,width:1.5,color:c,to:&out);appendLine(p1,p2,width:1.5,color:c,to:&out);appendLine(p2,p3,width:1.5,color:c,to:&out);appendLine(p3,p0,width:1.5,color:c,to:&out);for p in [p0,p1,p2,p3] { disk(p,5,c,to:&out) };let rot=SIMD2((a.x+b.x)/2,a.y-28);appendLine(SIMD2((a.x+b.x)/2,a.y),rot,width:1,color:c,to:&out);disk(rot,7,c,to:&out);if let rc=customRotationCenter { disk(viewPoint(from:rc),7,SIMD4<Float>(0.95,0.55,0.05,1),to:&out) };if selectedObjectIDs.count == 1,let id=selectedObjectIDs.first,let o=objectStore.object(with:id),o.kind == .line { for q in objectStore.transformedPoints(of:o).prefix(2) { disk(viewPoint(from:SIMD2(Float(q.x),Float(q.y))),7,SIMD4<Float>(0.95,0.55,0.05,1),to:&out) } } else if selectedObjectIDs.count == 1,let id=selectedObjectIDs.first,let o=objectStore.object(with:id),o.kind == .polygon { for q in objectStore.transformedPoints(of:o) { disk(viewPoint(from:SIMD2(Float(q.x),Float(q.y))),6,c,to:&out) } } }
    private func bounds(_ p:[InkPoint])->CGRect? { guard let f=p.first else { return nil }; var x0=f.x,x1=f.x,y0=f.y,y1=f.y;for q in p{x0=min(x0,q.x);x1=max(x1,q.x);y0=min(y0,q.y);y1=max(y1,q.y)};return CGRect(x:CGFloat(x0),y:CGFloat(y0),width:CGFloat(x1-x0),height:CGFloat(y1-y0)) }
    private func strokeIntersectsLasso(_ stroke:[InkPoint], _ lasso:[CGPoint])->Bool {
        guard stroke.count >= 2, lasso.count >= 3 else { return false }
        if stroke.contains(where: { pointInPolygon(CGPoint(x: CGFloat($0.x), y: CGFloat($0.y)), lasso) }) { return true }
        for pair in zip(stroke, stroke.dropFirst()) {
            let a = CGPoint(x: CGFloat(pair.0.x), y: CGFloat(pair.0.y))
            let b = CGPoint(x: CGFloat(pair.1.x), y: CGFloat(pair.1.y))
            if segmentIntersectsPolygon(a, b, lasso) { return true }
        }
        return false
    }
    private func pointInPolygon(_ p:CGPoint,_ poly:[CGPoint])->Bool { var inside=false; var j=poly.count-1; for i in poly.indices { let a=poly[i],b=poly[j]; if (a.y > p.y) != (b.y > p.y) { let d=b.y-a.y; if d != 0 { let x=(b.x-a.x)*(p.y-a.y)/d+a.x; if p.x < x { inside.toggle() } } }; j=i }; return inside }
    private func segmentIntersectsPolygon(_ a:CGPoint,_ b:CGPoint,_ polygon:[CGPoint])->Bool { if pointInPolygon(a,polygon) || pointInPolygon(b,polygon) { return true }; var edges=Array(polygon.dropFirst()); edges.append(polygon[0]); return zip(polygon,edges).contains { segmentsIntersect(a,b,$0.0,$0.1) } }
    private func segmentsIntersect(_ a:CGPoint,_ b:CGPoint,_ c:CGPoint,_ d:CGPoint)->Bool { func cross(_ p:CGPoint,_ q:CGPoint,_ r:CGPoint)->CGFloat { (q.x-p.x)*(r.y-p.y)-(q.y-p.y)*(r.x-p.x) }; let o1=cross(a,b,c),o2=cross(a,b,d),o3=cross(c,d,a),o4=cross(c,d,b); let eps:CGFloat=0.001; if abs(o1)<eps && onSegment(a,b,c){return true}; if abs(o2)<eps && onSegment(a,b,d){return true}; if abs(o3)<eps && onSegment(c,d,a){return true}; if abs(o4)<eps && onSegment(c,d,b){return true}; return (o1 > 0) != (o2 > 0) && (o3 > 0) != (o4 > 0) }
    private func onSegment(_ a:CGPoint,_ b:CGPoint,_ p:CGPoint)->Bool { p.x >= min(a.x,b.x)-0.001 && p.x <= max(a.x,b.x)+0.001 && p.y >= min(a.y,b.y)-0.001 && p.y <= max(a.y,b.y)+0.001 }
    private func appendLine(_ a:SIMD2<Float>,_ b:SIMD2<Float>,width:Float,color:SIMD4<Float>,to out:inout[InkVertex]) { let d=b-a,l=max(simd_length(d),0.001),n=SIMD2(-d.y,d.x)/l*width;triangle(a+n,a-n,b+n,color:color,to:&out);triangle(b+n,a-n,b-n,color:color,to:&out) }
    private func distance(_ p:SIMD2<Float>,_ a:SIMD2<Float>,_ b:SIMD2<Float>)->Float { let d=b-a,l=simd_length_squared(d);if l<0.0001{return simd_distance(p,a)};let t=max(0,min(1,simd_dot(p-a,d)/l));return simd_distance(p,a+d*t) }
    private func strokeWidth(_ pressure:Float,_ style:PenStyle)->Float { let p=max(0,min(1,pressure));let c=style.pressureEnabled ? pow(p,max(0.25,Float(style.pressureCurve))):0.75;return Float(style.width)*(0.45+0.75*c) }
    private func metalColor(_ style:PenStyle)->SIMD4<Float> { var c=SIMD4(Float(style.color.red),Float(style.color.green),Float(style.color.blue),Float(style.color.alpha*style.opacity));if displayInverted { let hi=max(c.x,max(c.y,c.z)),lo=min(c.x,min(c.y,c.z));if hi<0.12 || lo>0.88 { c=SIMD4(1,1,1,c.w) } };return c }
    private func metalColor(_ style:GraphicObject.Style)->SIMD4<Float> { var c=SIMD4(Float(style.strokeColor.red),Float(style.strokeColor.green),Float(style.strokeColor.blue),Float(style.strokeColor.alpha*style.opacity));if displayInverted { let hi=max(c.x,max(c.y,c.z)),lo=min(c.x,min(c.y,c.z));if hi<0.12 || lo>0.88 { c=SIMD4(1,1,1,c.w) } };return c }
    private func triangle(_ a:SIMD2<Float>,_ b:SIMD2<Float>,_ c:SIMD2<Float>,color:SIMD4<Float>,to out:inout[InkVertex]) { out.append(InkVertex(position:a,color:color));out.append(InkVertex(position:b,color:color));out.append(InkVertex(position:c,color:color)) }
    private func disk(_ center:SIMD2<Float>,_ radius:Float,_ color:SIMD4<Float>,to out:inout[InkVertex]) { let n=12;for i in 0..<n { let a=Float(i)/Float(n)*2*Float.pi,b=Float(i+1)/Float(n)*2*Float.pi;triangle(center,center+SIMD2(cos(a),sin(a))*radius,center+SIMD2(cos(b),sin(b))*radius,color:color,to:&out) } }
    private func updateUniformBuffer(for size:CGSize) { let u=Uniforms(viewportSize:SIMD2(Float(max(size.width,1)),Float(max(size.height,1))));if uniformBuffer == nil { uniformBuffer=device.makeBuffer(length:MemoryLayout<Uniforms>.stride,options:.storageModeShared) };guard let b=uniformBuffer else{return};withUnsafeBytes(of:u){if let p=$0.baseAddress{memcpy(b.contents(),p,MemoryLayout<Uniforms>.stride)}} }
}
