import CoreGraphics
import Foundation

/// Ratio-based construction helpers used by the geometry tools.
///
/// A ratio is deliberately not restricted to 0...1:
/// - segment ratio 1/3 puts P one third of AB from A;
/// - segment ratio 1.5 puts P beyond B at 1.5·AB from A;
/// - angle ratio 1/2 puts the constructed ray halfway between the two rays;
/// - angle ratio 2 continues past the terminal ray by another full angle.
enum GeometryConstruction {
    static func pointOnSegment(
        model: inout GeometryModel,
        startPointID: UUID,
        endPointID: UUID,
        ratio: CGFloat,
        name: String? = nil
    ) -> UUID? {
        guard let start = model.points.first(where: { $0.id == startPointID }),
              let end = model.points.first(where: { $0.id == endPointID }),
              ratio.isFinite else { return nil }

        let pointID = UUID()
        let point = GeometryPoint(
            id: pointID,
            name: name,
            position: interpolate(start.position, end.position, ratio: ratio)
        )
        model.points.append(point)
        model.constraints.append(.pointAtSegmentRatio(
            pointID: pointID,
            startPointID: startPointID,
            endPointID: endPointID,
            ratio: ratio
        ))
        GeometryConstraintSolver.apply(&model)
        return pointID
    }

    static func pointOnAngle(
        model: inout GeometryModel,
        vertexID: UUID,
        startPointID: UUID,
        endPointID: UUID,
        ratio: CGFloat,
        name: String? = nil,
        length: CGFloat = 80
    ) -> UUID? {
        guard let vertex = model.points.first(where: { $0.id == vertexID }),
              let start = model.points.first(where: { $0.id == startPointID }),
              let end = model.points.first(where: { $0.id == endPointID }),
              ratio.isFinite, length > 0 else { return nil }

        let a = CGPoint(x: start.position.x - vertex.position.x, y: start.position.y - vertex.position.y)
        let b = CGPoint(x: end.position.x - vertex.position.x, y: end.position.y - vertex.position.y)
        let la = hypot(a.x, a.y)
        let lb = hypot(b.x, b.y)
        guard la > 0.0001, lb > 0.0001 else { return nil }

        let startAngle = atan2(a.y, a.x)
        let included = acos(max(-1, min(1, (a.x * b.x + a.y * b.y) / (la * lb))))
        let cross = a.x * b.y - a.y * b.x
        let signed = cross >= 0 ? 1 : -1
        let targetAngle = startAngle + CGFloat(signed) * included * ratio
        let pointID = UUID()
        let point = GeometryPoint(
            id: pointID,
            name: name,
            position: CGPoint(
                x: vertex.position.x + length * cos(targetAngle),
                y: vertex.position.y + length * sin(targetAngle)
            )
        )
        model.points.append(point)
        model.constraints.append(.pointAtAngleRatio(
            pointID: pointID,
            vertexID: vertexID,
            startPointID: startPointID,
            endPointID: endPointID,
            ratio: ratio,
            length: length
        ))
        GeometryConstraintSolver.apply(&model)
        return pointID
    }

    private static func interpolate(_ a: CGPoint, _ b: CGPoint, ratio: CGFloat) -> CGPoint {
        CGPoint(
            x: a.x + (b.x - a.x) * ratio,
            y: a.y + (b.y - a.y) * ratio
        )
    }
}
