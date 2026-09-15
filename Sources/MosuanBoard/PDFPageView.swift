import AppKit
import PDFKit
import CoreImage

/// PDF page renderer with Mosuan's eye-comfort inversion.
/// This is intentionally not a photographic negative: paper luminance is
/// inverted while the original hue tendency of colored material is retained.
final class MosuanPDFPageView: NSView {
    var document: PDFDocument? { didSet { needsDisplay = true } }
    var pageIndex: Int = 0 { didSet { needsDisplay = true } }
    var eyeComfortInverted: Bool = false { didSet { needsDisplay = true } }
    var eyeComfortStrength: CGFloat = 1 { didSet { needsDisplay = true } }

    private let ciContext = CIContext(options: [.useSoftwareRenderer: false])

    override func draw(_ dirtyRect: NSRect) {
        guard let cgContext = NSGraphicsContext.current?.cgContext,
              let page = document?.page(at: pageIndex) else { return }

        let pageBounds = page.bounds(for: .mediaBox)
        guard pageBounds.width > 0, pageBounds.height > 0 else { return }

        let scale = min(bounds.width / pageBounds.width, bounds.height / pageBounds.height)
        guard scale.isFinite, scale > 0 else { return }

        let pixelWidth = max(1, Int(ceil(pageBounds.width * scale)))
        let pixelHeight = max(1, Int(ceil(pageBounds.height * scale)))
        let colorSpace = CGColorSpaceCreateDeviceRGB()
        guard let bitmap = CGContext(
            data: nil,
            width: pixelWidth,
            height: pixelHeight,
            bitsPerComponent: 8,
            bytesPerRow: pixelWidth * 4,
            space: colorSpace,
            bitmapInfo: CGImageAlphaInfo.premultipliedLast.rawValue
        ) else { return }

        bitmap.setFillColor(NSColor.white.cgColor)
        bitmap.fill(CGRect(x: 0, y: 0, width: pixelWidth, height: pixelHeight))
        bitmap.saveGState()
        bitmap.translateBy(x: 0, y: CGFloat(pixelHeight))
        bitmap.scaleBy(x: scale, y: -scale)
        page.draw(with: .mediaBox, to: bitmap)
        bitmap.restoreGState()

        guard let image = bitmap.makeImage() else { return }
        let source = CIImage(cgImage: image)
        let output = eyeComfortInverted
            ? MosuanPDFEyeComfortFilter.apply(to: source, strength: eyeComfortStrength)
            : source
        guard let outputImage = ciContext.createCGImage(output, from: output.extent) else { return }

        let drawWidth = pageBounds.width * scale
        let drawHeight = pageBounds.height * scale
        let drawRect = CGRect(
            x: bounds.midX - drawWidth * 0.5,
            y: bounds.midY - drawHeight * 0.5,
            width: drawWidth,
            height: drawHeight
        )
        cgContext.interpolationQuality = .high
        cgContext.draw(outputImage, in: drawRect, from: output.extent)
    }
}

/// Adaptive paper-to-night transform.
/// At full strength: white -> ~10% luminance, black -> ~90% luminance.
/// The result is blended with the original image so the user can choose a
/// gentler setting without ever turning the page into an RGB negative.
private enum MosuanPDFEyeComfortFilter {
    private static let kernel: CIColorKernel? = CIColorKernel(source: """
        kernel vec4 mosuanEyeComfort(__sample pixel, float strength) {
            float r = pixel.r;
            float g = pixel.g;
            float b = pixel.b;
            float luminance = dot(vec3(r, g, b), vec3(0.2126, 0.7152, 0.0722));

            // Paper white becomes a dark neutral rather than pure black.
            // Black ink becomes light. Chroma remains close to the source hue.
            float targetLuminance = 0.90 - 0.80 * luminance;
            float chromaScale = 0.88;
            vec3 transformed = vec3(targetLuminance) +
                (vec3(r, g, b) - vec3(luminance)) * chromaScale;
            transformed = clamp(transformed, 0.0, 1.0);

            vec3 target = mix(vec3(r, g, b), transformed, clamp(strength, 0.0, 1.0));
            return vec4(target, pixel.a);
        }
    """)

    static func apply(to image: CIImage, strength: CGFloat) -> CIImage {
        guard let kernel else { return image }
        return kernel.apply(
            extent: image.extent,
            arguments: [image, max(0, min(1, strength))]
        ) ?? image
    }
}

struct PDFPageView: NSViewRepresentable {
    let document: PDFDocument
    let pageIndex: Int
    let eyeComfortInverted: Bool
    let eyeComfortStrength: CGFloat

    func makeNSView(context: Context) -> MosuanPDFPageView {
        let view = MosuanPDFPageView()
        view.document = document
        view.pageIndex = pageIndex
        view.eyeComfortInverted = eyeComfortInverted
        view.eyeComfortStrength = eyeComfortStrength
        view.wantsLayer = true
        return view
    }

    func updateNSView(_ nsView: MosuanPDFPageView, context: Context) {
        nsView.document = document
        nsView.pageIndex = pageIndex
        nsView.eyeComfortInverted = eyeComfortInverted
        nsView.eyeComfortStrength = eyeComfortStrength
    }
}
