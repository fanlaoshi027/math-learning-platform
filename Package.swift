// swift-tools-version: 5.10
import PackageDescription

let package = Package(
    name: "MosuanBoard",
    platforms: [
        .macOS(.v14)
    ],
    products: [
        .executable(name: "MosuanBoard", targets: ["MosuanBoard"])
    ],
    targets: [
        .executableTarget(
            name: "MosuanBoard",
            path: "Sources/MosuanBoard",
            resources: [
                .process("Metal")
            ]
        )
    ]
)
