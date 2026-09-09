import CoreGraphics

// Keep the renderer's Float-based Metal geometry interoperable with the
// CGFloat-based geometry core without scattering conversions through UI code.
@inline(__always)
func * (lhs: Float, rhs: CGFloat) -> CGFloat {
    CGFloat(lhs) * rhs
}

@inline(__always)
func / (lhs: Float, rhs: CGFloat) -> CGFloat {
    CGFloat(lhs) / rhs
}
