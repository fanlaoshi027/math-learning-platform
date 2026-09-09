import Foundation

/// Lightweight cache for display-only brush meshes.
/// The cache is deliberately independent from document/history data.
struct MosuanStrokeRenderCache<Vertex> {
    private(set) var storage: [UInt64: [Vertex]] = [:]

    mutating func value(for key: UInt64) -> [Vertex]? {
        storage[key]
    }

    mutating func insert(_ value: [Vertex], for key: UInt64) {
        storage[key] = value
    }

    mutating func removeAll(keepingCapacity: Bool = true) {
        storage.removeAll(keepingCapacity: keepingCapacity)
    }
}
