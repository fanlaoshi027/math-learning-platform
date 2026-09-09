import SwiftUI

struct DynamicIsoscelesTriangleParameterPanel: View {
    @Binding var degrees: Double
    var onValueChanged: (() -> Void)?

    @State private var isPlaying = false
    @State private var direction = 1.0
    private let timer = Timer.publish(every: 1.0 / 30.0, on: .main, in: .common).autoconnect()

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack {
                Text("∠A")
                    .font(.system(size: 17, weight: .semibold))
                Spacer()
                Text("\(Int(degrees.rounded()))°")
                    .font(.system(size: 18, weight: .medium, design: .rounded))
                    .monospacedDigit()
            }

            Slider(value: $degrees, in: 30...150, step: 1) {
                Text("顶角")
            } onEditingChanged: { editing in
                if !editing { onValueChanged?() }
            }

            HStack {
                Text("30°").font(.caption).foregroundStyle(.secondary)
                Spacer()
                Text("150°").font(.caption).foregroundStyle(.secondary)
            }

            HStack(spacing: 8) {
                Button {
                    isPlaying.toggle()
                } label: {
                    Label(isPlaying ? "暂停" : "播放", systemImage: isPlaying ? "pause.fill" : "play.fill")
                }
                .buttonStyle(.borderedProminent)

                Button("回到 60°") {
                    isPlaying = false
                    direction = 1
                    degrees = 60
                    onValueChanged?()
                }
                .buttonStyle(.bordered)
            }
        }
        .padding(14)
        .frame(minWidth: 260)
        .background(.regularMaterial, in: RoundedRectangle(cornerRadius: 14, style: .continuous))
        .overlay {
            RoundedRectangle(cornerRadius: 14, style: .continuous)
                .stroke(.quaternary, lineWidth: 1)
        }
        .onReceive(timer) { _ in
            guard isPlaying else { return }
            var next = degrees + direction
            if next >= 150 {
                next = 150
                direction = -1
            } else if next <= 30 {
                next = 30
                direction = 1
            }
            degrees = next
            onValueChanged?()
        }
        .onDisappear {
            isPlaying = false
        }
    }
}
