
    @discardableResult
    func transform(id: UUID, position: CGPoint? = nil, scale: CGSize? = nil, rotation: CGFloat? = nil) -> Bool {
        guard let i = objects.firstIndex(where: { $0.id == id }) else { return false }
        if let position { objects[i].transform.position = position }
        if let scale { objects[i].transform.scale = scale }
        if let rotation { objects[i].transform.rotation = rotation }
        return true
    }

    func exportObjects() -> [GraphicObject] {
        objects
    }

    func importObjects(_ value: [GraphicObject]) {
        objects = value
    }

    private func transformedPoint(_ point: CGPoint, in object: GraphicObject) -> CGPoint {
        let center = object.transform.rotationCenter
        let translated = CGPoint(
            x: point.x - center.x,
            y: point.y - center.y
        )
        let scaled = CGPoint(
            x: translated.x * object.transform.scale.width,
            y: translated.y * object.transform.scale.height
        )
        let cosine = cos(object.transform.rotation)
        let sine = sin(object.transform.rotation)

        return CGPoint(
            x: scaled.x * cosine - scaled.y * sine + center.x + object.transform.position.x,
            y: scaled.x * sine + scaled.y * cosine + center.y + object.transform.position.y
        )
    }

    private func inverseTransform(_ point: CGPoint, in object: GraphicObject) -> CGPoint {
        let center = object.transform.rotationCenter

        let translated = CGPoint(
            x: point.x - object.transform.position.x - center.x,
            y: point.y - object.transform.position.y - center.y
        )

        let cosine = cos(object.transform.rotation)
        let sine = sin(object.transform.rotation)

        let rotatedX = translated.x * cosine + translated.y * sine
        let rotatedY = (-translated.x * sine) + (translated.y * cosine)

        let scaleX = abs(object.transform.scale.width) > 0.0001
            ? rotatedX / object.transform.scale.width
            : rotatedX
        let scaleY = abs(object.transform.scale.height) > 0.0001
            ? rotatedY / object.transform.scale.height
            : rotatedY

        return CGPoint(
            x: scaleX + center.x,
            y: scaleY + center.y
        )
    }

    private func segmentBounds(_ a: CGPoint, _ b: CGPoint) -> CGRect {
        CGRect(
            x: min(a.x, b.x),
            y: min(a.y, b.y),
            width: abs(b.x - a.x),
            height: abs(b.y - a.y)
        )
    }

    private func distance(_ p: CGPoint, toSegment a: CGPoint, _ b: CGPoint) -> CGFloat {
        let dx = b.x - a.x
        let dy = b.y - a.y
        let lengthSquared = dx * dx + dy * dy

        if lengthSquared == 0 {
            return hypot(p.x - a.x, p.y - a.y)
        }

        let t = max(
            0,
            min(
                1,
                ((p.x - a.x) * dx + (p.y - a.y) * dy) / lengthSquared
            )
        )

        let q = CGPoint(
            x: a.x + t * dx,
            y: a.y + t * dy
        )

        return hypot(p.x - q.x, p.y - q.y)
    }

    private func pointInPolygon(_ p: CGPoint, _ poly: [CGPoint]) -> Bool {
        guard poly.count >= 3 else { return false }

        var inside = false
        var j = poly.count - 1

        for i in poly.indices {
            let a = poly[i]
            let b = poly[j]

            if (a.y > p.y) != (b.y > p.y) {
                let denominator = b.y - a.y

                if denominator != 0 {
                    let x = (b.x - a.x) * (p.y - a.y) / denominator + a.x

                    if p.x < x {
                        inside.toggle()
                    }
                }
            }

            j = i
        }

        return inside
    }
}
