import SwiftUI

struct BoardView: View {
    @State private var selectedTool: BoardTool = .pen
    @State private var selectedPresetID = PenPreset.defaults[0].id

    private var selectedPreset: PenPreset {
        PenPreset.defaults.first(where: { $0.id == selectedPresetID }) ?? PenPreset.defaults[0]
    }

    var body: some View {
        VStack(spacing: 0) {
            ToolbarView(selectedTool: $selectedTool, selectedPresetID: $selectedPresetID)
            Divider()
            ZStack {
                Color(nsColor: .windowBackgroundColor)
                MetalInkCanvas(tool: $selectedTool, penStyle: selectedPreset.style, controller: CanvasController())
                    .background(.white)
                    .clipShape(Rectangle())
                    .padding(24)
            }
        }
    }
}

enum BoardTool: Equatable { case select, pen, eraser }

struct MetalInkCanvas: NSViewRepresentable {
    @Binding var tool: BoardTool
    let penStyle: PenStyle
    @ObservedObject var controller: CanvasController

    func makeNSView(context: Context) -> InkMetalView {
        let view = InkMetalView()
        view.isUserInteractionEnabledForTool = tool == .pen
        view.penStyle = penStyle
        controller.attach(view)
        return view
    }

    func updateNSView(_ nsView: InkMetalView, context: Context) {
        nsView.isUserInteractionEnabledForTool = tool == .pen
        nsView.penStyle = penStyle
        controller.attach(nsView)
    }
}

struct ToolbarView: View {
    @Binding var selectedTool: BoardTool
    @Binding var selectedPresetID: UUID

    var body: some View {
        HStack(spacing: 10) {
            Text("Mosuan Board").font(.headline).padding(.horizontal, 8)
            Divider().frame(height: 24)
            ToolButton(title: "选择", systemImage: "cursorarrow", selected: selectedTool == .select) { selectedTool = .select }
            ToolButton(title: "画笔", systemImage: "pencil.tip", selected: selectedTool == .pen) { selectedTool = .pen }
            ToolButton(title: "橡皮", systemImage: "eraser", selected: selectedTool == .eraser) { selectedTool = .eraser }
            Divider().frame(height: 24)
            PenTray(selectedPresetID: $selectedPresetID)
            Spacer()
            Button("撤销") { }.keyboardShortcut("z", modifiers: .command).disabled(true)
            Button("重做") { }.disabled(true)
        }
        .padding(.horizontal, 14)
        .frame(height: 52)
    }
}

struct PenTray: View {
    @Binding var selectedPresetID: UUID

    var body: some View {
        HStack(spacing: 5) {
            ForEach(PenPreset.defaults) { preset in
                Button { selectedPresetID = preset.id } label: {
                    VStack(spacing: 2) {
                        Circle()
                            .fill(Color(red: preset.style.color.red, green: preset.style.color.green, blue: preset.style.color.blue))
                            .frame(width: 19, height: 19)
                            .overlay { Circle().stroke(selectedPresetID == preset.id ? Color.accentColor : .clear, lineWidth: 2) }
                        Text(preset.name).font(.system(size: 9)).lineLimit(1)
                    }
                    .frame(width: 52, height: 39)
                }
                .buttonStyle(.plain)
                .help(preset.name)
            }
        }
    }
}

struct ToolButton: View {
    let title: String
    let systemImage: String
    let selected: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            Label(title, systemImage: systemImage).padding(.horizontal, 8)
        }
        .buttonStyle(.bordered)
        .tint(selected ? .accentColor : .secondary)
    }
}
