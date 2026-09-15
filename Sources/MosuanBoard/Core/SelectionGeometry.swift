import CoreGraphics
import Foundation

/// Small, testable geometry helpers shared by selection interactions.
/// Screen-space tolerances are intentionally converted by the caller so
/// snapping remains stable while zooming.
enum SelectionGeometry {
    struct SnapCandidate {
        let point: CGPoint
        let distance: CGFloat
        let priority: Int
    }

    static func projection(of point: CGPoint, ontoSegment a: CGPoint, _ b: CGPoint) -> CGPoint? {
        let dx = b.x - a.x
        let dy = b.y - a.y
        let lengthSquared = dx * dx + dy * dy
        guard lengthSquared > 0.0001 else { return nil }
        let t = max(0, min(1, ((point.x - a.x) * dx + (point.y - a.y) * dy) / lengthSquared))
        return CGPoint(x: a.x + t * dx, y: a.y + t * dy)
    }

    static func candidate(for requested: CGPoint, vertex: CGPoint, tolerance: CGFloat) -> SnapCandidate? {
        let distance = hypot(vertex.x - requested.x, vertex.y - requested.y)
        guard distance <= tolerance else { return nil }
        return SnapCandidate(point: vertex, distance: distance, priority: 0)
    }

    static func candidate(for requested: CGPoint, segmentStart a: CGPoint, segmentEnd b: CGPoint, tolerance: CGFloat) -> SnapCandidate? {
        guard let projected = projection(of: requested, ontoSegment: a, b) else { return nil }
        let distance = hypot(projected.x - requested.x, projected.y - requested.y)
        guard distance <= tolerance else { return nil }

        // Prefer a true midpoint over an arbitrary point on a long edge.
        let midpoint = CGPoint(x: (a.x + b.x) * 0.5, y: (a.y + b.y) * 0.5)
        let midpointDistance = hypot(midpoint.x - projected.x, midpoint.y - projected.y)
        let isMidpoint = midpointDistance <= max(0.5, tolerance * 0.08)
        return SnapCandidate(point: projected, distance: distance, priority: isMidpoint ? 1 : 2)
    }

    static func better(_ lhs: SnapCandidate?, than rhs: SnapCandidate?) -> SnapCandidate? {
        guard let lhs else { return rhs }
        guard let rhs else { return lhs }
        if lhs.priority != rhs.priority { return lhs.priority < rhs.priority ? lhs : rhs }
        return lhs.distance < rhs.distance ? lhs : rhs
    }
}
