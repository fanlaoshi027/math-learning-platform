import Foundation

/// Thin boundary for the handwriting engine.
///
/// The UI and Metal renderer must not grow their own smoothing/prediction
/// algorithm. The production implementation is backed by Google Ink Stroke
/// Modeler; this boundary only adapts its input/output to InkPoint.
protocol InkStrokeEngine {
    mutating func begin(at point: InkPoint, time: TimeInterval)
    mutating func update(point: InkPoint, time: TimeInterval) -> [InkPoint]
    mutating func end(point: InkPoint, time: TimeInterval) -> [InkPoint]
    mutating func cancel()
}

/// Isolated adapter boundary. It deliberately performs no smoothing.
/// The upstream C++ target is linked in Package.swift; the next change is to
/// replace this adapter's internals with the upstream StrokeModeler calls.
struct PassthroughInkStrokeEngine: InkStrokeEngine {
    mutating func begin(at point: InkPoint, time: TimeInterval) {}
    mutating func update(point: InkPoint, time: TimeInterval) -> [InkPoint] { [point] }
    mutating func end(point: InkPoint, time: TimeInterval) -> [InkPoint] { [point] }
    mutating func cancel() {}
}
