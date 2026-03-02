(function () {
  if (typeof window.ulsFrontend === 'undefined') {
    return;
  }

  var root = document.getElementById('uls-widget-root');
  if (!root) {
    return;
  }

  var toggle = root.querySelector('.uls-widget__toggle');
  var modal = root.querySelector('.uls-widget__modal');
  var closeBtn = root.querySelector('.uls-widget__close');
  var searchInput = root.querySelector('#uls-user-search');
  var status = root.querySelector('[data-uls-status]');
  var list = root.querySelector('[data-uls-users]');
  var requestTimer = null;

  function normalizeUrl(url) {
    if (!url) {
      return '';
    }

    return String(url).replace(/&amp;/g, '&');
  }

  function setOpen(open) {
    modal.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open && searchInput) {
      searchInput.focus();
    }
  }

  function setStatus(text) {
    if (!status) {
      return;
    }
    status.textContent = text;
  }

  function renderUsers(users) {
    if (!list) {
      return;
    }

    list.innerHTML = '';

    if (!users.length) {
      setStatus(window.ulsFrontend.empty_label);
      return;
    }

    setStatus('');

    users.forEach(function (user) {
      var li = document.createElement('li');
      li.className = 'uls-widget__list-item';

      var link = document.createElement('a');
      link.className = 'uls-widget__list-link';
      link.href = normalizeUrl(user.switch_url);

      var name = document.createElement('span');
      name.className = 'uls-widget__name';
      name.textContent = user.name;

      var meta = document.createElement('span');
      meta.className = 'uls-widget__meta';

      var metaParts = [
        user.username ? '@' + user.username : '',
        user.email || '',
        user.role || ''
      ].filter(Boolean);

      meta.textContent = metaParts.join(' | ');

      link.appendChild(name);
      link.appendChild(meta);
      li.appendChild(link);

      list.appendChild(li);
    });
  }

  function loadUsers(search) {
    if (!window.ulsFrontend.can_search) {
      return;
    }

    setStatus(window.ulsFrontend.loading_label);

    var body = new URLSearchParams({
      action: 'uls_search_users',
      nonce: window.ulsFrontend.nonce,
      search: search || ''
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
        return response.text();
      })
      .then(function (raw) {
        var json;
        try {
          json = JSON.parse(raw);
        } catch (e) {
          setStatus(window.ulsFrontend.error_label || window.ulsFrontend.empty_label);
          return;
        }

        if (!json || !json.success || !json.data || !Array.isArray(json.data.users)) {
          if (json && json.data && json.data.message) {
            setStatus(json.data.message);
            return;
          }
          setStatus(window.ulsFrontend.error_label || window.ulsFrontend.empty_label);
          return;
        }

        renderUsers(json.data.users);
      })
      .catch(function () {
        setStatus(window.ulsFrontend.empty_label);
      });
  }

  toggle.addEventListener('click', function () {
    var open = toggle.getAttribute('aria-expanded') !== 'true';
    setOpen(open);

    if (open && window.ulsFrontend.can_search) {
      loadUsers('');
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

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      if (requestTimer) {
        clearTimeout(requestTimer);
      }

      requestTimer = setTimeout(function () {
        loadUsers(searchInput.value.trim());
      }, 220);
    });
  }
})();
