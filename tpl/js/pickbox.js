/**
 * 검색형 선택 상자 — 국가·행정구역처럼 항목이 많은 목록에 쓴다.
 *
 * 보이는 것은 이름이고 실제로 담기는 것은 코드다. 목록에서 고르지 않고
 * 글자만 친 경우에는 값이 비워진다. 오타가 값으로 남으면 관리자가 정한
 * 규칙과 조용히 어긋나기 때문이다.
 *
 * 쓰는 법:
 *   zmcPickBox(input, {
 *     items: [{code, name, keywords}],
 *     value: 'KR-49',
 *     empty: '일치하는 항목이 없습니다',
 *     onPick: function (code) {}
 *   })
 *
 * input 옆에 같은 이름의 hidden 이 있어야 하며, 코드는 그쪽에 담긴다.
 */
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
			// 고쳐 쓰기 시작하면 고른 값은 풀린다
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
				// 고르지 않고 떠나면 글자를 지운다. 오타가 값으로 남지 않게 한다
				if (!input.dataset.picked) { setValue('', ''); }
				close();
			}, 120);
		});

		api.setItems = function (next) {
			items = next || [];
			setValue('', '');
		};
		api.setValue = function (code) {
			// 예전에 저장된 배송지는 코드가 아니라 이름으로 담겨 있다. 이름으로도 찾아 준다
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
