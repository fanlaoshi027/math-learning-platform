import Foundation

enum EraserMode: String, CaseIterable, Identifiable {
    case stroke
    case partial

    var id: String { rawValue }
    var title: String {
        switch self {
        case .stroke: return "整根删除"
        case .partial: return "局部删除"
        }
    }
}
