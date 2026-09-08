#if canImport(UIKit)
import UIKit
import simd

/// Converts UIKit touch events into the platform-neutral MosuanPointerEvent.
/// Keep UIKit out of the editing core; this adapter is the only layer that knows
/// about UITouch / Apple Pencil APIs.
struct MosuanPencilInputAdapter {
    static func event(
        from touch: UITouch,
        in view: UIView,
        phase: MosuanPointerEvent.Phase,
        modifiers: UInt32 = 0
    ) -> MosuanPointerEvent {
        let location = touch.location(in: view)
        let position = SIMD2<Float>(Float(location.x), Float(location.y))
        let isPencil = touch.type == .pencil

        let pressure: Float
        if isPencil {
            pressure = Float(touch.force)
        } else {
            pressure = 1
        }

        let tilt: SIMD2<Float>
        let azimuth: Float
        if isPencil {
            // altitudeAngle is measured from the screen plane. Convert it into
            // a normalized two-component tilt representation for the core.
            let altitude = Float(touch.altitudeAngle)
            let azimuthAngle = Float(touch.azimuthAngle(in: view))
            tilt = SIMD2<Float>(
                cos(azimuthAngle) * cos(altitude),
                sin(azimuthAngle) * cos(altitude)
            )
            azimuth = azimuthAngle
        } else {
            tilt = .zero
            azimuth = 0
        }

        return MosuanPointerEvent(
            position: position,
            pressure: pressure,
            tilt: tilt,
            azimuth: azimuth,
            phase: phase,
            deviceType: isPencil ? .pen : .touch,
            buttons: 0,
            modifiers: modifiers
        )
    }
}
#endif
