import SwiftUI

struct BoardView: View {
    @State private var selectedTool: BoardTool = .pen

    var body: some View {
        VStack(spacing: 0) {
            ToolbarView(selectedTool: $selectedTool)
            Divider()

            ZStack {
                Color(nsColor: .windowBackgroundColor)

                MetalInkCanvas(tool: $selectedTool)
                    .background(.white)
                    .clipShape(Rectangle())
                    .padding(24)
            }
        }
    }
}

enum BoardTool: Equatable {
    case select
    case pen
    case eraser
}

struct MetalInkCanvas: NSViewRepresentable {
    @Binding var tool: BoardTool

    func makeNSView(context: Context) -> InkMetalView {
        let view = InkMetalView()
        view.isUserInteractionEnabledForTool = tool == .pen
        return view
    }

    func updateNSView(_ nsView: InkMetalView, context: Context) {
        nsView.isUserInteractionEnabledForTool = tool == .pen
    }
}

struct ToolbarView: View {
    @Binding var selectedTool: BoardTool

    var body: some View {
        HStack(spacing: 10) {
            Text("Mosuan Board")
                .font(.headline)
                .padding(.horizontal, 8)

            Divider().frame(height: 24)

            ToolButton(title: "选择", systemImage: "cursorarrow", selected: selectedTool == .select) {
                selectedTool = .select
            }
            ToolButton(title: "画笔", systemImage: "pencil.tip", selected: selectedTool == .pen) {
                selectedTool = .pen
            }
            ToolButton(title: "橡皮", systemImage: "eraser", selected: selectedTool == .eraser) {
                selectedTool = .eraser
            }

            Spacer()

            Button("撤销") { }
                .keyboardShortcut("z", modifiers: .command)
                .disabled(true)
            Button("重做") { }
                .disabled(true)
        }
        .padding(.horizontal, 14)
        .frame(height: 48)
    }
}

struct ToolButton: View {
    let title: String
    let systemImage: String
    let selected: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            Label(title, systemImage: systemImage)
                .padding(.horizontal, 8)
        }
        .buttonStyle(.bordered)
        .tint(selected ? .accentColor : .secondary)
    }
}
