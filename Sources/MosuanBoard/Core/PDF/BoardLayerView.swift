import SwiftUI

struct BoardLayerView: View {
    @ObservedObject var store: BoardLayerStore

    var body: some View {
        VStack(alignment: .leading, spacing: 0) {
            HStack {
                Text("图层").font(.headline)
                Spacer()
                Button { store.addLayer() } label: { Image(systemName: "plus") }
                Button { store.deleteCurrentLayer() } label: { Image(systemName: "trash") }
            }.padding(10)
            Divider()
            ScrollView {
                VStack(spacing: 4) {
                    ForEach(store.note.layers.reversed()) { layer in
                        HStack(spacing: 8) {
                            Button { store.toggleVisibility(layer.id) } label: {
                                Image(systemName: layer.isVisible ? "eye" : "eye.slash")
                            }.buttonStyle(.plain)
                            Button { store.toggleLock(layer.id) } label: {
                                Image(systemName: layer.isLocked ? "lock.fill" : "lock.open")
                            }.buttonStyle(.plain)
                            Text(layer.name).lineLimit(1)
                            Spacer()
                            if layer.id == store.note.currentLayerID { Image(systemName: "checkmark") }
                        }
                        .padding(.horizontal, 10).padding(.vertical, 8)
                        .contentShape(Rectangle())
                        .onTapGesture { store.selectLayer(layer.id) }
                    }
                }.padding(.vertical, 6)
            }
        }
        .frame(width: 220, height: 300)
    }
}
