import MetalKit
import UIKit
import simd

/// iPad Metal canvas shell.
///
/// The view deliberately keeps three stroke buffers separate:
/// - committed: durable page content
/// - active: the current real Pencil stroke
/// - predicted: temporary latency compensation only
///
/// Predicted samples are cleared as soon as a real sample arrives and are never
/// exposed as page content or passed to undo/history.
final class MosuanMetalCanvasView: MTKView {
    private struct Vertex {
        var position: SIMD2<Float>
        var color: SIMD4<Float>
    }

    private var commandQueue: MTLCommandQueue?
    private var pipeline: MTLRenderPipelineState?
    private var committed: [[MosuanPointerEvent]] = []
    private var active: [MosuanPointerEvent] = []
    private var predicted: [MosuanPointerEvent] = []

    private var canvasScale: Float = 1
    private var canvasOffset: SIMD2<Float> = .zero

    var inputMode = MosuanPencilInputMode()
    var oneStrokeSettings = OneStrokeSettings()
    var strokeStyle = GraphicObject.Style()

    /// Called only after a real Pencil stroke has ended and has been committed.
    var onCommittedStroke: (([MosuanPointerEvent], GraphicObject?) -> Void)?

    override init(frame: CGRect, device: MTLDevice? = MTLCreateSystemDefaultDevice()) {
        super.init(frame: frame, device: device)
        configureMetal()
    }

    required init(coder: NSCoder) {
        super.init(coder: coder)
        configureMetal()
    }

    private func configureMetal() {
        framebufferOnly = true
        enableSetNeedsDisplay = false
        isPaused = false
        isMultipleTouchEnabled = true
        colorPixelFormat = .bgra8Unorm
        preferredFramesPerSecond = 120

        guard let device else { return }
        commandQueue = device.makeCommandQueue()

        guard let library = device.makeDefaultLibrary() else { return }
        let vertex = library.makeFunction(name: "mosuanBoardVertex")
        let fragment = library.makeFunction(name: "mosuanBoardFragment")
        guard let vertex, let fragment else { return }

        let descriptor = MTLRenderPipelineDescriptor()
        descriptor.vertexFunction = vertex
        descriptor.fragmentFunction = fragment
        descriptor.colorAttachments[0].pixelFormat = colorPixelFormat
        pipeline = try? device.makeRenderPipelineState(descriptor: descriptor)
        delegate = self
    }

    func connect(to input: MosuanPencilCanvasView) {
        input.onPointerEvent = { [weak self] event in
            self?.receiveActual(event)
        }
        input.onPredictedPointerEvent = { [weak self] event in
            self?.receivePredicted(event)
        }
        input.onPan = { [weak self] delta in
            self?.pan(delta)
        }
        input.onZoom = { [weak self] factor, point in
            self?.zoom(factor, around: point)
        }
    }

    private func receiveActual(_ event: MosuanPointerEvent) {
        predicted.removeAll(keepingCapacity: true)

        switch event.phase {
        case .began:
            active.removeAll(keepingCapacity: true)
            active.append(event)
        case .changed:
            guard !active.isEmpty else { active.append(event); return }
            active.append(event)
        case .ended:
            active.append(event)
            finishActiveStroke()
        case .cancelled:
            active.removeAll(keepingCapacity: true)
        }

        setNeedsDisplay()
    }

    private func receivePredicted(_ event: MosuanPointerEvent) {
        // Never append prediction after the real stroke has ended.
        guard !active.isEmpty else { return }
        predicted.append(event)
        setNeedsDisplay()
    }

    private func finishActiveStroke() {
        let finished = active
        active.removeAll(keepingCapacity: true)
        predicted.removeAll(keepingCapacity: true)
        guard finished.count >= 2 else { return }

        let points = finished.map { CGPoint(x: CGFloat($0.position.x), y: CGFloat($0.position.y)) }
        var committer = OneStrokeCommitter(settings: oneStrokeSettings)
        let object = committer.commit(points: points, style: strokeStyle)

        // Store the original stroke when shape recognition does not confidently
        // replace it. A recognized object is reported to the document layer.
        if object == nil {
            committed.append(finished)
        }
        onCommittedStroke?(finished, object)
    }

    func clearPage() {
        committed.removeAll(keepingCapacity: true)
        active.removeAll(keepingCapacity: true)
        predicted.removeAll(keepingCapacity: true)
        setNeedsDisplay()
    }

    func pan(_ delta: CGPoint) {
        canvasOffset += SIMD2<Float>(Float(delta.x), Float(delta.y))
        setNeedsDisplay()
    }

    func zoom(_ factor: CGFloat, around point: CGPoint) {
        guard factor.isFinite, factor > 0 else { return }
        let oldScale = canvasScale
        let newScale = min(max(oldScale * Float(factor), 0.25), 6)
        let localPoint = (SIMD2<Float>(Float(point.x), Float(point.y)) - canvasOffset) / oldScale
        canvasScale = newScale
        canvasOffset = SIMD2<Float>(Float(point.x), Float(point.y)) - localPoint * newScale
        setNeedsDisplay()
    }

    private func vertices(for strokes: [[MosuanPointerEvent]], color: SIMD4<Float>) -> [Vertex] {
        strokes.flatMap { stroke in
            stroke.map {
                Vertex(
                    position: canvasToNDC($0.position),
                    color: color
                )
            }
        }
    }

    private func canvasToNDC(_ point: SIMD2<Float>) -> SIMD2<Float> {
        let drawableSize = SIMD2<Float>(Float(max(drawableSize.width, 1)), Float(max(drawableSize.height, 1)))
        let p = point * canvasScale + canvasOffset
        return SIMD2(
            p.x / drawableSize.x * 2 - 1,
            1 - p.y / drawableSize.y * 2
        )
    }
}

extension MosuanMetalCanvasView: MTKViewDelegate {
    func mtkView(_ view: MTKView, drawableSizeWillChange size: CGSize) {}

    func draw(in view: MTKView) {
        guard let drawable = currentDrawable,
              let pass = currentRenderPassDescriptor,
              let commandQueue,
              let pipeline else { return }

        let commandBuffer = commandQueue.makeCommandBuffer()
        let encoder = commandBuffer?.makeRenderCommandEncoder(descriptor: pass)
        encoder?.setRenderPipelineState(pipeline)

        drawStrokeLayer(committed, color: SIMD4<Float>(0, 0, 0, 1), encoder: encoder)
        drawStrokeLayer(active.isEmpty ? [] : [active], color: SIMD4<Float>(0, 0, 0, 1), encoder: encoder)
        drawStrokeLayer(predicted.isEmpty ? [] : [predicted], color: SIMD4<Float>(0, 0, 0, 0.35), encoder: encoder)

        encoder?.endEncoding()
        if let drawable { commandBuffer?.present(drawable) }
        commandBuffer?.commit()
    }

    private func drawStrokeLayer(
        _ strokes: [[MosuanPointerEvent]],
        color: SIMD4<Float>,
        encoder: MTLRenderCommandEncoder?
    ) {
        guard !strokes.isEmpty else { return }
        var data = vertices(for: strokes, color: color)
        guard !data.isEmpty else { return }
        guard let buffer = device?.makeBuffer(
            bytes: &data,
            length: MemoryLayout<Vertex>.stride * data.count,
            options: .storageModeShared
        ) else { return }
        encoder?.setVertexBuffer(buffer, offset: 0, index: 0)
        encoder?.drawPrimitives(type: .lineStrip, vertexStart: 0, vertexCount: data.count)
    }
}
