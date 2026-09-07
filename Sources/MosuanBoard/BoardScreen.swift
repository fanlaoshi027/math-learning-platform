import SwiftUI

struct BoardScreen: View {
    @StateObject private var controller = CanvasController()
    @State private var tool: BoardTool = .pen
    @State private var presetID = PenPreset.defaults[0].id
    @State private var rotationText = "0"

    private var preset: PenPreset { PenPreset.defaults.first { $0.id == presetID } ?? PenPreset.defaults[0] }
    private var rotationBinding: Binding<String> {
        Binding(
            get: { rotationText },
            set: { rotationText = $0 }
        )
    }

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
                if controller.hasSelection {
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
        .onChange(of: controller.rotationDegrees) { _, value in
            rotationText = String(format: "%.1f", value)
        }
        .onChange(of: controller.hasSelection) { _, selected in
            if selected { rotationText = String(format: "%.1f", controller.rotationDegrees) }
            else { rotationText = "0" }
        }
    }

    private func applyRotation() {
        guard let value = Double(rotationText) else {
            rotationText = String(format: "%.1f", controller.rotationDegrees)
            return
        }
        controller.setRotationDegrees(value)
    }
}
