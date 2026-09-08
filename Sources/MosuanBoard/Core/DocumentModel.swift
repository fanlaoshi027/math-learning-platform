import Foundation

/// Persistent page metadata. The actual ink/object layer will be attached to this page model
/// as the document engine is expanded.
struct BoardPage: Identifiable, Codable, Equatable {
    let id: UUID
    var title: String
    var width: Double
    var height: Double
    var background: String
    var pattern: Int

    init(
        id: UUID = UUID(),
        title: String = "第 1 页",
        width: Double = 1280,
        height: Double = 720,
        background: String = "white",
        pattern: Int = 0
    ) {
        self.id = id
        self.title = title
        self.width = width
        self.height = height
        self.background = background
        self.pattern = pattern
    }
}

/// The document-level page container. Page content is deliberately kept separate from
/// the UI so PDF pages, whiteboard pages and future .mosuan persistence can share it.
struct MosuanDocument: Identifiable, Codable, Equatable {
    let id: UUID
    var title: String
    var pages: [BoardPage]
    var currentPageIndex: Int
    var version: Int

    init(
        id: UUID = UUID(),
        title: String = "未命名文档",
        pages: [BoardPage] = [BoardPage()],
        currentPageIndex: Int = 0,
        version: Int = 1
    ) {
        self.id = id
        self.title = title
        self.pages = pages.isEmpty ? [BoardPage()] : pages
        self.currentPageIndex = min(max(currentPageIndex, 0), max(self.pages.count - 1, 0))
        self.version = version
    }

    var currentPage: BoardPage? {
        guard pages.indices.contains(currentPageIndex) else { return nil }
        return pages[currentPageIndex]
    }

    mutating func addPage(after index: Int? = nil, template: BoardPage? = nil) {
        let source = template ?? currentPage ?? BoardPage()
        var page = source
        page = BoardPage(
            title: "第 \(pages.count + 1) 页",
            width: source.width,
            height: source.height,
            background: source.background,
            pattern: source.pattern
        )
        let insertionIndex = min(max((index ?? currentPageIndex) + 1, 0), pages.count)
        pages.insert(page, at: insertionIndex)
        currentPageIndex = insertionIndex
    }

    mutating func duplicateCurrentPage() {
        guard let source = currentPage else { return }
        addPage(after: currentPageIndex, template: source)
    }

    mutating func deletePage(at index: Int) {
        guard pages.count > 1, pages.indices.contains(index) else { return }
        pages.remove(at: index)
        currentPageIndex = min(currentPageIndex, pages.count - 1)
        if index < currentPageIndex { currentPageIndex -= 1 }
    }

    mutating func movePage(from source: Int, to destination: Int) {
        guard pages.indices.contains(source), destination >= 0, destination < pages.count, source != destination else { return }
        let page = pages.remove(at: source)
        pages.insert(page, at: destination)
        if currentPageIndex == source {
            currentPageIndex = destination
        } else if source < currentPageIndex && destination >= currentPageIndex {
            currentPageIndex -= 1
        } else if source > currentPageIndex && destination <= currentPageIndex {
            currentPageIndex += 1
        }
    }
}
