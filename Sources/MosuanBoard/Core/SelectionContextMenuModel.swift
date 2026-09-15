import Foundation

/// Minimal selection-context actions shared by the desktop UI.
/// The model deliberately contains no drawing logic; InkRenderer remains the
/// source of truth for object editing and clipboard operations.
enum SelectionContextMenuAction: String, CaseIterable, Identifiable {
    case copy
    case cut
    case paste
    case delete
    case bringToFront
    case sendToBack
    case lockOrUnlock
    case setRotationCenter

    var id: String { rawValue }

    var title: String {
        switch self {
        case .copy: return "复制"
        case .cut: return "剪切"
        case .paste: return "粘贴"
        case .delete: return "删除"
        case .bringToFront: return "置于顶层"
        case .sendToBack: return "置于底层"
        case .lockOrUnlock: return "锁定 / 解锁"
        case .setRotationCenter: return "设置旋转中心"
        }
    }
}

struct SelectionContextMenuState: Equatable {
    var hasSelection = false
    var allSelectedObjectsLocked = false
    var anySelectedObjectsLocked = false

    var canCopy: Bool { hasSelection }
    var canCut: Bool { hasSelection && !allSelectedObjectsLocked }
    var canDelete: Bool { hasSelection && !allSelectedObjectsLocked }
    var canReorder: Bool { hasSelection }
    var canSetRotationCenter: Bool { hasSelection }

    var lockTitle: String {
        allSelectedObjectsLocked ? "解锁对象" : "锁定对象"
    }
}
