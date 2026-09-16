import Foundation
import simd

extension InkMetalView {
    /// Erases only the portions of freehand strokes touched by the eraser path.
    /// The existing object/whole-stroke eraser remains unchanged for the default mode.
    func erasePartialAlongPath(_ path: [SIMD2<Float>]) {
        guard !path.isEmpty else { return }
        let state = currentPageState()
        guard !state.strokes.isEmpty else { return }

        let canvasPath = path.map { canvasPoint(from: $0) }
        let tolerance: Float = 14 / max(Float(zoomPercent) / 100, 0.25)
        var anyChanged = false
        var rebuilt: [CanvasStroke] = []
        rebuilt.reserveCapacity(state.strokes.count + 8)

        for stroke in state.strokes {
            guard stroke.points.count >= 2 else {
                rebuilt.append(stroke)
                continue
            }

            var runs: [[InkPoint]] = []
            var run: [InkPoint] = []
            run.reserveCapacity(stroke.points.count)
            var strokeChanged = false

            func flushRun() {
                if run.count >= 2 { runs.append(run) }
                run.removeAll(keepingCapacity: true)
            }

            for i in stroke.points.indices {
                let point = SIMD2(stroke.points[i].x, stroke.points[i].y)
                let nearPoint = distanceToPath(point, canvasPath) <= tolerance
                var nearSegment = nearPoint
                if i > 0 && !nearSegment {
                    let previous = SIMD2(stroke.points[i - 1].x, stroke.points[i - 1].y)
                    let midpoint = (previous + point) * 0.5
                    nearSegment = distanceToPath(midpoint, canvasPath) <= tolerance
                }

                if nearSegment {
                    flushRun()
                    strokeChanged = true
                    anyChanged = true
                } else {
                    run.append(stroke.points[i])
                }
            }
            flushRun()

            if strokeChanged {
                for segment in runs {
                    rebuilt.append(CanvasStroke(id: UUID(), points: segment, style: stroke.style, rotation: stroke.rotation))
                }
            } else {
                rebuilt.append(stroke)
            }
        }

        guard anyChanged else { return }
        replacePageState(CanvasPageState(strokes: rebuilt, objects: state.objects))
    }

    private func distanceToPath(_ point: SIMD2<Float>, _ path: [SIMD2<Float>]) -> Float {
        guard let first = path.first else { return .greatestFiniteMagnitude }
        if path.count == 1 { return simd_distance(point, first) }
        var best = Float.greatestFiniteMagnitude
        for pair in zip(path, path.dropFirst()) {
            let a = pair.0
            let b = pair.1
            let d = b - a
            let lengthSquared = simd_length_squared(d)
            if lengthSquared < 0.0001 {
                best = min(best, simd_distance(point, a))
                continue
            }
            let t = max(0, min(1, simd_dot(point - a, d) / lengthSquared))
            best = min(best, simd_distance(point, a + d * t))
        }
        return best
    }
}
