import Foundation

extension GraphicObjectStore {
    @discardableResult
    func setLocked(_ locked: Bool, ids: [UUID]) -> Bool {
        var changed = false
        for id in ids {
            guard var object = object(with: id), object.isLocked != locked else { continue }
            object.isLocked = locked
            update(object)
            changed = true
        }
        return changed
    }

    @discardableResult
    func toggleLocked(ids: [UUID]) -> Bool {
        let values = ids.compactMap { object(with: $0)?.isLocked }
        guard !values.isEmpty else { return false }
        let target = !values.allSatisfy { $0 }
        return setLocked(target, ids: ids)
    }

    @discardableResult
    func moveToFront(ids: [UUID]) -> Bool {
        reorder(ids: ids, direction: .front)
    }

    @discardableResult
    func moveToBack(ids: [UUID]) -> Bool {
        reorder(ids: ids, direction: .back)
    }

    @discardableResult
    func moveForward(ids: [UUID]) -> Bool {
        reorder(ids: ids, direction: .forward)
    }

    @discardableResult
    func moveBackward(ids: [UUID]) -> Bool {
        reorder(ids: ids, direction: .backward)
    }

    private enum ReorderDirection { case front, back, forward, backward }

    @discardableResult
    private func reorder(ids: [UUID], direction: ReorderDirection) -> Bool {
        let wanted = Set(ids)
        guard !wanted.isEmpty else { return false }
        let original = objects

        switch direction {
        case .front:
            let moving = objects.filter { wanted.contains($0.id) }
            objects.removeAll { wanted.contains($0.id) }
            objects.append(contentsOf: moving)
        case .back:
            let moving = objects.filter { wanted.contains($0.id) }
            objects.removeAll { wanted.contains($0.id) }
            objects.insert(contentsOf: moving, at: 0)
        case .forward:
            for index in stride(from: objects.count - 2, through: 0, by: -1) where wanted.contains(objects[index].id) {
                var next = index + 1
                while next < objects.count && wanted.contains(objects[next].id) { next += 1 }
                if next < objects.count {
                    objects.swapAt(index, next)
                }
            }
        case .backward:
            guard objects.count > 1 else { return false }
            for index in 1..<objects.count where wanted.contains(objects[index].id) {
                var previous = index - 1
                while previous >= 0 && wanted.contains(objects[previous].id) { previous -= 1 }
                if previous >= 0 {
                    objects.swapAt(index, previous)
                }
            }
        }

        return objects != original
    }
}
