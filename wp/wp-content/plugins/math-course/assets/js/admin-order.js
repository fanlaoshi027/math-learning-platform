(function () {
	'use strict';

	if (typeof window.MathCourseOrder === 'undefined') {
		return;
	}

	var dragged = null;
	var courseId = new URLSearchParams(window.location.search).get('course_id');
	var message = null;

	function findTopicCards() {
		return Array.prototype.filter.call(document.querySelectorAll('div'), function (el) {
			return el.querySelector(':scope > div > button[value^="delete_topic_"]') !== null;
		});
	}

	function addHandle(el, text) {
		if (el.querySelector('.mathcourse-drag-handle')) {
			return;
		}
		var handle = document.createElement('span');
		handle.className = 'mathcourse-drag-handle';
		handle.textContent = text || '☰';
		handle.title = '拖动调整顺序';
		handle.setAttribute('aria-label', '拖动调整顺序');
		handle.draggable = true;
		handle.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;width:28px;margin-right:8px;color:#646970;cursor:grab;font-size:16px;user-select:none;';
		el.insertBefore(handle, el.firstChild);
		handle.addEventListener('dragstart', function (event) {
			dragged = el;
			event.dataTransfer.effectAllowed = 'move';
			try { event.dataTransfer.setData('text/plain', 'mathcourse'); } catch (e) {}
			el.style.opacity = '0.45';
		});
		handle.addEventListener('dragend', function () {
			if (dragged) { dragged.style.opacity = ''; }
			dragged = null;
		});
	}

	function installTopicSorting() {
		var topics = findTopicCards();
		topics.forEach(function (topic, index) {
			addHandle(topic, '☰');
			topic.dataset.mathcourseTopic = '1';
			topic.addEventListener('dragover', function (event) {
				if (!dragged || dragged === topic || !topic.dataset.mathcourseTopic) return;
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

	function lessonRows(topic) {
		return Array.prototype.filter.call(topic.querySelectorAll('div'), function (el) {
			return el.querySelector(':scope > div > button[value^="delete_lesson_"]') !== null;
		});
	}

	function installLessonSorting(topic) {
		var rows = lessonRows(topic);
		rows.forEach(function (row) {
			addHandle(row, '⋮⋮');
			row.dataset.mathcourseLesson = '1';
			row.addEventListener('dragover', function (event) {
				if (!dragged || dragged === row || !dragged.dataset.mathcourseLesson || !row.dataset.mathcourseLesson) return;
				event.preventDefault();
				var rect = row.getBoundingClientRect();
				var after = event.clientY > rect.top + rect.height / 2;
				var parent = row.parentNode;
				if (after) parent.insertBefore(dragged, row.nextSibling);
				else parent.insertBefore(dragged, row);
			});
		});
	}

	function getIdFromButton(row, prefix) {
		var button = row.querySelector('button[value^="' + prefix + '"]');
		if (!button) return 0;
		var value = button.getAttribute('value') || '';
		return parseInt(value.replace(prefix, ''), 10) || 0;
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
			.then(function (response) { return response.json(); })
			.then(function (data) {
				if (!data || !data.success) throw new Error('save');
				showMessage(MathCourseOrder.saved, false);
			})
			.catch(function () { showMessage(MathCourseOrder.error, true); });
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
