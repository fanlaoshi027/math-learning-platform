import AppKit
import PDFKit
import CoreImage
import CoreImage.CIFilterBuiltins

/// PDF page renderer with Mosuan's eye-comfort inversion.
///
/// This is intentionally not a photographic negative. The transform operates
/// primarily on luminance, mapping paper white toward a dark gray background
/// while preserving the hue tendency of colored teaching material.
final class MosuanPDFPageView: NSView {
    var document: PDFDocument? { didSet { needsDisplay = true } }
    var pageIndex: Int = 0 { didSet { needsDisplay = true } }
    var eyeComfortInverted: Bool = false { didSet { needsDisplay = true } }

    private let context = CIContext(options: [.useSoftwareRenderer: false])

    override func draw(_ dirtyRect: NSRect) {
        guard let context = NSGraphicsContext.current?.cgContext,
              let page = document?.page(at: pageIndex) else { return }

        let bounds = page.bounds(for: .mediaBox)
        guard bounds.width > 0, bounds.height > 0 else { return }

        let scale = min(dirtyRect.width / bounds.width, dirtyRect.height / bounds.height)
        guard scale.isFinite, scale > 0 else { return }

        let width = max(1, Int(ceil(bounds.width * scale)))
        let height = max(1, Int(ceil(bounds.height * scale)))
        let colorSpace = CGColorSpaceCreateDeviceRGB()
        guard let bitmap = CGContext(
            data: nil,
            width: width,
            height: height,
            bitsPerComponent: 8,
            bytesPerRow: width * 4,
            space: colorSpace,
            bitmapInfo: CGImageAlphaInfo.premultipliedLast.rawValue
        ) else { return }

        bitmap.setFillColor(NSColor.white.cgColor)
        bitmap.fill(CGRect(x: 0, y: 0, width: width, height: height))
        bitmap.saveGState()
        bitmap.translateBy(x: 0, y: CGFloat(height))
        bitmap.scaleBy(x: scale, y: -scale)
        page.draw(with: .mediaBox, to: bitmap)
        bitmap.restoreGState()

        guard let image = bitmap.makeImage() else { return }
        let source = CIImage(cgImage: image)
        let output: CIImage

        if eyeComfortInverted {
            output = MosuanPDFEyeComfortFilter.apply(to: source)
        } else {
            output = source
        }

        guard let outputImage = context.createCGImage(output, from: output.extent) else { return }

        let drawRect = AVMakeRect(
            aspectRatio: CGSize(width: bounds.width, height: bounds.height),
            insideRect: dirtyRect
        )
        NSGraphicsContext.current?.cgContext.interpolationQuality = .high
        context.draw(outputImage, in: drawRect, from: output.extent)
    }
}

/// Adaptive paper-to-night transform:
/// white -> ~10% luminance, black -> ~90% luminance, gray values reverse,
/// while chroma is retained instead of performing an RGB negative.
private enum MosuanPDFEyeComfortFilter {
    private static let kernel: CIColorKernel? = CIColorKernel(source: """
        kernel vec4 mosuanEyeComfort(__sample pixel) {
            float r = pixel.r;
            float g = pixel.g;
            float b = pixel.b;
            float luminance = dot(vec3(r, g, b), vec3(0.2126, 0.7152, 0.0722));

            // Keep a small amount of light in paper white so the page is not
            // pure black, matching the long-writing eye-comfort target.
            float targetLuminance = 0.90 - 0.80 * luminance;
            float chromaScale = 0.88;
            vec3 target = vec3(targetLuminance) +
                (vec3(r, g, b) - vec3(luminance)) * chromaScale;
            target = clamp(target, 0.0, 1.0);
            return vec4(target, pixel.a);
        }
    """)

    static func apply(to image: CIImage) -> CIImage {
        guard let kernel else { return image }
        return kernel.apply(extent: image.extent, arguments: [image]) ?? image
    }
}

struct PDFPageView: NSViewRepresentable {
    let document: PDFDocument
    let pageIndex: Int
    let eyeComfortInverted: Bool

    func makeNSView(context: Context) -> MosuanPDFPageView {
        let view = MosuanPDFPageView()
        view.document = document
        view.pageIndex = pageIndex
        view.eyeComfortInverted = eyeComfortInverted
        view.wantsLayer = true
        return view
    }

    func updateNSView(_ nsView: MosuanPDFPageView, context: Context) {
        nsView.document = document
        nsView.pageIndex = pageIndex
        nsView.eyeComfortInverted = eyeComfortInverted
    }
}
