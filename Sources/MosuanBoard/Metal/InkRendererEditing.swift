import Foundation
import MetalKit

extension InkRenderer {
    @discardableResult
    func toggleSelectedObjectLock() -> Bool {
        guard !selectedObjectIDs.isEmpty, let store = editingObjectStore else { return false }
        let changed = store.toggleLocked(ids: selectedObjectIDs)
        if changed { redrawAfterEditing() }
        return changed
    }

    @discardableResult
    func setSelectedObjectLock(_ locked: Bool) -> Bool {
        guard !selectedObjectIDs.isEmpty, let store = editingObjectStore else { return false }
        let changed = store.setLocked(locked, ids: selectedObjectIDs)
        if changed { redrawAfterEditing() }
        return changed
    }

    @discardableResult
    func bringSelectedObjectsToFront() -> Bool {
        guard let store = editingObjectStore else { return false }
        let changed = store.moveToFront(ids: selectedObjectIDs)
        if changed { redrawAfterEditing() }
        return changed
    }

    @discardableResult
    func sendSelectedObjectsToBack() -> Bool {
        guard let store = editingObjectStore else { return false }
        let changed = store.moveToBack(ids: selectedObjectIDs)
        if changed { redrawAfterEditing() }
        return changed
    }

    @discardableResult
    func bringSelectedObjectsForward() -> Bool {
        guard let store = editingObjectStore else { return false }
        let changed = store.moveForward(ids: selectedObjectIDs)
        if changed { redrawAfterEditing() }
        return changed
    }

    @discardableResult
    func sendSelectedObjectsBackward() -> Bool {
        guard let store = editingObjectStore else { return false }
        let changed = store.moveBackward(ids: selectedObjectIDs)
        if changed { redrawAfterEditing() }
        return changed
    }

    var selectedObjectsAreAllLocked: Bool {
        !selectedObjectIDs.isEmpty && selectedObjectIDs.allSatisfy {
            editingObjectStore?.object(with: $0)?.isLocked == true
        }
    }

    var selectedObjectsAreAnyLocked: Bool {
        selectedObjectIDs.contains {
            editingObjectStore?.object(with: $0)?.isLocked == true
        }
    }

    private var editingObjectStore: GraphicObjectStore? {
        Mirror(reflecting: self).children.first(where: { $0.label == "objectStore" })?.value as? GraphicObjectStore
    }

    private var editingAttachedView: MTKView? {
        Mirror(reflecting: self).children.first(where: { $0.label == "attachedView" })?.value as? MTKView
    }

    private func redrawAfterEditing() {
        // Refresh through an existing renderer-owned setting entry point.
        // This avoids introducing a second rendering pipeline in the UI layer.
        if let pattern = Mirror(reflecting: self).children.first(where: { $0.label == "backgroundPattern" })?.value as? Int {
            setBackgroundPattern(pattern)
        }
        editingAttachedView?.draw()
    }
}
