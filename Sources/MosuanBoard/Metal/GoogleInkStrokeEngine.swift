import Foundation
import InkStrokeModelerBridge

/// Thin Swift adapter around Google's upstream Ink Stroke Modeler.
/// No smoothing or prediction algorithm is implemented in Swift.
final class GoogleInkStrokeEngine: InkStrokeEngine {
    private var handle: UnsafeMutablePointer<MosuanInkStrokeModeler>?
    private var buffer = [MosuanInkResult](repeating: MosuanInkResult(x: 0, y: 0, pressure: 1, tilt: -1, orientation: -1, time: 0), count: 128)

    init() {
        handle = mosuan_ink_create()
    }

    deinit {
        if let handle { mosuan_ink_destroy(handle) }
    }

    func begin(at point: InkPoint, time: TimeInterval) {
        _ = updateInternal(eventType: 0, point: point, time: time)
    }

    func update(point: InkPoint, time: TimeInterval) -> [InkPoint] {
        updateInternal(eventType: 1, point: point, time: time)
    }

    func end(point: InkPoint, time: TimeInterval) -> [InkPoint] {
        updateInternal(eventType: 2, point: point, time: time)
    }

    func predicted() -> [InkPoint] {
        guard let handle else { return [] }
        let count = buffer.withUnsafeMutableBufferPointer { storage in
            mosuan_ink_predict(handle, storage.baseAddress, Int32(storage.count))
        }
        guard count > 0 else { return [] }
        return buffer.prefix(Int(count)).map {
            InkPoint(x: $0.x, y: $0.y, pressure: $0.pressure >= 0 ? $0.pressure : 1)
        }
    }

    func cancel() {
        if let handle { mosuan_ink_reset(handle) }
    }

    private func updateInternal(eventType: Int32, point: InkPoint, time: TimeInterval) -> [InkPoint] {
        guard let handle else { return [point] }
        let count = buffer.withUnsafeMutableBufferPointer { storage in
            mosuan_ink_update(
                handle,
                eventType,
                point.x,
                point.y,
                time,
                point.pressure,
                -1,
                -1,
                storage.baseAddress,
                Int32(storage.count)
            )
        }
        guard count > 0 else { return [point] }
        return buffer.prefix(Int(count)).map {
            InkPoint(x: $0.x, y: $0.y, pressure: $0.pressure >= 0 ? $0.pressure : point.pressure)
        }
    }
}
