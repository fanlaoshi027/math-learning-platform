import CoreGraphics
import Foundation

/// Lightweight object-layer store used to introduce structured geometry without
/// replacing the existing high-performance ink stroke engine in one step.
final class GraphicObjectStore {
    private(set) var objects: [GraphicObject] = []

    var isEmpty: Bool { objects.isEmpty }

    func clear() {
        objects.removeAll(keepingCapacity: true)
    }

    @discardableResult
    func addLine(from start: CGPoint, to end: CGPoint, style: GraphicObject.Style) -> UUID {
        let object = GraphicObject.line(from: start, to: end, style: style)
        objects.append(object)
        return object.id
    }

    @discardableResult
    func addPolygon(points: [CGPoint], style: GraphicObject.Style) -> UUID {
        let object = GraphicObject.polygon(points: points, style: style)
        objects.append(object)
        return object.id
    }

    func object(with id: UUID) -> GraphicObject? {
        objects.first { $0.id == id }
    }

    func update(_ object: GraphicObject) {
        guard let index = objects.firstIndex(where: { $0.id == object.id }) else { return }
        objects[index] = object
    }

    func remove(id: UUID) {
        objects.removeAll { $0.id == id }
    }

    /// Returns the nearest structured line endpoint within the supplied radius.
    func nearestLineEndpoint(to point: CGPoint, tolerance: CGFloat = 12) -> (id: UUID, endpoint: Int, distance: CGFloat)? {
        var best: (id: UUID, endpoint: Int, distance: CGFloat)?
        for object in objects where object.kind == .line {
            guard object.geometry.points.count >= 2 else { continue }
            for endpoint in 0...1 {
                let p = transformedPoint(object.geometry.points[endpoint], in: object)
                let d = hypot(p.x - point.x, p.y - point.y)
                guard d <= tolerance else { continue }
                if best == nil || d < best!.distance {
                    best = (object.id, endpoint, d)
                }
            }
        }
        return best
    }

    /// Returns the axis-aligned bounds of an object's transformed geometry.
    func bounds(of id: UUID) -> CGRect? {
        guard let object = object(with: id) else { return nil }
        return bounds(of: object)
    }

    func bounds(of object: GraphicObject) -> CGRect? {
        let points: [CGPoint]
        switch object.kind {
        case .line, .polygon, .arrow, .freehandStroke:
            points = object.geometry.points
        case .rectangle, .ellipse, .coordinateSystem, .functionGraph:
            points = [
                CGPoint(x: object.geometry.x, y: object.geometry.y),
                CGPoint(x: object.geometry.x + object.geometry.width, y: object.geometry.y + object.geometry.height)
            ]
        case .group:
            return object.children.compactMap(bounds(of:)).reduce(nil) { partial, next in
                partial?.union(next) ?? next
            }
        }
        guard let first = points.first else { return nil }
        var result = CGRect(origin: transformedPoint(first, in: object), size: .zero)
        for point in points.dropFirst() {
            result = result.union(CGRect(origin: transformedPoint(point, in: object), size: .zero))
        }
        return result.insetBy(dx: -max(1, object.style.strokeWidth), dy: -max(1, object.style.strokeWidth))
    }

    /// Hit-tests a structured object in object/world coordinates.
    func hitTest(at point: CGPoint, tolerance: CGFloat = 10) -> UUID? {
        for object in objects.reversed() {
            if hitTest(object, at: point, tolerance: tolerance) { return object.id }
        }
        return nil
    }

    /// Finds objects touched by a freehand scribble. This is intentionally an
    /// object-level operation: a line, polygon, or future math object can be
    /// deleted as one unit rather than requiring the user to hit a tiny segment.
    func objectsHitByScribble(_ path: [CGPoint], tolerance: CGFloat = 12) -> [UUID] {
        guard path.count >= 2 else { return [] }
        return objects.filter { object in
            guard let bounds = bounds(of: object) else { return false }
            let expanded = bounds.insetBy(dx: -tolerance, dy: -tolerance)
            if path.contains(where: expanded.contains) { return true }
            return zip(path, path.dropFirst()).contains { expanded.intersects(segmentBounds($0, $1)) }
        }.map(\.id)
    }

    /// Deletes every structured object touched by a scribble and returns the
    /// stable IDs that were removed so the caller can record one undo action.
    @discardableResult
    func eraseByScribble(_ path: [CGPoint], tolerance: CGFloat = 12) -> [UUID] {
        let ids = Set(objectsHitByScribble(path, tolerance: tolerance))
        guard !ids.isEmpty else { return [] }
        objects.removeAll { ids.contains($0.id) }
        return Array(ids)
    }

    private func hitTest(_ object: GraphicObject, at point: CGPoint, tolerance: CGFloat) -> Bool {
        switch object.kind {
        case .line, .arrow:
            guard object.geometry.points.count >= 2 else { return false }
            let a = transformedPoint(object.geometry.points[0], in: object)
            let b = transformedPoint(object.geometry.points[1], in: object)
            return distance(point, toSegment: a, b) <= tolerance + object.style.strokeWidth
        case .polygon:
            guard object.geometry.points.count >= 2 else { return false }
            let transformed = object.geometry.points.map { transformedPoint($0, in: object) }
            return zip(transformed, transformed.dropFirst()).contains { distance(point, toSegment: $0, $1) <= tolerance + object.style.strokeWidth }
        case .rectangle, .ellipse, .coordinateSystem, .functionGraph:
            return bounds(of: object)?.insetBy(dx: -tolerance, dy: -tolerance).contains(point) == true
        case .freehandStroke:
            guard object.geometry.points.count >= 2 else { return false }
            let points = object.geometry.points.map { transformedPoint($0, in: object) }
            return zip(points, points.dropFirst()).contains { distance(point, toSegment: $0, $1) <= tolerance + object.style.strokeWidth }
        case .group:
            return object.children.contains { hitTest($0, at: point, tolerance: tolerance) }
        }
    }

    /// Moves one endpoint of a structured line while preserving the other endpoint.
    @discardableResult
    func moveLineEndpoint(id: UUID, endpoint: Int, to point: CGPoint) -> Bool {
        guard let index = objects.firstIndex(where: { $0.id == id }),
              objects[index].kind == .line,
              objects[index].geometry.points.count >= 2,
              endpoint == 0 || endpoint == 1 else { return false }
        objects[index].geometry.points[endpoint] = point
        return true
    }

    /// Applies a transform to an object without rewriting its source geometry.
    @discardableResult
    func transform(id: UUID, scale: CGSize? = nil, rotation: CGFloat? = nil, position: CGPoint? = nil) -> Bool {
        guard let index = objects.firstIndex(where: { $0.id == id }) else { return false }
        if let scale { objects[index].transform.scale = scale }
        if let rotation { objects[index].transform.rotation = rotation }
        if let position { objects[index].transform.position = position }
        return true
    }

    func exportObjects() -> [GraphicObject] {
        objects
    }

    func importObjects(_ value: [GraphicObject]) {
        objects = value
    }

    private func transformedPoint(_ point: CGPoint, in object: GraphicObject) -> CGPoint {
        let center = object.transform.rotationCenter
        let translated = CGPoint(x: point.x - center.x, y: point.y - center.y)
        let scaled = CGPoint(
            x: translated.x * object.transform.scale.width,
            y: translated.y * object.transform.scale.height
        )
        let c = cos(object.transform.rotation)
        let s = sin(object.transform.rotation)
        return CGPoint(
            x: scaled.x * c - scaled.y * s + center.x + object.transform.position.x,
            y: scaled.x * s + scaled.y * c + center.y + object.transform.position.y
        )
    }

    private func segmentBounds(_ a: CGPoint, _ b: CGPoint) -> CGRect {
        CGRect(
            x: min(a.x, b.x),
            y: min(a.y, b.y),
            width: abs(a.x - b.x),
            height: abs(a.y - b.y)
        )
    }

    private func distance(_ point: CGPoint, toSegment a: CGPoint, _ b: CGPoint) -> CGFloat {
        let dx = b.x - a.x
        let dy = b.y - a.y
        let lengthSquared = dx * dx + dy * dy
        if lengthSquared == 0 { return hypot(point.x - a.x, point.y - a.y) }
        let t = max(0, min(1, ((point.x - a.x) * dx + (point.y - a.y) * dy) / lengthSquared))
        let projection = CGPoint(x: a.x + t * dx, y: a.y + t * dy)
        return hypot(point.x - projection.x, point.y - projection.y)
    }
}
