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
                let p = object.geometry.points[endpoint]
                let d = hypot(p.x - point.x, p.y - point.y)
                guard d <= tolerance else { continue }
                if best == nil || d < best!.distance {
                    best = (object.id, endpoint, d)
                }
            }
        }
        return best
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

    func exportObjects() -> [GraphicObject] {
        objects
    }

    func importObjects(_ value: [GraphicObject]) {
        objects = value
    }
}
