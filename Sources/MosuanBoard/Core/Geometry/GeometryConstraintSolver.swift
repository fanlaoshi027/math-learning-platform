import CoreGraphics
import Foundation

enum GeometryConstraintSolver {
    static func apply(_ model: inout GeometryModel) {
        // Parameter references are the primary driver for interactive sliders and
        // animation. Explicit constraints below may further relate those values.
        applyParameterizedLengths(&model)
        applyParameterizedAngles(&model)

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

    private static func applyParameterizedLengths(_ model: inout GeometryModel) {
        // A parameterized segment is treated like a target constraint. Direction
        // is preserved while the movable endpoint changes its distance.
        for line in model.lines where line.kind == .segment {
            guard let reference = line.lengthReference,
                  let target = reference.resolved(using: model.parameters) else { continue }
            setLength(&model, lineID: line.id, targetLength: target)
        }
    }

    private static func applyParameterizedAngles(_ model: inout GeometryModel) {
        for annotation in model.angles {
            guard let target = GeometryAngleCalculator.targetDegrees(in: model, annotation: annotation) else { continue }
            setAngle(&model, angleID: annotation.id, targetDegrees: target)
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

    /// Keeps the line direction and target length. If the end is fixed but the
    /// start is movable, the start point is adjusted instead.
    private static func setLength(_ model: inout GeometryModel, lineID: UUID, targetLength: CGFloat) {
        guard let line = model.lines.first(where: { $0.id == lineID }),
              let startIndex = model.points.firstIndex(where: { $0.id == line.startPointID }),
              let endIndex = model.points.firstIndex(where: { $0.id == line.endPointID }) else { return }

        let start = model.points[startIndex].position
        let end = model.points[endIndex].position
        let dx = end.x - start.x
        let dy = end.y - start.y
        let current = hypot(dx, dy)
        let angle = current > 0.0001 ? atan2(dy, dx) : 0
        let target = max(0.001, targetLength)

        if !model.points[endIndex].isFixed {
            model.points[endIndex].position = CGPoint(
                x: start.x + target * cos(angle),
                y: start.y + target * sin(angle)
            )
        } else if !model.points[startIndex].isFixed {
            model.points[startIndex].position = CGPoint(
                x: end.x - target * cos(angle),
                y: end.y - target * sin(angle)
            )
        }
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

        let startVector = CGPoint(x: start.position.x - vertex.position.x, y: start.position.y - vertex.position.y)
        let endVector = CGPoint(x: model.points[endIndex].position.x - vertex.position.x,
                                y: model.points[endIndex].position.y - vertex.position.y)
        let startLength = hypot(startVector.x, startVector.y)
        let radius = max(0.001, hypot(endVector.x, endVector.y))
        guard startLength > 0.0001 else { return }

        let startAngle = atan2(startVector.y, startVector.x)
        let direction = max(0.001, min(179.999, targetDegrees)) * .pi / 180
        let currentCross = startVector.x * endVector.y - startVector.y * endVector.x
        let signedDirection: CGFloat = currentCross >= 0 ? 1 : -1
        let angle = startAngle + signedDirection * direction
        model.points[endIndex].position = CGPoint(
            x: vertex.position.x + radius * cos(angle),
            y: vertex.position.y + radius * sin(angle)
        )
    }
}
