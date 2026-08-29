/* MathCourse admin course sorting.
 * Lessons are intentionally isolated per topic: they can only move within their
 * own .mathcourse-lesson-list. Topics use a separate sortable container.
 */
(function () {
  'use strict';

  function init() {
    var topics = document.querySelector('[data-mathcourse-topics-sortable]');
    if (!topics) return;

    function enable(container, selector) {
      var items = Array.prototype.slice.call(container.querySelectorAll(':scope > ' + selector));
      if (!items.length) return;

      var dragged = null;
      var placeholder = document.createElement('div');
      placeholder.className = 'mathcourse-sort-placeholder';
      placeholder.style.height = '44px';
      placeholder.style.border = '2px dashed #2271b1';
      placeholder.style.borderRadius = '8px';
      placeholder.style.boxSizing = 'border-box';
      placeholder.style.margin = '6px 0';
      placeholder.style.background = '#f0f6fc';
      placeholder.hidden = true;

      items.forEach(function (item) {
        item.setAttribute('draggable', 'true');
        item.addEventListener('dragstart', function (e) {
          dragged = item;
          item.classList.add('mathcourse-is-dragging');
          e.dataTransfer.effectAllowed = 'move';
          e.dataTransfer.setData('text/plain', item.getAttribute('data-id') || '');
          placeholder.hidden = false;
          container.appendChild(placeholder);
        });
        item.addEventListener('dragend', function () {
          item.classList.remove('mathcourse-is-dragging');
          placeholder.hidden = true;
          if (dragged && dragged.parentNode !== container) container.appendChild(dragged);
          dragged = null;
        });
      });

      container.addEventListener('dragover', function (e) {
        if (!dragged || dragged.parentNode !== container) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        var target = e.target.closest(selector);
        if (!target || target === dragged || target.parentNode !== container) return;
        var rect = target.getBoundingClientRect();
        if (e.clientY < rect.top + rect.height / 2) container.insertBefore(placeholder, target);
        else container.insertBefore(placeholder, target.nextSibling);
      });

      container.addEventListener('drop', function (e) {
        if (!dragged || dragged.parentNode !== container) return;
        e.preventDefault();
        container.insertBefore(dragged, placeholder);
        placeholder.hidden = true;
        container.dispatchEvent(new CustomEvent('mathcourse-sort-changed', { bubbles: true }));
      });
    }

    enable(topics, '[data-mathcourse-topic]');
    Array.prototype.forEach.call(topics.querySelectorAll('[data-mathcourse-lessons-sortable]'), function (lessons) {
      enable(lessons, '[data-mathcourse-lesson]');
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
