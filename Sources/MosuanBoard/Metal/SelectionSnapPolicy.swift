import Foundation
import CoreGraphics
import simd

/// Pure geometry policy used by the selection system.
/// Screen-space tolerance keeps snapping predictable at every zoom level.
enum SelectionSnapPolicy {
    static let defaultScreenTolerance: CGFloat = 18

    struct Candidate {
        let point: CGPoint
        let priority: Int
        let distance: CGFloat
    }

    /// Returns the best candidate for the requested point.
    /// Priority: vertex > edge midpoint > edge projection.
    static func bestCandidate(
        requested: CGPoint,
        vertices: [CGPoint],
        closed: Bool,
        tolerance: CGFloat = defaultScreenTolerance
    ) -> CGPoint? {
        guard !vertices.isEmpty else { return nil }

        var best: Candidate?
        let vertexPriority = 0
        let midpointPriority = 1
        let edgePriority = 2

        func consider(_ point: CGPoint, priority: Int) {
            let distance = hypot(point.x - requested.x, point.y - requested.y)
            guard distance <= tolerance else { return }
            let candidate = Candidate(point: point, priority: priority, distance: distance)
            guard let current = best else { best = candidate; return }
            if candidate.priority < current.priority ||
                (candidate.priority == current.priority && candidate.distance < current.distance) {
                best = candidate
            }
        }

        for vertex in vertices {
            consider(vertex, priority: vertexPriority)
        }

        guard vertices.count >= 2 else { return best?.point }
        let count = closed ? vertices.count : vertices.count - 1
        for index in 0..<count {
            let a = vertices[index]
            let b = vertices[(index + 1) % vertices.count]
            let midpoint = CGPoint(x: (a.x + b.x) * 0.5, y: (a.y + b.y) * 0.5)
            consider(midpoint, priority: midpointPriority)

            let dx = b.x - a.x
            let dy = b.y - a.y
            let lengthSquared = dx * dx + dy * dy
            guard lengthSquared > 0.000001 else { continue }
            let t = max(0, min(1, ((requested.x - a.x) * dx + (requested.y - a.y) * dy) / lengthSquared))
            let projection = CGPoint(x: a.x + dx * t, y: a.y + dy * t)
            consider(projection, priority: edgePriority)
        }

        return best?.point
    }
}
