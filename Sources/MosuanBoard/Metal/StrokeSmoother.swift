import Foundation
import simd

/// Lightweight adaptive stroke smoothing for handwriting.
/// Keeps pressure untouched and only adjusts XY samples.
struct StrokeSmoother {
    struct Point {
        var position: SIMD2<Float>
        var pressure: Float
        var timestamp: Double
    }

    /// Very conservative defaults for the first real-world pen-feel test.
    /// The goal is to remove high-frequency jitter without introducing
    /// noticeable pen-lag.
    var minimumDistance: Float = 0.35
    var slowSmoothing: Float = 0.10
    var fastSmoothing: Float = 0.24
    var fastSpeed: Float = 3_000.0

    func filter(_ input: [Point]) -> [Point] {
        guard input.count > 2 else { return input }

        var output: [Point] = []
        output.reserveCapacity(input.count)
        output.append(input[0])

        for i in 1..<input.count {
            let previous = output[output.count - 1]
            let current = input[i]
            let delta = current.position - previous.position
            let distance = simd_length(delta)

            // Keep meaningful movement. This removes duplicate/high-frequency
            // samples that otherwise become tiny visible kinks.
            if distance < minimumDistance && i < input.count - 1 {
                continue
            }

            let dt = max(Float(current.timestamp - previous.timestamp), 1.0 / 240.0)
            let speed = distance / dt
            let speedRatio = min(max(speed / fastSpeed, 0), 1)

            // Faster strokes receive slightly more smoothing. Sharp turns are
            // detected from neighboring direction and retain more of the raw
            // point so mathematical symbols do not become overly rounded.
            var smoothing = slowSmoothing + (fastSmoothing - slowSmoothing) * speedRatio

            if i + 1 < input.count {
                let nextDelta = input[i + 1].position - current.position
                let nextLength = simd_length(nextDelta)
                if distance > 0.001 && nextLength > 0.001 {
                    let cosine = simd_dot(delta / distance, nextDelta / nextLength)
                    if cosine < 0.55 {
                        smoothing *= 0.45
                    }
                }
            }

            var adjusted = current
            adjusted.position = previous.position * smoothing + current.position * (1.0 - smoothing)
            // Pressure deliberately remains untouched.
            output.append(adjusted)
        }

        if let last = input.last, output.last?.timestamp != last.timestamp {
            output.append(last)
        }
        return output
    }
}
