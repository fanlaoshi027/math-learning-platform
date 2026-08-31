<?php
/**
 * Template Name: 学习播放页 (Learning Page)
 * 遵循《Math Learning Platform 接口规范 V1.0》第 7/8/9 条与《前端页面开发规范 V1.0》第 7 条
 * 入口格式：/learning/?course_id={COURSE_ID}&lesson_id={LESSON_ID}
 * 
 * @package MathCourse_Theme
 */

get_header();

$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$lesson_id = isset($_GET['lesson_id']) ? intval($_GET['lesson_id']) : 0;

// 调用规范核心服务 MathCourse\Course\Course_Service
$service = mathcourse_get_service();
$data = null;

if ($service && $course_id > 0) {
    $data = $service->get_course_directory($course_id, get_current_user_id());
}

// 查找当前选中的课时
$current_lesson = null;
if ($data && !empty($data['topics'])) {
    foreach ($data['topics'] as $topic) {
        if (!empty($topic['lessons'])) {
            foreach ($topic['lessons'] as $l) {
                if ($lesson_id > 0 && $l['id'] == $lesson_id) {
                    $current_lesson = $l;
                    break 2;
                }
            }
        }
    }
    // 如果未指定或未找到，默认取第一章节的第一节
    if (!$current_lesson && !empty($data['topics'][0]['lessons'][0])) {
        $current_lesson = $data['topics'][0]['lessons'][0];
    }
}
?>

<div class="bg-[#0b1329] text-white min-h-[calc(100vh-4rem)] py-4 sm:py-6">
  <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
    
    <?php if (!$data) : ?>
      <!-- 空课程容错状态 (遵循规范 9. 状态设计) -->
      <div class="bg-slate-900 border border-slate-800 rounded-2xl p-10 text-center max-w-xl mx-auto my-12">
        <div class="w-12 h-12 rounded-full bg-blue-500/10 text-blue-400 flex items-center justify-center mx-auto mb-4">
          <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <h2 class="text-lg font-bold text-white">请选择要学习的课程</h2>
        <p class="text-xs text-slate-400 mt-2">未检测到有效的课程参数或该课程正在编排中。</p>
        <a href="<?php echo esc_url(home_url('/courses/')); ?>" class="mt-6 inline-block px-5 py-2 bg-blue-600 hover:bg-blue-500 rounded-lg text-xs font-semibold text-white">
          返回课程中心
        </a>
      </div>
    <?php else : ?>

      <!-- 顶部课程元信息与进度 -->
      <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-800">
        <div>
          <div class="flex items-center gap-2">
            <span class="text-xs font-bold px-2 py-0.5 rounded bg-blue-600/30 text-blue-300 border border-blue-500/30">
              <?php echo esc_html(mathcourse_format_type($data['type'])); ?> · <?php echo esc_html(mathcourse_format_grade($data['grade'])); ?>
            </span>
            <h1 class="text-base sm:text-xl font-bold text-white"><?php echo esc_html($data['title']); ?></h1>
          </div>
          <?php if ($current_lesson) : ?>
            <p class="text-xs text-slate-400 mt-1">当前播放：<?php echo esc_html($current_lesson['title']); ?></p>
          <?php endif; ?>
        </div>

        <!-- 进度条 (规范 6. 课程进度: 只负责显示 percent) -->
        <?php if (!empty($data['progress'])) : ?>
          <div class="flex items-center gap-3 bg-slate-900/80 px-3 py-1.5 rounded-lg border border-slate-800">
            <div class="text-xs text-slate-400">
              已学完 <span class="font-bold text-emerald-400"><?php echo intval($data['progress']['completed']); ?></span> / <?php echo intval($data['progress']['total']); ?> 讲
            </div>
            <div class="w-24 bg-slate-800 rounded-full h-2 overflow-hidden">
              <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo intval($data['progress']['percent']); ?>%;"></div>
            </div>
            <span class="text-xs font-bold text-blue-300 font-mono"><?php echo intval($data['progress']['percent']); ?>%</span>
          </div>
        <?php endif; ?>
      </div>

      <!-- 主区域：左侧播放器 + 右侧章节目录 (独立滚动) -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        
        <!-- 左侧：黑板视频播放器容器 (规范 14. 空视频保护与清晰控制栏) -->
        <div class="lg:col-span-8 flex flex-col space-y-4">
          <div class="w-full aspect-[16/9] bg-[#0c1829] rounded-2xl border border-slate-800 shadow-2xl overflow-hidden relative flex items-center justify-center">
            <?php if (!empty($current_lesson['hls_url'])) : ?>
              <!-- 视频播放器 (由插件安全提供 HLS URL) -->
              <video 
                id="mc-video-player" 
                class="w-full h-full object-contain" 
                controls 
                playsinline
                src="<?php echo esc_url($current_lesson['hls_url']); ?>"
              ></video>
            <?php else : ?>
              <!-- 空视频友好占位 (遵循规范 14. 空视频保护) -->
              <div class="text-center p-6 space-y-3 select-none">
                <div class="w-14 h-14 rounded-full bg-slate-800/80 border border-slate-700 text-blue-400 flex items-center justify-center mx-auto shadow-inner">
                  <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                </div>
                <h3 class="text-base font-bold text-white">本讲视频正在高清重录编排中</h3>
                <p class="text-xs text-slate-400 max-w-sm mx-auto">
                  樊老师正按中考考纲完善板书推导，请点击右侧已解锁讲数继续学习。
                </p>
              </div>
            <?php endif; ?>
          </div>

          <!-- 课时快速切换 -->
          <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-4 flex items-center justify-between">
            <span class="text-xs text-slate-400">支持 0.75x ~ 2.0x 倍速播放与快进10秒</span>
            <div class="flex items-center gap-2">
              <span class="text-xs text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
                1080P 高清黑板板书
              </span>
            </div>
          </div>
        </div>

        <!-- 右侧：章节课时目录 (规范 7. 章节独立滚动区域，可折叠) -->
        <div class="lg:col-span-4 bg-slate-900 border border-slate-800 rounded-2xl p-4 flex flex-col h-[560px]">
          <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-3">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
              <svg class="w-4 h-4 text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/></svg>
              <span>课程目录与讲数</span>
            </h3>
            <span class="text-[11px] text-slate-400">独立滚动</span>
          </div>

          <!-- 独立滚动目录列表 -->
          <div class="flex-1 overflow-y-auto mc-theme-scrollbar space-y-3 pr-1">
            <?php if (!empty($data['topics'])) : ?>
              <?php foreach ($data['topics'] as $tIndex => $topic) : ?>
                <div class="mc-chapter-item bg-slate-950/60 border border-slate-800/80 rounded-xl overflow-hidden">
                  <!-- 章节头部 (折叠开关) -->
                  <div class="mc-chapter-toggle p-3 bg-slate-800/40 hover:bg-slate-800/70 flex items-center justify-between cursor-pointer select-none transition-colors">
                    <span class="text-xs font-bold text-slate-200"><?php echo esc_html($topic['title']); ?></span>
                    <svg class="mc-arrow-icon w-3.5 h-3.5 text-slate-400 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="6 9 12 15 18 9"/></svg>
                  </div>

                  <!-- 课时子列表 -->
                  <div class="mc-lesson-list p-1.5 space-y-1">
                    <?php if (!empty($topic['lessons'])) : ?>
                      <?php foreach ($topic['lessons'] as $lesson) : 
                        $is_active = ($current_lesson && $current_lesson['id'] == $lesson['id']);
                        $is_completed = !empty($lesson['completed']);
                        $is_accessible = !empty($lesson['accessible']);
                        $is_preview = !empty($lesson['preview']);
                      ?>
                        <a 
                          href="<?php echo !empty($lesson['url']) ? esc_url($lesson['url']) : esc_url(home_url('/learning/?course_id=' . $course_id . '&lesson_id=' . $lesson['id'])); ?>"
                          class="flex items-center justify-between p-2 rounded-lg text-xs transition-all <?php echo $is_active ? 'bg-blue-600 text-white font-bold shadow-xs' : 'text-slate-300 hover:bg-slate-800/80 hover:text-white'; ?>"
                        >
                          <div class="flex items-center gap-2 truncate pr-2">
                            <?php if ($is_completed) : ?>
                              <span class="text-emerald-400 font-bold">✓</span>
                            <?php else : ?>
                              <span class="w-1.5 h-1.5 rounded-full <?php echo $is_active ? 'bg-white' : 'bg-slate-500'; ?>"></span>
                            <?php endif; ?>
                            <span class="truncate"><?php echo esc_html($lesson['title']); ?></span>
                          </div>

                          <div class="shrink-0 flex items-center gap-1">
                            <?php if ($is_preview) : ?>
                              <span class="px-1.5 py-0.5 rounded text-[10px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">试看</span>
                            <?php elseif (!$is_accessible) : ?>
                              <span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-800 text-slate-400 border border-slate-700">🔒 锁课</span>
                            <?php endif; ?>
                          </div>
                        </a>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

        </div>

      </div>

    <?php endif; ?>

  </div>
</div>

<?php get_footer(); ?>
