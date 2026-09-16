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

# IMPORTANT: do not rewrite BoardScreen's body automatically. The previous
# transformation was fragile and produced invalid declarations. The original
# BoardScreen implementation is known-good and should remain untouched here.

print("Normalized injected helpers without rewriting BoardScreen.")
