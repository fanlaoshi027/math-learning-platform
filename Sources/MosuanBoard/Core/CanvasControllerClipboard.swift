import AppKit

extension CanvasController {
    private static let clipboardType = NSPasteboard.PasteboardType("com.fanlaoshi.mosuan.selection")

    func copySelection() {
        guard let canvas,
              let data = canvas.makeSelectionClipboardDataForEditing() else { return }
        let pasteboard = NSPasteboard.general
        pasteboard.clearContents()
        pasteboard.setData(data, forType: Self.clipboardType)
    }

    @discardableResult
    func cutSelection() -> Bool {
        guard let canvas,
              hasSelection,
              let data = canvas.makeSelectionClipboardDataForEditing() else { return false }
        let pasteboard = NSPasteboard.general
        pasteboard.clearContents()
        guard pasteboard.setData(data, forType: Self.clipboardType) else { return false }
        let changed = canvas.deleteSelectedRespectingLocks()
        refreshPublishedState()
        return changed
    }

    @discardableResult
    func pasteSelection() -> Bool {
        guard let canvas,
              let data = NSPasteboard.general.data(forType: Self.clipboardType),
              canvas.pasteSelectionClipboardDataForEditing(data) else { return false }
        refreshPublishedState()
        return true
    }
}
