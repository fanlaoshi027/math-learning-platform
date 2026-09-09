import CoreGraphics
import Foundation

enum TriangleParameter: Equatable {
    case baseLength
    case legLength
    case apexAngle
}

/// Shared geometry interaction logic. Platform layers only provide input coordinates.
enum GeometryInteractionEngine {
    static func hitTestPoint(in model: GeometryModel, at position: CGPoint, radius: CGFloat = 14) -> UUID? {
        let radiusSquared = radius * radius
        return model.points.min { lhs, rhs in
            distanceSquared(lhs.position, position) < distanceSquared(rhs.position, position)
        }.flatMap { point in
            distanceSquared(point.position, position) <= radiusSquared ? point.id : nil
        }
    }

    /// Drag a point while respecting fixed points, bindings and line/segment constraints.
    @discardableResult
    static func dragPoint(_ model: inout GeometryModel, pointID: UUID, to position: CGPoint) -> Bool {
        guard let index = model.points.firstIndex(where: { $0.id == pointID }), !model.points[index].isFixed else { return false }

        // Followers are driven by their master and cannot be dragged independently.
        if model.constraints.contains(where: { constraint in
            if case let .pointBinding(_, follower, _) = constraint { return follower == pointID }
            return false
        }) {
            return false
        }

        model.points[index].position = position
        GeometryConstraintSolver.apply(&model)
        return true
    }

    @discardableResult
    static func dragTriangle(_ triangle: inout ParameterizedTriangle, vertexIndex: Int, to position: CGPoint) -> Bool {
        switch vertexIndex {
        case 0:
            triangle.anchor = position
            return true
        case 1, 2:
            let dx = position.x - triangle.anchor.x
            let dy = position.y - triangle.anchor.y
            let distance = max(1, hypot(dx, dy))
            let angle = atan2(dy, dx)

            guard triangle.kind == .isosceles else { return false }
            triangle.setLegLength(distance)

            // B/C are symmetric around the triangle's local +Y axis.
            let halfApex = triangle.apexAngleDegrees * .pi / 360
            let targetDirection = vertexIndex == 1 ? angle + halfApex : angle - halfApex
            triangle.setRotation(targetDirection - .pi / 2)
            return true
        default:
            return false
        }
    }

    static func setTriangleParameter(_ triangle: inout ParameterizedTriangle, parameter: TriangleParameter, value: CGFloat) {
        switch parameter {
        case .baseLength:
            triangle.setBaseLength(value)
        case .legLength:
            triangle.setLegLength(value)
        case .apexAngle:
            triangle.setApexAngle(value)
        }
    }

    static func applyConstraints(_ model: inout GeometryModel) {
        GeometryConstraintSolver.apply(&model)
    }

    private static func distanceSquared(_ a: CGPoint, _ b: CGPoint) -> CGFloat {
        let dx = a.x - b.x
        let dy = a.y - b.y
        return dx * dx + dy * dy
    }
}
