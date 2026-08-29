/* MathCourse admin course sorting. Lessons are isolated to their own topic. */
(function () {
  'use strict';
  if (typeof window.MathCourseOrder === 'undefined') return;

  var dragged = null;
  var placeholder = null;
  var courseId = new URLSearchParams(window.location.search).get('course_id');
  var saveButton = null;
  var message = null;

  function closest(el, test) {
    while (el && el !== document.body) { if (test(el)) return el; el = el.parentElement; }
    return null;
  }
  function topicCards() {
    var result = [];
    document.querySelectorAll('button[value^="delete_topic_"]').forEach(function (button) {
      var card = closest(button, function (el) { return el !== button && !!el.querySelector('button[value^="delete_topic_"]') && /border:\s*1px solid #dcdcde/i.test(String(el.getAttribute('style') || '')); });
      if (card && result.indexOf(card) === -1) result.push(card);
    });
    return result;
  }
  function topicId(topic) { var b = topic.querySelector('button[value^="delete_topic_"]'); return b ? parseInt(b.value.replace('delete_topic_', ''), 10) || 0 : 0; }
  function lessonRows(topic) {
    var result = [];
    topic.querySelectorAll('button[value^="delete_lesson_"]').forEach(function (button) {
      var row = closest(button, function (el) { return el !== button && !!el.querySelector('button[value^="delete_lesson_"]') && /border-bottom:\s*1px solid #f0f0f1/i.test(String(el.getAttribute('style') || '')); });
      if (row && result.indexOf(row) === -1) result.push(row);
    });
    return result;
  }
  function lessonId(row) { var b = row.querySelector('button[value^="delete_lesson_"]'); return b ? parseInt(b.value.replace('delete_lesson_', ''), 10) || 0 : 0; }
  function lessonContainer(topic) { var rows = lessonRows(topic); return rows.length ? rows[0].parentElement : null; }
  function makePlaceholder(height) { var el = document.createElement('div'); el.className = 'mathcourse-sort-placeholder'; el.style.cssText = 'height:' + Math.max(42, height) + 'px;box-sizing:border-box;border:2px dashed #2271b1;border-radius:8px;margin:6px 0;background:#f0f6fc;pointer-events:none;'; return el; }
  function clearDrag() {
    if (placeholder && placeholder.parentNode) placeholder.parentNode.removeChild(placeholder);
    placeholder = null;
    if (dragged) { dragged.classList.remove('mathcourse-dragging'); dragged.style.opacity = ''; }
    document.querySelectorAll('.mathcourse-drop-active').forEach(function (el) { el.classList.remove('mathcourse-drop-active'); });
    dragged = null;
  }
  function beginDrag(item, type, event) {
    dragged = item;
    dragged._mathcourseDragType = type;
    dragged._mathcourseOriginContainer = type === 'lesson' ? dragged.parentElement : null;
    dragged.classList.add('mathcourse-dragging');
    dragged.style.opacity = '0.45';
    placeholder = makePlaceholder(dragged.getBoundingClientRect().height);
    try { event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', type + ':' + (type === 'lesson' ? lessonId(item) : topicId(item))); } catch (e) {}
  }
  function installItem(item, type) {
    item.setAttribute('draggable', 'true');
    item.addEventListener('dragstart', function (event) { beginDrag(item, type, event); });
    item.addEventListener('dragend', clearDrag);
  }
  function installLessonContainer(container) {
    if (!container || container._mathcourseSortReady) return;
    container._mathcourseSortReady = true;
    container.classList.add('mathcourse-lesson-sort-container');
    container.addEventListener('dragover', function (event) {
      if (!dragged || dragged._mathcourseDragType !== 'lesson' || dragged._mathcourseOriginContainer !== container) return;
      event.preventDefault(); event.stopPropagation();
      try { event.dataTransfer.dropEffect = 'move'; } catch (e) {}
      container.classList.add('mathcourse-drop-active');
      var target = closest(event.target, function (el) { return el && el.parentElement === container && el !== dragged && el !== placeholder; });
      if (!target) { if (!placeholder.parentNode) container.appendChild(placeholder); return; }
      var rect = target.getBoundingClientRect();
      if (event.clientY < rect.top + rect.height / 2) container.insertBefore(placeholder, target); else container.insertBefore(placeholder, target.nextSibling);
    });
    container.addEventListener('drop', function (event) {
      if (!dragged || dragged._mathcourseDragType !== 'lesson' || dragged._mathcourseOriginContainer !== container) return;
      event.preventDefault(); event.stopPropagation();
      if (placeholder && placeholder.parentNode === container) container.insertBefore(dragged, placeholder); else container.appendChild(dragged);
      clearDrag();
    });
  }
  function installTopicSorting() {
    var cards = topicCards();
    if (!cards.length) return;
    var parent = cards[0].parentElement;
    if (!parent || parent._mathcourseTopicSortReady) return;
    parent._mathcourseTopicSortReady = true;
    parent.classList.add('mathcourse-topic-sort-container');
    cards.forEach(function (topic) {
      installItem(topic, 'topic');
      var container = lessonContainer(topic);
      if (container) { lessonRows(topic).forEach(function (row) { installItem(row, 'lesson'); }); installLessonContainer(container); }
    });
    parent.addEventListener('dragover', function (event) {
      if (!dragged || dragged._mathcourseDragType !== 'topic') return;
      event.preventDefault(); event.stopPropagation();
      var target = closest(event.target, function (el) { return el && el.parentElement === parent && el !== dragged && el._mathcourseDragType === 'topic'; });
      if (!target) return;
      if (!placeholder) placeholder = makePlaceholder(dragged.getBoundingClientRect().height);
      var rect = target.getBoundingClientRect();
      if (event.clientY < rect.top + rect.height / 2) parent.insertBefore(placeholder, target); else parent.insertBefore(placeholder, target.nextSibling);
    });
    parent.addEventListener('drop', function (event) {
      if (!dragged || dragged._mathcourseDragType !== 'topic') return;
      event.preventDefault(); event.stopPropagation();
      if (placeholder && placeholder.parentNode === parent) parent.insertBefore(dragged, placeholder); else parent.appendChild(dragged);
      clearDrag();
    });
  }
  function showMessage(text, error) {
    if (!message) { message = document.createElement('div'); message.className = 'notice is-dismissible'; var wrap = document.querySelector('.wrap'); if (wrap) wrap.insertBefore(message, wrap.children[1] || wrap.firstChild); }
    message.classList.remove('notice-success', 'notice-error'); message.classList.add(error ? 'notice-error' : 'notice-success');
    var p = message.querySelector('p') || message.appendChild(document.createElement('p')); p.textContent = text;
  }
  function saveOrder() {
    var cards = topicCards(), topicOrder = [], lessonOrder = {};
    cards.forEach(function (topic) { var id = topicId(topic); if (!id) return; topicOrder.push(id); lessonOrder[id] = lessonRows(topic).map(lessonId).filter(Boolean); });
    if (!courseId || !topicOrder.length) { showMessage('没有检测到可保存的课程结构。', true); return; }
    var form = new FormData(); form.append('action', 'mathcourse_save_order'); form.append('nonce', MathCourseOrder.nonce); form.append('course_id', courseId);
    topicOrder.forEach(function (id) { form.append('topic_order[]', id); });
    Object.keys(lessonOrder).forEach(function (id) { lessonOrder[id].forEach(function (lesson) { form.append('lesson_order[' + id + '][]', lesson); }); });
    if (saveButton) { saveButton.disabled = true; saveButton.textContent = MathCourseOrder.saving || '正在保存排序…'; }
    fetch(MathCourseOrder.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); }).then(function (data) { if (!data || !data.success) throw new Error(data && data.data && data.data.message ? data.data.message : (MathCourseOrder.error || '排序保存失败。')); showMessage((data.data && data.data.message) || MathCourseOrder.saved || '排序已保存。', false); }).catch(function (e) { showMessage(e.message || MathCourseOrder.error || '排序保存失败。', true); }).finally(function () { if (saveButton) { saveButton.disabled = false; saveButton.textContent = '保存排序'; } });
  }
  function addSaveButton() {
    var heading = Array.prototype.slice.call(document.querySelectorAll('h2')).find(function (el) { return el.textContent.trim() === '课程内容'; });
    if (!heading || document.getElementById('mathcourse-save-order')) return;
    saveButton = document.createElement('button'); saveButton.type = 'button'; saveButton.id = 'mathcourse-save-order'; saveButton.className = 'button'; saveButton.textContent = '保存排序'; saveButton.style.marginLeft = '10px'; saveButton.addEventListener('click', saveOrder); heading.appendChild(saveButton);
  }
  function styles() { if (document.getElementById('mathcourse-sort-styles')) return; var s = document.createElement('style'); s.id = 'mathcourse-sort-styles'; s.textContent = '.mathcourse-topic-sort-container{position:relative}.mathcourse-lesson-sort-container{position:relative;min-height:12px;transition:background .12s,outline .12s}.mathcourse-lesson-sort-container.mathcourse-drop-active{background:rgba(34,113,177,.045);outline:1px dashed #2271b1;outline-offset:2px}.mathcourse-dragging{box-shadow:0 4px 12px rgba(0,0,0,.08)}.mathcourse-sort-placeholder{pointer-events:none}'; document.head.appendChild(s); }
  function init() { styles(); installTopicSorting(); addSaveButton(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
