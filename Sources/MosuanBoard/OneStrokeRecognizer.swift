import CoreGraphics
import Foundation
import simd

/// Conservative, dependency-free recognizer for the "一笔成型" classroom tool.
/// It intentionally recognizes only very obvious straight lines and simple closed polygons.
/// If a gesture is ambiguous, callers should keep the original freehand stroke.
enum OneStrokeRecognizer {
    enum Result: Equatable {
        case line(CGPoint, CGPoint)
        case polygon([CGPoint])
    }

    static func recognize(_ input: [InkPoint]) -> Result? {
        let points = deduplicated(input.map { CGPoint(x: CGFloat($0.x), y: CGFloat($0.y)) })
        guard points.count >= 4 else { return nil }
        guard let box = bounds(points) else { return nil }
        let diagonal = max(hypot(box.width, box.height), 1)
        let length = pathLength(points)
        guard length >= 30 else { return nil }

        // First, reject obviously straight strokes as a structured line.
        if isLine(points, diagonal: diagonal, pathLength: length) {
            return .line(points[0], points[points.count - 1])
        }

        // Closed gestures are candidates for a simple polygon. The closure threshold is
        // intentionally forgiving for a teacher drawing quickly with a stylus.
        let closure = hypot(points[0].x - points[points.count - 1].x,
                            points[0].y - points[points.count - 1].y)
        guard closure <= max(18, diagonal * 0.20), length / diagonal >= 1.35 else { return nil }

        let simplified = rdp(points, epsilon: max(4, diagonal * 0.035))
        guard simplified.count >= 3 && simplified.count <= 8 else { return nil }
        let vertices = simplified
        let fit = polygonFitError(points, vertices: vertices, closed: true)
        guard fit <= max(10, diagonal * 0.065) else { return nil }

        // Avoid converting round handwriting into a many-sided polygon. A polygon should
        // have real corner turns; each accepted corner must be reasonably sharp.
        let cornerCount = vertices.indices.filter { index in
            let prev = vertices[(index - 1 + vertices.count) % vertices.count]
            let current = vertices[index]
            let next = vertices[(index + 1) % vertices.count]
            return cornerAngle(prev, current, next) < 145
        }.count
        guard cornerCount >= 3 else { return nil }

        return .polygon(vertices)
    }

    private static func isLine(_ points: [CGPoint], diagonal: CGFloat, pathLength: CGFloat) -> Bool {
        guard let first = points.first, let last = points.last else { return false }
        let baseline = hypot(last.x - first.x, last.y - first.y)
        guard baseline >= 30, baseline / max(pathLength, 1) > 0.88 else { return false }
        let tolerance = max(5, diagonal * 0.035)
        let maxDeviation = points.map { distanceToSegment($0, first, last) }.max() ?? .greatestFiniteMagnitude
        return maxDeviation <= tolerance
    }

    private static func polygonFitError(_ points: [CGPoint], vertices: [CGPoint], closed: Bool) -> CGFloat {
        guard vertices.count >= 3 else { return .greatestFiniteMagnitude }
        var maxError: CGFloat = 0
        for point in points {
            var best = CGFloat.greatestFiniteMagnitude
            for i in vertices.indices {
                let a = vertices[i]
                let b = vertices[(i + 1) % vertices.count]
                best = min(best, distanceToSegment(point, a, b))
            }
            maxError = max(maxError, best)
        }
        return maxError
    }

    private static func cornerAngle(_ a: CGPoint, _ b: CGPoint, _ c: CGPoint) -> CGFloat {
        let v1 = CGVector(dx: a.x - b.x, dy: a.y - b.y)
        let v2 = CGVector(dx: c.x - b.x, dy: c.y - b.y)
        let d1 = hypot(v1.dx, v1.dy)
        let d2 = hypot(v2.dx, v2.dy)
        guard d1 > 0.001, d2 > 0.001 else { return 180 }
        let cosine = max(-1, min(1, (v1.dx * v2.dx + v1.dy * v2.dy) / (d1 * d2)))
        return acos(cosine) * 180 / .pi
    }

    private static func deduplicated(_ points: [CGPoint]) -> [CGPoint] {
        guard let first = points.first else { return [] }
        var result = [first]
        for point in points.dropFirst() {
            if hypot(point.x - result[result.count - 1].x,
                     point.y - result[result.count - 1].y) >= 1 {
                result.append(point)
            }
        }
        return result
    }

    private static func pathLength(_ points: [CGPoint]) -> CGFloat {
        zip(points, points.dropFirst()).reduce(0) { $0 + hypot($1.1.x - $1.0.x, $1.1.y - $1.0.y) }
    }

    private static func bounds(_ points: [CGPoint]) -> CGRect? {
        guard let first = points.first else { return nil }
        var minX = first.x, maxX = first.x, minY = first.y, maxY = first.y
        for p in points.dropFirst() {
            minX = min(minX, p.x); maxX = max(maxX, p.x)
            minY = min(minY, p.y); maxY = max(maxY, p.y)
        }
        return CGRect(x: minX, y: minY, width: maxX - minX, height: maxY - minY)
    }

    private static func distanceToSegment(_ p: CGPoint, _ a: CGPoint, _ b: CGPoint) -> CGFloat {
        let dx = b.x - a.x, dy = b.y - a.y
        let length2 = dx * dx + dy * dy
        guard length2 > 0.0001 else { return hypot(p.x - a.x, p.y - a.y) }
        let t = max(0, min(1, ((p.x - a.x) * dx + (p.y - a.y) * dy) / length2))
        let q = CGPoint(x: a.x + t * dx, y: a.y + t * dy)
        return hypot(p.x - q.x, p.y - q.y)
    }

    private static func rdp(_ points: [CGPoint], epsilon: CGFloat) -> [CGPoint] {
        guard points.count > 2 else { return points }
        let first = points[0], last = points[points.count - 1]
        var maxDistance: CGFloat = 0
        var index = 0
        for i in 1..<(points.count - 1) {
            let distance = distanceToSegment(points[i], first, last)
            if distance > maxDistance { maxDistance = distance; index = i }
        }
        guard maxDistance > epsilon else { return [first, last] }
        let left = rdp(Array(points[0...index]), epsilon: epsilon)
        let right = rdp(Array(points[index...]), epsilon: epsilon)
        return Array(left.dropLast()) + right
    }
}
