/* Shared UI behaviour */
(function () {
  // Toasts: close button + auto dismiss
  var box = document.getElementById('toasts');
  if (box) {
    box.addEventListener('click', function (e) {
      if (e.target.classList.contains('x')) e.target.parentNode.remove();
    });
    Array.prototype.forEach.call(box.querySelectorAll('.toast'), function (t) {
      setTimeout(function () { t.classList.add('out'); setTimeout(function () { t.remove(); }, 400); }, 6500);
    });
  }

  // Show / hide password on every password field
  Array.prototype.forEach.call(document.querySelectorAll('input[type=password]'), function (input) {
    var wrap = document.createElement('div');
    wrap.className = 'pw-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pw-toggle';
    btn.textContent = 'Show';
    btn.setAttribute('aria-label', 'Show password');
    btn.addEventListener('click', function () {
      var hidden = input.type === 'password';
      input.type = hidden ? 'text' : 'password';
      btn.textContent = hidden ? 'Hide' : 'Show';
      btn.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password');
    });
    wrap.appendChild(btn);
  });

  // Mobile menu
  var burger = document.getElementById('burger');
  if (burger) {
    burger.addEventListener('click', function () {
      document.body.classList.toggle('menu-open');
    });
  }

  // Sticky navbar shadow
  var nav = document.getElementById('siteNav');
  if (nav) {
    var f = function () { nav.classList.toggle('scrolled', window.scrollY > 8); };
    window.addEventListener('scroll', f, { passive: true }); f();
  }

  // Confirm dialogs on forms: <form data-confirm="Are you sure?">
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  // Invoice builder (admin billing page)
  var items = document.getElementById('invItems');
  if (items) {
    var tpl = document.getElementById('invRowTpl');
    var addBtn = document.getElementById('addRow');
    var totalEl = document.getElementById('invTotal');
    var discEl = document.querySelector('[name=discount]');
    var peso = function (n) { return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

    var recalc = function () {
      var sum = 0;
      Array.prototype.forEach.call(items.querySelectorAll('.inv-row'), function (r) {
        var q = parseFloat(r.querySelector('[name="qty[]"]').value) || 0;
        var p = parseFloat(r.querySelector('[name="price[]"]').value) || 0;
        sum += q * p;
      });
      var d = parseFloat(discEl && discEl.value) || 0;
      totalEl.textContent = peso(Math.max(0, sum - d));
    };
    var addRow = function (desc, price) {
      var node = tpl.content.firstElementChild.cloneNode(true);
      if (desc) node.querySelector('[name="desc[]"]').value = desc;
      if (price) node.querySelector('[name="price[]"]').value = price;
      items.appendChild(node);
      recalc();
    };
    addBtn.addEventListener('click', function () { addRow(); });
    items.addEventListener('input', recalc);
    items.addEventListener('click', function (e) {
      if (e.target.classList.contains('rm')) {
        var rows = items.querySelectorAll('.inv-row');
        if (rows.length > 1) { e.target.closest('.inv-row').remove(); recalc(); }
      }
    });
    if (discEl) discEl.addEventListener('input', recalc);

    var apptSel = document.getElementById('apptSel');
    var ownerSel = document.getElementById('ownerSel');
    if (apptSel) {
      apptSel.addEventListener('change', function () {
        var o = apptSel.options[apptSel.selectedIndex];
        if (!o || !o.value) return;
        if (ownerSel && o.dataset.owner) ownerSel.value = o.dataset.owner;
        var first = items.querySelector('.inv-row');
        if (first) {
          first.querySelector('[name="desc[]"]').value = o.dataset.service || '';
          first.querySelector('[name="price[]"]').value = o.dataset.price || '';
        }
        recalc();
      });
    }
    if (!items.querySelector('.inv-row')) addRow();
    if (apptSel && apptSel.value) apptSel.dispatchEvent(new Event('change'));  // pre-selected appointment
    recalc();
  }
})();