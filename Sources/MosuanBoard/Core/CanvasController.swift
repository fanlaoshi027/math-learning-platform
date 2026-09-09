import Combine
import CoreGraphics

final class CanvasController: ObservableObject {
    weak var canvas: InkMetalView?
    @Published private(set) var canUndo = false
    @Published private(set) var canRedo = false
    @Published private(set) var hasSelection = false
    @Published private(set) var selectionCount = 0
    @Published private(set) var rotationDegrees: Double = 0
    @Published private(set) var selectionFrame: CGRect = .zero
    @Published private(set) var dynamicAngleDegrees: Double?
    @Published private(set) var dynamicAnglePlaying = false
    @Published private(set) var dynamicTriangleDegrees: Double?

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
            self.selectionCount = canvas.selectionCount
            self.rotationDegrees = canvas.selectedRotationDegrees
            self.selectionFrame = canvas.selectionBoundsInView ?? .zero
            self.dynamicAngleDegrees = canvas.selectedDynamicAngleDegrees.map(Double.init)
            self.dynamicAnglePlaying = canvas.isSelectedDynamicAnglePlaying
            self.dynamicTriangleDegrees = canvas.selectedDynamicIsoscelesTriangleDegrees.map(Double.init)
        }
        refreshState()
    }

    func undo() { canvas?.undo(); refreshState() }
    func redo() { canvas?.redo(); refreshState() }
    func deleteSelected() { canvas?.deleteSelected(); refreshState() }
    func setRotationDegrees(_ d: Double) { canvas?.setSelectedRotationDegrees(d); refreshState() }
    func scaleSelected(by f: Float) { canvas?.scaleSelected(by: f); refreshState() }
    func reflectHorizontal() { canvas?.reflectSelected(horizontal: true); refreshState() }
    func reflectVertical() { canvas?.reflectSelected(horizontal: false); refreshState() }
    func resetRotationCenter() { canvas?.setRotationCenterToSelectionCenter(); refreshState() }

    func setDynamicAngleDegrees(_ degrees: Double) { canvas?.setSelectedDynamicAngleDegrees(CGFloat(degrees)); refreshState() }
    func toggleDynamicAnglePlayback() { canvas?.toggleSelectedDynamicAnglePlayback(); refreshState() }
    func setDynamicTriangleDegrees(_ degrees: Double) { canvas?.setSelectedDynamicIsoscelesTriangleDegrees(CGFloat(degrees)); refreshState() }

    func beginDynamicTrianglePlaybackHistory() { canvas?.beginHistoryTransaction(); refreshState() }
    func endDynamicTrianglePlaybackHistory() { canvas?.endHistoryTransaction(); refreshState() }

    func refreshState() {
        canUndo = canvas?.canUndo ?? false
        canRedo = canvas?.canRedo ?? false
        hasSelection = canvas?.hasSelection ?? false
        selectionCount = canvas?.selectionCount ?? 0
        rotationDegrees = canvas?.selectedRotationDegrees ?? 0
        selectionFrame = canvas?.selectionBoundsInView ?? .zero
        dynamicAngleDegrees = canvas?.selectedDynamicAngleDegrees.map(Double.init)
        dynamicAnglePlaying = canvas?.isSelectedDynamicAnglePlaying ?? false
        dynamicTriangleDegrees = canvas?.selectedDynamicIsoscelesTriangleDegrees.map(Double.init)
    }
}
