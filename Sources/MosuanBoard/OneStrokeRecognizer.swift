import CoreGraphics
import Foundation
import simd

/// Conservative, dependency-free recognizer for the "一笔成型" classroom tool.
/// The recognizer prefers obvious geometric shapes and otherwise returns a smoothed curve.
enum OneStrokeRecognizer {
    enum Result: Equatable {
        case line(CGPoint, CGPoint)
        case circle(center: CGPoint, radius: CGFloat)
        case ellipse(CGRect)
        case polygon([CGPoint])
        case curve([CGPoint])
    }

    static func recognize(_ input: [InkPoint]) -> Result? {
        let points = deduplicated(input.map { CGPoint(x: CGFloat($0.x), y: CGFloat($0.y)) })
        guard points.count >= 4 else { return nil }
        guard let box = bounds(points) else { return nil }
        let diagonal = max(hypot(box.width, box.height), 1)
        let length = pathLength(points)
        guard length >= 30 else { return nil }

        if isLine(points, diagonal: diagonal, pathLength: length) {
            return .line(points[0], points[points.count - 1])
        }

        let closure = hypot(points[0].x - points[points.count - 1].x,
                            points[0].y - points[points.count - 1].y)
        let isClosed = closure <= max(18, diagonal * 0.20) && length / diagonal >= 1.35

        if isClosed {
            // Check circles/ellipses before polygon fitting. A teacher's quick circle is
            // rarely perfectly round, so the fit deliberately allows modest hand wobble.
            if let ellipse = ellipseFit(points, box: box, pathLength: length) {
                let simplified = rdp(points + [points[0]], epsilon: max(4, diagonal * 0.035))
                let cornerCount = simplified.count >= 4 ? simplified.dropLast().indices.filter { index in
                    let prev = simplified[max(0, index - 1)]
                    let current = simplified[index]
                    let next = simplified[min(simplified.count - 1, index + 1)]
                    return cornerAngle(prev, current, next) < 135
                }.count : 0
                // Strong corners should stay polygons rather than becoming rounded shapes.
                if cornerCount <= 2 {
                    if abs(ellipse.width - ellipse.height) <= max(14, max(ellipse.width, ellipse.height) * 0.12) {
                        return .circle(center: CGPoint(x: ellipse.midX, y: ellipse.midY), radius: (ellipse.width + ellipse.height) * 0.25)
                    }
                    return .ellipse(ellipse)
                }
            }

            let simplified = rdp(points + [points[0]], epsilon: max(4, diagonal * 0.035))
            let vertices = Array(simplified.dropLast())
            guard vertices.count >= 3 && vertices.count <= 8 else { return nil }
            let fit = polygonFitError(points, vertices: vertices)
            guard fit <= max(10, diagonal * 0.065) else { return nil }
            let cornerCount = vertices.indices.filter { index in
                let prev = vertices[(index - 1 + vertices.count) % vertices.count]
                let current = vertices[index]
                let next = vertices[(index + 1) % vertices.count]
                return cornerAngle(prev, current, next) < 145
            }.count
            guard cornerCount >= 3 else { return nil }
            return .polygon(vertices)
        }

        // A non-straight, non-closed gesture is a curve. Simplify it enough to keep the
        // stored stroke light, while retaining the teacher's overall shape.
        let curve = rdp(points, epsilon: max(1.5, min(4, diagonal * 0.012)))
        guard curve.count >= 3 else { return nil }
        return .curve(curve)
    }

    private static func ellipseFit(_ points: [CGPoint], box: CGRect, pathLength: CGFloat) -> CGRect? {
        guard box.width >= 20, box.height >= 20 else { return nil }
        let cx = box.midX, cy = box.midY
        let rx = box.width / 2, ry = box.height / 2
        guard rx > 0.001, ry > 0.001 else { return nil }
        var totalError: CGFloat = 0
        var maxError: CGFloat = 0
        var samples = 0
        for p in points {
            let normalized = hypot((p.x - cx) / rx, (p.y - cy) / ry)
            let error = abs(normalized - 1) * min(rx, ry)
            totalError += error
            maxError = max(maxError, error)
            samples += 1
        }
        let meanError = totalError / CGFloat(max(samples, 1))
        let tolerance = max(10, min(rx, ry) * 0.16)
        let maxTolerance = max(18, min(rx, ry) * 0.32)
        guard meanError <= tolerance, maxError <= maxTolerance else { return nil }
        guard pathLength / (CGFloat.pi * (rx + ry)) >= 0.72 else { return nil }
        return box
    }

    private static func isLine(_ points: [CGPoint], diagonal: CGFloat, pathLength: CGFloat) -> Bool {
        guard let first = points.first, let last = points.last else { return false }
        let baseline = hypot(last.x - first.x, last.y - first.y)
        guard baseline >= 30, baseline / max(pathLength, 1) > 0.88 else { return false }
        let tolerance = max(5, diagonal * 0.035)
        let maxDeviation = points.map { distanceToSegment($0, first, last) }.max() ?? .greatestFiniteMagnitude
        return maxDeviation <= tolerance
    }

    private static func polygonFitError(_ points: [CGPoint], vertices: [CGPoint]) -> CGFloat {
        guard vertices.count >= 3 else { return .greatestFiniteMagnitude }
        var maxError: CGFloat = 0
        for point in points {
            var best = CGFloat.greatestFiniteMagnitude
            for i in vertices.indices {
                best = min(best, distanceToSegment(point, vertices[i], vertices[(i + 1) % vertices.count]))
            }
            maxError = max(maxError, best)
        }
        return maxError
    }

    private static func cornerAngle(_ a: CGPoint, _ b: CGPoint, _ c: CGPoint) -> CGFloat {
        let v1 = CGVector(dx: a.x - b.x, dy: a.y - b.y)
        let v2 = CGVector(dx: c.x - b.x, dy: c.y - b.y)
        let d1 = hypot(v1.dx, v1.dy), d2 = hypot(v2.dx, v2.dy)
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
        var maxDistance: CGFloat = 0, index = 0
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
