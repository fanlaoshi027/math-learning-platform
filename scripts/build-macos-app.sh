#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

APP_NAME="Mosuan Board"
APP_DIR="$ROOT_DIR/dist/$APP_NAME.app"
CONTENTS="$APP_DIR/Contents"
MACOS_DIR="$CONTENTS/MacOS"
RESOURCES_DIR="$CONTENTS/Resources"

rm -rf "$ROOT_DIR/dist"
mkdir -p "$MACOS_DIR" "$RESOURCES_DIR"

# Normalize the dynamic-angle renderer and prepare a transparent ink layer for PDF teaching.
python3 - <<'PY'
from pathlib import Path

renderer = Path("Sources/MosuanBoard/Metal/InkRenderer.swift")
s = renderer.read_text()
old = "        var previous = v + SIMD2(cos(startAngle), sin(startAngle)) * Float(radius)"
new = "        let startCos = Float(cos(startAngle))\n        let startSin = Float(sin(startAngle))\n        let startVector = SIMD2<Float>(startCos, startSin)\n        let radiusFloat = Float(radius)\n        var previous = v + startVector * radiusFloat"
if old in s:
    s = s.replace(old, new, 1)
old = "pass.colorAttachments[0].clearColor=MTLClearColor(red:Double(backgroundColor.x),green:Double(backgroundColor.y),blue:Double(backgroundColor.z),alpha:1)"
new = "pass.colorAttachments[0].clearColor=MTLClearColor(red:Double(backgroundColor.x),green:Double(backgroundColor.y),blue:Double(backgroundColor.z),alpha:Double(backgroundColor.w))"
if old in s:
    s = s.replace(old, new, 1)
renderer.write_text(s)

metal_view = Path("Sources/MosuanBoard/Metal/InkMetalView.swift")
s = metal_view.read_text()
old = "colorPixelFormat = .bgra8Unorm; clearColor"
new = "colorPixelFormat = .bgra8Unorm; isOpaque = false; layer?.isOpaque = false; clearColor"
if old in s:
    s = s.replace(old, new, 1)
metal_view.write_text(s)
PY

BUILD_LOG="$ROOT_DIR/dist/swift-build.log"
set +e
swift build -c release 2>&1 | tee "$BUILD_LOG"
BUILD_STATUS=${PIPESTATUS[0]}
set -e
if [ "$BUILD_STATUS" -ne 0 ]; then
  echo "Swift build failed with status $BUILD_STATUS" >&2
  exit "$BUILD_STATUS"
fi

BINARY="$ROOT_DIR/.build/release/MosuanBoard"
if [ ! -x "$BINARY" ]; then
  echo "Release binary not found: $BINARY" >&2
  exit 1
fi

cp "$BINARY" "$MACOS_DIR/MosuanBoard"

cat > "$CONTENTS/Info.plist" <<'PLIST'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple Computer//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>CFBundleDisplayName</key>
    <string>墨算</string>
    <key>CFBundleName</key>
    <string>Mosuan Board</string>
    <key>CFBundleIdentifier</key>
    <string>com.fanlaoshi.mosuan-board</string>
    <key>CFBundleVersion</key>
    <string>0.1.1</string>
    <key>CFBundleShortVersionString</key>
    <string>0.1.1</string>
    <key>CFBundlePackageType</key>
    <string>APPL</string>
    <key>CFBundleExecutable</key>
    <string>MosuanBoard</string>
    <key>LSMinimumSystemVersion</key>
    <string>14.0</string>
    <key>NSHighResolutionCapable</key>
    <true/>
</dict>
</plist>
PLIST

codesign --force --deep --sign - "$APP_DIR" >/dev/null 2>&1 || true

mkdir -p "$ROOT_DIR/dist"
diskutil_image="$(command -v hdiutil || true)"
if [ -n "$diskutil_image" ]; then
  "$diskutil_image" create -volname "$APP_NAME" -srcfolder "$APP_DIR" -ov -format UDZO "$ROOT_DIR/dist/Mosuan-Board-0.1.1.dmg"
fi

echo "Built: $APP_DIR"
[ -f "$ROOT_DIR/dist/Mosuan-Board-0.1.1.dmg" ] && echo "Built: $ROOT_DIR/dist/Mosuan-Board-0.1.1.dmg"
