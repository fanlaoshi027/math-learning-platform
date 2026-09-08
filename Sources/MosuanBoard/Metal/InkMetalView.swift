import AppKit
import MetalKit
import simd

final class InkMetalView: MTKView {
    private let renderer: InkRenderer
    private var points:[InkPoint]=[]
    private var eraserPoints:[SIMD2<Float>]=[]
    private var active=false
    private var selectionDrag=false
    private var resizeHandle:InkRenderer.SelectionHandle?
    private var rotationDrag=false
    private var panDrag=false
    private var spaceHeld=false
    private var lastPoint=SIMD2<Float>(0,0)
    private var lastRotationPoint=SIMD2<Float>(0,0)
    private var smartLineDetected=false
    private var smartLineWorkItem:DispatchWorkItem?

    var isUserInteractionEnabledForTool=true
    var isSelectionTool=false
    var isLineTool=false
    var isSmartLineTool=false
    var isEraserTool=false
    var backgroundPattern=0 { didSet { renderer.setBackgroundPattern(backgroundPattern) } }
    var onHistoryChanged:(()->Void)?
    var onSelectionChanged:(()->Void)?
    var onPageStateChanged:((CanvasPageState)->Void)?
    var penStyle=PenStyle(){didSet{renderer.setPenStyle(penStyle)}}
    var boardBackground:SIMD4<Float>=SIMD4(1,1,1,1){didSet{renderer.setBackgroundColor(boardBackground)}}
    var displayInverted=false{didSet{renderer.setDisplayInverted(displayInverted)}}
    var canUndo:Bool{renderer.canUndo};var canRedo:Bool{renderer.canRedo};var hasSelection:Bool{renderer.hasSelection};var selectedRotationDegrees:Double{renderer.selectedRotationDegrees}
    override var isFlipped:Bool{true};override var acceptsFirstResponder:Bool{true}

    init(frame frameRect:NSRect = .zero){guard let device=MTLCreateSystemDefaultDevice(),let renderer=InkRenderer(device:device)else{fatalError("Metal is unavailable on this Mac")};self.renderer=renderer;super.init(frame:frameRect,device:device);configureMetal();renderer.setPenStyle(penStyle)}
    required init(coder:NSCoder){guard let device=MTLCreateSystemDefaultDevice(),let renderer=InkRenderer(device:device)else{fatalError("Metal is unavailable on this Mac")};self.renderer=renderer;super.init(coder:coder);self.device=device;configureMetal();renderer.setPenStyle(penStyle)}
    private func configureMetal(){delegate=renderer;isPaused=true;enableSetNeedsDisplay=true;framebufferOnly=true;colorPixelFormat=.bgra8Unorm;clearColor=MTLClearColor(red:1,green:1,blue:1,alpha:1)}

    func loadPageState(_ state:CanvasPageState){renderer.importPageState(state);onHistoryChanged?();onSelectionChanged?();draw()}
    func currentPageState()->CanvasPageState{renderer.exportPageState()}
    func undo(){renderer.undo();notifyState();draw()};func redo(){renderer.redo();notifyState();draw()};func deleteSelected(){renderer.deleteSelected();notifyState();draw()};func setSelectedRotationDegrees(_ degrees:Double){renderer.setSelectedRotationDegrees(degrees);notifyState();draw()}
    private func notifyState(){onHistoryChanged?();onSelectionChanged?();onPageStateChanged?(renderer.exportPageState())}

    override func mouseDown(with event:NSEvent){window?.makeFirstResponder(self);let p=makePoint(from:event)
        if spaceHeld {panDrag=true;lastPoint=p;return}
        if isEraserTool {renderer.beginHistoryTransaction();eraserPoints=[p];return}
        if isSelectionTool {if renderer.rotationHandle(at:p){renderer.beginHistoryTransaction();rotationDrag=true;lastRotationPoint=p;return};if let h=renderer.selectionHandle(at:p){renderer.beginHistoryTransaction();resizeHandle=h;lastPoint=p;return};selectionDrag=renderer.selectStroke(at:p);lastPoint=p;onSelectionChanged?();draw();return}
        guard isUserInteractionEnabledForTool else{return};renderer.beginHistoryTransaction();active=true;smartLineDetected=false;smartLineWorkItem?.cancel();points=[InkPoint(x:p.x,y:p.y,pressure:event.pressure>0 ? Float(event.pressure):1)];renderer.setStroke(points);draw()
    }
    override func mouseDragged(with event:NSEvent){let p=makePoint(from:event)
        if panDrag {renderer.pan(by:p-lastPoint);lastPoint=p;draw();return}
        if isEraserTool {eraserPoints.append(p);return}
        if isSelectionTool {if rotationDrag{renderer.rotateSelected(to:p,from:lastRotationPoint);lastRotationPoint=p;onSelectionChanged?();draw();return};if resizeHandle != nil{renderer.resizeSelected(handle:resizeHandle!,to:p);draw();return};guard selectionDrag else{return};let d=p-lastPoint;if simd_length_squared(d)>0{renderer.beginHistoryTransaction();renderer.moveSelected(by:d);lastPoint=p;draw()};return}
        guard isUserInteractionEnabledForTool && active else{return};let pressure=event.pressure>0 ? Float(event.pressure):(points.last?.pressure ?? 1);points.append(InkPoint(x:p.x,y:p.y,pressure:pressure));if isLineTool || (isSmartLineTool && smartLineDetected){renderer.setStroke(linePreview(from:points))}else{renderer.setStroke(points)};scheduleSmartLineDetection();draw()
    }
    override func mouseUp(with event:NSEvent){smartLineWorkItem?.cancel();let p=makePoint(from:event)
        if panDrag{panDrag=false;return}
        if isEraserTool{eraserPoints.append(p);eraseAlongPath(eraserPoints);eraserPoints.removeAll(keepingCapacity:true);renderer.endHistoryTransaction();notifyState();return}
        if isSelectionTool{rotationDrag=false;resizeHandle=nil;selectionDrag=false;renderer.endHistoryTransaction();notifyState();return}
        guard isUserInteractionEnabledForTool && active else{return};let pressure=event.pressure>0 ? Float(event.pressure):(points.last?.pressure ?? 1);points.append(InkPoint(x:p.x,y:p.y,pressure:pressure));let committed=(isLineTool || (isSmartLineTool && smartLineDetected)) ? linePreview(from:points):points;renderer.commitStroke(committed);renderer.endHistoryTransaction();points.removeAll(keepingCapacity:true);active=false;smartLineDetected=false;renderer.setStroke([]);notifyState();draw()
    }
    override func keyDown(with event:NSEvent){if event.keyCode==49{spaceHeld=true;return};if isSelectionTool && event.keyCode==51{deleteSelected();return};super.keyDown(with:event)}
    override func keyUp(with event:NSEvent){if event.keyCode==49{spaceHeld=false;return};super.keyUp(with:event)}
    override func tabletPoint(with event:NSEvent){switch event.phase{case .began:mouseDown(with:event);case .changed:mouseDragged(with:event);case .ended:mouseUp(with:event);case .cancelled:smartLineWorkItem?.cancel();active=false;renderer.setStroke([]);renderer.endHistoryTransaction();draw();default:break}}

    private func scheduleSmartLineDetection(){guard isSmartLineTool,points.count>=4 else{return};smartLineWorkItem?.cancel();let work=DispatchWorkItem{[weak self] in guard let self,self.active,self.isSmartLineTool,self.points.count>=4 else{return};if self.isLikelyStraightLine(self.points){self.smartLineDetected=true;self.renderer.setStroke(self.linePreview(from:self.points));self.draw()}};smartLineWorkItem=work;DispatchQueue.main.asyncAfter(deadline:.now()+0.18,execute:work)}
    private func isLikelyStraightLine(_ p:[InkPoint])->Bool{guard let f=p.first,let l=p.last else{return false};let a=SIMD2(f.x,f.y),b=SIMD2(l.x,l.y),len=simd_distance(a,b);guard len>30 else{return false};let tol=max(7,len*0.075);return p.dropFirst().dropLast().allSatisfy{distanceToSegment(SIMD2($0.x,$0.y),a,b)<=tol}}
    private func linePreview(from p:[InkPoint])->[InkPoint]{guard let f=p.first,let l=p.last else{return p};return[f,l]}
    private func eraseAlongPath(_ path:[SIMD2<Float>]){guard !path.isEmpty else{return};var deleted=false;for p in path{if renderer.selectStroke(at:p,tolerance:16){renderer.deleteSelected();deleted=true}};guard path.count>=8 else{if deleted{draw()};return};var minX=path[0].x,maxX=path[0].x,minY=path[0].y,maxY=path[0].y;for p in path{minX=min(minX,p.x);maxX=max(maxX,p.x);minY=min(minY,p.y);maxY=max(maxY,p.y)};let w=maxX-minX,h=maxY-minY;guard w>20,h>20 else{if deleted{draw()};return};var y=minY+6;while y<maxY{var x=minX+6;while x<maxX{let dx=(x-(minX+maxX)*0.5)/max(w*0.5,1),dy=(y-(minY+maxY)*0.5)/max(h*0.5,1);if dx*dx+dy*dy<=1.15,renderer.selectStroke(at:SIMD2(x,y),tolerance:10){renderer.deleteSelected();deleted=true};x += 12};y += 12};if deleted{draw()}}
    private func distanceToSegment(_ p:SIMD2<Float>,_ a:SIMD2<Float>,_ b:SIMD2<Float>)->Float{let ab=b-a,l=simd_length_squared(ab);if l<0.0001{return simd_distance(p,a)};let t=max(0,min(1,simd_dot(p-a,ab)/l));return simd_distance(p,a+ab*t)}
    private func makePoint(from event:NSEvent)->SIMD2<Float>{let p=convert(event.locationInWindow,from:nil);return SIMD2(Float(p.x),Float(p.y))}
}
