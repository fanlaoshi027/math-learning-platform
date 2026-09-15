#include "InkStrokeModelerBridge.h"

#include <algorithm>
#include <memory>
#include <vector>

#include "ink_stroke_modeler/params.h"
#include "ink_stroke_modeler/stroke_modeler.h"
#include "ink_stroke_modeler/types.h"

struct MosuanInkStrokeModeler {
    ink::stroke_model::StrokeModeler modeler;
    bool initialized = false;
    std::vector<ink::stroke_model::Result> buffer;

    MosuanInkStrokeModeler() {
        using namespace ink::stroke_model;
        StrokeModelParams params{
            .wobble_smoother_params{
                .timeout{.04},
                .speed_floor = 1.31,
                .speed_ceiling = 1.44
            },
            .position_modeler_params{
                .spring_mass_constant = 11.f / 32400,
                .drag_constant = 72.f
            },
            .sampling_params{
                .min_output_rate = 180,
                .end_of_stroke_stopping_distance = .001,
                .end_of_stroke_max_iterations = 20
            },
            .prediction_params = StrokeEndPredictorParams()
        };
        initialized = modeler.Reset(params).ok();
        buffer.reserve(64);
    }
};

extern "C" MosuanInkStrokeModeler *mosuan_ink_create(void) {
    return new MosuanInkStrokeModeler();
}

extern "C" void mosuan_ink_destroy(MosuanInkStrokeModeler *modeler) {
    delete modeler;
}

extern "C" void mosuan_ink_reset(MosuanInkStrokeModeler *modeler) {
    if (!modeler) return;
    static_cast<void>(modeler->modeler.Reset());
    modeler->buffer.clear();
}

extern "C" int mosuan_ink_update(
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
) {
    if (!modeler || !modeler->initialized || !results || capacity <= 0) return 0;

    using namespace ink::stroke_model;
    Input input;
    input.event_type = static_cast<Input::EventType>(std::clamp(event_type, 0, 2));
    input.position = {.x = x, .y = y};
    input.time = Time(time);
    input.pressure = pressure;
    input.tilt = tilt;
    input.orientation = orientation;

    modeler->buffer.clear();
    if (!modeler->modeler.Update(input, modeler->buffer).ok()) return 0;

    const int count = std::min(capacity, static_cast<int>(modeler->buffer.size()));
    for (int i = 0; i < count; ++i) {
        const auto &r = modeler->buffer[i];
        results[i] = {
            .x = r.position.x,
            .y = r.position.y,
            .pressure = r.pressure,
            .tilt = r.tilt,
            .orientation = r.orientation,
            .time = r.time.Value()
        };
    }
    return count;
}

extern "C" int mosuan_ink_predict(
    MosuanInkStrokeModeler *modeler,
    MosuanInkResult *results,
    int capacity
) {
    if (!modeler || !modeler->initialized || !results || capacity <= 0) return 0;

    modeler->buffer.clear();
    if (!modeler->modeler.Predict(modeler->buffer).ok()) return 0;

    const int count = std::min(capacity, static_cast<int>(modeler->buffer.size()));
    for (int i = 0; i < count; ++i) {
        const auto &r = modeler->buffer[i];
        results[i] = {
            .x = r.position.x,
            .y = r.position.y,
            .pressure = r.pressure,
            .tilt = r.tilt,
            .orientation = r.orientation,
            .time = r.time.Value()
        };
    }
    return count;
}
