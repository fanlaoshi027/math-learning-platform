import CoreGraphics
import Foundation

/// A first-class dynamic angle teaching object.
///
/// It is deliberately represented by the same point/line/constraint graph used
/// by the rest of Mosuan geometry, so the angle can later participate in larger
/// constructions instead of becoming a special-case drawing primitive.
struct DynamicAngle: Codable, Equatable, Identifiable {
    let id: UUID
    var model: GeometryModel
    let vertexPointID: UUID
    let startPointID: UUID
    let endPointID: UUID
    let angleID: UUID
    let parameterID: UUID

    init(
        id: UUID = UUID(),
        model: GeometryModel,
        vertexPointID: UUID,
        startPointID: UUID,
        endPointID: UUID,
        angleID: UUID,
        parameterID: UUID
    ) {
        self.id = id
        self.model = model
        self.vertexPointID = vertexPointID
        self.startPointID = startPointID
        self.endPointID = endPointID
        self.angleID = angleID
        self.parameterID = parameterID
    }

    var angleDegrees: CGFloat? {
        guard let annotation = model.angles.first(where: { $0.id == angleID }) else { return nil }
        return GeometryAngleCalculator.value(in: model, annotation: annotation)?.degrees
    }

    var parameter: GeometryParameter? {
        model.parameter(id: parameterID)
    }

    /// Changes the dynamic angle and immediately resolves the two rays.
    @discardableResult
    mutating func setAngle(_ degrees: CGFloat) -> Bool {
        model.setParameterAndResolve(parameterID, value: degrees)
    }

    /// Changes the allowed motion range. The angle parameter remains the single
    /// source of truth used by sliders, numeric input and animation playback.
    @discardableResult
    mutating func setRange(minimum: CGFloat, maximum: CGFloat, step: CGFloat = 1) -> Bool {
        guard let index = model.parameters.firstIndex(where: { $0.id == parameterID }) else { return false }
        model.parameters[index].setRange(minimum: minimum, maximum: maximum, step: step)
        GeometryConstraintSolver.apply(&model)
        return true
    }

    /// Creates the common teaching case: two rays share a vertex, the first ray
    /// is the reference direction, and the second ray rotates around the vertex.
    static func make(
        vertex: CGPoint,
        startLength: CGFloat = 180,
        endLength: CGFloat = 180,
        angleDegrees: CGFloat = 45,
        minimum: CGFloat = 10,
        maximum: CGFloat = 170,
        step: CGFloat = 1,
        name: String = "α"
    ) -> DynamicAngle {
        let vertexPoint = GeometryPoint(name: "O", position: vertex, isFixed: true)
        let startPoint = GeometryPoint(
            name: "A",
            position: CGPoint(x: vertex.x + startLength, y: vertex.y),
            isFixed: true
        )
        let radians = angleDegrees * .pi / 180
        let endPoint = GeometryPoint(
            name: "B",
            position: CGPoint(
                x: vertex.x + endLength * cos(radians),
                y: vertex.y + endLength * sin(radians)
            )
        )

        let startLine = GeometryLine(
            startPointID: vertexPoint.id,
            endPointID: startPoint.id,
            kind: .ray
        )
        let endLine = GeometryLine(
            startPointID: vertexPoint.id,
            endPointID: endPoint.id,
            kind: .ray
        )

        let parameter = GeometryParameter(
            name: name,
            value: angleDegrees,
            minimum: minimum,
            maximum: maximum,
            step: step
        )

        let annotation = GeometryAngleAnnotation(
            vertexID: vertexPoint.id,
            startPointID: startPoint.id,
            endPointID: endPoint.id,
            mode: .nameAndDegrees,
            name: name,
            angleReference: .parameter(parameter.id),
            labelOffset: CGPoint(x: 0, y: -12),
            radius: min(startLength, endLength) * 0.32,
            autoAvoid: true
        )

        var model = GeometryModel(
            points: [vertexPoint, startPoint, endPoint],
            lines: [startLine, endLine],
            angles: [annotation],
            parameters: [parameter]
        )
        GeometryConstraintSolver.apply(&model)

        return DynamicAngle(
            model: model,
            vertexPointID: vertexPoint.id,
            startPointID: startPoint.id,
            endPointID: endPoint.id,
            angleID: annotation.id,
            parameterID: parameter.id
        )
    }
}

extension DynamicAngle {
    /// Convenience factory for inserting the object into the generic canvas object layer.
    func graphicObject(style: GraphicObject.Style = GraphicObject.Style()) -> GraphicObject {
        GraphicObject(
            id: id,
            kind: .dynamicAngle,
            style: style,
            geometry: GraphicObject.Geometry(points: model.points.map(\.position)),
            geometryModel: model,
            dynamicAngleModel: self
        )
    }
}
