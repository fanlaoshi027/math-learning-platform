import Metal
import MetalKit
import simd

final class InkRenderer: NSObject, MTKViewDelegate {
    enum SelectionHandle { case topLeft, topRight, bottomLeft, bottomRight }
    private struct Uniforms { var viewportSize: SIMD2<Float> }
    private struct StoredStroke { var id: UUID; var points: [InkPoint]; var style: PenStyle; var rotation: Float = 0 }
    private struct HistoryState { var strokes: [StoredStroke]; var selection: [Int] }

    private let device: any MTLDevice
    private let commandQueue: any MTLCommandQueue
    private let pipelineState: any MTLRenderPipelineState
    private var committedStrokes: [StoredStroke] = []
    private var activeStroke: [InkPoint] = []
    private var vertices: [InkVertex] = []
    private var vertexBuffer: (any MTLBuffer)?
    private var uniformBuffer: (any MTLBuffer)?
    private var penStyle = PenStyle()
    private(set) var selectedStrokeIndices: [Int] = []
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
        guard let q = device.makeCommandQueue(),
              let library = try? device.makeDefaultLibrary(bundle: Bundle.module),
              let vf = library.makeFunction(name: "inkVertex"),
              let ff = library.makeFunction(name: "inkFragment") else { return nil }
        let d = MTLRenderPipelineDescriptor()
        d.vertexFunction = vf
        d.fragmentFunction = ff
        d.colorAttachments[0].pixelFormat = .bgra8Unorm
        d.colorAttachments[0].isBlendingEnabled = true
        d.colorAttachments[0].sourceRGBBlendFactor = .sourceAlpha
        d.colorAttachments[0].destinationRGBBlendFactor = .oneMinusSourceAlpha
        d.colorAttachments[0].sourceAlphaBlendFactor = .sourceAlpha
        d.colorAttachments[0].destinationAlphaBlendFactor = .oneMinusSourceAlpha
        guard let p = try? device.makeRenderPipelineState(descriptor: d) else { return nil }
        self.device = device
        commandQueue = q
        pipelineState = p
        super.init()
    }

    var canUndo: Bool { !undoStack.isEmpty }
    var canRedo: Bool { !redoStack.isEmpty }
    var hasSelection: Bool { !selectedStrokeIndices.isEmpty }
    var selectionCount: Int { selectedStrokeIndices.count }
    var selectedStrokeIndex: Int? { selectedStrokeIndices.count == 1 ? selectedStrokeIndices[0] : nil }
    var zoomPercent: Int { Int((zoomScale * 100).rounded()) }

    var selectedRotationDegrees: Double {
        guard let i = selectedStrokeIndex, committedStrokes.indices.contains(i) else { return 0 }
        return Double(committedStrokes[i].rotation * 180 / .pi)
    }

    func setPenStyle(_ s: PenStyle) { penStyle = s; rebuildGeometry() }
    func setBackgroundColor(_ c: SIMD4<Float>) { backgroundColor = c; rebuildGeometry() }
    func setDisplayInverted(_ v: Bool) { displayInverted = v; rebuildGeometry() }
    func setBackgroundPattern(_ p: Int) { backgroundPattern = p; rebuildGeometry() }
    func setStroke(_ p: [InkPoint]) { activeStroke = p; rebuildGeometry() }

    func canvasPoint(from p: SIMD2<Float>) -> SIMD2<Float> { (p - panOffset) / zoomScale }
    func viewPoint(from p: SIMD2<Float>) -> SIMD2<Float> { p * zoomScale + panOffset }
    func pan(by d: SIMD2<Float>) { panOffset += d; rebuildGeometry() }

    func zoom(by f: Float, around p: SIMD2<Float>) {
        let old = zoomScale
        let new = min(max(old * f, 0.25), 4)
        guard abs(new - old) > 0.0001 else { return }
        let c = canvasPoint(from: p)
        zoomScale = new
        panOffset = p - c * zoomScale
        rebuildGeometry()
    }

    func resetZoom(centeredIn size: CGSize) {
        zoomScale = 1
        panOffset = SIMD2(Float(size.width * 0.5), Float(size.height * 0.5))
        rebuildGeometry()
    }

    func exportPageState() -> CanvasPageState {
        CanvasPageState(strokes: committedStrokes.map {
            CanvasStroke(id: $0.id, points: $0.points, style: $0.style, rotation: $0.rotation)
        })
    }

    func importPageState(_ state: CanvasPageState) {
        committedStrokes = state.strokes.map {
            StoredStroke(id: $0.id, points: $0.points, style: $0.style, rotation: $0.rotation)
        }
        activeStroke = []
        selectedStrokeIndices = []
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
        if before.strokes != after.strokes || before.selection != after.selection {
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
        HistoryState(strokes: committedStrokes, selection: selectedStrokeIndices)
    }

    private func restore(_ s: HistoryState) {
        committedStrokes = s.strokes
        selectedStrokeIndices = s.selection.filter { committedStrokes.indices.contains($0) }
        customRotationCenter = nil
        rebuildGeometry()
    }

    func undo() {
        guard let s = undoStack.popLast() else { return }
        redoStack.append(captureState())
        restore(s)
    }

    func redo() {
        guard let s = redoStack.popLast() else { return }
        undoStack.append(captureState())
        restore(s)
    }

    func commitStroke(_ p: [InkPoint]) {
        guard p.count >= 2 else { activeStroke = []; rebuildGeometry(); return }
        recordMutation()
        committedStrokes.append(StoredStroke(id: UUID(), points: p, style: penStyle))
        selectedStrokeIndices = []
        activeStroke = []
        rebuildGeometry()
    }

    @discardableResult
    func selectStroke(at point: SIMD2<Float>, tolerance: Float = 10) -> Bool {
        let c = canvasPoint(from: point)
        let tol = tolerance / zoomScale
        var best: Int?
        var bestDistance = tol
        for i in committedStrokes.indices.reversed() {
            let s = committedStrokes[i].points
            guard s.count >= 2 else { continue }
            for n in 0..<(s.count - 1) {
                let d = distanceFromPoint(c, toSegment: SIMD2(s[n].x, s[n].y), SIMD2(s[n + 1].x, s[n + 1].y))
                if d <= bestDistance { bestDistance = d; best = i; break }
            }
        }
        selectedStrokeIndices = best.map { [$0] } ?? []
        customRotationCenter = nil
        rebuildGeometry()
        return best != nil
    }

    @discardableResult
    func selectStrokes(in viewRect: CGRect, fullyContained: Bool = false) -> Int {
        let p0 = canvasPoint(from: SIMD2(Float(viewRect.minX), Float(viewRect.minY)))
        let p1 = canvasPoint(from: SIMD2(Float(viewRect.maxX), Float(viewRect.maxY)))
        let r = CGRect(x: CGFloat(min(p0.x, p1.x)), y: CGFloat(min(p0.y, p1.y)),
                       width: CGFloat(abs(p1.x - p0.x)), height: CGFloat(abs(p1.y - p0.y)))
        let result = committedStrokes.indices.filter {
            guard let b = strokeBounds(committedStrokes[$0].points) else { return false }
            return fullyContained ? r.contains(b) : r.intersects(b)
        }
        selectedStrokeIndices = Array(result)
        customRotationCenter = nil
        rebuildGeometry()
        return result.count
    }

    func clearSelection() { selectedStrokeIndices = []; customRotationCenter = nil; rebuildGeometry() }

    func selectionBounds() -> CGRect? {
        let bs = selectedStrokeIndices.compactMap {
            committedStrokes.indices.contains($0) ? strokeBounds(committedStrokes[$0].points) : nil
        }
        guard var r = bs.first else { return nil }
        for b in bs.dropFirst() { r = r.union(b) }
        return r.insetBy(dx: -8, dy: -8)
    }

    func selectionBoundsInView() -> CGRect? {
        guard let r = selectionBounds() else { return nil }
        let a = viewPoint(from: SIMD2(Float(r.minX), Float(r.minY)))
        let b = viewPoint(from: SIMD2(Float(r.maxX), Float(r.maxY)))
        return CGRect(x: CGFloat(min(a.x, b.x)), y: CGFloat(min(a.y, b.y)),
                      width: CGFloat(abs(b.x - a.x)), height: CGFloat(abs(b.y - a.y)))
    }

    func selectionCenter() -> SIMD2<Float>? {
        guard let r = selectionBounds() else { return nil }
        return SIMD2(Float(r.midX), Float(r.midY))
    }

    func setRotationCenter(to viewPoint: SIMD2<Float>) {
        customRotationCenter = canvasPoint(from: viewPoint)
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
        let c = SIMD2(Float(r.midX), Float(r.minY - 28))
        return simd_distance(p, c) <= tolerance / zoomScale
    }

    func rotationCenterHandle(at point: SIMD2<Float>, tolerance: Float = 12) -> Bool {
        guard customRotationCenter != nil, let c = rotationCenterViewPoint() else { return false }
        return simd_distance(point, c) <= tolerance
    }

    func resizeSelected(handle: SelectionHandle, to point: SIMD2<Float>) {
        guard !selectedStrokeIndices.isEmpty, let r = selectionBounds() else { return }
        let p = canvasPoint(from: point)
        let anchor: SIMD2<Float>
        switch handle {
        case .topLeft: anchor = SIMD2(Float(r.maxX), Float(r.maxY))
        case .topRight: anchor = SIMD2(Float(r.minX), Float(r.maxY))
        case .bottomLeft: anchor = SIMD2(Float(r.maxX), Float(r.minY))
        case .bottomRight: anchor = SIMD2(Float(r.minX), Float(r.minY))
        }
        let ow = max(Float(r.width), 1)
        let oh = max(Float(r.height), 1)
        let sx = max(abs(p.x - anchor.x), 1) / ow
        let sy = max(abs(p.y - anchor.y), 1) / oh
        for i in selectedStrokeIndices where committedStrokes.indices.contains(i) {
            committedStrokes[i].points = committedStrokes[i].points.map {
                InkPoint(x: anchor.x + ($0.x - anchor.x) * sx,
                         y: anchor.y + ($0.y - anchor.y) * sy,
                         pressure: $0.pressure)
            }
        }
        rebuildGeometry()
    }

    func scaleSelected(by factor: Float) {
        guard factor > 0, let c = selectionCenter() else { return }
        for i in selectedStrokeIndices where committedStrokes.indices.contains(i) {
            committedStrokes[i].points = committedStrokes[i].points.map {
                InkPoint(x: c.x + ($0.x - c.x) * factor,
                         y: c.y + ($0.y - c.y) * factor,
                         pressure: $0.pressure)
            }
        }
        rebuildGeometry()
    }

    func moveSelected(by d: SIMD2<Float>) {
        for i in selectedStrokeIndices where committedStrokes.indices.contains(i) {
            committedStrokes[i].points = committedStrokes[i].points.map {
                InkPoint(x: $0.x + d.x, y: $0.y + d.y, pressure: $0.pressure)
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
        guard let c = selectionCenter(), let i = selectedStrokeIndex, committedStrokes.indices.contains(i) else { return }
        let current = selectedRotationDegrees
        rotateSelected(by: Float(degrees - current) * .pi / 180, center: c)
    }

    private func rotateSelected(by d: Float, center c: SIMD2<Float>) {
        let co = cos(d), si = sin(d)
        for i in selectedStrokeIndices where committedStrokes.indices.contains(i) {
            committedStrokes[i].points = committedStrokes[i].points.map {
                let v = SIMD2($0.x, $0.y) - c
                return InkPoint(x: v.x * co - v.y * si + c.x,
                                y: v.x * si + v.y * co + c.y,
                                pressure: $0.pressure)
            }
            committedStrokes[i].rotation += d
        }
        rebuildGeometry()
    }

    func reflectSelected(horizontal: Bool) {
        guard let c = selectionCenter() else { return }
        for i in selectedStrokeIndices where committedStrokes.indices.contains(i) {
            committedStrokes[i].points = committedStrokes[i].points.map {
                horizontal
                    ? InkPoint(x: 2 * c.x - $0.x, y: $0.y, pressure: $0.pressure)
                    : InkPoint(x: $0.x, y: 2 * c.y - $0.y, pressure: $0.pressure)
            }
        }
        rebuildGeometry()
    }

    func deleteSelected() {
        guard !selectedStrokeIndices.isEmpty else { return }
        recordMutation()
        for i in selectedStrokeIndices.sorted(by: >) where committedStrokes.indices.contains(i) {
            committedStrokes.remove(at: i)
        }
        selectedStrokeIndices = []
        customRotationCenter = nil
        rebuildGeometry()
    }

    func mtkView(_ view: MTKView, drawableSizeWillChange size: CGSize) { updateUniformBuffer(for: size) }

    func draw(in view: MTKView) {
        guard let d = view.currentRenderPassDescriptor,
              let drawable = view.currentDrawable,
              let cb = commandQueue.makeCommandBuffer(),
              let e = cb.makeRenderCommandEncoder(descriptor: d) else { return }
        d.colorAttachments[0].clearColor = MTLClearColor(red: Double(backgroundColor.x), green: Double(backgroundColor.y), blue: Double(backgroundColor.z), alpha: 1)
        updateUniformBuffer(for: view.drawableSize)
        e.setRenderPipelineState(pipelineState)
        if let b = vertexBuffer { e.setVertexBuffer(b, offset: 0, index: 0) }
        if let b = uniformBuffer { e.setVertexBuffer(b, offset: 0, index: 1) }
        if !vertices.isEmpty { e.drawPrimitives(type: .triangle, vertexStart: 0, vertexCount: vertices.count) }
        e.endEncoding()
        cb.present(drawable)
        cb.commit()
    }

    private func viewPosition(_ p: SIMD2<Float>) -> SIMD2<Float> { p * zoomScale + panOffset }

    private func rebuildGeometry() {
        var output: [InkVertex] = []
        appendBackgroundPattern(to: &output)
        for i in committedStrokes.indices {
            let s = committedStrokes[i]
            appendStrokeGeometry(s.points, style: s.style, to: &output)
        }
        if let r = selectionBounds() {
            appendSelectionBounds(r, to: &output)
        }
        if !activeStroke.isEmpty {
            appendStrokeGeometry(activeStroke, style: penStyle, to: &output)
        }
        vertices = output
        vertexBuffer = vertices.isEmpty ? nil : device.makeBuffer(bytes: vertices, length: vertices.count * MemoryLayout<InkVertex>.stride, options: .storageModeShared)
    }

    // Existing geometry helpers remain unchanged below this point in the project.
    private func appendBackgroundPattern(to o: inout [InkVertex]) {
        guard backgroundPattern != 0 else { return }
        let c = displayInverted ? SIMD4<Float>(0.24, 0.24, 0.24, 0.55) : SIMD4<Float>(0.82, 0.84, 0.88, 0.55)
        let step: Float = backgroundPattern == 3 ? 24 : 32
        let ext: Float = 4000
        if backgroundPattern == 1 {
            var y: Float = -ext
            while y <= ext { appendLineQuad(viewPosition(SIMD2(-ext, y)), viewPosition(SIMD2(ext, y)), width: 0.55 * zoomScale, color: c, to: &o); y += step }
        } else if backgroundPattern == 3 {
            var y: Float = -ext
            while y <= ext {
                var x: Float = -ext
                while x <= ext { appendDisk(center: viewPosition(SIMD2(x, y)), radius: 1.1 * zoomScale, color: c, to: &o); x += step }
                y += step
            }
        } else {
            var y: Float = -ext
            while y <= ext {
                appendLineQuad(viewPosition(SIMD2(-ext, y)), viewPosition(SIMD2(ext, y)), width: 0.55 * zoomScale, color: c, to: &o)
                y += step
            }
            var x: Float = -ext
            while x <= ext {
                appendLineQuad(viewPosition(SIMD2(x, -ext)), viewPosition(SIMD2(x, ext)), width: 0.55 * zoomScale, color: c, to: &o)
                x += step
            }
        }
    }

    private func appendSelectionBounds(_ r: CGRect, to o: inout [InkVertex]) {
        let a = viewPosition(SIMD2(Float(r.minX), Float(r.minY)))
        let b = viewPosition(SIMD2(Float(r.maxX), Float(r.maxY)))
        let c = SIMD4<Float>(0.12, 0.45, 1.0, 0.9)
        let w: Float = 1.5
        appendLineQuad(a, SIMD2(b.x, a.y), width: w, color: c, to: &o)
        appendLineQuad(SIMD2(b.x, a.y), b, width: w, color: c, to: &o)
        appendLineQuad(b, SIMD2(a.x, b.y), width: w, color: c, to: &o)
        appendLineQuad(SIMD2(a.x, b.y), a, width: w, color: c, to: &o)
        let handles = [a, SIMD2(b.x, a.y), SIMD2(a.x, b.y), b]
        for h in handles { appendDisk(center: h, radius: 5.5, color: SIMD4<Float>(1, 1, 1, 1), to: &o); appendDisk(center: h, radius: 4.0, color: c, to: &o) }
        let center = customRotationCenter.map(viewPosition) ?? SIMD2((a.x + b.x) * 0.5, (a.y + b.y) * 0.5)
        appendDisk(center: center, radius: 5.0, color: SIMD4<Float>(1, 1, 1, 1), to: &o)
        appendDisk(center: center, radius: 3.5, color: SIMD4<Float>(0.95, 0.35, 0.1, 1), to: &o)
        let rotation = SIMD2((a.x + b.x) * 0.5, a.y - 28)
        appendLineQuad(SIMD2((a.x + b.x) * 0.5, a.y), rotation, width: 1.2, color: c, to: &o)
        appendDisk(center: rotation, radius: 5.5, color: SIMD4<Float>(1, 1, 1, 1), to: &o)
        appendDisk(center: rotation, radius: 4.0, color: c, to: &o)
    }

    private func updateUniformBuffer(for size: CGSize) {
        var u = Uniforms(viewportSize: SIMD2(Float(size.width), Float(size.height)))
        if uniformBuffer == nil { uniformBuffer = device.makeBuffer(length: MemoryLayout<Uniforms>.stride, options: .storageModeShared) }
        memcpy(uniformBuffer?.contents(), &u, MemoryLayout<Uniforms>.stride)
    }

    private func strokeBounds(_ points: [InkPoint]) -> CGRect? {
        guard let first = points.first else { return nil }
        var minX = CGFloat(first.x), maxX = minX, minY = CGFloat(first.y), maxY = minY
        for p in points.dropFirst() { minX = min(minX, CGFloat(p.x)); maxX = max(maxX, CGFloat(p.x)); minY = min(minY, CGFloat(p.y)); maxY = max(maxY, CGFloat(p.y)) }
        return CGRect(x: minX, y: minY, width: maxX - minX, height: maxY - minY)
    }

    private func distanceFromPoint(_ p: SIMD2<Float>, toSegment a: SIMD2<Float>, _ b: SIMD2<Float>) -> Float {
        let ab = b - a
        let lengthSquared = simd_length_squared(ab)
        if lengthSquared < 0.0001 { return simd_distance(p, a) }
        let t = max(0, min(1, simd_dot(p - a, ab) / lengthSquared))
        return simd_distance(p, a + ab * t)
    }

    private func appendStrokeGeometry(_ points: [InkPoint], style: PenStyle, to o: inout [InkVertex]) {
        guard points.count >= 2 else { return }
        let color = SIMD4<Float>(Float(style.color.red), Float(style.color.green), Float(style.color.blue), Float(style.color.alpha * style.opacity))
        for i in 0..<(points.count - 1) {
            let a = viewPosition(SIMD2(points[i].x, points[i].y))
            let b = viewPosition(SIMD2(points[i + 1].x, points[i + 1].y))
            appendLineQuad(a, b, width: Float(style.width(for: CGFloat(points[i].pressure))) * zoomScale, color: color, to: &o)
        }
    }

    private func appendLineQuad(_ a: SIMD2<Float>, _ b: SIMD2<Float>, width: Float, color: SIMD4<Float>, to o: inout [InkVertex]) {
        let d = b - a
        let l = max(simd_length(d), 0.001)
        let n = SIMD2(-d.y / l, d.x / l) * (width * 0.5)
        let p0 = a + n, p1 = a - n, p2 = b + n, p3 = b - n
        let v0 = InkVertex(position: p0, color: color), v1 = InkVertex(position: p1, color: color), v2 = InkVertex(position: p2, color: color), v3 = InkVertex(position: p3, color: color)
        o.append(contentsOf: [v0, v1, v2, v2, v1, v3])
    }

    private func appendDisk(center: SIMD2<Float>, radius: Float, color: SIMD4<Float>, to o: inout [InkVertex]) {
        let segments = 16
        for i in 0..<segments {
            let a0 = Float(i) / Float(segments) * 2 * .pi
            let a1 = Float(i + 1) / Float(segments) * 2 * .pi
            o.append(InkVertex(position: center, color: color))
            o.append(InkVertex(position: center + SIMD2(cos(a0), sin(a0)) * radius, color: color))
            o.append(InkVertex(position: center + SIMD2(cos(a1), sin(a1)) * radius, color: color))
        }
    }
}
