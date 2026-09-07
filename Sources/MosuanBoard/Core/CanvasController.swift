import Combine

final class CanvasController: ObservableObject {
    weak var canvas: InkMetalView?
    @Published private(set) var canUndo = false
    @Published private(set) var canRedo = false
    @Published private(set) var hasSelection = false

    func attach(_ canvas: InkMetalView) {
        self.canvas = canvas
        canvas.onHistoryChanged = { [weak self, weak canvas] in
            guard let self, let canvas else { return }
            self.canUndo = canvas.canUndo
            self.canRedo = canvas.canRedo
        }
        canvas.onSelectionChanged = { [weak self, weak canvas] in
            guard let self, let canvas else { return }
            self.hasSelection = canvas.hasSelection
        }
        refreshState()
    }

    func undo() {
        canvas?.undo()
        refreshState()
    }

    func redo() {
        canvas?.redo()
        refreshState()
    }

    func deleteSelected() {
        canvas?.deleteSelected()
        refreshState()
    }

    func refreshState() {
        canUndo = canvas?.canUndo ?? false
        canRedo = canvas?.canRedo ?? false
        hasSelection = canvas?.hasSelection ?? false
    }
}
