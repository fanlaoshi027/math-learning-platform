import Foundation

enum ToolbarDockPosition: String, CaseIterable {
    case top
    case bottom
    case leading
    case trailing
}

extension Notification.Name {
    static let mosuanToolbarDockChanged = Notification.Name("mosuanToolbarDockChanged")
}
