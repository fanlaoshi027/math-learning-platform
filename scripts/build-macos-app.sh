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

# The renderer currently contains a compact declaration that Swift 5.10 parses
# as the custom operator `=-`. Normalize only that token before compilation.
python3 - <<'PY'
from pathlib import Path
p = Path("Sources/MosuanBoard/Metal/InkRenderer.swift")
s = p.read_text()
s = s.replace("nx=-dy/l,ny=dx/l", "nx = -dy/l,ny = dx/l")
p.write_text(s)
PY

swift build -c release
BINARY="$ROOT_DIR/.build/release/MosuanBoard"
if [ ! -x "$BINARY" ]; then
  echo "Release binary not found: $BINARY" >&2
  exit 1
fi

cp "$BINARY" "$MACOS_DIR/MosuanBoard"

cat > "$CONTENTS/Info.plist" <<'PLIST'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>CFBundleDisplayName</key>
    <string>墨算</string>
    <key>CFBundleName</key>
    <string>Mosuan Board</string>
    <key>CFBundleIdentifier</key>
    <string>com.fanlaoshi.mosuan-board</string>
    <key>CFBundleVersion</key>
    <string>0.1.0</string>
    <key>CFBundleShortVersionString</key>
    <string>0.1.0</string>
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
  "$diskutil_image" create -volname "$APP_NAME" -srcfolder "$APP_DIR" -ov -format UDZO "$ROOT_DIR/dist/Mosuan-Board-0.1.0.dmg"
fi

echo "Built: $APP_DIR"
[ -f "$ROOT_DIR/dist/Mosuan-Board-0.1.0.dmg" ] && echo "Built: $ROOT_DIR/dist/Mosuan-Board-0.1.0.dmg"
