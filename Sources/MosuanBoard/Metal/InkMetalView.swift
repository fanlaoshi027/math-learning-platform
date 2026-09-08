import AppKit
import MetalKit
import simd

final class InkMetalView: MTKView {
    private let renderer: InkRenderer
    private var points: [InkPoint] = []
    private var eraserPoints: [SIMD2<Float>] = []
    private var active = false
    private var selectionDrag = false
    private var resizeHandle: InkRenderer.SelectionHandle?
    private var rotationDrag = false
    private var lastRotationPoint = SIMD2<Float>(0, 0)
    private var lastSelectionPoint = SIMD2<Float>(0, 0)
    private var smartLineDetected = false
    private var smartLineWorkItem: DispatchWorkItem?
    var isUserInteractionEnabledForTool = true
    var isSelectionTool = false
    var isLineTool = false
    var isSmartLineTool = false
    var isEraserTool = false
    var onHistoryChanged: (() -> Void)?
    var onSelectionChanged: (() -> Void)?

    var penStyle: PenStyle = PenStyle() { didSet { renderer.setPenStyle(penStyle) } }
    var canUndo: Bool { renderer.canUndo }
    var canRedo: Bool { renderer.canRedo }
    var hasSelection: Bool { renderer.hasSelection }
    var selectedRotationDegrees: Double { renderer.selectedRotationDegrees }

    override var isFlipped: Bool { true }
    override var acceptsFirstResponder: Bool { true }

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

    func undo() { renderer.undo(); onHistoryChanged?(); onSelectionChanged?(); draw() }
    func redo() { renderer.redo(); onHistoryChanged?(); onSelectionChanged?(); draw() }
    func deleteSelected() { renderer.deleteSelected(); onHistoryChanged?(); onSelectionChanged?(); draw() }
    func setSelectedRotationDegrees(_ degrees: Double) { renderer.setSelectedRotationDegrees(degrees); onSelectionChanged?(); draw() }

    override func mouseDown(with event: NSEvent) {
        window?.makeFirstResponder(self)
        let point = makePoint(from: event)

        if isEraserTool {
            eraserPoints = [point]
            return
        }

        if isSelectionTool {
            if renderer.rotationHandle(at: point) {
                rotationDrag = true; lastRotationPoint = point; return
            }
            if let handle = renderer.selectionHandle(at: point) {
                resizeHandle = handle; lastSelectionPoint = point; return
            }
            selectionDrag = renderer.selectStroke(at: point)
            lastSelectionPoint = point
            onSelectionChanged?(); draw(); return
        }
        guard isUserInteractionEnabledForTool else { return }
        active = true
        smartLineDetected = false
        smartLineWorkItem?.cancel()
        points = [InkPoint(x: point.x, y: point.y, pressure: event.pressure > 0 ? Float(event.pressure) : 1)]
        renderer.setStroke(points)
        draw()
    }

    override func mouseDragged(with event: NSEvent) {
        let point = makePoint(from: event)
        if isEraserTool {
            eraserPoints.append(point)
            return
        }
        if isSelectionTool {
            if rotationDrag {
                renderer.rotateSelected(to: point, from: lastRotationPoint); lastRotationPoint = point; onSelectionChanged?(); draw(); return
            }
            if let handle = resizeHandle { renderer.resizeSelected(handle: handle, to: point); draw(); return }
            guard selectionDrag else { return }
            let delta = point - lastSelectionPoint
            if simd_length_squared(delta) > 0 { renderer.moveSelected(by: delta); lastSelectionPoint = point; draw() }
            return
        }
        guard isUserInteractionEnabledForTool, active else { return }
        let pressure = event.pressure > 0 ? Float(event.pressure) : (points.last?.pressure ?? 1)
        points.append(InkPoint(x: point.x, y: point.y, pressure: pressure))
        if isLineTool {
            renderer.setStroke(linePreview(from: points))
        } else if isSmartLineTool && smartLineDetected {
            renderer.setStroke(linePreview(from: points))
        } else {
            renderer.setStroke(points)
        }
        scheduleSmartLineDetection()
        draw()
    }

    override func mouseUp(with event: NSEvent) {
        smartLineWorkItem?.cancel()
        let point = makePoint(from: event)
        if isEraserTool {
            eraserPoints.append(point)
            eraseAlongPath(eraserPoints)
            eraserPoints.removeAll(keepingCapacity: true)
            return
        }
        if isSelectionTool {
            rotationDrag = false; resizeHandle = nil; selectionDrag = false; onSelectionChanged?(); return
        }
        guard isUserInteractionEnabledForTool, active else { return }
        let pressure = event.pressure > 0 ? Float(event.pressure) : (points.last?.pressure ?? 1)
        points.append(InkPoint(x: point.x, y: point.y, pressure: pressure))
        let committed = (isLineTool || (isSmartLineTool && smartLineDetected)) ? linePreview(from: points) : points
        renderer.commitStroke(committed)
        points.removeAll(keepingCapacity: true); active = false; smartLineDetected = false; renderer.setStroke([])
        onHistoryChanged?(); onSelectionChanged?(); draw()
    }

    override func keyDown(with event: NSEvent) {
        if isSelectionTool && event.keyCode == 51 { deleteSelected(); return }
        super.keyDown(with: event)
    }

    override func tabletPoint(with event: NSEvent) {
        guard !isSelectionTool && !isEraserTool else {
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
            smartLineWorkItem?.cancel(); active = false; points.removeAll(keepingCapacity: true); renderer.setStroke([]); draw()
        default: break
        }
    }

    private func scheduleSmartLineDetection() {
        guard isSmartLineTool, points.count >= 4 else { return }
        smartLineWorkItem?.cancel()
        let work = DispatchWorkItem { [weak self] in
            guard let self, self.active, self.isSmartLineTool, self.points.count >= 4 else { return }
            if self.isLikelyStraightLine(self.points) {
                self.smartLineDetected = true
                self.renderer.setStroke(self.linePreview(from: self.points))
                self.draw()
            }
        }
        smartLineWorkItem = work
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.18, execute: work)
    }

    private func isLikelyStraightLine(_ points: [InkPoint]) -> Bool {
        guard let first = points.first, let last = points.last else { return false }
        let a = SIMD2<Float>(first.x, first.y), b = SIMD2<Float>(last.x, last.y)
        let length = simd_distance(a, b)
        guard length > 30 else { return false }
        let tolerance = max(7, length * 0.075)
        return points.dropFirst().dropLast().allSatisfy {
            distanceToSegment(SIMD2<Float>($0.x, $0.y), a, b) <= tolerance
        }
    }

    private func linePreview(from points: [InkPoint]) -> [InkPoint] {
        guard let first = points.first, let last = points.last else { return points }
        return [first, last]
    }

    private func eraseAlongPath(_ path: [SIMD2<Float>]) {
        guard !path.isEmpty else { return }
        var deleted = false
        // First pass: strokes crossed by the eraser/scribble path.
        for point in path {
            if renderer.selectStroke(at: point, tolerance: 16) {
                renderer.deleteSelected(); deleted = true
            }
        }
        // Second pass: for a closed “circle and erase” gesture, sample its bounding box
        // so objects inside the scribble can be removed even when the outline misses them.
        guard path.count >= 8 else {
            if deleted { onHistoryChanged?(); draw() }
            return
        }
        var minX = path[0].x, maxX = path[0].x, minY = path[0].y, maxY = path[0].y
        for p in path { minX = min(minX, p.x); maxX = max(maxX, p.x); minY = min(minY, p.y); maxY = max(maxY, p.y) }
        let width = maxX - minX, height = maxY - minY
        guard width > 20, height > 20 else {
            if deleted { onHistoryChanged?(); draw() }
            return
        }
        let step: Float = 12
        var y = minY + step * 0.5
        while y < maxY {
            var x = minX + step * 0.5
            while x < maxX {
                let dx = (x - (minX + maxX) * 0.5) / max(width * 0.5, 1)
                let dy = (y - (minY + maxY) * 0.5) / max(height * 0.5, 1)
                if dx * dx + dy * dy <= 1.15, renderer.selectStroke(at: SIMD2<Float>(x, y), tolerance: 10) {
                    renderer.deleteSelected(); deleted = true
                }
                x += step
            }
            y += step
        }
        if deleted { onHistoryChanged?() }
        draw()
    }

    private func distanceToSegment(_ p: SIMD2<Float>, _ a: SIMD2<Float>, _ b: SIMD2<Float>) -> Float {
        let ab = b - a
        let lengthSquared = simd_length_squared(ab)
        if lengthSquared < 0.0001 { return simd_distance(p, a) }
        let t = max(0, min(1, simd_dot(p - a, ab) / lengthSquared))
        return simd_distance(p, a + ab * t)
    }

    private func makePoint(from event: NSEvent) -> SIMD2<Float> {
        let p = convert(event.locationInWindow, from: nil)
        return SIMD2<Float>(Float(p.x), Float(p.y))
    }
}
