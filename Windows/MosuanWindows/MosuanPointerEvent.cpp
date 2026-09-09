#include "MosuanPointerEvent.h"

#include <windowsx.h>

namespace mosuan {

static DeviceType deviceTypeForPointer(UINT32 pointerId) {
    POINTER_INPUT_TYPE type = PT_POINTER;
    if (GetPointerType(pointerId, &type)) {
        switch (type) {
        case PT_PEN: return DeviceType::Pen;
        case PT_TOUCH: return DeviceType::Touch;
        case PT_MOUSE: return DeviceType::Mouse;
        default: break;
        }
    }
    return DeviceType::Unknown;
}

PointerEvent makePointerEvent(HWND hwnd, UINT message, WPARAM wParam, LPARAM lParam) {
    PointerEvent event;
    event.pointerId = GET_POINTERID_WPARAM(wParam);
    event.position = { GET_X_LPARAM(lParam), GET_Y_LPARAM(lParam) };
    event.deviceType = deviceTypeForPointer(event.pointerId);

    switch (message) {
    case WM_POINTERDOWN: event.phase = Phase::Began; break;
    case WM_POINTERUP: event.phase = Phase::Ended; break;
    case WM_POINTERUPDATE: event.phase = Phase::Moved; break;
    default: event.phase = Phase::Cancelled; break;
    }

    if (event.deviceType == DeviceType::Pen) {
        POINTER_PEN_INFO penInfo{};
        if (GetPointerPenInfo(event.pointerId, &penInfo)) {
            // Windows reports pressure in [0, 1024]. Keep the normalized
            // representation identical to the Apple-side PointerEvent model.
            event.pressure = static_cast<float>(penInfo.pressure) / 1024.0f;
            event.tiltX = static_cast<float>(penInfo.tiltX);
            event.tiltY = static_cast<float>(penInfo.tiltY);
            event.buttons = penInfo.penFlags;
        }
    } else if (event.deviceType == DeviceType::Touch) {
        POINTER_TOUCH_INFO touchInfo{};
        if (GetPointerTouchInfo(event.pointerId, &touchInfo)) {
            event.pressure = 1.0f;
        }
    }

    event.modifiers = 0;
    if (GetKeyState(VK_SHIFT) & 0x8000) event.modifiers |= 1u;
    if (GetKeyState(VK_CONTROL) & 0x8000) event.modifiers |= 2u;
    if (GetKeyState(VK_MENU) & 0x8000) event.modifiers |= 4u;

    // Keep the parameter explicit so the function remains suitable for a
    // future client-area coordinate transform / DPI scale layer.
    (void)hwnd;
    return event;
}

} // namespace mosuan
