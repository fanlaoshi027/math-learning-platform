import Metal
import MetalKit
import simd

final class InkRenderer: NSObject, MTKViewDelegate {
    enum SelectionHandle { case topLeft, topRight, bottomLeft, bottomRight }
    private struct Uniforms { var viewportSize: SIMD2<Float> }
    private struct StoredStroke { var id: UUID; var points: [InkPoint]; var style: PenStyle; var rotation: Float = 0 }
    private struct HistoryState { var strokes: [StoredStroke]; var selection: Int? }

    private let device: any MTLDevice
    private let commandQueue: any MTLCommandQueue
    private let pipelineState: any MTLRenderPipelineState
    private var committedStrokes: [StoredStroke] = []
    private var activeStroke: [InkPoint] = []
    private var vertices: [InkVertex] = []
    private var vertexBuffer: (any MTLBuffer)?
    private var uniformBuffer: (any MTLBuffer)?
    private var penStyle = PenStyle()
    private(set) var selectedStrokeIndex: Int?
    private var backgroundColor = SIMD4<Float>(1, 1, 1, 1)
    private var displayInverted = false
    private var backgroundPattern = 0
    private var panOffset = SIMD2<Float>(0, 0)
    private var undoStack: [HistoryState] = []
    private var redoStack: [HistoryState] = []
    private var transactionStart: HistoryState?

    init?(device: any MTLDevice) {
        guard let commandQueue = device.makeCommandQueue(),
              let library = try? device.makeDefaultLibrary(bundle: Bundle.module),
              let vertexFunction = library.makeFunction(name: "inkVertex"),
              let fragmentFunction = library.makeFunction(name: "inkFragment") else { return nil }
        let descriptor = MTLRenderPipelineDescriptor()
        descriptor.vertexFunction = vertexFunction
        descriptor.fragmentFunction = fragmentFunction
        descriptor.colorAttachments[0].pixelFormat = .bgra8Unorm
        descriptor.colorAttachments[0].isBlendingEnabled = true
        descriptor.colorAttachments[0].sourceRGBBlendFactor = .sourceAlpha
        descriptor.colorAttachments[0].destinationRGBBlendFactor = .oneMinusSourceAlpha
        descriptor.colorAttachments[0].sourceAlphaBlendFactor = .sourceAlpha
        descriptor.colorAttachments[0].destinationAlphaBlendFactor = .oneMinusSourceAlpha
        guard let pipelineState = try? device.makeRenderPipelineState(descriptor: descriptor) else { return nil }
        self.device = device; self.commandQueue = commandQueue; self.pipelineState = pipelineState
        super.init()
    }

    var canUndo: Bool { !undoStack.isEmpty }
    var canRedo: Bool { !redoStack.isEmpty }
    var hasSelection: Bool { selectedStrokeIndex != nil }
    var selectedRotationDegrees: Double {
        guard let index = selectedStrokeIndex, committedStrokes.indices.contains(index) else { return 0 }
        return Double(committedStrokes[index].rotation * 180 / .pi)
    }

    func setPenStyle(_ style: PenStyle) { penStyle = style; rebuildGeometry() }
    func setBackgroundColor(_ color: SIMD4<Float>) { backgroundColor = color; rebuildGeometry() }
    func setDisplayInverted(_ inverted: Bool) { displayInverted = inverted; rebuildGeometry() }
    func setBackgroundPattern(_ pattern: Int) { backgroundPattern = pattern; rebuildGeometry() }
    func setStroke(_ points: [InkPoint]) { activeStroke = points; rebuildGeometry() }
    func canvasPoint(from viewPoint: SIMD2<Float>) -> SIMD2<Float> { viewPoint - panOffset }
    func pan(by delta: SIMD2<Float>) { panOffset += delta; rebuildGeometry() }
    func resetPan() { panOffset = .zero; rebuildGeometry() }

    func exportPageState() -> CanvasPageState {
        CanvasPageState(strokes: committedStrokes.map { CanvasStroke(id: $0.id, points: $0.points, style: $0.style, rotation: $0.rotation) })
    }

    func importPageState(_ state: CanvasPageState) {
        committedStrokes = state.strokes.map { StoredStroke(id: $0.id, points: $0.points, style: $0.style, rotation: $0.rotation) }
        activeStroke.removeAll(keepingCapacity: true); selectedStrokeIndex = nil
        undoStack.removeAll(keepingCapacity: true); redoStack.removeAll(keepingCapacity: true); transactionStart = nil
        rebuildGeometry()
    }

    func beginHistoryTransaction() {
        if transactionStart == nil { transactionStart = captureState() }
    }

    func endHistoryTransaction() {
        guard let before = transactionStart else { return }
        transactionStart = nil
        let after = captureState()
        if before.strokes != after.strokes || before.selection != after.selection {
            undoStack.append(before); redoStack.removeAll(keepingCapacity: true)
        }
    }

    private func recordMutation() {
        guard transactionStart == nil else { return }
        undoStack.append(captureState()); redoStack.removeAll(keepingCapacity: true)
    }

    private func captureState() -> HistoryState { HistoryState(strokes: committedStrokes, selection: selectedStrokeIndex) }
    private func restore(_ state: HistoryState) { committedStrokes = state.strokes; selectedStrokeIndex = state.selection; rebuildGeometry() }

    func commitStroke(_ points: [InkPoint]) {
        guard points.count >= 2 else { activeStroke.removeAll(); rebuildGeometry(); return }
        recordMutation()
        committedStrokes.append(StoredStroke(id: UUID(), points: points, style: penStyle))
        selectedStrokeIndex = nil; activeStroke.removeAll(keepingCapacity: true); rebuildGeometry()
    }

    func undo() { guard let state = undoStack.popLast() else { return }; redoStack.append(captureState()); restore(state) }
    func redo() { guard let state = redoStack.popLast() else { return }; undoStack.append(captureState()); restore(state) }

    @discardableResult
    func selectStroke(at point: SIMD2<Float>, tolerance: Float = 10) -> Bool {
        let canvas = canvasPoint(from: point)
        var bestIndex: Int?; var bestDistance = tolerance
        for index in committedStrokes.indices.reversed() {
            let stroke = committedStrokes[index].points
            guard stroke.count >= 2 else { continue }
            for segment in 0..<(stroke.count - 1) {
                let a = SIMD2<Float>(stroke[segment].x, stroke[segment].y), b = SIMD2<Float>(stroke[segment + 1].x, stroke[segment + 1].y)
                let distance = distanceFromPoint(canvas, toSegment: a, b)
                if distance <= bestDistance { bestDistance = distance; bestIndex = index; break }
            }
        }
        selectedStrokeIndex = bestIndex; rebuildGeometry(); return bestIndex != nil
    }

    func clearSelection() { selectedStrokeIndex = nil; rebuildGeometry() }

    func selectionHandle(at point: SIMD2<Float>, tolerance: Float = 10) -> SelectionHandle? {
        guard let bounds = selectedBounds() else { return nil }
        let p = canvasPoint(from: point)
        let handles: [(SelectionHandle, SIMD2<Float>)] = [(.topLeft, SIMD2(bounds.minX,bounds.minY)),(.topRight,SIMD2(bounds.maxX,bounds.minY)),(.bottomLeft,SIMD2(bounds.minX,bounds.maxY)),(.bottomRight,SIMD2(bounds.maxX,bounds.maxY))]
        return handles.first { simd_distance(p, $0.1) <= tolerance }?.0
    }

    func rotationHandle(at point: SIMD2<Float>, tolerance: Float = 12) -> Bool {
        guard let bounds = selectedBounds() else { return false }
        let p = canvasPoint(from: point); let center = SIMD2((bounds.minX+bounds.maxX)*0.5, bounds.minY-28)
        return simd_distance(p, center) <= tolerance
    }

    func resizeSelected(handle: SelectionHandle, to point: SIMD2<Float>) {
        guard let index = selectedStrokeIndex, committedStrokes.indices.contains(index), let bounds = selectedBounds() else { return }
        let p = canvasPoint(from: point); let anchor: SIMD2<Float>
        switch handle { case .topLeft: anchor = SIMD2(bounds.maxX,bounds.maxY); case .topRight: anchor = SIMD2(bounds.minX,bounds.maxY); case .bottomLeft: anchor = SIMD2(bounds.maxX,bounds.minY); case .bottomRight: anchor = SIMD2(bounds.minX,bounds.minY) }
        let oldWidth = max(bounds.maxX-bounds.minX,1), oldHeight = max(bounds.maxY-bounds.minY,1)
        let sx = max(abs(p.x-anchor.x),1)/oldWidth, sy = max(abs(p.y-anchor.y),1)/oldHeight
        committedStrokes[index].points = committedStrokes[index].points.map { InkPoint(x: anchor.x+($0.x-anchor.x)*sx, y: anchor.y+($0.y-anchor.y)*sy, pressure: $0.pressure) }
        rebuildGeometry()
    }

    func rotateSelected(to point: SIMD2<Float>, from previous: SIMD2<Float>) {
        guard let bounds = selectedBounds() else { return }
        let p = canvasPoint(from: point), q = canvasPoint(from: previous); let center = SIMD2((bounds.minX+bounds.maxX)*0.5,(bounds.minY+bounds.maxY)*0.5)
        rotateSelected(by: atan2(p.y-center.y,p.x-center.x)-atan2(q.y-center.y,q.x-center.x))
    }

    func setSelectedRotationDegrees(_ degrees: Double) {
        guard let index = selectedStrokeIndex, committedStrokes.indices.contains(index) else { return }
        rotateSelected(by: Float(degrees * .pi / 180) - committedStrokes[index].rotation)
    }

    private func rotateSelected(by delta: Float) {
        guard let index = selectedStrokeIndex, committedStrokes.indices.contains(index), let bounds = selectedBounds() else { return }
        let center = SIMD2((bounds.minX+bounds.maxX)*0.5,(bounds.minY+bounds.maxY)*0.5), c=cos(delta), s=sin(delta)
        committedStrokes[index].points = committedStrokes[index].points.map { let v=SIMD2($0.x,$0.y)-center; return InkPoint(x:v.x*c-v.y*s+center.x,y:v.x*s+v.y*c+center.y,pressure:$0.pressure) }
        committedStrokes[index].rotation += delta; rebuildGeometry()
    }

    func moveSelected(by delta: SIMD2<Float>) {
        guard let index = selectedStrokeIndex, committedStrokes.indices.contains(index) else { return }
        committedStrokes[index].points = committedStrokes[index].points.map { InkPoint(x:$0.x+delta.x,y:$0.y+delta.y,pressure:$0.pressure) }; rebuildGeometry()
    }

    func deleteSelected() {
        guard let index = selectedStrokeIndex, committedStrokes.indices.contains(index) else { return }
        recordMutation(); committedStrokes.remove(at:index); selectedStrokeIndex=nil; rebuildGeometry()
    }

    func mtkView(_ view: MTKView, drawableSizeWillChange size: CGSize) { updateUniformBuffer(for:size) }
    func draw(in view: MTKView) {
        guard let descriptor=view.currentRenderPassDescriptor, let drawable=view.currentDrawable, let commandBuffer=commandQueue.makeCommandBuffer(), let encoder=commandBuffer.makeRenderCommandEncoder(descriptor:descriptor) else { return }
        descriptor.colorAttachments[0].clearColor=MTLClearColor(red:Double(backgroundColor.x),green:Double(backgroundColor.y),blue:Double(backgroundColor.z),alpha:1)
        updateUniformBuffer(for:view.drawableSize); encoder.setRenderPipelineState(pipelineState)
        if let vertexBuffer { encoder.setVertexBuffer(vertexBuffer,offset:0,index:0) }; if let uniformBuffer { encoder.setVertexBuffer(uniformBuffer,offset:0,index:1) }
        if !vertices.isEmpty { encoder.drawPrimitives(type:.triangle,vertexStart:0,vertexCount:vertices.count) }
        encoder.endEncoding(); commandBuffer.present(drawable); commandBuffer.commit()
    }

    private func rebuildGeometry() {
        var output:[InkVertex]=[]
        appendBackgroundPattern(to:&output)
        for index in committedStrokes.indices { let stroke=committedStrokes[index]; appendStrokeGeometry(stroke.points,style:stroke.style,to:&output); if selectedStrokeIndex==index { appendSelectionBounds(stroke.points,color:SIMD4(0.1,0.45,1,0.75),to:&output) } }
        if !activeStroke.isEmpty { appendStrokeGeometry(activeStroke,style:penStyle,to:&output) }
        vertices=output; vertexBuffer=vertices.isEmpty ? nil : device.makeBuffer(bytes:vertices,length:vertices.count*MemoryLayout<InkVertex>.stride,options:.storageModeShared)
    }

    private func appendBackgroundPattern(to output: inout [InkVertex]) {
        guard backgroundPattern != 0 else { return }
        let color = displayInverted ? SIMD4<Float>(0.24,0.24,0.24,0.55) : SIMD4<Float>(0.82,0.84,0.88,0.55)
        let step: Float = backgroundPattern == 3 ? 24 : 32
        let extent: Float = 4000
        if backgroundPattern == 1 { var y:Float = -extent; while y <= extent { appendLineQuad(SIMD2(-extent,y),SIMD2(extent,y),width:0.55,color:color,to:output); y += step } }
        else if backgroundPattern == 3 { var y:Float = -extent; while y <= extent { var x:Float = -extent; while x <= extent { appendDisk(center:SIMD2(x,y),radius:1.1,color:color,to:output); x += step }; y += step } }
        else { var x:Float = -extent; while x <= extent { appendLineQuad(SIMD2(x,-extent),SIMD2(x,extent),width:0.45,color:color,to:output); x += step }; var y:Float = -extent; while y <= extent { appendLineQuad(SIMD2(-extent,y),SIMD2(extent,y),width:0.45,color:color,to:output); y += step } }
    }

    private func selectedBounds() -> (minX:Float,maxX:Float,minY:Float,maxY:Float)? { guard let i=selectedStrokeIndex,committedStrokes.indices.contains(i),let first=committedStrokes[i].points.first else{return nil}; var minX=first.x,maxX=first.x,minY=first.y,maxY=first.y; for p in committedStrokes[i].points { minX=min(minX,p.x);maxX=max(maxX,p.x);minY=min(minY,p.y);maxY=max(maxY,p.y) }; return(minX,maxX,minY,maxY) }
    private func appendStrokeGeometry(_ stroke:[InkPoint],style:PenStyle,to output:inout [InkVertex]) { guard let first=stroke.first else{return}; let color=metalColor(style); for i in 0..<(max(stroke.count-1,0)) { let p0=stroke[i],p1=stroke[i+1],dx=p1.x-p0.x,dy=p1.y-p0.y,length=max(sqrt(dx*dx+dy*dy),0.001),nx=-dy/length,ny=dx/length,w0=strokeWidth(p0.pressure,style:style),w1=strokeWidth(p1.pressure,style:style); let a=SIMD2(p0.x+nx*w0+panOffset.x,p0.y+ny*w0+panOffset.y),b=SIMD2(p0.x-nx*w0+panOffset.x,p0.y-ny*w0+panOffset.y),c=SIMD2(p1.x+nx*w1+panOffset.x,p1.y+ny*w1+panOffset.y),d=SIMD2(p1.x-nx*w1+panOffset.x,p1.y-ny*w1+panOffset.y); appendTriangle(a,b,c,color:color,to:&output);appendTriangle(c,b,d,color:color,to:&output) }; appendDisk(center:SIMD2(first.x+panOffset.x,first.y+panOffset.y),radius:strokeWidth(first.pressure,style:style),color:color,to:&output); if let last=stroke.last { appendDisk(center:SIMD2(last.x+panOffset.x,last.y+panOffset.y),radius:strokeWidth(last.pressure,style:style),color:color,to:&output) }; if stroke.count>2 { for p in stroke.dropFirst().dropLast(){appendDisk(center:SIMD2(p.x+panOffset.x,p.y+panOffset.y),radius:strokeWidth(p.pressure,style:style),color:color,to:&output)} } }
    private func appendSelectionBounds(_ stroke:[InkPoint],color:SIMD4<Float>,to output:inout[InkVertex]) { guard let b=selectedBounds() else{return}; let pad:Float=8,x0=b.minX-pad+panOffset.x,x1=b.maxX+pad+panOffset.x,y0=b.minY-pad+panOffset.y,y1=b.maxY+pad+panOffset.y,a=SIMD2(x0,y0),bb=SIMD2(x1,y0),c=SIMD2(x1,y1),d=SIMD2(x0,y1); appendLineQuad(a,bb,width:1.5,color:color,to:&output);appendLineQuad(bb,c,width:1.5,color:color,to:&output);appendLineQuad(c,d,width:1.5,color:color,to:&output);appendLineQuad(d,a,width:1.5,color:color,to:&output); for p in [a,bb,c,d]{appendDisk(center:p,radius:5,color:color,to:&output);appendDisk(center:p,radius:2.5,color:SIMD4(1,1,1,1),to:&output)}; let r=SIMD2((x0+x1)*0.5,y0-28);appendLineQuad(SIMD2((x0+x1)*0.5,y0),r,width:1,color:color,to:&output);appendDisk(center:r,radius:7,color:color,to:&output);appendDisk(center:r,radius:3,color:SIMD4(1,1,1,1),to:&output) }
    private func appendLineQuad(_ a:SIMD2<Float>,_ b:SIMD2<Float>,width:Float,color:SIMD4<Float>,to output:inout[InkVertex]) { let d=b-a,l=max(simd_length(d),0.001),n=SIMD2(-d.y/l,d.x/l)*width;appendTriangle(a+n,a-n,b+n,color:color,to:&output);appendTriangle(b+n,a-n,b-n,color:color,to:&output) }
    private func distanceFromPoint(_ p:SIMD2<Float>,toSegment a:SIMD2<Float>,_ b:SIMD2<Float>)->Float { let ab=b-a,l=simd_length_squared(ab);if l<0.0001{return simd_distance(p,a)};let t=max(0,min(1,simd_dot(p-a,ab)/l));return simd_distance(p,a+ab*t) }
    private func strokeWidth(_ pressure:Float,style:PenStyle)->Float { let p=max(0,min(1,pressure)),c=style.pressureEnabled ? pow(p,max(0.25,Float(style.pressureCurve))):0.75;return Float(style.width)*(0.45+0.75*c) }
    private func metalColor(_ style:PenStyle)->SIMD4<Float> { var c=SIMD4(Float(style.color.red),Float(style.color.green),Float(style.color.blue),Float(style.color.alpha*style.opacity));if displayInverted { let hi=max(c.x,max(c.y,c.z)),lo=min(c.x,min(c.y,c.z));if hi<0.12 || lo>0.88 {c=SIMD4(1,1,1,c.w)} };return c }
    private func appendTriangle(_ a:SIMD2<Float>,_ b:SIMD2<Float>,_ c:SIMD2<Float>,color:SIMD4<Float>,to output:inout[InkVertex]) { output.append(InkVertex(position:a,color:color));output.append(InkVertex(position:b,color:color));output.append(InkVertex(position:c,color:color)) }
    private func appendDisk(center:SIMD2<Float>,radius:Float,color:SIMD4<Float>,to output:inout[InkVertex]) { let n=12;for i in 0..<n {let a=Float(i)/Float(n)*2*.pi,b=Float(i+1)/Float(n)*2*.pi;appendTriangle(center,center+SIMD2(cos(a),sin(a))*radius,center+SIMD2(cos(b),sin(b))*radius,color:color,to:&output)} }
    private func updateUniformBuffer(for size:CGSize) { let u=Uniforms(viewportSize:SIMD2(Float(max(size.width,1)),Float(max(size.height,1))));if uniformBuffer==nil{uniformBuffer=device.makeBuffer(length:MemoryLayout<Uniforms>.stride,options:.storageModeShared)};guard let b=uniformBuffer else{return};withUnsafeBytes(of:u){memcpy(b.contents(),$0.baseAddress!,MemoryLayout<Uniforms>.stride)} }
}
