(function (global) {
	'use strict';

	function normalize(text) {
		return String(text || '').trim().toLowerCase();
	}

	global.zmcPickBox = function (input, options) {
		if (!input || input.zmcPickBound) { return null; }
		input.zmcPickBound = true;

		var opts = options || {};
		var items = opts.items || [];
		var hidden = input.parentNode.querySelector('input[type="hidden"][data-pick-value]');
		var box = document.createElement('div');
		box.className = 'zmc-pick-list';
		box.hidden = true;
		input.parentNode.appendChild(box);
		input.setAttribute('autocomplete', 'off');

		var api = {};
		var active = -1;

		function setValue(code, name) {
			if (hidden) { hidden.value = code || ''; }
			input.value = name || '';
			input.dataset.picked = code ? '1' : '';
			if (typeof opts.onPick === 'function') { opts.onPick(code || ''); }
		}

		function close() {
			box.hidden = true;
			active = -1;
		}

		function render(list) {
			box.innerHTML = '';
			if (!list.length) {
				var none = document.createElement('div');
				none.className = 'zmc-pick-empty';
				none.textContent = opts.empty || '';
				box.appendChild(none);
				box.hidden = false;
				return;
			}
			list.slice(0, 60).forEach(function (item, index) {
				var row = document.createElement('div');
				row.className = 'zmc-pick-item';
				row.textContent = item.name;
				row.addEventListener('mousedown', function (e) {
					e.preventDefault();
					setValue(item.code, item.name);
					close();
				});
				row.addEventListener('mouseenter', function () {
					active = index;
					mark();
				});
				box.appendChild(row);
			});
			active = -1;
			box.hidden = false;
		}

		function mark() {
			var rows = box.querySelectorAll('.zmc-pick-item');
			for (var i = 0; i < rows.length; i++) {
				rows[i].classList.toggle('is-on', i === active);
			}
		}

		function search(text) {
			var q = normalize(text);
			if (q === '') { return items; }
			return items.filter(function (item) {
				return String(item.keywords || item.name).indexOf(q) !== -1;
			});
		}

		input.addEventListener('focus', function () { render(search(input.value)); });
		input.addEventListener('input', function () {
			if (hidden) { hidden.value = ''; }
			input.dataset.picked = '';
			render(search(input.value));
		});
		input.addEventListener('keydown', function (e) {
			var rows = box.querySelectorAll('.zmc-pick-item');
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				if (box.hidden) { render(search(input.value)); return; }
				e.preventDefault();
				active += (e.key === 'ArrowDown' ? 1 : -1);
				if (active < 0) { active = rows.length - 1; }
				if (active >= rows.length) { active = 0; }
				mark();
				if (rows[active]) { rows[active].scrollIntoView({ block: 'nearest' }); }
			} else if (e.key === 'Enter') {
				if (!box.hidden && rows[active]) {
					e.preventDefault();
					rows[active].dispatchEvent(new MouseEvent('mousedown'));
				}
			} else if (e.key === 'Escape') {
				close();
			}
		});
		input.addEventListener('blur', function () {
			setTimeout(function () {
				if (!input.dataset.picked) { setValue('', ''); }
				close();
			}, 120);
		});

		api.setItems = function (next) {
			items = next || [];
			setValue('', '');
		};
		api.setValue = function (code) {
			var want = normalize(code);
			var found = null;
			for (var i = 0; i < items.length; i++) {
				if (items[i].code === code || normalize(items[i].name) === want) { found = items[i]; break; }
			}
			if (found) { setValue(found.code, found.name); }
			else { setValue('', ''); }
		};
		input.zmcPick = api;
		if (opts.value) { api.setValue(opts.value); }
		return api;
	};
})(window);
