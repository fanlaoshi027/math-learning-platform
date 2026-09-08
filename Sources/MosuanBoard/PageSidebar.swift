import SwiftUI

struct PageSidebar: View {
    @ObservedObject var controller: PageController

    var body: some View {
        VStack(spacing: 0) {
            HStack {
                Text("页面").font(.headline)
                Spacer()
                Button { controller.addPage() } label: { Image(systemName: "plus") }
                    .buttonStyle(.borderless)
                    .help("新建页面")
            }
            .padding(.horizontal, 12)
            .padding(.vertical, 10)

            Divider()

            ScrollView {
                LazyVStack(spacing: 10) {
                    ForEach(Array(controller.pages.enumerated()), id: \.element.id) { index, page in
                        PageThumbnail(page: page, index: index, selected: index == controller.currentIndex) {
                            controller.selectPage(index)
                        }
                        .contextMenu {
                            Button("复制页面") { controller.selectPage(index); controller.duplicateCurrentPage() }
                            Button("删除页面", role: .destructive) {
                                controller.selectPage(index)
                                controller.deleteCurrentPage()
                            }
                        }
                    }
                }
                .padding(10)
            }

            Divider()

            HStack(spacing: 6) {
                Button { controller.duplicateCurrentPage() } label: { Label("复制", systemImage: "plus.square.on.square") }
                Button { controller.deleteCurrentPage() } label: { Image(systemName: "trash") }
                    .disabled(controller.pages.count <= 1)
            }
            .buttonStyle(.borderless)
            .padding(9)
        }
        .frame(width: 190)
        .background(.regularMaterial)
    }
}

private struct PageThumbnail: View {
    let page: BoardPage
    let index: Int
    let selected: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            VStack(alignment: .leading, spacing: 5) {
                ZStack {
                    RoundedRectangle(cornerRadius: 6, style: .continuous)
                        .fill(backgroundColor)
                    RoundedRectangle(cornerRadius: 6, style: .continuous)
                        .stroke(selected ? Color.accentColor : Color.secondary.opacity(0.25), lineWidth: selected ? 2 : 1)
                    VStack(spacing: 4) {
                        Text("数学笔记")
                            .font(.system(size: 8, weight: .medium))
                            .foregroundStyle(foregroundColor.opacity(0.7))
                        HStack(spacing: 4) {
                            Rectangle().frame(width: 42, height: 2)
                            Rectangle().frame(width: 26, height: 2)
                        }
                        HStack(spacing: 4) {
                            Rectangle().frame(width: 32, height: 2)
                            Rectangle().frame(width: 38, height: 2)
                        }
                    }
                }
                .aspectRatio(16 / 9, contentMode: .fit)

                Text(page.title)
                    .font(.system(size: 11))
                    .foregroundStyle(selected ? Color.accentColor : .primary)
                    .lineLimit(1)
            }
        }
        .buttonStyle(.plain)
    }

    private var backgroundColor: Color {
        switch page.background {
        case "black": return .black
        case "darkGray": return Color(white: 0.18)
        case "lightGray": return Color(white: 0.92)
        case "cream": return Color(red: 0.98, green: 0.96, blue: 0.88)
        default: return .white
        }
    }

    private var foregroundColor: Color {
        page.background == "white" || page.background == "lightGray" || page.background == "cream" ? .black : .white
    }
}
