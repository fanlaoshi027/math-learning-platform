#!/bin/bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

APP_NAME="Mosuan Board"
PRODUCT="MosuanBoard"
BUILD_DIR="$ROOT/Build"
APP_DIR="$BUILD_DIR/$APP_NAME.app"

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

echo "==> Building $APP_NAME"
swift build -c release

BIN_DIR="$(swift build -c release --show-bin-path)"
BIN="$BIN_DIR/$PRODUCT"
if [ ! -f "$BIN" ]; then
  echo "Build failed: executable not found: $BIN"
  exit 1
fi

mkdir -p "$APP_DIR/Contents/MacOS"
mkdir -p "$APP_DIR/Contents/Resources"
cp "$BIN" "$APP_DIR/Contents/MacOS/$PRODUCT"

# SwiftPM places processed resources (including the Metal library) in a resource bundle.
# Copy that bundle into the app so Bundle.module can load the Metal shaders at runtime.
RESOURCE_BUNDLE="$(find "$BIN_DIR" -maxdepth 1 -type d -name '*.bundle' -print -quit)"
if [ -n "$RESOURCE_BUNDLE" ]; then
  echo "==> Packaging resource bundle: $(basename "$RESOURCE_BUNDLE")"
  cp -R "$RESOURCE_BUNDLE" "$APP_DIR/Contents/Resources/"
else
  echo "WARNING: SwiftPM resource bundle not found in $BIN_DIR"
  echo "The app may build but Metal shader loading can fail at runtime."
fi

cat > "$APP_DIR/Contents/Info.plist" <<'PLIST'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>CFBundleDisplayName</key>
    <string>Mosuan Board</string>
    <key>CFBundleExecutable</key>
    <string>MosuanBoard</string>
    <key>CFBundleIdentifier</key>
    <string>com.fanlaoshi.mosuanboard</string>
    <key>CFBundleName</key>
    <string>Mosuan Board</string>
    <key>CFBundlePackageType</key>
    <string>APPL</string>
    <key>CFBundleShortVersionString</key>
    <string>0.1.0</string>
    <key>CFBundleVersion</key>
    <string>1</string>
    <key>LSMinimumSystemVersion</key>
    <string>14.0</string>
</dict>
</plist>
PLIST

chmod +x "$APP_DIR/Contents/MacOS/$PRODUCT"

DMG="$BUILD_DIR/Mosuan-Board-0.1.0.dmg"
rm -f "$DMG"

echo "==> Creating DMG"
hdiutil create -volname "$APP_NAME" -srcfolder "$APP_DIR" -ov -format UDZO "$DMG" >/dev/null

echo
echo "Build complete:"
echo "  App: $APP_DIR"
echo "  DMG: $DMG"
