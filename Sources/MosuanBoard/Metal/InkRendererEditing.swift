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

    /// Delete only editable objects. Locked objects remain selected so the user can
    /// immediately unlock them without losing the selection context.
    @discardableResult
    func deleteSelectedRespectingLocks() -> Bool {
        let objectIDsToDelete = selectedObjectIDs.filter {
            editingObjectStore?.object(with: $0)?.isLocked != true
        }
        let strokeIndicesToDelete = selectedStrokeIndices.sorted(by: >)
        guard !objectIDsToDelete.isEmpty || !strokeIndicesToDelete.isEmpty else { return false }

        recordMutation()
        if let store = editingObjectStore {
            for id in objectIDsToDelete { store.remove(id: id) }
        }
        for index in strokeIndicesToDelete where committedStrokes.indices.contains(index) {
            committedStrokes.remove(at: index)
        }

        selectedObjectIDs.removeAll { objectIDsToDelete.contains($0) }
        selectedStrokeIndices.removeAll { strokeIndicesToDelete.contains($0) }
        rebuildGeometry()
        return true
    }

    var selectedObjectsAreEditable: Bool {
        !selectedObjectIDs.isEmpty && selectedObjectIDs.contains {
            editingObjectStore?.object(with: $0)?.isLocked != true
        }
    }

    /// Rotation-center snapping is deliberately scoped to the current selection.
    /// The existing renderer owns the actual center state, so we temporarily expose
    /// only selected objects to its mature snapping implementation, then restore the
    /// full object list immediately.
    func setSelectionRotationCenter(to point: SIMD2<Float>) {
        guard let store = editingObjectStore, !selectedObjectIDs.isEmpty else { return }
        let original = store.objects
        let selected = original.filter { selectedObjectIDs.contains($0.id) }
        guard !selected.isEmpty else { return }
        store.objects = selected
        setRotationCenter(to: point)
        store.objects = original
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
        if let pattern = Mirror(reflecting: self).children.first(where: { $0.label == "backgroundPattern" })?.value as? Int {
            setBackgroundPattern(pattern)
        }
        editingAttachedView?.draw()
    }
}
