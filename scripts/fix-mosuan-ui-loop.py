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

if "private var loadedPageState: CanvasPageState?" not in s:
    raise SystemExit("loadedPageState guard was not inserted")
if "if loadedPageState == state { return }" not in s:
    raise SystemExit("loadPageState guard was not inserted")
if "Page state pushed by SwiftUI is input only" not in s:
    raise SystemExit("page-load isolation marker missing")

path.write_text(s)

# The old MetalInkCanvas used pageID: UUID = UUID(). BoardScreenV3 does not supply
# pageID, so SwiftUI recreated a new UUID on every render. That made updateNSView
# treat every ordinary @Published update as a page switch and repeatedly reload Metal.
ui_path = Path("Sources/MosuanBoard/UIComponents.swift")
u = ui_path.read_text()

u = u.replace("    var pageID: UUID = UUID()\n", "    var pageID: UUID? = nil\n", 1)

old_update = '''    func updateNSView(_ view: InkMetalView, context: Context) {
        configure(view)
        view.onPageStateChanged = onPageStateChanged
        view.onZoomChanged = { value in zoomPercent = value }
        if context.coordinator.loadedPageID != pageID { context.coordinator.loadedPageID = pageID; view.loadPageState(pageState) }
        controller.attach(view)
    }
'''
new_update = '''    func updateNSView(_ view: InkMetalView, context: Context) {
        configure(view)
        view.onPageStateChanged = onPageStateChanged
        view.onZoomChanged = { value in zoomPercent = value }
        // pageState is the actual content source of truth. Do not use a default
        // UUID as view identity: UUID() changes whenever SwiftUI recreates this
        // value-type representable, which can reset the Metal view continuously.
        view.loadPageState(pageState)
        controller.attach(view)
    }
'''
if old_update not in u:
    raise SystemExit("MetalInkCanvas updateNSView block not found")
u = u.replace(old_update, new_update, 1)

old_coord = "    final class Coordinator { var loadedPageID: UUID? }\n"
new_coord = "    final class Coordinator { }\n"
if old_coord not in u:
    raise SystemExit("MetalInkCanvas coordinator block not found")
u = u.replace(old_coord, new_coord, 1)

ui_path.write_text(u)
print("Applied page-state isolation and stable Metal canvas identity")
