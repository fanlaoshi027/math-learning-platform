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

        // Light-weight handwriting smoothing. Keep the original samples as the
        // centerline, but replace each interior point with a short weighted average.
        // This removes jitter without making mathematical writing look like a vector curve.
        var smooth = s
        if s.count >= 3 {
            for i in 1..<(s.count - 1) {
                let p0 = s[i - 1]
                let p1 = s[i]
                let p2 = s[i + 1]
                smooth[i] = InkPoint(
                    x: (p0.x + 2.0 * p1.x + p2.x) * 0.25,
                    y: (p0.y + 2.0 * p1.y + p2.y) * 0.25,
                    pressure: (p0.pressure + 2.0 * p1.pressure + p2.pressure) * 0.25
                )
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

        // Round joins and caps retain a natural pen feel.
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
# an "almost straight" hand-drawn stroke is still converted into a line.
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
print("Applied compile-safe ink smoothing, 4x MSAA, and forgiving smart-line pause/commit detection.")
