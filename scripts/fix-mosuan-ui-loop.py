from pathlib import Path

path = Path("Sources/MosuanBoard/Metal/InkMetalView.swift")
s = path.read_text()

marker = "    private var polygonModel = PolygonToolModel()\n"
if "private var loadedPageState: CanvasPageState?" not in s:
    if marker not in s:
        raise SystemExit("InkMetalView marker not found")
    s = s.replace(marker, marker + "    // Prevent SwiftUI <-> MTKView update feedback from re-importing the same page.\n    private var loadedPageState: CanvasPageState?\n", 1)

old = '''    func loadPageState(_ state: CanvasPageState) {\n        polygonModel.cancel()\n        polygonVertexDrag = nil\n        dynamicAngleDragID = nil\n        dynamicIsoscelesTriangleDragID = nil\n        dynamicTrianglePlaybackHistoryActive = false\n        dynamicTriangleParameterHistoryActive = false\n        lassoActive = false\n        lassoPoints.removeAll(keepingCapacity: true)\n        lassoOverlay.update(points: [], visible: false)\n        renderer.importPageState(state)\n        onHistoryChanged?()\n        onSelectionChanged?()\n        draw()\n    }\n'''

new = '''    func loadPageState(_ state: CanvasPageState) {\n        // updateNSView can be called many times for unrelated SwiftUI state changes.\n        // Never re-import or redraw when the page content is already loaded: that\n        // creates a SwiftUI -> MTKView -> Published -> SwiftUI feedback loop and can\n        // block CAMetalLayer.nextDrawable on the main thread.\n        if loadedPageState == state { return }\n\n        polygonModel.cancel()\n        polygonVertexDrag = nil\n        dynamicAngleDragID = nil\n        dynamicIsoscelesTriangleDragID = nil\n        dynamicTrianglePlaybackHistoryActive = false\n        dynamicTriangleParameterHistoryActive = false\n        lassoActive = false\n        lassoPoints.removeAll(keepingCapacity: true)\n        lassoOverlay.update(points: [], visible: false)\n        renderer.importPageState(state)\n        loadedPageState = state\n        onHistoryChanged?()\n        onSelectionChanged?()\n        draw()\n    }\n'''

if old not in s:
    raise SystemExit("loadPageState block not found")
s = s.replace(old, new, 1)

old_notify = "    private func notifyState() { onHistoryChanged?(); onSelectionChanged?(); onPageStateChanged?(renderer.exportPageState()) }\n"
new_notify = "    private func notifyState() { let state = renderer.exportPageState(); loadedPageState = state; onHistoryChanged?(); onSelectionChanged?(); onPageStateChanged?(state) }\n"
if old_notify in s:
    s = s.replace(old_notify, new_notify, 1)

path.write_text(s)
