(function () {
	'use strict';

	if (typeof window.MathCourseOrder === 'undefined') return;

	var dragged = null;
	var courseId = new URLSearchParams(window.location.search).get('course_id');
	var message = null;

	function closestBy(el, test) {
		while (el && el !== document.body) {
			if (test(el)) return el;
			el = el.parentElement;
		}
		return null;
	}

	function hasButton(el, prefix) {
		return !!el.querySelector('button[value^="' + prefix + '"]');
	}

	function getTopicCardFromButton(button) {
		return closestBy(button, function (el) {
			return el !== button && hasButton(el, 'delete_topic_') && /border:\s*1px solid #dcdcde/i.test(String(el.getAttribute('style') || ''));
		});
	}

	function getLessonRowFromButton(button) {
		return closestBy(button, function (el) {
			var style = String(el.getAttribute('style') || '');
			return el !== button && /border-bottom:\s*1px solid #f0f0f1/i.test(style) && hasButton(el, 'delete_lesson_');
		});
	}

	function findTopicCards() {
		var result = [];
		document.querySelectorAll('button[value^="delete_topic_"]').forEach(function (button) {
			var card = getTopicCardFromButton(button);
			if (card && result.indexOf(card) === -1) result.push(card);
		});
		return result;
	}

	function lessonRows(topic) {
		var result = [];
		topic.querySelectorAll('button[value^="delete_lesson_"]').forEach(function (button) {
			var row = getLessonRowFromButton(button);
			if (row && result.indexOf(row) === -1) result.push(row);
		});
		return result;
	}

	function getIdFromButton(row, prefix) {
		var button = row.querySelector('button[value^="' + prefix + '"]');
		return button ? parseInt((button.getAttribute('value') || '').replace(prefix, ''), 10) || 0 : 0;
	}

	function getLessonContainer(topic) {
		var rows = lessonRows(topic);
		if (!rows.length) return null;
		return rows[0].parentNode;
	}

	function addHandle(el, text, type) {
		if (el.querySelector(':scope > .mathcourse-drag-handle')) return;
		var handle = document.createElement('span');
		handle.className = 'mathcourse-drag-handle';
		handle.textContent = text;
		handle.title = '拖动调整顺序';
		handle.setAttribute('aria-label', '拖动调整顺序');
		handle.draggable = true;
		handle.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;width:28px;margin-right:8px;color:#646970;cursor:grab;font-size:16px;user-select:none;';
		el.insertBefore(handle, el.firstChild);

		handle.addEventListener('dragstart', function (event) {
			dragged = el;
			dragged.dataset.mathcourseDragType = type;
			dragged.dataset.mathcourseOriginParent = type === 'lesson' ? (dragged.parentNode ? 'lesson-container' : '') : '';
			event.dataTransfer.effectAllowed = 'move';
			try { event.dataTransfer.setData('text/plain', 'mathcourse-' + type); } catch (e) {}
			el.classList.add('mathcourse-dragging');
			el.style.opacity = '0.45';
		});

		handle.addEventListener('dragend', function () {
			if (dragged) {
				dragged.style.opacity = '';
				dragged.classList.remove('mathcourse-dragging');
			}
			document.querySelectorAll('.mathcourse-drop-active').forEach(function (el) { el.classList.remove('mathcourse-drop-active'); });
			dragged = null;
		});
	}

	function installTopicSorting() {
		var cards = findTopicCards();
		cards.forEach(function (topic) {
			addHandle(topic, '☰', 'topic');
			topic.dataset.mathcourseTopic = '1';
			topic.addEventListener('dragover', function (event) {
				if (!dragged || dragged.dataset.mathcourseDragType !== 'topic' || dragged === topic) return;
				event.preventDefault();
				event.dataTransfer.dropEffect = 'move';
				var rect = topic.getBoundingClientRect();
				var parent = topic.parentNode;
				if (event.clientY > rect.top + rect.height / 2) parent.insertBefore(dragged, topic.nextSibling);
				else parent.insertBefore(dragged, topic);
			});
			installLessonSorting(topic);
		});
	}

	function installLessonSorting(topic) {
		var rows = lessonRows(topic);
		var container = getLessonContainer(topic);
		if (!container) return;

		container.classList.add('mathcourse-lesson-sort-container');
		container.style.minHeight = rows.length ? '' : '42px';
		container.addEventListener('dragover', function (event) {
			if (!dragged || dragged.dataset.mathcourseDragType !== 'lesson') return;
			/* A lesson may only enter its original container. */
			if (dragged.parentNode !== container) return;
			event.preventDefault();
			event.dataTransfer.dropEffect = 'move';
			container.classList.add('mathcourse-drop-active');
		});
		container.addEventListener('dragleave', function (event) {
			if (!container.contains(event.relatedTarget)) container.classList.remove('mathcourse-drop-active');
		});

		rows.forEach(function (row) {
			addHandle(row, '⋮⋮', 'lesson');
			row.dataset.mathcourseLesson = '1';
			row.addEventListener('dragover', function (event) {
				if (!dragged || dragged.dataset.mathcourseDragType !== 'lesson' || dragged === row) return;
				if (dragged.parentNode !== container || row.parentNode !== container) return;
				event.preventDefault();
				event.stopPropagation();
				event.dataTransfer.dropEffect = 'move';
				container.classList.add('mathcourse-drop-active');
				var rect = row.getBoundingClientRect();
				if (event.clientY > rect.top + rect.height / 2) container.insertBefore(dragged, row.nextSibling);
				else container.insertBefore(dragged, row);
			});
		});
	}

	function addSortStyles() {
		if (document.getElementById('mathcourse-order-styles')) return;
		var style = document.createElement('style');
		style.id = 'mathcourse-order-styles';
		style.textContent = '.mathcourse-lesson-sort-container{position:relative;transition:background .12s,outline .12s}.mathcourse-lesson-sort-container.mathcourse-drop-active{background:rgba(34,113,177,.045);outline:1px dashed #2271b1;outline-offset:2px}.mathcourse-dragging{box-shadow:0 4px 12px rgba(0,0,0,.08)}';
		document.head.appendChild(style);
	}

	function showMessage(text, error) {
		if (!message) {
			message = document.createElement('div');
			message.className = 'notice is-dismissible';
			var wrap = document.querySelector('.wrap');
			if (wrap) wrap.insertBefore(message, wrap.children[1] || wrap.firstChild);
		}
		message.classList.remove('notice-success', 'notice-error');
		message.classList.add(error ? 'notice-error' : 'notice-success');
		message.innerHTML = '<p>' + String(text).replace(/[&<>]/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;'})[c]; }) + '</p>';
	}

	function saveOrder() {
		var topics = findTopicCards();
		var topicOrder = [];
		var lessonOrder = {};

		topics.forEach(function (topic) {
			var topicId = getIdFromButton(topic, 'delete_topic_');
			if (!topicId) return;
			topicOrder.push(topicId);
			lessonOrder[topicId] = [];
			lessonRows(topic).forEach(function (row) {
				var lessonId = getIdFromButton(row, 'delete_lesson_');
				if (lessonId) lessonOrder[topicId].push(lessonId);
			});
		});

		if (!courseId || !topicOrder.length) {
			showMessage('没有检测到可保存的课程结构。', true);
			return;
		}

		var form = new FormData();
		form.append('action', 'mathcourse_save_order');
		form.append('nonce', MathCourseOrder.nonce);
		form.append('course_id', courseId);
		topicOrder.forEach(function (id) { form.append('topic_order[]', id); });
		Object.keys(lessonOrder).forEach(function (topicId) {
			lessonOrder[topicId].forEach(function (lessonId) { form.append('lesson_order[' + topicId + '][]', lessonId); });
		});

		showMessage(MathCourseOrder.saving, false);
		fetch(MathCourseOrder.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form })
			.then(function (response) { if (!response.ok) throw new Error('HTTP ' + response.status); return response.json(); })
			.then(function (data) {
				if (!data || !data.success) throw new Error(data && data.data && data.data.message ? data.data.message : MathCourseOrder.error);
				showMessage((data.data && data.data.message) || MathCourseOrder.saved, false);
			})
			.catch(function (error) { showMessage(error.message || MathCourseOrder.error, true); });
	}

	function addSaveButton() {
		var headings = Array.prototype.slice.call(document.querySelectorAll('h2'));
		var contentHeading = headings.find(function (el) { return el.textContent.trim() === '课程内容'; });
		if (!contentHeading || document.getElementById('mathcourse-save-order')) return;
		var button = document.createElement('button');
		button.type = 'button';
		button.id = 'mathcourse-save-order';
		button.className = 'button';
		button.textContent = '保存排序';
		button.style.marginLeft = '10px';
		button.addEventListener('click', saveOrder);
		contentHeading.appendChild(button);
	}

	function init() {
		addSortStyles();
		installTopicSorting();
		addSaveButton();
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
