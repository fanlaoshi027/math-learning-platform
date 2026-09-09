# 墨算 Windows

Windows 版本采用原生 WinUI 3 + Windows App SDK + DirectX 渲染层。

## 架构原则

Windows UI / 输入 / 渲染与 Apple 平台分离，但书写数据和核心几何规则保持一致。

第一阶段先建立 Windows 工程边界，不把 SwiftUI / Metal 带入 Windows 目标。

## 共享核心

以下能力应保持跨平台一致：

- 笔迹数据模型
- 页面模型
- 撤销/重做命令模型
- 一笔成型识别规则
- 图形对象模型
- 书写笔参数
- `.mosuan` 文件格式

## Windows 平台层

- WinUI 3：窗口、工具栏、页面 UI
- Windows Pointer / Ink：触控笔、触摸、鼠标输入
- DirectX：低延迟笔迹渲染
- Windows App SDK：应用生命周期与系统能力
- MSIX：正式发布安装包

## 输入策略

默认：

- 手写笔：书写
- 手指：平移/缩放
- 鼠标：选择、工具操作
- 手掌：由 Windows Ink 输入系统和平台层过滤

## 当前阶段

这里只建立 Windows 平台边界和 CI 构建入口。真正的 WinUI 3 工程将在 Core 接口稳定后接入，避免提前复制 Mac/iPad 的业务逻辑。
