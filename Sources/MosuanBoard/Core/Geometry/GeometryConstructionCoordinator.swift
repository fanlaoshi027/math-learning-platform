import CoreGraphics

/// Small interaction coordinator for click-based geometry tools.
/// Rendering and AppKit input stay outside this type so mouse/tablet/touch can
/// share exactly the same construction state machine.
struct GeometryConstructionCoordinator {
    private(set) var session = GeometryConstructionSession()

    var isActive: Bool { session.isConstructing }
    var tool: GeometryConstructionSession.Tool? { session.tool }
    var points: [CGPoint] { session.points }

    mutating func begin(_ tool: GeometryConstructionSession.Tool) {
        session.begin(tool)
    }

    mutating func cancel() {
        session.cancel()
    }

    @discardableResult
    mutating func click(at point: CGPoint, closeTolerance: CGFloat = 18) -> GeometryConstructionSession.Result? {
        session.click(at: point, closeTolerance: closeTolerance)
    }

    func preview(to point: CGPoint) -> GeometryConstructionSession.Result? {
        session.preview(to: point)
    }
}
