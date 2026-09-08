import Foundation

/// Cross-platform input policy exposed to the iPad canvas layer.
/// Pencil always has priority over finger navigation while a stroke is active.
struct MosuanPencilInputMode: Equatable, Codable {
    enum Preset: String, CaseIterable, Codable {
        case standard
        case strong
        case extreme
    }

    var preset: Preset = .strong

    var pencilWrites: Bool { true }
    var fingerWrites: Bool { false }
    var fingerNavigates: Bool { preset != .extreme }
    var lockFingerWhilePencilDown: Bool { preset != .standard }

    func acceptsWriting(_ device: MosuanPointerEvent.DeviceType) -> Bool {
        switch device {
        case .pen: return pencilWrites
        case .touch: return fingerWrites
        case .mouse, .unknown: return false
        }
    }

    func acceptsNavigation(_ device: MosuanPointerEvent.DeviceType, pencilActive: Bool) -> Bool {
        guard device == .touch else { return false }
        guard fingerNavigates else { return false }
        guard !(lockFingerWhilePencilDown && pencilActive) else { return false }
        return true
    }
}
