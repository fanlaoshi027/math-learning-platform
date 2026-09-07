import SwiftUI

struct BoardView: View {
    @State private var selectedTool: BoardTool = .pen
    @State private var strokes: [Stroke] = []
    @State private var currentStroke: Stroke?

    var body: some View {
        VStack(spacing: 0) {
            ToolbarView(selectedTool: $selectedTool)

            Divider()

            ZStack {
                Color(nsColor: .windowBackgroundColor)

                Canvas { context, size in
                    for stroke in strokes {
                        draw(stroke, in: &context)
                    }
                    if let currentStroke {
                        draw(currentStroke, in: &context)
                    }
                }
                .gesture(drawingGesture)
                .background(Color.white)
                .clipShape(Rectangle())
                .padding(24)
            }
        }
    }

    private var drawingGesture: some Gesture {
        DragGesture(minimumDistance: 0)
            .onChanged { value in
                guard selectedTool == .pen else { return }
                let point = StrokePoint(
                    location: value.location,
                    pressure: 1.0,
                    timestamp: Date().timeIntervalSinceReferenceDate
                )

                if currentStroke == nil {
                    currentStroke = Stroke(points: [point])
                } else {
                    currentStroke?.points.append(point)
                }
            }
            .onEnded { _ in
                guard let currentStroke else { return }
                strokes.append(currentStroke)
                self.currentStroke = nil
            }
    }

    private func draw(_ stroke: Stroke, in context: inout GraphicsContext) {
        guard let first = stroke.points.first else { return }

        var path = Path()
        path.move(to: first.location)
        for point in stroke.points.dropFirst() {
            path.addLine(to: point.location)
        }

        context.stroke(
            path,
            with: .color(.black),
            style: StrokeStyle(lineWidth: 3, lineCap: .round, lineJoin: .round)
        )
    }
}

enum BoardTool {
    case select
    case pen
    case eraser
}

struct StrokePoint: Identifiable {
    let id = UUID()
    let location: CGPoint
    let pressure: CGFloat
    let timestamp: TimeInterval
}

struct Stroke: Identifiable {
    let id = UUID()
    var points: [StrokePoint]
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
