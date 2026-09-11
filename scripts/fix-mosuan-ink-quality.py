from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
renderer = ROOT / "Sources/MosuanBoard/Metal/InkRenderer.swift"
view = ROOT / "Sources/MosuanBoard/Metal/InkMetalView.swift"

r = renderer.read_text()

# Repair the legacy one-line appendBackground declaration: it was missing
# the function-closing brace, which makes every following private method local.
r = r.replace(
    'y+=step}}\n    private func appendStroke',
    'y+=step}}\n    }\n    private func appendStroke',
    1
)

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

        // Light smoothing: preserve the original centerline shape while removing
        // small sampling jitter. This is intentionally not vector-like smoothing.
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

        // Round joins/caps retain a natural pen feel.
        for p in smooth {
            disk(viewPoint(from: SIMD2(p.x, p.y)), strokeWidth(p.pressure, style), color, to: &out)
        }
    }
'''
r = r[:start] + new_stroke + r[end:]

# Make color SIMD literals explicitly Float and normalize Swift lexer-sensitive math.
r = r.replace('let color=SIMD4(0.82,0.84,0.88,0.55)', 'let color=SIMD4<Float>(0.82,0.84,0.88,0.55)', 1)
r = r.replace('let c=SIMD4(0.1,0.45,1,0.75)', 'let c=SIMD4<Float>(0.1,0.45,1,0.75)', 1)
r = r.replace('let c=SIMD4(0.1,0.45,1,0.85)', 'let c=SIMD4<Float>(0.1,0.45,1,0.85)', 1)
r = r.replace('SIMD4(0.95,0.55,0.05,1)', 'SIMD4<Float>(0.95,0.55,0.05,1)')
r = r.replace('180.0/.pi', '180.0 / .pi')
r = r.replace('2*.pi', '2 * .pi')

r = r.replace(
    'descriptor.colorAttachments[0].pixelFormat = .bgra8Unorm\n',
    'descriptor.colorAttachments[0].pixelFormat = .bgra8Unorm\n        descriptor.rasterSampleCount = 4\n',
    1
)
renderer.write_text(r)

v = view.read_text()
v = v.replace('colorPixelFormat = .bgra8Unorm\n', 'colorPixelFormat = .bgra8Unorm\n        sampleCount = 4\n', 1)
v = v.replace(
    'if isLineTool || (isSmartLineTool && smartLineDetected) { let line = linePreview(from: points);',
    'let smartLine = isSmartLineTool && (smartLineDetected || (points.count >= 3 && LineGeometry.isLikelyStraight(points: points, tolerance: 18, minimumLength: 20)))\n        if isLineTool || smartLine { let line = linePreview(from: points);',
    1
)
v = v.replace('tolerance: 8, minimumLength: 30', 'tolerance: 18, minimumLength: 20', 1)
view.write_text(v)
print("Applied compile-safe ink smoothing, 4x MSAA, and forgiving smart-line pause/commit detection.")
