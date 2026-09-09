#include <windows.h>
#include <windowsx.h>
#include <d2d1.h>
#include <vector>
#include <algorithm>
#include <cmath>
#include <utility>

#pragma comment(lib, "d2d1.lib")

namespace {
struct Point { float x, y, pressure; };
struct Stroke { std::vector<Point> points; };

ID2D1Factory* g_factory = nullptr;
ID2D1HwndRenderTarget* g_target = nullptr;
ID2D1SolidColorBrush* g_inkBrush = nullptr;
std::vector<Stroke> g_strokes;
std::vector<Stroke> g_redo;
Stroke g_active;
UINT32 g_activePointer = 0;
bool g_drawing = false;
bool g_eraser = false;
bool g_spaceDown = false;
bool g_panning = false;
POINT g_lastPan{};
float g_scale = 1.0f;
float g_offsetX = 0.0f;
float g_offsetY = 0.0f;

constexpr float kMinScale = 0.25f;
constexpr float kMaxScale = 8.0f;

float ClampScale(float value) { return std::clamp(value, kMinScale, kMaxScale); }

POINT ClientPointFromPointer(UINT32 id) {
    POINTER_INFO pi{};
    POINT p{};
    if (GetPointerInfo(id, &pi)) {
        p = pi.ptPixelLocation;
        ScreenToClient(GetActiveWindow(), &p);
    }
    return p;
}

float NormalizePressure(UINT32 raw) {
    if (raw == 0) return 0.5f;
    return std::clamp(static_cast<float>(raw) / 1024.0f, 0.05f, 1.0f);
}

Point ScreenToDocument(POINT p) {
    return {(p.x - g_offsetX) / g_scale, (p.y - g_offsetY) / g_scale, 0.5f};
}

D2D1_POINT_2F DocumentToScreen(Point p) {
    return D2D1::Point2F(p.x * g_scale + g_offsetX, p.y * g_scale + g_offsetY);
}

void ReleaseRenderResources() {
    if (g_inkBrush) { g_inkBrush->Release(); g_inkBrush = nullptr; }
    if (g_target) { g_target->Release(); g_target = nullptr; }
}

void EnsureRenderResources(HWND hwnd) {
    if (!g_factory) D2D1CreateFactory(D2D1_FACTORY_TYPE_SINGLE_THREADED, &g_factory);
    if (g_target) return;
    RECT r{}; GetClientRect(hwnd, &r);
    g_factory->CreateHwndRenderTarget(
        D2D1::RenderTargetProperties(),
        D2D1::HwndRenderTargetProperties(hwnd, D2D1::SizeF(float(r.right-r.left), float(r.bottom-r.top))),
        &g_target);
    if (g_target) g_target->CreateSolidColorBrush(D2D1::ColorF(0.08f,0.12f,0.18f,1.0f), &g_inkBrush);
}

float Distance(Point a, Point b) { return std::hypot(a.x-b.x, a.y-b.y); }

void DrawStroke(const Stroke& s) {
    if (!g_target || !g_inkBrush || s.points.size() < 2) return;
    for (size_t i=1; i<s.points.size(); ++i) {
        const auto& a=s.points[i-1]; const auto& b=s.points[i];
        float p=(a.pressure+b.pressure)*0.5f;
        float width=(1.6f + p*5.4f) * g_scale;
        auto sa = DocumentToScreen(a);
        auto sb = DocumentToScreen(b);
        g_target->DrawLine(sa, sb, g_inkBrush, width);
    }
}

bool HitStroke(const Stroke& s, float x, float y, float radius) {
    if (s.points.empty()) return false;
    for (size_t i=1;i<s.points.size();++i) {
        float vx=s.points[i].x-s.points[i-1].x, vy=s.points[i].y-s.points[i-1].y;
        float wx=x-s.points[i-1].x, wy=y-s.points[i-1].y;
        float len2=vx*vx+vy*vy;
        float t=len2>0 ? std::clamp((wx*vx+wy*vy)/len2,0.0f,1.0f) : 0.0f;
        float dx=x-(s.points[i-1].x+t*vx), dy=y-(s.points[i-1].y+t*vy);
        if (std::hypot(dx,dy)<=radius) return true;
    }
    return Distance(s.points.front(), {x,y,0})<=radius;
}

void EraseAtDocument(float x,float y) {
    constexpr float screenRadius=14.0f;
    const float radius=screenRadius/g_scale;
    g_strokes.erase(std::remove_if(g_strokes.begin(), g_strokes.end(), [&](const Stroke& s){ return HitStroke(s,x,y,radius); }), g_strokes.end());
}

void Undo() {
    if (g_strokes.empty()) return;
    g_redo.push_back(std::move(g_strokes.back()));
    g_strokes.pop_back();
}
void Redo() {
    if (g_redo.empty()) return;
    g_strokes.push_back(std::move(g_redo.back()));
    g_redo.pop_back();
}

void ZoomAt(HWND hwnd, float factor, POINT screenPoint) {
    const Point before = ScreenToDocument(screenPoint);
    g_scale = ClampScale(g_scale * factor);
    g_offsetX = screenPoint.x - before.x * g_scale;
    g_offsetY = screenPoint.y - before.y * g_scale;
    InvalidateRect(hwnd,nullptr,FALSE);
}

void Render(HWND hwnd) {
    EnsureRenderResources(hwnd); if (!g_target) return;
    g_target->BeginDraw();
    g_target->Clear(D2D1::ColorF(0.97f,0.97f,0.95f,1.0f));
    for (const auto& s:g_strokes) DrawStroke(s);
    if (g_drawing && !g_eraser) DrawStroke(g_active);
    HRESULT hr=g_target->EndDraw();
    if (hr==D2DERR_RECREATE_TARGET) ReleaseRenderResources();
}

bool ReadPointer(UINT32 id, Point& out, bool& pen) {
    POINTER_INFO pi{};
    if (!GetPointerInfo(id,&pi)) return false;
    pen=(pi.pointerType==PT_PEN);
    POINT client=pi.ptPixelLocation;
    HWND hwnd=GetActiveWindow();
    ScreenToClient(hwnd,&client);
    out=ScreenToDocument(client);
    if (pen) {
        POINTER_PEN_INFO pp{};
        if (GetPointerPenInfo(id,&pp)) out.pressure=NormalizePressure(pp.pressure);
    }
    return true;
}

void AddPoint(Point p) {
    if (!g_active.points.empty() && Distance(g_active.points.back(),p)<0.7f/g_scale) return;
    g_active.points.push_back(p);
}
}

LRESULT CALLBACK WndProc(HWND hwnd, UINT msg, WPARAM wp, LPARAM lp) {
    switch(msg) {
    case WM_CREATE: EnsureRenderResources(hwnd); return 0;
    case WM_KEYDOWN:
        if (wp==VK_SPACE) { g_spaceDown=true; return 0; }
        if ((GetKeyState(VK_CONTROL)&0x8000) && wp=='Z') Undo();
        else if ((GetKeyState(VK_CONTROL)&0x8000) && wp=='Y') Redo();
        else if (wp=='B') g_eraser=false;
        else if (wp=='E') g_eraser=true;
        InvalidateRect(hwnd,nullptr,FALSE); return 0;
    case WM_KEYUP:
        if (wp==VK_SPACE) { g_spaceDown=false; g_panning=false; ReleaseCapture(); return 0; }
        break;
    case WM_MOUSEWHEEL: {
        POINT p{GET_X_LPARAM(lp), GET_Y_LPARAM(lp)};
        ScreenToClient(hwnd,&p);
        const float factor = GET_WHEEL_DELTA_WPARAM(wp) > 0 ? 1.12f : (1.0f/1.12f);
        ZoomAt(hwnd,factor,p);
        return 0;
    }
    case WM_LBUTTONDOWN:
        if (g_spaceDown) {
            g_panning=true; g_lastPan={GET_X_LPARAM(lp),GET_Y_LPARAM(lp)}; SetCapture(hwnd); return 0;
        }
        break;
    case WM_MOUSEMOVE:
        if (g_panning) {
            POINT p{GET_X_LPARAM(lp),GET_Y_LPARAM(lp)};
            g_offsetX += float(p.x-g_lastPan.x);
            g_offsetY += float(p.y-g_lastPan.y);
            g_lastPan=p;
            InvalidateRect(hwnd,nullptr,FALSE);
            return 0;
        }
        break;
    case WM_LBUTTONUP:
        if (g_panning) { g_panning=false; ReleaseCapture(); return 0; }
        break;
    case WM_POINTERDOWN: {
        UINT32 id=GET_POINTERID(wp); Point p{}; bool pen=false;
        if (!ReadPointer(id,p,pen)) return 0;
        if (pen) {
            g_activePointer=id; g_drawing=true; g_active.points.clear();
            if (g_eraser) EraseAtDocument(p.x,p.y); else { AddPoint(p); g_redo.clear(); }
            SetCapture(hwnd); InvalidateRect(hwnd,nullptr,FALSE); return 0;
        }
        break;
    }
    case WM_POINTERUPDATE: {
        UINT32 id=GET_POINTERID(wp); if (!g_drawing || id!=g_activePointer) break;
        Point p{}; bool pen=false; if (!ReadPointer(id,p,pen)) break;
        if (g_eraser) EraseAtDocument(p.x,p.y); else AddPoint(p);
        InvalidateRect(hwnd,nullptr,FALSE); return 0;
    }
    case WM_POINTERUP: {
        UINT32 id=GET_POINTERID(wp); if (!g_drawing || id!=g_activePointer) break;
        Point p{}; bool pen=false; if (ReadPointer(id,p,pen) && !g_eraser) AddPoint(p);
        if (!g_eraser && g_active.points.size()>1) g_strokes.push_back(std::move(g_active));
        g_active.points.clear(); g_drawing=false; g_activePointer=0; ReleaseCapture();
        InvalidateRect(hwnd,nullptr,FALSE); return 0;
    }
    case WM_SIZE:
        if (g_target) g_target->Resize(D2D1::SizeU(LOWORD(lp),HIWORD(lp)));
        return 0;
    case WM_PAINT: { PAINTSTRUCT ps{}; BeginPaint(hwnd,&ps); Render(hwnd); EndPaint(hwnd,&ps); return 0; }
    case WM_DESTROY:
        ReleaseRenderResources(); if (g_factory) { g_factory->Release(); g_factory=nullptr; }
        PostQuitMessage(0); return 0;
    }
    return DefWindowProc(hwnd,msg,wp,lp);
}

int WINAPI wWinMain(HINSTANCE h,HINSTANCE, PWSTR,int) {
    const wchar_t* cls=L"MosuanWindowsCanvas";
    WNDCLASS wc{}; wc.lpfnWndProc=WndProc; wc.hInstance=h; wc.lpszClassName=cls; wc.hCursor=LoadCursor(nullptr,IDC_ARROW);
    RegisterClass(&wc);
    HWND hwnd=CreateWindowEx(0,cls,L"墨算 · Windows 开发版",WS_OVERLAPPEDWINDOW|WS_VISIBLE,CW_USEDEFAULT,CW_USEDEFAULT,1280,800,nullptr,nullptr,h,nullptr);
    if(!hwnd) return 0;
    MSG msg{}; while(GetMessage(&msg,nullptr,0,0)>0){TranslateMessage(&msg);DispatchMessage(&msg);} return 0;
}
