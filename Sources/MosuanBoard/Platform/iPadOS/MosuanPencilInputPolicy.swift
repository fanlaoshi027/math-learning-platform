import Foundation

/// Decides which device is allowed to write on the iPad canvas.
/// The policy keeps Apple Pencil as the primary ink device while allowing
/// touch navigation to remain available.
struct MosuanPencilInputPolicy: Equatable {
    var pencilWrites = true
    var fingerWrites = false
    var fingerNavigates = true
    var palmRejected = true

    func acceptsWriting(_ device: MosuanPointerEvent.DeviceType) -> Bool {
        switch device {
        case .pen:
            return pencilWrites
        case .touch:
            return fingerWrites
        case .mouse:
            return false
        case .unknown:
            return false
        }
    }

    func acceptsNavigation(_ device: MosuanPointerEvent.DeviceType) -> Bool {
        switch device {
        case .touch:
            return fingerNavigates
        default:
            return false
        }
    }
}
