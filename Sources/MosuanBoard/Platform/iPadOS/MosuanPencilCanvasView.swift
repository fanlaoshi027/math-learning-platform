import UIKit

/// Native iPad input surface for Mosuan.
///
/// Pencil draws; one-finger touch is reserved for UI/selection gestures; two or
/// more fingers pan/zoom the canvas. Rendering stays outside this input layer.
final class MosuanPencilCanvasView: UIView {
    enum GestureMode {
        case pencilWriting
        case fingerNavigation
    }

    var onPointerEvent: ((MosuanPointerEvent) -> Void)?
    var onPan: ((CGPoint) -> Void)?
    var onZoom: ((CGFloat, CGPoint) -> Void)?

    private(set) var gestureMode: GestureMode = .fingerNavigation
    private var activePencilTouch: UITouch?
    private var lastNavigationPoint: CGPoint?
    private var lastPinchScale: CGFloat = 1

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
        // Do not delay Pencil input behind UIKit gesture recognition.
        delaysContentTouches = false
    }

    override func touchesBegan(_ touches: Set<UITouch>, with event: UIEvent?) {
        guard let touch = touches.first else { return }

        if touch.type == .pencil {
            activePencilTouch = touch
            gestureMode = .pencilWriting
            emit(touch, phase: .began)
            return
        }

        guard activePencilTouch == nil else { return }
        if touches.count >= 2 {
            gestureMode = .fingerNavigation
            lastNavigationPoint = averageLocation(of: touches)
        } else {
            // A single finger does not write. This leaves room for tap/select UI.
            gestureMode = .fingerNavigation
            lastNavigationPoint = touch.location(in: self)
        }
    }

    override func touchesMoved(_ touches: Set<UITouch>, with event: UIEvent?) {
        if let pencil = activePencilTouch, touches.contains(pencil) {
            emit(pencil, phase: .changed)
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
            emit(pencil, phase: .ended)
            activePencilTouch = nil
            return
        }

        lastNavigationPoint = nil
    }

    override func touchesCancelled(_ touches: Set<UITouch>, with event: UIEvent?) {
        if let pencil = activePencilTouch, touches.contains(pencil) {
            emit(pencil, phase: .cancelled)
            activePencilTouch = nil
        }
        lastNavigationPoint = nil
    }

    private func emit(_ touch: UITouch, phase: MosuanPointerEvent.Phase) {
        let location = touch.location(in: self)
        let previous = touch.previousLocation(in: self)
        let vector = touch.azimuthUnitVector(in: self)
        let altitude = touch.altitudeAngle

        // Convert Pencil altitude/azimuth into a compact tilt vector. Consumers
        // can use pressure directly and ignore tilt when a pen does not expose it.
        let tiltX = Float(cos(altitude) * vector.dx)
        let tiltY = Float(cos(altitude) * vector.dy)

        let event = MosuanPointerEvent(
            position: SIMD2(Float(location.x), Float(location.y)),
            pressure: max(0, Float(touch.force)),
            tilt: SIMD2(tiltX, tiltY),
            azimuth: Float(atan2(vector.dy, vector.dx)),
            phase: phase,
            deviceType: .pen
        )
        onPointerEvent?(event)

        // Keep the compiler aware that previousLocation is intentionally part of
        // the input sampling contract; future coalesced samples can use it.
        _ = previous
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
