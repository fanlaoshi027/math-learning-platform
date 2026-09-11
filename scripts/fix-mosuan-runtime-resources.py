from pathlib import Path

startup = Path("Sources/MosuanBoard/StartupDiagnostics.swift")
text = startup.read_text()
old = "guard let library = try? device.makeDefaultLibrary(bundle: Bundle.module) else {"
new = "guard let resourceBundle = MosuanResourceBundle.bundle else {\n            fail(\"Metal Shader 资源包未找到。\")\n            return\n        }\n        guard let library = try? device.makeDefaultLibrary(bundle: resourceBundle) else {"
if old not in text:
    raise SystemExit("StartupDiagnostics Bundle.module lookup not found")
startup.write_text(text.replace(old, new, 1).replace("版本：0.1.2", "版本：0.1.4", 1))

renderer = Path("Sources/MosuanBoard/Metal/InkRenderer.swift")
text = renderer.read_text()
old = "guard let queue = device.makeCommandQueue(), let library = try? device.makeDefaultLibrary(bundle: Bundle.module), let vf = library.makeFunction(name: \"inkVertex\"), let ff = library.makeFunction(name: \"inkFragment\") else { return nil }"
new = "guard let queue = device.makeCommandQueue(), let resourceBundle = MosuanResourceBundle.bundle, let library = try? device.makeDefaultLibrary(bundle: resourceBundle), let vf = library.makeFunction(name: \"inkVertex\"), let ff = library.makeFunction(name: \"inkFragment\") else { return nil }"
if old not in text:
    raise SystemExit("InkRenderer Bundle.module lookup not found")
renderer.write_text(text.replace(old, new, 1))
