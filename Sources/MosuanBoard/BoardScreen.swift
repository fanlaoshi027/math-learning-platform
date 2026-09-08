import SwiftUI

struct BoardScreen: View {
    private enum ToolbarDock: String { case top, bottom, left, right }
    @StateObject private var controller = CanvasController()
    @State private var tool: BoardTool = .pen
    @State private var presetID = PenPreset.defaults[0].id
    @State private var rotationText = "0"
    @State private var toolbarDock: ToolbarDock = .top
    @State private var isDraggingToolbar = false
    @State private var dragLocation = CGPoint.zero
    @State private var background: BoardBackground = .white
    @State private var inverted = false

    private var preset: PenPreset { PenPreset.defaults.first { $0.id == presetID } ?? PenPreset.defaults[0] }
    private var rotationBinding: Binding<String> { Binding(get: { rotationText }, set: { rotationText = $0 }) }
    private var toolbarIsVertical: Bool { toolbarDock == .left || toolbarDock == .right }

    var body: some View {
        GeometryReader { proxy in
            ZStack {
                background.color.ignoresSafeArea()
                MetalInkCanvas(tool: $tool, penStyle: preset.style, controller: controller, background: background, inverted: inverted)
                    .background(background.color).padding(24)
                if isDraggingToolbar { dockingGuides(for: proxy.size) }
                toolbar(in: proxy.size)
                    .frame(maxWidth: toolbarIsVertical ? 82 : .infinity, maxHeight: toolbarIsVertical ? .infinity : 72)
                    .background(.regularMaterial)
                    .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                    .overlay { RoundedRectangle(cornerRadius: 12, style: .continuous).stroke(.quaternary, lineWidth: 1) }
                    .shadow(color: .black.opacity(0.12), radius: 8, y: 2)
                    .padding(8)
                    .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: toolbarAlignment)
            }
            .coordinateSpace(name: "board")
            .animation(.easeInOut(duration: 0.18), value: toolbarDock)
            .onAppear {
                if let raw = UserDefaults.standard.string(forKey: "mosuan.toolbarDock"), let saved = ToolbarDock(rawValue: raw) { toolbarDock = saved }
            }
            .onChange(of: controller.rotationDegrees) { _, value in rotationText = String(format: "%.1f", value) }
            .onChange(of: controller.hasSelection) { _, selected in rotationText = selected ? String(format: "%.1f", controller.rotationDegrees) : "0" }
            .onChange(of: toolbarDock) { _, value in UserDefaults.standard.set(value.rawValue, forKey: "mosuan.toolbarDock") }
        }
        .frame(minWidth: 1100, minHeight: 700)
    }

    private var toolbarAlignment: Alignment { switch toolbarDock { case .top: .top; case .bottom: .bottom; case .left: .leading; case .right: .trailing } }

    @ViewBuilder private func toolbar(in size: CGSize) -> some View {
        if toolbarIsVertical { VStack(spacing: 8) { dragHandle(in: size); Divider(); toolbarContents }.padding(8) }
        else { HStack(spacing: 10) { dragHandle(in: size); Divider().frame(height: 24); toolbarContents }.padding(.horizontal, 12).padding(.vertical, 8) }
    }

    @ViewBuilder private var toolbarContents: some View {
        ToolButton(title: "选择", systemImage: "cursorarrow", selected: tool == .select) { tool = .select }
        ToolButton(title: "画笔", systemImage: "pencil.tip", selected: tool == .pen) { tool = .pen }
        ToolButton(title: "直线", systemImage: "line.diagonal", selected: tool == .line) { tool = .line }
        ToolButton(title: "智能直线", systemImage: "scribble.variable", selected: tool == .smartLine) { tool = .smartLine }
        ToolButton(title: "橡皮", systemImage: "eraser", selected: tool == .eraser) { tool = .eraser }
        Divider().frame(height: toolbarIsVertical ? nil : 24)
        ForEach(PenPreset.defaults) { item in Button { presetID = item.id; tool = .pen } label: { Circle().fill(Color(red: item.style.color.red, green: item.style.color.green, blue: item.style.color.blue)).frame(width: 18, height: 18).overlay { Circle().stroke(presetID == item.id ? Color.accentColor : .clear, lineWidth: 2) } }.buttonStyle(.plain).help(item.name) }
        Divider().frame(height: toolbarIsVertical ? nil : 24)
        Menu {
            ForEach(BoardBackground.allCases) { item in Button { background = item } label: { Label(item.title, systemImage: background == item ? "checkmark" : "square") } }
        } label: { Label("背景", systemImage: "rectangle.fill") }.menuStyle(.borderlessButton)
        Toggle(isOn: $inverted) { Label("反色", systemImage: "circle.lefthalf.filled") }.toggleStyle(.checkbox).help("白色与黑色背景/墨迹互换，彩色墨迹保持颜色")
        if controller.hasSelection {
            HStack(spacing: 5) { Image(systemName: "rotate.right"); TextField("角度", text: rotationBinding).frame(width: 62).textFieldStyle(.roundedBorder).onSubmit { applyRotation() }; Text("°") }.help("输入旋转角度")
        }
        Button { controller.deleteSelected() } label: { Label("删除", systemImage: "trash") }.disabled(!controller.hasSelection)
        Button { controller.undo() } label: { Label("撤销", systemImage: "arrow.uturn.backward") }.keyboardShortcut("z", modifiers: .command).disabled(!controller.canUndo)
        Button { controller.redo() } label: { Label("重做", systemImage: "arrow.uturn.forward") }.keyboardShortcut("z", modifiers: [.command, .shift]).disabled(!controller.canRedo)
    }

    private func dragHandle(in size: CGSize) -> some View {
        Image(systemName: "line.3.horizontal").font(.system(size: 14, weight: .semibold)).frame(width: 28, height: 32).contentShape(Rectangle()).foregroundStyle(.secondary).help("拖动工具条到上、下、左、右")
            .gesture(DragGesture(coordinateSpace: .named("board")).onChanged { value in isDraggingToolbar = true; dragLocation = value.location }.onEnded { value in toolbarDock = nearestDock(for: value.location, in: size); isDraggingToolbar = false })
    }

    @ViewBuilder private func dockingGuides(for size: CGSize) -> some View {
        let target = nearestDock(for: dragLocation, in: size)
        ZStack { edgeGuide(.top, selected: target == .top, size: size); edgeGuide(.bottom, selected: target == .bottom, size: size); edgeGuide(.left, selected: target == .left, size: size); edgeGuide(.right, selected: target == .right, size: size) }.allowsHitTesting(false)
    }

    private func edgeGuide(_ dock: ToolbarDock, selected: Bool, size: CGSize) -> some View {
        let thickness: CGFloat = selected ? 8 : 3
        return Rectangle().fill(Color.accentColor.opacity(selected ? 0.28 : 0.08)).frame(width: dock == .left || dock == .right ? thickness : size.width, height: dock == .top || dock == .bottom ? thickness : size.height).frame(maxWidth: .infinity, maxHeight: .infinity, alignment: edgeAlignment(dock))
    }
    private func edgeAlignment(_ dock: ToolbarDock) -> Alignment { switch dock { case .top: .top; case .bottom: .bottom; case .left: .leading; case .right: .trailing } }
    private func nearestDock(for point: CGPoint, in size: CGSize) -> ToolbarDock { let distances: [(ToolbarDock, CGFloat)] = [(.top, max(0, point.y)), (.bottom, max(0, size.height - point.y)), (.left, max(0, point.x)), (.right, max(0, size.width - point.x))]; return distances.min(by: { $0.1 < $1.1 })?.0 ?? .top }
    private func applyRotation() { guard let value = Double(rotationText) else { rotationText = String(format: "%.1f", controller.rotationDegrees); return }; controller.setRotationDegrees(value) }
}
