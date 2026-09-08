#include <metal_stdlib>
using namespace metal;

struct MosuanVertex {
    float2 position;
    float4 color;
};

struct MosuanRaster {
    float4 position [[position]];
    float4 color;
};

vertex MosuanRaster mosuanBoardVertex(
    const device MosuanVertex *vertices [[buffer(0)]],
    uint vertexID [[vertex_id]]
) {
    MosuanRaster out;
    out.position = float4(vertices[vertexID].position, 0.0, 1.0);
    out.color = vertices[vertexID].color;
    return out;
}

fragment float4 mosuanBoardFragment(MosuanRaster in [[stage_in]]) {
    return in.color;
}
