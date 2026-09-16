from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
renderer = ROOT / "Sources/MosuanBoard/Metal/InkRenderer.swift"
view = ROOT / "Sources/MosuanBoard/Metal/InkMetalView.swift"

r = renderer.read_text()

# Add history-preserving page replacement/clear operations.
if "func replacePageState(_ state: CanvasPageState)" not in r:
    marker = "    func mtkView("
    insert = '''    func replacePageState(_ state: CanvasPageState) {
        recordMutation()
        committedStrokes = state.strokes.map { StoredStroke(id: $0.id, points: $0.points, style: $0.style, rotation: $0.rotation) }
        objectStore.importObjects(state.objects)
        activeStroke = []
        selectedStrokeIndices = []
        selectedObjectIDs = []
        customRotationCenter = nil
        rebuildGeometry()
    }

    func clearPage() {
        guard !committedStrokes.isEmpty || !objectStore.objects.isEmpty else { return }
        replacePageState(CanvasPageState())
    }

'''
    r = r.replace(marker, insert + marker, 1)

# Global dashed rendering also applies to freehand strokes. The normal solid path
# remains the high-quality Noteful-tuned renderer; dashed mode uses a uniform dash
# geometry while preserving pressure-derived width inside each dash.
needle = "        let color = metalColor(style)\n        guard s.count > 1 else {"
if "appendDashedStroke(s, style: style, to: &out)" not in r:
    replacement = "        let color = metalColor(style)\n        if style.lineStyle != .solid {\n            appendDashedStroke(s, style: style, to: &out)\n            return\n        }\n        guard s.count > 1 else {"
    if needle not in r:
        raise SystemExit("appendStroke insertion point not found")
    r = r.replace(needle, replacement, 1)

if "private func appendDashedStroke(" not in r:
    marker = "    private func appendSelection("
    helper = '''    private func appendDashedStroke(_ s: [InkPoint], style: PenStyle, to out: inout [InkVertex]) {
        guard s.count > 1 else {
            if let p = s.first { disk(viewPoint(from: SIMD2(p.x, p.y)), strokeWidth(p.pressure, style) * 0.82, metalColor(style), to: &out) }
            return
        }
        let color = metalColor(style)
        let dashLength: Float = 14
        let gapLength: Float = 8
        var phase: Float = 0
        var drawing = true
        for pair in zip(s, s.dropFirst()) {
            let a = SIMD2(pair.0.x, pair.0.y)
            let b = SIMD2(pair.1.x, pair.1.y)
            let segment = b - a
            let length = simd_length(segment)
            guard length > 0.001 else { continue }
            let dir = segment / length
            var consumed: Float = 0
            while consumed < length {
                let limit = drawing ? dashLength : gapLength
                let remaining = limit - phase
                let step = min(remaining, length - consumed)
                let t0 = consumed / length
                let t1 = (consumed + step) / length
                if drawing {
                    let p0 = a + segment * t0
                    let p1 = a + segment * t1
                    let pressure = (pair.0.pressure + pair.1.pressure) * 0.5
                    let width = strokeWidth(pressure, style)
                    appendLine(viewPoint(from: p0), viewPoint(from: p1), width: width, color: color, to: &out)
                    disk(viewPoint(from: p0), width * 0.92, color, to: &out)
                    disk(viewPoint(from: p1), width * 0.92, color, to: &out)
                }
                consumed += step
                phase += step
                if phase >= limit - 0.0001 {
                    phase = 0
                    drawing.toggle()
                }
            }
        }
    }

'''
    if marker not in r:
        raise SystemExit("appendSelection marker not found")
    r = r.replace(marker, helper + marker, 1)

renderer.write_text(r)

v = view.read_text()

if "func replacePageState(_ state: CanvasPageState)" not in v:
    marker = "    func setSelectedRotationDegrees"
    insert = '''    func replacePageState(_ state: CanvasPageState) {
        renderer.replacePageState(state)
        notifyState()
        draw()
    }
    func clearPageContents() {
        renderer.clearPage()
        notifyState()
        draw()
    }
'''
    v = v.replace(marker, insert + marker, 1)

old = "    private func eraseAlongPath(_ path: [SIMD2<Float>]) { guard !path.isEmpty else { return }; var deleted = renderer.eraseObjectsByScribble(path, tolerance: 14); for p in path { if renderer.selectStroke(at: p, tolerance: 16) { renderer.deleteSelected(); deleted = true } }; if deleted { draw() } }"
new = "    private func eraseAlongPath(_ path: [SIMD2<Float>]) { guard !path.isEmpty else { return }; if UserDefaults.standard.string(forKey: \"mosuan.eraserMode\") == EraserMode.partial.rawValue { erasePartialAlongPath(path); return }; var deleted = renderer.eraseObjectsByScribble(path, tolerance: 14); for p in path { if renderer.selectStroke(at: p, tolerance: 16) { renderer.deleteSelected(); deleted = true } }; if deleted { draw() } }"
if old not in v:
    raise SystemExit("eraseAlongPath target not found")
v = v.replace(old, new, 1)
view.write_text(v)

print("Applied clear-page, history-preserving page replacement, and global dashed freehand rendering.")
