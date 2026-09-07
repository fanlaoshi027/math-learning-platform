import SwiftUI

struct BoardScreen: View {
    @StateObject private var controller = CanvasController()
    @State private var tool: BoardTool = .pen
    @State private var presetID = PenPreset.defaults[0].id

    private var preset: PenPreset { PenPreset.defaults.first { $0.id == presetID } ?? PenPreset.defaults[0] }

    var body: some View {
        VStack(spacing: 0) {
            HStack(spacing: 10) {
                Text("Mosuan Board").font(.headline)
                Divider().frame(height: 24)
                ToolButton(title: "选择", systemImage: "cursorarrow", selected: tool == .select) { tool = .select }
                ToolButton(title: "画笔", systemImage: "pencil.tip", selected: tool == .pen) { tool = .pen }
                ToolButton(title: "橡皮", systemImage: "eraser", selected: tool == .eraser) { tool = .eraser }
                Divider().frame(height: 24)
                ForEach(PenPreset.defaults) { item in
                    Button {
                        presetID = item.id; tool = .pen
                    } label: {
                        Circle()
                            .fill(Color(red: item.style.color.red, green: item.style.color.green, blue: item.style.color.blue))
                            .frame(width: 18, height: 18)
                            .overlay { Circle().stroke(presetID == item.id ? Color.accentColor : .clear, lineWidth: 2) }
                    }
                    .buttonStyle(.plain)
                    .help(item.name)
                }
                Spacer()
                Button { controller.deleteSelected() } label: { Label("删除", systemImage: "trash") }
                    .disabled(!controller.hasSelection)
                Button { controller.undo() } label: { Label("撤销", systemImage: "arrow.uturn.backward") }
                    .keyboardShortcut("z", modifiers: .command)
                    .disabled(!controller.canUndo)
                Button { controller.redo() } label: { Label("重做", systemImage: "arrow.uturn.forward") }
                    .keyboardShortcut("z", modifiers: [.command, .shift])
                    .disabled(!controller.canRedo)
            }
            .padding(.horizontal, 14)
            .frame(height: 52)
            Divider()
            MetalInkCanvas(tool: $tool, penStyle: preset.style, controller: controller)
                .background(.white)
                .padding(24)
        }
    }
}
