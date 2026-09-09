#pragma once

#include <windows.h>
#include <cstdint>

namespace mosuan {

enum class DeviceType : uint8_t {
    Pen,
    Touch,
    Mouse,
    Unknown
};

enum class Phase : uint8_t {
    Began,
    Moved,
    Ended,
    Cancelled
};

struct PointerEvent {
    POINT position{};
    float pressure = 0.0f;
    float tiltX = 0.0f;
    float tiltY = 0.0f;
    uint32_t buttons = 0;
    uint32_t modifiers = 0;
    uint32_t pointerId = 0;
    DeviceType deviceType = DeviceType::Unknown;
    Phase phase = Phase::Moved;
};

PointerEvent makePointerEvent(HWND hwnd, UINT message, WPARAM wParam, LPARAM lParam);

} // namespace mosuan
