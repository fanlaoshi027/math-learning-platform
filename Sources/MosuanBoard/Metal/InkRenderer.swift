import Metal
import MetalKit
import simd

final class InkRenderer: NSObject, MTKViewDelegate {
    private struct Uniforms {
        var viewportSize: SIMD2<Float>
    }

    private let device: any MTLDevice
    private let commandQueue: any MTLCommandQueue
    private let pipelineState: any MTLRenderPipelineState

    private var committedStrokes: [[InkPoint]] = []
    private var activeStroke: [InkPoint] = []
    private var vertices: [InkVertex] = []
    private var vertexBuffer: (any MTLBuffer)?
    private var uniformBuffer: (any MTLBuffer)?

    init?(device: any MTLDevice) {
        guard let commandQueue = device.makeCommandQueue(),
              let library = device.makeDefaultLibrary(),
              let vertexFunction = library.makeFunction(name: "inkVertex"),
              let fragmentFunction = library.makeFunction(name: "inkFragment") else {
            return nil
        }

        let descriptor = MTLRenderPipelineDescriptor()
        descriptor.vertexFunction = vertexFunction
        descriptor.fragmentFunction = fragmentFunction
        descriptor.colorAttachments[0].pixelFormat = .bgra8Unorm
        descriptor.colorAttachments[0].isBlendingEnabled = true
        descriptor.colorAttachments[0].sourceRGBBlendFactor = .sourceAlpha
        descriptor.colorAttachments[0].destinationRGBBlendFactor = .oneMinusSourceAlpha
        descriptor.colorAttachments[0].sourceAlphaBlendFactor = .sourceAlpha
        descriptor.colorAttachments[0].destinationAlphaBlendFactor = .oneMinusSourceAlpha

        guard let pipelineState = try? device.makeRenderPipelineState(descriptor: descriptor) else {
            return nil
        }

        self.device = device
        self.commandQueue = commandQueue
        self.pipelineState = pipelineState
        super.init()
    }

    func setStroke(_ points: [InkPoint]) {
        activeStroke = points
        rebuildGeometry()
    }

    func commitStroke(_ points: [InkPoint]) {
        guard points.count >= 2 else {
            activeStroke.removeAll(keepingCapacity: true)
            rebuildGeometry()
            return
        }
        committedStrokes.append(points)
        activeStroke.removeAll(keepingCapacity: true)
        rebuildGeometry()
    }

    func mtkView(_ view: MTKView, drawableSizeWillChange size: CGSize) {
        updateUniformBuffer(for: size)
    }

    func draw(in view: MTKView) {
        guard let descriptor = view.currentRenderPassDescriptor,
              let drawable = view.currentDrawable,
              let commandBuffer = commandQueue.makeCommandBuffer(),
              let encoder = commandBuffer.makeRenderCommandEncoder(descriptor: descriptor) else {
            return
        }

        updateUniformBuffer(for: view.bounds.size)

        encoder.setRenderPipelineState(pipelineState)
        if let vertexBuffer {
            encoder.setVertexBuffer(vertexBuffer, offset: 0, index: 0)
        }
        if let uniformBuffer {
            encoder.setVertexBuffer(uniformBuffer, offset: 0, index: 1)
        }
        if !vertices.isEmpty {
            encoder.drawPrimitives(type: .triangle, vertexStart: 0, vertexCount: vertices.count)
        }
        encoder.endEncoding()
        commandBuffer.present(drawable)
        commandBuffer.commit()
    }

    private func rebuildGeometry() {
        var output: [InkVertex] = []
        let allStrokes = committedStrokes + (activeStroke.isEmpty ? [] : [activeStroke])

        for stroke in allStrokes where stroke.count >= 2 {
            for index in 0..<(stroke.count - 1) {
                let p0 = stroke[index]
                let p1 = stroke[index + 1]
                let dx = p1.x - p0.x
                let dy = p1.y - p0.y
                let length = max(sqrt(dx * dx + dy * dy), 0.001)
                let nx = -dy / length
                let ny = dx / length
                let width0 = 0.75 + 2.75 * max(0, min(1, p0.pressure))
                let width1 = 0.75 + 2.75 * max(0, min(1, p1.pressure))

                let a = SIMD2<Float>(p0.x + nx * width0, p0.y + ny * width0)
                let b = SIMD2<Float>(p0.x - nx * width0, p0.y - ny * width0)
                let c = SIMD2<Float>(p1.x + nx * width1, p1.y + ny * width1)
                let d = SIMD2<Float>(p1.x - nx * width1, p1.y - ny * width1)
                let color = SIMD4<Float>(0, 0, 0, 1)

                output.append(InkVertex(position: a, color: color))
                output.append(InkVertex(position: b, color: color))
                output.append(InkVertex(position: c, color: color))
                output.append(InkVertex(position: c, color: color))
                output.append(InkVertex(position: b, color: color))
                output.append(InkVertex(position: d, color: color))
            }
        }

        vertices = output
        if vertices.isEmpty {
            vertexBuffer = nil
        } else {
            let length = vertices.count * MemoryLayout<InkVertex>.stride
            vertexBuffer = device.makeBuffer(bytes: vertices, length: length, options: .storageModeShared)
        }
    }

    private func updateUniformBuffer(for size: CGSize) {
        var uniforms = Uniforms(viewportSize: SIMD2<Float>(Float(max(size.width, 1)), Float(max(size.height, 1))))
        if uniformBuffer == nil {
            uniformBuffer = device.makeBuffer(length: MemoryLayout<Uniforms>.stride, options: .storageModeShared)
        }
        memcpy(uniformBuffer?.contents(), &uniforms, MemoryLayout<Uniforms>.stride)
    }
}
