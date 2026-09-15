import Combine
import CoreGraphics

final class CanvasController: ObservableObject {
    weak var canvas: InkMetalView?
    private let dynamicTrianglePlayback = DynamicIsoscelesTrianglePlaybackController()
    @Published private(set) var canUndo = false
    @Published private(set) var canRedo = false
    @Published private(set) var hasSelection = false
    @Published private(set) var selectionCount = 0
    @Published private(set) var rotationDegrees: Double = 0
    @Published private(set) var selectionFrame: CGRect = .zero
    @Published private(set) var dynamicAngleDegrees: Double?
    @Published private(set) var dynamicAnglePlaying = false
    @Published private(set) var dynamicTriangleDegrees: Double?
    @Published private(set) var dynamicTriangleLegLength: Double?
    @Published private(set) var selectedObjectsAreAllLocked = false
    @Published private(set) var selectedObjectsAreAnyLocked = false

    deinit { dynamicTrianglePlayback.stop() }

    func attach(_ canvas: InkMetalView) {
        if self.canvas === canvas { return }
        dynamicTrianglePlayback.stop()
        self.canvas = canvas
        canvas.onHistoryChanged = { [weak self, weak canvas] in
            guard let self, let canvas else { return }
            self.canUndo = canvas.canUndo
            self.canRedo = canvas.canRedo
        }
        canvas.onSelectionChanged = { [weak self, weak canvas] in
            guard let self, let canvas else { return }
            self.refreshState(from: canvas)
        }
        refreshState()
    }

    func undo() { stopEditing(); canvas?.undo(); refreshState() }
    func redo() { stopEditing(); canvas?.redo(); refreshState() }
    func deleteSelected() { stopEditing(); canvas?.deleteSelectedRespectingLocks(); refreshState() }
    func setRotationDegrees(_ d: Double) { canvas?.setSelectedRotationDegrees(d); refreshState() }
    func scaleSelected(by f: Float) { canvas?.scaleSelected(by: f); refreshState() }
    func reflectHorizontal() { canvas?.reflectSelected(horizontal: true); refreshState() }
    func reflectVertical() { canvas?.reflectSelected(horizontal: false); refreshState() }
    func resetRotationCenter() { canvas?.setRotationCenterToSelectionCenter(); refreshState() }

    @discardableResult func toggleSelectedLock() -> Bool {
        let changed = canvas?.toggleSelectedObjectLock() ?? false
        refreshState()
        return changed
    }
    @discardableResult func setSelectedLock(_ locked: Bool) -> Bool {
        let changed = canvas?.setSelectedObjectLock(locked) ?? false
        refreshState()
        return changed
    }
    @discardableResult func bringSelectedToFront() -> Bool { let c = canvas?.bringSelectedObjectsToFront() ?? false; refreshState(); return c }
    @discardableResult func sendSelectedToBack() -> Bool { let c = canvas?.sendSelectedObjectsToBack() ?? false; refreshState(); return c }
    @discardableResult func bringSelectedForward() -> Bool { let c = canvas?.bringSelectedObjectsForward() ?? false; refreshState(); return c }
    @discardableResult func sendSelectedBackward() -> Bool { let c = canvas?.sendSelectedObjectsBackward() ?? false; refreshState(); return c }

    func setDynamicAngleDegrees(_ degrees: Double) { guard !dynamicAnglePlaying else { return }; canvas?.setSelectedDynamicAngleDegrees(CGFloat(degrees)); refreshState() }
    func toggleDynamicAnglePlayback() { canvas?.toggleSelectedDynamicAnglePlayback(); refreshState() }
    func setDynamicTriangleDegrees(_ degrees: Double) { guard !dynamicTrianglePlayback.isPlaying else { return }; canvas?.setSelectedDynamicIsoscelesTriangleDegrees(CGFloat(degrees)); refreshState() }
    func setDynamicTriangleLegLength(_ length: Double) { guard !dynamicTrianglePlayback.isPlaying else { return }; canvas?.setSelectedDynamicIsoscelesTriangleLegLength(CGFloat(length)); refreshState() }
    func beginDynamicTriangleParameterEditHistory() { guard !dynamicTrianglePlayback.isPlaying else { return }; canvas?.beginDynamicTriangleParameterEditHistory() }
    func endDynamicTriangleParameterEditHistory() { canvas?.endDynamicTriangleParameterEditHistory(); refreshState() }
    func beginDynamicTrianglePlaybackHistory() {
        guard !dynamicTrianglePlayback.isPlaying else { return }
        guard let canvas, let initialAngle = canvas.selectedDynamicIsoscelesTriangleDegrees else { refreshState(); return }
        canvas.beginDynamicTrianglePlaybackHistory()
        dynamicTrianglePlayback.start(currentAngle: initialAngle) { [weak self] degrees in self?.canvas?.setSelectedDynamicIsoscelesTriangleDegrees(degrees); self?.refreshState() }
        refreshState()
    }
    func endDynamicTrianglePlaybackHistory() { dynamicTrianglePlayback.stop(); canvas?.endDynamicTrianglePlaybackHistory(); refreshState() }

    /// Allows feature extensions to resynchronize published UI state without exposing
    /// the individual setters.
    func refreshPublishedState() { refreshState() }

    private func stopEditing() {
        dynamicTrianglePlayback.stop()
        canvas?.stopDynamicAnglePlayback()
        canvas?.endDynamicTrianglePlaybackHistory()
        canvas?.endDynamicTriangleParameterEditHistory()
    }

    private func refreshState(from canvas: InkMetalView? = nil) {
        let canvas = canvas ?? self.canvas
        canUndo = canvas?.canUndo ?? false
        canRedo = canvas?.canRedo ?? false
        hasSelection = canvas?.hasSelection ?? false
        selectionCount = canvas?.selectionCount ?? 0
        rotationDegrees = canvas?.selectedRotationDegrees ?? 0
        selectionFrame = canvas?.selectionBoundsInView ?? .zero
        dynamicAngleDegrees = canvas?.selectedDynamicAngleDegrees.map(Double.init)
        dynamicAnglePlaying = canvas?.isSelectedDynamicAnglePlaying ?? false
        dynamicTriangleDegrees = canvas?.selectedDynamicIsoscelesTriangleDegrees.map(Double.init)
        dynamicTriangleLegLength = canvas?.selectedDynamicIsoscelesTriangleLegLength.map(Double.init)
        selectedObjectsAreAllLocked = canvas?.selectedObjectsAreAllLocked ?? false
        selectedObjectsAreAnyLocked = canvas?.selectedObjectsAreAnyLocked ?? false
    }
}