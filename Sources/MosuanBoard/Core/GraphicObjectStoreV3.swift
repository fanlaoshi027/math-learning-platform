import CoreGraphics
import Foundation

final class GraphicObjectStore {
    private(set) var objects:[GraphicObject]=[]
    func clear(){objects.removeAll(keepingCapacity:true)}
    @discardableResult func addLine(from start:CGPoint,to end:CGPoint,style:GraphicObject.Style)->UUID { let o=GraphicObject.line(from:start,to:end,style:style); objects.append(o); return o.id }
    @discardableResult func addPolygon(points:[CGPoint],style:GraphicObject.Style)->UUID { let o=GraphicObject.polygon(points:points,style:style); objects.append(o); return o.id }
    func object(with id:UUID)->GraphicObject?{objects.first{$0.id==id}}
    func update(_ object:GraphicObject){guard let i=objects.firstIndex(where:{$0.id==object.id}) else{return};objects[i]=object}
    func remove(id:UUID){objects.removeAll{$0.id == id}}
    func transformedPoints(of object:GraphicObject)->[CGPoint]{object.geometry.points.map{transformedPoint($0,in:object)}}
    func transformedPoints(of id:UUID)->[CGPoint]?{guard let o=object(with:id) else{return nil};return transformedPoints(of:o)}
    func nearestLineEndpoint(to point:CGPoint,tolerance:CGFloat=12)->(id:UUID,endpoint:Int,distance:CGFloat)?{var best:(id:UUID,endpoint:Int,distance:CGFloat)?;for o in objects where o.kind == .line {guard o.geometry.points.count>=2 else{continue};for e in 0...1{let p=transformedPoint(o.geometry.points[e],in:o),d=hypot(p.x-point.x,p.y-point.y);if d<=tolerance && (best == nil || d < best!.distance){best=(o.id,e,d)}}};return best}
    func nearestPolygonVertex(to point:CGPoint,tolerance:CGFloat=14)->(id:UUID,index:Int,distance:CGFloat)?{var best:(id:UUID,index:Int,distance:CGFloat)?;for o in objects where o.kind == .polygon {for (index,vertex) in o.geometry.points.enumerated(){let p=transformedPoint(vertex,in:o),d=hypot(p.x-point.x,p.y-point.y);if d<=tolerance && (best == nil || d < best!.distance){best=(o.id,index,d)}}};return best}
    @discardableResult func movePolygonVertex(id:UUID,index:Int,to point:CGPoint)->Bool{guard let i=objects.firstIndex(where:{$0.id==id}),objects[i].kind == .polygon,objects[i].geometry.points.indices.contains(index) else{return false};let local=inverseTransformedPoint(point,in:objects[i]);objects[i].geometry.points[index]=local;return true}
    func bounds(of id:UUID)->CGRect?{guard let o=object(with:id) else{return nil};return bounds(of:o)}
    func bounds(of object:GraphicObject)->CGRect?{if object.kind == .rectangle || object.kind == .ellipse || object.kind == .coordinateSystem || object.kind == .functionGraph {let a=transformedPoint(CGPoint(x:object.geometry.x,y:object.geometry.y),in:object),b=transformedPoint(CGPoint(x:object.geometry.x+object.geometry.width,y:object.geometry.y+object.geometry.height),in:object);return CGRect(x:min(a.x,b.x),y:min(a.y,b.y),width:abs(a.x-b.x),height:abs(a.y-b.y)).insetBy(dx:-max(1,object.style.strokeWidth),dy:-max(1,object.style.strokeWidth))};let p=transformedPoints(of:object);guard let first=p.first else{return nil};var r=CGRect(origin:first,size:.zero);for q in p.dropFirst(){r=r.union(CGRect(origin:q,size:.zero))};return r.insetBy(dx:-max(1,object.style.strokeWidth),dy:-max(1,object.style.strokeWidth))}
    func hitTest(at point:CGPoint,tolerance:CGFloat=10)->UUID?{for o in objects.reversed(){if hitTest(o,at:point,tolerance:tolerance){return o.id}};return nil}
    private func hitTest(_ o:GraphicObject,at point:CGPoint,tolerance:CGFloat)->Bool{let t=tolerance+o.style.strokeWidth;switch o.kind{case .line,.arrow:let p=transformedPoints(of:o);return p.count>=2 && distance(point,to:p[0],segment:p[1])<=t;case .polygon:let p=transformedPoints(of:o);guard p.count>=3 else{return false};if pointInPolygon(point,p){return true};var edges=Array(p.dropFirst());edges.append(p[0]);return zip(p,edges).contains{distance(point,to:$0.0,segment:$0.1)<=t};case .rectangle:guard let r=bounds(of:o) else{return false};return r.insetBy(dx:-t,dy:-t).contains(point);case .ellipse:guard let r=bounds(of:o) else{return false};let rx=max(r.width/2,0.001),ry=max(r.height/2,0.001);return pow((point.x-r.midX)/rx,2)+pow((point.y-r.midY)/ry,2)<=1+t/max(rx,ry);case .freehandStroke:let p=transformedPoints(of:o);return zip(p,p.dropFirst()).contains{distance(point,to:$0.0,segment:$0.1)<=t};case .coordinateSystem,.functionGraph:return bounds(of:o)?.insetBy(dx:-t,dy:-t).contains(point) ?? false;case .group:return o.children.contains{hitTest($0,at:point,tolerance:tolerance)}}}
    func objectsIntersecting(_ rect:CGRect,fullyContained:Bool=false)->[UUID]{objects.compactMap{guard let b=bounds(of:$0) else{return nil};return (fullyContained ? rect.contains(b) : rect.intersects(b)) ? $0.id : nil}}
    /// Returns structured objects touched by a freeform closed lasso.
    /// Geometry is tested rather than relying only on object bounding boxes.
    func objectsIntersectingLasso(_ lasso:[CGPoint])->[UUID]{
        guard lasso.count >= 3, let lassoBounds = bounds(ofPoints: lasso) else { return [] }
        return objects.compactMap { object in
            guard let objectBounds = bounds(of: object), objectBounds.intersects(lassoBounds) else { return nil }
            if lasso.contains(where: { pointInPolygon($0, lasso) && objectHitGeometry(object, at: $0, tolerance: max(1, object.style.strokeWidth)) }) { return object.id }
            let geometry = transformedPoints(of: object)
            if geometry.contains(where: { pointInPolygon($0, lasso) }) { return object.id }
            if geometry.count >= 2 {
                let pairs = object.kind == .polygon ? zip(geometry, geometry.dropFirst() + [geometry[0]]) : zip(geometry, geometry.dropFirst())
                if pairs.contains(where: { segmentIntersectsPolygon($0.0, $0.1, lasso) }) { return object.id }
            }
            if object.kind == .polygon, geometry.count >= 3, pointInPolygon(lasso[0], geometry) { return object.id }
            if object.kind == .rectangle || object.kind == .ellipse || object.kind == .coordinateSystem || object.kind == .functionGraph {
                if lasso.contains(where: { bounds(of: object)?.contains($0) ?? false }) { return object.id }
            }
            return nil
        }
    }
    func eraseByScribble(_ path:[CGPoint],tolerance:CGFloat=12)->[UUID]{guard path.count>=2 else{return[]};let ids=Set(objects.filter{guard let b=bounds(of:$0) else{return false};let e=b.insetBy(dx:-tolerance,dy:-tolerance);return path.contains(where:e.contains) || zip(path,path.dropFirst()).contains{e.intersects(CGRect(x:min($0.x,$1.x),y:min($0.y,$1.y),width:abs($0.x-$1.x),height:abs($0.y-$1.y)))} }.map(\.id));objects.removeAll{ids.contains($0.id)};return Array(ids)}
    @discardableResult func moveLineEndpoint(id:UUID,endpoint:Int,to point:CGPoint)->Bool{guard let i=objects.firstIndex(where:{$0.id == id}),objects[i].kind == .line,objects[i].geometry.points.count>=2,(0...1).contains(endpoint) else{return false};objects[i].geometry.points[endpoint]=point;return true}
    @discardableResult func transform(id:UUID,scale:CGSize?=nil,rotation:CGFloat?=nil,position:CGPoint?=nil)->Bool{guard let i=objects.firstIndex(where:{$0.id==id}) else{return false};if let scale{objects[i].transform.scale=scale};if let rotation{objects[i].transform.rotation=rotation};if let position{objects[i].transform.position=position};return true}
    func exportObjects()->[GraphicObject]{objects}
    func importObjects(_ value:[GraphicObject]){objects=value}
    private func transformedPoint(_ p:CGPoint,in o:GraphicObject)->CGPoint{let c=o.transform.rotationCenter,x=(p.x-c.x)*o.transform.scale.width,y=(p.y-c.y)*o.transform.scale.height,co=cos(o.transform.rotation),si=sin(o.transform.rotation);return CGPoint(x:x*co-y*si+c.x+o.transform.position.x,y:x*si+y*co+c.y+o.transform.position.y)}
    private func inverseTransformedPoint(_ p:CGPoint,in o:GraphicObject)->CGPoint{let c=o.transform.rotationCenter;let x=p.x-c.x-o.transform.position.x;let y=p.y-c.y-o.transform.position.y;let co=cos(o.transform.rotation),si=sin(o.transform.rotation);let rx=x*co+y*si;let ry=-x*si+y*co;let sx=abs(o.transform.scale.width)>0.0001 ? o.transform.scale.width : 1;let sy=abs(o.transform.scale.height)>0.0001 ? o.transform.scale.height : 1;return CGPoint(x:rx/sx+c.x,y:ry/sy+c.y)}
    private func distance(_ p:CGPoint,to a:CGPoint,segment b:CGPoint)->CGFloat{let dx=b.x-a.x,dy=b.y-a.y,l2=dx*dx+dy*dy;if l2==0{return hypot(p.x-a.x,p.y-a.y)};let t=max(0,min(1,((p.x-a.x)*dx+(p.y-a.y)*dy)/l2));return hypot(p.x-(a.x+t*dx),p.y-(a.y+t*dy))}
    private func pointInPolygon(_ p:CGPoint,_ poly:[CGPoint])->Bool{var inside=false;var j=poly.count-1;for i in poly.indices{let a=poly[i],b=poly[j];if (a.y>p.y) != (b.y>p.y){let d=b.y-a.y;if d != 0 {let x=(b.x-a.x)*(p.y-a.y)/d+a.x;if p.x<x{inside.toggle()}}};j=i};return inside}
    private func bounds(ofPoints points:[CGPoint])->CGRect?{guard let first=points.first else{return nil};var r=CGRect(origin:first,size:.zero);for p in points.dropFirst(){r=r.union(CGRect(origin:p,size:.zero))};return r}
    private func objectHitGeometry(_ object:GraphicObject,at point:CGPoint,tolerance:CGFloat)->Bool{hitTest(object,at:point,tolerance:tolerance)}
    private func segmentIntersectsPolygon(_ a:CGPoint,_ b:CGPoint,_ polygon:[CGPoint])->Bool{if pointInPolygon(a,polygon)||pointInPolygon(b,polygon){return true};var edges=Array(polygon.dropFirst());edges.append(polygon[0]);return zip(polygon,edges).contains{segmentsIntersect(a,b,$0.0,$0.1)}}
    private func segmentsIntersect(_ a:CGPoint,_ b:CGPoint,_ c:CGPoint,_ d:CGPoint)->Bool{func cross(_ p:CGPoint,_ q:CGPoint,_ r:CGPoint)->CGFloat{(q.x-p.x)*(r.y-p.y)-(q.y-p.y)*(r.x-p.x)};let o1=cross(a,b,c),o2=cross(a,b,d),o3=cross(c,d,a),o4=cross(c,d,b);let eps:CGFloat=0.001;if abs(o1)<eps && onSegment(a,b,c){return true};if abs(o2)<eps && onSegment(a,b,d){return true};if abs(o3)<eps && onSegment(c,d,a){return true};if abs(o4)<eps && onSegment(c,d,b){return true};return (o1 > 0) != (o2 > 0) && (o3 > 0) != (o4 > 0)}
    private func onSegment(_ a:CGPoint,_ b:CGPoint,_ p:CGPoint)->Bool{p.x >= min(a.x,b.x)-0.001 && p.x <= max(a.x,b.x)+0.001 && p.y >= min(a.y,b.y)-0.001 && p.y <= max(a.y,b.y)+0.001}
}
