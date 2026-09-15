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
    @State private var eyeComfortInverted = false
    @State private var eyeComfortStrength: Double = 1
    @State private var showEyeComfortSettings = false

    init(document: PDFDocument?) {
        self.document = document
        _layers = StateObject(wrappedValue: PDFLayerStore(document: document))
    }

    var body: some View {
        VStack(spacing: 0) {
            topBar
            HStack(spacing: 0) {
                ZStack {
                    if let document {
                        PDFPageView(
                            document: document,
                            pageIndex: pageIndex,
                            eyeComfortInverted: eyeComfortInverted,
                            eyeComfortStrength: CGFloat(eyeComfortStrength)
                        )
                    }
                    MetalInkCanvas(
                        pageState: layers.compositeState(page: pageIndex + 1),
                        tool: $tool,
                        penStyle: PenPreset.defaults[0].style,
                        controller: controller,
                        background: SIMD4<Float>(0, 0, 0, 0),
                        inverted: eyeComfortInverted,
                        pattern: .blank,
                        zoomPercent: $zoomPercent,
                        onPageStateChanged: { state in
                            layers.saveCurrentLayerState(state, page: pageIndex + 1)
                        }
                    )
                }
                if showLayers {
                    BoardLayerView(store: layers)
                        .background(.regularMaterial)
                        .overlay(alignment: .leading) { Divider() }
                }
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
            Button("下一页") {
                guard let document else { return }
                pageIndex = min(max(0, document.pageCount - 1), pageIndex + 1)
            }
            Button {
                eyeComfortInverted.toggle()
            } label: {
                Label(
                    eyeComfortInverted ? "护眼反色" : "反色",
                    systemImage: eyeComfortInverted ? "sun.max.fill" : "moon.fill"
                )
            }
            .help("护眼反色：白底教材变深色，彩色内容保留色彩倾向")
            .buttonStyle(.bordered)
            .popover(isPresented: $showEyeComfortSettings, arrowEdge: .bottom) {
                eyeComfortPanel
            }
            Button {
                showEyeComfortSettings.toggle()
            } label: {
                Image(systemName: "slider.horizontal.3")
            }
            .help("护眼反色设置")
            .buttonStyle(.bordered)
            Toggle("图层", isOn: $showLayers).toggleStyle(.switch)
        }
        .padding(10)
    }

    private var eyeComfortPanel: some View {
        VStack(alignment: .leading, spacing: 14) {
            Text("护眼反色")
                .font(.headline)
            Text("不是普通负片：主要反转纸张亮度，尽量保持教材和彩色图表的色彩关系。")
                .font(.caption)
                .foregroundStyle(.secondary)
                .fixedSize(horizontal: false, vertical: true)

            Toggle("启用护眼反色", isOn: $eyeComfortInverted)

            VStack(alignment: .leading, spacing: 6) {
                HStack {
                    Text("反色强度")
                    Spacer()
                    Text("\(Int(eyeComfortStrength * 100))%")
                        .monospacedDigit()
                        .foregroundStyle(.secondary)
                }
                Slider(value: $eyeComfortStrength, in: 0.55...1, step: 0.05)
            }

            HStack(spacing: 8) {
                Button("柔和") { eyeComfortStrength = 0.65 }
                Button("标准") { eyeComfortStrength = 1 }
            }
            .buttonStyle(.bordered)
        }
        .padding(18)
        .frame(width: 300)
    }
}
