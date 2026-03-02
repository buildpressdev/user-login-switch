(function () {
	if (typeof window.ulsFrontend === 'undefined') {
		return;
	}

	var root = document.getElementById('uls-widget-root');
	if (!root) {
		return;
	}

	var toggle = root.querySelector('.uls-widget__toggle');
	var panel = root.querySelector('.uls-widget__panel');
	var closeBtn = root.querySelector('.uls-widget__close');
	var searchWrap = root.querySelector('.uls-widget__search-wrap');
	var searchInput = root.querySelector('#uls-user-search');
	var status = root.querySelector('[data-uls-status]');
	var list = root.querySelector('[data-uls-users]');

	var users = [];
	var loaded = false;
	var hideTimer = null;

	if (!toggle || !panel || !closeBtn || !status || !list) {
		return;
	}

	function withRedirect(url) {
		var cleanUrl = String(url || '').replace(/&amp;/g, '&');
		var separator = cleanUrl.indexOf('?') === -1 ? '?' : '&';
		return cleanUrl + separator + 'redirect_to=' + encodeURIComponent(window.location.href);
	}

	function setStatus(text) {
		status.textContent = text || '';
	}

	function setSearchVisible(visible) {
		if (!searchWrap) {
			return;
		}

		searchWrap.hidden = !visible;
		if (!visible && searchInput) {
			searchInput.value = '';
		}
	}

	function setOpen(open) {
		toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

		if (hideTimer) {
			window.clearTimeout(hideTimer);
			hideTimer = null;
		}

		if (open) {
			panel.hidden = false;
			window.requestAnimationFrame(function () {
				panel.classList.add('is-open');
			});
			return;
		}

		panel.classList.remove('is-open');
		hideTimer = window.setTimeout(function () {
			panel.hidden = true;
		}, 180);
	}

	function renderList(items) {
		list.innerHTML = '';

		if (!items.length) {
			setStatus(window.ulsFrontend.empty_label);
			return;
		}

		setStatus('');

		items.forEach(function (user) {
			var li = document.createElement('li');

			var link = document.createElement('a');
			link.className = 'uls-widget__item-link';
			link.href = withRedirect(user.switch_url);

			var name = document.createElement('span');
			name.className = 'uls-widget__item-name';
			name.textContent = user.name;

			var meta = document.createElement('span');
			meta.className = 'uls-widget__item-meta';
			meta.textContent = [user.username ? '@' + user.username : '', user.role || ''].filter(Boolean).join(' | ');

			link.appendChild(name);
			link.appendChild(meta);
			li.appendChild(link);
			list.appendChild(li);
		});
	}

	function filterList() {
		if (!searchInput || searchWrap.hidden) {
			renderList(users);
			return;
		}

		var term = searchInput.value.trim().toLowerCase();

		if (!term) {
			renderList(users);
			return;
		}

		var filtered = users.filter(function (user) {
			var line = [user.name, user.username, user.email, user.role].join(' ').toLowerCase();
			return line.indexOf(term) !== -1;
		});

		renderList(filtered);
	}

	function fetchUsers() {
		if (loaded) {
			filterList();
			return;
		}

		if (!window.ulsFrontend.can_search) {
			setStatus(window.ulsFrontend.no_access_text || window.ulsFrontend.empty_label);
			setSearchVisible(false);
			return;
		}

		setStatus(window.ulsFrontend.loading_label);

		var body = new URLSearchParams({
			action: 'uls_search_users',
			nonce: window.ulsFrontend.nonce
		});

		window.fetch(window.ulsFrontend.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With': 'XMLHttpRequest'
			},
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (json) {
				if (!json || !json.success || !json.data || !Array.isArray(json.data.users)) {
					setStatus(window.ulsFrontend.error_label);
					return;
				}

				users = json.data.users;
				loaded = true;
				setSearchVisible(users.length > Number(window.ulsFrontend.search_limit || 7));
				renderList(users);
			})
			.catch(function () {
				setStatus(window.ulsFrontend.error_label);
			});
	}

	toggle.addEventListener('click', function () {
		var open = toggle.getAttribute('aria-expanded') !== 'true';
		setOpen(open);

		if (open) {
			fetchUsers();
		}
	});

	closeBtn.addEventListener('click', function () {
		setOpen(false);
	});

	document.addEventListener('click', function (event) {
		if (!root.contains(event.target)) {
			setOpen(false);
		}
	});

	document.addEventListener('keydown', function (event) {
		if ('Escape' === event.key) {
			setOpen(false);
		}
	});

	if (searchInput) {
		searchInput.addEventListener('input', filterList);
	}
})();
