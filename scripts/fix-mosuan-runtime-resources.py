from pathlib import Path

TARGETS = [
    Path("Sources/MosuanBoard/StartupDiagnostics.swift"),
    Path("Sources/MosuanBoard/Metal/InkRenderer.swift"),
]

old = "device.makeDefaultLibrary(bundle: Bundle.module)"
new = "device.makeDefaultLibrary(bundle: MosuanResourceBundle.bundle!)"

for path in TARGETS:
    text = path.read_text()
    if old not in text:
        raise SystemExit(f"Bundle.module lookup not found in {path}")
    path.write_text(text.replace(old, new))
PY