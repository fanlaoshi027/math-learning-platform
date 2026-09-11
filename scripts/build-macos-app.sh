#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

APP_NAME="Mosuan Board"
VERSION="0.1.2"
BUILD_NUMBER="3"
APP_DIR="$ROOT_DIR/dist/$APP_NAME.app"
CONTENTS="$APP_DIR/Contents"
MACOS_DIR="$CONTENTS/MacOS"
RESOURCES_DIR="$CONTENTS/Resources"

rm -rf "$ROOT_DIR/dist"
mkdir -p "$MACOS_DIR" "$RESOURCES_DIR"

# Normalize expressions rejected by some Swift 6 toolchains.
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

ARCH="$(uname -m)"
echo "Built binary architecture:"
file "$BINARY"
if [ "$ARCH" = "arm64" ] && ! file "$BINARY" | grep -q "arm64"; then
  echo "ERROR: Apple Silicon runner produced a non-arm64 binary." >&2
  exit 1
fi

cp "$BINARY" "$MACOS_DIR/MosuanBoard"

# SwiftPM generates the resource bundle, but a .metal file stored only as a
# resource is NOT a compiled Metal library. The old package therefore contained
# InkShaders.metal but makeDefaultLibrary(bundle:) could not find default.metallib.
# Compile the shader explicitly and put default.metallib inside the same bundle.
RESOURCE_BUNDLE="$(find "$ROOT_DIR/.build" -type d -name 'MosuanBoard_MosuanBoard.bundle' -print -quit)"
if [ -z "$RESOURCE_BUNDLE" ]; then
  echo "SwiftPM resource bundle not found: MosuanBoard_MosuanBoard.bundle" >&2
  find "$ROOT_DIR/.build" -maxdepth 6 -type d -name '*.bundle' -print >&2 || true
  exit 1
fi

METAL_SOURCE="$ROOT_DIR/Sources/MosuanBoard/Metal/InkShaders.metal"
METAL_BUILD_DIR="$ROOT_DIR/dist/metal-build"
AIR="$METAL_BUILD_DIR/InkShaders.air"
METALLIB="$METAL_BUILD_DIR/default.metallib"
mkdir -p "$METAL_BUILD_DIR"

if ! xcrun -sdk macosx metal -mmacosx-version-min=14.0 -c "$METAL_SOURCE" -o "$AIR"; then
  echo "Metal shader compilation failed." >&2
  exit 1
fi
if ! xcrun -sdk macosx metallib "$AIR" -o "$METALLIB"; then
  echo "Metal library linking failed." >&2
  exit 1
fi
cp "$METALLIB" "$RESOURCE_BUNDLE/default.metallib"

# Ship the complete resource bundle in both locations used by SwiftPM's
# Bundle.module lookup across toolchain versions.
cp -R "$RESOURCE_BUNDLE" "$APP_DIR/"
cp -R "$RESOURCE_BUNDLE" "$RESOURCES_DIR/"
echo "Packaged Metal library: $METALLIB"
echo "Packaged SwiftPM resource bundle: $(basename "$RESOURCE_BUNDLE")"

# Generate a deterministic macOS app icon during the build instead of relying on
# a checked-in PNG that may be stale/transparent/blank in Finder. The icon is a
# simple teaching/ink mark: blue rounded square, white pen stroke and orange tip.
GENERATED_ICON="$ROOT_DIR/dist/generated-AppIcon.png"
cat > "$ROOT_DIR/dist/make-app-icon.swift" <<'SWIFT'
import AppKit
import CoreGraphics

let size = 1024
let image = NSImage(size: NSSize(width: size, height: size))
image.lockFocus()

NSColor(calibratedRed: 0.08, green: 0.30, blue: 0.70, alpha: 1).setFill()
NSBezierPath(roundedRect: NSRect(x: 32, y: 32, width: 960, height: 960), xRadius: 210, yRadius: 210).fill()

NSColor.white.setFill()
let pen = NSBezierPath()
pen.move(to: NSPoint(x: 270, y: 690))
pen.line(to: NSPoint(x: 650, y: 310))
pen.line(to: NSPoint(x: 760, y: 420))
pen.line(to: NSPoint(x: 380, y: 800))
pen.close()
pen.fill()

NSColor(calibratedRed: 0.98, green: 0.55, blue: 0.10, alpha: 1).setFill()
let tip = NSBezierPath()
tip.move(to: NSPoint(x: 650, y: 310))
tip.line(to: NSPoint(x: 820, y: 260))
tip.line(to: NSPoint(x: 760, y: 420))
tip.close()
tip.fill()

NSColor(calibratedWhite: 1, alpha: 0.28).setStroke()
let line = NSBezierPath()
line.lineWidth = 22
line.move(to: NSPoint(x: 220, y: 260))
line.line(to: NSPoint(x: 560, y: 260))
line.stroke()

image.unlockFocus()

guard let tiff = image.tiffRepresentation,
      let rep = NSBitmapImageRep(data: tiff),
      let png = rep.representation(using: .png, properties: [:]) else {
    exit(1)
}
try png.write(to: URL(fileURLWithPath: CommandLine.arguments[1]))
SWIFT
swift "$ROOT_DIR/dist/make-app-icon.swift" "$GENERATED_ICON"

ICONSET="$ROOT_DIR/dist/AppIcon.iconset"
mkdir -p "$ICONSET"
while read -r size name; do
  sips -z "$size" "$size" "$GENERATED_ICON" --out "$ICONSET/$name" >/dev/null
done <<'ICON_SIZES'
16 icon_16x16.png
32 icon_16x16@2x.png
32 icon_32x32.png
64 icon_32x32@2x.png
128 icon_128x128.png
256 icon_128x128@2x.png
256 icon_256x256.png
512 icon_256x256@2x.png
512 icon_512x512.png
1024 icon_512x512@2x.png
ICON_SIZES
iconutil -c icns "$ICONSET" -o "$RESOURCES_DIR/AppIcon.icns"

cat > "$CONTENTS/Info.plist" <<PLIST
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
    <string>${BUILD_NUMBER}</string>
    <key>CFBundleShortVersionString</key>
    <string>${VERSION}</string>
    <key>CFBundlePackageType</key>
    <string>APPL</string>
    <key>CFBundleExecutable</key>
    <string>MosuanBoard</string>
    <key>CFBundleIconFile</key>
    <string>AppIcon.icns</string>
    <key>CFBundleIconName</key>
    <string>AppIcon</string>
    <key>LSMinimumSystemVersion</key>
    <string>14.0</string>
    <key>NSHighResolutionCapable</key>
    <true/>
</dict>
</plist>
PLIST

chmod +x "$MACOS_DIR/MosuanBoard"
codesign --force --deep --sign - "$APP_DIR" >/dev/null 2>&1 || true

DMG="$ROOT_DIR/dist/Mosuan-Board-${VERSION}.dmg"
DMG_STAGING="$ROOT_DIR/dist/dmg-staging"
rm -rf "$DMG_STAGING"
mkdir -p "$DMG_STAGING"
cp -R "$APP_DIR" "$DMG_STAGING/"
ln -s /Applications "$DMG_STAGING/Applications"
hdiutil create -volname "$APP_NAME $VERSION" -srcfolder "$DMG_STAGING" -ov -format UDZO "$DMG" >/dev/null

test -d "$APP_DIR/Contents/Resources/MosuanBoard_MosuanBoard.bundle"
test -f "$APP_DIR/Contents/Resources/MosuanBoard_MosuanBoard.bundle/default.metallib"
test -f "$APP_DIR/Contents/Resources/AppIcon.icns"
test -L "$DMG_STAGING/Applications"

rm -rf "$DMG_STAGING" "$ICONSET" "$METAL_BUILD_DIR" "$ROOT_DIR/dist/make-app-icon.swift" "$GENERATED_ICON"

echo "Built: $APP_DIR"
echo "Built: $DMG"
echo "Architecture: $ARCH"
echo "Version: $VERSION"
echo "Build: $BUILD_NUMBER"
