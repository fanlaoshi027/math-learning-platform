import SwiftUI

enum BoardTool: Equatable { case select, pen, line, smartLine, eraser }

enum BoardBackground: String, CaseIterable, Identifiable {
    case white, black, darkGray, lightGray, cream
    var id: String { rawValue }
    var title: String { switch self { case .white: "白色"; case .black: "黑色"; case .darkGray: "深灰"; case .lightGray: "浅灰"; case .cream: "米白" } }
    var color: Color { switch self { case .white: .white; case .black: .black; case .darkGray: Color(white: 0.18); case .lightGray: Color(white: 0.92); case .cream: Color(red: 0.98, green: 0.96, blue: 0.88) } }
    var metal: SIMD4<Float> { switch self { case .white: SIMD4(1,1,1,1); case .black: SIMD4(0,0,0,1); case .darkGray: SIMD4(0.18,0.18,0.18,1); case .lightGray: SIMD4(0.92,0.92,0.92,1); case .cream: SIMD4(0.98,0.96,0.88,1) } }
}

enum BoardPattern: Int, CaseIterable, Identifiable {
    case blank = 0, ruled = 1, grid = 2, dots = 3, mathGrid = 4
    var id: Int { rawValue }
    var title: String { switch self { case .blank: "空白"; case .ruled: "横线"; case .grid: "方格"; case .dots: "点阵"; case .mathGrid: "数学方格" } }
}

enum EyeComfortBackground: String, CaseIterable, Identifiable {
    case black90, deepGreen, deepBlue, custom
    var id: String { rawValue }
    var title: String { switch self { case .black90: "90%黑"; case .deepGreen: "深绿"; case .deepBlue: "深蓝"; case .custom: "自定义色值" } }
    var color: SIMD4<Float> { switch self { case .black90: SIMD4(0.10,0.10,0.10,1); case .deepGreen: SIMD4(0.08,0.16,0.12,1); case .deepBlue: SIMD4(0.07,0.12,0.22,1); case .custom: SIMD4(0.10,0.10,0.10,1) } }
}

struct BoardView: View {
    @State private var selectedTool: BoardTool = .pen
    @State private var selectedPresetID = PenPreset.defaults[0].id
    @StateObject private var controller = CanvasController()
    var body: some View {
        VStack(spacing: 0) {
            ToolbarView(selectedTool: $selectedTool, selectedPresetID: $selectedPresetID)
            Divider()
            ZStack { Color(nsColor: .windowBackgroundColor); MetalInkCanvas(tool: $selectedTool, penStyle: PenPreset.defaults.first(where: {$0.id == selectedPresetID})?.style ?? PenPreset.defaults[0].style, controller: controller).background(.white).padding(24) }
        }
    }
}

struct MetalInkCanvas: NSViewRepresentable {
    @Binding var tool: BoardTool
    let penStyle: PenStyle
    @ObservedObject var controller: CanvasController
    var background: SIMD4<Float> = SIMD4(1,1,1,1)
    var inverted: Bool = false
    var pattern: BoardPattern = .blank
    func makeNSView(context: Context) -> InkMetalView { let view = InkMetalView(); configure(view); controller.attach(view); return view }
    func updateNSView(_ nsView: InkMetalView, context: Context) { configure(nsView); controller.attach(nsView) }
    private func configure(_ view: InkMetalView) {
        view.isUserInteractionEnabledForTool = tool == .pen || tool == .line || tool == .smartLine
        view.isSelectionTool = tool == .select; view.isLineTool = tool == .line; view.isSmartLineTool = tool == .smartLine; view.isEraserTool = tool == .eraser
        view.penStyle = penStyle; view.boardBackground = background; view.displayInverted = inverted; view.backgroundPattern = pattern.rawValue
    }
}

struct ToolbarView: View {
    @Binding var selectedTool: BoardTool
    @Binding var selectedPresetID: UUID
    var body: some View {
        HStack(spacing: 10) {
            Text("Mosuan Board").font(.headline).padding(.horizontal, 8); Divider().frame(height: 24)
            ToolButton(title: "选择", systemImage: "cursorarrow", selected: selectedTool == .select) { selectedTool = .select }
            ToolButton(title: "画笔", systemImage: "pencil.tip", selected: selectedTool == .pen) { selectedTool = .pen }
            ToolButton(title: "直线", systemImage: "line.diagonal", selected: selectedTool == .line) { selectedTool = .line }
            ToolButton(title: "智能直线", systemImage: "scribble.variable", selected: selectedTool == .smartLine) { selectedTool = .smartLine }
            ToolButton(title: "橡皮", systemImage: "eraser", selected: selectedTool == .eraser) { selectedTool = .eraser }
            Divider().frame(height: 24); PenTray(selectedPresetID: $selectedPresetID); Spacer()
        }.padding(.horizontal, 14).frame(height: 52)
    }
}

struct PenTray: View {
    @Binding var selectedPresetID: UUID
    var body: some View { HStack(spacing: 5) { ForEach(PenPreset.defaults) { preset in Button { selectedPresetID = preset.id } label: { VStack(spacing: 2) { Circle().fill(Color(red: preset.style.color.red, green: preset.style.color.green, blue: preset.style.color.blue)).frame(width: 19, height: 19).overlay { Circle().stroke(selectedPresetID == preset.id ? Color.accentColor : .clear, lineWidth: 2) }; Text(preset.name).font(.system(size: 9)).lineLimit(1) }.frame(width: 52, height: 39) }.buttonStyle(.plain).help(preset.name) } } }

struct ToolButton: View {
    let title: String; let systemImage: String; let selected: Bool; let action: () -> Void
    var body: some View { Button(action: action) { Label(title, systemImage: systemImage).padding(.horizontal, 8) }.buttonStyle(.bordered).tint(selected ? .accentColor : .secondary) }
}
