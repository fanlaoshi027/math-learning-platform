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

BIN="$(swift build -c release --show-bin-path)/$PRODUCT"
if [ ! -f "$BIN" ]; then
  echo "Build failed: executable not found: $BIN"
  exit 1
fi

mkdir -p "$APP_DIR/Contents/MacOS"
mkdir -p "$APP_DIR/Contents/Resources"
cp "$BIN" "$APP_DIR/Contents/MacOS/$PRODUCT"

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
