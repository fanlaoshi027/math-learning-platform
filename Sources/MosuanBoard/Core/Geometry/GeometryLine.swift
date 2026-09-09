import CoreGraphics
import Foundation

/// A geometric line primitive backed by two named points.
/// The endpoints are geometry references; rendering decides how the primitive is displayed.
struct GeometryLine: Codable, Equatable, Identifiable {
    enum Kind: String, Codable, CaseIterable {
        case line
        case segment
        case ray
    }

    let id: UUID
    var startPointID: UUID
    var endPointID: UUID
    var kind: Kind

    init(
        id: UUID = UUID(),
        startPointID: UUID,
        endPointID: UUID,
        kind: Kind = .segment
    ) {
        self.id = id
        self.startPointID = startPointID
        self.endPointID = endPointID
        self.kind = kind
    }
}
