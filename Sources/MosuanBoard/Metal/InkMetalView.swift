import AppKit
import MetalKit
import simd

final class InkMetalView: MTKView {
    private let renderer: InkRenderer
    private var points: [InkPoint] = []
    private var active = false
    private var selectionDrag = false
    private var lastSelectionPoint = SIMD2<Float>(0, 0)
    var isUserInteractionEnabledForTool = true
    var isSelectionTool = false
    var onHistoryChanged: (() -> Void)?
    var onSelectionChanged: (() -> Void)?

    var penStyle: PenStyle = PenStyle() { didSet { renderer.setPenStyle(penStyle) } }
    var canUndo: Bool { renderer.canUndo }
    var canRedo: Bool { renderer.canRedo }
    var hasSelection: Bool { renderer.hasSelection }

    override var isFlipped: Bool { true }

    init(frame frameRect: NSRect = .zero) {
        guard let device = MTLCreateSystemDefaultDevice(), let renderer = InkRenderer(device: device) else { fatalError("Metal is unavailable on this Mac") }
        self.renderer = renderer
        super.init(frame: frameRect, device: device)
        configureMetal(); renderer.setPenStyle(penStyle)
    }

    required init(coder: NSCoder) {
        guard let device = MTLCreateSystemDefaultDevice(), let renderer = InkRenderer(device: device) else { fatalError("Metal is unavailable on this Mac") }
        self.renderer = renderer
        super.init(coder: coder); self.device = device
        configureMetal(); renderer.setPenStyle(penStyle)
    }

    private func configureMetal() {
        delegate = renderer; isPaused = true; enableSetNeedsDisplay = true
        framebufferOnly = true; colorPixelFormat = .bgra8Unorm
        clearColor = MTLClearColor(red: 1, green: 1, blue: 1, alpha: 1)
    }

    func undo() { renderer.undo(); onHistoryChanged?(); draw() }
    func redo() { renderer.redo(); onHistoryChanged?(); draw() }
    func deleteSelected() { renderer.deleteSelected(); onHistoryChanged?(); onSelectionChanged?(); draw() }

    override func mouseDown(with event: NSEvent) {
        let point = makePoint(from: event)
        if isSelectionTool {
            selectionDrag = renderer.selectStroke(at: point)
            lastSelectionPoint = point
            onSelectionChanged?()
            draw()
            return
        }
        guard isUserInteractionEnabledForTool else { return }
        active = true; points = [point]; renderer.setStroke(points); draw()
    }

    override func mouseDragged(with event: NSEvent) {
        let point = makePoint(from: event)
        if isSelectionTool {
            guard selectionDrag else { return }
            let delta = point - lastSelectionPoint
            if simd_length_squared(delta) > 0 {
                renderer.moveSelected(by: delta)
                lastSelectionPoint = point
                draw()
            }
            return
        }
        guard isUserInteractionEnabledForTool, active else { return }
        points.append(point); renderer.setStroke(points); draw()
    }

    override func mouseUp(with event: NSEvent) {
        if isSelectionTool {
            selectionDrag = false
            onSelectionChanged?()
            return
        }
        guard isUserInteractionEnabledForTool, active else { return }
        points.append(makePoint(from: event)); renderer.commitStroke(points)
        points.removeAll(keepingCapacity: true); active = false; renderer.setStroke([]); onHistoryChanged?(); draw()
    }

    override func keyDown(with event: NSEvent) {
        if isSelectionTool && event.keyCode == 51 {
            deleteSelected()
            return
        }
        super.keyDown(with: event)
    }

    override func tabletPoint(with event: NSEvent) {
        guard !isSelectionTool else {
            switch event.phase {
            case .began: mouseDown(with: event)
            case .changed: mouseDragged(with: event)
            case .ended: mouseUp(with: event)
            default: break
            }
            return
        }
        guard isUserInteractionEnabledForTool else { return }
        switch event.phase {
        case .began: mouseDown(with: event)
        case .changed: mouseDragged(with: event)
        case .ended: mouseUp(with: event)
        case .cancelled:
            active = false; points.removeAll(keepingCapacity: true); renderer.setStroke([]); draw()
        default: break
        }
    }

    private func makePoint(from event: NSEvent) -> SIMD2<Float> {
        let p = convert(event.locationInWindow, from: nil)
        return SIMD2<Float>(Float(p.x), Float(p.y))
    }
}
