import CoreGraphics
import Foundation

enum GeometryConstraintSolver {
    static func apply(_ model: inout GeometryModel) {
        for constraint in model.constraints {
            switch constraint {
            case let .fixedPoint(pointID, position):
                set(&model, pointID: pointID, position: position)
            case let .pointBinding(master, follower, offset):
                guard let masterPoint = model.points.first(where: { $0.id == master }) else { continue }
                set(&model, pointID: follower, position: CGPoint(x: masterPoint.position.x + offset.x, y: masterPoint.position.y + offset.y))
            case let .pointOnLine(pointID, lineID):
                project(&model, pointID: pointID, ontoLine: lineID, segmentOnly: false)
            case let .pointOnSegment(pointID, lineID):
                project(&model, pointID: pointID, ontoLine: lineID, segmentOnly: true)
            case let .pointOnCircle(pointID, circleID):
                _ = pointID; _ = circleID
            case let .fixedLength(segmentID, length):
                _ = segmentID; _ = length
            case let .equalLength(first, second):
                _ = first; _ = second
            case let .fixedAngle(angleID, degrees):
                _ = angleID; _ = degrees
            case let .rotationAround(pointID, objectID):
                _ = pointID; _ = objectID
            case let .parallel(first, second):
                _ = first; _ = second
            case let .perpendicular(first, second):
                _ = first; _ = second
            }
        }
    }

    private static func set(_ model: inout GeometryModel, pointID: UUID, position: CGPoint) {
        guard let index = model.points.firstIndex(where: { $0.id == pointID }) else { return }
        if !model.points[index].isFixed { model.points[index].position = position }
    }

    private static func project(_ model: inout GeometryModel, pointID: UUID, ontoLine lineID: UUID, segmentOnly: Bool) {
        guard let pointIndex = model.points.firstIndex(where: { $0.id == pointID }),
              let lineIndex = model.points.firstIndex(where: { $0.id == lineID }),
              lineIndex + 1 < model.points.count else { return }
        let a = model.points[lineIndex].position
        let b = model.points[lineIndex + 1].position
        let dx = b.x - a.x, dy = b.y - a.y
        let denominator = dx * dx + dy * dy
        guard denominator > 0 else { return }
        var t = ((model.points[pointIndex].position.x - a.x) * dx + (model.points[pointIndex].position.y - a.y) * dy) / denominator
        if segmentOnly { t = max(0, min(1, t)) }
        model.points[pointIndex].position = CGPoint(x: a.x + t * dx, y: a.y + t * dy)
    }
}
