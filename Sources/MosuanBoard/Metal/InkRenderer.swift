import Metal
import MetalKit
import simd

final class InkRenderer: NSObject, MTKViewDelegate {
    private struct Uniforms { var viewportSize: SIMD2<Float> }
    private let device: any MTLDevice
    private let commandQueue: any MTLCommandQueue
    private let pipelineState: any MTLRenderPipelineState
    private var committedStrokes: [[InkPoint]] = []
    private var activeStroke: [InkPoint] = []
    private var vertices: [InkVertex] = []
    private var vertexBuffer: (any MTLBuffer)?
    private var uniformBuffer: (any MTLBuffer)?
    private var penStyle = PenStyle()

    init?(device: any MTLDevice) {
        guard let commandQueue = device.makeCommandQueue(),
              let library = device.makeDefaultLibrary(),
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
        self.device = device
        self.commandQueue = commandQueue
        self.pipelineState = pipelineState
        super.init()
    }

    func setPenStyle(_ style: PenStyle) {
        penStyle = style
        rebuildGeometry()
    }

    func setStroke(_ points: [InkPoint]) {
        activeStroke = points
        rebuildGeometry()
    }

    func commitStroke(_ points: [InkPoint]) {
        guard points.count >= 2 else { activeStroke.removeAll(); rebuildGeometry(); return }
        committedStrokes.append(points)
        activeStroke.removeAll(keepingCapacity: true)
        rebuildGeometry()
    }

    func mtkView(_ view: MTKView, drawableSizeWillChange size: CGSize) { updateUniformBuffer(for: view.bounds.size) }

    func draw(in view: MTKView) {
        guard let descriptor = view.currentRenderPassDescriptor,
              let drawable = view.currentDrawable,
              let commandBuffer = commandQueue.makeCommandBuffer(),
              let encoder = commandBuffer.makeRenderCommandEncoder(descriptor: descriptor) else { return }
        updateUniformBuffer(for: view.bounds.size)
        encoder.setRenderPipelineState(pipelineState)
        if let vertexBuffer { encoder.setVertexBuffer(vertexBuffer, offset: 0, index: 0) }
        if let uniformBuffer { encoder.setVertexBuffer(uniformBuffer, offset: 0, index: 1) }
        if !vertices.isEmpty { encoder.drawPrimitives(type: .triangle, vertexStart: 0, vertexCount: vertices.count) }
        encoder.endEncoding()
        commandBuffer.present(drawable)
        commandBuffer.commit()
    }

    private func rebuildGeometry() {
        var output: [InkVertex] = []
        let strokes = committedStrokes + (activeStroke.isEmpty ? [] : [activeStroke])
        for stroke in strokes where stroke.count >= 2 { appendStrokeGeometry(stroke, to: &output) }
        vertices = output
        if vertices.isEmpty { vertexBuffer = nil }
        else { vertexBuffer = device.makeBuffer(bytes: vertices, length: vertices.count * MemoryLayout<InkVertex>.stride, options: .storageModeShared) }
    }

    private func appendStrokeGeometry(_ stroke: [InkPoint], to output: inout [InkVertex]) {
        guard let first = stroke.first, let last = stroke.last else { return }
        for index in 0..<(stroke.count - 1) {
            let p0 = stroke[index], p1 = stroke[index + 1]
            let dx = p1.x - p0.x, dy = p1.y - p0.y
            let length = max(sqrt(dx * dx + dy * dy), 0.001)
            let nx = -dy / length, ny = dx / length
            let w0 = strokeWidth(p0.pressure), w1 = strokeWidth(p1.pressure)
            let a = SIMD2<Float>(p0.x + nx * w0, p0.y + ny * w0)
            let b = SIMD2<Float>(p0.x - nx * w0, p0.y - ny * w0)
            let c = SIMD2<Float>(p1.x + nx * w1, p1.y + ny * w1)
            let d = SIMD2<Float>(p1.x - nx * w1, p1.y - ny * w1)
            appendTriangle(a, b, c, to: &output)
            appendTriangle(c, b, d, to: &output)
        }
        appendDisk(center: SIMD2<Float>(first.x, first.y), radius: strokeWidth(first.pressure), to: &output)
        appendDisk(center: SIMD2<Float>(last.x, last.y), radius: strokeWidth(last.pressure), to: &output)
        if stroke.count > 2 {
            for point in stroke.dropFirst().dropLast() {
                appendDisk(center: SIMD2<Float>(point.x, point.y), radius: strokeWidth(point.pressure), to: &output)
            }
        }
    }

    private func strokeWidth(_ pressure: Float) -> Float {
        let p = max(0, min(1, pressure))
        let curved = pow(p, Float(max(0.25, penStyle.pressureCurve)))
        if !penStyle.pressureEnabled { return Float(max(0.5, penStyle.width / 2)) }
        return Float(max(0.5, penStyle.width * (0.45 + 0.75 * CGFloat(curved))))
    }

    private func appendTriangle(_ a: SIMD2<Float>, _ b: SIMD2<Float>, _ c: SIMD2<Float>, to output: inout [InkVertex]) {
        let color = metalColor()
        output += [InkVertex(position: a, color: color), InkVertex(position: b, color: color), InkVertex(position: c, color: color)]
    }

    private func appendDisk(center: SIMD2<Float>, radius: Float, to output: inout [InkVertex]) {
        let color = metalColor()
        let segments = 16
        let step = Float.pi * 2 / Float(segments)
        for index in 0..<segments {
            let a = Float(index) * step, b = Float(index + 1) * step
            output += [
                InkVertex(position: center, color: color),
                InkVertex(position: center + SIMD2<Float>(cos(a), sin(a)) * radius, color: color),
                InkVertex(position: center + SIMD2<Float>(cos(b), sin(b)) * radius, color: color)
            ]
        }
    }

    private func metalColor() -> SIMD4<Float> {
        SIMD4<Float>(
            Float(penStyle.color.red),
            Float(penStyle.color.green),
            Float(penStyle.color.blue),
            Float(penStyle.color.alpha * penStyle.opacity)
        )
    }

    private func updateUniformBuffer(for size: CGSize) {
        var uniforms = Uniforms(viewportSize: SIMD2<Float>(Float(max(size.width, 1)), Float(max(size.height, 1))))
        if uniformBuffer == nil { uniformBuffer = device.makeBuffer(length: MemoryLayout<Uniforms>.stride, options: .storageModeShared) }
        memcpy(uniformBuffer?.contents(), &uniforms, MemoryLayout<Uniforms>.stride)
    }
}
