import CoreGraphics
import Foundation

/// Ratio-based construction helpers used by the geometry tools.
enum GeometryConstruction {
    static func pointOnSegment(model: inout GeometryModel, startPointID: UUID, endPointID: UUID, ratio: CGFloat, name: String? = nil) -> UUID? {
        guard let start = model.points.first(where: { $0.id == startPointID }), let end = model.points.first(where: { $0.id == endPointID }), ratio.isFinite else { return nil }
        let pointID = UUID()
        model.points.append(GeometryPoint(id: pointID, name: name, position: interpolate(start.position, end.position, ratio: ratio)))
        model.constraints.append(.pointAtSegmentRatio(pointID: pointID, startPointID: startPointID, endPointID: endPointID, ratio: ratio))
        GeometryConstraintSolver.apply(&model)
        return pointID
    }

    static func pointOnAngle(model: inout GeometryModel, vertexID: UUID, startPointID: UUID, endPointID: UUID, ratio: CGFloat, name: String? = nil, length: CGFloat = 80) -> UUID? {
        guard let vertex = model.points.first(where: { $0.id == vertexID }), let start = model.points.first(where: { $0.id == startPointID }), let end = model.points.first(where: { $0.id == endPointID }), ratio.isFinite, length > 0 else { return nil }
        let a = CGPoint(x: start.position.x - vertex.position.x, y: start.position.y - vertex.position.y)
        let b = CGPoint(x: end.position.x - vertex.position.x, y: end.position.y - vertex.position.y)
        let la = hypot(a.x, a.y), lb = hypot(b.x, b.y)
        guard la > 0.0001, lb > 0.0001 else { return nil }
        let startAngle = atan2(a.y, a.x)
        let included = acos(max(-1, min(1, (a.x * b.x + a.y * b.y) / (la * lb))))
        let signed: CGFloat = (a.x * b.y - a.y * b.x) >= 0 ? 1 : -1
        let targetAngle = startAngle + signed * included * ratio
        let pointID = UUID()
        model.points.append(GeometryPoint(id: pointID, name: name, position: CGPoint(x: vertex.position.x + length * cos(targetAngle), y: vertex.position.y + length * sin(targetAngle))))
        model.constraints.append(.pointAtAngleRatio(pointID: pointID, vertexID: vertexID, startPointID: startPointID, endPointID: endPointID, ratio: ratio, length: length))
        GeometryConstraintSolver.apply(&model)
        return pointID
    }

    /// Creates a point constrained to a line and returns its ID. The initial
    /// position is projected immediately, so the object is valid from creation.
    static func attachPointToLine(model: inout GeometryModel, pointID: UUID, lineID: UUID, segmentOnly: Bool = false) -> Bool {
        GeometryInteractionEngine.attachPointToLine(&model, pointID: pointID, lineID: lineID, segmentOnly: segmentOnly)
    }

    /// Creates a new dependent point at the nearest position on a line.
    static func createPointOnLine(model: inout GeometryModel, lineID: UUID, position: CGPoint, name: String? = nil, segmentOnly: Bool = false) -> UUID? {
        guard let line = model.lines.first(where: { $0.id == lineID }),
              let a = model.points.first(where: { $0.id == line.startPointID }),
              let b = model.points.first(where: { $0.id == line.endPointID }) else { return nil }
        let projected = project(position, onto: a.position, b.position, kind: line.kind, segmentOnly: segmentOnly)
        let pointID = UUID()
        model.points.append(GeometryPoint(id: pointID, name: name, position: projected))
        guard GeometryInteractionEngine.attachPointToLine(&model, pointID: pointID, lineID: lineID, segmentOnly: segmentOnly) else { return nil }
        return pointID
    }

    private static func interpolate(_ a: CGPoint, _ b: CGPoint, ratio: CGFloat) -> CGPoint {
        CGPoint(x: a.x + (b.x - a.x) * ratio, y: a.y + (b.y - a.y) * ratio)
    }

    private static func project(_ p: CGPoint, onto a: CGPoint, _ b: CGPoint, kind: GeometryLine.Kind, segmentOnly: Bool) -> CGPoint {
        let dx = b.x - a.x, dy = b.y - a.y
        let q = dx * dx + dy * dy
        guard q > 0.000001 else { return a }
        var t = ((p.x - a.x) * dx + (p.y - a.y) * dy) / q
        if segmentOnly || kind == .segment { t = max(0, min(1, t)) }
        if kind == .ray { t = max(0, t) }
        return CGPoint(x: a.x + t * dx, y: a.y + t * dy)
    }
}
