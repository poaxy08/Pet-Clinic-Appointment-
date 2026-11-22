/* Live slot picker: shows availability, reserves a slot for the first person to pick it,
   and marks it "Not available" for everyone else. */
(function () {
  var form = document.getElementById('bookForm');
  if (!form) return;

  var vetCards = form.querySelectorAll('.vet-card');
  function vetVal() {
    var c = form.querySelector('[name=vet_id]:checked');
    return c ? c.value : '';
  }
  var dateIn = form.querySelector('[name=date]');
  var timeIn = document.getElementById('timeInput');
  var grid = document.getElementById('slotGrid');
  var hint = document.getElementById('slotHint');
  var submit = document.getElementById('bookSubmit');

  var selected = null;     // time string "HH:MM" that this user holds
  var expiresAt = 0;       // ms timestamp when the hold ends
  var busy = false;
  var NA = 'Not available — this date and time was already taken by another client. Please choose a different time.';

  function toast(msg, type) {
    var box = document.getElementById('toasts');
    if (!box) return;
    var t = document.createElement('div');
    t.className = 'toast t-' + (type || 'error');
    t.textContent = msg;
    var x = document.createElement('button');
    x.type = 'button'; x.className = 'x'; x.textContent = '×';
    t.appendChild(x);
    box.appendChild(t);
    setTimeout(function () { t.remove(); }, 6000);
  }

  function post(data) {
    var body = new URLSearchParams(data).toString();
    return fetch(window.APP.base + '/api/hold.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': window.APP.csrf },
      body: body
    }).then(function (r) { return r.json(); });
  }

  function setSelected(time, secs) {
    selected = time;
    timeIn.value = time || '';
    submit.disabled = !time;
    expiresAt = time && secs ? Date.now() + secs * 1000 : 0;
    tick();
  }

  function tick() {
    if (!selected) { hint.textContent = ''; hint.className = 'hint'; return; }
    var left = Math.max(0, Math.round((expiresAt - Date.now()) / 1000));
    if (left <= 0) {
      setSelected(null);
      toast('Your reserved time expired. Please choose a time again.', 'error');
      load();
      return;
    }
    var m = Math.floor(left / 60), s = left % 60;
    hint.className = 'hint ok';
    hint.textContent = 'Reserved for you — ' + m + ':' + (s < 10 ? '0' : '') + s + ' left to confirm.';
  }
  setInterval(tick, 1000);

  function render(slots) {
    grid.innerHTML = '';
    slots.forEach(function (s) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'slot ' + s.state;
      b.dataset.time = s.time;
      var small = 'Available';
      if (s.state === 'booked' || s.state === 'held') { b.disabled = true; small = 'Not available'; b.title = 'Another client already chose this time'; }
      else if (s.state === 'past') { b.disabled = true; small = 'Passed'; }
      else if (s.state === 'mine') { small = 'Your choice'; b.classList.add('selected'); }
      b.innerHTML = '<span>' + s.label + '</span><small>' + small + '</small>';
      if (!b.disabled) b.addEventListener('click', function () { choose(s.time); });
      grid.appendChild(b);
    });
  }

  function load() {
    var vet = vetVal(), date = dateIn.value;
    if (!vet || !date) {
      grid.innerHTML = '<p class="muted">Select a veterinarian and a date to see open times.</p>';
      return Promise.resolve();
    }
    var url = window.APP.base + '/api/slots.php?vet_id=' + encodeURIComponent(vet) + '&date=' + encodeURIComponent(date);
    return fetch(url, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok || !d.slots.length) {
          grid.innerHTML = '<p class="muted">' + (d.message || 'No time slots for this day.') + '</p>';
          setSelected(null);
          return;
        }
        var mine = d.slots.filter(function (s) { return s.state === 'mine'; })[0];
        if (mine) {
          if (selected !== mine.time) setSelected(mine.time, d.hold_expires_in);
          else if (d.hold_expires_in) expiresAt = Date.now() + d.hold_expires_in * 1000;
        } else if (selected) {
          // We thought we had a slot but we no longer do.
          setSelected(null);
          toast('That time is no longer reserved for you.', 'error');
        }
        render(d.slots);
      })
      .catch(function () { /* ignore transient network errors; next poll retries */ });
  }

  function choose(time) {
    if (busy) return;
    busy = true;
    post({ action: 'hold', vet_id: vetVal(), date: dateIn.value, time: time })
      .then(function (d) {
        if (d.ok) { setSelected(time, d.expires_in); }
        else { toast(d.message || NA, 'error'); }
        return load();
      })
      .catch(function () { toast('Network problem. Please try again.', 'error'); })
      .then(function () { busy = false; });
  }

  function changed() {
    var had = selected;
    setSelected(null);
    var p = had ? post({ action: 'release' }) : Promise.resolve();
    p.then(load);
  }

  // Only list veterinarians who perform the chosen service.
  function filterVets() {
    var svc = form.querySelector('[name=service_id]:checked');
    var sid = svc ? svc.value : '';
    var vets = (window.BOOK && window.BOOK.vets) || {};
    var reset = false, shown = 0;
    Array.prototype.forEach.call(vetCards, function (card) {
      var id = card.getAttribute('data-vet');
      var radio = card.querySelector('input');
      var list = (vets[id] && vets[id].services) || [];
      var ok = !sid || !list.length || list.indexOf(sid) !== -1;
      card.hidden = !ok;
      radio.disabled = !ok;
      if (ok) shown++;
      if (!ok && radio.checked) { radio.checked = false; reset = true; }
    });
    var none = document.getElementById('vetNone');
    if (none) none.hidden = shown > 0;
    if (reset) changed();
  }
  Array.prototype.forEach.call(form.querySelectorAll('[name=service_id]'), function (r) {
    r.addEventListener('change', filterVets);
  });

  Array.prototype.forEach.call(vetCards, function (card) {
    card.querySelector('input').addEventListener('change', changed);
  });
  dateIn.addEventListener('change', changed);

  // Refresh availability every 5 seconds so "Not available" appears quickly.
  setInterval(function () { if (!busy && !document.hidden) load(); }, 5000);
  filterVets();
  load();
})();