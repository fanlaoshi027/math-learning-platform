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
        refreshAfterClipboardEdit()
        return changed
    }

    @discardableResult
    func pasteSelection() -> Bool {
        guard let canvas,
              let data = NSPasteboard.general.data(forType: Self.clipboardType),
              canvas.pasteSelectionClipboardDataForEditing(data) else { return false }
        refreshAfterClipboardEdit()
        return true
    }

    private func refreshAfterClipboardEdit() {
        let canvas = self.canvas
        canUndo = canvas?.canUndo ?? false
        canRedo = canvas?.canRedo ?? false
        hasSelection = canvas?.hasSelection ?? false
        selectionCount = canvas?.selectionCount ?? 0
        rotationDegrees = canvas?.selectedRotationDegrees ?? 0
        selectionFrame = canvas?.selectionBoundsInView ?? .zero
        selectedObjectsAreAllLocked = canvas?.selectedObjectsAreAllLocked ?? false
        selectedObjectsAreAnyLocked = canvas?.selectedObjectsAreAnyLocked ?? false
    }
}
