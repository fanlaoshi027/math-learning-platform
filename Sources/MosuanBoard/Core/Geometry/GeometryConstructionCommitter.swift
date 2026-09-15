import CoreGraphics
import Foundation

/// Converts completed click-based construction results into structured objects.
/// This boundary keeps the interaction state machine independent from the store.
struct GeometryConstructionCommitter {
    @discardableResult
    static func commit(
        _ result: GeometryConstructionSession.Result,
        to store: GraphicObjectStore,
        style: GraphicObject.Style = GraphicObject.Style()
    ) -> UUID? {
        switch result {
        case let .line(kind, start, end):
            let startID = UUID()
            let endID = UUID()
            let lineID = UUID()
            let lineKind: GeometryLine.Kind
            switch kind {
            case .line: lineKind = .line
            case .segment: lineKind = .segment
            case .ray: lineKind = .ray
            default: return nil
            }
            let model = GeometryModel(
                points: [
                    GeometryPoint(id: startID, position: start),
                    GeometryPoint(id: endID, position: end)
                ],
                lines: [
                    GeometryLine(
                        id: lineID,
                        startPointID: startID,
                        endPointID: endID,
                        kind: lineKind
                    )
                ]
            )
            let object = GraphicObject(
                id: lineID,
                kind: .line,
                style: style,
                geometry: GraphicObject.Geometry(points: [start, end]),
                geometryModel: model
            )
            store.insert(object)
            return object.id

        case let .circle(center, radius):
            guard radius.isFinite, radius > 0 else { return nil }
            let diameter = radius * 2
            let rect = CGRect(
                x: center.x - radius,
                y: center.y - radius,
                width: diameter,
                height: diameter
            )
            let object = GraphicObject.ellipse(rect, style: style)
            store.insert(object)
            return object.id

        case let .polygon(points):
            guard points.count >= 3 else { return nil }
            let object = GraphicObject.polygon(points: points, style: style)
            store.insert(object)
            return object.id
        }
    }
}
