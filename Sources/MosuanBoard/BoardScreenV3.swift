import SwiftUI
import AppKit

struct BoardScreen: View {
    private enum ToolbarDock: String { case top, bottom, left, right }
    @StateObject private var controller = CanvasController()
    @StateObject private var pageController = PageController()
    @State private var tool: BoardTool = .pen
    @State private var presetID = PenPreset.defaults[0].id
    @State private var rotationText = "0"
    @State private var zoomPercent = 100
    @State private var toolbarDock: ToolbarDock = .top
    @State private var toolbarDragOffset = CGSize.zero
    @State private var background: BoardBackground = .black
    @State private var inverted = false
    @State private var eyeComfortBackground: EyeComfortBackground = .black90
    @State private var customHex = "1A1A1A"
    @State private var interfaceTheme: BoardInterfaceTheme = .darkPurple

    private var preset: PenPreset { PenPreset.defaults.first { $0.id == presetID } ?? PenPreset.defaults[0] }
    private var toolbarIsVertical: Bool { toolbarDock == .left || toolbarDock == .right }
    private var effectiveBackground: SIMD4<Float> {
        if inverted && background == .white { return eyeComfortBackground == .custom ? customColor : eyeComfortBackground.color }
        return background.metal
    }
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
                        MetalInkCanvas(tool:$tool, penStyle:preset.style, controller:controller, background:effectiveBackground, inverted:inverted, pattern:.blank, zoomPercent:$zoomPercent)
                            .padding(24)
                            .contextMenu {
                                Section("编辑") {
                                    Button { controller.copySelection() } label: { Label("复制", systemImage: "doc.on.doc") }
                                        .disabled(!controller.hasSelection)
                                    Button { _ = controller.cutSelection() } label: { Label("剪切", systemImage: "scissors") }
                                        .disabled(!controller.hasSelection || controller.selectedObjectsAreAnyLocked)
                                    Button { _ = controller.pasteSelection() } label: { Label("粘贴", systemImage: "doc.on.clipboard") }
                                    Divider()
                                    Button(role: .destructive) { controller.deleteSelected() } label: { Label("删除", systemImage: "trash") }
                                        .disabled(!controller.hasSelection || controller.selectedObjectsAreAnyLocked)
                                }
                                Section("对象") {
                                    Button { controller.toggleSelectedLock() } label: {
                                        Label(controller.selectedObjectsAreAllLocked ? "解锁对象" : "锁定对象", systemImage: controller.selectedObjectsAreAllLocked ? "lock.open" : "lock")
                                    }.disabled(!controller.hasSelection)
                                    Button { controller.bringSelectedToFront() } label: { Label("置于顶层", systemImage: "square.stack.3d.up") }
                                        .disabled(!controller.hasSelection)
                                    Button { controller.sendSelectedToBack() } label: { Label("置于底层", systemImage: "square.stack.3d.down") }
                                        .disabled(!controller.hasSelection)
                                    Button { controller.bringSelectedForward() } label: { Label("上移一层", systemImage: "arrow.up") }
                                        .disabled(!controller.hasSelection)
                                    Button { controller.sendSelectedBackward() } label: { Label("下移一层", systemImage: "arrow.down") }
                                        .disabled(!controller.hasSelection)
                                }
                                Section("变换") {
                                    Button { controller.resetRotationCenter() } label: { Label("旋转中心回到选区中心", systemImage: "scope") }
                                        .disabled(!controller.hasSelection)
                                    Button { controller.reflectHorizontal() } label: { Label("水平翻转", systemImage: "arrow.left.and.right") }
                                        .disabled(!controller.hasSelection || controller.selectedObjectsAreAllLocked)
                                    Button { controller.reflectVertical() } label: { Label("垂直翻转", systemImage: "arrow.up.and.down") }
                                        .disabled(!controller.hasSelection || controller.selectedObjectsAreAllLocked)
                                }
                            }
                        toolbar(in: proxy.size)
                            .frame(maxWidth:toolbarIsVertical ? 96 : .infinity, maxHeight:toolbarIsVertical ? .infinity : 76)
                            .background(.regularMaterial)
                            .clipShape(RoundedRectangle(cornerRadius:12, style:.continuous))
                            .overlay { RoundedRectangle(cornerRadius:12, style:.continuous).stroke(.quaternary, lineWidth:1) }
                            .offset(toolbarDragOffset)
                            .padding(8)
                            .frame(maxWidth:.infinity, maxHeight:.infinity, alignment:toolbarAlignment)
                        FavoriteDockHost(presetID: $presetID, tool: $tool)
                            .frame(maxWidth: .infinity, maxHeight: .infinity)
                    }
                }
            }
            .onAppear {
                ShortcutEventMonitor.shared.start()
                if let raw=UserDefaults.standard.string(forKey:"mosuan.toolbarDock"), let saved=ToolbarDock(rawValue:raw) { toolbarDock=saved }
                if let raw=UserDefaults.standard.string(forKey:"mosuan.eyeComfortBackground"), let saved=EyeComfortBackground(rawValue:raw) { eyeComfortBackground=saved }
                if let saved=UserDefaults.standard.string(forKey:"mosuan.customHex") { customHex=saved }
                if let raw=UserDefaults.standard.string(forKey:"mosuan.interfaceTheme"), let saved=BoardInterfaceTheme(rawValue:raw) { interfaceTheme=saved }
            }
            .onDisappear { ShortcutEventMonitor.shared.stop() }
            .onReceive(NotificationCenter.default.publisher(for: .mosuanShortcutAction)) { note in
                guard let action = note.object as? String else { return }
                switch action {
                case "select": tool = .select
                case "pen": tool = .pen
                case "line": tool = .line
                case "smartLine": tool = .smartLine
                case "eraser": tool = .eraser
                case "hand": tool = .hand
                case "undo": controller.undo()
                case "redo": controller.redo()
                case "nextColor":
                    let presets = PenPreset.defaults
                    guard let index = presets.firstIndex(where: { $0.id == presetID }) else { return }
                    presetID = presets[(index + 1) % presets.count].id
                    tool = .pen
                default: break
                }
            }
            .onChange(of:toolbarDock) { _,v in UserDefaults.standard.set(v.rawValue, forKey:"mosuan.toolbarDock") }
            .onChange(of:eyeComfortBackground) { _,v in UserDefaults.standard.set(v.rawValue, forKey:"mosuan.eyeComfortBackground") }
            .onChange(of:customHex) { _,v in UserDefaults.standard.set(v, forKey:"mosuan.customHex") }
            .onChange(of:interfaceTheme) { _,v in UserDefaults.standard.set(v.rawValue, forKey:"mosuan.interfaceTheme") }
        }
        .frame(minWidth:1100,minHeight:700)
        .preferredColorScheme(interfaceTheme.colorScheme)
        .tint(interfaceTheme.accent)
    }

    private var toolbarAlignment:Alignment { switch toolbarDock { case .top:.top; case .bottom:.bottom; case .left:.leading; case .right:.trailing } }
    private func finishToolbarDrag(_ translation: CGSize, in size: CGSize) {
        let distance = hypot(translation.width, translation.height); guard distance > 60 else { return }
        let x = size.width * 0.5 + translation.width; let y = size.height * 0.5 + translation.height
        let candidates: [(ToolbarDock, CGFloat)] = [(.left, abs(x)), (.right, abs(size.width - x)), (.top, abs(y)), (.bottom, abs(size.height - y))]
        if let nearest = candidates.min(by: { $0.1 < $1.1 })?.0 { toolbarDock = nearest }
    }
    @ViewBuilder private func toolbar(in size:CGSize)->some View {
        if toolbarIsVertical { VStack(spacing:8) { toolbarDragHandle; toolbarContents }.padding(8) }
        else { HStack(spacing:10) { toolbarDragHandle; toolbarContents }.padding(.horizontal,12).padding(.vertical,8) }
    }
    @ViewBuilder private var toolbarContents:some View {
        ToolButton(title:"选区",systemImage:"lasso",selected:tool == .select) { tool = .select }
        ToolButton(title:"抓手",systemImage:"hand.draw",selected:tool == .hand) { tool = .hand }
        ToolButton(title:"画笔",systemImage:"pencil.tip",selected:tool == .pen) { tool = .pen }
        ToolButton(title:"直线",systemImage:"line.diagonal",selected:tool == .line) { tool = .line }
        ToolButton(title:"智能直线",systemImage:"scribble.variable",selected:tool == .smartLine) { tool = .smartLine }
        ToolButton(title:"一笔成型",systemImage:"wand.and.stars",selected:tool == .oneStroke) { tool = .oneStroke }
        ToolButton(title:"多边形",systemImage:"triangle",selected:tool == .polygon) { tool = .polygon }
        ToolButton(title:"橡皮",systemImage:"eraser",selected:tool == .eraser) { tool = .eraser }
        ForEach(PenPreset.defaults) { item in Button { presetID=item.id; tool = .pen } label: { Circle().fill(Color(red:item.style.color.red, green:item.style.color.green, blue:item.style.color.blue)).frame(width:18,height:18) }.buttonStyle(.plain).help(item.name) }
        Button { NotificationCenter.default.post(name: .mosuanAddFavoritePen, object: presetID) } label: { Image(systemName: "heart.fill").font(.system(size: 15, weight: .semibold)).frame(width: 30, height: 30) }.buttonStyle(.plain).foregroundStyle(interfaceTheme.accent).help("收藏当前笔")
        Menu { ForEach(BoardBackground.allCases) { item in Button(item.title) { background=item; if item != .white { inverted=false } } } } label: { Label("背景",systemImage:"rectangle.fill") }.menuStyle(.borderlessButton)
        if background == .white {
            Toggle("反色",isOn:$inverted).toggleStyle(.checkbox)
            if inverted { Menu { ForEach(EyeComfortBackground.allCases) { item in Button(item.title) { eyeComfortBackground=item } } } label: { Label(eyeComfortBackground.title,systemImage:"moon.fill") }.menuStyle(.borderlessButton) }
        }
        Menu { ForEach(BoardInterfaceTheme.allCases) { item in Button { interfaceTheme=item } label: { Label(item.title,systemImage:item.systemImage) } } } label: { Label(interfaceTheme.title,systemImage:interfaceTheme.systemImage) }.menuStyle(.borderlessButton)
        if controller.hasSelection { TextField("角度",text:Binding(get:{rotationText},set:{rotationText=$0})).frame(width:58).textFieldStyle(.roundedBorder).onSubmit { if let d=Double(rotationText) { controller.setRotationDegrees(d) } }; Text("°") }
        Button { controller.deleteSelected() } label: { Label("删除",systemImage:"trash") }.disabled(!controller.hasSelection || controller.selectedObjectsAreAnyLocked)
        Menu {
            Section("对象") {
                Button { controller.toggleSelectedLock() } label: { Label(controller.selectedObjectsAreAllLocked ? "解锁对象" : "锁定对象", systemImage: controller.selectedObjectsAreAllLocked ? "lock.open" : "lock") }.disabled(!controller.hasSelection)
                Button { controller.bringSelectedToFront() } label: { Label("置于顶层", systemImage:"square.stack.3d.up") }.disabled(!controller.hasSelection)
                Button { controller.sendSelectedToBack() } label: { Label("置于底层", systemImage:"square.stack.3d.down") }.disabled(!controller.hasSelection)
                Button { controller.bringSelectedForward() } label: { Label("上移一层", systemImage:"arrow.up") }.disabled(!controller.hasSelection)
                Button { controller.sendSelectedBackward() } label: { Label("下移一层", systemImage:"arrow.down") }.disabled(!controller.hasSelection)
            }
            Section("变换") {
                Button { controller.resetRotationCenter() } label: { Label("旋转中心回到选区中心", systemImage:"scope") }.disabled(!controller.hasSelection)
                Button { controller.reflectHorizontal() } label: { Label("水平翻转", systemImage:"arrow.left.and.right") }.disabled(!controller.hasSelection || controller.selectedObjectsAreAnyLocked)
                Button { controller.reflectVertical() } label: { Label("垂直翻转", systemImage:"arrow.up.and.down") }.disabled(!controller.hasSelection || controller.selectedObjectsAreAnyLocked)
            }
        } label: { Label("更多", systemImage:"ellipsis.circle") }.menuStyle(.borderlessButton)
        Button { controller.undo() } label: { Label("撤销",systemImage:"arrow.uturn.backward") }.disabled(!controller.canUndo)
        Button { controller.redo() } label: { Label("重做",systemImage:"arrow.uturn.forward") }.disabled(!controller.canRedo)
        Text("\(zoomPercent)%").font(.caption).monospacedDigit()
    }
    private var toolbarDragHandle: some View {
        Image(systemName:"circle.grid.2x2").font(.system(size:14, weight:.semibold)).foregroundStyle(.secondary).frame(width:28, height:28).contentShape(Rectangle()).help("拖动工具栏到上、下、左、右")
            .gesture(DragGesture(minimumDistance:3).onChanged { value in toolbarDragOffset = value.translation }.onEnded { value in finishToolbarDrag(value.translation, in: NSScreen.main?.visibleFrame.size ?? CGSize(width:1100,height:700)); toolbarDragOffset = .zero })
    }
}