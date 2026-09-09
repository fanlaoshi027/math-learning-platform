import CoreGraphics
import Foundation

/// Reusable constraints for editable middle-school geometry.
/// Constraints describe relationships; the renderer remains responsible for drawing.
enum GeometryConstraint: Codable, Equatable, Identifiable {
    case fixedPoint(pointID: UUID, position: CGPoint)
    case pointBinding(master: UUID, follower: UUID, offset: CGPoint)
    case pointOnLine(pointID: UUID, lineID: UUID)
    case pointOnSegment(pointID: UUID, lineID: UUID)
    case pointOnCircle(pointID: UUID, circleID: UUID)
    case fixedLength(segmentID: UUID, length: CGFloat)
    case equalLength(first: UUID, second: UUID)
    case fixedAngle(angleID: UUID, degrees: CGFloat)
    case rotationAround(pointID: UUID, objectID: UUID)
    case parallel(first: UUID, second: UUID)
    case perpendicular(first: UUID, second: UUID)

    var id: String {
        switch self {
        case let .fixedPoint(pointID, _): return "fixedPoint:\(pointID.uuidString)"
        case let .pointBinding(master, follower, _): return "pointBinding:\(master.uuidString):\(follower.uuidString)"
        case let .pointOnLine(pointID, lineID): return "pointOnLine:\(pointID.uuidString):\(lineID.uuidString)"
        case let .pointOnSegment(pointID, lineID): return "pointOnSegment:\(pointID.uuidString):\(lineID.uuidString)"
        case let .pointOnCircle(pointID, circleID): return "pointOnCircle:\(pointID.uuidString):\(circleID.uuidString)"
        case let .fixedLength(segmentID, _): return "fixedLength:\(segmentID.uuidString)"
        case let .equalLength(first, second): return "equalLength:\(first.uuidString):\(second.uuidString)"
        case let .fixedAngle(angleID, _): return "fixedAngle:\(angleID.uuidString)"
        case let .rotationAround(pointID, objectID): return "rotationAround:\(pointID.uuidString):\(objectID.uuidString)"
        case let .parallel(first, second): return "parallel:\(first.uuidString):\(second.uuidString)"
        case let .perpendicular(first, second): return "perpendicular:\(first.uuidString):\(second.uuidString)"
        }
    }
}

struct GeometryPoint: Codable, Equatable, Identifiable {
    let id: UUID
    var name: String?
    var position: CGPoint
    var isFixed: Bool

    init(id: UUID = UUID(), name: String? = nil, position: CGPoint, isFixed: Bool = false) {
        self.id = id
        self.name = name
        self.position = position
        self.isFixed = isFixed
    }
}

struct GeometryModel: Codable, Equatable, Identifiable {
    let id: UUID
    var points: [GeometryPoint]
    var constraints: [GeometryConstraint]

    init(id: UUID = UUID(), points: [GeometryPoint] = [], constraints: [GeometryConstraint] = []) {
        self.id = id
        self.points = points
        self.constraints = constraints
    }

    mutating func setFixed(_ pointID: UUID, position: CGPoint? = nil) {
        guard let index = points.firstIndex(where: { $0.id == pointID }) else { return }
        points[index].isFixed = true
        if let position { points[index].position = position }
        constraints.removeAll { constraint in
            if case let .fixedPoint(id, _) = constraint { return id == pointID }
            return false
        }
        constraints.append(.fixedPoint(pointID: pointID, position: points[index].position))
    }

    mutating func bind(master: UUID, follower: UUID) {
        guard let masterPoint = points.first(where: { $0.id == master }),
              let followerPoint = points.first(where: { $0.id == follower }) else { return }
        let offset = CGPoint(x: followerPoint.position.x - masterPoint.position.x,
                             y: followerPoint.position.y - masterPoint.position.y)
        constraints.removeAll { constraint in
            if case let .pointBinding(existingMaster, existingFollower, _) = constraint {
                return existingMaster == master || existingFollower == follower
            }
            return false
        }
        constraints.append(.pointBinding(master: master, follower: follower, offset: offset))
    }
}
