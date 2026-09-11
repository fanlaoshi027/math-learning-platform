import SwiftUI

struct BoardScreen: View {
    @StateObject private var controller = CanvasController()
    @StateObject private var pageController = PageController()
    @State private var tool: BoardTool = .pen
    @State private var presetID = PenPreset.defaults[0].id
    @State private var rotationText = "0"
    @State private var background: BoardBackground = .white
    @State private var inverted = false
    @State private var eyeComfortBackground: EyeComfortBackground = .black90
    @State private var customHex = "1A1A1A"
    @State private var zoomPercent = 100
    @State private var showMoreTools = false

    private var preset: PenPreset { PenPreset.defaults.first { $0.id == presetID } ?? PenPreset.defaults[0] }
    private var rotationBinding: Binding<String> { Binding(get: { rotationText }, set: { rotationText = $0 }) }
    private var currentPage: BoardPage? { pageController.currentPage }
    private var currentPageID: UUID { currentPage?.id ?? UUID() }
    private var currentPageState: CanvasPageState { currentPage?.content ?? CanvasPageState() }

    private var customColor: SIMD4<Float> {
        let value = customHex.trimmingCharacters(in: .whitespacesAndNewlines).replacingOccurrences(of: "#", with: "")
        guard value.count == 6, let rgb = UInt64(value, radix: 16) else { return EyeComfortBackground.black90.color }
        return SIMD4(Float((rgb >> 16) & 0xFF) / 255, Float((rgb >> 8) & 0xFF) / 255, Float(rgb & 0xFF) / 255, 1)
    }

    private var effectiveBackground: SIMD4<Float> {
        if inverted && background == .white { return eyeComfortBackground == .custom ? customColor : eyeComfortBackground.color }
        return background.metal
    }

    private var effectiveBackgroundColor: Color {
        Color(red: Double(effectiveBackground.x), green: Double(effectiveBackground.y), blue: Double(effectiveBackground.z))
    }

    var body: some View {
        ZStack(alignment: .top) {
            effectiveBackgroundColor.ignoresSafeArea()

            HStack(spacing: 0) {
                PageSidebar(controller: pageController)
                    .overlay(alignment: .trailing) { Divider() }

                ZStack(alignment: .top) {
                    MetalInkCanvas(
                        pageID: currentPageID,
                        pageState: currentPageState,
                        tool: $tool,
                        penStyle: preset.style,
                        controller: controller,
                        background: effectiveBackground,
                        inverted: inverted,
                        pattern: .blank,
                        zoomPercent: $zoomPercent,
                        onPageStateChanged: { state in
                            pageController.saveCurrentPageState(state)
                        }
                    )
                    .background(effectiveBackgroundColor)
                    .padding(24)

                    compactToolbar
                }
            }
        }
        .onAppear {
            if let raw = UserDefaults.standard.string(forKey: "mosuan.eyeComfortBackground"), let saved = EyeComfortBackground(rawValue: raw) { eyeComfortBackground = saved }
            if let saved = UserDefaults.standard.string(forKey: "mosuan.customHex"), saved.count == 6 { customHex = saved }
        }
        .onChange(of: controller.rotationDegrees) { _, value in rotationText = String(format: "%.1f", value) }
        .onChange(of: controller.hasSelection) { _, selected in rotationText = selected ? String(format: "%.1f", controller.rotationDegrees) : "0" }
        .onChange(of: eyeComfortBackground) { _, value in UserDefaults.standard.set(value.rawValue, forKey: "mosuan.eyeComfortBackground") }
        .onChange(of: customHex) { _, value in UserDefaults.standard.set(value, forKey: "mosuan.customHex") }
        .frame(minWidth: 1100, minHeight: 700)
    }

    private var compactToolbar: some View {
        HStack(spacing: 4) {
            compactButton("star.fill", active: false, help: "常用笔") {
                presetID = PenPreset.defaults[0].id
                tool = .pen
            }
            toolbarDivider
            compactButton("cursorarrow", active: tool == .select, help: "选择") { tool = .select }
            compactButton("pencil.tip", active: tool == .pen, help: "画笔") { tool = .pen }
            compactButton("line.diagonal", active: tool == .line, help: "直线") { tool = .line }
            compactButton("scribble.variable", active: tool == .smartLine, help: "智能直线") { tool = .smartLine }
            compactButton("eraser", active: tool == .eraser, help: "橡皮") { tool = .eraser }
            toolbarDivider
            ForEach(PenPreset.defaults) { item in
                Button {
                    presetID = item.id
                    tool = .pen
                } label: {
                    Circle()
                        .fill(Color(red: item.style.color.red, green: item.style.color.green, blue: item.style.color.blue))
                        .frame(width: 16, height: 16)
                        .overlay(Circle().stroke(presetID == item.id ? Color.accentColor : .clear, lineWidth: 2))
                        .frame(width: 30, height: 34)
                }
                .buttonStyle(.plain)
                .help(item.name)
            }
            toolbarDivider
            compactButton("arrow.uturn.backward", active: false, help: "撤销") { controller.undo() }
                .disabled(!controller.canUndo)
            compactButton("arrow.uturn.forward", active: false, help: "重做") { controller.redo() }
                .disabled(!controller.canRedo)
            compactButton("plus", active: showMoreTools, help: "更多工具") { showMoreTools.toggle() }
        }
        .padding(.horizontal, 7)
        .padding(.vertical, 5)
        .background(.regularMaterial)
        .clipShape(RoundedRectangle(cornerRadius: 11, style: .continuous))
        .overlay(RoundedRectangle(cornerRadius: 11, style: .continuous).stroke(.quaternary, lineWidth: 1))
        .shadow(color: .black.opacity(0.18), radius: 9, y: 3)
        .padding(.top, 8)
        .popover(isPresented: $showMoreTools, arrowEdge: .top) { moreToolsMenu }
    }

    private var toolbarDivider: some View {
        Divider().frame(height: 22).padding(.horizontal, 3)
    }

    private func compactButton(_ image: String, active: Bool, help: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            Image(systemName: image)
                .font(.system(size: 15, weight: .medium))
                .frame(width: 30, height: 34)
                .foregroundStyle(active ? Color.accentColor : .primary)
                .background(active ? Color.accentColor.opacity(0.14) : Color.clear)
                .clipShape(RoundedRectangle(cornerRadius: 7, style: .continuous))
        }
        .buttonStyle(.plain)
        .help(help)
    }

    @ViewBuilder private var moreToolsMenu: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text("更多工具").font(.headline)
            Divider()
            backgroundMenu
            if background == .white {
                Toggle("反色", isOn: $inverted)
                if inverted { eyeComfortMenu }
            }
            if controller.hasSelection {
                HStack(spacing: 5) {
                    Image(systemName: "rotate.right")
                    TextField("角度", text: rotationBinding)
                        .frame(width: 58)
                        .textFieldStyle(.roundedBorder)
                        .onSubmit { applyRotation() }
                    Text("°")
                    Button("删除") { controller.deleteSelected() }
                }
            }
            HStack {
                Button("缩小") { controller.canvas?.zoomOut() }
                Text("\(zoomPercent)%")
                Button("放大") { controller.canvas?.zoomIn() }
                Button("100%") { controller.canvas?.resetZoom() }
            }
        }
        .padding(14)
        .frame(width: 250)
    }

    @ViewBuilder private var backgroundMenu: some View {
        Menu {
            ForEach(BoardBackground.allCases) { item in
                Button { background = item; if item != .white { inverted = false } } label: {
                    Label(item.title, systemImage: background == item ? "checkmark" : "square")
                }
            }
        } label: { Label("背景", systemImage: "rectangle.fill") }
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
            }.padding(.horizontal, 8)
        } label: { Label(eyeComfortBackground == .custom ? "护眼色" : eyeComfortBackground.title, systemImage: "moon.fill") }
    }

    private var customColorBinding: Binding<Color> {
        Binding(get: {
            Color(red: Double(customColor.x), green: Double(customColor.y), blue: Double(customColor.z))
        }, set: { color in
            let ns = NSColor(color).usingColorSpace(.deviceRGB) ?? NSColor.black
            customHex = String(format: "%02X%02X%02X", Int(round(ns.redComponent * 255)), Int(round(ns.greenComponent * 255)), Int(round(ns.blueComponent * 255)))
            eyeComfortBackground = .custom
        })
    }

    private func applyRotation() {
        guard let value = Double(rotationText) else {
            rotationText = String(format: "%.1f", controller.rotationDegrees)
            return
        }
        controller.setRotationDegrees(value)
    }
}
