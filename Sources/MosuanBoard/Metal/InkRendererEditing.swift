import Foundation
import MetalKit
import simd

extension InkRenderer {
    /// Object-editing commands used by the selection context menu.
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
        for child in Mirror(reflecting: self).children {
            if child.label == "objectStore", let store = child.value as? GraphicObjectStore {
                return store
            }
        }
        return GraphicObjectStore()
    }

    private func requestRedrawAfterEditing() {
        // setBackgroundPattern is already a renderer-owned rebuild entry point.
        // Re-applying the current value refreshes Metal geometry without
        // duplicating the renderer's private rebuild pipeline here.
        for child in Mirror(reflecting: self).children {
            if child.label == "backgroundPattern", let pattern = child.value as? Int {
                setBackgroundPattern(pattern)
                break
            }
        }
        for child in Mirror(reflecting: self).children {
            if child.label == "attachedView", let view = child.value as? MTKView {
                view.draw()
                break
            }
        }
    }
}
