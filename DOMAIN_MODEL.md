# Mosuan Board 数据模型

## 1. Document

```text
Document
├── id
├── version
├── metadata
├── canvas
├── pages[]
└── settings
```

## 2. Page

```text
Page
├── id
├── size
├── background
├── backgroundPattern
├── pdfContent
├── annotationObjects[]
└── state
```

## 3. Stroke

```text
FreehandStroke
├── id
├── points[]
├── style
└── transform
```

Point：

```text
Point
├── x
├── y
├── pressure
├── timestamp
├── tiltX
├── tiltY
└── pointerType
```

## 4. GraphicObject

```text
GraphicObject
├── id
├── type
├── geometry
├── transform
├── style
└── metadata
```

对象类型至少包括：

- FreehandStroke
- Line
- Arrow
- Polygon
- Rectangle
- Ellipse
- CoordinateSystem
- FunctionGraph
- Group

## 5. Transform

```text
Transform
├── position
├── scale
├── rotation
└── rotationCenter
```

rotationCenter 默认对象中心，也允许用户拖动到任意位置。

## 6. Style

```text
Style
├── strokeColor
├── strokeWidth
├── lineStyle
├── opacity
├── fillEnabled
├── fillColor
└── fillOpacity
```

lineStyle：

- solid
- dashed
- dotted
- dashDot

## 7. Polygon

```text
PolygonGeometry
└── vertices[]
```

每个顶点都可以成为选择状态下的控制点。

三角形无需建立独立底层类型，可以使用 Polygon + `vertexCount=3`。

## 8. FunctionGraph

```text
FunctionGraph
├── expression
├── parameters
├── xRange
├── yRange
├── axes
├── grid
└── style
```

第一种表达式实现：

`y = ax² + bx + c`

## 9. CoordinateSystem

```text
CoordinateSystem
├── xRange
├── yRange
├── xAxis
├── yAxis
├── grid
├── ticks
└── labels
```

## 10. Gallery

图库不是一个 GraphicObject，而是一个可复用对象集合：

```text
GalleryItem
├── id
├── name
├── category
├── thumbnail
├── tags
└── diagram

Diagram
└── objects[]
```

保存图库时保留完整的结构化对象。

## 11. 命令模型

Undo/Redo 可围绕 Command 建立：

```text
Command
├── execute()
└── undo()
```

典型命令：

- CreateObject
- DeleteObject
- UpdateStyle
- TransformObject
- EditVertex
- GroupObjects
- UngroupObjects
- InsertDiagram

## 12. 数据模型原则

- 不以截图作为编辑数据。
- 不以屏幕像素作为几何坐标。
- PDF 与 Annotation 分离。
- 临时对象与持久对象分离。
- 所有对象拥有稳定 ID。
- 数据模型必须可序列化到 `.mosuan`。
- 不让 UI 控件直接成为文档数据模型。
