import SwiftUI

@main
struct MosuanBoardApp: App {
    var body: some Scene {
        WindowGroup("Mosuan Board") {
            BoardScreen()
                .frame(minWidth: 1100, minHeight: 700)
        }
        .windowStyle(.titleBar)
        .windowToolbarStyle(.unified)
    }
}
