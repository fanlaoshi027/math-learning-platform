from pathlib import Path

path = Path("Sources/MosuanBoard/Metal/InkMetalView.swift")
s = path.read_text()

marker = "    private var polygonModel = PolygonToolModel()\n"
if "private var loadedPageState: CanvasPageState?" not in s:
    if marker not in s:
        raise SystemExit("InkMetalView marker not found")
    s = s.replace(marker, marker + "    // Page state pushed by SwiftUI is input only. Never echo it back during updateNSView.\n    private var loadedPageState: CanvasPageState?\n", 1)

old = '''    func loadPageState(_ state: CanvasPageState) {
        polygonModel.cancel()
        polygonVertexDrag = nil
        dynamicAngleDragID = nil
        dynamicIsoscelesTriangleDragID = nil
        dynamicTrianglePlaybackHistoryActive = false
        dynamicTriangleParameterHistoryActive = false
        lassoActive = false
        lassoPoints.removeAll(keepingCapacity: true)
        lassoOverlay.update(points: [], visible: false)
        renderer.importPageState(state)
        onHistoryChanged?()
        onSelectionChanged?()
        draw()
    }
'''

new = '''    func loadPageState(_ state: CanvasPageState) {
        // SwiftUI may call updateNSView for unrelated @Published changes.
        // Loading a page is a one-way synchronization operation. Do not notify SwiftUI
        // from here, otherwise updateNSView -> Published -> updateNSView can repeat forever.
        if loadedPageState == state { return }

        polygonModel.cancel()
        polygonVertexDrag = nil
        dynamicAngleDragID = nil
        dynamicIsoscelesTriangleDragID = nil
        dynamicTrianglePlaybackHistoryActive = false
        dynamicTriangleParameterHistoryActive = false
        lassoActive = false
        lassoPoints.removeAll(keepingCapacity: true)
        lassoOverlay.update(points: [], visible: false)
        renderer.importPageState(state)
        loadedPageState = state
        draw()
    }
'''

if old not in s:
    raise SystemExit("loadPageState block not found")
s = s.replace(old, new, 1)

old_notify = "    private func notifyState() { onHistoryChanged?(); onSelectionChanged?(); onPageStateChanged?(renderer.exportPageState()) }\n"
new_notify = "    private func notifyState() { let state = renderer.exportPageState(); loadedPageState = state; onHistoryChanged?(); onSelectionChanged?(); onPageStateChanged?(state) }\n"
if old_notify in s:
    s = s.replace(old_notify, new_notify, 1)

# The build must fail if the protective source transformation did not actually apply.
if "private var loadedPageState: CanvasPageState?" not in s:
    raise SystemExit("loadedPageState guard was not inserted")
if "if loadedPageState == state { return }" not in s:
    raise SystemExit("loadPageState guard was not inserted")
if "Page state pushed by SwiftUI is input only" not in s:
    raise SystemExit("page-load isolation marker missing")

path.write_text(s)
print("Applied Mosuan page-load isolation guard")
