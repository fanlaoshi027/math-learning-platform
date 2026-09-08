import SwiftUI

/// Interface appearance is independent from the whiteboard page background.
/// The canvas may stay white, dark, grid, PDF, etc. while the surrounding app UI
/// uses either the light or dark visual theme.
enum BoardInterfaceTheme: String, CaseIterable, Identifiable {
    case light
    case dark

    var id: String { rawValue }

    var title: String {
        switch self {
        case .light: return "明亮模式"
        case .dark: return "黑暗模式"
        }
    }

    var systemImage: String {
        switch self {
        case .light: return "sun.max.fill"
        case .dark: return "moon.fill"
        }
    }

    var colorScheme: ColorScheme {
        switch self {
        case .light: return .light
        case .dark: return .dark
        }
    }
}
