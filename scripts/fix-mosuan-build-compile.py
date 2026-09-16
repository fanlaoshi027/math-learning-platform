from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def find_braced_block(text: str, start: int):
    """Return (opening_brace, closing_brace) for the brace at/after start."""
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
for signature in [
    "    func replacePageState(_ state: CanvasPageState)",
    "    func clearPageContents()",
    "    func canvasPoint(from point: SIMD2<Float>)",
]:
    v = keep_first_function(v, signature)

# ToolFeatures needs the coordinate conversion, but renderer remains private.
if "    func canvasPoint(from point: SIMD2<Float>)" not in v:
    marker = "    private func notifyState()"
    wrapper = "    func canvasPoint(from point: SIMD2<Float>) -> SIMD2<Float> { renderer.canvasPoint(from: point) }\n"
    if marker not in v:
        raise SystemExit("InkMetalView notifyState marker not found")
    v = v.replace(marker, wrapper + marker, 1)
view_path.write_text(v)

renderer_path = ROOT / "Sources/MosuanBoard/Metal/InkRenderer.swift"
r = renderer_path.read_text()
for signature in [
    "    func replacePageState(_ state: CanvasPageState)",
    "    func clearPage()",
]:
    r = keep_first_function(r, signature)
renderer_path.write_text(r)

# Swift 5.10 can time out while type-checking the very large BoardScreen body.
# Move the GeometryReader's large ZStack into a separate computed ViewBuilder.
# This transformation is deliberately brace-aware and preserves the original
# body contents byte-for-byte inside boardWorkspace, avoiding the fragile
# string slicing used by the earlier failed repair.
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
    original_block = b[start:closing + 1]
    inner = b[opening + 1:closing]

    # The original body has the form:
    #   GeometryReader { proxy in ... }
    #   .frame(...)
    #   .preferredColorScheme(...)
    # Keep those outer modifiers in body, while extracting only the large
    # GeometryReader expression into boardWorkspace.
    geom_start = inner.find("GeometryReader {")
    if geom_start < 0:
        raise SystemExit("GeometryReader not found in BoardScreen body")

    # Find the GeometryReader closure's matching brace.
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

    # Preserve the outer body modifiers exactly, but make GeometryReader call
    # the extracted view. This creates a real compiler boundary.
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

print("Normalized injected helpers and safely split BoardScreen for Swift type-checking.")
