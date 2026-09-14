import SwiftUI

/// The five magnetic positions used by the detachable favorite-pen dock.
enum FavoriteDockPlacement: String, CaseIterable {
    case top
    case leftInside
    case rightInside
    case leftOutside
    case rightOutside

    var isVertical: Bool {
        switch self {
        case .top: return false
        case .leftInside, .rightInside, .leftOutside, .rightOutside: return true
        }
    }
}

struct FavoriteToolDock: View {
    let placement: FavoriteDockPlacement
    let presetIDs: [String]
    let onSelect: (PenPreset) -> Void
    let onRemove: (String) -> Void
    let onDragChanged: (CGSize) -> Void
    let onDragEnded: (CGSize) -> Void

    private var presets: [PenPreset] {
        presetIDs.compactMap { id in PenPreset.defaults.first { $0.id == id } }
    }

    var body: some View {
        Group {
            if placement.isVertical {
                VStack(spacing: 3) { content }
                    .padding(.vertical, 7)
                    .padding(.horizontal, 5)
            } else {
                HStack(spacing: 3) { content }
                    .padding(.horizontal, 8)
                    .padding(.vertical, 5)
            }
        }
        .background(.regularMaterial)
        .clipShape(RoundedRectangle(cornerRadius: 11, style: .continuous))
        .overlay {
            RoundedRectangle(cornerRadius: 11, style: .continuous)
                .stroke(.quaternary, lineWidth: 1)
        }
        .shadow(color: .black.opacity(0.22), radius: 10, y: 3)
        .contentShape(RoundedRectangle(cornerRadius: 11, style: .continuous))
        .gesture(
            DragGesture(minimumDistance: 5)
                .onChanged { onDragChanged($0.translation) }
                .onEnded { onDragEnded($0.translation) }
        )
    }

    @ViewBuilder private var content: some View {
        Image(systemName: "star.fill")
            .font(.system(size: 14, weight: .semibold))
            .foregroundStyle(.green)
            .frame(width: 28, height: 28)

        if !presets.isEmpty {
            Divider()
                .frame(width: placement.isVertical ? 26 : nil,
                       height: placement.isVertical ? nil : 24)
        }

        ForEach(presets) { preset in
            Button {
                onSelect(preset)
            } label: {
                presetIcon(preset)
            }
            .buttonStyle(.plain)
            .help(preset.name)
            .contextMenu {
                Button("移出收藏") { onRemove(preset.id) }
            }
        }

        Divider()
            .frame(width: placement.isVertical ? 26 : nil,
                   height: placement.isVertical ? nil : 24)

        Image(systemName: "plus")
            .font(.system(size: 13, weight: .semibold))
            .foregroundStyle(.green)
            .frame(width: 28, height: 28)
            .help("拖动到绿色吸附位置")
    }

    private func presetIcon(_ preset: PenPreset) -> some View {
        Circle()
            .fill(Color(red: preset.style.color.red,
                        green: preset.style.color.green,
                        blue: preset.style.color.blue))
            .frame(width: 19, height: 19)
            .overlay {
                Circle().stroke(.white.opacity(0.55), lineWidth: 1)
            }
            .frame(width: 30, height: 32)
    }
}

struct FavoriteDockDropTargets: View {
    let active: Bool
    let highlighted: FavoriteDockPlacement?

    var body: some View {
        if active {
            ZStack {
                target(.top)
                target(.leftInside)
                target(.rightInside)
                target(.leftOutside)
                target(.rightOutside)
            }
            .allowsHitTesting(false)
            .animation(.easeOut(duration: 0.12), value: highlighted)
        }
    }

    @ViewBuilder
    private func target(_ placement: FavoriteDockPlacement) -> some View {
        let selected = highlighted == placement
        RoundedRectangle(cornerRadius: 7, style: .continuous)
            .fill(selected ? Color.green.opacity(0.32) : Color.green.opacity(0.16))
            .overlay {
                RoundedRectangle(cornerRadius: 7, style: .continuous)
                    .stroke(Color.green.opacity(selected ? 0.75 : 0.35), lineWidth: selected ? 2 : 1)
            }
            .frame(width: placement.isVertical ? 52 : 260,
                   height: placement.isVertical ? 260 : 44)
            .position(position(for: placement))
    }

    private func position(for placement: FavoriteDockPlacement) -> CGPoint {
        // Geometry is supplied by the parent through the surrounding ZStack's frame.
        // Alignment modifiers in BoardScreen place the actual targets; these are fallback
        // positions for the compact overlay and are intentionally unobtrusive.
        switch placement {
        case .top: return CGPoint(x: 300, y: 32)
        case .leftInside: return CGPoint(x: 28, y: 300)
        case .rightInside: return CGPoint(x: 980, y: 300)
        case .leftOutside: return CGPoint(x: 8, y: 300)
        case .rightOutside: return CGPoint(x: 1072, y: 300)
        }
    }
}
