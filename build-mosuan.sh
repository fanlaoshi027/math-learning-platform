#!/bin/bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

APP_NAME="Mosuan Board"
PRODUCT="MosuanBoard"
VERSION="0.1.2"
BUILD_NUMBER="2"
BUILD_DIR="$ROOT/Build"
APP_DIR="$BUILD_DIR/$APP_NAME.app"

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"

SWIFT_BIN="$(xcrun --find swift)"
ARCH="$(uname -m)"

if [ "$ARCH" = "arm64" ]; then
  echo "==> Native Apple Silicon build (arm64)"
  SWIFT_CMD=(arch -arm64 "$SWIFT_BIN")
else
  echo "==> Intel build (x86_64)"
  echo "WARNING: This Mac is currently running an Intel shell/toolchain."
  echo "For Apple Silicon, run Terminal/Xcode natively rather than through Rosetta."
  SWIFT_CMD=(arch -x86_64 "$SWIFT_BIN")
fi

echo "==> Building $APP_NAME $VERSION"
"${SWIFT_CMD[@]}" build -c release

BIN_DIR="$("${SWIFT_CMD[@]}" build -c release --show-bin-path)"
BIN="$BIN_DIR/$PRODUCT"
if [ ! -f "$BIN" ]; then
  echo "Build failed: executable not found: $BIN"
  exit 1
fi

echo "==> Built binary architecture:"
file "$BIN"

if [ "$ARCH" = "arm64" ] && ! file "$BIN" | grep -q "arm64"; then
  echo "ERROR: Expected an arm64 binary, but the build output is not arm64."
  exit 1
fi

mkdir -p "$APP_DIR/Contents/MacOS"
mkdir -p "$APP_DIR/Contents/Resources"
cp "$BIN" "$APP_DIR/Contents/MacOS/$PRODUCT"

# SwiftPM places processed resources (including the Metal resource bundle)
# next to the executable. Copy every matching bundle into the app resources.
RESOURCE_BUNDLES=("$BIN_DIR"/*.bundle)
if [ -e "${RESOURCE_BUNDLES[0]}" ]; then
  for RESOURCE_BUNDLE in "${RESOURCE_BUNDLES[@]}"; do
    echo "==> Packaging resource bundle: $(basename "$RESOURCE_BUNDLE")"
    cp -R "$RESOURCE_BUNDLE" "$APP_DIR/Contents/Resources/"
  done
else
  echo "ERROR: SwiftPM resource bundle not found in $BIN_DIR"
  echo "Metal shader loading would fail at runtime."
  exit 1
fi

cat > "$APP_DIR/Contents/Info.plist" <<PLIST
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>CFBundleDisplayName</key>
    <string>Mosuan Board</string>
    <key>CFBundleExecutable</key>
    <string>MosuanBoard</string>
    <key>CFBundleIdentifier</key>
    <string>com.fanlaoshi.mosuan-board</string>
    <key>CFBundleName</key>
    <string>Mosuan Board</string>
    <key>CFBundlePackageType</key>
    <string>APPL</string>
    <key>CFBundleShortVersionString</key>
    <string>${VERSION}</string>
    <key>CFBundleVersion</key>
    <string>${BUILD_NUMBER}</string>
    <key>LSMinimumSystemVersion</key>
    <string>14.0</string>
</dict>
</plist>
PLIST

chmod +x "$APP_DIR/Contents/MacOS/$PRODUCT"

DMG="$BUILD_DIR/Mosuan-Board-${VERSION}.dmg"
rm -f "$DMG"

echo "==> Creating DMG"
hdiutil create -volname "$APP_NAME $VERSION" -srcfolder "$APP_DIR" -ov -format UDZO "$DMG" >/dev/null

echo
echo "Build complete:"
echo "  App: $APP_DIR"
echo "  DMG: $DMG"
echo "  Architecture: $ARCH"
