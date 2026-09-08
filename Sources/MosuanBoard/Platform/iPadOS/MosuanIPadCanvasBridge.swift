import Foundation
import simd

/// Platform-neutral bridge for the iPad canvas host.
/// Pencil input and touch navigation stay separate from document editing.
final class MosuanIPadCanvasBridge {
    enum PressureMode: Equatable {
        case devicePressure
        case syntheticNib
    }

    var onPointerEvent: ((MosuanPointerEvent) -> Void)?
    var onPan: ((SIMD2<Float>) -> Void)?
    var onZoom: ((Float, SIMD2<Float>) -> Void)?

    /// Use synthetic nib dynamics by default so pressure-free active pens and
    /// capacitive styluses still produce visible thick/thin pen strokes.
    var pressureMode: PressureMode = .syntheticNib
    var inkDynamics = MosuanInkDynamics()

    private(set) var lastPencilPosition: SIMD2<Float>?

    func receivePencil(_ event: MosuanPointerEvent) {
        receivePencil(event, timestamp: ProcessInfo.processInfo.systemUptime)
    }

    func receivePencil(_ event: MosuanPointerEvent, timestamp: TimeInterval) {
        guard event.deviceType == .pen else { return }
        lastPencilPosition = event.position

        var forwarded = event
        if pressureMode == .syntheticNib {
            forwarded.pressure = inkDynamics.syntheticPressure(
                position: event.position,
                timestamp: timestamp,
                phase: event.phase
            )
        }

        onPointerEvent?(forwarded)

        if event.phase == .ended || event.phase == .cancelled {
            inkDynamics.reset()
        }
    }

    func receiveTouchPan(delta: SIMD2<Float>) {
        onPan?(delta)
    }

    func receiveTouchZoom(factor: Float, around point: SIMD2<Float>) {
        guard factor.isFinite, factor > 0 else { return }
        onZoom?(factor, point)
    }

    func reset() {
        lastPencilPosition = nil
        inkDynamics.reset()
    }
}
