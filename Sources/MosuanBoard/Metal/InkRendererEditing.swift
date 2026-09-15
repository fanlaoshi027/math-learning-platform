import Foundation
import MetalKit
import simd

extension InkRenderer {
    /// Object-editing commands used by the selection context menu.
    /// These operate on the renderer's canonical GraphicObjectStore.
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
        !selectedObjectIDs.isEmpty && selectedObjectIDs.allSatisfy {
            objectStoreForEditing.object(with: $0)?.isLocked == true
        }
    }

    var selectedObjectsAreAnyLocked: Bool {
        selectedObjectIDs.contains {
            objectStoreForEditing.object(with: $0)?.isLocked == true
        }
    }

    private var objectStoreForEditing: GraphicObjectStore {
        // Swift keeps the renderer's store private. Reflection is used only
        // as a temporary compatibility seam until the renderer/store boundary
        // is made explicitly internal in the next integration pass.
        for child in Mirror(reflecting: self).children {
            if child.label == "objectStore", let store = child.value as? GraphicObjectStore {
                return store
            }
        }
        return GraphicObjectStore()
    }

    private func requestRedrawAfterEditing() {
        for child in Mirror(reflecting: self).children {
            if child.label == "attachedView", let view = child.value as? MTKView {
                view.draw()
                return
            }
        }
    }
}
