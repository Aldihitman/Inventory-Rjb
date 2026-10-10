/**
 * Gudang RJB — interaksi sisi klien
 *  1. initCombo()  : dropdown pencarian barang (dipakai di Masuk, Keluar, Rakit, Surat Jalan)
 *  2. Surat Jalan  : baris barang dinamis + pilihan kondisi (sudah rakit / belum rakit) + cek stok
 *  3. Filter tabel : kotak pencarian untuk Daftar Barang & Surat Jalan tersimpan
 */

function escHtml(s) {
  return String(s === undefined || s === null ? '' : s).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}

/* ------------------------------------------------------------------
 * 1. Dropdown pencarian (combobox)
 * Markup:
 * <div class="combo" data-combo data-items='[...json...]' [data-optional]>
 *   <input type="text" data-combo-input>
 *   <input type="hidden" name="item_id" data-combo-value>
 *   <div class="combo-list" data-combo-list></div>
 * </div>
 * Event: 'combo:select' (detail = item) dan 'combo:clear' dikirim ke elemen .combo.
 * data-optional = tidak wajib dipilih saat submit (dipakai untuk baris surat jalan).
 * ------------------------------------------------------------------ */
function initCombo(wrap) {
  if (wrap._comboReady) return;
  wrap._comboReady = true;

  var input = wrap.querySelector('[data-combo-input]');
  var hidden = wrap.querySelector('[data-combo-value]');
  var list = wrap.querySelector('[data-combo-list]');
  if (!input || !hidden || !list) return;

  var items = [];
  try { items = JSON.parse(wrap.getAttribute('data-items') || '[]'); } catch (e) { items = []; }

  var filtered = [];
  var activeIndex = -1;

  function labelFor(item) { return item.kode + ' — ' + item.nama; }

  function search(q) {
    q = (q || '').trim().toLowerCase();
    if (!q) return items;
    return items.filter(function (i) {
      return (i.kode + ' ' + i.nama).toLowerCase().indexOf(q) !== -1;
    });
  }

  function render(arr) {
    filtered = arr;
    activeIndex = -1;
    if (filtered.length === 0) {
      list.innerHTML = '<div class="combo-empty">Barang tidak ditemukan.</div>';
    } else {
      list.innerHTML = filtered.map(function (item, idx) {
        var hasStok = item.stok !== undefined && item.stok !== null;
        var hasBelumRakit = item.stok_belum_rakit !== undefined && item.stok_belum_rakit !== null;
        var low = hasStok && item.stok_min && Number(item.stok) <= Number(item.stok_min);
        var stokText = '';
        if (hasBelumRakit) {
          stokText = 'Belum rakit: ' + item.stok_belum_rakit + ' · Siap kirim: ' + item.stok + ' ' + escHtml(item.satuan);
        } else if (hasStok) {
          stokText = 'Stok: ' + item.stok + ' ' + escHtml(item.satuan);
        }
        return '<div class="combo-option" data-idx="' + idx + '">' +
          '<span><span class="kode">' + escHtml(item.kode) + '</span>' + escHtml(item.nama) + '</span>' +
          '<span class="stok' + (low ? ' low-stock' : '') + '">' + stokText + '</span>' +
        '</div>';
      }).join('');
    }
    list.classList.add('open');
  }

  function close() { list.classList.remove('open'); }

  function selectItem(item) {
    hidden.value = item.id;
    input.value = labelFor(item);
    wrap.classList.remove('invalid');
    close();
    wrap.dispatchEvent(new CustomEvent('combo:select', { detail: item }));
  }

  function updateActive(opts) {
    opts.forEach(function (o, i) { o.classList.toggle('active', i === activeIndex); });
    if (opts[activeIndex]) opts[activeIndex].scrollIntoView({ block: 'nearest' });
  }

  input.addEventListener('focus', function () { render(search(input.value)); });

  input.addEventListener('input', function () {
    hidden.value = '';
    wrap.dispatchEvent(new CustomEvent('combo:clear'));
    render(search(input.value));
  });

  input.addEventListener('keydown', function (e) {
    if (!list.classList.contains('open')) return;
    var opts = list.querySelectorAll('.combo-option');
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      activeIndex = Math.min(activeIndex + 1, opts.length - 1);
      updateActive(opts);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      activeIndex = Math.max(activeIndex - 1, 0);
      updateActive(opts);
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (activeIndex >= 0 && filtered[activeIndex]) selectItem(filtered[activeIndex]);
      else if (filtered.length === 1) selectItem(filtered[0]);
    } else if (e.key === 'Escape') {
      close();
    }
  });

  list.addEventListener('click', function (e) {
    var opt = e.target.closest('.combo-option');
    if (!opt) return;
    var idx = parseInt(opt.getAttribute('data-idx'), 10);
    if (filtered[idx]) selectItem(filtered[idx]);
  });

  document.addEventListener('click', function (e) {
    if (!wrap.contains(e.target)) close();
  });

  if (!wrap.hasAttribute('data-optional')) {
    var form = wrap.closest('form');
    if (form) {
      form.addEventListener('submit', function (e) {
        if (!hidden.value) {
          e.preventDefault();
          wrap.classList.add('invalid');
          input.focus();
        }
      });
    }
  }
}

window.initCombo = initCombo;
document.querySelectorAll('[data-combo]').forEach(initCombo);

/* ------------------------------------------------------------------
 * 2. Surat Jalan: baris barang dinamis
 * window.RJB_ITEMS diisi oleh surat_jalan.php sebelum file ini dimuat.
 * Tiap baris: barang (pencarian) + kondisi + jumlah + satuan + keterangan.
 * ------------------------------------------------------------------ */
(function () {
  var rowsWrap = document.getElementById('sj-rows');
  var addBtn = document.getElementById('sj-add-row');
  var form = document.getElementById('form-sj');
  if (!rowsWrap || !addBtn) return; // bukan halaman surat jalan

  var items = window.RJB_ITEMS || [];
  var itemsById = {};
  items.forEach(function (i) { itemsById[i.id] = i; });
  var itemsJson = escHtml(JSON.stringify(items));

  var LABEL = { rakit: 'sudah rakit', belum_rakit: 'belum rakit (koli)' };

  function stockOf(item, kondisi) {
    return kondisi === 'belum_rakit' ? Number(item.stok_belum_rakit) : Number(item.stok);
  }

  function addRow() {
    var row = document.createElement('div');
    row.className = 'sj-row';
    row.innerHTML =
      '<div class="combo" data-combo data-optional data-items="' + itemsJson + '">' +
        '<input type="text" class="combo-input" data-combo-input placeholder="Cari kode / nama barang..." autocomplete="off">' +
        '<input type="hidden" name="barang_item[]" data-combo-value>' +
        '<div class="combo-list" data-combo-list></div>' +
      '</div>' +
      '<select name="barang_kondisi[]" class="sj-kondisi">' +
        '<option value="rakit">Sudah rakit</option>' +
        '<option value="belum_rakit">Belum rakit (koli)</option>' +
      '</select>' +
      '<input type="number" name="barang_qty[]" class="sj-qty" min="1" placeholder="Jumlah">' +
      '<input type="text" class="sj-satuan" placeholder="Satuan" readonly>' +
      '<input type="text" name="barang_ket[]" class="sj-ket" placeholder="Keterangan (opsional)">' +
      '<button type="button" class="icon-btn sj-remove">✕</button>' +
      '<div class="sj-row-warn"></div>';
    rowsWrap.appendChild(row);

    var combo = row.querySelector('.combo');
    var kondisiSel = row.querySelector('.sj-kondisi');
    var qty = row.querySelector('.sj-qty');
    var satuan = row.querySelector('.sj-satuan');
    var warn = row.querySelector('.sj-row-warn');
    var hidden = row.querySelector('[data-combo-value]');
    initCombo(combo);

    function currentItem() { return itemsById[hidden.value] || null; }

    function refresh() {
      var item = currentItem();
      if (!item) {
        satuan.value = '';
        qty.placeholder = 'Jumlah';
        warn.textContent = ''; warn.classList.remove('show');
        return;
      }
      var kondisi = kondisiSel.value;
      var avail = stockOf(item, kondisi);
      satuan.value = item.satuan;
      qty.placeholder = 'maks ' + avail;
      var q = parseInt(qty.value, 10) || 0;
      if (q > avail) {
        warn.textContent = 'Melebihi stok ' + LABEL[kondisi] + '! Tersedia hanya ' + avail + ' ' + item.satuan + '.';
        warn.classList.add('show');
      } else {
        warn.textContent = ''; warn.classList.remove('show');
      }
    }

    combo.addEventListener('combo:select', refresh);
    combo.addEventListener('combo:clear', refresh);
    kondisiSel.addEventListener('change', refresh);
    qty.addEventListener('input', refresh);
    row.querySelector('.sj-remove').addEventListener('click', function () { row.remove(); });
  }

  addBtn.addEventListener('click', addRow);
  addRow(); // mulai dengan satu baris kosong

  // Validasi akhir sebelum submit. Server tetap penentu akhir.
  if (form) {
    form.addEventListener('submit', function (e) {
      var needed = {};
      var problems = [];
      rowsWrap.querySelectorAll('.sj-row:not(.head)').forEach(function (row) {
        var id = row.querySelector('[data-combo-value]').value;
        var kondisi = row.querySelector('.sj-kondisi').value;
        var q = parseInt(row.querySelector('.sj-qty').value, 10) || 0;
        if (q > 0 && !id) {
          problems.push('Ada baris dengan jumlah terisi tapi barangnya belum dipilih dari daftar.');
        } else if (q > 0 && id) {
          var key = id + '|' + kondisi;
          needed[key] = (needed[key] || 0) + q;
        }
      });

      Object.keys(needed).forEach(function (key) {
        var parts = key.split('|');
        var item = itemsById[parts[0]];
        var avail = item ? stockOf(item, parts[1]) : 0;
        if (item && needed[key] > avail) {
          problems.push(item.nama + ' (' + LABEL[parts[1]] + '): diminta ' + needed[key] + ' ' + item.satuan +
            ', stok tersedia hanya ' + avail + ' ' + item.satuan + '.');
        }
      });

      if (problems.length > 0) {
        e.preventDefault();
        alert('Surat jalan tidak bisa disimpan:\n\n' + problems.join('\n'));
      }
    });
  }
})();

/* ------------------------------------------------------------------
 * 3. Filter tabel/daftar (kotak pencarian)
 * <input data-filter-rows="CSS selector baris" data-filter-empty="#id elemen 'tidak ada hasil'">
 * ------------------------------------------------------------------ */
document.querySelectorAll('input[data-filter-rows]').forEach(function (input) {
  var rowSelector = input.getAttribute('data-filter-rows');
  var emptySel = input.getAttribute('data-filter-empty');

  input.addEventListener('input', function () {
    var q = input.value.trim().toLowerCase();
    var rows = document.querySelectorAll(rowSelector);
    var visible = 0;
    rows.forEach(function (row) {
      var match = !q || row.textContent.toLowerCase().indexOf(q) !== -1;
      row.style.display = match ? '' : 'none';
      if (match) visible++;
    });
    var empty = emptySel ? document.querySelector(emptySel) : null;
    if (empty) empty.style.display = (rows.length > 0 && visible === 0) ? '' : 'none';
  });
});
