#include <windows.h>
#include <windowsx.h>
#include <d2d1.h>
#include <vector>
#include <utility>

#pragma comment(lib, "d2d1.lib")

namespace {

struct Point {
    float x = 0;
    float y = 0;
    float pressure = 0.5f;
};

struct Stroke {
    std::vector<Point> points;
};

ID2D1Factory* g_factory = nullptr;
ID2D1HwndRenderTarget* g_target = nullptr;
ID2D1SolidColorBrush* g_brush = nullptr;
std::vector<Stroke> g_strokes;
Stroke g_activeStroke;
bool g_writing = false;
UINT32 g_pointerId = 0;

void ReleaseTarget() {
    if (g_brush) { g_brush->Release(); g_brush = nullptr; }
    if (g_target) { g_target->Release(); g_target = nullptr; }
}

bool EnsureTarget(HWND hwnd) {
    if (g_target) return true;

    RECT rc{};
    GetClientRect(hwnd, &rc);
    if (rc.right <= 0 || rc.bottom <= 0) return false;

    const D2D1_SIZE_U size = D2D1::SizeU(
        static_cast<UINT32>(rc.right - rc.left),
        static_cast<UINT32>(rc.bottom - rc.top));

    HRESULT hr = g_factory->CreateHwndRenderTarget(
        D2D1::RenderTargetProperties(),
        D2D1::HwndRenderTargetProperties(hwnd, size),
        &g_target);
    if (FAILED(hr)) return false;

    hr = g_target->CreateSolidColorBrush(
        D2D1::ColorF(0.08f, 0.10f, 0.13f, 1.0f), &g_brush);
    if (FAILED(hr)) {
        ReleaseTarget();
        return false;
    }
    return true;
}

float NormalizePressure(UINT32 raw) {
    if (raw == 0) return 0.5f;
    return min(1.0f, max(0.05f, static_cast<float>(raw) / 1024.0f));
}

void AddPoint(float x, float y, float pressure) {
    if (!g_writing) return;

    const float p = min(1.0f, max(0.05f, pressure));
    if (!g_activeStroke.points.empty()) {
        const Point& last = g_activeStroke.points.back();
        const float dx = x - last.x;
        const float dy = y - last.y;
        if ((dx * dx + dy * dy) < 1.0f) return;
    }
    g_activeStroke.points.push_back({x, y, p});
}

void DrawStroke(const Stroke& stroke) {
    if (!g_target || !g_brush || stroke.points.size() < 2) return;

    for (size_t i = 1; i < stroke.points.size(); ++i) {
        const Point& a = stroke.points[i - 1];
        const Point& b = stroke.points[i];
        const float pressure = (a.pressure + b.pressure) * 0.5f;
        const float width = 2.0f + pressure * 5.0f;
        g_target->DrawLine(
            D2D1::Point2F(a.x, a.y),
            D2D1::Point2F(b.x, b.y),
            g_brush,
            width);
    }
}

void Render(HWND hwnd) {
    if (!EnsureTarget(hwnd)) return;

    g_target->BeginDraw();
    g_target->Clear(D2D1::ColorF(0.98f, 0.98f, 0.97f, 1.0f));

    for (const Stroke& stroke : g_strokes) DrawStroke(stroke);
    DrawStroke(g_activeStroke);

    const HRESULT hr = g_target->EndDraw();
    if (hr == D2DERR_RECREATE_TARGET) ReleaseTarget();
}

bool ReadPointer(HWND hwnd, UINT32 pointerId) {
    POINTER_INFO info{};
    if (!GetPointerInfo(pointerId, &info)) return false;

    POINT pt = info.ptPixelLocation;
    ScreenToClient(hwnd, &pt);

    float pressure = 0.5f;
    if (info.pointerType == PT_PEN) {
        POINTER_PEN_INFO pen{};
        if (GetPointerPenInfo(pointerId, &pen)) {
            pressure = NormalizePressure(pen.pressure);
        }
    }

    AddPoint(static_cast<float>(pt.x), static_cast<float>(pt.y), pressure);
    return true;
}

LRESULT CALLBACK WndProc(HWND hwnd, UINT msg, WPARAM wParam, LPARAM lParam) {
    switch (msg) {
    case WM_CREATE:
        return 0;

    case WM_SIZE:
        if (g_target) {
            const UINT width = LOWORD(lParam);
            const UINT height = HIWORD(lParam);
            g_target->Resize(D2D1::SizeU(width, height));
        }
        InvalidateRect(hwnd, nullptr, FALSE);
        return 0;

    case WM_ERASEBKGND:
        return 1;

    case WM_POINTERDOWN: {
        const UINT32 pointerId = GET_POINTERID_WPARAM(wParam);
        POINTER_INFO info{};
        if (GetPointerInfo(pointerId, &info)) {
            // Pen is the primary writing device. Mouse remains available as a development fallback.
            if (info.pointerType == PT_PEN || info.pointerType == PT_MOUSE) {
                g_writing = true;
                g_pointerId = pointerId;
                g_activeStroke.points.clear();
                ReadPointer(hwnd, pointerId);
                SetCapture(hwnd);
                InvalidateRect(hwnd, nullptr, FALSE);
            }
        }
        return 0;
    }

    case WM_POINTERUPDATE: {
        const UINT32 pointerId = GET_POINTERID_WPARAM(wParam);
        if (g_writing && pointerId == g_pointerId) {
            ReadPointer(hwnd, pointerId);
            InvalidateRect(hwnd, nullptr, FALSE);
        }
        return 0;
    }

    case WM_POINTERUP: {
        const UINT32 pointerId = GET_POINTERID_WPARAM(wParam);
        if (g_writing && pointerId == g_pointerId) {
            ReadPointer(hwnd, pointerId);
            if (g_activeStroke.points.size() >= 2) {
                g_strokes.push_back(std::move(g_activeStroke));
            }
            g_activeStroke.points.clear();
            g_writing = false;
            g_pointerId = 0;
            ReleaseCapture();
            InvalidateRect(hwnd, nullptr, FALSE);
        }
        return 0;
    }

    case WM_PAINT: {
        PAINTSTRUCT ps{};
        BeginPaint(hwnd, &ps);
        Render(hwnd);
        EndPaint(hwnd, &ps);
        return 0;
    }

    case WM_DESTROY:
        ReleaseTarget();
        if (g_factory) { g_factory->Release(); g_factory = nullptr; }
        PostQuitMessage(0);
        return 0;
    }

    return DefWindowProcW(hwnd, msg, wParam, lParam);
}

} // namespace

int WINAPI wWinMain(HINSTANCE hInstance, HINSTANCE, PWSTR, int nCmdShow) {
    HRESULT hr = D2D1CreateFactory(D2D1_FACTORY_TYPE_SINGLE_THREADED, &g_factory);
    if (FAILED(hr)) return 1;

    const wchar_t kClassName[] = L"MosuanWindowsCanvas";

    WNDCLASSW wc{};
    wc.hInstance = hInstance;
    wc.lpfnWndProc = WndProc;
    wc.lpszClassName = kClassName;
    wc.hCursor = LoadCursor(nullptr, IDC_ARROW);
    wc.hbrBackground = nullptr;

    if (!RegisterClassW(&wc)) {
        g_factory->Release();
        g_factory = nullptr;
        return 1;
    }

    HWND hwnd = CreateWindowExW(
        0,
        kClassName,
        L"墨算 · Windows 开发版",
        WS_OVERLAPPEDWINDOW,
        CW_USEDEFAULT, CW_USEDEFAULT,
        1280, 800,
        nullptr, nullptr, hInstance, nullptr);

    if (!hwnd) {
        g_factory->Release();
        g_factory = nullptr;
        return 1;
    }

    ShowWindow(hwnd, nCmdShow);
    UpdateWindow(hwnd);

    MSG msg{};
    while (GetMessageW(&msg, nullptr, 0, 0) > 0) {
        TranslateMessage(&msg);
        DispatchMessageW(&msg);
    }

    return static_cast<int>(msg.wParam);
}
