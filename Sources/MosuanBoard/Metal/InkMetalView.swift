import AppKit
import MetalKit

final class InkMetalView: MTKView {
    private let renderer: InkRenderer
    private var points: [InkPoint] = []
    private var active = false
    var isUserInteractionEnabledForTool = true

    var penStyle: PenStyle = PenStyle() {
        didSet { renderer.setPenStyle(penStyle) }
    }

    override var isFlipped: Bool { true }

    init(frame frameRect: NSRect = .zero) {
        guard let device = MTLCreateSystemDefaultDevice(),
              let renderer = InkRenderer(device: device) else {
            fatalError("Metal is unavailable on this Mac")
        }
        self.renderer = renderer
        super.init(frame: frameRect, device: device)
        configureMetal()
        renderer.setPenStyle(penStyle)
    }

    required init(coder: NSCoder) {
        guard let device = MTLCreateSystemDefaultDevice(),
              let renderer = InkRenderer(device: device) else {
            fatalError("Metal is unavailable on this Mac")
        }
        self.renderer = renderer
        super.init(coder: coder)
        self.device = device
        configureMetal()
        renderer.setPenStyle(penStyle)
    }

    private func configureMetal() {
        delegate = renderer
        isPaused = true
        enableSetNeedsDisplay = true
        framebufferOnly = true
        colorPixelFormat = .bgra8Unorm
        clearColor = MTLClearColor(red: 1, green: 1, blue: 1, alpha: 1)
    }

    override func mouseDown(with event: NSEvent) {
        guard isUserInteractionEnabledForTool else { return }
        active = true
        points = [makePoint(from: event)]
        renderer.setStroke(points)
        draw()
    }

    override func mouseDragged(with event: NSEvent) {
        guard isUserInteractionEnabledForTool, active else { return }
        points.append(makePoint(from: event))
        renderer.setStroke(points)
        draw()
    }

    override func mouseUp(with event: NSEvent) {
        guard isUserInteractionEnabledForTool, active else { return }
        points.append(makePoint(from: event))
        renderer.commitStroke(points)
        points.removeAll(keepingCapacity: true)
        active = false
        renderer.setStroke([])
        draw()
    }

    override func tabletPoint(with event: NSEvent) {
        guard isUserInteractionEnabledForTool else { return }
        switch event.phase {
        case .began: mouseDown(with: event)
        case .changed: mouseDragged(with: event)
        case .ended: mouseUp(with: event)
        case .cancelled:
            active = false
            points.removeAll(keepingCapacity: true)
            renderer.setStroke([])
            draw()
        default: break
        }
    }

    private func makePoint(from event: NSEvent) -> InkPoint {
        let p = convert(event.locationInWindow, from: nil)
        let pressure = event.type == .tabletPoint ? max(0, min(1, event.pressure)) : 1
        return InkPoint(x: Float(p.x), y: Float(p.y), pressure: Float(pressure))
    }
}
