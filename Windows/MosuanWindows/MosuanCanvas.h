#pragma once

#include <d2d1.h>
#include <wrl.h>
#include <vector>

#include "MosuanPointerEvent.h"

namespace mosuan {

class Canvas {
public:
    explicit Canvas(HWND hwnd);
    ~Canvas();

    bool initialize();
    void resize(UINT width, UINT height);
    void beginStroke(const PointerEvent& event);
    void updateStroke(const PointerEvent& event);
    void endStroke(const PointerEvent& event);
    void cancelStroke();
    void render();

private:
    struct Point {
        float x;
        float y;
        float pressure;
    };

    void createResources();
    void discardResources();
    void drawStroke(ID2D1DeviceContext* context, const std::vector<Point>& stroke);

    HWND hwnd_ = nullptr;
    UINT width_ = 0;
    UINT height_ = 0;
    bool drawing_ = false;
    std::vector<std::vector<Point>> strokes_;
    std::vector<Point> activeStroke_;

    Microsoft::WRL::ComPtr<ID2D1Factory1> factory_;
    Microsoft::WRL::ComPtr<ID2D1HwndRenderTarget> renderTarget_;
    Microsoft::WRL::ComPtr<ID2D1SolidColorBrush> inkBrush_;
};

} // namespace mosuan
