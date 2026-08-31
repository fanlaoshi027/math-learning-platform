<?php
/**
 * Math Course Theme - Learning Player Page.
 *
 * Course selection opens the learning player directly. The player chooses
 * the first accessible lesson when lesson_id is not supplied.
 */
defined( 'ABSPATH' ) || exit;

get_header();

$course_id = isset( $_GET['course_id'] ) ? absint( $_GET['course_id'] ) : 0;
$lesson_id = isset( $_GET['lesson_id'] ) ? absint( $_GET['lesson_id'] ) : 0;
?>

<main class="mc-learning-page">
	<div class="mc-learning-container">
		<?php
		if ( $course_id && shortcode_exists( 'mathcourse_course_player' ) ) {
			echo do_shortcode(
				'[mathcourse_course_player course_id="' . esc_attr( $course_id ) . '"' .
				( $lesson_id ? ' lesson_id="' . esc_attr( $lesson_id ) . '"' : '' ) .
				']'
			);
		} else {
			?>
			<section class="mc-lesson-card">
				<h1>课程不存在或链接无效</h1>
				<p><a href="<?php echo esc_url( home_url( '/course-center/' ) ); ?>">返回课程中心</a></p>
			</section>
			<?php
		}
		?>
	</div>
</main>

<script>
(function () {
  'use strict';

  function formatTime(seconds) {
    if (!isFinite(seconds) || seconds < 0) return '00:00';
    var total = Math.floor(seconds);
    var hours = Math.floor(total / 3600);
    var minutes = Math.floor((total % 3600) / 60);
    var secs = total % 60;
    if (hours > 0) {
      return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
    }
    return String(minutes).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
  }

  function initPlayer(video) {
    if (!video || video.dataset.mcEnhanced === '1') return;
    var shell = video.closest('.mathcourse-video');
    if (!shell) return;

    video.dataset.mcEnhanced = '1';
    shell.classList.add('mc-player-enhanced');
    video.removeAttribute('controls');

    var controls = document.createElement('div');
    controls.className = 'mc-player-controls';
    controls.innerHTML = '' +
      '<div class="mc-player-progress" role="slider" tabindex="0" aria-label="视频播放进度">' +
        '<span class="mc-player-progress__buffer"></span>' +
        '<span class="mc-player-progress__value"></span>' +
        '<span class="mc-player-progress__thumb"></span>' +
      '</div>' +
      '<div class="mc-player-toolbar">' +
        '<button type="button" class="mc-player-button mc-player-button--main" data-action="play" aria-label="播放或暂停">▶</button>' +
        '<button type="button" class="mc-player-button mc-player-skip" data-action="back" aria-label="后退10秒">↶10</button>' +
        '<button type="button" class="mc-player-button mc-player-skip" data-action="forward" aria-label="前进10秒">10↷</button>' +
        '<span class="mc-player-time"><span data-time="current">00:00</span> / <span data-time="duration">00:00</span></span>' +
        '<span class="mc-player-spacer"></span>' +
        '<span class="mc-player-badge">1080P</span>' +
        '<button type="button" class="mc-player-speed" data-action="speed" aria-label="切换播放速度">1×</button>' +
        '<button type="button" class="mc-player-button" data-action="fullscreen" aria-label="全屏">⛶</button>' +
      '</div>';
    shell.appendChild(controls);

    var playButton = controls.querySelector('[data-action="play"]');
    var currentTime = controls.querySelector('[data-time="current"]');
    var duration = controls.querySelector('[data-time="duration"]');
    var valueBar = controls.querySelector('.mc-player-progress__value');
    var bufferBar = controls.querySelector('.mc-player-progress__buffer');
    var thumb = controls.querySelector('.mc-player-progress__thumb');
    var progress = controls.querySelector('.mc-player-progress');
    var speedButton = controls.querySelector('[data-action="speed"]');
    var speeds = [1, 1.25, 1.5, 1.75, 2, 0.75];
    var speedIndex = 0;
    var hideTimer;

    function updateProgress() {
      var percent = video.duration ? (video.currentTime / video.duration) * 100 : 0;
      valueBar.style.width = percent + '%';
      thumb.style.left = percent + '%';
      currentTime.textContent = formatTime(video.currentTime);
      duration.textContent = formatTime(video.duration);
      if (video.buffered.length && video.duration) {
        bufferBar.style.width = (video.buffered.end(video.buffered.length - 1) / video.duration) * 100 + '%';
      }
    }

    function updatePlayState() {
      playButton.textContent = video.paused ? '▶' : 'Ⅱ';
      playButton.setAttribute('aria-label', video.paused ? '播放' : '暂停');
    }

    function showControls() {
      controls.classList.remove('is-hidden');
      clearTimeout(hideTimer);
      if (!video.paused) {
        hideTimer = setTimeout(function () { controls.classList.add('is-hidden'); }, 2600);
      }
    }

    function seekFromEvent(event) {
      var rect = progress.getBoundingClientRect();
      var ratio = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
      if (video.duration) video.currentTime = ratio * video.duration;
    }

    playButton.addEventListener('click', function () {
      if (video.paused) video.play(); else video.pause();
      showControls();
    });

    controls.querySelector('[data-action="back"]').addEventListener('click', function () {
      video.currentTime = Math.max(0, video.currentTime - 10);
      showControls();
    });

    controls.querySelector('[data-action="forward"]').addEventListener('click', function () {
      video.currentTime = Math.min(video.duration || Infinity, video.currentTime + 10);
      showControls();
    });

    speedButton.addEventListener('click', function () {
      speedIndex = (speedIndex + 1) % speeds.length;
      video.playbackRate = speeds[speedIndex];
      speedButton.textContent = speeds[speedIndex] + '×';
      showControls();
    });

    controls.querySelector('[data-action="fullscreen"]').addEventListener('click', function () {
      if (document.fullscreenElement) {
        document.exitFullscreen();
      } else if (shell.requestFullscreen) {
        shell.requestFullscreen();
      } else if (video.webkitEnterFullscreen) {
        video.webkitEnterFullscreen();
      }
    });

    progress.addEventListener('click', seekFromEvent);
    progress.addEventListener('keydown', function (event) {
      if (!video.duration) return;
      if (event.key === 'ArrowLeft') { video.currentTime = Math.max(0, video.currentTime - 5); event.preventDefault(); }
      if (event.key === 'ArrowRight') { video.currentTime = Math.min(video.duration, video.currentTime + 5); event.preventDefault(); }
      updateProgress();
    });

    video.addEventListener('timeupdate', updateProgress);
    video.addEventListener('progress', updateProgress);
    video.addEventListener('loadedmetadata', updateProgress);
    video.addEventListener('durationchange', updateProgress);
    video.addEventListener('play', function () { updatePlayState(); showControls(); });
    video.addEventListener('pause', function () { updatePlayState(); showControls(); });
    video.addEventListener('ended', function () { updatePlayState(); showControls(); });
    shell.addEventListener('mousemove', showControls);
    shell.addEventListener('touchstart', showControls, {passive: true});

    updatePlayState();
    updateProgress();
  }

  function boot() {
    document.querySelectorAll('.mathcourse-video video.mathcourse-player, .mathcourse-video video').forEach(initPlayer);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
</script>

<?php get_footer();
