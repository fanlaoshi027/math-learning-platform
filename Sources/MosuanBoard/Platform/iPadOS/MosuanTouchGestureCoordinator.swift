import Foundation
import simd

/// Platform-neutral gesture state used by the iPad canvas adapter.
/// UIKit recognizers can feed these values into the renderer without leaking
/// UIKit types into the Mosuan editing core.
struct MosuanTouchGestureState: Equatable {
    var isPanning = false
    var isZooming = false
    var scale: Float = 1
    var translation: SIMD2<Float> = .zero

    mutating func reset() {
        isPanning = false
        isZooming = false
        scale = 1
        translation = .zero
    }
}

/// Converts the common two-finger navigation gestures into platform-neutral
/// canvas commands. Pencil input remains independent and is handled by the
/// Pencil adapter.
final class MosuanTouchGestureCoordinator {
    private(set) var state = MosuanTouchGestureState()

    func beginPan() {
        state.isPanning = true
        state.isZooming = false
        state.translation = .zero
    }

    func updatePan(delta: SIMD2<Float>) -> SIMD2<Float> {
        guard state.isPanning else { return .zero }
        state.translation += delta
        return delta
    }

    func beginZoom() {
        state.isZooming = true
        state.isPanning = false
        state.scale = 1
    }

    func updateZoom(scale: Float) -> Float {
        guard state.isZooming else { return 1 }
        let factor = max(scale / max(state.scale, 0.0001), 0.01)
        state.scale = scale
        return factor
    }

    func end() {
        state.reset()
    }
}
