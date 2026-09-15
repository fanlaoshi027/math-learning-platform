import Foundation
import MetalKit
import CoreGraphics
import simd

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

    /// The renderer's mature delete path is used unchanged. We only add the lock
    /// policy at its boundary so no locked object can be removed accidentally.
    @discardableResult
    func deleteSelectedRespectingLocks() -> Bool {
        guard !selectedObjectIDs.contains(where: {
            editingObjectStore?.object(with: $0)?.isLocked == true
        }) else { return false }
        guard hasSelection else { return false }
        deleteSelected()
        return true
    }

    func makeSelectionClipboardDataForEditing() -> Data? {
        makeSelectionClipboardData()
    }

    @discardableResult
    func pasteSelectionClipboardDataForEditing(_ data: Data) -> Bool {
        pasteSelectionClipboardData(data)
    }

    /// Snap the rotation center only against the currently selected geometric
    /// objects. Freehand strokes are deliberately excluded. Candidates are tested
    /// in view/screen coordinates so the 18 px tolerance remains stable at every zoom.
    /// Priority is vertex > edge midpoint > edge projection.
    func setSelectionRotationCenter(to point: SIMD2<Float>) {
        guard !selectedObjectIDs.isEmpty,
              let store = editingObjectStore,
              !selectedObjectsAreAnyLocked else { return }

        let requested = CGPoint(x: CGFloat(point.x), y: CGFloat(point.y))
        var best: SelectionSnapPolicy.Candidate?

        func consider(_ candidate: CGPoint, priority: Int) {
            let distance = hypot(candidate.x - requested.x, candidate.y - requested.y)
            guard distance <= SelectionSnapPolicy.defaultScreenTolerance else { return }
            let value = SelectionSnapPolicy.Candidate(point: candidate, priority: priority, distance: distance)
            guard let current = best else {
                best = value
                return
            }
            if value.priority < current.priority ||
                (value.priority == current.priority && value.distance < current.distance) {
                best = value
            }
        }

        for id in selectedObjectIDs {
            guard let object = store.object(with: id),
                  object.kind != .freehandStroke else { continue }

            let canvasPoints = store.transformedPoints(of: object)
            guard !canvasPoints.isEmpty else { continue }
            let screenPoints = canvasPoints.map { viewPoint(from: SIMD2(Float($0.x), Float($0.y))) }

            // Run the mature geometry policy per object. This avoids accidentally
            // creating an edge between two different selected objects.
            let closed: Bool
            switch object.kind {
            case .polygon, .rectangle, .ellipse, .parameterizedTriangle, .geometryPoint:
                closed = true
            case .line, .arrow, .dynamicAngle, .coordinateSystem, .functionGraph:
                closed = false
            case .group, .freehandStroke:
                continue
            }

            if let candidate = SelectionSnapPolicy.bestCandidate(
                requested: requested,
                vertices: screenPoints,
                closed: closed
            ) {
                let priorityDistance = hypot(candidate.x - requested.x, candidate.y - requested.y)
                let inferredPriority: Int
                // Re-evaluate priority explicitly so this aggregate comparison keeps
                // the same vertex/midpoint/projection ordering as SelectionSnapPolicy.
                let vertexHit = screenPoints.contains {
                    hypot($0.x - requested.x, $0.y - requested.y) <= SelectionSnapPolicy.defaultScreenTolerance &&
                    hypot($0.x - candidate.x, $0.y - candidate.y) < 0.5
                }
                if vertexHit {
                    inferredPriority = 0
                } else if screenPoints.count >= 2 {
                    var midpointHit = false
                    let count = closed ? screenPoints.count : screenPoints.count - 1
                    for index in 0..<count {
                        let a = screenPoints[index]
                        let b = screenPoints[(index + 1) % screenPoints.count]
                        let midpoint = CGPoint(x: (a.x + b.x) * 0.5, y: (a.y + b.y) * 0.5)
                        if hypot(midpoint.x - candidate.x, midpoint.y - candidate.y) < 0.5 {
                            midpointHit = true
                            break
                        }
                    }
                    inferredPriority = midpointHit ? 1 : 2
                } else {
                    inferredPriority = 2
                }
                consider(candidate, priority: inferredPriority)
                _ = priorityDistance
            }
        }

        let target = best?.point ?? requested
        setRotationCenter(to: SIMD2(Float(target.x), Float(target.y)))
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
