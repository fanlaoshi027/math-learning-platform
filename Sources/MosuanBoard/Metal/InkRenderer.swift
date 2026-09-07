import Metal
import MetalKit
import simd

final class InkRenderer: NSObject, MTKViewDelegate {
    private let device: any MTLDevice
    private let commandQueue: any MTLCommandQueue
    private let pipelineState: any MTLRenderPipelineState

    private var vertices: [InkVertex] = []
    private var vertexBuffer: (any MTLBuffer)?

    init?(view: MTKView) {
        guard let device = MTLCreateSystemDefaultDevice(),
              let commandQueue = device.makeCommandQueue() else {
            return nil
        }

        guard let library = device.makeDefaultLibrary(),
              let vertexFunction = library.makeFunction(name: "inkVertex"),
              let fragmentFunction = library.makeFunction(name: "inkFragment") else {
            return nil
        }

        let descriptor = MTLRenderPipelineDescriptor()
        descriptor.vertexFunction = vertexFunction
        descriptor.fragmentFunction = fragmentFunction
        descriptor.colorAttachments[0].pixelFormat = view.colorPixelFormat

        guard let pipelineState = try? device.makeRenderPipelineState(descriptor: descriptor) else {
            return nil
        }

        self.device = device
        self.commandQueue = commandQueue
        self.pipelineState = pipelineState
        super.init()

        view.device = device
        view.delegate = self
        view.colorPixelFormat = .bgra8Unorm
        view.clearColor = MTLClearColor(red: 1, green: 1, blue: 1, alpha: 1)
        view.enableSetNeedsDisplay = true
        view.isPaused = true
    }

    func setPolyline(points: [CGPoint], pressure: [CGFloat]) {
        guard points.count >= 2 else {
            vertices.removeAll(keepingCapacity: true)
            vertexBuffer = nil
            return
        }

        var output: [InkVertex] = []
        output.reserveCapacity((points.count - 1) * 6)

        for index in 0..<(points.count - 1) {
            let p0 = points[index]
            let p1 = points[index + 1]
            let width0 = max(1.0, min(12.0, pressure[index] * 10.0))
            let width1 = max(1.0, min(12.0, pressure[index + 1] * 10.0))

            let direction = simd_normalize(SIMD2<Float>(Float(p1.x - p0.x), Float(p1.y - p0.y)))
            let normal = SIMD2<Float>(-direction.y, direction.x)

            let a = SIMD2<Float>(Float(p0.x), Float(p0.y)) + normal * Float(width0 * 0.5)
            let b = SIMD2<Float>(Float(p0.x), Float(p0.y)) - normal * Float(width0 * 0.5)
            let c = SIMD2<Float>(Float(p1.x), Float(p1.y)) + normal * Float(width1 * 0.5)
            let d = SIMD2<Float>(Float(p1.x), Float(p1.y)) - normal * Float(width1 * 0.5)

            let color = SIMD4<Float>(0, 0, 0, 1)
            output.append(InkVertex(position: a, color: color))
            output.append(InkVertex(position: b, color: color))
            output.append(InkVertex(position: c, color: color))
            output.append(InkVertex(position: c, color: color))
            output.append(InkVertex(position: b, color: color))
            output.append(InkVertex(position: d, color: color))
        }

        vertices = output
        uploadVertices()
    }

    func mtkView(_ view: MTKView, drawableSizeWillChange size: CGSize) {}

    func draw(in view: MTKView) {
        guard let drawable = view.currentDrawable,
              let descriptor = view.currentRenderPassDescriptor,
              let commandBuffer = commandQueue.makeCommandBuffer(),
              let encoder = commandBuffer.makeRenderCommandEncoder(descriptor: descriptor) else {
            return
        }

        encoder.setRenderPipelineState(pipelineState)
        if let vertexBuffer {
            encoder.setVertexBuffer(vertexBuffer, offset: 0, index: 0)
            encoder.drawPrimitives(type: .triangle, vertexStart: 0, vertexCount: vertices.count)
        }
        encoder.endEncoding()
        commandBuffer.present(drawable)
        commandBuffer.commit()
    }

    private func uploadVertices() {
        guard !vertices.isEmpty else {
            vertexBuffer = nil
            return
        }

        let length = vertices.count * MemoryLayout<InkVertex>.stride
        vertexBuffer = device.makeBuffer(bytes: vertices, length: length, options: .storageModeShared)
    }
}
