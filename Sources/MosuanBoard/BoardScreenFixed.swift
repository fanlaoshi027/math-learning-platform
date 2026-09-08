import SwiftUI
import AppKit

struct BoardScreen: View {
    private enum ToolbarDock: String { case top, bottom, left, right }
    @StateObject private var controller = CanvasController()
    @StateObject private var pageController = PageController()
    @State private var tool: BoardTool = .pen
    @State private var presetID = PenPreset.defaults[0].id
    @State private var rotationText = "0"
    @State private var zoomPercent = 100
    @State private var toolbarDock: ToolbarDock = .top
    @State private var background: BoardBackground = .white
    @State private var inverted = false
    @State private var eyeComfortBackground: EyeComfortBackground = .black90
    @State private var customHex = "1A1A1A"

    private var preset: PenPreset { PenPreset.defaults.first { $0.id == presetID } ?? PenPreset.defaults[0] }
    private var toolbarIsVertical: Bool { toolbarDock == .left || toolbarDock == .right }
    private var customColor: SIMD4<Float> {
        let value = customHex.replacingOccurrences(of: "#", with: "")
        guard value.count == 6, let rgb = UInt64(value, radix: 16) else { return EyeComfortBackground.black90.color }
        return SIMD4(Float((rgb >> 16) & 255)/255, Float((rgb >> 8) & 255)/255, Float(rgb & 255)/255, 1)
    }
    private var effectiveBackground: SIMD4<Float> {
        inverted && background == .white ? (eyeComfortBackground == .custom ? customColor : eyeComfortBackground.color) : background.metal
    }

    var body: some View {
        GeometryReader { proxy in
            ZStack {
                Color(red:Double(effectiveBackground.x),green:Double(effectiveBackground.y),blue:Double(effectiveBackground.z)).ignoresSafeArea()
                HStack(spacing:0) {
                    PageSidebar(controller: pageController).overlay(alignment:.trailing){Divider()}
                    ZStack {
                        MetalInkCanvas(tool:$tool, penStyle:preset.style, controller:controller, background:effectiveBackground, inverted:inverted, pattern:.blank, zoomPercent:$zoomPercent)
                            .padding(24)
                        toolbar(in:proxy.size)
                            .frame(maxWidth:toolbarIsVertical ? 86 : .infinity,maxHeight:toolbarIsVertical ? .infinity : 72)
                            .background(.regularMaterial)
                            .clipShape(RoundedRectangle(cornerRadius:12,style:.continuous))
                            .overlay{RoundedRectangle(cornerRadius:12,style:.continuous).stroke(.quaternary,lineWidth:1)}
                            .padding(8)
                            .frame(maxWidth:.infinity,maxHeight:.infinity,alignment:toolbarAlignment)
                    }
                }
            }
            .coordinateSpace(name:"board")
            .onAppear {
                if let raw=UserDefaults.standard.string(forKey:"mosuan.toolbarDock"),let saved=ToolbarDock(rawValue:raw){toolbarDock=saved}
                if let raw=UserDefaults.standard.string(forKey:"mosuan.eyeComfortBackground"),let saved=EyeComfortBackground(rawValue:raw){eyeComfortBackground=saved}
                if let saved=UserDefaults.standard.string(forKey:"mosuan.customHex"){customHex=saved}
            }
            .onChange(of:toolbarDock){_,v in UserDefaults.standard.set(v.rawValue,forKey:"mosuan.toolbarDock")}
            .onChange(of:eyeComfortBackground){_,v in UserDefaults.standard.set(v.rawValue,forKey:"mosuan.eyeComfortBackground")}
            .onChange(of:customHex){_,v in UserDefaults.standard.set(v,forKey:"mosuan.customHex")}
        }
        .frame(minWidth:1100,minHeight:700)
    }

    private var toolbarAlignment:Alignment { switch toolbarDock {case .top:.top;case .bottom:.bottom;case .left:.leading;case .right:.trailing} }
    @ViewBuilder private func toolbar(in size:CGSize)->some View {
        if toolbarIsVertical { VStack(spacing:8){toolbarContents}.padding(8) }
        else { HStack(spacing:10){toolbarContents}.padding(.horizontal,12).padding(.vertical,8) }
    }
    @ViewBuilder private var toolbarContents:some View {
        ToolButton(title:"选择",systemImage:"cursorarrow",selected:tool == .select){tool = .select}
        ToolButton(title:"画笔",systemImage:"pencil.tip",selected:tool == .pen){tool = .pen}
        ToolButton(title:"直线",systemImage:"line.diagonal",selected:tool == .line){tool = .line}
        ToolButton(title:"智能直线",systemImage:"scribble.variable",selected:tool == .smartLine){tool = .smartLine}
        ToolButton(title:"橡皮",systemImage:"eraser",selected:tool == .eraser){tool = .eraser}
        ForEach(PenPreset.defaults){item in Button{presetID=item.id;tool = .pen}{Circle().fill(Color(red:item.style.color.red,green:item.style.color.green,blue:item.style.color.blue)).frame(width:18,height:18)}.buttonStyle(.plain).help(item.name)}
        Menu{ForEach(BoardBackground.allCases){item in Button(item.title){background=item;if item != .white{inverted=false}}}}label:{Label("背景",systemImage:"rectangle.fill")}.menuStyle(.borderlessButton)
        if background == .white { Toggle("反色",isOn:$inverted).toggleStyle(.checkbox); if inverted {Menu{ForEach(EyeComfortBackground.allCases){item in Button(item.title){eyeComfortBackground=item}}}label:{Label(eyeComfortBackground.title,systemImage:"moon.fill")}.menuStyle(.borderlessButton)} }
        if controller.hasSelection { TextField("角度",text:Binding(get:{rotationText},set:{rotationText=$0})).frame(width:58).textFieldStyle(.roundedBorder).onSubmit{if let d=Double(rotationText){controller.setRotationDegrees(d)}};Text("°") }
        Button{controller.deleteSelected()}label:{Label("删除",systemImage:"trash")}.disabled(!controller.hasSelection)
        Button{controller.undo()}label:{Label("撤销",systemImage:"arrow.uturn.backward")}.disabled(!controller.canUndo)
        Button{controller.redo()}label:{Label("重做",systemImage:"arrow.uturn.forward")}.disabled(!controller.canRedo)
        Text("\(zoomPercent)%").font(.caption).monospacedDigit()
    }
}
