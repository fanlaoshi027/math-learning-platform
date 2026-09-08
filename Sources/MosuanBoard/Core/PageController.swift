import Combine
import Foundation

@MainActor
final class PageController: ObservableObject {
    @Published private(set) var document: MosuanDocument

    init(document: MosuanDocument = MosuanDocument()) {
        self.document = document
    }

    var pages: [BoardPage] { document.pages }
    var currentIndex: Int { document.currentPageIndex }

    func selectPage(_ index: Int) {
        guard document.pages.indices.contains(index) else { return }
        document.currentPageIndex = index
    }

    func addPage() {
        document.addPage()
    }

    func duplicateCurrentPage() {
        document.duplicateCurrentPage()
    }

    func deleteCurrentPage() {
        document.deletePage(at: document.currentPageIndex)
    }

    func movePage(from source: Int, to destination: Int) {
        document.movePage(from: source, to: destination)
    }

    func renamePage(_ title: String, at index: Int) {
        guard document.pages.indices.contains(index) else { return }
        let trimmed = title.trimmingCharacters(in: .whitespacesAndNewlines)
        document.pages[index].title = trimmed.isEmpty ? "第 \(index + 1) 页" : trimmed
    }
}
