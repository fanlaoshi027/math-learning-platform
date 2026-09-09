#include <metal_stdlib>
using namespace metal;

struct MosuanVertex {
    float2 position;
    float2 point;
    float2 p0;
    float2 p1;
    float radius;
    uint kind;
    float4 color;
};

struct MosuanRaster {
    float4 position [[position]];
    float2 point;
    float2 p0;
    float2 p1;
    float radius;
    uint kind;
    float4 color;
};

vertex MosuanRaster mosuanBoardVertex(
    const device MosuanVertex *vertices [[buffer(0)]],
    uint vertexID [[vertex_id]]
) {
    MosuanVertex v = vertices[vertexID];
    MosuanRaster out;
    out.position = float4(v.position, 0.0, 1.0);
    out.point = v.point;
    out.p0 = v.p0;
    out.p1 = v.p1;
    out.radius = v.radius;
    out.kind = v.kind;
    out.color = v.color;
    return out;
}

fragment float4 mosuanBoardFragment(MosuanRaster in [[stage_in]]) {
    // Analytic coverage for a round brush. CPU only supplies a conservative
    // bounding quad; the shader decides the actual ink coverage per pixel.
    float2 segment = in.p1 - in.p0;
    float segmentLengthSquared = dot(segment, segment);
    float2 closest;

    if (in.kind == 0u || segmentLengthSquared < 0.0001) {
        closest = in.p0;
    } else {
        float h = clamp(dot(in.point - in.p0, segment) / segmentLengthSquared, 0.0, 1.0);
        closest = in.p0 + segment * h;
    }

    float signedDistance = distance(in.point, closest) - in.radius;
    float aa = max(fwidth(signedDistance), 0.65);
    float coverage = 1.0 - smoothstep(-aa, aa, signedDistance);
    if (coverage <= 0.001) {
        discard_fragment();
    }

    return float4(in.color.rgb, in.color.a * coverage);
}
