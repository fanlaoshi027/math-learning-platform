import SwiftUI
import CoreGraphics

/// Temporary interactive surface for validating the dynamic-angle teaching object.
/// The data model already lives in Core/Geometry; this view validates the teacher-facing
/// interaction before the object is moved into the Metal renderer's persistent object path.
struct DynamicAngleOverlay: View {
    @State private var angle: CGFloat = 45
    @State private var center = CGPoint(x: 520, y: 340)
    @State private var radius: CGFloat = 180
    @State private var playing = false
    @State private var timer: Timer?
    @State private var direction: CGFloat = 1

    var body: some View {
        ZStack(alignment: .bottomTrailing) {
            GeometryReader { proxy in
                DynamicAngleDrawing(center: center, radius: radius, degrees: angle)
                    .contentShape(Rectangle())
                    .gesture(endpointDrag)
                    .onAppear {
                        if center == CGPoint(x: 520, y: 340) {
                            center = CGPoint(x: proxy.size.width * 0.5, y: proxy.size.height * 0.5)
                        }
                    }
            }
            .allowsHitTesting(true)

            VStack(alignment: .leading, spacing: 10) {
                HStack {
                    Text("α").font(.system(size: 22, weight: .semibold, design: .rounded))
                    Text("动态角").foregroundStyle(.secondary)
                    Spacer()
                    Text("\(Int(angle.rounded()))°").monospacedDigit().fontWeight(.semibold)
                }
                Slider(value: $angle, in: 10...170, step: 1)
                HStack {
                    Text("拖动橙色端点").font(.caption).foregroundStyle(.secondary)
                    Spacer()
                    Button(playing ? "暂停" : "播放") { togglePlay() }
                    Button("重置") { angle = 45 }
                }
            }
            .padding(14)
            .frame(width: 300)
            .background(.regularMaterial, in: RoundedRectangle(cornerRadius: 14))
            .shadow(radius: 8, y: 3)
            .padding(18)
            .allowsHitTesting(true)
        }
        .onDisappear { stopTimer() }
    }

    private var endpointDrag: some Gesture {
        DragGesture(minimumDistance: 2)
            .onChanged { value in
                let dx = value.location.x - center.x
                let dy = value.location.y - center.y
                guard hypot(dx, dy) > 10 else { return }
                let raw = atan2(dy, dx) * 180 / .pi
                let normalized = raw < 0 ? raw + 360 : raw
                let acuteOrReflex = normalized <= 180 ? normalized : 360 - normalized
                angle = min(170, max(10, acuteOrReflex))
            }
    }

    private func togglePlay() {
        if playing { stopTimer(); return }
        playing = true
        timer = Timer.scheduledTimer(withTimeInterval: 1.0 / 30.0, repeats: true) { _ in
            angle += direction * 1.2
            if angle >= 170 { angle = 170; direction = -1 }
            if angle <= 10 { angle = 10; direction = 1 }
        }
    }

    private func stopTimer() {
        timer?.invalidate()
        timer = nil
        playing = false
    }
}

private struct DynamicAngleDrawing: View {
    let center: CGPoint
    let radius: CGFloat
    let degrees: CGFloat

    var body: some View {
        Canvas { context, _ in
            let start = CGPoint(x: center.x + radius, y: center.y)
            let radians = degrees * .pi / 180
            let end = CGPoint(x: center.x + radius * cos(radians), y: center.y + radius * sin(radians))

            var first = Path()
            first.move(to: center)
            first.addLine(to: start)
            context.stroke(first, with: .color(.accentColor), lineWidth: 3)

            var second = Path()
            second.move(to: center)
            second.addLine(to: end)
            context.stroke(second, with: .color(.accentColor), lineWidth: 3)

            var arc = Path()
            arc.addArc(center: center, radius: radius * 0.32, startAngle: .degrees(0), endAngle: .degrees(Double(degrees)), clockwise: false)
            context.stroke(arc, with: .color(.orange), lineWidth: 3)

            context.fill(Path(ellipseIn: CGRect(x: end.x - 9, y: end.y - 9, width: 18, height: 18)), with: .color(.orange))
            context.fill(Path(ellipseIn: CGRect(x: center.x - 5, y: center.y - 5, width: 10, height: 10)), with: .color(.accentColor))
            context.draw(Text("α = \(Int(degrees.rounded()))°").font(.system(size: 16, weight: .semibold)), at: CGPoint(x: center.x + radius * 0.38, y: center.y - 18))
        }
        .allowsHitTesting(true)
    }
}
