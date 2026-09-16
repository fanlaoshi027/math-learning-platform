from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def function_block(text: str, signature: str):
    start = text.find(signature)
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
    first = function_block(text, signature)
    if first is None:
        return text
    first_start, first_end = first
    cursor = first_end
    while True:
        nxt = text.find(signature, cursor)
        if nxt < 0:
            break
        block = function_block(text, signature)
        # Search the specific occurrence by locating its opening brace directly.
        brace = text.find("{", nxt)
        if brace < 0:
            break
        depth = 0
        end = None
        for i in range(brace, len(text)):
            if text[i] == "{":
                depth += 1
            elif text[i] == "}":
                depth -= 1
                if depth == 0:
                    end = i + 1
                    break
        if end is None:
            break
        text = text[:nxt] + text[end:]
        cursor = nxt
    return text


# The tool-feature workflow patch can inject helper methods into the same source
# more than once when a workflow is re-run from a previously patched workspace.
# Normalize the workspace before Swift compilation so the source is deterministic.
view_path = ROOT / "Sources/MosuanBoard/Metal/InkMetalView.swift"
v = view_path.read_text()
for signature in [
    "    func replacePageState(_ state: CanvasPageState)",
    "    func clearPageContents()",
    "    func canvasPoint(from point: SIMD2<Float>)",
]:
    v = keep_first_function(v, signature)

# ToolFeatures is a separate extension file, so expose the renderer conversion
# through the view without making the renderer itself public.
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

# Move BoardScreen's large root expression behind a separate ViewBuilder property.
# Swift 5.10 then type-checks the body wrapper and the large tree independently.
board_path = ROOT / "Sources/MosuanBoard/BoardScreenV3.swift"
b = board_path.read_text()
if "private var boardRoot: some View" not in b:
    marker = "    var body: some View {"
    start = b.find(marker)
    if start < 0:
        raise SystemExit("BoardScreen body marker not found")
    brace = b.find("{", start)
    depth = 0
    end = None
    for i in range(brace, len(b)):
        if b[i] == "{":
            depth += 1
        elif b[i] == "}":
            depth -= 1
            if depth == 0:
                end = i + 1
                break
    if end is None:
        raise SystemExit("BoardScreen body end not found")
    original = b[start:end]
    inner = original[len(marker):].strip()
    replacement = "    var body: some View { boardRoot }\n\n    @ViewBuilder\n    private var boardRoot: some View " + inner
    b = b[:start] + replacement + b[end:]
    board_path.write_text(b)

print("Normalized injected helper methods, added canvasPoint bridge, and split BoardScreen root type-checking.")
