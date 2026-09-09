import SwiftUI
import CoreGraphics

/// First interactive macOS teaching surface for the dynamic-angle system.
/// It sits above the existing Metal board so the current ink/selection implementation
/// remains untouched while the geometry interaction is validated.
struct DynamicAngleOverlay: View {
    @State private var active = false
    @State private var angle: CGFloat = 45
    @State private var center = CGPoint(x: 520, y: 340)
    @State private var radius: CGFloat = 180
    @State private var playing = false
    @State private var showPanel = true
    @State private var timer: Timer?
    @State private var direction: CGFloat = 1

    var body: some View {
        ZStack(alignment: .topTrailing) {
            if active {
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

                if showPanel {
                    VStack(alignment: .leading, spacing: 10) {
                        HStack {
                            Text("α").font(.system(size: 22, weight: .semibold, design: .rounded))
                            Text("动态角").foregroundStyle(.secondary)
                            Spacer()
                            Text("\(Int(angle.rounded()))°").monospacedDigit().fontWeight(.semibold)
                        }
                        Slider(value: $angle, in: 10...170, step: 1)
                        HStack {
                            Text("10° — 170°").font(.caption).foregroundStyle(.secondary)
                            Spacer()
                            Button(playing ? "暂停" : "播放") { togglePlay() }
                            Button("删除") { stopAndRemove() }
                        }
                    }
                    .padding(14)
                    .frame(width: 270)
                    .background(.regularMaterial, in: RoundedRectangle(cornerRadius: 14))
                    .shadow(radius: 8, y: 3)
                    .padding(18)
                    .frame(maxWidth: .infinity, maxHeight: .infinity, alignment: .bottomTrailing)
                    .allowsHitTesting(true)
                }
            }

            HStack(spacing: 8) {
                Button {
                    active.toggle()
                    if !active { stopTimer() }
                } label: {
                    Label(active ? "退出动态角" : "动态角", systemImage: "angle")
                }
                .buttonStyle(.borderedProminent)

                if active {
                    Button { showPanel.toggle() } label: {
                        Image(systemName: "slider.horizontal.3")
                    }
                    .buttonStyle(.bordered)
                }
            }
            .padding(18)
        }
        .onDisappear { stopTimer() }
    }

    private var endpointDrag: some Gesture {
        DragGesture(minimumDistance: 2)
            .onChanged { value in
                let dx = value.location.x - center.x
                let dy = value.location.y - center.y
                guard hypot(dx, dy) > 10 else { return }
                let degrees = atan2(abs(dy), max(dx, 0.0001)) * 180 / .pi
                if dx < 0 {
                    angle = min(170, max(10, 180 - degrees))
                } else {
                    angle = min(170, max(10, degrees))
                }
            }
    }

    private func togglePlay() {
        if playing {
            stopTimer()
            return
        }
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

    private func stopAndRemove() {
        stopTimer()
        active = false
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

            context.fill(Path(ellipseIn: CGRect(x: end.x - 8, y: end.y - 8, width: 16, height: 16)), with: .color(.orange))
            context.draw(Text("α = \(Int(degrees.rounded()))°").font(.system(size: 16, weight: .semibold)), at: CGPoint(x: center.x + radius * 0.38, y: center.y - 18))
        }
        .allowsHitTesting(true)
    }
}