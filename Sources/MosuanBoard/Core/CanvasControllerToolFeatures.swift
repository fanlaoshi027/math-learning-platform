import Foundation

extension CanvasController {
    func clearCurrentPage() {
        canvas?.clearPageContents()
        refreshState()
    }
}
