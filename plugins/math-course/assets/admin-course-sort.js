/* MathCourse course sorting. */
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

    function topicCards() {
      return Array.prototype.slice.call(document.querySelectorAll('.mathcourse-topic-card'));
    }

    function topicId(card) {
      return parseInt(card.getAttribute('data-topic-id') || '0', 10) || 0;
    }

    function lessonRows(card) {
      return Array.prototype.slice.call(card.querySelectorAll('.mathcourse-lesson-row'));
    }

    function lessonId(row) {
      return parseInt(row.getAttribute('data-lesson-id') || '0', 10) || 0;
    }

    function lessonContainer(card) {
      var rows = lessonRows(card);
      return rows.length ? rows[0].parentElement : null;
    }

    function makeHandle(text, extraClass) {
      var handle = document.createElement('span');
      handle.className = 'mathcourse-sort-handle ' + extraClass;
      handle.textContent = '⋮⋮';
      handle.title = text;
      handle.setAttribute('aria-label', text);
      handle.setAttribute('draggable', 'true');
      handle.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;width:30px;min-width:30px;margin-right:8px;color:#8c8f94;font-size:17px;font-weight:700;line-height:1;cursor:grab;user-select:none;touch-action:none;';
      return handle;
    }

    function installHandles() {
      topicCards().forEach(function (card) {
        var header = card.querySelector('.mathcourse-topic-header');
        if (header && !header.querySelector('.mathcourse-topic-sort-handle')) {
          var topicHandle = makeHandle('拖动调整专题顺序', 'mathcourse-topic-sort-handle');
          header.insertBefore(topicHandle, header.firstChild);
          bind(topicHandle, card, 'topic');
        }

        var rows = lessonRows(card);
        rows.forEach(function (row) {
          if (row.querySelector('.mathcourse-lesson-sort-handle')) return;
          var handle = makeHandle('拖动调整课时顺序', 'mathcourse-lesson-sort-handle');
          row.insertBefore(handle, row.firstChild);
          bind(handle, row, 'lesson');
        });
      });
    }

    function bind(handle, item, type) {
      handle.addEventListener('pointerdown', function (event) {
        if (event.button !== undefined && event.button !== 0) return;
        event.preventDefault();
        event.stopPropagation();
        var container = item.parentElement;
        if (!container) return;
        active = { el: item, type: type, container: container, pointerId: event.pointerId };
        placeholder.style.height = Math.max(44, Math.round(item.getBoundingClientRect().height)) + 'px';
        container.insertBefore(placeholder, item);
        item.style.opacity = '0.45';
        item.style.boxShadow = '0 5px 16px rgba(0,0,0,.12)';
        document.body.classList.add('mathcourse-sorting');
        try { handle.setPointerCapture(event.pointerId); } catch (ignore) {}
      });

      handle.addEventListener('pointermove', function (event) {
        if (!active || active.pointerId !== event.pointerId) return;
        movePlaceholder(event.clientY);
      });
      handle.addEventListener('pointerup', finish);
      handle.addEventListener('pointercancel', finish);
    }

    document.addEventListener('pointermove', function (event) {
      if (!active || active.pointerId !== event.pointerId) return;
      movePlaceholder(event.clientY);
    }, { passive: false });

    document.addEventListener('pointerup', function (event) {
      if (active && active.pointerId === event.pointerId) finish(event);
    });

    function movePlaceholder(clientY) {
      if (!active) return;
      var container = active.container;
      var selector = active.type === 'lesson' ? '.mathcourse-lesson-row' : '.mathcourse-topic-card';
      var items = Array.prototype.slice.call(container.children).filter(function (child) {
        return child !== active.el && child !== placeholder && child.matches && child.matches(selector);
      });
      var before = null;
      for (var i = 0; i < items.length; i++) {
        var rect = items[i].getBoundingClientRect();
        if (clientY < rect.top + rect.height / 2) { before = items[i]; break; }
      }
      if (before) container.insertBefore(placeholder, before);
      else container.appendChild(placeholder);
    }

    function finish(event) {
      if (!active || active.pointerId !== event.pointerId) return;
      event.preventDefault();
      event.stopPropagation();
      if (placeholder.parentNode === active.container) active.container.insertBefore(active.el, placeholder);
      active.el.style.opacity = '';
      active.el.style.boxShadow = '';
      if (placeholder.parentNode) placeholder.parentNode.removeChild(placeholder);
      document.body.classList.remove('mathcourse-sorting');
      active = null;
    }

    function showNotice(text, error) {
      if (!noticeBox) {
        noticeBox = document.createElement('div');
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
      var cards = topicCards();
      var topicOrder = [];
      var lessonOrder = {};
      cards.forEach(function (card) {
        var tid = topicId(card);
        if (!tid) return;
        topicOrder.push(tid);
        lessonOrder[tid] = lessonRows(card).map(lessonId).filter(Boolean);
      });
      if (!topicOrder.length) { showNotice('没有检测到可保存的课程结构。', true); return; }

      var params = new URLSearchParams(window.location.search);
      var data = new FormData();
      data.append('action', 'mathcourse_save_order');
      data.append('nonce', MathCourseOrder.nonce);
      data.append('course_id', params.get('course_id') || '');
      topicOrder.forEach(function (tid) { data.append('topic_order[]', String(tid)); });
      Object.keys(lessonOrder).forEach(function (tid) {
        lessonOrder[tid].forEach(function (lid) { data.append('lesson_order[' + tid + '][]', String(lid)); });
      });

      saveButton.disabled = true;
      saveButton.textContent = MathCourseOrder.saving || '正在保存排序…';
      fetch(MathCourseOrder.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data })
        .then(function (response) { return response.json(); })
        .then(function (result) {
          if (!result || !result.success) throw new Error(result && result.data && result.data.message ? result.data.message : (MathCourseOrder.error || '排序保存失败。'));
          showNotice(result.data && result.data.message ? result.data.message : (MathCourseOrder.saved || '排序已保存。'), false);
        })
        .catch(function (error) { showNotice(error.message || '排序保存失败，请刷新页面后重试。', true); })
        .finally(function () { saveButton.disabled = false; saveButton.textContent = '保存排序'; });
    }

    installHandles();
    addSaveButton();
  });
})();