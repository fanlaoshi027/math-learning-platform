import MetalKit
import UIKit
import simd

/// iPad Metal canvas with a CPU-generated brush mesh and transient prediction layer.
/// Actual samples are persisted; predicted samples are display-only.
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

    /// Rendering-only smoothing. Stored input and one-stroke recognition always use
    /// the original coalesced samples so smoothing can never alter document data.
    var smoothingEnabled = true
    var smoothingSubdivisions = 3

    var inputMode = MosuanPencilInputMode()
    var oneStrokeSettings = OneStrokeSettings()
    var strokeStyle = GraphicObject.Style()
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
        sampleCount = 4

        guard let device else { return }
        commandQueue = device.makeCommandQueue()
        guard let library = device.makeDefaultLibrary(),
              let vertex = library.makeFunction(name: "mosuanBoardVertex"),
              let fragment = library.makeFunction(name: "mosuanBoardFragment") else { return }

        let descriptor = MTLRenderPipelineDescriptor()
        descriptor.vertexFunction = vertex
        descriptor.fragmentFunction = fragment
        descriptor.colorAttachments[0].pixelFormat = colorPixelFormat
        descriptor.sampleCount = sampleCount
        descriptor.colorAttachments[0].isBlendingEnabled = true
        descriptor.colorAttachments[0].rgbBlendOperation = .add
        descriptor.colorAttachments[0].alphaBlendOperation = .add
        descriptor.colorAttachments[0].sourceRGBBlendFactor = .sourceAlpha
        descriptor.colorAttachments[0].destinationRGBBlendFactor = .oneMinusSourceAlpha
        descriptor.colorAttachments[0].sourceAlphaBlendFactor = .sourceAlpha
        descriptor.colorAttachments[0].destinationAlphaBlendFactor = .oneMinusSourceAlpha
        pipeline = try? device.makeRenderPipelineState(descriptor: descriptor)
        delegate = self
    }

    func connect(to input: MosuanPencilCanvasView) {
        inputMode = input.inputMode
        input.onPointerEvent = { [weak self] event in self?.receiveActual(event) }
        input.onPredictedPointerEvent = { [weak self] event in self?.receivePredicted(event) }
        input.onPan = { [weak self] delta in self?.pan(delta) }
        input.onZoom = { [weak self] factor, point in self?.zoom(factor, around: point) }
    }

    private func receiveActual(_ event: MosuanPointerEvent) {
        predicted.removeAll(keepingCapacity: true)

        switch event.phase {
        case .began:
            active.removeAll(keepingCapacity: true)
            active.append(event)
        case .changed:
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
        let committer = OneStrokeCommitter(settings: oneStrokeSettings)
        let object = committer.commit(points: points, style: strokeStyle)

        if object == nil { committed.append(finished) }
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

    private func effectiveWidth(for event: MosuanPointerEvent, previous: MosuanPointerEvent?) -> Float {
        let pressure = min(max(event.pressure, 0), 1)
        let pressureFactor: Float
        if pressure > 0.01 {
            pressureFactor = 0.68 + 0.68 * pressure
        } else if let previous {
            let dt = max(Float(event.timestamp - previous.timestamp), 1.0 / 240.0)
            let speed = simd_distance(event.position, previous.position) / dt
            let speedT = min(max((speed - 80) / (1800 - 80), 0), 1)
            pressureFactor = 1.18 + (0.58 - 1.18) * speedT
        } else {
            pressureFactor = 0.85
        }
        return max(Float(strokeStyle.strokeWidth) * pressureFactor, 0.5)
    }

    /// Catmull-Rom interpolation is used only for the display mesh. It preserves
    /// the original samples while removing visible polygonal corners during fast
    /// handwriting. Endpoints are duplicated so the visible stroke stays anchored
    /// to the actual Pencil contact points.
    private func smoothedEvents(for stroke: [MosuanPointerEvent]) -> [MosuanPointerEvent] {
        guard smoothingEnabled, stroke.count >= 3 else { return stroke }
        let subdivisions = min(max(smoothingSubdivisions, 1), 5)
        var result: [MosuanPointerEvent] = []
        result.reserveCapacity(stroke.count * subdivisions)

        func interpolate(_ a: SIMD2<Float>, _ b: SIMD2<Float>, _ c: SIMD2<Float>, _ d: SIMD2<Float>, _ t: Float) -> SIMD2<Float> {
            let t2 = t * t
            let t3 = t2 * t
            return 0.5 * ((2 * b) + (-a + c) * t + (2 * a - 5 * b + 4 * c - d) * t2 + (-a + 3 * b - 3 * c + d) * t3)
        }

        for index in 0..<(stroke.count - 1) {
            let p0 = index > 0 ? stroke[index - 1].position : stroke[index].position
            let p1 = stroke[index].position
            let p2 = stroke[index + 1].position
            let p3 = index + 2 < stroke.count ? stroke[index + 2].position : p2

            for step in 0..<subdivisions {
                let t = Float(step) / Float(subdivisions)
                var event = stroke[index]
                event.position = interpolate(p0, p1, p2, p3, t)
                event.pressure = stroke[index].pressure + (stroke[index + 1].pressure - stroke[index].pressure) * t
                event.timestamp = stroke[index].timestamp + (stroke[index + 1].timestamp - stroke[index].timestamp) * TimeInterval(t)
                result.append(event)
            }
        }
        if let last = stroke.last { result.append(last) }
        return result
    }

    private func brushVertices(for stroke: [MosuanPointerEvent], opacity: Float) -> [Vertex] {
        let renderStroke = smoothedEvents(for: stroke)
        guard !renderStroke.isEmpty else { return [] }
        let sides = 12
        var vertices: [Vertex] = []
        vertices.reserveCapacity(renderStroke.count * sides * 3 + max(renderStroke.count - 1, 0) * 6)

        let baseColor = strokeStyle.strokeColor
        let color = SIMD4<Float>(Float(baseColor.red), Float(baseColor.green), Float(baseColor.blue), Float(baseColor.alpha) * opacity)

        func screenPoint(_ point: SIMD2<Float>) -> SIMD2<Float> {
            point * canvasScale + canvasOffset
        }

        func appendDisk(center: SIMD2<Float>, radius: Float) {
            let c = screenPoint(center)
            for i in 0..<sides {
                let a0 = Float(i) * 2 * .pi / Float(sides)
                let a1 = Float(i + 1) * 2 * .pi / Float(sides)
                vertices.append(Vertex(position: canvasToNDC(c), color: color))
                vertices.append(Vertex(position: canvasToNDC(c + SIMD2<Float>(cos(a0), sin(a0)) * radius), color: color))
                vertices.append(Vertex(position: canvasToNDC(c + SIMD2<Float>(cos(a1), sin(a1)) * radius), color: color))
            }
        }

        for index in renderStroke.indices {
            let current = renderStroke[index]
            let previous = index > 0 ? renderStroke[index - 1] : nil
            let radius = effectiveWidth(for: current, previous: previous) * canvasScale * 0.5
            appendDisk(center: current.position, radius: radius)

            guard index > 0 else { continue }
            let previousEvent = renderStroke[index - 1]
            let a = screenPoint(previousEvent.position)
            let b = screenPoint(current.position)
            let delta = b - a
            let length = simd_length(delta)
            guard length > 0.001 else { continue }
            let normal = SIMD2<Float>(-delta.y, delta.x) / length
            let previousRadius = effectiveWidth(for: previousEvent, previous: index > 1 ? renderStroke[index - 2] : nil) * canvasScale * 0.5
            let r = max(radius, previousRadius)

            let p0 = a + normal * r
            let p1 = a - normal * r
            let p2 = b + normal * r
            let p3 = b - normal * r
            vertices.append(Vertex(position: canvasToNDC(p0), color: color))
            vertices.append(Vertex(position: canvasToNDC(p1), color: color))
            vertices.append(Vertex(position: canvasToNDC(p2), color: color))
            vertices.append(Vertex(position: canvasToNDC(p2), color: color))
            vertices.append(Vertex(position: canvasToNDC(p1), color: color))
            vertices.append(Vertex(position: canvasToNDC(p3), color: color))
        }
        return vertices
    }

    private func canvasToNDC(_ point: SIMD2<Float>) -> SIMD2<Float> {
        let size = SIMD2<Float>(Float(max(drawableSize.width, 1)), Float(max(drawableSize.height, 1)))
        return SIMD2(point.x / size.x * 2 - 1, 1 - point.y / size.y * 2)
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

        drawStrokeLayer(committed, opacity: 1, encoder: encoder)
        if !active.isEmpty { drawStrokeLayer([active], opacity: 1, encoder: encoder) }
        if !predicted.isEmpty { drawStrokeLayer([predicted], opacity: 0.35, encoder: encoder) }

        encoder?.endEncoding()
        commandBuffer?.present(drawable)
        commandBuffer?.commit()
    }

    private func drawStrokeLayer(
        _ strokes: [[MosuanPointerEvent]],
        opacity: Float,
        encoder: MTLRenderCommandEncoder?
    ) {
        for stroke in strokes where stroke.count > 0 {
            var data = brushVertices(for: stroke, opacity: opacity)
            guard !data.isEmpty,
                  let buffer = device?.makeBuffer(
                    bytes: &data,
                    length: MemoryLayout<Vertex>.stride * data.count,
                    options: .storageModeShared
                  ) else { continue }
            encoder?.setVertexBuffer(buffer, offset: 0, index: 0)
            encoder?.drawPrimitives(type: .triangle, vertexStart: 0, vertexCount: data.count)
        }
    }
}
