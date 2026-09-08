import Foundation

/// Serializable vector ink state belonging to one document page.
struct CanvasStroke: Codable, Equatable, Identifiable {
    let id: UUID
    var points: [InkPoint]
    var style: PenStyle
    var rotation: Float

    init(id: UUID = UUID(), points: [InkPoint], style: PenStyle, rotation: Float = 0) {
        self.id = id
        self.points = points
        self.style = style
        self.rotation = rotation
    }
}

struct CanvasPageState: Codable, Equatable {
    var strokes: [CanvasStroke] = []
}
