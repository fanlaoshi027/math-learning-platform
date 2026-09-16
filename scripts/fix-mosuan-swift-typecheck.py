from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
renderer = ROOT / "Sources/MosuanBoard/Metal/InkRenderer.swift"
r = renderer.read_text()

# Swift 5.10 can time out on the large SIMD Catmull-Rom expression. Keep the
# exact same math, but split it into compiler-friendly intermediate values.
pattern = re.compile(
    r'(?m)^\s*let position = 0\.5 \* \(\(2\.0 \* p1\) \+ \(-p0 \+ p2\) \* t \+ \(2\.0 \* p0 - 5\.0 \* p1 \+ 4\.0 \* p2 - p3\) \* t2 \+ \(-p0 \+ 3\.0 \* p1 - 3\.0 \* p2 \+ p3\) \* t3\)\s*$'
)
replacement = '''            let a = 2.0 * p1
            let b = (-p0 + p2) * t
            let c = (2.0 * p0 - 5.0 * p1 + 4.0 * p2 - p3) * t2
            let d = (-p0 + 3.0 * p1 - 3.0 * p2 + p3) * t3
            let position = 0.5 * (a + b + c + d)'''

new_r, count = pattern.subn(replacement, r, count=1)
if count != 1:
    raise SystemExit("Catmull-Rom expression not found; refusing to modify unrelated code")

renderer.write_text(new_r)
print("Split the Catmull-Rom SIMD expression into compiler-friendly subexpressions; math unchanged.")
