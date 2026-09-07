import Metal
import MetalKit
import simd

final class InkRenderer: NSObject, MTKViewDelegate {
    private struct Uniforms { var viewportSize: SIMD2<Float> }
    private struct StoredStroke { var points: [InkPoint]; var style: PenStyle }
    private let device: any MTLDevice
    private let commandQueue: any MTLCommandQueue
    private let pipelineState: any MTLRenderPipelineState
    private var committedStrokes: [StoredStroke] = []
    private var redoStrokes: [StoredStroke] = []
    private var activeStroke: [InkPoint] = []
    private var vertices: [InkVertex] = []
    private var vertexBuffer: (any MTLBuffer)?
    private var uniformBuffer: (any MTLBuffer)?
    private var penStyle = PenStyle()
    private(set) var selectedStrokeIndex: Int?

    init?(device: any MTLDevice) {
        guard let commandQueue = device.makeCommandQueue(), let library = device.makeDefaultLibrary(), let vertexFunction = library.makeFunction(name: "inkVertex"), let fragmentFunction = library.makeFunction(name: "inkFragment") else { return nil }
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

    var canUndo: Bool { !committedStrokes.isEmpty }
    var canRedo: Bool { !redoStrokes.isEmpty }
    var hasSelection: Bool { selectedStrokeIndex != nil }

    func setPenStyle(_ style: PenStyle) { penStyle = style; rebuildGeometry() }
    func setStroke(_ points: [InkPoint]) { activeStroke = points; rebuildGeometry() }

    func commitStroke(_ points: [InkPoint]) {
        guard points.count >= 2 else { activeStroke.removeAll(); rebuildGeometry(); return }
        committedStrokes.append(StoredStroke(points: points, style: penStyle))
        redoStrokes.removeAll(keepingCapacity: true)
        selectedStrokeIndex = nil
        activeStroke.removeAll(keepingCapacity: true)
        rebuildGeometry()
    }

    func undo() {
        guard let stroke = committedStrokes.popLast() else { return }
        redoStrokes.append(stroke)
        selectedStrokeIndex = nil
        rebuildGeometry()
    }

    func redo() {
        guard let stroke = redoStrokes.popLast() else { return }
        committedStrokes.append(stroke)
        selectedStrokeIndex = nil
        rebuildGeometry()
    }

    @discardableResult
    func selectStroke(at point: SIMD2<Float>, tolerance: Float = 10) -> Bool {
        var bestIndex: Int?
        var bestDistance = tolerance
        for index in committedStrokes.indices.reversed() {
            let stroke = committedStrokes[index].points
            guard stroke.count >= 2 else { continue }
            for segment in 0..<(stroke.count - 1) {
                let a = SIMD2<Float>(stroke[segment].x, stroke[segment].y)
                let b = SIMD2<Float>(stroke[segment + 1].x, stroke[segment + 1].y)
                let distance = distanceFromPoint(point, toSegment: a, b)
                if distance <= bestDistance {
                    bestDistance = distance
                    bestIndex = index
                    break
                }
            }
        }
        selectedStrokeIndex = bestIndex
        rebuildGeometry()
        return bestIndex != nil
    }

    func clearSelection() {
        selectedStrokeIndex = nil
        rebuildGeometry()
    }

    func moveSelected(by delta: SIMD2<Float>) {
        guard let index = selectedStrokeIndex, committedStrokes.indices.contains(index) else { return }
        committedStrokes[index].points = committedStrokes[index].points.map {
            InkPoint(x: $0.x + delta.x, y: $0.y + delta.y, pressure: $0.pressure)
        }
        rebuildGeometry()
    }

    func deleteSelected() {
        guard let index = selectedStrokeIndex, committedStrokes.indices.contains(index) else { return }
        committedStrokes.remove(at: index)
        selectedStrokeIndex = nil
        redoStrokes.removeAll(keepingCapacity: true)
        rebuildGeometry()
    }

    func mtkView(_ view: MTKView, drawableSizeWillChange size: CGSize) { updateUniformBuffer(for: view.bounds.size) }

    func draw(in view: MTKView) {
        guard let descriptor = view.currentRenderPassDescriptor, let drawable = view.currentDrawable, let commandBuffer = commandQueue.makeCommandBuffer(), let encoder = commandBuffer.makeRenderCommandEncoder(descriptor: descriptor) else { return }
        updateUniformBuffer(for: view.bounds.size)
        encoder.setRenderPipelineState(pipelineState)
        if let vertexBuffer { encoder.setVertexBuffer(vertexBuffer, offset: 0, index: 0) }
        if let uniformBuffer { encoder.setVertexBuffer(uniformBuffer, offset: 0, index: 1) }
        if !vertices.isEmpty { encoder.drawPrimitives(type: .triangle, vertexStart: 0, vertexCount: vertices.count) }
        encoder.endEncoding(); commandBuffer.present(drawable); commandBuffer.commit()
    }

    private func rebuildGeometry() {
        var output: [InkVertex] = []
        for index in committedStrokes.indices {
            let stroke = committedStrokes[index]
            appendStrokeGeometry(stroke.points, style: stroke.style, to: &output)
            if selectedStrokeIndex == index { appendSelectionBounds(stroke.points, color: SIMD4<Float>(0.1, 0.45, 1, 0.75), to: &output) }
        }
        if !activeStroke.isEmpty { appendStrokeGeometry(activeStroke, style: penStyle, to: &output) }
        vertices = output
        vertexBuffer = vertices.isEmpty ? nil : device.makeBuffer(bytes: vertices, length: vertices.count * MemoryLayout<InkVertex>.stride, options: .storageModeShared)
    }

    private func appendStrokeGeometry(_ stroke: [InkPoint], style: PenStyle, to output: inout [InkVertex]) {
        guard let first = stroke.first, let last = stroke.last else { return }
        let color = metalColor(style)
        for index in 0..<(stroke.count - 1) {
            let p0 = stroke[index], p1 = stroke[index + 1]
            let dx = p1.x - p0.x, dy = p1.y - p0.y
            let length = max(sqrt(dx * dx + dy * dy), 0.001)
            let nx = -dy / length, ny = dx / length
            let w0 = strokeWidth(p0.pressure, style: style), w1 = strokeWidth(p1.pressure, style: style)
            let a = SIMD2<Float>(p0.x + nx * w0, p0.y + ny * w0)
            let b = SIMD2<Float>(p0.x - nx * w0, p0.y - ny * w0)
            let c = SIMD2<Float>(p1.x + nx * w1, p1.y + ny * w1)
            let d = SIMD2<Float>(p1.x - nx * w1, p1.y - ny * w1)
            appendTriangle(a, b, c, color: color, to: &output); appendTriangle(c, b, d, color: color, to: &output)
        }
        appendDisk(center: SIMD2<Float>(first.x, first.y), radius: strokeWidth(first.pressure, style: style), color: color, to: &output)
        appendDisk(center: SIMD2<Float>(last.x, last.y), radius: strokeWidth(last.pressure, style: style), color: color, to: &output)
        if stroke.count > 2 { for point in stroke.dropFirst().dropLast() { appendDisk(center: SIMD2<Float>(point.x, point.y), radius: strokeWidth(point.pressure, style: style), color: color, to: &output) } }
    }

    private func appendSelectionBounds(_ stroke: [InkPoint], color: SIMD4<Float>, to output: inout [InkVertex]) {
        guard let first = stroke.first else { return }
        var minX = first.x, maxX = first.x, minY = first.y, maxY = first.y
        for point in stroke {
            minX = min(minX, point.x); maxX = max(maxX, point.x)
            minY = min(minY, point.y); maxY = max(maxY, point.y)
        }
        let pad: Float = 8
        let x0 = minX - pad, x1 = maxX + pad, y0 = minY - pad, y1 = maxY + pad
        let a = SIMD2<Float>(x0, y0), b = SIMD2<Float>(x1, y0), c = SIMD2<Float>(x1, y1), d = SIMD2<Float>(x0, y1)
        appendLineQuad(a, b, width: 1.5, color: color, to: &output)
        appendLineQuad(b, c, width: 1.5, color: color, to: &output)
        appendLineQuad(c, d, width: 1.5, color: color, to: &output)
        appendLineQuad(d, a, width: 1.5, color: color, to: &output)
    }

    private func appendLineQuad(_ a: SIMD2<Float>, _ b: SIMD2<Float>, width: Float, color: SIMD4<Float>, to output: inout [InkVertex]) {
        let delta = b - a
        let length = max(simd_length(delta), 0.001)
        let normal = SIMD2<Float>(-delta.y / length, delta.x / length) * width
        appendTriangle(a + normal, a - normal, b + normal, color: color, to: &output)
        appendTriangle(b + normal, a - normal, b - normal, color: color, to: &output)
    }

    private func distanceFromPoint(_ p: SIMD2<Float>, toSegment a: SIMD2<Float>, _ b: SIMD2<Float>) -> Float {
        let ab = b - a
        let lengthSquared = simd_length_squared(ab)
        if lengthSquared < 0.0001 { return simd_distance(p, a) }
        let t = max(0, min(1, simd_dot(p - a, ab) / lengthSquared))
        return simd_distance(p, a + ab * t)
    }

    private func strokeWidth(_ pressure: Float, style: PenStyle) -> Float {
        let p = max(0, min(1, pressure))
        let curved = pow(p, Float(max(0.25, style.pressureCurve)))
        if !style.pressureEnabled { return Float(max(0.5, style.width / 2)) }
        return Float(max(0.5, style.width * (0.45 + 0.75 * CGFloat(curved))))
    }

    private func metalColor(_ style: PenStyle) -> SIMD4<Float> { SIMD4<Float>(Float(style.color.red), Float(style.color.green), Float(style.color.blue), Float(style.color.alpha * style.opacity)) }
    private func appendTriangle(_ a: SIMD2<Float>, _ b: SIMD2<Float>, _ c: SIMD2<Float>, color: SIMD4<Float>, to output: inout [InkVertex]) { output += [InkVertex(position: a, color: color), InkVertex(position: b, color: color), InkVertex(position: c, color: color)] }

    private func appendDisk(center: SIMD2<Float>, radius: Float, color: SIMD4<Float>, to output: inout [InkVertex]) {
        let segments = 16, step = Float.pi * 2 / Float(segments)
        for index in 0..<segments {
            let a = Float(index) * step, b = Float(index + 1) * step
            output += [InkVertex(position: center, color: color), InkVertex(position: center + SIMD2<Float>(cos(a), sin(a)) * radius, color: color), InkVertex(position: center + SIMD2<Float>(cos(b), sin(b)) * radius, color: color)]
        }
    }

    private func updateUniformBuffer(for size: CGSize) {
        var uniforms = Uniforms(viewportSize: SIMD2<Float>(Float(max(size.width, 1)), Float(max(size.height, 1))))
        if uniformBuffer == nil { uniformBuffer = device.makeBuffer(length: MemoryLayout<Uniforms>.stride, options: .storageModeShared) }
        memcpy(uniformBuffer?.contents(), &uniforms, MemoryLayout<Uniforms>.stride)
    }
}
