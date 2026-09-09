import SwiftUI

/// Compact control surface for the currently selected dynamic angle.
/// The canvas owns the geometry; this view only edits the shared parameter.
struct DynamicAngleParameterPanel: View {
    @Binding var degrees: Double
    var minimum: Double = 10
    var maximum: Double = 170
    var step: Double = 1
    var name: String = "α"
    var onPlayPause: (() -> Void)?
    var isPlaying: Bool = false

    private var normalizedStep: Double { max(step, 0.1) }

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            HStack(spacing: 8) {
                Text(name)
                    .font(.system(size: 22, weight: .semibold, design: .rounded))
                Text("动态角")
                    .font(.system(size: 13, weight: .medium))
                    .foregroundStyle(.secondary)
                Spacer()
                Text("\(degrees, specifier: "%.0f")°")
                    .font(.system(size: 18, weight: .semibold, design: .rounded))
                    .monospacedDigit()
            }

            Slider(value: $degrees, in: minimum...maximum, step: normalizedStep)

            HStack(spacing: 8) {
                Text("范围")
                    .foregroundStyle(.secondary)
                Text("\(minimum, specifier: "%.0f")°")
                Text("—")
                    .foregroundStyle(.secondary)
                Text("\(maximum, specifier: "%.0f")°")
                Spacer()
                if let onPlayPause {
                    Button(action: onPlayPause) {
                        Label(isPlaying ? "暂停" : "播放", systemImage: isPlaying ? "pause.fill" : "play.fill")
                    }
                    .buttonStyle(.bordered)
                }
            }
            .font(.system(size: 12))
        }
        .padding(14)
        .background(.regularMaterial, in: RoundedRectangle(cornerRadius: 14))
    }
}
