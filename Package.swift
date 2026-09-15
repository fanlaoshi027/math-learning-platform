// swift-tools-version: 5.10
import PackageDescription

let package = Package(
    name: "MosuanBoard",
    platforms: [.macOS(.v14)],
    products: [.executable(name: "MosuanBoard", targets: ["MosuanBoard"])],
    dependencies: [
        .package(
            url: "https://github.com/firebase/abseil-cpp-SwiftPM.git",
            branch: "main"
        )
    ],
    targets: [
        .target(
            name: "InkStrokeModeler",
            dependencies: [
                .product(name: "abseil", package: "abseil-cpp-SwiftPM")
            ],
            path: "ThirdParty/ink-stroke-modeler",
            sources: [
                "ink_stroke_modeler/numbers.h",
                "ink_stroke_modeler/params.cc",
                "ink_stroke_modeler/params.h",
                "ink_stroke_modeler/stroke_modeler.cc",
                "ink_stroke_modeler/stroke_modeler.h",
                "ink_stroke_modeler/types.cc",
                "ink_stroke_modeler/types.h",
                "ink_stroke_modeler/internal/internal_types.cc",
                "ink_stroke_modeler/internal/internal_types.h",
                "ink_stroke_modeler/internal/loop_contraction_mitigation_modeler.cc",
                "ink_stroke_modeler/internal/loop_contraction_mitigation_modeler.h",
                "ink_stroke_modeler/internal/position_modeler.cc",
                "ink_stroke_modeler/internal/position_modeler.h",
                "ink_stroke_modeler/internal/stylus_state_modeler.cc",
                "ink_stroke_modeler/internal/stylus_state_modeler.h",
                "ink_stroke_modeler/internal/utils.cc",
                "ink_stroke_modeler/internal/utils.h",
                "ink_stroke_modeler/internal/validation.h",
                "ink_stroke_modeler/internal/wobble_smoother.cc",
                "ink_stroke_modeler/internal/wobble_smoother.h",
                "ink_stroke_modeler/internal/prediction/input_predictor.h",
                "ink_stroke_modeler/internal/prediction/kalman_predictor.cc",
                "ink_stroke_modeler/internal/prediction/kalman_predictor.h",
                "ink_stroke_modeler/internal/prediction/stroke_end_predictor.cc",
                "ink_stroke_modeler/internal/prediction/stroke_end_predictor.h",
                "ink_stroke_modeler/internal/prediction/kalman_filter/axis_predictor.cc",
                "ink_stroke_modeler/internal/prediction/kalman_filter/axis_predictor.h",
                "ink_stroke_modeler/internal/prediction/kalman_filter/kalman_filter.cc",
                "ink_stroke_modeler/internal/prediction/kalman_filter/kalman_filter.h",
                "ink_stroke_modeler/internal/prediction/kalman_filter/matrix.h"
            ],
            publicHeadersPath: "ink_stroke_modeler",
            cxxSettings: [.headerSearchPath(".")]
        ),
        .executableTarget(
            name: "MosuanBoard",
            dependencies: ["InkStrokeModeler"],
            path: "Sources/MosuanBoard",
            exclude: [
                "BoardScreen.swift",
                "BoardScreenFixed.swift",
                "BoardScreenV2.swift",
                "PDFTeachingWorkspace.swift",
                "Metal/InkRendererFixed.swift",
                "Core/GraphicObjectStoreFixed.swift",
                "Core/GraphicObjectStoreV2.swift",
                "Core/GraphicObjectStoreV3.swift",
                "Platform/iPadOS",
                "SMART_LINE_AND_ERASER.md"
            ],
            resources: [.process("Metal/InkShaders.metal")]
        )
    ],
    cxxLanguageStandard: .cxx20
)
