import UIKit
import simd

/// Native iPad input surface for Mosuan.
///
/// Pencil draws; finger navigation never becomes ink. Actual Pencil samples use
/// UIKit coalesced touches for high-fidelity input, while predicted samples are
/// exposed separately as temporary preview data and are never committed.
final class MosuanPencilCanvasView: UIView {
    enum GestureMode {
        case pencilWriting
        case fingerNavigation
    }

    var onPointerEvent: ((MosuanPointerEvent) -> Void)?
    /// Temporary predicted samples. The renderer must discard/replace these when
    /// the next real event arrives; they must never enter saved page content.
    var onPredictedPointerEvent: ((MosuanPointerEvent) -> Void)?
    var onPan: ((CGPoint) -> Void)?
    var onZoom: ((CGFloat, CGPoint) -> Void)?

    private(set) var gestureMode: GestureMode = .fingerNavigation
    private var activePencilTouch: UITouch?
    private var lastNavigationPoint: CGPoint?

    override init(frame: CGRect) {
        super.init(frame: frame)
        configure()
    }

    required init?(coder: NSCoder) {
        super.init(coder: coder)
        configure()
    }

    private func configure() {
        isMultipleTouchEnabled = true
        backgroundColor = .clear
        delaysContentTouches = false
    }

    override func touchesBegan(_ touches: Set<UITouch>, with event: UIEvent?) {
        guard let touch = touches.first else { return }

        if touch.type == .pencil {
            activePencilTouch = touch
            gestureMode = .pencilWriting
            emitActualSamples(for: touch, event: event, fallbackPhase: .began)
            emitPredictedSamples(for: touch, event: event)
            return
        }

        guard activePencilTouch == nil else { return }
        gestureMode = .fingerNavigation
        lastNavigationPoint = averageLocation(of: touches)
    }

    override func touchesMoved(_ touches: Set<UITouch>, with event: UIEvent?) {
        if let pencil = activePencilTouch, touches.contains(pencil) {
            // UIKit can provide up to 240 Hz input while ordinary delivery is
            // commonly around 60 Hz. Coalesced touches recover the missing path.
            emitActualSamples(for: pencil, event: event, fallbackPhase: .changed)
            emitPredictedSamples(for: pencil, event: event)
            return
        }

        let points = touches.filter { $0.type != .pencil }
        guard !points.isEmpty, activePencilTouch == nil else { return }
        let current = averageLocation(of: points)
        if let previous = lastNavigationPoint {
            onPan?(current - previous)
        }
        lastNavigationPoint = current
    }

    override func touchesEnded(_ touches: Set<UITouch>, with event: UIEvent?) {
        if let pencil = activePencilTouch, touches.contains(pencil) {
            emitActualSamples(for: pencil, event: event, fallbackPhase: .ended)
            activePencilTouch = nil
            return
        }
        lastNavigationPoint = nil
    }

    override func touchesCancelled(_ touches: Set<UITouch>, with event: UIEvent?) {
        if let pencil = activePencilTouch, touches.contains(pencil) {
            emitActualSamples(for: pencil, event: event, fallbackPhase: .cancelled)
            activePencilTouch = nil
        }
        lastNavigationPoint = nil
    }

    private func emitActualSamples(
        for touch: UITouch,
        event: UIEvent?,
        fallbackPhase: MosuanPointerEvent.Phase
    ) {
        guard let event else {
            onPointerEvent?(makeEvent(from: touch, phase: fallbackPhase))
            return
        }

        // Do not mix the normal touch with coalesced touches: the coalesced array
        // already includes the latest reported touch.
        let samples = event.coalescedTouches(for: touch) ?? [touch]
        for sample in samples {
            let phase: MosuanPointerEvent.Phase
            switch sample.phase {
            case .began: phase = .began
            case .moved, .stationary: phase = .changed
            case .ended: phase = .ended
            case .cancelled: phase = .cancelled
            @unknown default: phase = fallbackPhase
            }
            onPointerEvent?(makeEvent(from: sample, phase: phase))
        }
    }

    private func emitPredictedSamples(for touch: UITouch, event: UIEvent?) {
        guard let event, let predicted = event.predictedTouches(for: touch) else { return }
        for sample in predicted {
            onPredictedPointerEvent?(makeEvent(from: sample, phase: .changed))
        }
    }

    private func makeEvent(from touch: UITouch, phase: MosuanPointerEvent.Phase) -> MosuanPointerEvent {
        let location = touch.location(in: self)
        let vector = touch.azimuthUnitVector(in: self)
        let altitude = touch.altitudeAngle
        let tilt = SIMD2<Float>(
            Float(cos(altitude) * vector.dx),
            Float(cos(altitude) * vector.dy)
        )

        return MosuanPointerEvent(
            position: SIMD2(Float(location.x), Float(location.y)),
            pressure: max(0, Float(touch.force)),
            tilt: tilt,
            azimuth: Float(atan2(vector.dy, vector.dx)),
            phase: phase,
            deviceType: .pen,
            timestamp: touch.timestamp
        )
    }

    private func averageLocation(of touches: Set<UITouch>) -> CGPoint {
        let points = touches.map { $0.location(in: self) }
        guard !points.isEmpty else { return .zero }
        let x = points.reduce(CGFloat.zero) { $0 + $1.x } / CGFloat(points.count)
        let y = points.reduce(CGFloat.zero) { $0 + $1.y } / CGFloat(points.count)
        return CGPoint(x: x, y: y)
    }
}

private extension CGPoint {
    static func -(lhs: CGPoint, rhs: CGPoint) -> CGPoint {
        CGPoint(x: lhs.x - rhs.x, y: lhs.y - rhs.y)
    }
}
