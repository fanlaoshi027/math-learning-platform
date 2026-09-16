from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def find_braced_block(text: str, start: int):
    opening = text.find("{", start)
    if opening < 0:
        return None
    depth = 0
    for i in range(opening, len(text)):
        if text[i] == "{":
            depth += 1
        elif text[i] == "}":
            depth -= 1
            if depth == 0:
                return opening, i
    return None


def find_function_block(text: str, signature: str, start_at: int = 0):
    start = text.find(signature, start_at)
    if start < 0:
        return None
    pair = find_braced_block(text, start)
    if pair is None:
        return None
    return start, pair[1] + 1


def keep_first_function(text: str, signature: str):
    first = find_function_block(text, signature)
    if first is None:
        return text
    cursor = first[1]
    while True:
        nxt = find_function_block(text, signature, cursor)
        if nxt is None:
            break
        text = text[:nxt[0]] + text[nxt[1]:]
        cursor = nxt[0]
    return text


# Normalize helper methods so repeated workflow runs cannot create duplicate
# declarations in the generated workspace.
view_path = ROOT / "Sources/MosuanBoard/Metal/InkMetalView.swift"
v = view_path.read_text()

# These bridge methods are intentionally kept on InkMetalView (not the feature
# extension) because renderer is a private stored property of the view.
bridge_methods = """    func replacePageState(_ state: CanvasPageState) { renderer.importPageState(state); notifyState(); draw() }
    func clearPageContents() { renderer.beginHistoryTransaction(); renderer.importPageState(CanvasPageState(strokes: [], objects: [])); renderer.endHistoryTransaction(); notifyState(); draw() }
    func canvasPoint(from point: SIMD2<Float>) -> SIMD2<Float> { renderer.canvasPoint(from: point) }
"""

for signature in [
    "    func replacePageState(_ state: CanvasPageState)",
    "    func clearPageContents()",
    "    func canvasPoint(from point: SIMD2<Float>)",
]:
    v = keep_first_function(v, signature)

if "    func replacePageState(_ state: CanvasPageState)" not in v:
    marker = "    private func notifyState()"
    if marker not in v:
        raise SystemExit("InkMetalView notifyState marker not found")
    v = v.replace(marker, bridge_methods + marker, 1)
view_path.write_text(v)

# Remove any accidental duplicate renderer declarations, but do not rewrite
# renderer implementation or its drawing algorithms.
renderer_path = ROOT / "Sources/MosuanBoard/Metal/InkRenderer.swift"
r = renderer_path.read_text()
for signature in [
    "    func replacePageState(_ state: CanvasPageState)",
    "    func clearPage()",
]:
    r = keep_first_function(r, signature)
renderer_path.write_text(r)

# Swift 5.10 can time out while type-checking the very large BoardScreen body.
# Move the GeometryReader's large expression into a separate ViewBuilder.
board_path = ROOT / "Sources/MosuanBoard/BoardScreenV3.swift"
b = board_path.read_text()

if "private func boardWorkspace(proxy: GeometryProxy) -> some View" not in b:
    marker = "    var body: some View {"
    start = b.find(marker)
    if start < 0:
        raise SystemExit("BoardScreen body marker not found")
    pair = find_braced_block(b, start)
    if pair is None:
        raise SystemExit("BoardScreen body braces not found")
    opening, closing = pair
    inner = b[opening + 1:closing]

    geom_start = inner.find("GeometryReader {")
    if geom_start < 0:
        raise SystemExit("GeometryReader not found in BoardScreen body")
    geom_open = inner.find("{", geom_start)
    geom_depth = 0
    geom_close = None
    for i in range(geom_open, len(inner)):
        if inner[i] == "{":
            geom_depth += 1
        elif inner[i] == "}":
            geom_depth -= 1
            if geom_depth == 0:
                geom_close = i
                break
    if geom_close is None:
        raise SystemExit("GeometryReader closure not closed")

    geometry_expression = inner[geom_start:geom_close + 1]
    suffix = inner[geom_close + 1:]
    suffix_stripped = suffix.strip()

    body_replacement = (
        "    var body: some View {\n"
        "        GeometryReader { proxy in\n"
        "            boardWorkspace(proxy: proxy)\n"
        "        }"
        + ("\n" + suffix_stripped if suffix_stripped else "")
        + "\n    }\n\n"
        "    @ViewBuilder\n"
        "    private func boardWorkspace(proxy: GeometryProxy) -> some View {\n"
        + geometry_expression
        + "\n    }"
    )
    b = b[:start] + body_replacement + b[closing + 1:]
    board_path.write_text(b)

print("Restored InkMetalView state bridges and kept BoardScreen transformation brace-safe.")
