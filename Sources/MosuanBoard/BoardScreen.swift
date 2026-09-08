import SwiftUI

struct BoardScreen: View {
    private enum ToolbarDock: String {
        case top
        case bottom
        case left
        case right
    }

    @StateObject private var controller = CanvasController()
    @State private var tool: BoardTool = .pen
    @State private var presetID = PenPreset.defaults[0].id
    @State private var rotationText = "0"
    @State private var toolbarDock: ToolbarDock = .top

    private var preset: PenPreset { PenPreset.defaults.first { $0.id == presetID } ?? PenPreset.defaults[0] }
    private var rotationBinding: Binding<String> {
        Binding(get: { rotationText }, set: { rotationText = $0 })
    }

    private var toolbarIsVertical: Bool {
        toolbarDock == .left || toolbarDock == .right
    }

    var body: some View {
        GeometryReader { proxy in
            ZStack {
                MetalInkCanvas(tool: $tool, penStyle: preset.style, controller: controller)
                    .background(.white)
                    .padding(24)

                toolbar
                    .frame(
                        maxWidth: toolbarIsVertical ? 78 : .infinity,
                        maxHeight: toolbarIsVertical ? .infinity : 64
                    )
                    .background(.regularMaterial)
                    .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                    .overlay {
                        RoundedRectangle(cornerRadius: 12, style: .continuous)
                            .stroke(.quaternary, lineWidth: 1)
                    }
                    .shadow(color: .black.opacity(0.12), radius: 8, y: 2)
                    .padding(8)
                    .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: toolbarAlignment)
            }
            .coordinateSpace(name: "board")
            .animation(.easeInOut(duration: 0.18), value: toolbarDock)
            .onChange(of: controller.rotationDegrees) { _, value in
                rotationText = String(format: "%.1f", value)
            }
            .onChange(of: controller.hasSelection) { _, selected in
                if selected { rotationText = String(format: "%.1f", controller.rotationDegrees) }
                else { rotationText = "0" }
            }
            .environment(\.layoutDirection, .leftToRight)
            .onAppear {
                // Restore the last toolbar edge without making it part of the document.
                if let raw = UserDefaults.standard.string(forKey: "mosuan.toolbarDock"),
                   let saved = ToolbarDock(rawValue: raw) {
                    toolbarDock = saved
                }
            }
            .onChange(of: toolbarDock) { _, value in
                UserDefaults.standard.set(value.rawValue, forKey: "mosuan.toolbarDock")
            }
            .overlay(alignment: .topLeading) {
                Color.clear
                    .frame(width: 1, height: 1)
                    .allowsHitTesting(false)
                    .accessibilityHidden(true)
            }
            .background(Color.clear)
            .onPreferenceChange(EmptyPreferenceKey.self) { _ in }
            .contentShape(Rectangle())
            .simultaneousGesture(
                DragGesture(coordinateSpace: .named("board"))
                    .onEnded { value in
                        // This gesture only becomes active when no toolbar control consumes it.
                        // The actual handle gesture is preferred for normal use.
                        if value.translation.width.magnitude > 180 || value.translation.height.magnitude > 180 {
                            toolbarDock = nearestDock(for: value.location, in: proxy.size)
                        }
                    }
            )
        }
        .frame(minWidth: 1100, minHeight: 700)
    }

    private var toolbarAlignment: Alignment {
        switch toolbarDock {
        case .top: return .top
        case .bottom: return .bottom
        case .left: return .leading
        case .right: return .trailing
        }
    }

    @ViewBuilder
    private var toolbar: some View {
        Group {
            if toolbarIsVertical {
                VStack(spacing: 8) {
                    dragHandle
                    Divider()
                    toolbarContents
                }
                .padding(8)
            } else {
                HStack(spacing: 10) {
                    dragHandle
                    Divider().frame(height: 24)
                    toolbarContents
                }
                .padding(.horizontal, 12)
                .padding(.vertical, 8)
            }
        }
    }

    @ViewBuilder
    private var toolbarContents: some View {
        ToolButton(title: "选择", systemImage: "cursorarrow", selected: tool == .select) { tool = .select }
        ToolButton(title: "画笔", systemImage: "pencil.tip", selected: tool == .pen) { tool = .pen }
        ToolButton(title: "橡皮", systemImage: "eraser", selected: tool == .eraser) { tool = .eraser }

        if toolbarIsVertical {
            Divider()
        } else {
            Divider().frame(height: 24)
        }

        ForEach(PenPreset.defaults) { item in
            Button {
                presetID = item.id
                tool = .pen
            } label: {
                Circle()
                    .fill(Color(red: item.style.color.red, green: item.style.color.green, blue: item.style.color.blue))
                    .frame(width: 18, height: 18)
                    .overlay {
                        Circle().stroke(presetID == item.id ? Color.accentColor : .clear, lineWidth: 2)
                    }
            }
            .buttonStyle(.plain)
            .help(item.name)
        }

        if controller.hasSelection {
            if toolbarIsVertical {
                Divider()
                VStack(spacing: 4) {
                    Image(systemName: "rotate.right")
                    TextField("角度", text: rotationBinding)
                        .frame(width: 56)
                        .textFieldStyle(.roundedBorder)
                        .onSubmit { applyRotation() }
                    Text("°")
                }
                .help("输入旋转角度")
            } else {
                HStack(spacing: 5) {
                    Image(systemName: "rotate.right")
                    TextField("角度", text: rotationBinding)
                        .frame(width: 62)
                        .textFieldStyle(.roundedBorder)
                        .onSubmit { applyRotation() }
                    Text("°")
                }
                .help("输入旋转角度")
            }
        }

        Button { controller.deleteSelected() } label: {
            Label("删除", systemImage: "trash")
        }
        .disabled(!controller.hasSelection)

        Button { controller.undo() } label: {
            Label("撤销", systemImage: "arrow.uturn.backward")
        }
        .keyboardShortcut("z", modifiers: .command)
        .disabled(!controller.canUndo)

        Button { controller.redo() } label: {
            Label("重做", systemImage: "arrow.uturn.forward")
        }
        .keyboardShortcut("z", modifiers: [.command, .shift])
        .disabled(!controller.canRedo)
    }

    private var dragHandle: some View {
        Image(systemName: "line.3.horizontal")
            .font(.system(size: 14, weight: .semibold))
            .frame(width: 28, height: 32)
            .contentShape(Rectangle())
            .foregroundStyle(.secondary)
            .help("拖动工具条到上、下、左、右")
            .gesture(
                DragGesture(coordinateSpace: .named("board"))
                    .onEnded { value in
                        // Drawboard-style edge docking: drop near an edge to dock there.
                        toolbarDock = nearestDock(for: value.location, in: currentBoardSize)
                    }
            )
    }

    // The four-edge decision is intentionally based on the current window rather than a fixed size.
    private var currentBoardSize: CGSize {
        // GeometryReader updates the view when the window changes; using the minimum supported
        // window here keeps the helper deterministic until the next layout pass.
        CGSize(width: max(1100, NSScreen.main?.visibleFrame.width ?? 1100),
               height: max(700, NSScreen.main?.visibleFrame.height ?? 700))
    }

    private func nearestDock(for point: CGPoint, in size: CGSize) -> ToolbarDock {
        let distances: [(ToolbarDock, CGFloat)] = [
            (.top, max(0, point.y)),
            (.bottom, max(0, size.height - point.y)),
            (.left, max(0, point.x)),
            (.right, max(0, size.width - point.x))
        ]
        return distances.min(by: { $0.1 < $1.1 })?.0 ?? .top
    }

    private func applyRotation() {
        guard let value = Double(rotationText) else {
            rotationText = String(format: "%.1f", controller.rotationDegrees)
            return
        }
        controller.setRotationDegrees(value)
    }
}

private struct EmptyPreferenceKey: PreferenceKey {
    static var defaultValue: Bool = false
    static func reduce(value: inout Bool, nextValue: () -> Bool) {
        value = value || nextValue()
    }
}
