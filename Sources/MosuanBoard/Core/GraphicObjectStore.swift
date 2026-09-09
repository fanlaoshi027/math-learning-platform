import CoreGraphics
import Foundation

/// Lightweight object-layer store used to introduce structured geometry without
/// replacing the existing high-performance ink stroke engine in one step.
final class GraphicObjectStore {
    private(set) var objects: [GraphicObject] = []

    var isEmpty: Bool { objects.isEmpty }

    func clear() { objects.removeAll(keepingCapacity: true) }

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

    func insert(_ object: GraphicObject) { objects.append(object) }

    func object(with id: UUID) -> GraphicObject? { objects.first { $0.id == id } }

    func update(_ object: GraphicObject) {
        guard let index = objects.firstIndex(where: { $0.id == object.id }) else {
            objects.append(object)
            return
        }
        objects[index] = object
    }

    func remove(id: UUID) { objects.removeAll { $0.id != id ? false : true } }

    func transformedPoints(of object: GraphicObject) -> [CGPoint] {
        if object.kind == .parameterizedTriangle, let triangle = object.triangleModel {
            return triangle.vertices().map { transformedPoint($0, in: object) }
        }
        if object.kind == .dynamicAngle, let dynamicAngle = object.dynamicAngleModel {
            return dynamicAngle.model.points.map { transformedPoint($0.position, in: object) }
        }
        return object.geometry.points.map { transformedPoint($0, in: object) }
    }

    func transformedPoints(of id: UUID) -> [CGPoint]? {
        guard let object = object(with: id) else { return nil }
        return transformedPoints(of: object)
    }

    @discardableResult
    func updateTriangleModel(id: UUID, _ update: (inout ParameterizedTriangle) -> Void) -> Bool {
        guard let index = objects.firstIndex(where: { $0.id == id }),
              objects[index].kind == .parameterizedTriangle,
              var triangle = objects[index].triangleModel else { return false }
        update(&triangle)
        objects[index].triangleModel = triangle
        return true
    }

    @discardableResult
    func dragTriangleVertex(id: UUID, vertexIndex: Int, to point: CGPoint) -> Bool {
        guard let index = objects.firstIndex(where: { $0.id == id }),
              objects[index].kind == .parameterizedTriangle,
              var triangle = objects[index].triangleModel else { return false }
        guard GeometryInteractionEngine.dragTriangle(&triangle, vertexIndex: vertexIndex, to: point) else { return false }
        objects[index].triangleModel = triangle
        return true
    }

    @discardableResult
    func setTriangleParameter(id: UUID, parameter: TriangleParameter, value: CGFloat) -> Bool {
        guard let index = objects.firstIndex(where: { $0.id == id }),
              objects[index].kind == .parameterizedTriangle,
              var triangle = objects[index].triangleModel else { return false }
        GeometryInteractionEngine.setTriangleParameter(&triangle, parameter: parameter, value: value)
        objects[index].triangleModel = triangle
        return true
    }

    @discardableResult
    func setDynamicAngle(id: UUID, degrees: CGFloat) -> Bool {
        guard let index = objects.firstIndex(where: { $0.id == id }),
              objects[index].kind == .dynamicAngle,
              var dynamicAngle = objects[index].dynamicAngleModel else { return false }
        guard dynamicAngle.setAngle(degrees) else { return false }
        objects[index].dynamicAngleModel = dynamicAngle
        objects[index].geometryModel = dynamicAngle.model
        return true
    }

    @discardableResult
    func dragDynamicAngleEndpoint(id: UUID, to point: CGPoint) -> Bool {
        guard let index = objects.firstIndex(where: { $0.id == id }),
              objects[index].kind == .dynamicAngle,
              var dynamicAngle = objects[index].dynamicAngleModel else { return false }
        guard let endIndex = dynamicAngle.model.points.firstIndex(where: { $0.id == dynamicAngle.endPointID }),
              !dynamicAngle.model.points[endIndex].isFixed,
              let vertex = dynamicAngle.model.points.first(where: { $0.id == dynamicAngle.vertexPointID }),
              let start = dynamicAngle.model.points.first(where: { $0.id == dynamicAngle.startPointID }) else { return false }
        let dx = point.x - vertex.position.x
        let dy = point.y - vertex.position.y
        guard hypot(dx, dy) > 0.001 else { return false }
        let sx = start.position.x - vertex.position.x
        let sy = start.position.y - vertex.position.y
        let cross = sx * dy - sy * dx
        let dot = sx * dx + sy * dy
        var degrees = atan2(abs(cross), dot) * 180 / .pi
        if degrees < 0 { degrees += 360 }
        degrees = min(170, max(10, degrees))
        guard dynamicAngle.setAngle(degrees) else { return false }
        objects[index].dynamicAngleModel = dynamicAngle
        objects[index].geometryModel = dynamicAngle.model
        return true
    }

    func nearestLineEndpoint(to point: CGPoint, tolerance: CGFloat = 12) -> (id: UUID, endpoint: Int, distance: CGFloat)? {
        var best: (id: UUID, endpoint: Int, distance: CGFloat)?
        for object in objects where object.kind == .line {
            guard object.geometry.points.count >= 2 else { continue }
            for endpoint in 0...1 {
                let p = transformedPoint(object.geometry.points[endpoint], in: object)
                let d = hypot(p.x - point.x, p.y - point.y)
                guard d <= tolerance else { continue }
                if best == nil || d < best!.distance { best = (object.id, endpoint, d) }
            }
        }
        return best
    }

    func bounds(of id: UUID) -> CGRect? {
        guard let object = object(with: id) else { return nil }
        return bounds(of: object)
    }

    func bounds(of object: GraphicObject) -> CGRect? {
        switch object.kind {
        case .group:
            return object.children.compactMap(bounds(of:)).reduce(nil) { $0?.union($1) ?? $1 }
        case .rectangle, .ellipse, .coordinateSystem, .functionGraph:
            let p1 = transformedPoint(CGPoint(x: object.geometry.x, y: object.geometry.y), in: object)
            let p2 = transformedPoint(CGPoint(x: object.geometry.x + object.geometry.width, y: object.geometry.y + object.geometry.height), in: object)
            return CGRect(x: min(p1.x, p2.x), y: min(p1.y, p2.y), width: abs(p2.x - p1.x), height: abs(p2.y - p1.y)).insetBy(dx: -max(1, object.style.strokeWidth), dy: -max(1, object.style.strokeWidth))
        default:
            let points = transformedPoints(of: object)
            guard let first = points.first else { return nil }
            var result = CGRect(origin: first, size: .zero)
            for point in points.dropFirst() { result = result.union(CGRect(origin: point, size: .zero)) }
            return result.insetBy(dx: -max(1, object.style.strokeWidth), dy: -max(1, object.style.strokeWidth))
        }
    }

    func hitTest(at point: CGPoint, tolerance: CGFloat = 10) -> UUID? {
        for object in objects.reversed() where hitTest(object, at: point, tolerance: tolerance) {
            return object.id
        }
        return nil
    }

    private func hitTest(_ object: GraphicObject, at point: CGPoint, tolerance: CGFloat) -> Bool {
        let t = tolerance + object.style.strokeWidth
        switch object.kind {
        case .line, .arrow, .parameterizedTriangle, .dynamicAngle:
            let p = transformedPoints(of: object)
            guard p.count >= 2 else { return false }
            let closingPoints = object.kind == .parameterizedTriangle ? p.dropFirst() + [p[0]] : p.dropFirst()
            return zip(p, closingPoints).contains { distance(point, toSegment: $0.0, $0.1) <= t }
        case .polygon:
            let p = transformedPoints(of: object)
            guard p.count >= 3 else { return false }
            if pointInPolygon(point, p) { return true }
            return zip(p, p.dropFirst() + [p[0]]).contains { distance(point, toSegment: $0.0, $0.1) <= t }
        case .rectangle, .ellipse:
            guard let r = bounds(of: object) else { return false }
            if object.kind == .rectangle { return r.insetBy(dx: -t, dy: -t).contains(point) }
            let cx = r.midX, cy = r.midY
            let rx = max(r.width * 0.5, 0.001), ry = max(r.height * 0.5, 0.001)
            let normalized = pow((point.x - cx) / rx, 2) + pow((point.y - cy) / ry, 2)
            return normalized <= 1.0 + (t / max(rx, ry))
        case .freehandStroke:
            let p = transformedPoints(of: object)
            return zip(p, p.dropFirst()).contains { distance(point, toSegment: $0.0, $0.1) <= t }
        case .coordinateSystem, .functionGraph:
            return bounds(of: object)?.insetBy(dx: -t, dy: -t).contains(point) ?? false
        case .group:
            return object.children.contains { hitTest($0, at: point, tolerance: tolerance) }
        }
    }

    func objectsIntersecting(_ rect: CGRect, fullyContained: Bool = false) -> [UUID] {
        objects.compactMap { object in
            guard let b = bounds(of: object) else { return nil }
            return (fullyContained ? rect.contains(b) : rect.intersects(b)) ? object.id : nil
        }
    }

    func objectsHitByScribble(_ path: [CGPoint], tolerance: CGFloat = 12) -> [UUID] {
        guard path.count >= 2 else { return [] }
        return objects.filter { object in
            guard let b = bounds(of: object) else { return false }
            let expanded = b.insetBy(dx: -tolerance, dy: -tolerance)
            if path.contains(where: expanded.contains) { return true }
            return zip(path, path.dropFirst()).contains { expanded.intersects(segmentBounds($0, $1)) }
        }.map(\.id)
    }

    @discardableResult
    func eraseByScribble(_ path: [CGPoint], tolerance: CGFloat = 12) -> [UUID] {
        let ids = Set(objectsHitByScribble(path, tolerance: tolerance))
        guard !ids.isEmpty else { return [] }
        objects.removeAll { ids.contains($0.id) }
        return Array(ids)
    }

    @discardableResult
    func moveLineEndpoint(id: UUID, endpoint: Int, to point: CGPoint) -> Bool {
        guard let index = objects.firstIndex(where: { $0.id == id }),
              objects[index].kind == .line,
              objects[index].geometry.points.count >= 2,
              endpoint == 0 || endpoint == 1 else { return false }
        objects[index].geometry.points[endpoint] = point
        return true
    }

    @discardableResult
    func transform(id: UUID, scale: CGSize? = nil, rotation: CGFloat? = nil, position: CGPoint? = nil) -> Bool {
        guard let index = objects.firstIndex(where: { $0.id == id }) else { return false }
        if let scale { objects[index].transform.scale = scale }
        if let rotation { objects[index].transform.rotation = rotation }
        if let position { objects[index].transform.position = position }
        return true
    }

    func exportObjects() -> [GraphicObject] { objects }
    func importObjects(_ value: [GraphicObject]) { objects = value }

    private func transformedPoint(_ point: CGPoint, in object: GraphicObject) -> CGPoint {
        let center = object.transform.rotationCenter
        let translated = CGPoint(x: point.x - center.x, y: point.y - center.y)
        let scaled = CGPoint(x: translated.x * object.transform.scale.width, y: translated.y * object.transform.scale.height)
        let c = cos(object.transform.rotation), s = sin(object.transform.rotation)
        return CGPoint(x: scaled.x * c - scaled.y * s + center.x + object.transform.position.x,
                       y: scaled.x * s + scaled.y * c + center.y + object.transform.position.y)
    }

    private func segmentBounds(_ a: CGPoint, _ b: CGPoint) -> CGRect {
        CGRect(x: min(a.x, b.x), y: min(a.y, b.y), width: abs(a.x - b.x), height: abs(a.y - b.y))
    }

    private func distance(_ point: CGPoint, toSegment a: CGPoint, _ b: CGPoint) -> CGFloat {
        let dx = b.x - a.x, dy = b.y - a.y
        let lengthSquared = dx * dx + dy * dy
        if lengthSquared == 0 { return hypot(point.x - a.x, point.y - a.y) }
        let t = max(0, min(1, ((point.x - a.x) * dx + (point.y - a.y) * dy) / lengthSquared))
        let projection = CGPoint(x: a.x + t * dx, y: a.y + t * dy)
        return hypot(point.x - projection.x, point.y - projection.y)
    }

    private func pointInPolygon(_ point: CGPoint, _ polygon: [CGPoint]) -> Bool {
        guard polygon.count >= 3 else { return false }
        var inside = false
        var j = polygon.count - 1
        for i in polygon.indices {
            let a = polygon[i], b = polygon[j]
            if (a.y > point.y) != (b.y > point.y) {
                let denominator = b.y - a.y
                if denominator != 0 {
                    let x = (b.x - a.x) * (point.y - a.y) / denominator + a.x
                    if point.x < x { inside.toggle() }
                }
            }
            j = i
        }
        return inside
    }
}
