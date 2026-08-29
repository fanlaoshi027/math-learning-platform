(function () {
	'use strict';

	if (typeof window.MathCourseOrder === 'undefined') {
		return;
	}

	var dragged = null;
	var courseId = new URLSearchParams(window.location.search).get('course_id');
	var message = null;

	function hasButton(el, prefix) {
		return !!el.querySelector('button[value^="' + prefix + '"]');
	}

	function closestBy(el, test) {
		while (el && el !== document.body) {
			if (test(el)) return el;
			el = el.parentElement;
		}
		return null;
	}

	/*
	 * The editor markup is intentionally kept simple. Instead of relying on
	 * generic div selectors, locate the actual topic card from its delete
	 * button. This prevents lesson rows from being mistaken for topics.
	 */
	function getTopicCardFromButton(button) {
		return closestBy(button, function (el) {
			return el !== button && hasButton(el, 'delete_topic_') && (
				String(el.getAttribute('style') || '').indexOf('border:1px solid #dcdcde') !== -1 ||
				String(el.getAttribute('style') || '').indexOf('border: 1px solid #dcdcde') !== -1
			);
		});
	}

	function getLessonRowFromButton(button) {
		return closestBy(button, function (el) {
			var style = String(el.getAttribute('style') || '');
			return el !== button && style.indexOf('border-bottom:1px solid #f0f0f1') !== -1 && hasButton(el, 'delete_lesson_');
		});
	}

	function findTopicCards() {
		var result = [];
		Array.prototype.forEach.call(document.querySelectorAll('button[value^="delete_topic_"]'), function (button) {
			var card = getTopicCardFromButton(button);
			if (card && result.indexOf(card) === -1) result.push(card);
		});
		return result;
	}

	function lessonRows(topic) {
		var result = [];
		Array.prototype.forEach.call(topic.querySelectorAll('button[value^="delete_lesson_"]'), function (button) {
			var row = getLessonRowFromButton(button);
			if (row && result.indexOf(row) === -1) result.push(row);
		});
		return result;
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
			event.dataTransfer.effectAllowed = 'move';
			try { event.dataTransfer.setData('text/plain', 'mathcourse-' + type); } catch (e) {}
			el.style.opacity = '0.45';
		});
		handle.addEventListener('dragend', function () {
			if (dragged) dragged.style.opacity = '';
			dragged = null;
		});
	}

	function installTopicSorting() {
		findTopicCards().forEach(function (topic) {
			addHandle(topic, '☰', 'topic');
			topic.dataset.mathcourseTopic = '1';
			topic.addEventListener('dragover', function (event) {
				if (!dragged || dragged === topic || dragged.dataset.mathcourseDragType !== 'topic') return;
				event.preventDefault();
				var rect = topic.getBoundingClientRect();
				var after = event.clientY > rect.top + rect.height / 2;
				var parent = topic.parentNode;
				if (after) parent.insertBefore(dragged, topic.nextSibling);
				else parent.insertBefore(dragged, topic);
			});
			installLessonSorting(topic);
		});
	}

	function installLessonSorting(topic) {
		var rows = lessonRows(topic);
		var lessonContainer = rows.length ? rows[0].parentNode : null;
		rows.forEach(function (row) {
			addHandle(row, '⋮⋮', 'lesson');
			row.dataset.mathcourseLesson = '1';
			row.addEventListener('dragover', function (event) {
				/* Lessons may ONLY be reordered inside their current topic. */
				if (!dragged || dragged === row || dragged.dataset.mathcourseDragType !== 'lesson') return;
				if (dragged.parentNode !== row.parentNode) return;
				event.preventDefault();
				var rect = row.getBoundingClientRect();
				var after = event.clientY > rect.top + rect.height / 2;
				var parent = row.parentNode;
				if (after) parent.insertBefore(dragged, row.nextSibling);
				else parent.insertBefore(dragged, row);
			});
		});
		if (lessonContainer) {
			lessonContainer.addEventListener('dragover', function (event) {
				if (!dragged || dragged.dataset.mathcourseDragType !== 'lesson') return;
				if (dragged.parentNode !== lessonContainer) return;
				event.preventDefault();
			});
		}
	}

	function getIdFromButton(row, prefix) {
		var button = row.querySelector('button[value^="' + prefix + '"]');
		if (!button) return 0;
		return parseInt((button.getAttribute('value') || '').replace(prefix, ''), 10) || 0;
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

		if (!topicOrder.length) {
			showMessage('没有检测到可保存的课程结构。', true);
			return;
		}

		var form = new FormData();
		form.append('action', 'mathcourse_save_order');
		form.append('nonce', MathCourseOrder.nonce);
		form.append('course_id', courseId || '0');
		topicOrder.forEach(function (id) { form.append('topic_order[]', id); });
		Object.keys(lessonOrder).forEach(function (topicId) {
			lessonOrder[topicId].forEach(function (lessonId) { form.append('lesson_order[' + topicId + '][]', lessonId); });
		});

		showMessage(MathCourseOrder.saving, false);
		fetch(MathCourseOrder.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: form })
			.then(function (response) {
				if (!response.ok) throw new Error('http');
				return response.json();
			})
			.then(function (data) {
				if (!data || !data.success) throw new Error(data && data.data && data.data.message ? data.data.message : 'save');
				showMessage((data.data && data.data.message) || MathCourseOrder.saved, false);
			})
			.catch(function (error) {
				showMessage(error.message && error.message !== 'save' ? error.message : MathCourseOrder.error, true);
			});
	}

	function addSaveButton() {
		var contentHeading = Array.prototype.find.call(document.querySelectorAll('h2'), function (el) { return el.textContent.trim() === '课程内容'; });
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
		installTopicSorting();
		addSaveButton();
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
