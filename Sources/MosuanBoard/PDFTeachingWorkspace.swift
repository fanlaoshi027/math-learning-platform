import SwiftUI
import AppKit
import PDFKit

@MainActor
final class PDFWorkspaceController: ObservableObject {
    static let shared = PDFWorkspaceController()
    private var window: NSWindow?

    func openPDF() {
        let panel = NSOpenPanel()
        panel.allowedContentTypes = [.pdf]
        panel.allowsMultipleSelection = false
        panel.canChooseDirectories = false
        panel.title = "导入 PDF"
        panel.prompt = "打开"
        guard panel.runModal() == .OK, let url = panel.url else { return }
        open(url: url)
    }

    func open(url: URL) {
        let view = PDFTeachingWorkspace(url: url)
        let hosting = NSHostingView(rootView: view)
        let window = NSWindow(contentRect: NSRect(x: 0, y: 0, width: 1280, height: 820), styleMask: [.titled, .closable, .miniaturizable, .resizable], backing: .buffered, defer: false)
        window.title = "墨算 · \(url.deletingPathExtension().lastPathComponent)"
        window.contentView = hosting
        window.center()
        window.isReleasedWhenClosed = false
        window.makeKeyAndOrderFront(nil)
        NSApp.activate(ignoringOtherApps: true)
        self.window = window
    }
}

struct PDFTeachingWorkspace: View {
    let url: URL
    @State private var document: PDFDocument?
    @State private var pageIndex = 0
    @State private var pageCount = 0
    @State private var tool: BoardTool = .pen
    @StateObject private var controller = CanvasController()
    @StateObject private var pageController = PageController()
    @State private var zoomPercent = 100

    var body: some View {
        VStack(spacing: 0) {
            HStack(spacing: 10) {
                Text(url.deletingPathExtension().lastPathComponent).font(.headline).lineLimit(1)
                Divider().frame(height: 20)
                ToolButton(title: "选择", systemImage: "cursorarrow", selected: tool == .select) { tool = .select }
                ToolButton(title: "画笔", systemImage: "pencil.tip", selected: tool == .pen) { tool = .pen }
                ToolButton(title: "直线", systemImage: "line.diagonal", selected: tool == .line) { tool = .line }
                ToolButton(title: "橡皮", systemImage: "eraser", selected: tool == .eraser) { tool = .eraser }
                Spacer()
                Button { previousPage() } label: { Image(systemName: "chevron.left") }.disabled(pageIndex <= 0)
                Text("第 \(pageIndex + 1) / \(max(pageCount, 1)) 页").monospacedDigit()
                Button { nextPage() } label: { Image(systemName: "chevron.right") }.disabled(pageIndex + 1 >= pageCount)
            }
            .padding(.horizontal, 14)
            .padding(.vertical, 8)
            .background(.regularMaterial)

            ZStack {
                if let document {
                    PDFPageView(document: document, pageIndex: pageIndex)
                }
                MetalInkCanvas(
                    pageID: pageController.currentPage?.id ?? UUID(),
                    pageState: pageController.currentPage?.content ?? CanvasPageState(),
                    tool: $tool,
                    penStyle: PenPreset.defaults[0].style,
                    controller: controller,
                    background: SIMD4<Float>(0, 0, 0, 0),
                    inverted: false,
                    pattern: .blank,
                    zoomPercent: $zoomPercent,
                    onPageStateChanged: { state in
                        pageController.saveCurrentPageState(state)
                    }
                )
            }
        }
        .task { loadDocument() }
        .frame(minWidth: 900, minHeight: 650)
    }

    private func loadDocument() {
        guard let doc = PDFDocument(url: url), doc.pageCount > 0 else { return }
        document = doc
        pageCount = doc.pageCount
        for _ in 1..<doc.pageCount { pageController.addPage() }
        pageController.selectPage(0)
    }

    private func previousPage() {
        guard pageIndex > 0 else { return }
        pageIndex -= 1
        pageController.selectPage(pageIndex)
    }

    private func nextPage() {
        guard pageIndex + 1 < pageCount else { return }
        pageIndex += 1
        pageController.selectPage(pageIndex)
    }
}

struct PDFPageView: NSViewRepresentable {
    let document: PDFDocument
    let pageIndex: Int

    func makeNSView(context: Context) -> PDFView {
        let view = PDFView()
        view.autoScales = true
        view.displayMode = .singlePage
        view.displayDirection = .horizontal
        view.displaysPageBreaks = false
        view.backgroundColor = .clear
        view.document = document
        return view
    }

    func updateNSView(_ view: PDFView, context: Context) {
        view.document = document
        guard let page = document.page(at: pageIndex) else { return }
        if view.currentPage !== page { view.go(to: page) }
    }
}
