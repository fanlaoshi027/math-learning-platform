import AppKit
import SwiftUI

struct PenCanvas: NSViewRepresentable {
    @Binding var tool: BoardTool

    func makeNSView(context: Context) -> PenCanvasNSView {
        let view = PenCanvasNSView()
        view.tool = tool
        return view
    }

    func updateNSView(_ nsView: PenCanvasNSView, context: Context) {
        nsView.tool = tool
    }
}

final class PenCanvasNSView: NSView {
    var tool: BoardTool = .pen {
        didSet { needsDisplay = true }
    }

    private var strokes: [[PenPoint]] = []
    private var currentStroke: [PenPoint] = []

    override var isFlipped: Bool { true }

    override func acceptsFirstMouse(for event: NSEvent?) -> Bool { true }
    override var acceptsFirstResponder: Bool { true }

    override func mouseDown(with event: NSEvent) {
        guard tool == .pen else { return }
        window?.makeFirstResponder(self)
        currentStroke = [point(from: event)]
        needsDisplay = true
    }

    override func mouseDragged(with event: NSEvent) {
        guard tool == .pen else { return }
        currentStroke.append(point(from: event))
        needsDisplay = true
    }

    override func mouseUp(with event: NSEvent) {
        guard tool == .pen else { return }
        currentStroke.append(point(from: event))
        if !currentStroke.isEmpty {
            strokes.append(currentStroke)
        }
        currentStroke.removeAll(keepingCapacity: true)
        needsDisplay = true
    }

    override func draw(_ dirtyRect: NSRect) {
        super.draw(dirtyRect)
        NSColor.white.setFill()
        dirtyRect.fill()

        for stroke in strokes {
            draw(stroke)
        }
        draw(currentStroke)
    }

    private func draw(_ stroke: [PenPoint]) {
        guard let first = stroke.first else { return }
        let path = NSBezierPath()
        path.move(to: first.location)

        for point in stroke.dropFirst() {
            path.line(to: point.location)
        }

        path.lineCapStyle = .round
        path.lineJoinStyle = .round

        let width = max(1.5, 1.5 + (first.pressure * 3.0))
        path.lineWidth = width
        NSColor.labelColor.setStroke()
        path.stroke()
    }

    private func point(from event: NSEvent) -> PenPoint {
        let location = convert(event.locationInWindow, from: nil)
        let pressure = event.pressure > 0 ? event.pressure : 1.0

        return PenPoint(
            location: location,
            pressure: pressure,
            timestamp: event.timestamp
        )
    }
}

struct PenPoint {
    let location: CGPoint
    let pressure: CGFloat
    let timestamp: TimeInterval
}
