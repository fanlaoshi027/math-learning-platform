import SwiftUI

struct DynamicIsoscelesTriangleParameterPanel: View {
    @Binding var degrees: Double
    var onValueChanged: (() -> Void)?
    var onPlaybackChanged: ((Bool) -> Void)?

    @State private var isPlaying = false
    @State private var direction = 1.0
    private let timer = Timer.publish(every: 1.0 / 30.0, on: .main, in: .common).autoconnect()

    private var apexAngle: Int {
        Int(degrees.rounded())
    }

    private var baseAngle: Int {
        Int(((180 - degrees) / 2).rounded())
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack(alignment: .firstTextBaseline) {
                Text("等腰三角形")
                    .font(.system(size: 17, weight: .semibold))
                Spacer()
                Text("∠A = \(apexAngle)°")
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

            HStack(spacing: 10) {
                Text("AB = AC")
                    .font(.system(size: 14, weight: .medium))
                Text("∠B = ∠C = \(baseAngle)°")
                    .font(.system(size: 14, weight: .regular, design: .rounded))
                    .foregroundStyle(.secondary)
                    .monospacedDigit()
            }

            HStack(spacing: 8) {
                Button {
                    let next = !isPlaying
                    onPlaybackChanged?(next)
                    isPlaying = next
                } label: {
                    Label(isPlaying ? "暂停" : "播放", systemImage: isPlaying ? "pause.fill" : "play.fill")
                }
                .buttonStyle(.borderedProminent)

                Button("回到 60°") {
                    if isPlaying {
                        isPlaying = false
                        onPlaybackChanged?(false)
                    }
                    direction = 1
                    degrees = 60
                    onValueChanged?()
                }
                .buttonStyle(.bordered)
            }
        }
        .padding(14)
        .frame(minWidth: 280)
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
            if isPlaying {
                isPlaying = false
                onPlaybackChanged?(false)
            }
        }
    }
}
