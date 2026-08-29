/* MathCourse course sorting.
 * No HTML5 drag/drop. Topics and lessons use separate pointer sorting contexts.
 * A lesson can only be reordered inside the topic that owns it.
 */
(function () {
  'use strict';

  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  ready(function () {
    if (!window.MathCourseOrder) return;

    var saveButton = null;
    var noticeBox = null;
    var active = null;
    var placeholder = document.createElement('div');
    placeholder.className = 'mathcourse-sort-placeholder';
    placeholder.style.cssText = 'display:block;box-sizing:border-box;height:44px;margin:6px 0;border:2px dashed #2271b1;border-radius:8px;background:#f0f6fc;pointer-events:none;';

    function directTopicCards() {
      return Array.prototype.slice.call(document.querySelectorAll('button[name="mathcourse_action"][value^="delete_topic_"]')).map(function (button) {
        return button.parentElement && button.parentElement.parentElement;
      }).filter(Boolean);
    }

    function topicId(card) {
      var button = card.querySelector('button[name="mathcourse_action"][value^="delete_topic_"]');
      return button ? parseInt(button.value.replace('delete_topic_', ''), 10) || 0 : 0;
    }

    function lessonRows(card) {
      return Array.prototype.slice.call(card.querySelectorAll('button[name="mathcourse_action"][value^="delete_lesson_"]')).map(function (button) {
        // Current editor markup: controls div -> lesson row div.
        return button.parentElement && button.parentElement.parentElement;
      }).filter(function (row, index, rows) {
        return row && rows.indexOf(row) === index;
      });
    }

    function lessonId(row) {
      var button = row.querySelector('button[name="mathcourse_action"][value^="delete_lesson_"]');
      return button ? parseInt(button.value.replace('delete_lesson_', ''), 10) || 0 : 0;
    }

    function lessonContainer(card) {
      var rows = lessonRows(card);
      return rows.length ? rows[0].parentElement : null;
    }

    function setAttrs() {
      directTopicCards().forEach(function (card) {
        card.setAttribute('data-mc-topic', '1');
        var container = lessonContainer(card);
        if (!container) return;
        container.setAttribute('data-mc-lesson-container', '1');
        lessonRows(card).forEach(function (row) {
          row.setAttribute('data-mc-lesson', '1');
        });
      });
    }

    function cleanup() {
      if (placeholder.parentNode) placeholder.parentNode.removeChild(placeholder);
      document.body.classList.remove('mathcourse-sorting');
      if (active) {
        active.el.style.opacity = '';
        active.el.style.boxShadow = '';
        active.el.style.position = '';
        active.el.style.zIndex = '';
        active.el.style.userSelect = '';
      }
      active = null;
    }

    function begin(el, type, event) {
      if (event.button !== undefined && event.button !== 0) return;
      event.preventDefault();
      event.stopPropagation();

      var container = el.parentElement;
      if (!container) return;

      active = {
        el: el,
        type: type,
        container: container,
        pointerId: event.pointerId,
        moved: false
      };

      var rect = el.getBoundingClientRect();
      placeholder.style.height = Math.max(44, Math.round(rect.height)) + 'px';
      container.insertBefore(placeholder, el);

      el.style.opacity = '0.45';
      el.style.boxShadow = '0 5px 16px rgba(0,0,0,.12)';
      el.style.userSelect = 'none';
      document.body.classList.add('mathcourse-sorting');

      try { el.setPointerCapture(event.pointerId); } catch (ignore) {}
    }

    function move(event) {
      if (!active || event.pointerId !== active.pointerId) return;
      event.preventDefault();
      active.moved = true;

      // Never accept an element from another container. This is the key rule
      // that prevents lessons from leaving their topic.
      var container = active.container;
      var rows = Array.prototype.slice.call(container.children).filter(function (child) {
        return child !== active.el && child !== placeholder && child.getAttribute('data-mc-lesson') === '1';
      });

      var before = null;
      for (var i = 0; i < rows.length; i++) {
        var rect = rows[i].getBoundingClientRect();
        if (event.clientY < rect.top + rect.height / 2) {
          before = rows[i];
          break;
        }
      }

      if (before) container.insertBefore(placeholder, before);
      else container.appendChild(placeholder);
    }

    function end(event) {
      if (!active || event.pointerId !== active.pointerId) return;
      event.preventDefault();
      event.stopPropagation();

      if (placeholder.parentNode === active.container) {
        active.container.insertBefore(active.el, placeholder);
      }
      cleanup();
      setAttrs();
    }

    function bind(el, type) {
      if (el._mcSortBound) return;
      el._mcSortBound = true;
      el.style.cursor = 'grab';
      el.style.touchAction = 'none';

      el.addEventListener('pointerdown', function (event) {
        // Lesson pointerdown must not bubble into the topic card.
        begin(el, type, event);
      });
      el.addEventListener('pointermove', move);
      el.addEventListener('pointerup', end);
      el.addEventListener('pointercancel', end);
    }

    function install() {
      setAttrs();
      directTopicCards().forEach(function (card) {
        // Topic itself is sortable, but lesson rows stop propagation at source.
        bind(card, 'topic');
        lessonRows(card).forEach(function (row) { bind(row, 'lesson'); });
      });
    }

    function showNotice(text, error) {
      if (!noticeBox) {
        noticeBox = document.createElement('div');
        noticeBox.className = 'notice';
        var wrap = document.querySelector('.wrap');
        if (wrap) wrap.insertBefore(noticeBox, wrap.firstChild);
      }
      noticeBox.className = 'notice ' + (error ? 'notice-error' : 'notice-success');
      noticeBox.innerHTML = '';
      var p = document.createElement('p');
      p.textContent = text;
      noticeBox.appendChild(p);
    }

    function addSaveButton() {
      var heading = Array.prototype.slice.call(document.querySelectorAll('h2')).find(function (h) {
        return h.textContent.trim() === '课程内容';
      });
      if (!heading || document.getElementById('mathcourse-save-order')) return;

      saveButton = document.createElement('button');
      saveButton.type = 'button';
      saveButton.id = 'mathcourse-save-order';
      saveButton.className = 'button';
      saveButton.textContent = '保存排序';
      saveButton.style.marginLeft = '12px';
      saveButton.addEventListener('click', saveOrder);
      heading.appendChild(saveButton);
    }

    function saveOrder() {
      setAttrs();
      var cards = directTopicCards();
      var topicOrder = [];
      var lessonOrder = {};

      cards.forEach(function (card) {
        var tid = topicId(card);
        if (!tid) return;
        topicOrder.push(tid);
        lessonOrder[tid] = lessonRows(card).map(lessonId).filter(Boolean);
      });

      if (!topicOrder.length) {
        showNotice('没有检测到可保存的课程结构。', true);
        return;
      }

      var params = new URLSearchParams(window.location.search);
      var courseId = params.get('course_id') || '';
      var data = new FormData();
      data.append('action', 'mathcourse_save_order');
      data.append('nonce', MathCourseOrder.nonce);
      data.append('course_id', courseId);
      topicOrder.forEach(function (tid) { data.append('topic_order[]', String(tid)); });
      Object.keys(lessonOrder).forEach(function (tid) {
        lessonOrder[tid].forEach(function (lid) {
          data.append('lesson_order[' + tid + '][]', String(lid));
        });
      });

      saveButton.disabled = true;
      saveButton.textContent = MathCourseOrder.saving || '正在保存排序…';

      fetch(MathCourseOrder.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: data
      }).then(function (response) {
        return response.json();
      }).then(function (result) {
        if (!result || !result.success) {
          throw new Error(result && result.data && result.data.message ? result.data.message : (MathCourseOrder.error || '排序保存失败。'));
        }
        showNotice(result.data && result.data.message ? result.data.message : (MathCourseOrder.saved || '排序已保存。'), false);
      }).catch(function (error) {
        showNotice(error.message || '排序保存失败，请刷新页面后重试。', true);
      }).finally(function () {
        saveButton.disabled = false;
        saveButton.textContent = '保存排序';
      });
    }

    install();
    addSaveButton();
  });
})();
