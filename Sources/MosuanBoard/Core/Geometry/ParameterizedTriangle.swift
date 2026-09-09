import CoreGraphics
import Foundation

enum TriangleKind: String, Codable, CaseIterable {
    case arbitrary
    case isosceles
    case equilateral
    case right
}

/// A triangle whose geometry can be regenerated from teaching-friendly parameters.
struct ParameterizedTriangle: Codable, Equatable, Identifiable {
    let id: UUID
    var kind: TriangleKind
    var anchor: CGPoint
    var rotation: CGFloat
    var baseLength: CGFloat
    var legLength: CGFloat
    var apexAngleDegrees: CGFloat
    var lockedBaseLength: Bool
    var lockedLegLength: Bool
    var lockedApexAngle: Bool
    var anchorPointName: String?

    init(
        id: UUID = UUID(),
        kind: TriangleKind = .isosceles,
        anchor: CGPoint = .zero,
        rotation: CGFloat = 0,
        baseLength: CGFloat = 160,
        legLength: CGFloat = 120,
        apexAngleDegrees: CGFloat = 60,
        lockedBaseLength: Bool = false,
        lockedLegLength: Bool = false,
        lockedApexAngle: Bool = false,
        anchorPointName: String? = "A"
    ) {
        self.id = id
        self.kind = kind
        self.anchor = anchor
        self.rotation = rotation
        self.baseLength = baseLength
        self.legLength = legLength
        self.apexAngleDegrees = apexAngleDegrees
        self.lockedBaseLength = lockedBaseLength
        self.lockedLegLength = lockedLegLength
        self.lockedApexAngle = lockedApexAngle
        self.anchorPointName = anchorPointName
    }

    /// Returns A, B, C in canvas coordinates. A is the anchored vertex.
    func vertices() -> [CGPoint] {
        let angle = max(1, min(179, apexAngleDegrees)) * .pi / 180
        let leg = max(1, legLength)
        let halfBase = leg * sin(angle / 2)
        let height = leg * cos(angle / 2)

        // A is the apex/anchor. The local triangle is symmetric around +Y.
        let localA = CGPoint.zero
        let localB = CGPoint(x: -halfBase, y: height)
        let localC = CGPoint(x: halfBase, y: height)
        return [localA, rotate(localB), rotate(localC)].map {
            CGPoint(x: $0.x + anchor.x, y: $0.y + anchor.y)
        }
    }

    mutating func setApexAngle(_ degrees: CGFloat) {
        guard !lockedApexAngle else { return }
        apexAngleDegrees = max(1, min(179, degrees))
    }

    mutating func setLegLength(_ length: CGFloat) {
        guard !lockedLegLength else { return }
        legLength = max(1, length)
    }

    mutating func setBaseLength(_ length: CGFloat) {
        guard !lockedBaseLength else { return }
        baseLength = max(1, length)
        guard kind == .isosceles else { return }
        let half = baseLength / 2
        let angle = max(1, min(179, apexAngleDegrees)) * .pi / 180
        let requiredLeg = half / max(sin(angle / 2), 0.001)
        if !lockedLegLength { legLength = requiredLeg }
    }

    mutating func setRotation(_ radians: CGFloat) {
        rotation = radians
    }

    private func rotate(_ point: CGPoint) -> CGPoint {
        let c = cos(rotation), s = sin(rotation)
        return CGPoint(x: point.x * c - point.y * s,
                       y: point.x * s + point.y * c)
    }
}
