        let startAngle = atan2(s.y - v.y, s.x - v.x)
        let endAngle = atan2(e.y - v.y, e.x - v.x)
        let cross = (s.x-v.x)*(e.y-v.y) - (s.y-v.y)*(e.x-v.x)
        let signed: Float = cross >= 0 ? 1 : -1
        var delta = abs(endAngle - startAngle)
        if delta > .pi { delta = 2 * .pi - delta }
        let radius = min(60, max(18, hypot(s.x-v.x, s.y-v.y) * 0.32))
        var previous = v + SIMD2(cos(startAngle), sin(startAngle)) * radius
        let segments = max(12, Int((delta * 180 / .pi) / 4))
        for i in 1...segments {
            let step = delta / Float(segments)
            let index = Float(i)
            let a = startAngle + signed * step * index
            let current = v + SIMD2(cos(a), sin(a)) * radius
            appendLine(previous, current, width: Float(max(1.0, object.style.strokeWidth * 0.9)), color: color, to: &out)
            previous = current
        }
        if selectedObjectIDs.contains(object.id) {
            disk(v, 6, SIMD4<Float>(0.1,0.45,1,0.9), to: &out)