import Metal
import MetalKit
import simd

final class InkRenderer: NSObject, MTKViewDelegate {
    enum SelectionHandle { case topLeft, topRight, bottomLeft, bottomRight }

    private struct Uniforms { var viewportSize: SIMD2<Float> }
    private struct StoredStroke: Equatable {
        var id: UUID
        var points: [InkPoint]
        var style: PenStyle
        var rotation: Float = 0
    }
    private struct HistoryState {
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
        if selectedObjectIDs.count == 1,
           let id = selectedObjectIDs.first,
           let object = objectStore.object(with: id) {
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
    func zoom(by factor: Float, around point: SIMD2<Float>) {
        let c = canvasPoint(from: point)
        zoomScale = min(max(zoomScale * factor, 0.25), 4)
        panOffset = point - c * zoomScale
        rebuildGeometry()
    }
    func resetZoom(centeredIn size: CGSize) {
        zoomScale = 1
        panOffset = SIMD2(Float(size.width / 2), Float(size.height / 2))
        rebuildGeometry()
    }

    func exportPageState() -> CanvasPageState {
        CanvasPageState(
            strokes: committedStrokes.map {
                CanvasStroke(id: $0.id, points: $0.points, style: $0.style, rotation: $0.rotation)
            },
            objects: objectStore.exportObjects()
        )
    }

    func importPageState(_ state: CanvasPageState) {
        committedStrokes = state.strokes.map {
            StoredStroke(id: $0.id, points: $0.points, style: $0.style, rotation: $0.rotation)
        }
        objectStore.importObjects(state.objects)
        activeStroke = []
        selectedStrokeIndices = []
        selectedObjectIDs = []
        customRotationCenter = nil
        undoStack = []
        redoStack = []
        transactionStart = nil
        rebuildGeometry()
    }

    func beginHistoryTransaction() {
        if transactionStart == nil { transactionStart = captureState() }
    }

    func endHistoryTransaction() {
        guard let before = transactionStart else { return }
        transactionStart = nil
        let after = captureState()
        if before.strokes != after.strokes || before.objects != after.objects ||
            before.selection != after.selection || before.objectSelection != after.objectSelection {
            undoStack.append(before)
            redoStack.removeAll()
        }
    }

    private func recordMutation() {
        guard transactionStart == nil else { return }
        undoStack.append(captureState())
        redoStack.removeAll()
    }

    private func captureState() -> HistoryState {
        HistoryState(
            strokes: committedStrokes,
            objects: objectStore.exportObjects(),
            selection: selectedStrokeIndices,
            objectSelection: selectedObjectIDs
        )
    }

    private func restore(_ state: HistoryState) {
        committedStrokes = state.strokes
        objectStore.importObjects(state.objects)
        selectedStrokeIndices = state.selection.filter { committedStrokes.indices.contains($0) }
        selectedObjectIDs = state.objectSelection.filter { objectStore.object(with: $0) != nil }
        customRotationCenter = nil
        rebuildGeometry()
    }

    func undo() {
        guard let state = undoStack.popLast() else { return }
        redoStack.append(captureState())
        restore(state)
    }

    func redo() {
        guard let state = redoStack.popLast() else { return }
        undoStack.append(captureState())
        restore(state)
    }

    func commitStroke(_ points: [InkPoint]) {
        guard points.count >= 2 else { activeStroke = []; rebuildGeometry(); return }
        recordMutation()
        committedStrokes.append(StoredStroke(id: UUID(), points: points, style: penStyle))
        selectedStrokeIndices = []
        selectedObjectIDs = []
        activeStroke = []
        rebuildGeometry()
    }

    func commitLine(from start: SIMD2<Float>, to end: SIMD2<Float>) {
        recordMutation()
        let style = GraphicObject.Style(
            strokeColor: penStyle.color,
            strokeWidth: penStyle.width,
            opacity: penStyle.opacity,
            lineStyle: penStyle.lineStyle,
            fillEnabled: false,
            fillColor: .black,
            fillOpacity: 0
        )
        _ = objectStore.addLine(
            from: CGPoint(x: CGFloat(start.x), y: CGFloat(start.y)),
            to: CGPoint(x: CGFloat(end.x), y: CGFloat(end.y)),
            style: style
        )
        selectedStrokeIndices = []
        selectedObjectIDs = []
        activeStroke = []
        rebuildGeometry()
    }

    @discardableResult
    func selectObject(at point: SIMD2<Float>, tolerance: Float = 10) -> Bool {
        let p = canvasPoint(from: point)
        guard let id = objectStore.hitTest(
            at: CGPoint(x: CGFloat(p.x), y: CGFloat(p.y)),
            tolerance: CGFloat(tolerance / zoomScale)
        ) else {
            clearSelection()
            return false
        }
        selectedObjectIDs = [id]
        selectedStrokeIndices = []
        customRotationCenter = nil
        rebuildGeometry()
        return true
    }

    @discardableResult
    func toggleObject(at point: SIMD2<Float>, tolerance: Float = 10) -> Bool {
        let p = canvasPoint(from: point)
        guard let id = objectStore.hitTest(
            at: CGPoint(x: CGFloat(p.x), y: CGFloat(p.y)),
            tolerance: CGFloat(tolerance / zoomScale)
        ) else { return false }
        if selectedObjectIDs.contains(id) {
            selectedObjectIDs.removeAll { $0 == id }
        } else {
            selectedObjectIDs.append(id)
        }
        selectedStrokeIndices = []
        rebuildGeometry()
        return true
    }

    @discardableResult
    func selectObjects(in viewRect: CGRect, fullyContained: Bool = false) -> Int {
        let a = canvasPoint(from: SIMD2(Float(viewRect.minX), Float(viewRect.minY)))
        let b = canvasPoint(from: SIMD2(Float(viewRect.maxX), Float(viewRect.maxY)))
        let r = CGRect(
            x: CGFloat(min(a.x, b.x)),
            y: CGFloat(min(a.y, b.y)),
            width: CGFloat(abs(a.x - b.x)),
            height: CGFloat(abs(a.y - b.y))
        )
        selectedObjectIDs = objectStore.objectsIntersecting(r, fullyContained: fullyContained)
        selectedStrokeIndices = []
        rebuildGeometry()
        return selectedObjectIDs.count
    }

    @discardableResult
    func lineEndpoint(at point: SIMD2<Float>, tolerance: Float = 14) -> (id: UUID, endpoint: Int)? {
        let p = canvasPoint(from: point)
        guard let hit = objectStore.nearestLineEndpoint(
            to: CGPoint(x: CGFloat(p.x), y: CGFloat(p.y)),
            tolerance: CGFloat(tolerance / zoomScale)
        ) else { return nil }
        return (hit.id, hit.endpoint)
    }

    @discardableResult
    func moveSelectedLineEndpoint(id: UUID, endpoint: Int, to point: SIMD2<Float>) -> Bool {
        let p = canvasPoint(from: point)
        guard objectStore.moveLineEndpoint(
            id: id,
            endpoint: endpoint,
            to: CGPoint(x: CGFloat(p.x), y: CGFloat(p.y))
        ) else { return false }
        rebuildGeometry()
        return true
    }

    func deleteSelectedObjects() {
        guard !selectedObjectIDs.isEmpty else { return }
        recordMutation()
        for id in selectedObjectIDs { objectStore.remove(id: id) }
        selectedObjectIDs = []
        rebuildGeometry()
    }

    @discardableResult
    func eraseObjectsByScribble(_ path: [SIMD2<Float>], tolerance: Float = 12) -> Bool {
        let p = path.map {
            let q = canvasPoint(from: $0)
            return CGPoint(x: CGFloat(q.x), y: CGFloat(q.y))
        }
        let ids = objectStore.eraseByScribble(p, tolerance: CGFloat(tolerance / zoomScale))
        guard !ids.isEmpty else { return false }
        selectedObjectIDs.removeAll { ids.contains($0) }
        rebuildGeometry()
        return true
    }

    @discardableResult
    func selectStroke(at point: SIMD2<Float>, tolerance: Float = 10) -> Bool {
        let p = canvasPoint(from: point)
        var hit: Int?
        var best = tolerance / zoomScale
        for i in committedStrokes.indices.reversed() {
            let s = committedStrokes[i].points
            guard s.count > 1 else { continue }
            for j in 0..<(s.count - 1) {
                let d = distance(p, SIMD2(s[j].x, s[j].y), SIMD2(s[j + 1].x, s[j + 1].y))
                if d <= best {
                    best = d
                    hit = i
                    break
                }
            }
        }
        selectedStrokeIndices = hit.map { [$0] } ?? []
        selectedObjectIDs = []
        rebuildGeometry()
        return hit != nil
    }

    @discardableResult
    func toggleStroke(at point: SIMD2<Float>, tolerance: Float = 10) -> Bool {
        selectStroke(at: point, tolerance: tolerance)
    }

    @discardableResult
    func selectStrokes(in viewRect: CGRect, fullyContained: Bool = false) -> Int {
        let a = canvasPoint(from: SIMD2(Float(viewRect.minX), Float(viewRect.minY)))
        let b = canvasPoint(from: SIMD2(Float(viewRect.maxX), Float(viewRect.maxY)))
        let r = CGRect(
            x: CGFloat(min(a.x, b.x)),
            y: CGFloat(min(a.y, b.y)),
            width: CGFloat(abs(a.x - b.x)),
            height: CGFloat(abs(a.y - b.y))
        )
        let result = committedStrokes.indices.filter {
            guard let b = bounds(committedStrokes[$0].points) else { return false }
            return fullyContained ? r.contains(b) : r.intersects(b)
        }
        selectedStrokeIndices = Array(result)
        selectedObjectIDs = []
        rebuildGeometry()
        return result.count
    }

    func clearSelection() {
        selectedStrokeIndices = []
        selectedObjectIDs = []
        customRotationCenter = nil
        rebuildGeometry()
    }

    func selectionBounds() -> CGRect? {
        var result: CGRect?
        for i in selectedStrokeIndices {
            if let b = bounds(committedStrokes[i].points) { result = result?.union(b) ?? b }
        }
        for id in selectedObjectIDs {
            if let b = objectStore.bounds(of: id) { result = result?.union(b) ?? b }
        }
        return result?.insetBy(dx: -8, dy: -8)
    }

    func selectionBoundsInView() -> CGRect? {
        guard let r = selectionBounds() else { return nil }
        let a = viewPoint(from: SIMD2(Float(r.minX), Float(r.minY)))
        let b = viewPoint(from: SIMD2(Float(r.maxX), Float(r.maxY)))
        return CGRect(
            x: CGFloat(min(a.x, b.x)),
            y: CGFloat(min(a.y, b.y)),
            width: CGFloat(abs(a.x - b.x)),
            height: CGFloat(abs(a.y - b.y))
        )
    }

    func selectionCenter() -> SIMD2<Float>? {
        guard let r = selectionBounds() else { return nil }
        return SIMD2(Float(r.midX), Float(r.midY))
    }

    func setRotationCenter(to point: SIMD2<Float>) {
        customRotationCenter = canvasPoint(from: point)
        rebuildGeometry()
    }

    func rotationCenterViewPoint() -> SIMD2<Float>? {
        guard let c = customRotationCenter ?? selectionCenter() else { return nil }
        return viewPoint(from: c)
    }

    func selectionHandle(at point: SIMD2<Float>, tolerance: Float = 10) -> SelectionHandle? {
        guard let r = selectionBounds() else { return nil }
        let p = canvasPoint(from: point)
        let t = tolerance / zoomScale
        let handles: [(SelectionHandle, SIMD2<Float>)] = [
            (.topLeft, SIMD2(Float(r.minX), Float(r.minY))),
            (.topRight, SIMD2(Float(r.maxX), Float(r.minY))),
            (.bottomLeft, SIMD2(Float(r.minX), Float(r.maxY))),
            (.bottomRight, SIMD2(Float(r.maxX), Float(r.maxY)))
        ]
        return handles.first { simd_distance(p, $0.1) <= t }?.0
    }

    func rotationHandle(at point: SIMD2<Float>, tolerance: Float = 12) -> Bool {
        guard let r = selectionBounds() else { return false }
        let p = canvasPoint(from: point)
        return simd_distance(p, SIMD2(Float(r.midX), Float(r.minY - 28))) <= tolerance / zoomScale
    }

    func rotationCenterHandle(at point: SIMD2<Float>, tolerance: Float = 12) -> Bool {
        guard customRotationCenter != nil, let c = rotationCenterViewPoint() else { return false }
        return simd_distance(point, c) <= tolerance
    }

    func resizeSelected(handle: SelectionHandle, to point: SIMD2<Float>) {
        guard !selectedStrokeIndices.isEmpty, selectedObjectIDs.isEmpty, let r = selectionBounds() else { return }
        let p = canvasPoint(from: point)
        let anchor: SIMD2<Float>
        switch handle {
        case .topLeft: anchor = SIMD2(Float(r.maxX), Float(r.maxY))
        case .topRight: anchor = SIMD2(Float(r.minX), Float(r.maxY))
        case .bottomLeft: anchor = SIMD2(Float(r.maxX), Float(r.minY))
        case .bottomRight: anchor = SIMD2(Float(r.minX), Float(r.minY))
        }
        let sx = max(abs(p.x - anchor.x), 1) / max(Float(r.width), 1)
        let sy = max(abs(p.y - anchor.y), 1) / max(Float(r.height), 1)
        for i in selectedStrokeIndices {
            committedStrokes[i].points = committedStrokes[i].points.map {
                InkPoint(
                    x: anchor.x + ($0.x - anchor.x) * sx,
                    y: anchor.y + ($0.y - anchor.y) * sy,
                    pressure: $0.pressure
                )
            }
        }
        rebuildGeometry()
    }

    func scaleSelected(by factor: Float) {
        guard factor > 0, let c = selectionCenter() else { return }
        if !selectedObjectIDs.isEmpty {
            for id in selectedObjectIDs {
                guard let o = objectStore.object(with: id) else { continue }
                let s = o.transform.scale
                _ = objectStore.transform(id: id, scale: CGSize(width: s.width * CGFloat(factor), height: s.height * CGFloat(factor)))
            }
        } else {
            for i in selectedStrokeIndices {
                committedStrokes[i].points = committedStrokes[i].points.map {
                    InkPoint(x: c.x + ($0.x - c.x) * factor, y: c.y + ($0.y - c.y) * factor, pressure: $0.pressure)
                }
            }
        }
        rebuildGeometry()
    }

    func moveSelected(by delta: SIMD2<Float>) {
        if !selectedObjectIDs.isEmpty {
            for id in selectedObjectIDs {
                guard let o = objectStore.object(with: id) else { continue }
                let p = o.transform.position
                _ = objectStore.transform(id: id, position: CGPoint(x: p.x + CGFloat(delta.x), y: p.y + CGFloat(delta.y)))
            }
        } else {
            for i in selectedStrokeIndices {
                committedStrokes[i].points = committedStrokes[i].points.map {
                    InkPoint(x: $0.x + delta.x, y: $0.y + delta.y, pressure: $0.pressure)
                }
            }
        }
        rebuildGeometry()
    }

    func rotateSelected(to point: SIMD2<Float>, from previous: SIMD2<Float>) {
        guard let c = customRotationCenter ?? selectionCenter() else { return }
        let p = canvasPoint(from: point)
        let q = canvasPoint(from: previous)
        rotateSelected(by: atan2(p.y - c.y, p.x - c.x) - atan2(q.y - c.y, q.x - c.x), center: c)
    }

    func setSelectedRotationDegrees(_ degrees: Double) {
        guard let c = selectionCenter() else { return }
        rotateSelected(by: Float(degrees - selectedRotationDegrees) * Float.pi / 180, center: c)
    }

    private func rotateSelected(by d: Float, center c: SIMD2<Float>) {
        if !selectedObjectIDs.isEmpty {
            for id in selectedObjectIDs {
                guard let o = objectStore.object(with: id) else { continue }
                _ = objectStore.transform(id: id, rotation: o.transform.rotation + CGFloat(d))
            }
        } else {
            let co = cos(d)
            let si = sin(d)
            for i in selectedStrokeIndices {
                committedStrokes[i].points = committedStrokes[i].points.map {
                    let v = SIMD2($0.x, $0.y) - c
                    return InkPoint(
                        x: v.x * co - v.y * si + c.x,
                        y: v.x * si + v.y * co + c.y,
                        pressure: $0.pressure
                    )
                }
                committedStrokes[i].rotation += d
            }
        }
        rebuildGeometry()
    }

    func reflectSelected(horizontal: Bool) {
        guard let c = selectionCenter() else { return }
        if !selectedObjectIDs.isEmpty {
            for id in selectedObjectIDs {
                guard let o = objectStore.object(with: id) else { continue }
                let s = o.transform.scale
                _ = objectStore.transform(
                    id: id,
                    scale: CGSize(width: horizontal ? -s.width : s.width, height: horizontal ? s.height : -s.height)
                )
            }
        } else {
            for i in selectedStrokeIndices {
                committedStrokes[i].points = committedStrokes[i].points.map {
                    horizontal
                        ? InkPoint(x: 2 * c.x - $0.x, y: $0.y, pressure: $0.pressure)
                        : InkPoint(x: $0.x, y: 2 * c.y - $0.y, pressure: $0.pressure)
                }
            }
        }
        rebuildGeometry()
    }

    func deleteSelected() {
        if !selectedObjectIDs.isEmpty { deleteSelectedObjects(); return }
        guard !selectedStrokeIndices.isEmpty else { return }
        recordMutation()
        for i in selectedStrokeIndices.sorted(by: >) {
            if committedStrokes.indices.contains(i) { committedStrokes.remove(at: i) }
        }
        selectedStrokeIndices = []
        rebuildGeometry()
    }

    func mtkView(_ view: MTKView, drawableSizeWillChange size: CGSize) {
        updateUniformBuffer(for: size)
    }

    func draw(in view: MTKView) {
        guard let pass = view.currentRenderPassDescriptor,
              let drawable = view.currentDrawable,
              let cb = commandQueue.makeCommandBuffer(),
              let encoder = cb.makeRenderCommandEncoder(descriptor: pass) else { return }

        pass.colorAttachments[0].clearColor = MTLClearColor(
            red: Double(backgroundColor.x),
            green: Double(backgroundColor.y),
            blue: Double(backgroundColor.z),
            alpha: 1
        )
        updateUniformBuffer(for: view.drawableSize)
        encoder.setRenderPipelineState(pipelineState)
        if let b = vertexBuffer { encoder.setVertexBuffer(b, offset: 0, index: 0) }
        if let b = uniformBuffer { encoder.setVertexBuffer(b, offset: 0, index: 1) }
        if !vertices.isEmpty { encoder.drawPrimitives(type: .triangle, vertexStart: 0, vertexCount: vertices.count) }
        encoder.endEncoding()
        cb.present(drawable)
        cb.commit()
    }

    private func rebuildGeometry() {
        var out: [InkVertex] = []
        appendBackground(to: &out)
        for s in committedStrokes { appendStroke(s.points, style: s.style, to: &out) }
        appendObjects(to: &out)
        if let r = selectionBounds() { appendSelection(r, to: &out) }
        if !activeStroke.isEmpty { appendStroke(activeStroke, style: penStyle, to: &out) }
        vertices = out
        vertexBuffer = vertices.isEmpty ? nil : device.makeBuffer(bytes: vertices, length: vertices.count * MemoryLayout<InkVertex>.stride, options: .storageModeShared)
    }

    private func appendObjects(to out: inout [InkVertex]) {
        for o in objectStore.objects {
            let p = objectStore.transformedPoints(of: o)
            guard p.count >= 2 else { continue }
            let color = metalColor(o.style)
            if o.kind == .line || o.kind == .arrow {
                appendLine(
                    viewPoint(from: SIMD2(Float(p[0].x), Float(p[0].y))),
                    viewPoint(from: SIMD2(Float(p[1].x), Float(p[1].y))),
                    width: Float(max(0.5, o.style.strokeWidth)), color: color, to: &out
                )
            } else if o.kind == .polygon {
                let limit = o.geometry.points.count >= 3 ? p.count : max(0, p.count - 1)
                if limit >= 2 {
                    for i in 0..<limit {
                        let j = (i + 1) % limit
                        appendLine(
                            viewPoint(from: SIMD2(Float(p[i].x), Float(p[i].y))),
                            viewPoint(from: SIMD2(Float(p[j].x), Float(p[j].y))),
                            width: Float(max(0.5, o.style.strokeWidth)), color: color, to: &out
                        )
                    }
                }
            }
        }
    }

    private func appendBackground(to out: inout [InkVertex]) {
        guard backgroundPattern != 0 else { return }
        let color = SIMD4<Float>(0.82, 0.84, 0.88, 0.55)
        let step: Float = backgroundPattern == 3 ? 24 : 32
        let e: Float = 2000
        if backgroundPattern == 1 {
            var y: Float = -e
            while y <= e {
                appendLine(viewPoint(from: SIMD2(-e, y)), viewPoint(from: SIMD2(e, y)), width: 0.55, color: color, to: &out)
                y += step
            }
        } else {
            var x: Float = -e
            while x <= e {
                appendLine(viewPoint(from: SIMD2(x, -e)), viewPoint(from: SIMD2(x, e)), width: 0.45, color: color, to: &out)
                x += step
            }
            var y: Float = -e
            while y <= e {
                appendLine(viewPoint(from: SIMD2(-e, y)), viewPoint(from: SIMD2(e, y)), width: 0.45, color: color, to: &out)
                y += step
            }
        }
    }

    private func appendStroke(_ s: [InkPoint], style: PenStyle, to out: inout [InkVertex]) {
        guard !s.isEmpty else { return }
        let color = metalColor(style)
        if s.count > 1 {
            for i in 0..<(s.count - 1) {
                let p = s[i]
                let q = s[i + 1]
                let dx = q.x - p.x
                let dy = q.y - p.y
                let length = max(sqrt(dx * dx + dy * dy), 0.001)
                let nx = -dy / length
                let ny = dx / length
                let w0 = strokeWidth(p.pressure, style)
                let w1 = strokeWidth(q.pressure, style)
                let a = viewPoint(from: SIMD2(p.x + nx * w0, p.y + ny * w0))
                let b = viewPoint(from: SIMD2(p.x - nx * w0, p.y - ny * w0))
                let c = viewPoint(from: SIMD2(q.x + nx * w1, q.y + ny * w1))
                let d = viewPoint(from: SIMD2(q.x - nx * w1, q.y - ny * w1))
                triangle(a, b, c, color: color, to: &out)
                triangle(c, b, d, color: color, to: &out)
            }
        }
        for p in s {
            disk(viewPoint(from: SIMD2(p.x, p.y)), radius: strokeWidth(p.pressure, style), color, to: &out)
        }
    }

    private func appendSelection(_ r: CGRect, to out: inout [InkVertex]) {
        let color = SIMD4<Float>(0.1, 0.45, 1, 0.75)
        let a = viewPoint(from: SIMD2(Float(r.minX), Float(r.minY)))
        let b = viewPoint(from: SIMD2(Float(r.maxX), Float(r.maxY)))
        let p0 = SIMD2(a.x, a.y)
        let p1 = SIMD2(b.x, a.y)
        let p2 = SIMD2(b.x, b.y)
        let p3 = SIMD2(a.x, b.y)
        appendLine(p0, p1, width: 1.5, color: color, to: &out)
        appendLine(p1, p2, width: 1.5, color: color, to: &out)
        appendLine(p2, p3, width: 1.5, color: color, to: &out)
        appendLine(p3, p0, width: 1.5, color: color, to: &out)
        for p in [p0, p1, p2, p3] { disk(p, 5, color, to: &out) }
        let rotation = SIMD2((a.x + b.x) / 2, a.y - 28)
        appendLine(SIMD2((a.x + b.x) / 2, a.y), rotation, width: 1, color: color, to: &out)
        disk(rotation, 7, color, to: &out)
        if let rc = customRotationCenter {
            disk(viewPoint(from: rc), 7, SIMD4<Float>(0.95, 0.55, 0.05, 1), to: &out)
        }
        if selectedObjectIDs.count == 1,
           let id = selectedObjectIDs.first,
           let object = objectStore.object(with: id),
           object.kind == .line {
            for q in objectStore.transformedPoints(of: object).prefix(2) {
                disk(viewPoint(from: SIMD2(Float(q.x), Float(q.y))), 7, SIMD4<Float>(0.95, 0.55, 0.05, 1), to: &out)
            }
        }
    }

    private func bounds(_ points: [InkPoint]) -> CGRect? {
        guard let first = points.first else { return nil }
        var x0 = first.x, x1 = first.x, y0 = first.y, y1 = first.y
        for p in points {
            x0 = min(x0, p.x); x1 = max(x1, p.x)
            y0 = min(y0, p.y); y1 = max(y1, p.y)
        }
        return CGRect(x: CGFloat(x0), y: CGFloat(y0), width: CGFloat(x1 - x0), height: CGFloat(y1 - y0))
    }

    private func appendLine(_ a: SIMD2<Float>, _ b: SIMD2<Float>, width: Float, color: SIMD4<Float>, to out: inout [InkVertex]) {
        let d = b - a
        let length = max(simd_length(d), 0.001)
        let n = SIMD2(-d.y, d.x) / length * width
        triangle(a + n, a - n, b + n, color: color, to: &out)
        triangle(b + n, a - n, b - n, color: color, to: &out)
    }

    private func distance(_ p: SIMD2<Float>, _ a: SIMD2<Float>, _ b: SIMD2<Float>) -> Float {
        let d = b - a
        let lengthSquared = simd_length_squared(d)
        if lengthSquared < 0.0001 { return simd_distance(p, a) }
        let t = max(0, min(1, simd_dot(p - a, d) / lengthSquared))
        return simd_distance(p, a + d * t)
    }

    private func strokeWidth(_ pressure: Float, _ style: PenStyle) -> Float {
        let p = max(0, min(1, pressure))
        let curve = style.pressureEnabled ? pow(p, max(0.25, Float(style.pressureCurve))) : 0.75
        return Float(style.width) * (0.45 + 0.75 * curve)
    }

    private func metalColor(_ style: PenStyle) -> SIMD4<Float> {
        var color = SIMD4(
            Float(style.color.red),
            Float(style.color.green),
            Float(style.color.blue),
            Float(style.color.alpha * style.opacity)
        )
        if displayInverted {
            let high = max(color.x, max(color.y, color.z))
            let low = min(color.x, min(color.y, color.z))
            if high < 0.12 || low > 0.88 { color = SIMD4(1, 1, 1, color.w) }
        }
        return color
    }

    private func triangle(_ a: SIMD2<Float>, _ b: SIMD2<Float>, _ c: SIMD2<Float>, color: SIMD4<Float>, to out: inout [InkVertex]) {
        out.append(InkVertex(position: a, color: color))
        out.append(InkVertex(position: b, color: color))
        out.append(InkVertex(position: c, color: color))
    }

    private func disk(_ center: SIMD2<Float>, _ radius: Float, _ color: SIMD4<Float>, to out: inout [InkVertex]) {
        let count = 12
        for i in 0..<count {
            let a = Float(i) / Float(count) * 2 * Float.pi
            let b = Float(i + 1) / Float(count) * 2 * Float.pi
            triangle(
                center,
                center + SIMD2(cos(a), sin(a)) * radius,
                center + SIMD2(cos(b), sin(b)) * radius,
                color: color,
                to: &out
            )
        }
    }

    private func updateUniformBuffer(for size: CGSize) {
        let uniforms = Uniforms(viewportSize: SIMD2(Float(max(size.width, 1)), Float(max(size.height, 1))))
        if uniformBuffer == nil {
            uniformBuffer = device.makeBuffer(length: MemoryLayout<Uniforms>.stride, options: .storageModeShared)
        }
        guard let buffer = uniformBuffer else { return }
        withUnsafeBytes(of: uniforms) {
            if let base = $0.baseAddress { memcpy(buffer.contents(), base, MemoryLayout<Uniforms>.stride) }
        }
    }
}
