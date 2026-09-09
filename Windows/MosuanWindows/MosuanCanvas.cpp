#include "MosuanCanvas.h"

#include <algorithm>
#include <cmath>

#pragma comment(lib, "d2d1.lib")

namespace mosuan {

Canvas::Canvas(HWND hwnd) : hwnd_(hwnd) {}

Canvas::~Canvas() {
    discardResources();
}

bool Canvas::initialize() {
    if (factory_) return true;

    D2D1_FACTORY_OPTIONS options{};
    HRESULT hr = D2D1CreateFactory(
        D2D1_FACTORY_TYPE_SINGLE_THREADED,
        __uuidof(ID2D1Factory1),
        &options,
        reinterpret_cast<void**>(factory_.GetAddressOf()));
    if (FAILED(hr)) return false;

    createResources();
    return renderTarget_ != nullptr;
}

void Canvas::createResources() {
    if (!factory_ || renderTarget_) return;

    RECT rect{};
    GetClientRect(hwnd_, &rect);
    width_ = static_cast<UINT>(std::max<LONG>(0, rect.right - rect.left));
    height_ = static_cast<UINT>(std::max<LONG>(0, rect.bottom - rect.top));

    D2D1_RENDER_TARGET_PROPERTIES props = D2D1::RenderTargetProperties(
        D2D1_RENDER_TARGET_TYPE_DEFAULT,
        D2D1::PixelFormat(DXGI_FORMAT_B8G8R8A8_UNORM, D2D1_ALPHA_MODE_IGNORE));
    D2D1_HWND_RENDER_TARGET_PROPERTIES hwndProps =
        D2D1::HwndRenderTargetProperties(hwnd_, D2D1::SizeU(width_, height_));

    HRESULT hr = factory_->CreateHwndRenderTarget(props, hwndProps, &renderTarget_);
    if (FAILED(hr)) return;

    renderTarget_->CreateSolidColorBrush(
        D2D1::ColorF(D2D1::ColorF::DarkSlateBlue), &inkBrush_);
}

void Canvas::discardResources() {
    inkBrush_.Reset();
    renderTarget_.Reset();
}

void Canvas::resize(UINT width, UINT height) {
    width_ = width;
    height_ = height;
    if (renderTarget_) renderTarget_->Resize(D2D1::SizeU(width, height));
}

void Canvas::beginStroke(const PointerEvent& event) {
    // Default policy matches the product definition: pen writes, touch
    // navigates. Mouse is intentionally ignored by the drawing path for now.
    if (event.deviceType != DeviceType::Pen) return;
    drawing_ = true;
    activeStroke_.clear();
    activeStroke_.push_back({
        static_cast<float>(event.position.x),
        static_cast<float>(event.position.y),
        std::clamp(event.pressure, 0.0f, 1.0f)});
}

void Canvas::updateStroke(const PointerEvent& event) {
    if (!drawing_ || event.deviceType != DeviceType::Pen) return;

    const Point next{
        static_cast<float>(event.position.x),
        static_cast<float>(event.position.y),
        std::clamp(event.pressure, 0.0f, 1.0f)};

    if (!activeStroke_.empty()) {
        const Point& previous = activeStroke_.back();
        const float dx = next.x - previous.x;
        const float dy = next.y - previous.y;
        if ((dx * dx + dy * dy) < 0.25f) return;
    }
    activeStroke_.push_back(next);
}

void Canvas::endStroke(const PointerEvent& event) {
    if (!drawing_ || event.deviceType != DeviceType::Pen) return;
    updateStroke(event);
    if (activeStroke_.size() >= 2) strokes_.push_back(activeStroke_);
    activeStroke_.clear();
    drawing_ = false;
}

void Canvas::cancelStroke() {
    activeStroke_.clear();
    drawing_ = false;
}

void Canvas::drawStroke(ID2D1DeviceContext* context, const std::vector<Point>& stroke) {
    if (stroke.size() < 2 || !inkBrush_) return;

    for (size_t i = 1; i < stroke.size(); ++i) {
        const Point& a = stroke[i - 1];
        const Point& b = stroke[i];
        const float pressure = std::max(0.05f, (a.pressure + b.pressure) * 0.5f);
        const float width = 1.5f + pressure * 4.5f;
        context->DrawLine(
            D2D1::Point2F(a.x, a.y),
            D2D1::Point2F(b.x, b.y),
            inkBrush_.Get(), width);
    }
}

void Canvas::render() {
    if (!initialize() || !renderTarget_) return;

    renderTarget_->BeginDraw();
    renderTarget_->Clear(D2D1::ColorF(D2D1::ColorF::White));

    for (const auto& stroke : strokes_) {
        drawStroke(renderTarget_.Get(), stroke);
    }
    if (drawing_) {
        drawStroke(renderTarget_.Get(), activeStroke_);
    }

    const HRESULT hr = renderTarget_->EndDraw();
    if (hr == D2DERR_RECREATE_TARGET) discardResources();
}

} // namespace mosuan
