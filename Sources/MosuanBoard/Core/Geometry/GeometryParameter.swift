import CoreGraphics
import Foundation

/// A named numeric parameter that can be shared by multiple geometry elements.
/// The value is always kept inside the configured range and aligned to the step.
struct GeometryParameter: Codable, Equatable, Identifiable {
    let id: UUID
    var name: String
    var value: CGFloat
    var minimum: CGFloat
    var maximum: CGFloat
    var step: CGFloat

    init(
        id: UUID = UUID(),
        name: String,
        value: CGFloat,
        minimum: CGFloat = 1,
        maximum: CGFloat = 500,
        step: CGFloat = 1
    ) {
        self.id = id
        self.name = name
        self.minimum = min(minimum, maximum)
        self.maximum = max(minimum, maximum)
        self.step = max(0.0001, abs(step))
        self.value = Self.snap(value, minimum: self.minimum, maximum: self.maximum, step: self.step)
    }

    mutating func setValue(_ value: CGFloat) {
        self.value = Self.snap(value, minimum: minimum, maximum: maximum, step: step)
    }

    mutating func setRange(minimum: CGFloat, maximum: CGFloat, step: CGFloat) {
        self.minimum = min(minimum, maximum)
        self.maximum = max(minimum, maximum)
        self.step = max(0.0001, abs(step))
        value = Self.snap(value, minimum: self.minimum, maximum: self.maximum, step: self.step)
    }

    private static func snap(_ value: CGFloat, minimum: CGFloat, maximum: CGFloat, step: CGFloat) -> CGFloat {
        let clamped = max(minimum, min(maximum, value))
        let index = ((clamped - minimum) / step).rounded()
        return max(minimum, min(maximum, minimum + index * step))
    }
}

/// A length reference. A segment can either own a concrete length or reference
/// a named parameter, allowing multiple edges to stay synchronized.
enum GeometryLengthReference: Codable, Equatable {
    case fixed(CGFloat)
    case parameter(UUID)

    func resolved(using parameters: [GeometryParameter]) -> CGFloat? {
        switch self {
        case let .fixed(value):
            return max(0.0001, value)
        case let .parameter(id):
            return parameters.first(where: { $0.id == id })?.value
        }
    }
}
