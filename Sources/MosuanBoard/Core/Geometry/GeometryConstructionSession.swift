import CoreGraphics
import Foundation

/// State machine for click-based geometric construction.
/// Rendering and selection remain outside this type.
struct GeometryConstructionSession {
    enum Tool: Equatable {
        case line
        case segment
        case ray
        case circleThreePoint
        case polygon
    }

    enum State: Equatable {
        case idle
        case waitingForPoints(Tool, [CGPoint])
    }

    enum Result: Equatable {
        case line(kind: Tool, start: CGPoint, end: CGPoint)
        case circle(center: CGPoint, radius: CGFloat)
        case polygon(points: [CGPoint])
    }

    private(set) var state: State = .idle
    private(set) var lastResult: Result?

    var isConstructing: Bool {
        if case .waitingForPoints = state { return true }
        return false
    }

    var tool: Tool? {
        if case let .waitingForPoints(tool, _) = state { return tool }
        return nil
    }

    var points: [CGPoint] {
        if case let .waitingForPoints(_, points) = state { return points }
        return []
    }

    mutating func begin(_ tool: Tool) {
        lastResult = nil
        state = .waitingForPoints(tool, [])
    }

    mutating func cancel() {
        state = .idle
        lastResult = nil
    }

    /// Adds one click. Completion always returns to idle so construction never
    /// leaks into selection/control-point editing.
    @discardableResult
    mutating func click(at point: CGPoint, closeTolerance: CGFloat = 18) -> Result? {
        guard case let .waitingForPoints(tool, current) = state else { return nil }

        switch tool {
        case .line, .segment, .ray:
            guard let first = current.first else {
                state = .waitingForPoints(tool, [point])
                return nil
            }
            guard distance(first, point) > 0.5 else { return nil }
            return finish(.line(kind: tool, start: first, end: point))

        case .circleThreePoint:
            let updated = current + [point]
            guard updated.count < 3 else {
                guard let circle = Self.circleThrough(updated[0], updated[1], updated[2]) else {
                    state = .waitingForPoints(tool, Array(updated.dropLast()))
                    return nil
                }
                return finish(.circle(center: circle.center, radius: circle.radius))
            }
            state = .waitingForPoints(tool, updated)
            return nil

        case .polygon:
            if current.count >= 3 && distance(point, current[0]) <= max(1, closeTolerance) {
                return finish(.polygon(points: current))
            }
            state = .waitingForPoints(tool, current + [point])
            return nil
        }
    }

    /// Returns a live preview without mutating construction state.
    func preview(to point: CGPoint) -> Result? {
        guard case let .waitingForPoints(tool, current) = state else { return nil }
        guard let first = current.first else { return nil }

        switch tool {
        case .line, .segment, .ray:
            return .line(kind: tool, start: first, end: point)
        case .circleThreePoint:
            guard current.count == 2,
                  let circle = Self.circleThrough(first, current[1], point) else { return nil }
            return .circle(center: circle.center, radius: circle.radius)
        case .polygon:
            guard current.count >= 2 else { return nil }
            return .polygon(points: current + [point])
        }
    }

    private mutating func finish(_ result: Result) -> Result {
        lastResult = result
        state = .idle
        return result
    }

    private static func circleThrough(_ a: CGPoint, _ b: CGPoint, _ c: CGPoint) -> (center: CGPoint, radius: CGFloat)? {
        let denominator = 2 * (a.x * (b.y - c.y) + b.x * (c.y - a.y) + c.x * (a.y - b.y))
        guard abs(denominator) > 0.000001 else { return nil }

        let aa = a.x * a.x + a.y * a.y
        let bb = b.x * b.x + b.y * b.y
        let cc = c.x * c.x + c.y * c.y
        let ux = (aa * (b.y - c.y) + bb * (c.y - a.y) + cc * (a.y - b.y)) / denominator
        let uy = (aa * (c.x - b.x) + bb * (a.x - c.x) + cc * (b.x - a.x)) / denominator
        let center = CGPoint(x: ux, y: uy)
        let radius = distance(center, a)
        guard radius.isFinite, radius > 0.5 else { return nil }
        return (center, radius)
    }

    private static func distance(_ a: CGPoint, _ b: CGPoint) -> CGFloat {
        hypot(a.x - b.x, a.y - b.y)
    }
}
