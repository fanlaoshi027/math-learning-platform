import SwiftUI
import PDFKit

struct PDFTeachingWorkspace: View {
    let document: PDFDocument?
    @StateObject private var layers: PDFLayerStore
    @StateObject private var controller = CanvasController()
    @State private var pageIndex = 0
    @State private var tool: BoardTool = .pen
    @State private var zoomPercent = 100
    @State private var showLayers = true

    init(document: PDFDocument?) {
        self.document = document
        _layers = StateObject(wrappedValue: PDFLayerStore(document: document))
    }

    var body: some View {
        VStack(spacing: 0) {
            topBar
            HStack(spacing: 0) {
                ZStack {
                    if let document { PDFPageView(document: document, pageIndex: pageIndex) }
                    MetalInkCanvas(pageState: layers.compositeState(page: pageIndex + 1), tool: $tool, penStyle: PenPreset.defaults[0].style, controller: controller, background: SIMD4<Float>(0, 0, 0, 0), inverted: false, pattern: .blank, zoomPercent: $zoomPercent, onPageStateChanged: { state in layers.saveCurrentLayerState(state, page: pageIndex + 1) })
                }
                if showLayers { BoardLayerView(store: layers).background(.regularMaterial).overlay(alignment: .leading) { Divider() } }
            }
        }
        .onChange(of: layers.note.currentLayerID) { _, _ in reloadCanvas() }
        .onChange(of: layers.note.layers) { _, _ in reloadCanvas() }
        .frame(minWidth: 1000, minHeight: 700)
    }

    private func reloadCanvas() {
        guard let canvas = controller.canvas else { return }
        canvas.loadPageState(layers.compositeState(page: pageIndex + 1))
    }

    private var topBar: some View {
        HStack {
            Text("PDF 讲课").font(.headline)
            Spacer()
            Button("上一页") { pageIndex = max(0, pageIndex - 1) }
            Text("第 \(pageIndex + 1) 页")
            Button("下一页") { pageIndex += 1 }
            Toggle("图层", isOn: $showLayers).toggleStyle(.switch)
        }.padding(10)
    }
}
