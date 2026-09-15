import Foundation

extension InkMetalView {
    @discardableResult
    func toggleSelectedObjectLock() -> Bool {
        guard let renderer = editingRenderer else { return false }
        let changed = renderer.toggleSelectedObjectLock()
        if changed { onSelectionChanged?(); draw() }
        return changed
    }

    @discardableResult
    func setSelectedObjectLock(_ locked: Bool) -> Bool {
        guard let renderer = editingRenderer else { return false }
        let changed = renderer.setSelectedObjectLock(locked)
        if changed { onSelectionChanged?(); draw() }
        return changed
    }

    @discardableResult
    func bringSelectedObjectsToFront() -> Bool {
        guard let renderer = editingRenderer else { return false }
        let changed = renderer.bringSelectedObjectsToFront()
        if changed { draw() }
        return changed
    }

    @discardableResult
    func sendSelectedObjectsToBack() -> Bool {
        guard let renderer = editingRenderer else { return false }
        let changed = renderer.sendSelectedObjectsToBack()
        if changed { draw() }
        return changed
    }

    @discardableResult
    func bringSelectedObjectsForward() -> Bool {
        guard let renderer = editingRenderer else { return false }
        let changed = renderer.bringSelectedObjectsForward()
        if changed { draw() }
        return changed
    }

    @discardableResult
    func sendSelectedObjectsBackward() -> Bool {
        guard let renderer = editingRenderer else { return false }
        let changed = renderer.sendSelectedObjectsBackward()
        if changed { draw() }
        return changed
    }

    private var editingRenderer: InkRenderer? {
        Mirror(reflecting: self).children.first(where: { $0.label == "renderer" })?.value as? InkRenderer
    }
}
