from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
renderer = ROOT / "Sources/MosuanBoard/Metal/InkRenderer.swift"
r = renderer.read_text()

# Swift 5.10 can time out on the large SIMD Catmull-Rom expression. The
# renderer may already have been converted by the Noteful smoothing repair.
# Make this CI repair idempotent: if the old expression is gone, leave the
# newer renderer untouched instead of failing the entire build.
pattern = re.compile(
    r'(?m)^\s*let position = 0\.5 \* \(\(2\.0 \* p1\) \+ \(-p0 \+ p2\) \* t \+ \(2\.0 \* p0 - 5\.0 \* p1 \+ 4\.0 \* p2 - p3\) \* t2 \+ \(-p0 \+ 3\.0 \* p1 - 3\.0 \* p2 \+ p3\) \* t3\)\s*$'
)
replacement = '''            let a = 2.0 * p1
            let b = (-p0 + p2) * t
            let c = (2.0 * p0 - 5.0 * p1 + 4.0 * p2 - p3) * t2
            let d = (-p0 + 3.0 * p1 - 3.0 * p2 + p3) * t3
            let position = 0.5 * (a + b + c + d)'''

new_r, count = pattern.subn(replacement, r, count=1)
if count == 1:
    renderer.write_text(new_r)
    print("Split the legacy Catmull-Rom SIMD expression into compiler-friendly subexpressions; math unchanged.")
else:
    print("No legacy Catmull-Rom expression found; renderer already uses the newer smoothing implementation. Skipping type-check repair.")
