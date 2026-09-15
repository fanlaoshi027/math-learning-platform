import Foundation

extension GraphicObjectStore {
    /// Reorders top-level objects without exposing the store's backing array.
    /// Selection order is preserved for multi-object operations.
    func moveToFront(ids: Set<UUID>) {
        reorder(ids: ids, toFront: true)
    }

    func moveToBack(ids: Set<UUID>) {
        reorder(ids: ids, toFront: false)
    }

    func moveForward(ids: Set<UUID>) {
        guard !ids.isEmpty else { return }
        var value = objects
        guard value.count > 1 else { return }
        for index in stride(from: value.count - 2, through: 0, by: -1) {
            if ids.contains(value[index].id) && !ids.contains(value[index + 1].id) {
                value.swapAt(index, index + 1)
            }
        }
        replaceObjects(value)
    }

    func moveBackward(ids: Set<UUID>) {
        guard !ids.isEmpty else { return }
        var value = objects
        guard value.count > 1 else { return }
        for index in 1..<value.count {
            if ids.contains(value[index].id) && !ids.contains(value[index - 1].id) {
                value.swapAt(index, index - 1)
            }
        }
        replaceObjects(value)
    }

    func setLocked(_ locked: Bool, ids: Set<UUID>) {
        guard !ids.isEmpty else { return }
        for object in objects where ids.contains(object.id) {
            var updated = object
            updated.isLocked = locked
            update(updated)
        }
    }

    func toggleLocked(ids: Set<UUID>) {
        let shouldLock = objects.filter { ids.contains($0.id) }.contains { !$0.isLocked }
        setLocked(shouldLock, ids: ids)
    }

    func lockedObjectIDs(in ids: Set<UUID>) -> Set<UUID> {
        Set(objects.filter { ids.contains($0.id) && $0.isLocked }.map(\.id))
    }

    private func reorder(ids: Set<UUID>, toFront: Bool) {
        guard !ids.isEmpty else { return }
        let selected = objects.filter { ids.contains($0.id) }
        let remaining = objects.filter { !ids.contains($0.id) }
        replaceObjects(toFront ? remaining + selected : selected + remaining)
    }

    private func replaceObjects(_ value: [GraphicObject]) {
        let current = Set(objects.map(\.id))
        let incoming = Set(value.map(\.id))
        guard current == incoming else { return }
        let existing = objects
        for id in existing.map(\.id) {
            remove(id: id)
        }
        for object in value {
            insert(object)
        }
    }
}
