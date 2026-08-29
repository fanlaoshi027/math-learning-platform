/* MathCourse admin sorting — pointer based, isolated per topic. */
(function () {
  'use strict';
  if (!window.MathCourseOrder) return;

  var active = null;
  var placeholder = null;
  var saveButton = null;
  var message = null;
  var changed = false;

  function qsAll(s, root) { return Array.prototype.slice.call((root || document).querySelectorAll(s)); }
  function closest(el, fn) { while (el && el !== document.body) { if (fn(el)) return el; el = el.parentElement; } return null; }
  function topicCards() {
    return qsAll('button[value^="delete_topic_"]').map(function (b) {
      return closest(b, function (el) { return el !== b && el.querySelector && el.querySelector('button[value^="delete_topic_"]') && /border:\s*1px solid #dcdcde/i.test(el.getAttribute('style') || ''); });
    }).filter(function (x, i, a) { return x && a.indexOf(x) === i; });
  }
  function lessonRows(topic) {
    return qsAll('button[value^="delete_lesson_"]', topic).map(function (b) {
      return closest(b, function (el) { return el !== b && el.parentElement && /border-bottom:\s*1px solid #f0f0f1/i.test(el.getAttribute('style') || ''); });
    }).filter(function (x, i, a) { return x && x.parentElement === a[0].parentElement && a.indexOf(x) === i; });
  }
  function idFrom(row, prefix) { var b = row.querySelector('button[value^="' + prefix + '"]'); return b ? parseInt(b.value.replace(prefix, ''), 10) || 0 : 0; }
  function lessonContainer(topic) { var rows = lessonRows(topic); return rows.length ? rows[0].parentElement : null; }
  function ensurePlaceholder(h) {
    if (!placeholder) {
      placeholder = document.createElement('div');
      placeholder.className = 'mathcourse-pointer-placeholder';
      placeholder.style.cssText = 'box-sizing:border-box;border:2px dashed #2271b1;border-radius:8px;margin:6px 0;background:#f0f6fc;pointer-events:none;';
    }
    placeholder.style.height = Math.max(44, Math.round(h || 44)) + 'px';
  }
  function clearVisual() {
    if (placeholder && placeholder.parentNode) placeholder.parentNode.removeChild(placeholder);
    document.body.classList.remove('mathcourse-sorting-active');
    if (active) { active.el.style.opacity = ''; active.el.style.pointerEvents = ''; active.el.style.position = ''; active.el.style.zIndex = ''; active.el.style.width = ''; active.el.classList.remove('mathcourse-dragging'); }
    active = null;
  }
  function movePlaceholder(container, y) {
    if (!active || active.container !== container) return;
    var rows = qsAll('[data-mathcourse-sort-row="1"]', container).filter(function (r) { return r !== active.el && r !== placeholder; });
    var before = null;
    for (var i = 0; i < rows.length; i++) { var r = rows[i].getBoundingClientRect(); if (y < r.top + r.height / 2) { before = rows[i]; break; } }
    if (before) container.insertBefore(placeholder, before); else container.appendChild(placeholder);
  }
  function start(el, type, e) {
    if (e.button !== undefined && e.button !== 0) return;
    e.preventDefault();
    var container = type === 'lesson' ? el.parentElement : el.parentElement;
    ensurePlaceholder(el.getBoundingClientRect().height);
    active = { el: el, type: type, container: container, pointerId: e.pointerId, startParent: container };
    el.classList.add('mathcourse-dragging');
    el.style.opacity = '.45';
    el.style.pointerEvents = 'none';
    container.insertBefore(placeholder, el);
    document.body.classList.add('mathcourse-sorting-active');
    try { el.setPointerCapture(e.pointerId); } catch (_) {}
  }
  function move(e) {
    if (!active || e.pointerId !== active.pointerId) return;
    e.preventDefault();
    movePlaceholder(active.container, e.clientY);
  }
  function finish(e) {
    if (!active || e.pointerId !== active.pointerId) return;
    e.preventDefault();
    if (placeholder && placeholder.parentNode === active.container) {
      active.container.insertBefore(active.el, placeholder);
      changed = true;
    }
    clearVisual();
    qsAll('[data-mathcourse-sort-row="1"]').forEach(function (r) { r.style.userSelect = ''; });
  }
  function bind(el, type) {
    if (el._mcPointerBound) return;
    el._mcPointerBound = true;
    el.setAttribute('data-mathcourse-sort-row', '1');
    el.style.touchAction = 'none';
    el.addEventListener('pointerdown', function (e) { start(el, type, e); });
    el.addEventListener('pointermove', move);
    el.addEventListener('pointerup', finish);
    el.addEventListener('pointercancel', finish);
  }
  function install() {
    var cards = topicCards();
    cards.forEach(function (topic) {
      bind(topic, 'topic');
      var c = lessonContainer(topic);
      if (c) lessonRows(topic).forEach(function (row) { bind(row, 'lesson'); });
    });
  }
  function show(text, error) {
    if (!message) { message = document.createElement('div'); var wrap = document.querySelector('.wrap'); if (wrap) wrap.insertBefore(message, wrap.children[1] || wrap.firstChild); }
    message.className = 'notice ' + (error ? 'notice-error' : 'notice-success') + ' is-dismissible'; message.innerHTML = '<p></p>'; message.querySelector('p').textContent = text;
  }
  function save() {
    var cards = topicCards(), topics = [], lessons = {};
    cards.forEach(function (topic) { var tid = idFrom(topic, 'delete_topic_'); if (!tid) return; topics.push(tid); lessons[tid] = lessonRows(topic).map(function (r) { return idFrom(r, 'delete_lesson_'); }).filter(Boolean); });
    if (!topics.length) { show('没有检测到可保存的课程结构。', true); return; }
    var fd = new FormData(); fd.append('action', 'mathcourse_save_order'); fd.append('nonce', MathCourseOrder.nonce); fd.append('course_id', new URLSearchParams(location.search).get('course_id') || '');
    topics.forEach(function (id) { fd.append('topic_order[]', id); (lessons[id] || []).forEach(function (lid) { fd.append('lesson_order[' + id + '][]', lid); }); });
    saveButton.disabled = true; saveButton.textContent = '正在保存排序…';
    fetch(MathCourseOrder.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd }).then(function (r) { return r.json(); }).then(function (d) { if (!d || !d.success) throw new Error(d && d.data && d.data.message || '排序保存失败。'); changed = false; show(d.data.message || '排序已保存。', false); }).catch(function (err) { show(err.message || '排序保存失败。', true); }).finally(function () { saveButton.disabled = false; saveButton.textContent = '保存排序'; });
  }
  function button() {
    var h = qsAll('h2').find(function (x) { return x.textContent.trim() === '课程内容'; });
    if (!h || document.getElementById('mathcourse-save-order')) return;
    saveButton = document.createElement('button'); saveButton.type = 'button'; saveButton.id = 'mathcourse-save-order'; saveButton.className = 'button'; saveButton.textContent = '保存排序'; saveButton.style.marginLeft = '10px'; saveButton.onclick = save; h.appendChild(saveButton);
  }
  function css() { if (document.getElementById('mathcourse-pointer-sort-css')) return; var s = document.createElement('style'); s.id = 'mathcourse-pointer-sort-css'; s.textContent = '.mathcourse-sorting-active,.mathcourse-sorting-active *{cursor:grabbing!important}.mathcourse-dragging{box-shadow:0 5px 16px rgba(0,0,0,.12)!important}.mathcourse-pointer-placeholder{display:block!important;pointer-events:none!important}'; document.head.appendChild(s); }
  function init() { css(); install(); button(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
