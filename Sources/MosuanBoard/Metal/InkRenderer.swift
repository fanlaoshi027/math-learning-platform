import Metal
import MetalKit
import simd

final class InkRenderer: NSObject, MTKViewDelegate {
    enum SelectionHandle { case topLeft, topRight, bottomLeft, bottomRight }
    private struct Uniforms { var viewportSize: SIMD2<Float> }
    private struct StoredStroke { var id: UUID; var points: [InkPoint]; var style: PenStyle; var rotation: Float = 0 }
    private struct HistoryState { var strokes: [StoredStroke]; var selection: [Int] }

    private let device: any MTLDevice
    private let commandQueue: any MTLCommandQueue
    private let pipelineState: any MTLRenderPipelineState
    private var committedStrokes: [StoredStroke] = []
    private var activeStroke: [InkPoint] = []
    private var vertices: [InkVertex] = []
    private var vertexBuffer: (any MTLBuffer)?
    private var uniformBuffer: (any MTLBuffer)?
    private var penStyle = PenStyle()
    private(set) var selectedStrokeIndices: [Int] = []
    private var customRotationCenter: SIMD2<Float>?
    private var backgroundColor = SIMD4<Float>(1,1,1,1)
    private var displayInverted = false
    private var backgroundPattern = 0
    private var panOffset = SIMD2<Float>(0,0)
    private var zoomScale: Float = 1
    private var undoStack:[HistoryState]=[]
    private var redoStack:[HistoryState]=[]
    private var transactionStart:HistoryState?

    init?(device:any MTLDevice){
        guard let q=device.makeCommandQueue(),let library=try? device.makeDefaultLibrary(bundle:Bundle.module),let vf=library.makeFunction(name:"inkVertex"),let ff=library.makeFunction(name:"inkFragment") else{return nil}
        let d=MTLRenderPipelineDescriptor();d.vertexFunction=vf;d.fragmentFunction=ff;d.colorAttachments[0].pixelFormat=.bgra8Unorm;d.colorAttachments[0].isBlendingEnabled=true;d.colorAttachments[0].sourceRGBBlendFactor=.sourceAlpha;d.colorAttachments[0].destinationRGBBlendFactor=.oneMinusSourceAlpha;d.colorAttachments[0].sourceAlphaBlendFactor=.sourceAlpha;d.colorAttachments[0].destinationAlphaBlendFactor=.oneMinusSourceAlpha
        guard let p=try? device.makeRenderPipelineState(descriptor:d) else{return nil};self.device=device;commandQueue=q;pipelineState=p;super.init()
    }

    var canUndo:Bool{!undoStack.isEmpty};var canRedo:Bool{!redoStack.isEmpty};var hasSelection:Bool{!selectedStrokeIndices.isEmpty};var selectionCount:Int{selectedStrokeIndices.count};var selectedStrokeIndex:Int?{selectedStrokeIndices.count==1 ? selectedStrokeIndices[0]:nil};var zoomPercent:Int{Int((zoomScale*100).rounded())}
    var selectedRotationDegrees:Double{guard let i=selectedStrokeIndex,committedStrokes.indices.contains(i) else{return 0};return Double(committedStrokes[i].rotation*180/.pi)}

    func setPenStyle(_ s:PenStyle){penStyle=s;rebuildGeometry()};func setBackgroundColor(_ c:SIMD4<Float>){backgroundColor=c;rebuildGeometry()};func setDisplayInverted(_ v:Bool){displayInverted=v;rebuildGeometry()};func setBackgroundPattern(_ p:Int){backgroundPattern=p;rebuildGeometry()};func setStroke(_ p:[InkPoint]){activeStroke=p;rebuildGeometry()}
    func canvasPoint(from p:SIMD2<Float>)->SIMD2<Float>{(p-panOffset)/zoomScale};func viewPoint(from p:SIMD2<Float>)->SIMD2<Float>{p*zoomScale+panOffset};func pan(by d:SIMD2<Float>){panOffset += d;rebuildGeometry()}
    func zoom(by f:Float,around p:SIMD2<Float>){let old=zoomScale,new=min(max(old*f,0.25),4);guard abs(new-old)>0.0001 else{return};let c=canvasPoint(from:p);zoomScale=new;panOffset=p-c*zoomScale;rebuildGeometry()}
    func setZoom(_ v:Float,around p:SIMD2<Float>){let new=min(max(v,0.25),4);guard abs(new-zoomScale)>0.0001 else{return};let c=canvasPoint(from:p);zoomScale=new;panOffset=p-c*zoomScale;rebuildGeometry()}
    func resetZoom(centeredIn size:CGSize){zoomScale=1;panOffset=SIMD2(Float(size.width*0.5),Float(size.height*0.5));rebuildGeometry()}

    func exportPageState()->CanvasPageState{CanvasPageState(strokes:committedStrokes.map{CanvasStroke(id:$0.id,points:$0.points,style:$0.style,rotation:$0.rotation)})}
    func importPageState(_ state:CanvasPageState){committedStrokes=state.strokes.map{StoredStroke(id:$0.id,points:$0.points,style:$0.style,rotation:$0.rotation)};activeStroke=[];selectedStrokeIndices=[];customRotationCenter=nil;undoStack=[];redoStack=[];transactionStart=nil;rebuildGeometry()}
    func beginHistoryTransaction(){if transactionStart==nil{transactionStart=captureState()}}
    func endHistoryTransaction(){guard let before=transactionStart else{return};transactionStart=nil;let after=captureState();if before.strokes != after.strokes || before.selection != after.selection{undoStack.append(before);redoStack.removeAll()}}
    private func recordMutation(){guard transactionStart==nil else{return};undoStack.append(captureState());redoStack.removeAll()};private func captureState()->HistoryState{HistoryState(strokes:committedStrokes,selection:selectedStrokeIndices)};private func restore(_ s:HistoryState){committedStrokes=s.strokes;selectedStrokeIndices=s.selection.filter{committedStrokes.indices.contains($0)};customRotationCenter=nil;rebuildGeometry()}
    func undo(){guard let s=undoStack.popLast() else{return};redoStack.append(captureState());restore(s)};func redo(){guard let s=redoStack.popLast() else{return};undoStack.append(captureState());restore(s)}
    func commitStroke(_ p:[InkPoint]){guard p.count>=2 else{activeStroke=[];rebuildGeometry();return};recordMutation();committedStrokes.append(StoredStroke(id:UUID(),points:p,style:penStyle));selectedStrokeIndices=[];activeStroke=[];rebuildGeometry()}

    @discardableResult func selectStroke(at point:SIMD2<Float>,tolerance:Float=10)->Bool{let c=canvasPoint(from:point),tol=tolerance/zoomScale;var best:Int?;var bd=tol;for i in committedStrokes.indices.reversed(){let s=committedStrokes[i].points;guard s.count>=2 else{continue};for n in 0..<(s.count-1){let d=distanceFromPoint(c,toSegment:SIMD2(s[n].x,s[n].y),SIMD2(s[n+1].x,s[n+1].y));if d<=bd{bd=d;best=i;break}}};selectedStrokeIndices=best.map{[$0]} ?? [];customRotationCenter=nil;rebuildGeometry();return best != nil}
    @discardableResult func toggleStroke(at point:SIMD2<Float>,tolerance:Float=10)->Bool{let c=canvasPoint(from:point),tol=tolerance/zoomScale;var hit:Int?;for i in committedStrokes.indices.reversed(){let s=committedStrokes[i].points;guard s.count>=2 else{continue};for n in 0..<(s.count-1) where distanceFromPoint(c,toSegment:SIMD2(s[n].x,s[n].y),SIMD2(s[n+1].x,s[n+1].y))<=tol{hit=i;break};if hit != nil{break}};guard let i=hit else{return false};if selectedStrokeIndices.contains(i){selectedStrokeIndices.removeAll{$0==i}}else{selectedStrokeIndices.append(i)};rebuildGeometry();return true}
    @discardableResult func selectStrokes(in viewRect:CGRect,fullyContained:Bool=false)->Int{let p0=canvasPoint(from:SIMD2(Float(viewRect.minX),Float(viewRect.minY))),p1=canvasPoint(from:SIMD2(Float(viewRect.maxX),Float(viewRect.maxY)));let r=CGRect(x:CGFloat(min(p0.x,p1.x)),y:CGFloat(min(p0.y,p1.y)),width:CGFloat(abs(p1.x-p0.x)),height:CGFloat(abs(p1.y-p0.y)));let result=committedStrokes.indices.filter{guard let b=strokeBounds(committedStrokes[$0].points) else{return false};return fullyContained ? r.contains(b):r.intersects(b)};selectedStrokeIndices=Array(result);customRotationCenter=nil;rebuildGeometry();return result.count}
    func clearSelection(){selectedStrokeIndices=[];customRotationCenter=nil;rebuildGeometry()}

    func selectionBounds()->CGRect?{let bs=selectedStrokeIndices.compactMap{committedStrokes.indices.contains($0) ? strokeBounds(committedStrokes[$0].points):nil};guard var r=bs.first else{return nil};for b in bs.dropFirst(){r=r.union(b)};return r.insetBy(dx:-8,dy:-8)}
    func selectionBoundsInView()->CGRect?{guard let r=selectionBounds() else{return nil};let a=viewPoint(from:SIMD2(Float(r.minX),Float(r.minY))),b=viewPoint(from:SIMD2(Float(r.maxX),Float(r.maxY)));return CGRect(x:CGFloat(min(a.x,b.x)),y:CGFloat(min(a.y,b.y)),width:CGFloat(abs(b.x-a.x)),height:CGFloat(abs(b.y-a.y)))}
    func selectionCenter()->SIMD2<Float>?{guard let r=selectionBounds() else{return nil};return SIMD2(Float(r.midX),Float(r.midY))}
    func setRotationCenter(to viewPoint:SIMD2<Float>){customRotationCenter=canvasPoint(from:viewPoint);rebuildGeometry()};func rotationCenterViewPoint()->SIMD2<Float>?{guard let c=customRotationCenter ?? selectionCenter() else{return nil};return viewPoint(from:c)}

    func selectionHandle(at point:SIMD2<Float>,tolerance:Float=10)->SelectionHandle?{guard let r=selectionBounds() else{return nil};let p=canvasPoint(from:point),t=tolerance/zoomScale;let h:[(SelectionHandle,SIMD2<Float>)]=[(.topLeft,SIMD2(Float(r.minX),Float(r.minY))),(.topRight,SIMD2(Float(r.maxX),Float(r.minY))),(.bottomLeft,SIMD2(Float(r.minX),Float(r.maxY))),(.bottomRight,SIMD2(Float(r.maxX),Float(r.maxY)))];return h.first{simd_distance(p,$0.1)<=t}?.0}
    func rotationHandle(at point:SIMD2<Float>,tolerance:Float=12)->Bool{guard let r=selectionBounds() else{return false};let p=canvasPoint(from:point),c=SIMD2(Float(r.midX),Float(r.minY-28));return simd_distance(p,c)<=tolerance/zoomScale}
    func rotationCenterHandle(at point:SIMD2<Float>,tolerance:Float=12)->Bool{guard customRotationCenter != nil,let c=rotationCenterViewPoint() else{return false};return simd_distance(point,c)<=tolerance}

    func resizeSelected(handle:SelectionHandle,to point:SIMD2<Float>){guard !selectedStrokeIndices.isEmpty,let r=selectionBounds() else{return};let p=canvasPoint(from:point);let a:SIMD2<Float>;switch handle{case .topLeft:a=SIMD2(Float(r.maxX),Float(r.maxY));case .topRight:a=SIMD2(Float(r.minX),Float(r.maxY));case .bottomLeft:a=SIMD2(Float(r.maxX),Float(r.minY));case .bottomRight:a=SIMD2(Float(r.minX),Float(r.minY))};let ow=max(Float(r.width),1),oh=max(Float(r.height),1),sx=max(abs(p.x-a.x),1)/ow,sy=max(abs(p.y-a.y),1)/oh;for i in selectedStrokeIndices where committedStrokes.indices.contains(i){committedStrokes[i].points=committedStrokes[i].points.map{InkPoint(x:a.x+($0.x-a.x)*sx,y:a.y+($0.y-a.y)*sy,pressure:$0.pressure)}};rebuildGeometry()}
    func scaleSelected(by f:Float){guard f>0,let c=selectionCenter() else{return};for i in selectedStrokeIndices where committedStrokes.indices.contains(i){committedStrokes[i].points=committedStrokes[i].points.map{InkPoint(x:c.x+($0.x-c.x)*f,y:c.y+($0.y-c.y)*f,pressure:$0.pressure)}};rebuildGeometry()}
    func moveSelected(by d:SIMD2<Float>){for i in selectedStrokeIndices where committedStrokes.indices.contains(i){committedStrokes[i].points=committedStrokes[i].points.map{InkPoint(x:$0.x+d.x,y:$0.y+d.y,pressure:$0.pressure)}};rebuildGeometry()}
    func rotateSelected(to point:SIMD2<Float>,from previous:SIMD2<Float>){guard let c=customRotationCenter ?? selectionCenter() else{return};let p=canvasPoint(from:point),q=canvasPoint(from:previous);rotateSelected(by:atan2(p.y-c.y,p.x-c.x)-atan2(q.y-c.y,q.x-c.x),center:c)}
    func setSelectedRotationDegrees(_ degrees:Double){guard let c=selectionCenter() else{return};rotateSelected(by:Float(degrees-selectedRotationDegrees)*.pi/180,center:c)}
    private func rotateSelected(by d:Float,center c:SIMD2<Float>){let co=cos(d),si=sin(d);for i in selectedStrokeIndices where committedStrokes.indices.contains(i){committedStrokes[i].points=committedStrokes[i].points.map{let v=SIMD2($0.x,$0.y)-c;return InkPoint(x:v.x*co-v.y*si+c.x,y:v.x*si+v.y*co+c.y,pressure:$0.pressure)};committedStrokes[i].rotation += d};rebuildGeometry()}
    func reflectSelected(horizontal:Bool){guard let c=selectionCenter() else{return};for i in selectedStrokeIndices where committedStrokes.indices.contains(i){committedStrokes[i].points=committedStrokes[i].points.map{horizontal ? InkPoint(x:2*c.x-$0.x,y:$0.y,pressure:$0.pressure):InkPoint(x:$0.x,y:2*c.y-$0.y,pressure:$0.pressure)}};rebuildGeometry()}
    func deleteSelected(){guard !selectedStrokeIndices.isEmpty else{return};recordMutation();for i in selectedStrokeIndices.sorted(by:>).where({committedStrokes.indices.contains($0)}){committedStrokes.remove(at:i)};selectedStrokeIndices=[];customRotationCenter=nil;rebuildGeometry()}

    func mtkView(_ view:MTKView,drawableSizeWillChange size:CGSize){updateUniformBuffer(for:size)}
    func draw(in view:MTKView){guard let d=view.currentRenderPassDescriptor,let drawable=view.currentDrawable,let cb=commandQueue.makeCommandBuffer(),let e=cb.makeRenderCommandEncoder(descriptor:d) else{return};d.colorAttachments[0].clearColor=MTLClearColor(red:Double(backgroundColor.x),green:Double(backgroundColor.y),blue:Double(backgroundColor.z),alpha:1);updateUniformBuffer(for:view.drawableSize);e.setRenderPipelineState(pipelineState);if let b=vertexBuffer{e.setVertexBuffer(b,offset:0,index:0)};if let b=uniformBuffer{e.setVertexBuffer(b,offset:0,index:1)};if !vertices.isEmpty{e.drawPrimitives(type:.triangle,vertexStart:0,vertexCount:vertices.count)};e.endEncoding();cb.present(drawable);cb.commit()}

    private func viewPosition(_ p:SIMD2<Float>)->SIMD2<Float>{p*zoomScale+panOffset}
    private func rebuildGeometry(){var o:[InkVertex]=[];appendBackgroundPattern(to:&o);if let r=selectionBounds(){for i in committedStrokes.indices{let s=committedStrokes[i];appendStrokeGeometry(s.points,style:s.style,to:&o);if selectedStrokeIndices.contains(i){appendSelectionBounds(r,to:&o)}}}else{for s in committedStrokes{appendStrokeGeometry(s.points,style:s.style,to:&o)}};if !activeStroke.isEmpty{appendStrokeGeometry(activeStroke,style:penStyle,to:&o)};vertices=o;vertexBuffer=vertices.isEmpty ? nil:device.makeBuffer(bytes:vertices,length:vertices.count*MemoryLayout<InkVertex>.stride,options:.storageModeShared)}
    private func appendBackgroundPattern(to o:inout[InkVertex]){guard backgroundPattern != 0 else{return};let c=displayInverted ? SIMD4<Float>(0.24,0.24,0.24,0.55):SIMD4<Float>(0.82,0.84,0.88,0.55),step:Float=backgroundPattern==3 ? 24:32,ext:Float=4000;if backgroundPattern==1{var y:Float = -ext;while y<=ext{appendLineQuad(viewPosition(SIMD2(-ext,y)),viewPosition(SIMD2(ext,y)),width:0.55*zoomScale,color:c,to:&o);y+=step}}else if backgroundPattern==3{var y:Float = -ext;while y<=ext{var x:Float = -ext;while x<=ext{appendDisk(center:viewPosition(SIMD2(x,y)),radius:1.1*zoomScale,color:c,to:&o);x+=step};y+=step}}else{var x:Float = -ext;while x<=ext{appendLineQuad(viewPosition(SIMD2(x,-ext)),viewPosition(SIMD2(x,ext)),width:0.45*zoomScale,color:c,to:&o);x+=step};var y:Float = -ext;while y<=ext{appendLineQuad(viewPosition(SIMD2(-ext,y)),viewPosition(SIMD2(ext,y)),width:0.45*zoomScale,color:c,to:&o);y+=step}}}
    private func appendStrokeGeometry(_ s:[InkPoint],style:PenStyle,to o:inout[InkVertex]){guard !s.isEmpty else{return};let c=metalColor(style);if s.count>1{for i in 0..<(s.count-1){let p0=s[i],p1=s[i+1],dx=p1.x-p0.x,dy=p1.y-p0.y,l=max(sqrt(dx*dx+dy*dy),0.001),nx=-dy/l,ny=dx/l,w0=strokeWidth(p0.pressure,style:style),w1=strokeWidth(p1.pressure,style:style);let a=viewPosition(SIMD2(p0.x+nx*w0,p0.y+ny*w0)),b=viewPosition(SIMD2(p0.x-nx*w0,p0.y-ny*w0)),cc=viewPosition(SIMD2(p1.x+nx*w1,p1.y+ny*w1)),d=viewPosition(SIMD2(p1.x-nx*w1,p1.y-ny*w1));appendTriangle(a,b,cc,color:c,to:&o);appendTriangle(cc,b,d,color:c,to:&o)}};for p in s{appendDisk(center:viewPosition(SIMD2(p.x,p.y)),radius:strokeWidth(p.pressure,style:style),color:c,to:&o)}}
    private func appendSelectionBounds(_ r:CGRect,to o:inout[InkVertex]){let c=SIMD4<Float>(0.1,0.45,1,0.75),x0=Float(r.minX)*zoomScale+panOffset.x,x1=Float(r.maxX)*zoomScale+panOffset.x,y0=Float(r.minY)*zoomScale+panOffset.y,y1=Float(r.maxY)*zoomScale+panOffset.y,a=SIMD2(x0,y0),b=SIMD2(x1,y0),cc=SIMD2(x1,y1),d=SIMD2(x0,y1);appendLineQuad(a,b,width:1.5,color:c,to:&o);appendLineQuad(b,cc,width:1.5,color:c,to:&o);appendLineQuad(cc,d,width:1.5,color:c,to:&o);appendLineQuad(d,a,width:1.5,color:c,to:&o);for p in [a,b,cc,d]{appendDisk(center:p,radius:5,color:c,to:&o);appendDisk(center:p,radius:2.5,color:SIMD4<Float>(1,1,1,1),to:&o)};let rot=SIMD2((x0+x1)*0.5,y0-28);appendLineQuad(SIMD2((x0+x1)*0.5,y0),rot,width:1,color:c,to:&o);appendDisk(center:rot,radius:7,color:c,to:&o);appendDisk(center:rot,radius:3,color:SIMD4<Float>(1,1,1,1),to:&o);if let rc=customRotationCenter{let p=viewPosition(rc);appendDisk(center:p,radius:7,color:SIMD4<Float>(0.95,0.55,0.05,1),to:&o);appendDisk(center:p,radius:3,color:SIMD4<Float>(1,1,1,1),to:&o)}}
    private func strokeBounds(_ p:[InkPoint])->CGRect?{guard let f=p.first else{return nil};var minX=f.x,maxX=f.x,minY=f.y,maxY=f.y;for q in p{minX=min(minX,q.x);maxX=max(maxX,q.x);minY=min(minY,q.y);maxY=max(maxY,q.y)};return CGRect(x:CGFloat(minX),y:CGFloat(minY),width:CGFloat(maxX-minX),height:CGFloat(maxY-minY))}
    private func appendLineQuad(_ a:SIMD2<Float>,_ b:SIMD2<Float>,width:Float,color:SIMD4<Float>,to o:inout[InkVertex]){let d=b-a,l=max(simd_length(d),0.001),n=SIMD2(-d.y,d.x)/l*width;appendTriangle(a+n,a-n,b+n,color:color,to:&o);appendTriangle(b+n,a-n,b-n,color:color,to:&o)}
    private func distanceFromPoint(_ p:SIMD2<Float>,toSegment a:SIMD2<Float>,_ b:SIMD2<Float>)->Float{let ab=b-a,l=simd_length_squared(ab);if l<0.0001{return simd_distance(p,a)};let t=max(0,min(1,simd_dot(p-a,ab)/l));return simd_distance(p,a+ab*t)}
    private func strokeWidth(_ p:Float,style:PenStyle)->Float{let x=max(0,min(1,p)),c=style.pressureEnabled ? pow(x,max(0.25,Float(style.pressureCurve))):0.75;return Float(style.width)*(0.45+0.75*c)}
    private func metalColor(_ s:PenStyle)->SIMD4<Float>{var c=SIMD4(Float(s.color.red),Float(s.color.green),Float(s.color.blue),Float(s.color.alpha*s.opacity));if displayInverted{let hi=max(c.x,max(c.y,c.z)),lo=min(c.x,min(c.y,c.z));if hi<0.12 || lo>0.88{c=SIMD4(1,1,1,c.w)}};return c}
    private func appendTriangle(_ a:SIMD2<Float>,_ b:SIMD2<Float>,_ c:SIMD2<Float>,color:SIMD4<Float>,to o:inout[InkVertex]){o.append(InkVertex(position:a,color:color));o.append(InkVertex(position:b,color:color));o.append(InkVertex(position:c,color:color))}
    private func appendDisk(center:SIMD2<Float>,radius:Float,color:SIMD4<Float>,to o:inout[InkVertex]){let n=12;for i in 0..<n{let a=Float(i)/Float(n)*2*.pi,b=Float(i+1)/Float(n)*2*.pi;appendTriangle(center,center+SIMD2(cos(a),sin(a))*radius,center+SIMD2(cos(b),sin(b))*radius,color:color,to:&o)}}
    private func updateUniformBuffer(for size:CGSize){let u=Uniforms(viewportSize:SIMD2(Float(max(size.width,1)),Float(max(size.height,1))));if uniformBuffer==nil{uniformBuffer=device.makeBuffer(length:MemoryLayout<Uniforms>.stride,options:.storageModeShared)};guard let b=uniformBuffer else{return};withUnsafeBytes(of:u){memcpy(b.contents(),$0.baseAddress!,MemoryLayout<Uniforms>.stride)}}
}
