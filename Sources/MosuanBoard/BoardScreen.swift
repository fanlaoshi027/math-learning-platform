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
    @State private var eyeComfortBackground: EyeComfortBackground = .black90
    @State private var customHex = "1A1A1A"

    private var preset: PenPreset { PenPreset.defaults.first { $0.id == presetID } ?? PenPreset.defaults[0] }
    private var rotationBinding: Binding<String> { Binding(get: { rotationText }, set: { rotationText = $0 }) }
    private var toolbarIsVertical: Bool { toolbarDock == .left || toolbarDock == .right }

    private var customColor: SIMD4<Float> {
        let value = customHex.trimmingCharacters(in: .whitespacesAndNewlines).replacingOccurrences(of: "#", with: "")
        guard value.count == 6, let rgb = UInt64(value, radix: 16) else { return EyeComfortBackground.black90.color }
        return SIMD4(Float((rgb >> 16) & 0xFF) / 255, Float((rgb >> 8) & 0xFF) / 255, Float(rgb & 0xFF) / 255, 1)
    }

    private var effectiveBackground: SIMD4<Float> {
        if inverted && background == .white {
            return eyeComfortBackground == .custom ? customColor : eyeComfortBackground.color
        }
        return background.metal
    }

    private var effectiveBackgroundColor: Color {
        Color(red: Double(effectiveBackground.x), green: Double(effectiveBackground.y), blue: Double(effectiveBackground.z))
    }

    var body: some View {
        GeometryReader { proxy in
            ZStack {
                effectiveBackgroundColor.ignoresSafeArea()
                MetalInkCanvas(tool: $tool, penStyle: preset.style, controller: controller, background: effectiveBackground, inverted: inverted)
                    .background(effectiveBackgroundColor).padding(24)
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
                if let raw = UserDefaults.standard.string(forKey: "mosuan.eyeComfortBackground"), let saved = EyeComfortBackground(rawValue: raw) { eyeComfortBackground = saved }
                if let saved = UserDefaults.standard.string(forKey: "mosuan.customHex"), saved.count == 6 { customHex = saved }
            }
            .onChange(of: controller.rotationDegrees) { _, value in rotationText = String(format: "%.1f", value) }
            .onChange(of: controller.hasSelection) { _, selected in rotationText = selected ? String(format: "%.1f", controller.rotationDegrees) : "0" }
            .onChange(of: toolbarDock) { _, value in UserDefaults.standard.set(value.rawValue, forKey: "mosuan.toolbarDock") }
            .onChange(of: eyeComfortBackground) { _, value in UserDefaults.standard.set(value.rawValue, forKey: "mosuan.eyeComfortBackground") }
            .onChange(of: customHex) { _, value in UserDefaults.standard.set(value, forKey: "mosuan.customHex") }
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
        backgroundMenu
        if background == .white {
            Toggle(isOn: $inverted) { Label("反色", systemImage: "circle.lefthalf.filled") }
                .toggleStyle(.checkbox)
                .help("白色背景切换为护眼深色背景")
            if inverted { eyeComfortMenu }
        }
        if controller.hasSelection {
            HStack(spacing: 5) { Image(systemName: "rotate.right"); TextField("角度", text: rotationBinding).frame(width: 62).textFieldStyle(.roundedBorder).onSubmit { applyRotation() }; Text("°") }.help("输入旋转角度")
        }
        Button { controller.deleteSelected() } label: { Label("删除", systemImage: "trash") }.disabled(!controller.hasSelection)
        Button { controller.undo() } label: { Label("撤销", systemImage: "arrow.uturn.backward") }.keyboardShortcut("z", modifiers: .command).disabled(!controller.canUndo)
        Button { controller.redo() } label: { Label("重做", systemImage: "arrow.uturn.forward") }.keyboardShortcut("z", modifiers: [.command, .shift]).disabled(!controller.canRedo)
    }

    @ViewBuilder private var backgroundMenu: some View {
        Menu {
            ForEach(BoardBackground.allCases) { item in Button { background = item; if item != .white { inverted = false } } label: { Label(item.title, systemImage: background == item ? "checkmark" : "square") } }
        } label: { Label("背景", systemImage: "rectangle.fill") }.menuStyle(.borderlessButton)
    }

    @ViewBuilder private var eyeComfortMenu: some View {
        Menu {
            ForEach(EyeComfortBackground.allCases) { item in
                Button { eyeComfortBackground = item } label: {
                    Label(item.title, systemImage: eyeComfortBackground == item ? "checkmark" : "circle")
                }
            }
            Divider()
            HStack(spacing: 6) {
                Text("色值")
                TextField("RRGGBB", text: $customHex).frame(width: 78).textFieldStyle(.roundedBorder)
                ColorPicker("", selection: customColorBinding).labelsHidden()
            }
            .padding(.horizontal, 8)
        } label: {
            Label(eyeComfortBackground == .custom ? "护眼色" : eyeComfortBackground.title, systemImage: "moon.fill")
        }
        .menuStyle(.borderlessButton)
    }

    private var customColorBinding: Binding<Color> {
        Binding(
            get: { Color(red: Double(customColor.x), green: Double(customColor.y), blue: Double(customColor.z)) },
            set: { color in
                let ns = NSColor(color).usingColorSpace(.deviceRGB) ?? NSColor.black
                let r = Int(round(ns.redComponent * 255)), g = Int(round(ns.greenComponent * 255)), b = Int(round(ns.blueComponent * 255))
                customHex = String(format: "%02X%02X%02X", r, g, b)
                eyeComfortBackground = .custom
            }
        )
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
