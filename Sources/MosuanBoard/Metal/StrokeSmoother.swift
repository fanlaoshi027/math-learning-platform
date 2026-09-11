import Foundation

/// Lightweight stroke preprocessing inspired by the smoothing approach used by
/// mature Metal drawing engines. It deliberately stays platform-independent so
/// the renderer can keep ownership of Metal and canvas state.
enum StrokeSmoother {
    struct Configuration {
        var enabled: Bool = true
        var strength: Float = 0.65
        var cornerAngleDegrees: Float = 55
        var minimumPointDistance: Float = 0.8
    }

    static func smooth(_ input: [InkPoint], configuration: Configuration = Configuration()) -> [InkPoint] {
        guard configuration.enabled, input.count >= 3 else { return input }

        let points = deduplicated(input, minimumDistance: configuration.minimumPointDistance)
        guard points.count >= 3 else { return points }

        var output: [InkPoint] = []
        output.reserveCapacity(points.count * 2)
        output.append(points[0])

        let strength = max(0, min(1, configuration.strength))
        let cornerCosine = cos(configuration.cornerAngleDegrees * .pi / 180)

        for i in 1..<(points.count - 1) {
            let previous = points[i - 1]
            let current = points[i]
            let next = points[i + 1]

            let inVector = SIMD2(current.x - previous.x, current.y - previous.y)
            let outVector = SIMD2(next.x - current.x, next.y - current.y)
            let inLength = simdLength(inVector)
            let outLength = simdLength(outVector)

            guard inLength > 0.001, outLength > 0.001 else {
                output.append(current)
                continue
            }

            let cosine = simdDot(inVector / inLength, outVector / outLength)
            if cosine < cornerCosine {
                // Preserve mathematical handwriting corners instead of rounding
                // them away (e.g. a "7", angle, triangle vertex, etc.).
                output.append(current)
                continue
            }

            let midpoint = SIMD2(
                (previous.x + next.x) * 0.5,
                (previous.y + next.y) * 0.5
            )
            let target = SIMD2(current.x, current.y) * Float(1 - strength) + midpoint * strength
            let pressure = current.pressure

            output.append(InkPoint(x: target.x, y: target.y, pressure: pressure))
            output.append(current)
        }

        output.append(points[points.count - 1])
        return output
    }

    private static func deduplicated(_ input: [InkPoint], minimumDistance: Float) -> [InkPoint] {
        guard let first = input.first else { return [] }
        let thresholdSquared = minimumDistance * minimumDistance
        var output: [InkPoint] = [first]
        output.reserveCapacity(input.count)

        for point in input.dropFirst() {
            guard let last = output.last else { continue }
            let dx = point.x - last.x
            let dy = point.y - last.y
            if dx * dx + dy * dy >= thresholdSquared {
                output.append(point)
            } else {
                // Keep the latest pressure sample while avoiding a zero-length
                // geometry segment.
                output[output.count - 1] = InkPoint(x: last.x, y: last.y, pressure: point.pressure)
            }
        }
        return output
    }
}

private func simdLength(_ value: SIMD2<Float>) -> Float {
    sqrt(value.x * value.x + value.y * value.y)
}

private func simdDot(_ lhs: SIMD2<Float>, _ rhs: SIMD2<Float>) -> Float {
    lhs.x * rhs.x + lhs.y * rhs.y
}
