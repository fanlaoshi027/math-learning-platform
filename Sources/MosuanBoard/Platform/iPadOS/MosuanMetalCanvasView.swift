import MetalKit
import UIKit
import simd

/// iPad Metal canvas focused on natural handwriting: high-frequency input,
/// rendering-only smoothing, pressure/speed dynamics, and transient prediction.
final class MosuanMetalCanvasView: MTKView {
    private struct Vertex {
        var position: SIMD2<Float> // NDC position used by the vertex stage.
        var point: SIMD2<Float>    // Screen-space pixel coordinate for SDF coverage.
        var p0: SIMD2<Float>       // Circle center or segment start.
        var p1: SIMD2<Float>       // Segment end; equal to p0 for a circle.
        var radius: Float
        var kind: UInt32            // 0 = round point, 1 = round segment.
        var color: SIMD4<Float>
    }

    private var commandQueue: MTLCommandQueue?
    private var pipeline: MTLRenderPipelineState?
    private var committed: [[MosuanPointerEvent]] = []
    private var active: [MosuanPointerEvent] = []
    private var predicted: [MosuanPointerEvent] = []
    private var canvasScale: Float = 1
    private var canvasOffset: SIMD2<Float> = .zero

    /// Display-only CPU mesh cache. Document samples remain the source of truth.
    private var committedMeshCache: [UInt64: [Vertex]] = [:]

    var smoothingEnabled = true
    var smoothingSubdivisions = 3
    var dynamicsEnabled = true
    var entryTaperSamples = 5
    var exitTaperSamples = 5
    var turnWidthRecovery: Float = 0.82

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

        if object == nil {
            committed.append(finished)
            invalidateCommittedMeshCache()
        }
        onCommittedStroke?(finished, object)
    }

    func clearPage() {
        committed.removeAll(keepingCapacity: true)
        active.removeAll(keepingCapacity: true)
        predicted.removeAll(keepingCapacity: true)
        invalidateCommittedMeshCache()
        setNeedsDisplay()
    }

    func pan(_ delta: CGPoint) {
        canvasOffset += SIMD2<Float>(Float(delta.x), Float(delta.y))
        invalidateCommittedMeshCache()
        setNeedsDisplay()
    }

    func zoom(_ factor: CGFloat, around point: CGPoint) {
        guard factor.isFinite, factor > 0 else { return }
        let oldScale = canvasScale
        let newScale = min(max(oldScale * Float(factor), 0.25), 6)
        let localPoint = (SIMD2<Float>(Float(point.x), Float(point.y)) - canvasOffset) / oldScale
        canvasScale = newScale
        canvasOffset = SIMD2<Float>(Float(point.x), Float(point.y)) - localPoint * newScale
        invalidateCommittedMeshCache()
        setNeedsDisplay()
    }

    func invalidateCommittedMeshCache() {
        committedMeshCache.removeAll(keepingCapacity: true)
    }

    private func baseWidth(for event: MosuanPointerEvent, previous: MosuanPointerEvent?) -> Float {
        let pressure = min(max(event.pressure, 0), 1)
        if pressure > 0.01 {
            return Float(strokeStyle.strokeWidth) * (0.62 + 0.76 * pressure)
        }
        guard let previous else { return Float(strokeStyle.strokeWidth) * 0.88 }
        let dt = max(Float(event.timestamp - previous.timestamp), 1.0 / 240.0)
        let speed = simd_distance(event.position, previous.position) / dt
        let speedT = min(max((speed - 80) / (1800 - 80), 0), 1)
        return Float(strokeStyle.strokeWidth) * (1.16 - 0.60 * speedT)
    }

    private func smoothedEvents(for stroke: [MosuanPointerEvent]) -> [MosuanPointerEvent] {
        guard smoothingEnabled, stroke.count >= 3 else { return stroke }
        let subdivisions = min(max(smoothingSubdivisions, 1), 5)
        var result: [MosuanPointerEvent] = []
        result.reserveCapacity(stroke.count * subdivisions + 1)

        func lerp(_ a: SIMD2<Float>, _ b: SIMD2<Float>, _ t: Float) -> SIMD2<Float> {
            a + (b - a) * t
        }

        func catmull(_ p0: SIMD2<Float>, _ p1: SIMD2<Float>, _ p2: SIMD2<Float>, _ p3: SIMD2<Float>, _ t: Float) -> SIMD2<Float> {
            let t2 = t * t
            let t3 = t2 * t
            return 0.5 * ((2 * p1) + (-p0 + p2) * t +
                (2 * p0 - 5 * p1 + 4 * p2 - p3) * t2 +
                (-p0 + 3 * p1 - 3 * p2 + p3) * t3)
        }

        for index in 0..<(stroke.count - 1) {
            let p0 = index > 0 ? stroke[index - 1].position : stroke[index].position
            let p1 = stroke[index].position
            let p2 = stroke[index + 1].position
            let p3 = index + 2 < stroke.count ? stroke[index + 2].position : p2
            let incomingVector = p1 - p0
            let outgoingVector = p3 - p2
            let incomingLength = simd_length(incomingVector)
            let outgoingLength = simd_length(outgoingVector)
            let turn: Float
            if incomingLength > 0.001, outgoingLength > 0.001 {
                let incoming = incomingVector / incomingLength
                let outgoing = outgoingVector / outgoingLength
                turn = min(max((1 - simd_dot(incoming, outgoing)) * 0.5, 0), 1)
            } else {
                turn = 0
            }

            for step in 0..<subdivisions {
                let t = Float(step) / Float(subdivisions)
                var event = stroke[index]
                let curvePoint = catmull(p0, p1, p2, p3, t)
                let damp = min(turn * 0.70, 0.70)
                event.position = lerp(curvePoint, lerp(p1, p2, t), damp)
                event.pressure = stroke[index].pressure + (stroke[index + 1].pressure - stroke[index].pressure) * t
                event.timestamp = stroke[index].timestamp +
                    (stroke[index + 1].timestamp - stroke[index].timestamp) * TimeInterval(t)
                result.append(event)
            }
        }
        if let last = stroke.last { result.append(last) }
        return result
    }

    private func widthEnvelope(for stroke: [MosuanPointerEvent]) -> [Float] {
        guard !stroke.isEmpty else { return [] }
        var raw = [Float](repeating: Float(strokeStyle.strokeWidth), count: stroke.count)
        for i in stroke.indices {
            raw[i] = baseWidth(for: stroke[i], previous: i > 0 ? stroke[i - 1] : nil)
            if dynamicsEnabled, i > 0, i + 1 < stroke.count {
                let aVector = stroke[i].position - stroke[i - 1].position
                let bVector = stroke[i + 1].position - stroke[i].position
                let aLength = simd_length(aVector)
                let bLength = simd_length(bVector)
                if aLength > 0.001, bLength > 0.001 {
                    let a = aVector / aLength
                    let b = bVector / bLength
                    let dot = min(max(simd_dot(a, b), -1), 1)
                    let turn = (1 - dot) * 0.5
                    raw[i] *= 1 - min(max(turn, 0), 1) * (1 - turnWidthRecovery)
                }
            }
        }

        guard dynamicsEnabled else { return raw }
        var smoothed = raw
        for i in stroke.indices {
            var sum: Float = 0
            var weight: Float = 0
            for offset in -2...2 {
                let j = i + offset
                guard stroke.indices.contains(j) else { continue }
                let w: Float = offset == 0 ? 3 : (abs(offset) == 1 ? 2 : 1)
                sum += raw[j] * w
                weight += w
            }
            smoothed[i] = sum / max(weight, 1)
        }

        let entryCount = max(entryTaperSamples, 1)
        let exitCount = max(exitTaperSamples, 1)
        for i in stroke.indices {
            let entryT = min(Float(i + 1) / Float(entryCount), 1)
            let exitT = min(Float(stroke.count - i) / Float(exitCount), 1)
            let taper = min(1, min(0.72 + 0.28 * entryT, 0.72 + 0.28 * exitT))
            smoothed[i] *= taper
        }
        return smoothed
    }

    private func brushVertices(for stroke: [MosuanPointerEvent], opacity: Float) -> [Vertex] {
        let renderStroke = smoothedEvents(for: stroke)
        guard !renderStroke.isEmpty else { return [] }
        let widths = widthEnvelope(for: renderStroke)
        var vertices: [Vertex] = []
        vertices.reserveCapacity(renderStroke.count * 6 + max(renderStroke.count - 1, 0) * 6)

        let baseColor = strokeStyle.strokeColor
        let color = SIMD4<Float>(Float(baseColor.red), Float(baseColor.green), Float(baseColor.blue), Float(baseColor.alpha) * opacity)

        func screenPoint(_ point: SIMD2<Float>) -> SIMD2<Float> {
            point * canvasScale + canvasOffset
        }

        func appendTriangle(_ a: SIMD2<Float>, _ b: SIMD2<Float>, _ c: SIMD2<Float>, p0: SIMD2<Float>, p1: SIMD2<Float>, radius: Float, kind: UInt32) {
            let primitive = { (point: SIMD2<Float>) -> Vertex in
                Vertex(position: canvasToNDC(point), point: point, p0: p0, p1: p1, radius: radius, kind: kind, color: color)
            }
            vertices.append(primitive(a))
            vertices.append(primitive(b))
            vertices.append(primitive(c))
        }

        func appendCircle(center: SIMD2<Float>, radius: Float) {
            let minPoint = center - SIMD2<Float>(radius, radius)
            let maxPoint = center + SIMD2<Float>(radius, radius)
            appendTriangle(minPoint, SIMD2<Float>(maxPoint.x, minPoint.y), maxPoint, p0: center, p1: center, radius: radius, kind: 0)
            appendTriangle(minPoint, maxPoint, SIMD2<Float>(minPoint.x, maxPoint.y), p0: center, p1: center, radius: radius, kind: 0)
        }

        for index in renderStroke.indices {
            let current = renderStroke[index]
            let currentPoint = screenPoint(current.position)
            let currentRadius = max(widths[index], 0.5) * canvasScale * 0.5
            appendCircle(center: currentPoint, radius: currentRadius)

            guard index > 0 else { continue }
            let previous = renderStroke[index - 1]
            let a = screenPoint(previous.position)
            let b = currentPoint
            let delta = b - a
            let length = simd_length(delta)
            guard length > 0.001 else { continue }

            let normal = SIMD2<Float>(-delta.y, delta.x) / length
            let r0 = max(widths[index - 1], 0.5) * canvasScale * 0.5
            let r1 = currentRadius
            let radius = max(r0, r1)
            let minPoint = SIMD2<Float>(min(a.x, b.x), min(a.y, b.y)) - SIMD2<Float>(radius, radius)
            let maxPoint = SIMD2<Float>(max(a.x, b.x), max(a.y, b.y)) + SIMD2<Float>(radius, radius)
            appendTriangle(minPoint, SIMD2<Float>(maxPoint.x, minPoint.y), maxPoint, p0: a, p1: b, radius: radius, kind: 1)
            appendTriangle(minPoint, maxPoint, SIMD2<Float>(minPoint.x, maxPoint.y), p0: a, p1: b, radius: radius, kind: 1)

            // Keep the variable-width join fully covered by the endpoint circles.
            _ = normal
            _ = r0
            _ = r1
        }
        return vertices
    }

    private func canvasToNDC(_ point: SIMD2<Float>) -> SIMD2<Float> {
        let size = SIMD2<Float>(Float(max(drawableSize.width, 1)), Float(max(drawableSize.height, 1)))
        return SIMD2(point.x / size.x * 2 - 1, 1 - point.y / size.y * 2)
    }

    private func cacheKey(for stroke: [MosuanPointerEvent]) -> UInt64 {
        var hash: UInt64 = 1469598103934665603
        func mix(_ value: UInt64) {
            hash ^= value
            hash &*= 1099511628211
        }
        mix(UInt64(stroke.count))
        for sample in stroke {
            mix(UInt64(sample.position.x.bitPattern))
            mix(UInt64(sample.position.y.bitPattern))
            mix(UInt64(sample.pressure.bitPattern))
            mix(UInt64(sample.timestamp.bitPattern))
        }
        mix(UInt64(canvasScale.bitPattern))
        mix(UInt64(canvasOffset.x.bitPattern))
        mix(UInt64(canvasOffset.y.bitPattern))
        mix(UInt64(strokeStyle.strokeWidth.bitPattern))
        mix(UInt64(strokeStyle.strokeColor.red.bitPattern))
        mix(UInt64(strokeStyle.strokeColor.green.bitPattern))
        mix(UInt64(strokeStyle.strokeColor.blue.bitPattern))
        mix(UInt64(strokeStyle.strokeColor.alpha.bitPattern))
        mix(smoothingEnabled ? 1 : 0)
        mix(UInt64(smoothingSubdivisions))
        mix(dynamicsEnabled ? 1 : 0)
        mix(UInt64(entryTaperSamples))
        mix(UInt64(exitTaperSamples))
        mix(UInt64(turnWidthRecovery.bitPattern))
        return hash
    }
}

extension MosuanMetalCanvasView: MTKViewDelegate {
    func mtkView(_ view: MTKView, drawableSizeWillChange size: CGSize) {
        invalidateCommittedMeshCache()
    }

    func draw(in view: MTKView) {
        guard let drawable = currentDrawable,
              let pass = currentRenderPassDescriptor,
              let commandQueue,
              let pipeline else { return }

        let commandBuffer = commandQueue.makeCommandBuffer()
        let encoder = commandBuffer?.makeRenderCommandEncoder(descriptor: pass)
        encoder?.setRenderPipelineState(pipeline)

        drawStrokeLayer(committed, opacity: 1, encoder: encoder, cacheCommitted: true)
        if !active.isEmpty { drawStrokeLayer([active], opacity: 1, encoder: encoder, cacheCommitted: false) }
        if !predicted.isEmpty { drawStrokeLayer([predicted], opacity: 0.35, encoder: encoder, cacheCommitted: false) }

        encoder?.endEncoding()
        commandBuffer?.present(drawable)
        commandBuffer?.commit()
    }

    private func drawStrokeLayer(
        _ strokes: [[MosuanPointerEvent]],
        opacity: Float,
        encoder: MTLRenderCommandEncoder?,
        cacheCommitted: Bool
    ) {
        for stroke in strokes where !stroke.isEmpty {
            let data: [Vertex]
            if cacheCommitted {
                let key = cacheKey(for: stroke)
                if let cached = committedMeshCache[key] {
                    data = cached
                } else {
                    let generated = brushVertices(for: stroke, opacity: opacity)
                    committedMeshCache[key] = generated
                    data = generated
                }
            } else {
                data = brushVertices(for: stroke, opacity: opacity)
            }

            guard !data.isEmpty,
                  let buffer = device?.makeBuffer(
                    bytes: data,
                    length: MemoryLayout<Vertex>.stride * data.count,
                    options: .storageModeShared
                  ) else { continue }
            encoder?.setVertexBuffer(buffer, offset: 0, index: 0)
            encoder?.drawPrimitives(type: .triangle, vertexStart: 0, vertexCount: data.count)
        }
    }
}
