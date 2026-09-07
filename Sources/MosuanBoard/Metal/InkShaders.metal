#include <metal_stdlib>
using namespace metal;

struct InkVertex {
    float2 position;
    float4 color;
};

struct RasterVertex {
    float4 position [[position]];
    float4 color;
};

vertex RasterVertex inkVertex(const device InkVertex *vertices [[buffer(0)]],
                              uint vertexID [[vertex_id]]) {
    InkVertex input = vertices[vertexID];
    RasterVertex output;

    // The initial prototype uses pixel coordinates and is converted to NDC
    // by the renderer in a later pass. Keep the shader intentionally small.
    output.position = float4(input.position.x, input.position.y, 0.0, 1.0);
    output.color = input.color;
    return output;
}

fragment float4 inkFragment(RasterVertex input [[stage_in]]) {
    return input.color;
}
