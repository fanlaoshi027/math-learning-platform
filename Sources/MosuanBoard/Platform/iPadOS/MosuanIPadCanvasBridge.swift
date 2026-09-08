import Foundation
import simd

/// A small platform-neutral bridge for an iPad canvas host.
/// The eventual UIKit/Metal view can forward Pencil events and navigation
/// gestures here, keeping document editing independent of UIKit.
final class MosuanIPadCanvasBridge {
    var onPointerEvent: ((MosuanPointerEvent) -> Void)?
    var onPan: ((SIMD2<Float>) -> Void)?
    var onZoom: ((Float, SIMD2<Float>) -> Void)?

    private(set) var lastPencilPosition: SIMD2<Float>?

    func receivePencil(_ event: MosuanPointerEvent) {
        guard event.deviceType == .pen else { return }
        lastPencilPosition = event.position
        onPointerEvent?(event)
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
    }
}
