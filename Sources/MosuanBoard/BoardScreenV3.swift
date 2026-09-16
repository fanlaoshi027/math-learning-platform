import SwiftUI
import AppKit

struct BoardScreen: View {
    private enum ToolbarDock: String { case top, bottom, left, right }
    private enum CanvasBackground: String, CaseIterable, Identifiable {
        case white, lightGray, warmWhite, paleBlue, paleGreen, paleYellow, darkGray, black
        var id: String { rawValue }
        var title: String {
            switch self {
            case .white: return "白色"
            case .lightGray: return "浅灰"
            case .warmWhite: return "米白"
            case .paleBlue: return "淡蓝"
            case .paleGreen: return "淡绿"
            case .paleYellow: return "淡黄"
            case .darkGray: return "深灰"
            case .black: return "黑色"
            }
        }
        var metal: SIMD4<Float> {
            switch self {
            case .white: return SIMD4(1, 1, 1, 1)
            case .lightGray: return SIMD4(0.94, 0.94, 0.94, 1)
            case .warmWhite: return SIMD4(0.98, 0.97, 0.92, 1)
            case .paleBlue: return SIMD4(0.93, 0.96, 1.0, 1)
            case .paleGreen: return SIMD4(0.93, 0.98, 0.94, 1)
            case .paleYellow: return SIMD4(1.0, 0.98, 0.88, 1)
            case .darkGray: return SIMD4(0.16, 0.17, 0.18, 1)
            case .black: return SIMD4(0, 0, 0, 1)
            }
        }
    }
    @StateObject private var controller = CanvasController()
    @StateObject private var pageController = PageController()
    @State private var tool: BoardTool = .pen
    @State private var presetID = PenPreset.defaults[0].id
    @State private var penWidth: Double = 2.0
    @State private var pressureSensitivity: Double = 1.0
    @State private var globalDashed = false
    @State private var eraserMode: EraserMode = .stroke
    @State private var rotationText = "0"
    @State private var zoomPercent = 100
    @State private var toolbarDock: ToolbarDock = .top
    @State private var toolbarDragOffset = CGSize.zero
    @State private var background: BoardBackground = .white
    @State private var canvasBackground: CanvasBackground = .white
    @State private var inverted = false
    @State private var eyeComfortBackground: EyeComfortBackground = .black90
    @State private var customHex = "1A1A1A"
    @State private var interfaceTheme: BoardInterfaceTheme = .light

    private var preset: PenPreset { PenPreset.defaults.first { $0.id == presetID } ?? PenPreset.defaults[0] }
    private var effectivePenStyle: PenStyle {
        var style = preset.style
        style.width = CGFloat(penWidth)
        // PenStyle's pressureCurve is inverse to the user-facing sensitivity:
        // a lower curve makes light pressure respond sooner.
        style.pressureCurve = CGFloat(1.4 - 0.6 * pressureSensitivity)
        style.lineStyle = globalDashed ? .dashed : .solid
        return style
    }
    private var toolbarIsVertical: Bool { toolbarDock == .left || toolbarDock == .right }
    private var effectiveBackground: SIMD4<Float> {
        if inverted && background == .white { return eyeComfortBackground == .custom ? customColor : eyeComfortBackground.color }
        return background.metal
    }
    private var effectiveCanvasBackground: SIMD4<Float> { canvasBackground.metal }
    private var customColor: SIMD4<Float> {
        let value = customHex.replacingOccurrences(of: "#", with: "")
        guard value.count == 6, let rgb = UInt64(value, radix: 16) else { return EyeComfortBackground.black90.color }
        return SIMD4(Float((rgb >> 16) & 255)/255, Float((rgb >> 8) & 255)/255, Float(rgb & 255)/255, 1)
    }

    var body: some View {
        GeometryReader { proxy in
            ZStack {
                Color(red:Double(effectiveBackground.x), green:Double(effectiveBackground.y), blue:Double(effectiveBackground.z)).ignoresSafeArea()
                HStack(spacing:0) {
                    PageSidebar(controller: pageController).overlay(alignment:.trailing) { Divider() }
                    ZStack {
                        MetalInkCanvas(tool:$tool, penStyle:effectivePenStyle, controller:controller, background:effectiveCanvasBackground, inverted:false, pattern:.blank, zoomPercent:$zoomPercent)
                            .padding(24)

                        if let degrees = controller.dynamicAngleDegrees, tool == .select {
                            DynamicAngleParameterPanel(
                                degrees: Binding(get: { degrees }, set: { controller.setDynamicAngleDegrees($0) }),
                                onPlayPause: { controller.toggleDynamicAnglePlayback() },
                                isPlaying: controller.dynamicAnglePlaying
                            )
                            .frame(width: 300)
                            .padding(16)
                            .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .topTrailing)
                        }

                        if let degrees = controller.dynamicTriangleDegrees,
                           let legLength = controller.dynamicTriangleLegLength,
                           tool == .select {
                            DynamicIsoscelesTriangleParameterPanel(
                                degrees: Binding(get: { degrees }, set: { controller.setDynamicTriangleDegrees($0) }),
                                legLength: Binding(get: { legLength }, set: { controller.setDynamicTriangleLegLength($0) }),
                                onParameterEditingChanged: { editing in
                                    if editing { controller.beginDynamicTriangleParameterEditHistory() }
                                    else { controller.endDynamicTriangleParameterEditHistory() }
                                },
                                onPlaybackChanged: { playing in
                                    if playing { controller.beginDynamicTrianglePlaybackHistory() }
                                    else { controller.endDynamicTrianglePlaybackHistory() }
                                }
                            )
                            .frame(width: 300)
                            .padding(16)
                            .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .topTrailing)
                        }

                        toolbar(in: proxy.size)
                            .frame(maxWidth:toolbarIsVertical ? 96 : .infinity, maxHeight:toolbarIsVertical ? .infinity : 76)
                            .background(.regularMaterial)
                            .clipShape(RoundedRectangle(cornerRadius:12, style:.continuous))
                            .overlay { RoundedRectangle(cornerRadius:12, style:.continuous).stroke(.quaternary, lineWidth:1) }
                            .offset(toolbarDragOffset)
                            .padding(8)
                            .frame(maxWidth:.infinity, maxHeight:.infinity, alignment:toolbarAlignment)
                    }
                }
            }
            .onAppear {
                if let raw=UserDefaults.standard.string(forKey:"mosuan.toolbarDock"), let saved=ToolbarDock(rawValue:raw) { toolbarDock=saved }
                if let raw=UserDefaults.standard.string(forKey:"mosuan.eyeComfortBackground"), let saved=EyeComfortBackground(rawValue:raw) { eyeComfortBackground=saved }
                if let saved=UserDefaults.standard.string(forKey:"mosuan.customHex") { customHex=saved }
                if let raw=UserDefaults.standard.string(forKey:"mosuan.interfaceTheme"), let saved=BoardInterfaceTheme(rawValue:raw) { interfaceTheme=saved }
                if let saved=UserDefaults.standard.object(forKey:"mosuan.penWidth") as? Double { penWidth=min(max(saved,0.75),8) }
                if let saved=UserDefaults.standard.object(forKey:"mosuan.pressureSensitivity") as? Double { pressureSensitivity=min(max(saved,0.5),1.5) }
                globalDashed=UserDefaults.standard.bool(forKey:"mosuan.globalDashed")
                if let raw=UserDefaults.standard.string(forKey:"mosuan.eraserMode"), let saved=EraserMode(rawValue:raw) { eraserMode=saved }
                if let raw=UserDefaults.standard.string(forKey:"mosuan.canvasBackground"), let saved=CanvasBackground(rawValue:raw) { canvasBackground=saved }
            }
            .onChange(of:toolbarDock) { _,v in UserDefaults.standard.set(v.rawValue, forKey:"mosuan.toolbarDock") }
            .onChange(of:eyeComfortBackground) { _,v in UserDefaults.standard.set(v.rawValue, forKey:"mosuan.eyeComfortBackground") }
            .onChange(of:customHex) { _,v in UserDefaults.standard.set(v, forKey:"mosuan.customHex") }
            .onChange(of:interfaceTheme) { _,v in UserDefaults.standard.set(v.rawValue, forKey:"mosuan.interfaceTheme") }
            .onChange(of:penWidth) { _,v in UserDefaults.standard.set(v, forKey:"mosuan.penWidth") }
            .onChange(of:pressureSensitivity) { _,v in UserDefaults.standard.set(v, forKey:"mosuan.pressureSensitivity") }
            .onChange(of:globalDashed) { _,v in UserDefaults.standard.set(v, forKey:"mosuan.globalDashed") }
            .onChange(of:eraserMode) { _,v in UserDefaults.standard.set(v.rawValue, forKey:"mosuan.eraserMode") }
            .onChange(of:canvasBackground) { _,v in UserDefaults.standard.set(v.rawValue, forKey:"mosuan.canvasBackground") }
        }
        .frame(minWidth:1100,minHeight:700)
        .preferredColorScheme(interfaceTheme.colorScheme)
    }

    private var toolbarAlignment:Alignment { switch toolbarDock { case .top:.top; case .bottom:.bottom; case .left:.leading; case .right:.trailing } }

    private func finishToolbarDrag(_ translation: CGSize, in size: CGSize) {
        let distance = hypot(translation.width, translation.height)
        guard distance > 60 else { return }
        let x = size.width * 0.5 + translation.width
        let y = size.height * 0.5 + translation.height
        let candidates: [(ToolbarDock, CGFloat)] = [(.left, abs(x)), (.right, abs(size.width - x)), (.top, abs(y)), (.bottom, abs(size.height - y))]
        if let nearest = candidates.min(by: { $0.1 < $1.1 })?.0 { toolbarDock = nearest }
    }

    @ViewBuilder private func toolbar(in size:CGSize)->some View {
        if toolbarIsVertical { VStack(spacing:8) { toolbarDragHandle; toolbarContents }.padding(8) }
        else { HStack(spacing:10) { toolbarDragHandle; toolbarContents }.padding(.horizontal,12).padding(.vertical,8) }
    }

    private var toolbarDragHandle: some View {
        Image(systemName:"circle.grid.2x2")
            .font(.system(size:14, weight:.semibold))
            .foregroundStyle(.secondary)
            .frame(width:28, height:28)
            .contentShape(Rectangle())
            .help("拖动工具栏到上、下、左、右")
            .gesture(DragGesture(minimumDistance:3).onChanged { value in toolbarDragOffset = value.translation }.onEnded { value in
                finishToolbarDrag(value.translation, in: NSScreen.main?.visibleFrame.size ?? CGSize(width:1100,height:700)); toolbarDragOffset = .zero
            })
    }

    @ViewBuilder private var toolbarContents:some View {
        ToolButton(title:"选区",systemImage:"lasso",selected:tool == .select) { tool = .select }
        ToolButton(title:"抓手",systemImage:"hand.draw",selected:tool == .hand) { tool = .hand }
        ToolButton(title:"画笔",systemImage:"pencil.tip",selected:tool == .pen) { tool = .pen }
        ToolButton(title:"直线",systemImage:"line.diagonal",selected:tool == .line) { tool = .line }
        ToolButton(title:"智能直线",systemImage:"scribble.variable",selected:tool == .smartLine) { tool = .smartLine }
        ToolButton(title:"多边形",systemImage:"triangle",selected:tool == .polygon) { tool = .polygon }
        ToolButton(title:"动态角",systemImage:"angle",selected:tool == .dynamicAngle) { tool = .dynamicAngle }
        ToolButton(title:"等腰三角",systemImage:"triangle",selected:tool == .dynamicIsoscelesTriangle) { tool = .dynamicIsoscelesTriangle }

        // Primary click selects the eraser. Secondary interaction (including long press)
        // opens the three requested eraser actions.
        Menu {
            Button { eraserMode = .stroke; tool = .eraser } label: { Label("整根删除", systemImage:"eraser.line.dashed") }
            Button { eraserMode = .partial; tool = .eraser } label: { Label("局部删除", systemImage:"eraser") }
            Divider()
            Button(role: .destructive) { controller.clearCurrentPage() } label: { Label("清屏", systemImage:"rectangle.dashed.and.paperclip") }
        } label: {
            Label("橡皮", systemImage:"eraser")
                .padding(.horizontal,8)
        } primaryAction: {
            tool = .eraser
        }
        .menuStyle(.borderedButton)
        .tint(tool == .eraser ? .accentColor : .secondary)

        Menu {
            Menu("笔粗细") {
                ForEach([1.0, 1.5, 2.0, 2.5, 3.0, 4.0, 5.0, 6.0, 8.0], id:\.self) { value in
                    Button("\(value, specifier: "%.1f") px") { penWidth = value; tool = .pen }
                }
            }
            Menu("压感敏感度") {
                ForEach([0.5, 0.75, 1.0, 1.25, 1.5], id:\.self) { value in
                    Button("\(value, specifier: "%.2f")") { pressureSensitivity = value }
                }
            }
            Toggle("全局虚线", isOn:$globalDashed)
            Divider()
            Text("当前笔宽：\(penWidth, specifier: "%.1f") px")
            Text("压感：\(pressureSensitivity, specifier: "%.2f")")
        } label: {
            Label("笔设置", systemImage:"slider.horizontal.3")
        }
        .menuStyle(.borderlessButton)

        ForEach(PenPreset.defaults) { item in
            Button {
                presetID=item.id
                penWidth=Double(item.style.width)
                tool = .pen
            } label: {
                Circle().fill(Color(red:item.style.color.red, green:item.style.color.green, blue:item.style.color.blue)).frame(width:18,height:18)
            }.buttonStyle(.plain).help(item.name)
        }
        Menu { ForEach(BoardBackground.allCases) { item in Button(item.title) { background=item; if item != .white { inverted=false } } } } label: { Label("背景",systemImage:"rectangle.fill") }.menuStyle(.borderlessButton)
        Menu { ForEach(CanvasBackground.allCases) { item in Button(item.title) { canvasBackground=item } } } label: { Label("画布",systemImage:"rectangle.dashed") }.menuStyle(.borderlessButton)
        if background == .white {
            Toggle("反色",isOn:$inverted).toggleStyle(.checkbox)
            if inverted { Menu { ForEach(EyeComfortBackground.allCases) { item in Button(item.title) { eyeComfortBackground=item } } } label: { Label(eyeComfortBackground.title,systemImage:"moon.fill") }.menuStyle(.borderlessButton) }
        }
        Menu { ForEach(BoardInterfaceTheme.allCases) { item in Button { interfaceTheme=item } label: { Label(item.title,systemImage:item.systemImage) } } } label: { Label(interfaceTheme.title,systemImage:interfaceTheme.systemImage) }.menuStyle(.borderlessButton)
        if controller.hasSelection {
            TextField("角度",text:Binding(get:{rotationText},set:{rotationText=$0})).frame(width:58).textFieldStyle(.roundedBorder).onSubmit { if let d=Double(rotationText) { controller.setRotationDegrees(d) } }
            Text("°")
        }
        Button { controller.deleteSelected() } label: { Label("删除",systemImage:"trash") }.disabled(!controller.hasSelection)
        Button { controller.undo() } label: { Label("撤销",systemImage:"arrow.uturn.backward") }.disabled(!controller.canUndo)
        Button { controller.redo() } label: { Label("重做",systemImage:"arrow.uturn.forward") }.disabled(!controller.canRedo)
        Text("\(zoomPercent)%").font(.caption).monospacedDigit()
    }
}