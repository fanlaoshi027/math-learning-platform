import Foundation

/// Describes how a new selection result should be combined with the current selection.
/// The actual renderer owns the selection storage; this type keeps modifier-key
/// semantics centralized so mouse and tablet input can share the same rules.
enum SelectionOperation: Equatable {
    /// Replace the current selection with the hit set.
    case replace
    /// Add the hit set to the current selection.
    case add
    /// Remove the hit set from the current selection.
    case subtract

    /// Resolves the operation from AppKit modifier flags.
    /// Shift = additive selection; Option = subtractive selection.
    static func fromModifiers(_ flags: NSEvent.ModifierFlags) -> SelectionOperation {
        if flags.contains(.option) { return .subtract }
        if flags.contains(.shift) { return .add }
        return .replace
    }
}
