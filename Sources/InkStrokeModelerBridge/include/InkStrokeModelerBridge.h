#pragma once

#ifdef __cplusplus
extern "C" {
#endif

typedef struct MosuanInkStrokeModeler MosuanInkStrokeModeler;

typedef struct {
    float x;
    float y;
    float pressure;
    float tilt;
    float orientation;
    double time;
} MosuanInkResult;

MosuanInkStrokeModeler *mosuan_ink_create(void);
void mosuan_ink_destroy(MosuanInkStrokeModeler *modeler);
void mosuan_ink_reset(MosuanInkStrokeModeler *modeler);

int mosuan_ink_update(
    MosuanInkStrokeModeler *modeler,
    int event_type,
    float x,
    float y,
    double time,
    float pressure,
    float tilt,
    float orientation,
    MosuanInkResult *results,
    int capacity
);

int mosuan_ink_predict(
    MosuanInkStrokeModeler *modeler,
    MosuanInkResult *results,
    int capacity
);

#ifdef __cplusplus
}
#endif
