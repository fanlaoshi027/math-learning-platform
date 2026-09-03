<?php

namespace MathCourse\Tutor;

defined('ABSPATH') || exit;

/**
 * Tutor preview compatibility wrapper.
 * Preview state is owned by the shared Tutor Adapter.
 */
class Preview {

    /**
     * Determine whether a lesson is explicitly configured as previewable.
     */
    public function is_preview($lesson_id) {
        $adapter = new Adapter();
        return $adapter->is_preview_lesson(absint($lesson_id));
    }

    /**
     * Enable preview using the canonical MathCourse/Tutor metadata bridge.
     */
    public function enable_preview($lesson_id) {
        $adapter = new Adapter();
        return $adapter->set_lesson_preview(absint($lesson_id), true);
    }

    /**
     * Disable preview using the same canonical metadata bridge.
     */
    public function disable_preview($lesson_id) {
        $adapter = new Adapter();
        return $adapter->set_lesson_preview(absint($lesson_id), false);
    }
}
