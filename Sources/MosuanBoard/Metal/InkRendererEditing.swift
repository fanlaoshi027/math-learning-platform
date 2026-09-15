import Foundation
import simd

extension InkRenderer {
    /// Object-editing commands used by the selection context menu.
    /// These intentionally operate on the existing GraphicObjectStore instead
    /// of duplicating object mutation logic in the UI layer.
    @discardableResult
    func toggleSelectedObjectLock() -> Bool {
        guard !selectedObjectIDs.isEmpty else { return false }
        let changed = objectStoreForEditing.toggleLocked(ids: selectedObjectIDs)
        if changed { requestRedrawAfterEditing() }
        return changed
    }

    @discardableResult
    func setSelectedObjectLock(_ locked: Bool) -> Bool {
        guard !selectedObjectIDs.isEmpty else { return false }
        let changed = objectStoreForEditing.setLocked(locked, ids: selectedObjectIDs)
        if changed { requestRedrawAfterEditing() }
        return changed
    }

    @discardableResult
    func bringSelectedObjectsToFront() -> Bool {
        let changed = objectStoreForEditing.moveToFront(ids: selectedObjectIDs)
        if changed { requestRedrawAfterEditing() }
        return changed
    }

    @discardableResult
    func sendSelectedObjectsToBack() -> Bool {
        let changed = objectStoreForEditing.moveToBack(ids: selectedObjectIDs)
        if changed { requestRedrawAfterEditing() }
        return changed
    }

    @discardableResult
    func bringSelectedObjectsForward() -> Bool {
        let changed = objectStoreForEditing.moveForward(ids: selectedObjectIDs)
        if changed { requestRedrawAfterEditing() }
        return changed
    }

    @discardableResult
    func sendSelectedObjectsBackward() -> Bool {
        let changed = objectStoreForEditing.moveBackward(ids: selectedObjectIDs)
        if changed { requestRedrawAfterEditing() }
        return changed
    }

    var selectedObjectsAreAllLocked: Bool {
        !selectedObjectIDs.isEmpty && selectedObjectIDs.allSatisfy { objectStoreForEditing.object(with: $0)?.isLocked == true }
    }

    var selectedObjectsAreAnyLocked: Bool {
        selectedObjectIDs.contains { objectStoreForEditing.object(with: $0)?.isLocked == true }
    }

    private var objectStoreForEditing: GraphicObjectStore {
        // The renderer keeps the canonical store; this indirection gives the
        // editing extension one stable seam for future UI commands.
        objectStore
    }

    private func requestRedrawAfterEditing() {
        // Selection/layer changes affect draw order immediately. The existing
        // renderer rebuilds on the next interaction; invalidate via the view
        // when it is attached without introducing a second rendering pipeline.
        attachedViewForEditing?.draw()
    }

    private var attachedViewForEditing: MTKView? {
        Mirror(reflecting: self).children.compactMap { child -> MTKView? in
            guard child.label == "attachedView" else { return nil }
            return child.value as? MTKView
        }.first
    }
}
