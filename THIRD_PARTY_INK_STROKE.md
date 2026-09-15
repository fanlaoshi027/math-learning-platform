# 墨算手写底层：开源实现优先

## 目标

墨算的手写体验优先采用成熟、许可证友好的开源底层，不自行重新发明平滑、预测、采样等算法。UI、工具布局、收藏工具和教学交互由墨算自己负责。

## 首选：Google Ink Stroke Modeler

仓库：https://github.com/google/ink-stroke-modeler

许可证：Apache-2.0。

它专门将触控笔/指针的原始输入转换为平滑笔迹，并提供运动预测以降低显示延迟。当前墨算的 `StrokeSmoother.swift` 是早期自定义方案，不能作为最终手写引擎。

## 当前状态

- 当前输入入口：`Sources/MosuanBoard/Metal/InkMetalView.swift`
- 当前渲染：Metal
- 当前项目已有 `StrokeSmoother.swift`，但它不应继续扩展为自研手写算法。
- 下一步应接入上游 Ink Stroke Modeler，保持上游实现尽量原样，只做最薄的 Swift/C++ 适配层。

## 集成原则

1. 不修改上游算法来“调出自己的算法”。
2. 仅负责把 macOS `NSEvent` 的位置、时间、pressure 等数据转换成上游 `Input`。
3. 上游输出的 modeled/predicted points 再转换为墨算的 `InkPoint`。
4. Metal 只负责显示，不承担输入平滑逻辑。
5. 保留清晰的第三方目录和许可证文件。
6. 如果某个第三方组件许可证不适合未来商业闭源版本，不进入最终核心依赖。

## 验收标准

先不追求 UI 完整度，只测试高速数学板书：

- 快速连续书写不出现明显“分节”。
- 快速横线、竖线、圆弧连续。
- 停笔位置不明显拖尾。
- 压感连续。
- 延迟不能明显高于当前版本。
- 慢写和快写都保持自然，不出现明显“吸附”或过度美化。
