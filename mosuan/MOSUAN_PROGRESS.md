# 墨算（Mosuan）开发进展

## 定位
墨算是一块好用的数字书写板，重点是自然书写、板书效率、课堂演示和动态几何。

## 动态几何
核心模型：Geometry Object → Geometry Graph → Constraints → Parameters → Solver → 实时姿态。

图形由点、线、角度、长度和约束组成。已建立固定点、可拖动点、点绑定、线/线段/射线、固定/参数化长度、固定/参数化角度，以及等长、等角、比例等约束基础。

## 动态角
DynamicAngle 已接入 GeometryModel、GeometryParameter、GeometryAngle、GeometryConstraintSolver，并进入 GraphicObject.Kind.dynamicAngle。

已支持创建、Metal 绘制、端点拖动改角、数值设置、范围/步长、播放/暂停/循环、选中控制点、基础保存/加载。

当前第一优先级是“两根线之间的夹角动态变化”。后续为动态长度、动态点、点在线段运动、图形联动、旋转、平行/垂直约束、函数联动。

## CI
曾出现 Swift 类型检查超时，涉及 SIMD2(cos/sin) 表达式；明确 Float 类型可降低类型推导压力。

连续测试 macos-14、macos-15、macos-15-intel、macos-26-intel，均出现约 5～10 秒直接 failure、0 steps、无正常 build log/artifact。因此当前优先排查 GitHub Actions runner / 仓库执行层，而不是继续盲改动态角业务代码。

当前 Workflow 使用 macos-26-intel，并加入 diagnostics。build-macos-app.sh 暂时仍有 perl 自动修改 InkRenderer.swift 的诊断逻辑，最终必须删除，让源码本身保持正确的 Float 写法。

## 下一步
1. 解决 CI runner 0 steps 失败
2. 真正跑 Swift build
3. 修复实际编译错误
4. 删除构建脚本自动改源码的临时方案
5. CI 通过后完善动态角参数面板、播放/Ping-Pong、Undo/Redo、保存/加载
6. 扩展动态长度和动态点

> 产品目标：让老师在黑板上画出来的东西，可以真正动起来。
