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
    private var inkImage: NSImage?
    private var inkImageSize: NSSize = .zero

    override var isFlipped: Bool { true }
    override func acceptsFirstMouse(for event: NSEvent?) -> Bool { true }
    override var acceptsFirstResponder: Bool { true }

    override func viewDidMoveToWindow() {
        super.viewDidMoveToWindow()
        rebuildInkCache()
    }

    override func setFrameSize(_ newSize: NSSize) {
        super.setFrameSize(newSize)
        if newSize != inkImageSize {
            rebuildInkCache()
        }
    }

    override func mouseDown(with event: NSEvent) {
        guard tool == .pen else { return }
        window?.makeFirstResponder(self)
        currentStroke = [point(from: event)]
        needsDisplay = true
    }

    override func mouseDragged(with event: NSEvent) {
        guard tool == .pen, !currentStroke.isEmpty else { return }
        let next = point(from: event)
        currentStroke.append(next)
        drawCurrentStrokeIntoCache()
        needsDisplay = true
    }

    override func mouseUp(with event: NSEvent) {
        guard tool == .pen, !currentStroke.isEmpty else { return }
        currentStroke.append(point(from: event))
        strokes.append(currentStroke)
        drawCurrentStrokeIntoCache()
        currentStroke.removeAll(keepingCapacity: true)
        needsDisplay = true
    }

    override func tabletPoint(with event: NSEvent) {
        switch event.phase {
        case .began:
            mouseDown(with: event)
        case .changed:
            mouseDragged(with: event)
        case .ended, .cancelled:
            mouseUp(with: event)
        default:
            break
        }
    }

    override func draw(_ dirtyRect: NSRect) {
        super.draw(dirtyRect)
        NSColor.white.setFill()
        dirtyRect.fill()

        guard let inkImage else { return }
        inkImage.draw(
            in: bounds,
            from: NSRect(origin: .zero, size: inkImageSize),
            operation: .sourceOver,
            fraction: 1
        )
    }

    private func rebuildInkCache() {
        inkImageSize = bounds.size
        guard inkImageSize.width > 0, inkImageSize.height > 0 else {
            inkImage = nil
            return
        }

        let image = NSImage(size: inkImageSize)
        image.lockFocusFlipped(true)
        NSColor.clear.setFill()
        NSRect(origin: .zero, size: inkImageSize).fill()
        for stroke in strokes {
            draw(stroke, in: imageSizeRect)
        }
        image.unlockFocus()
        inkImage = image
    }

    private var imageSizeRect: NSRect {
        NSRect(origin: .zero, size: inkImageSize)
    }

    private func drawCurrentStrokeIntoCache() {
        guard !currentStroke.isEmpty,
              inkImageSize.width > 0,
              inkImageSize.height > 0 else { return }

        if inkImage == nil {
            rebuildInkCache()
        }

        guard let inkImage else { return }
        inkImage.lockFocusFlipped(true)
        draw(currentStroke, in: imageSizeRect)
        inkImage.unlockFocus()
    }

    private func draw(_ stroke: [PenPoint], in rect: NSRect) {
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
        NSColor.black.setStroke()
        path.stroke()
    }

    private func point(from event: NSEvent) -> PenPoint {
        let location = convert(event.locationInWindow, from: nil)
        let pressure = event.type == .tabletPoint
            ? max(0, min(1, event.pressure))
            : 1.0

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
