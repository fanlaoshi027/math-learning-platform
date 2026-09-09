import CoreGraphics
import Foundation

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
    var label: GeometryPointLabel

    init(id: UUID = UUID(), name: String? = nil, position: CGPoint, isFixed: Bool = false, label: GeometryPointLabel? = nil) {
        self.id = id
        self.name = name
        self.position = position
        self.isFixed = isFixed
        self.label = label ?? GeometryPointLabel(mode: name == nil ? .hidden : .name, text: name ?? "")
    }

    var isLabelVisible: Bool { label.mode != .hidden && !label.text.isEmpty }
    mutating func setLabelVisible(_ visible: Bool) { label.mode = visible ? .name : .hidden }
    mutating func setLabelText(_ text: String) {
        name = text.isEmpty ? nil : text
        label.text = text
        if !text.isEmpty && label.mode == .hidden { label.mode = .name }
    }
}

struct GeometryModel: Codable, Equatable, Identifiable {
    let id: UUID
    var points: [GeometryPoint]
    var lines: [GeometryLine]
    var constraints: [GeometryConstraint]

    init(id: UUID = UUID(), points: [GeometryPoint] = [], lines: [GeometryLine] = [], constraints: [GeometryConstraint] = []) {
        self.id = id
        self.points = points
        self.lines = lines
        self.constraints = constraints
    }

    private enum CodingKeys: String, CodingKey { case id, points, lines, constraints }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        id = try container.decode(UUID.self, forKey: .id)
        points = try container.decode([GeometryPoint].self, forKey: .points)
        lines = try container.decodeIfPresent([GeometryLine].self, forKey: .lines) ?? []
        constraints = try container.decode([GeometryConstraint].self, forKey: .constraints)
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
        guard let masterPoint = points.first(where: { $0.id == master }), let followerPoint = points.first(where: { $0.id == follower }) else { return }
        let offset = CGPoint(x: followerPoint.position.x - masterPoint.position.x, y: followerPoint.position.y - masterPoint.position.y)
        constraints.removeAll { constraint in
            if case let .pointBinding(existingMaster, existingFollower, _) = constraint {
                return existingMaster == master || existingFollower == follower
            }
            return false
        }
        constraints.append(.pointBinding(master: master, follower: follower, offset: offset))
    }

    mutating func addLine(from startPointID: UUID, to endPointID: UUID, kind: GeometryLine.Kind = .segment) -> UUID? {
        guard points.contains(where: { $0.id == startPointID }), points.contains(where: { $0.id == endPointID }), startPointID != endPointID else { return nil }
        let line = GeometryLine(startPointID: startPointID, endPointID: endPointID, kind: kind)
        lines.append(line)
        return line.id
    }
}
