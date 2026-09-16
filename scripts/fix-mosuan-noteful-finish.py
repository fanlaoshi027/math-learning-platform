from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
renderer = ROOT / "Sources/MosuanBoard/Metal/InkRenderer.swift"
r = renderer.read_text()

start = r.index("    private func appendStroke(")
end = r.index("    private func appendSelection(", start)

new_stroke = r'''    private func appendStroke(_ s: [InkPoint], style: PenStyle, to out: inout [InkVertex]) {
        guard !s.isEmpty else { return }
        let color = metalColor(style)
        guard s.count > 1 else {
            let p = s[0]
            disk(viewPoint(from: SIMD2(p.x, p.y)), strokeWidth(p.pressure, style) * 0.62, color, to: &out)
            return
        }

        // Smooth pressure independently from the visual taper. This lets the
        // stroke become thin at the end even when the tablet pressure stays high.
        let rawPressure = s.map { max(0, min(1, $0.pressure)) }
        var smoothPressure = rawPressure
        if rawPressure.count >= 3 {
            for i in rawPressure.indices {
                var weighted: Float = 0
                var weight: Float = 0
                for d in -2...2 {
                    let j = i + d
                    guard j >= 0 && j < rawPressure.count else { continue }
                    let w: Float = d == 0 ? 4 : (abs(d) == 1 ? 2 : 1)
                    weighted += rawPressure[j] * w
                    weight += w
                }
                smoothPressure[i] = weighted / max(weight, 1)
            }
        }

        var curve: [(SIMD2<Float>, Float)] = []
        let count = s.count
        curve.reserveCapacity(count * 12)

        // Centripetal Catmull-Rom interpolation is more stable than the uniform
        // form when tablet samples are unevenly spaced. It reduces overshoot and
        // the small kinks/flat spots that can appear during fast curved writing.
        func sample(_ i: Int, _ t: Float) -> (SIMD2<Float>, Float) {
            let i0 = max(0, i - 1), i1 = i, i2 = min(count - 1, i + 1), i3 = min(count - 1, i + 2)
            let p0 = SIMD2(s[i0].x, s[i0].y), p1 = SIMD2(s[i1].x, s[i1].y)
            let p2 = SIMD2(s[i2].x, s[i2].y), p3 = SIMD2(s[i3].x, s[i3].y)

            func knot(_ a: SIMD2<Float>, _ b: SIMD2<Float>, _ previous: Float) -> Float {
                previous + pow(max(simd_distance(a, b), 0.0001), 0.5)
            }
            let t0: Float = 0
            let t1 = knot(p0, p1, t0)
            let t2 = knot(p1, p2, t1)
            let t3 = knot(p2, p3, t2)
            let u = t1 + (t2 - t1) * t

            let A1 = (t1 - u) / max(t1 - t0, 0.0001) * p0 + (u - t0) / max(t1 - t0, 0.0001) * p1
            let A2 = (t2 - u) / max(t2 - t1, 0.0001) * p1 + (u - t1) / max(t2 - t1, 0.0001) * p2
            let A3 = (t3 - u) / max(t3 - t2, 0.0001) * p2 + (u - t2) / max(t3 - t2, 0.0001) * p3
            let B1 = (t2 - u) / max(t2 - t0, 0.0001) * A1 + (u - t0) / max(t2 - t0, 0.0001) * A2
            let B2 = (t3 - u) / max(t3 - t1, 0.0001) * A2 + (u - t1) / max(t3 - t1, 0.0001) * A3
            let position = (t2 - u) / max(t2 - t1, 0.0001) * B1 + (u - t1) / max(t2 - t1, 0.0001) * B2
            let pressure = smoothPressure[i1] + (smoothPressure[i2] - smoothPressure[i1]) * t
            return (position, pressure)
        }

        for i in 0..<(count - 1) {
            let dx = s[i + 1].x - s[i].x
            let dy = s[i + 1].y - s[i].y
            let distance = sqrt(dx * dx + dy * dy)
            let subdivisions = max(8, min(48, Int(ceil(distance / 1.75))))
            for step in 0..<subdivisions {
                curve.append(sample(i, Float(step) / Float(subdivisions)))
            }
        }
        curve.append((SIMD2(s[count - 1].x, s[count - 1].y), smoothPressure[count - 1]))

        // Re-sample the interpolated curve at a nearly uniform arc-length spacing.
        // This prevents dense and sparse triangle strips from appearing as visible
        // changes of curvature when the pen moves quickly or the event rate varies.
        var uniformCurve: [(SIMD2<Float>, Float)] = []
        uniformCurve.reserveCapacity(curve.count)
        let targetSpacing: Float = 1.15
        uniformCurve.append(curve[0])
        var carryDistance: Float = 0
        var previous = curve[0]
        for current in curve.dropFirst() {
            var a = previous.0
            let b = current.0
            var segment = simd_distance(a, b)
            if segment < 0.0001 { previous = current; continue }
            while carryDistance + segment >= targetSpacing {
                let need = targetSpacing - carryDistance
                let f = need / segment
                a += (b - a) * f
                let pressure = previous.1 + (current.1 - previous.1) * f
                uniformCurve.append((a, pressure))
                segment -= need
                carryDistance = 0
            }
            carryDistance += segment
            previous = current
        }
        if simd_distance(uniformCurve.last?.0 ?? curve[0].0, curve.last!.0) > 0.01 {
            uniformCurve.append(curve.last!)
        }
        curve = uniformCurve

        var cumulative: [Float] = Array(repeating: 0, count: curve.count)
        if curve.count > 1 {
            for i in 1..<curve.count {
                cumulative[i] = cumulative[i - 1] + simd_distance(curve[i - 1].0, curve[i].0)
            }
        }
        let totalLength = max(cumulative.last ?? 1, 1)
        let capLength = min(28, max(10, Float(style.width) * 2.4))

        func visualWidth(_ index: Int) -> Float {
            let base = strokeWidth(curve[index].1, style)
            let fromStart = cumulative[index]
            let fromEnd = totalLength - cumulative[index]
            // Deliberate visual taper: it does not depend on pressure dropping.
            let startFactor = min(1, max(0.62, fromStart / capLength))
            let endFactor = min(1, max(0.62, fromEnd / capLength))
            return base * min(startFactor, endFactor)
        }

        func normal(at index: Int) -> SIMD2<Float> {
            let previous = curve[max(0, index - 1)].0
            let next = curve[min(curve.count - 1, index + 1)].0
            let tangent = next - previous
            let length = max(simd_length(tangent), 0.001)
            return SIMD2(-tangent.y, tangent.x) / length
        }

        // Vertex normals are computed from neighboring curve samples rather than
        // separately for each segment. This makes direction changes join cleanly
        // instead of producing the small open "notches" visible at turns.
        if curve.count > 1 {
            for i in 0..<(curve.count - 1) {
                let p = curve[i].0, q = curve[i + 1].0
                let n0 = normal(at: i), n1 = normal(at: i + 1)
                let w0 = visualWidth(i), w1 = visualWidth(i + 1)
                let a = viewPoint(from: p + n0 * w0)
                let b = viewPoint(from: p - n0 * w0)
                let c = viewPoint(from: q + n1 * w1)
                let d = viewPoint(from: q - n1 * w1)
                triangle(a, b, c, color: color, to: &out)
                triangle(c, b, d, color: color, to: &out)

                // Round only meaningful direction changes. This fills sharp joins
                // without putting a visible disk at every input sample.
                if i > 0 && i + 1 < curve.count {
                    let nPrev = normal(at: i - 1)
                    let nNext = normal(at: i + 1)
                    let turn = abs(asin(max(-1, min(1, simd_dot(nPrev, nNext)))))
                    if turn > 0.10 {
                        disk(viewPoint(from: p), max(w0, w1) * 0.98, color, to: &out)
                    }
                }
            }
        }

        let first = curve[0]
        let last = curve[curve.count - 1]
        disk(viewPoint(from: first.0), visualWidth(0) * 0.82, color, to: &out)
        disk(viewPoint(from: last.0), visualWidth(curve.count - 1) * 0.82, color, to: &out)
    }
'''
r = r[:start] + new_stroke + r[end:]

old = '    private func strokeWidth(_ pressure:Float,_ style:PenStyle)->Float{let p=max(0,min(1,pressure));let normalized=0.28+0.72*p;let c=style.pressureEnabled ? pow(normalized,max(0.55,Float(style.pressureCurve)*0.85)):0.5;return Float(style.width)*(0.74+0.36*c)}'
if old not in r:
    old = '    private func strokeWidth(_ pressure:Float,_ style:PenStyle)->Float{let p=max(0,min(1,pressure));let normalized=0.28+0.72*p;let c=style.pressureEnabled ? pow(normalized,max(0.55,Float(style.pressureCurve)*0.85)):0.5;return Float(style.width)*(0.70+0.46*c)}'
new = '    private func strokeWidth(_ pressure:Float,_ style:PenStyle)->Float{let p=max(0,min(1,pressure));let normalized=0.28+0.72*p;let c=style.pressureEnabled ? pow(normalized,max(0.55,Float(style.pressureCurve)*0.85)):0.5;return Float(style.width)*(0.70+0.46*c)}'
if old not in r:
    raise SystemExit("strokeWidth target not found")
r = r.replace(old, new, 1)
renderer.write_text(r)
print("Applied centripetal Catmull-Rom interpolation, uniform arc-length resampling, smooth join normals, and responsive pressure.")
