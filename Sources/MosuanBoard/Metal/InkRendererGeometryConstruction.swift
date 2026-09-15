import CoreGraphics
import MetalKit

/// Renderer-facing boundary for completed click-based geometry construction.
/// Construction itself stays in Core/Geometry; this extension only commits the
/// finished object and deliberately leaves selection empty.
extension InkRenderer {
    @discardableResult
    func commitGeometryConstruction(
        _ result: GeometryConstructionSession.Result,
        style: GraphicObject.Style? = nil
    ) -> UUID? {
        guard let store = geometryConstructionObjectStore else { return nil }
        let resolvedStyle = style ?? geometryConstructionStyle
        let id = GeometryConstructionCommitter.commit(result, to: store, style: resolvedStyle)
        guard id != nil else { return nil }

        // setStroke([]) is the renderer's existing safe path for rebuilding the
        // drawable geometry without reaching across file-private renderer state.
        setStroke([])
        editingAttachedView?.draw()
        return id
    }

    private var geometryConstructionObjectStore: GraphicObjectStore? {
        Mirror(reflecting: self).children.first(where: { $0.label == "objectStore" })?.value as? GraphicObjectStore
    }

    private var geometryConstructionStyle: GraphicObject.Style {
        let mirror = Mirror(reflecting: self)
        guard let value = mirror.children.first(where: { $0.label == "penStyle" })?.value as? PenStyle else {
            return GraphicObject.Style()
        }
        return GraphicObject.Style(
            strokeColor: value.color,
            strokeWidth: value.width,
            opacity: value.opacity,
            lineStyle: value.lineStyle
        )
    }

    private var editingAttachedView: MTKView? {
        Mirror(reflecting: self).children.first(where: { $0.label == "attachedView" })?.value as? MTKView
    }
}
