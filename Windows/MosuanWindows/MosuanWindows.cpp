#include <windows.h>

int WINAPI wWinMain(HINSTANCE hInstance, HINSTANCE, PWSTR, int nCmdShow) {
    MessageBoxW(nullptr, L"墨算 Windows 开发版\n\nWindows 原生画布工程已启动。", L"墨算", MB_OK | MB_ICONINFORMATION);
    return 0;
}
