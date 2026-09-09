import SwiftUI

struct DynamicIsoscelesTriangleParameterPanel: View {
    @Binding var degrees: Double
    var onValueChanged: (() -> Void)?

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
        }
        .padding(14)
        .frame(minWidth: 260)
        .background(.regularMaterial, in: RoundedRectangle(cornerRadius: 14, style: .continuous))
        .overlay {
            RoundedRectangle(cornerRadius: 14, style: .continuous)
                .stroke(.quaternary, lineWidth: 1)
        }
    }
}
