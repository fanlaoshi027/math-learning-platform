import SwiftUI

@main
struct MosuanBoardApp: App {
    var body: some Scene {
        WindowGroup("Mosuan Board") {
            ZStack {
                BoardScreen()
                DynamicAngleOverlay()
            }
            .frame(minWidth: 1100, minHeight: 700)
        }
        .windowStyle(.titleBar)
        .windowToolbarStyle(.unified)
        .commands {
            CommandGroup(after: .newItem) {
                Button("导入 PDF…") {
                    PDFWorkspaceController.shared.openPDF()
                }
                .keyboardShortcut("o", modifiers: [.command, .shift])
            }
        }
    }
}
