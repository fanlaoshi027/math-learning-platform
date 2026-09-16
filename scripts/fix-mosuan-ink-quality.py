from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
renderer = ROOT / "Sources/MosuanBoard/Metal/InkRenderer.swift"
view = ROOT / "Sources/MosuanBoard/Metal/InkMetalView.swift"

r = renderer.read_text()

# Repair the known legacy missing brace without changing the golden-base source permanently.
bg_start = r.index("    private func appendBackground(")
stroke_start = r.index("    private func appendStroke(", bg_start)
bg_block = r[bg_start:stroke_start]
if bg_block.count("{") > bg_block.count("}"):
    r = r[:stroke_start] + "    }\n" + r[stroke_start:]

start = r.index("    private func appendStroke(")
end = r.index("    private func appendSelection(", start)
new_stroke = r'''    private func appendStroke(_ s: [InkPoint], style: PenStyle, to out: inout [InkVertex]) {
        guard !s.isEmpty else { return }
        let color = metalColor(style)
        guard s.count > 1 else {
            let p = s[0]
            // A short entry cap avoids the visible pressure-heavy dot while the
            // first real segment has not arrived yet.
            disk(viewPoint(from: SIMD2(p.x, p.y)), strokeWidth(p.pressure, style) * 0.72, color, to: &out)
            return
        }

        // Smooth pressure first. The first/last samples are deliberately kept
        // closer to the normal pen width so a hard tablet touchdown does not
        // create a bulb at the beginning of a stroke.
        var smoothPressure = s.map { $0.pressure }
        if smoothPressure.count >= 3 {
            for i in 1..<(smoothPressure.count - 1) {
                smoothPressure[i] = (smoothPressure[i - 1] + 2.0 * smoothPressure[i] + smoothPressure[i + 1]) * 0.25
            }
        }
        if smoothPressure.count >= 2 {
            smoothPressure[0] = min(smoothPressure[0], smoothPressure[1] * 0.85 + 0.15)
            smoothPressure[smoothPressure.count - 1] = min(smoothPressure[smoothPressure.count - 1], smoothPressure[smoothPressure.count - 2] * 0.85 + 0.15)
        }

        // Catmull-Rom interpolation fills the large gaps produced by fast mouse/
        // tablet events. Eight samples per source segment keeps arcs continuous
        // without turning the stroke into a visibly delayed vector curve.
        var curve: [(SIMD2<Float>, Float)] = []
        let count = s.count
        let subdivisions = 8
        curve.reserveCapacity((count - 1) * subdivisions + 1)
        func sample(_ i: Int, _ t: Float) -> (SIMD2<Float>, Float) {
            let i0 = max(0, i - 1), i1 = i, i2 = min(count - 1, i + 1), i3 = min(count - 1, i + 2)
            let p0 = SIMD2(s[i0].x, s[i0].y), p1 = SIMD2(s[i1].x, s[i1].y)
            let p2 = SIMD2(s[i2].x, s[i2].y), p3 = SIMD2(s[i3].x, s[i3].y)
            let t2 = t * t, t3 = t2 * t
            let position = 0.5 * ((2.0 * p1) + (-p0 + p2) * t + (2.0 * p0 - 5.0 * p1 + 4.0 * p2 - p3) * t2 + (-p0 + 3.0 * p1 - 3.0 * p2 + p3) * t3)
            let pressure = smoothPressure[i1] + (smoothPressure[i2] - smoothPressure[i1]) * t
            return (position, pressure)
        }
        for i in 0..<(count - 1) {
            for step in 0..<subdivisions { curve.append(sample(i, Float(step) / Float(subdivisions))) }
        }
        let last = s[count - 1]
        curve.append((SIMD2(last.x, last.y), smoothPressure[count - 1]))

        for i in 0..<(curve.count - 1) {
            let p = curve[i].0, q = curve[i + 1].0
            let dx = q.x - p.x, dy = q.y - p.y
            let length = max(sqrt(dx * dx + dy * dy), 0.001)
            let nx = -dy / length, ny = dx / length
            let w0 = strokeWidth(curve[i].1, style), w1 = strokeWidth(curve[i + 1].1, style)
            let a = viewPoint(from: SIMD2(p.x + nx * w0, p.y + ny * w0))
            let b = viewPoint(from: SIMD2(p.x - nx * w0, p.y - ny * w0))
            let c = viewPoint(from: SIMD2(q.x + nx * w1, q.y + ny * w1))
            let d = viewPoint(from: SIMD2(q.x - nx * w1, q.y - ny * w1))
            triangle(a, b, c, color: color, to: &out)
            triangle(c, b, d, color: color, to: &out)
        }

        // Only add round caps at the two ends. Adding a disk at every raw input
        // sample was the main source of the small beads seen on fast handwriting.
        let first = curve[0]
        let lastCurve = curve[curve.count - 1]
        disk(viewPoint(from: first.0), strokeWidth(first.1, style) * 0.82, color, to: &out)
        disk(viewPoint(from: lastCurve.0), strokeWidth(lastCurve.1, style) * 0.92, color, to: &out)
    }
'''
r = r[:start] + new_stroke + r[end:]

# Softer pressure response: pressure remains visible, but it no longer dominates
# the visual weight of a classroom pen stroke.
old_width = '    private func strokeWidth(_ pressure:Float,_ style:PenStyle)->Float{let p=max(0,min(1,pressure));let c=style.pressureEnabled ? pow(p,max(0.25,Float(style.pressureCurve))):0.75;return Float(style.width)*(0.45+0.75*c)}'
new_width = '    private func strokeWidth(_ pressure:Float,_ style:PenStyle)->Float{let p=max(0,min(1,pressure));let c=style.pressureEnabled ? pow(p,max(0.25,Float(style.pressureCurve))):0.5;return Float(style.width)*(0.70+0.45*c)}'
if old_width not in r:
    raise SystemExit("strokeWidth target not found")
r = r.replace(old_width, new_width, 1)

# Keep the previously validated compile-safe Metal syntax and 4x MSAA.
r = r.replace('SIMD4(0.82,0.84,0.88,0.55)', 'SIMD4<Float>(0.82,0.84,0.88,0.55)')
r = r.replace('SIMD4(0.1,0.45,1,0.75)', 'SIMD4<Float>(0.1,0.45,1,0.75)')
r = r.replace('SIMD4(0.1,0.45,1,0.85)', 'SIMD4<Float>(0.1,0.45,1,0.85)')
r = r.replace('SIMD4(0.1,0.45,1,0.9)', 'SIMD4<Float>(0.1,0.45,1,0.9)')
r = r.replace('SIMD4(0.95,0.55,0.05,1)', 'SIMD4<Float>(0.95,0.55,0.05,1)')
r = r.replace('180.0/.pi', '180.0 / .pi')
r = r.replace('2*.pi', '2 * .pi')
r = r.replace('sourceRadius*CGFloat(0.32)', 'sourceRadius * CGFloat(0.32)')
r = r.replace('delta*CGFloat(180.0 / .pi)', 'delta * CGFloat(180.0 / .pi)')
r = r.replace('nx=-dy/l', 'nx = -dy / l')
r = r.replace('ny=dx/l', 'ny = dx / l')
if 'descriptor.rasterSampleCount' not in r:
    r = r.replace('descriptor.colorAttachments[0].pixelFormat = .bgra8Unorm\n', 'descriptor.colorAttachments[0].pixelFormat = .bgra8Unorm\n        descriptor.rasterSampleCount = 4\n', 1)
renderer.write_text(r)

v = view.read_text()
if 'sampleCount = 4' not in v:
    v = v.replace('colorPixelFormat = .bgra8Unorm\n', 'colorPixelFormat = .bgra8Unorm\n        sampleCount = 4\n', 1)
v = v.replace(
    'if isLineTool || (isSmartLineTool && smartLineDetected) { let line = linePreview(from: points);',
    'let smartLine = isSmartLineTool && (smartLineDetected || (points.count >= 3 && LineGeometry.isLikelyStraight(points: points, tolerance: 18, minimumLength: 20)))\n        if isLineTool || smartLine { let line = linePreview(from: points);',
    1
)
v = v.replace('tolerance: 8, minimumLength: 30', 'tolerance: 18, minimumLength: 20', 1)
view.write_text(v)
print("Applied pressure-softened ink, anti-bulb entry caps, Catmull-Rom fast-stroke smoothing, 4x MSAA, and forgiving smart-line detection.")
