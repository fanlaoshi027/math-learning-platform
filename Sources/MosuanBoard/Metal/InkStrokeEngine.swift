import Foundation

/// Thin boundary for the handwriting engine.
///
/// The implementation is intentionally kept behind this boundary so the UI and
/// Metal renderer do not grow their own smoothing/prediction algorithms. The
/// production implementation will be backed by Google Ink Stroke Modeler.
protocol InkStrokeEngine {
    mutating func begin(at point: InkPoint, time: TimeInterval)
    mutating func update(point: InkPoint, time: TimeInterval) -> [InkPoint]
    mutating func end(point: InkPoint, time: TimeInterval) -> [InkPoint]
    mutating func cancel()
}

/// Temporary pass-through adapter used until the upstream C++ engine is linked.
/// It deliberately performs no custom smoothing; this keeps the boundary clean
/// and prevents the current Swift prototype from becoming the long-term engine.
struct PassthroughInkStrokeEngine: InkStrokeEngine {
    mutating func begin(at point: InkPoint, time: TimeInterval) {}

    mutating func update(point: InkPoint, time: TimeInterval) -> [InkPoint] {
        [point]
    }

    mutating func end(point: InkPoint, time: TimeInterval) -> [InkPoint] {
        [point]
    }

    mutating func cancel() {}
}
