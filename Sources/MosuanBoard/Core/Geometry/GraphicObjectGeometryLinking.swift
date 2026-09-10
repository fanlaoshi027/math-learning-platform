import CoreGraphics
import Foundation

/// Bridges independent GraphicObjects into one shared geometry model.
/// This keeps object rendering/selection separate while allowing real
/// cross-object constraints and dependency updates.
extension GraphicObjectStore {
    /// Merges the geometry models of line objects into one model and assigns
    /// that model to every participating object. UUIDs are already unique, so
    /// points, lines and constraints can be combined without remapping.
    @discardableResult
    func mergeGeometryModels(for objectIDs: [UUID]) -> UUID? {
        let selected = objectIDs.compactMap { object(with: $0) }
        guard selected.count == objectIDs.count, !selected.isEmpty,
              selected.allSatisfy({ $0.kind == .line || $0.kind == .geometryPoint }) else { return nil }

        let models = selected.compactMap(\.geometryModel)
        guard models.count == selected.count else { return nil }

        let points = models.flatMap(\.points)
        let lines = models.flatMap(\.lines)
        let angles = models.flatMap(\.angles)
        let constraints = models.flatMap(\.constraints)
        let parameters = models.flatMap(\.parameters)
        let translations = models.flatMap(\.translations)
        let guides = models.flatMap(\.translationGuides)

        let merged = GeometryModel(
            id: UUID(), points: points, lines: lines, angles: angles,
            constraints: constraints, parameters: parameters,
            translations: translations, translationGuides: guides
        )

        for object in selected {
            var copy = object
            copy.geometryModel = merged
            synchronizeGeometryProjection(&copy, model: merged)
            update(copy)
        }
        return merged.id
    }

    /// Creates a real intersection point object whose geometry model is shared
    /// with both source lines. Moving either line can therefore re-solve the
    /// same point instead of leaving behind a static dot.
    @discardableResult
    func createIntersectionPoint(firstLineObjectID: UUID, secondLineObjectID: UUID, name: String? = nil, segmentOnly: Bool = false) -> UUID? {
        guard let first = object(with: firstLineObjectID),
              let second = object(with: secondLineObjectID),
              first.kind == .line, second.kind == .line,
              isIdentityTransform(first.transform), isIdentityTransform(second.transform) else { return nil }

        guard let modelID = mergeGeometryModels(for: [firstLineObjectID, secondLineObjectID]),
              let source = object(with: firstLineObjectID),
              var model = source.geometryModel,
              let pointID = GeometryConstruction.createIntersectionPoint(
                model: &model,
                firstLineID: firstLineObjectID,
                secondLineID: secondLineObjectID,
                name: name,
                segmentOnly: segmentOnly
              ) else { return nil }

        guard model.id == modelID,
              let point = model.points.first(where: { $0.id == pointID }) else { return nil }

        for object in objects where object.geometryModel?.id == modelID {
            var copy = object
            copy.geometryModel = model
            synchronizeGeometryProjection(&copy, model: model)
            update(copy)
        }

        let style = GraphicObject.Style(strokeColor: first.style.strokeColor, strokeWidth: max(2, first.style.strokeWidth), opacity: first.style.opacity)
        var marker = GraphicObject(
            kind: .geometryPoint,
            style: style,
            geometry: GraphicObject.Geometry(points: [point.position]),
            geometryModel: model
        )
        marker.geometry.parameters["pointID"] = 0
        insert(marker)
        return marker.id
    }

    /// Moves a line endpoint through its geometry model and immediately solves
    /// every object that shares that model. Fixed points remain authoritative.
    @discardableResult
    func moveLineEndpointResolved(id: UUID, endpoint: Int, to point: CGPoint) -> Bool {
        guard let object = object(with: id), object.kind == .line,
              endpoint == 0 || endpoint == 1,
              var model = object.geometryModel,
              let line = model.lines.first(where: { $0.id == id }) ?? model.lines.first,
              let pointID = endpoint == 0 ? line.startPointID : line.endPointID,
              let pointIndex = model.points.firstIndex(where: { $0.id == pointID }),
              !model.points[pointIndex].isFixed else { return false }

        model.points[pointIndex].position = point
        GeometryConstraintSolver.apply(&model)
        synchronizeGeometryModel(model)
        return true
    }

    /// Re-solves a shared model and projects its points back into each
    /// participating GraphicObject's render geometry.
    @discardableResult
    func synchronizeGeometryModel(_ model: GeometryModel) -> Bool {
        var changed = false
        for object in objects where object.geometryModel?.id == model.id {
            var copy = object
            copy.geometryModel = model
            synchronizeGeometryProjection(&copy, model: model)
            if copy != object { update(copy); changed = true }
        }
        return changed
    }

    private func synchronizeGeometryProjection(_ object: inout GraphicObject, model: GeometryModel) {
        switch object.kind {
        case .line, .arrow:
            guard let line = model.lines.first(where: { $0.id == object.id }) ?? model.lines.first,
                  let a = model.points.first(where: { $0.id == line.startPointID }),
                  let b = model.points.first(where: { $0.id == line.endPointID }) else { return }
            object.geometry.points = [a.position, b.position]
        case .geometryPoint:
            let pointID: UUID?
            if let raw = object.geometry.points.first {
                pointID = model.points.min { distanceSquared($0.position, raw) < distanceSquared($1.position, raw) }?.id
            } else {
                pointID = model.points.first?.id
            }
            if let pointID, let point = model.points.first(where: { $0.id == pointID }) {
                object.geometry.points = [point.position]
            }
        default:
            break
        }
    }

    private func isIdentityTransform(_ transform: GraphicObject.Transform) -> Bool {
        abs(transform.position.x) < 0.0001 && abs(transform.position.y) < 0.0001 &&
        abs(transform.scale.width - 1) < 0.0001 && abs(transform.scale.height - 1) < 0.0001 &&
        abs(transform.rotation) < 0.0001 && abs(transform.rotationCenter.x) < 0.0001 && abs(transform.rotationCenter.y) < 0.0001
    }

    private func distanceSquared(_ a: CGPoint, _ b: CGPoint) -> CGFloat {
        let dx = a.x - b.x
        let dy = a.y - b.y
        return dx * dx + dy * dy
    }
}
