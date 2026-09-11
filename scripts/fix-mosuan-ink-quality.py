from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
renderer = ROOT / "Sources/MosuanBoard/Metal/InkRenderer.swift"
view = ROOT / "Sources/MosuanBoard/Metal/InkMetalView.swift"

r = renderer.read_text()
start = r.index("    private func appendStroke(")
end = r.index("    private func appendSelection(", start)
new_stroke = r'''    private func appendStroke(_ s: [InkPoint], style: PenStyle, to out: inout [InkVertex]) {
        guard !s.isEmpty else { return }
        let color = metalColor(style)
        guard s.count > 1 else {
            let p = s[0]
            disk(viewPoint(from: SIMD2(p.x, p.y)), strokeWidth(p.pressure, style), color, to: &out)
            return
        }

        // Handwriting is rendered from a smoothed centerline rather than directly
        // connecting every sampled mouse/tablet point. This removes visible
        // polygonal/jagged edges while preserving pressure changes.
        var smooth: [InkPoint] = []
        smooth.reserveCapacity(s.count * 3)
        func append(_ p: InkPoint) {
            if let last = smooth.last {
                let d = hypot(p.x - last.x, p.y - last.y)
                if d < 0.7 { return }
            }
            smooth.append(p)
        }

        append(s[0])
        if s.count == 2 {
            append(s[1])
        } else {
            for i in 0..<(s.count - 1) {
                let p0 = s[max(0, i - 1)]
                let p1 = s[i]
                let p2 = s[i + 1]
                let p3 = s[min(s.count - 1, i + 2)]
                let distance = hypot(p2.x - p1.x, p2.y - p1.y)
                let samples = max(2, min(8, Int(ceil(distance / 5.0))))
                if i == 0 { append(p1) }
                if samples > 1 {
                    for step in 1...samples {
                        let t = Float(step) / Float(samples)
                        let t2 = t * t
                        let t3 = t2 * t
                        let x = 0.5 * ((2 * p1.x) + (-p0.x + p2.x) * t + (2 * p0.x - 5 * p1.x + 4 * p2.x - p3.x) * t2 + (-p0.x + 3 * p1.x - 3 * p2.x + p3.x) * t3)
                        let y = 0.5 * ((2 * p1.y) + (-p0.y + p2.y) * t + (2 * p0.y - 5 * p1.y + 4 * p2.y - p3.y) * t2 + (-p0.y + 3 * p1.y - 3 * p2.y + p3.y) * t3)
                        let pressure = p1.pressure + (p2.pressure - p1.pressure) * t
                        append(InkPoint(x: x, y: y, pressure: pressure))
                    }
                }
            }
        }

        for i in 0..<(smooth.count - 1) {
            let p = smooth[i]
            let q = smooth[i + 1]
            let dx = q.x - p.x
            let dy = q.y - p.y
            let length = max(sqrt(dx * dx + dy * dy), 0.001)
            let nx = -dy / length
            let ny = dx / length
            let w0 = strokeWidth(p.pressure, style)
            let w1 = strokeWidth(q.pressure, style)
            let a = viewPoint(from: SIMD2(p.x + nx * w0, p.y + ny * w0))
            let b = viewPoint(from: SIMD2(p.x - nx * w0, p.y - ny * w0))
            let c = viewPoint(from: SIMD2(q.x + nx * w1, q.y + ny * w1))
            let d = viewPoint(from: SIMD2(q.x - nx * w1, q.y - ny * w1))
            triangle(a, b, c, color: color, to: &out)
            triangle(c, b, d, color: color, to: &out)
        }

        // Round joins/caps make handwriting look like ink instead of a chain of quads.
        for p in smooth {
            disk(viewPoint(from: SIMD2(p.x, p.y)), strokeWidth(p.pressure, style), color, to: &out)
        }
    }
'''
r = r[:start] + new_stroke + r[end:]
r = r.replace('descriptor.colorAttachments[0].pixelFormat = .bgra8Unorm\n', 'descriptor.colorAttachments[0].pixelFormat = .bgra8Unorm\n        descriptor.rasterSampleCount = 4\n', 1)
renderer.write_text(r)

v = view.read_text()
v = v.replace('colorPixelFormat = .bgra8Unorm\n', 'colorPixelFormat = .bgra8Unorm\n        sampleCount = 4\n', 1)

# Smart-line recognition should work both while the pen pauses and when the user
# releases immediately after drawing. Use a deliberately forgiving tolerance so
# a hand-drawn "almost straight" stroke is still converted into a line.
v = v.replace(
    'if isLineTool || (isSmartLineTool && smartLineDetected) { let line = linePreview(from: points);',
    'let smartLine = isSmartLineTool && (smartLineDetected || (points.count >= 3 && LineGeometry.isLikelyStraight(points: points, tolerance: 18, minimumLength: 20)))\n        if isLineTool || smartLine { let line = linePreview(from: points);',
    1
)
v = v.replace(
    'tolerance: 8, minimumLength: 30',
    'tolerance: 18, minimumLength: 20',
    1
)
view.write_text(v)
print("Applied smoothed pressure-sensitive ink, 4x MSAA, and forgiving smart-line pause/commit detection.")
