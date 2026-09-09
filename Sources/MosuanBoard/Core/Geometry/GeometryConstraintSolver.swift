import CoreGraphics
import Foundation

enum GeometryConstraintSolver {
    static func apply(_ model: inout GeometryModel) {
        // Parameter-driven angle annotations are constraints in their own right.
        // Applying them before the explicit constraint list keeps the parameter
        // slider/animation path independent from UI code.
        for annotation in model.angles {
            if let target = GeometryAngleCalculator.targetDegrees(in: model, annotation: annotation) {
                setAngle(&model, angleID: annotation.id, targetDegrees: target)
            }
        }

        for constraint in model.constraints {
            switch constraint {
            case let .fixedPoint(pointID, position):
                set(&model, pointID: pointID, position: position)
            case let .pointBinding(master, follower, offset):
                guard let masterPoint = model.points.first(where: { $0.id == master }) else { continue }
                set(&model, pointID: follower, position: CGPoint(x: masterPoint.position.x + offset.x, y: masterPoint.position.y + offset.y))
            case let .pointOnLine(pointID, lineID):
                project(&model, pointID: pointID, lineID: lineID, lowerBound: nil, upperBound: nil)
            case let .pointOnSegment(pointID, lineID):
                project(&model, pointID: pointID, lineID: lineID, lowerBound: 0, upperBound: 1)
            case let .pointOnCircle(pointID, circleID):
                _ = pointID; _ = circleID
            case let .fixedLength(segmentID, length):
                setLength(&model, lineID: segmentID, targetLength: max(0.001, length))
            case let .equalLength(first, second):
                if let length = lineLength(model, lineID: first) {
                    setLength(&model, lineID: second, targetLength: length)
                }
            case let .lengthRatio(first, second, multiplier):
                guard let firstLength = lineLength(model, lineID: first) else { continue }
                setLength(&model, lineID: second, targetLength: firstLength / max(0.0001, multiplier))
            case let .fixedAngle(angleID, degrees):
                setAngle(&model, angleID: angleID, targetDegrees: degrees)
            case let .equalAngle(first, second):
                if let value = angleValue(in: model, id: first) {
                    setAngle(&model, angleID: second, targetDegrees: value)
                }
            case let .angleRatio(first, second, multiplier):
                if let value = angleValue(in: model, id: first) {
                    setAngle(&model, angleID: second, targetDegrees: value / max(0.0001, multiplier))
                }
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

    private static func project(_ model: inout GeometryModel, pointID: UUID, lineID: UUID, lowerBound: CGFloat?, upperBound: CGFloat?) {
        guard let pointIndex = model.points.firstIndex(where: { $0.id == pointID }),
              let line = model.lines.first(where: { $0.id == lineID }),
              let start = model.points.first(where: { $0.id == line.startPointID }),
              let end = model.points.first(where: { $0.id == line.endPointID }) else { return }

        let dx = end.position.x - start.position.x
        let dy = end.position.y - start.position.y
        let denominator = dx * dx + dy * dy
        guard denominator > 0 else { return }

        let p = model.points[pointIndex].position
        var t = ((p.x - start.position.x) * dx + (p.y - start.position.y) * dy) / denominator
        if let lowerBound { t = max(lowerBound, t) }
        if let upperBound { t = min(upperBound, t) }
        model.points[pointIndex].position = CGPoint(x: start.position.x + t * dx, y: start.position.y + t * dy)
    }

    private static func lineLength(_ model: GeometryModel, lineID: UUID) -> CGFloat? {
        guard let line = model.lines.first(where: { $0.id == lineID }),
              let a = model.points.first(where: { $0.id == line.startPointID }),
              let b = model.points.first(where: { $0.id == line.endPointID }) else { return nil }
        return hypot(b.position.x - a.position.x, b.position.y - a.position.y)
    }

    /// Keeps the line's start point fixed and moves its end point to the requested length.
    private static func setLength(_ model: inout GeometryModel, lineID: UUID, targetLength: CGFloat) {
        guard let lineIndex = model.lines.firstIndex(where: { $0.id == lineID }),
              let start = model.points.first(where: { $0.id == model.lines[lineIndex].startPointID }),
              let endIndex = model.points.firstIndex(where: { $0.id == model.lines[lineIndex].endPointID }),
              !model.points[endIndex].isFixed else { return }

        let dx = model.points[endIndex].position.x - start.position.x
        let dy = model.points[endIndex].position.y - start.position.y
        let current = hypot(dx, dy)
        let angle = current > 0.0001 ? atan2(dy, dx) : 0
        model.points[endIndex].position = CGPoint(
            x: start.position.x + targetLength * cos(angle),
            y: start.position.y + targetLength * sin(angle)
        )
    }

    private static func angleValue(in model: GeometryModel, id: UUID) -> CGFloat? {
        guard let annotation = model.angles.first(where: { $0.id == id }) else { return nil }
        return GeometryAngleCalculator.value(in: model, annotation: annotation)?.degrees
    }

    /// Changes an angle by rotating its end ray around the angle vertex.
    /// The start ray remains the reference ray.
    private static func setAngle(_ model: inout GeometryModel, angleID: UUID, targetDegrees: CGFloat) {
        guard let annotation = model.angles.first(where: { $0.id == angleID }),
              let vertex = model.points.first(where: { $0.id == annotation.vertexID }),
              let start = model.points.first(where: { $0.id == annotation.startPointID }),
              let endIndex = model.points.firstIndex(where: { $0.id == annotation.endPointID }),
              !model.points[endIndex].isFixed else { return }

        let startAngle = atan2(start.position.y - vertex.position.y, start.position.x - vertex.position.x)
        let endVector = CGPoint(x: model.points[endIndex].position.x - vertex.position.x,
                                y: model.points[endIndex].position.y - vertex.position.y)
        let radius = max(0.001, hypot(endVector.x, endVector.y))
        let direction = max(0.001, min(179.999, targetDegrees)) * .pi / 180
        let currentCross = (start.position.x - vertex.position.x) * endVector.y - (start.position.y - vertex.position.y) * endVector.x
        let signedDirection: CGFloat = currentCross >= 0 ? 1 : -1
        let angle = startAngle + signedDirection * direction
        model.points[endIndex].position = CGPoint(
            x: vertex.position.x + radius * cos(angle),
            y: vertex.position.y + radius * sin(angle)
        )
    }
}
