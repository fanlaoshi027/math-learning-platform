from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def find_function_block(text: str, signature: str, start_at: int = 0):
    start = text.find(signature, start_at)
    if start < 0:
        return None
    brace = text.find("{", start)
    if brace < 0:
        return None
    depth = 0
    for i in range(brace, len(text)):
        if text[i] == "{":
            depth += 1
        elif text[i] == "}":
            depth -= 1
            if depth == 0:
                return start, i + 1
    return None


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

# Split BoardScreen's large root expression without changing its contents.
board_path = ROOT / "Sources/MosuanBoard/BoardScreenV3.swift"
b = board_path.read_text()
if "private var boardRoot: some View" not in b:
    marker = "    var body: some View {"
    start = b.find(marker)
    if start < 0:
        raise SystemExit("BoardScreen body marker not found")
    block = find_function_block(b, marker)
    if block is None:
        raise SystemExit("BoardScreen body end not found")
    end = block[1]
    original = b[start:end]
    # Keep the original body expression exactly intact; only move it behind a
    # computed ViewBuilder property. The property declaration must include '{'.
    prefix_len = len(marker)
    inner = original[prefix_len:].strip()
    replacement = "    var body: some View { boardRoot }\n\n    @ViewBuilder\n    private var boardRoot: some View {\n" + inner[1:-1] + "\n    }"
    b = b[:start] + replacement + b[end:]
    board_path.write_text(b)

print("Normalized injected helpers and split BoardScreen root type-checking.")
