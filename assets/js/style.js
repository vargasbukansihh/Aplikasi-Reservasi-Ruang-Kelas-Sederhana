(function () {
  'use strict';

  var tbody = document.getElementById('tabel-pengguna');
  var kosong = document.getElementById('keadaan-kosong');
  var teksKosong = document.getElementById('teks-kosong');
  var area = document.getElementById('area-notifikasi');

  var formCari = document.getElementById('form-cari');
  var inputCari = document.getElementById('cari-kata');
  var selectCariPeran = document.getElementById('cari-peran');
  var pagination = document.getElementById('pagination-pengguna');
  var infoHalaman = document.getElementById('info-halaman');
  var pageSize = 5;
  var currentPage = 1;

  var modalPengguna = document.getElementById('modal-pengguna');
  var formPengguna = document.getElementById('form-pengguna');
  var judulModal = document.getElementById('judul-modal-pengguna');
  var btnSimpan = document.getElementById('btn-simpan');
  var fieldId = document.getElementById('field-id');
  var fields = {
    nama: document.getElementById('field-nama'),
    nim: document.getElementById('field-nim'),
    username: document.getElementById('field-username'),
    kelas: document.getElementById('field-kelas'),
    role: document.getElementById('field-role'),
    password: document.getElementById('field-password')
  };

  var modalHapus = document.getElementById('modal-hapus');
  var formHapus = document.getElementById('form-hapus');
  var hapusId = document.getElementById('hapus-id');
  var hapusRingkasan = document.getElementById('hapus-ringkasan');

  /* ---------- Notifikasi ---------- */
  function notifikasi(tipe, pesan) {
    var ikon = { success: 'fa-circle-check', info: 'fa-circle-info', danger: 'fa-circle-exclamation' }[tipe];
    area.textContent = '';
    var el = document.createElement('div');
    el.className = 'alert alert-' + tipe + ' alert-feedback alert-dismissible';
    el.setAttribute('role', 'status');
    var i = document.createElement('i');
    i.className = 'fa-solid ' + ikon + ' me-2';
    i.setAttribute('aria-hidden', 'true');
    el.appendChild(i);
    el.appendChild(document.createTextNode(pesan));
    var tutup = document.createElement('button');
    tutup.type = 'button';
    tutup.className = 'btn-close';
    tutup.setAttribute('data-alert-dismiss', '');
    tutup.setAttribute('aria-label', 'Tutup notifikasi');
    el.appendChild(tutup);
    area.appendChild(el);
  }

  /* ---------- Pencarian dan filter (sisi klien, pada baris yang tampil) ---------- */
  function baris() {
    return Array.prototype.slice.call(tbody.querySelectorAll('tr'));
  }

  function renderPagination(pageCount) {
    pagination.textContent = '';
    if (pageCount <= 1) {
      return;
    }

    function addItem(page, label, disabled, active) {
      var item = document.createElement('li');
      var control = document.createElement(disabled || active ? 'span' : 'a');
      item.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
      control.className = 'page-link';
      control.textContent = label;

      if (active) {
        item.setAttribute('aria-current', 'page');
      } else if (disabled) {
        control.setAttribute('aria-disabled', 'true');
        control.setAttribute('aria-label', label === '‹' ? 'Halaman sebelumnya' : 'Halaman berikutnya');
      } else {
        control.href = '#';
        control.dataset.page = String(page);
        if (label === '‹' || label === '›') {
          control.setAttribute('aria-label', label === '‹' ? 'Halaman sebelumnya' : 'Halaman berikutnya');
        }
      }

      item.appendChild(control);
      pagination.appendChild(item);
    }

    addItem(currentPage - 1, '‹', currentPage === 1, false);
    for (var page = 1; page <= pageCount; page++) {
      addItem(page, String(page), false, page === currentPage);
    }
    addItem(currentPage + 1, '›', currentPage === pageCount, false);
  }

  function terapkanFilter(umpanBalik, resetPage) {
    if (resetPage) {
      currentPage = 1;
    }
    var kata = inputCari.value.trim().toLowerCase();
    var peran = selectCariPeran.value;
    var semua = baris();
    var cocok = [];

    semua.forEach(function (tr) {
      var sel = tr.cells;
      var nama = sel[1].textContent.toLowerCase();
      var nim = sel[2].textContent.toLowerCase();
      var username = sel[3].textContent.toLowerCase();
      var cocokKata = !kata || nama.indexOf(kata) !== -1 ||
        nim.indexOf(kata) !== -1 || username.indexOf(kata) !== -1;
      var cocokPeran = !peran || tr.dataset.role === peran;
      if (cocokKata && cocokPeran) {
        cocok.push(tr);
      }
    });

    var pageCount = Math.ceil(cocok.length / pageSize);
    if (currentPage > pageCount) {
      currentPage = Math.max(1, pageCount);
    }
    var startIndex = (currentPage - 1) * pageSize;
    var visibleRows = cocok.slice(startIndex, startIndex + pageSize);
    semua.forEach(function (tr) { tr.hidden = visibleRows.indexOf(tr) === -1; });

    kosong.hidden = cocok.length > 0;
    if (cocok.length === 0) {
      teksKosong.textContent = semua.length === 0
        ? 'Belum ada data pengguna.'
        : 'Tidak ada pengguna yang cocok dengan pencarian.';
    }

    if (umpanBalik && semua.length > 0) {
      notifikasi(cocok.length > 0 ? 'info' : 'danger',
        cocok.length > 0 ? cocok.length + ' pengguna ditemukan.' : 'Tidak ada pengguna yang cocok dengan pencarian.');
    }

    infoHalaman.textContent = cocok.length
      ? 'Menampilkan ' + (startIndex + 1) + '–' + Math.min(startIndex + pageSize, cocok.length) +
        ' dari ' + cocok.length + ' pengguna'
      : 'Tidak ada data pengguna untuk ditampilkan.';
    renderPagination(pageCount);
  }

  formCari.addEventListener('submit', function (e) {
    e.preventDefault();
    terapkanFilter(true, true);
  });
  inputCari.addEventListener('input', function () { terapkanFilter(false, true); });
  selectCariPeran.addEventListener('change', function () { terapkanFilter(true, true); });

  pagination.addEventListener('click', function (event) {
    var link = event.target.closest('a[data-page]');
    if (!link) {
      return;
    }
    event.preventDefault();
    currentPage = Number(link.dataset.page);
    terapkanFilter(false, false);
  });

  /* ---------- Validasi form ---------- */
  function setPesan(field, pesan) {
    var el = document.getElementById(field.getAttribute('aria-describedby'));
    if (el) { el.textContent = pesan; }
  }

  function bersihkanValidasi() {
    Object.keys(fields).forEach(function (k) { fields[k].classList.remove('is-invalid'); });
  }

  function sesuaikanNim() {
    fields.nim.required = fields.role.value === 'mahasiswa';
  }

  function validasi() {
    bersihkanValidasi();
    var pertama = null;

    function tandai(field, pesan) {
      field.classList.add('is-invalid');
      if (pesan) { setPesan(field, pesan); }
      pertama = pertama || field;
    }

    fields.nama.value = fields.nama.value.trim();
    if (!fields.nama.value) { tandai(fields.nama, 'Nama lengkap wajib diisi.'); }

    fields.nim.value = fields.nim.value.trim();
    if (fields.role.value === 'mahasiswa' && !fields.nim.value) {
      tandai(fields.nim, 'NIM wajib diisi untuk mahasiswa.');
    }

    fields.username.value = fields.username.value.trim();
    if (!fields.username.value) { tandai(fields.username, 'Username wajib diisi.'); }

    fields.kelas.value = fields.kelas.value.trim();
    if (fields.kelas.value.length > 20) { tandai(fields.kelas, 'Kelas maksimal 20 karakter.'); }

    if (!fields.role.value) { tandai(fields.role, 'Peran wajib dipilih.'); }

    if (!fieldId.value && !fields.password.value) {
      tandai(fields.password, 'Password wajib diisi untuk akun baru.');
    } else if (
      fields.password.value
      && (fields.password.value.length < 8 || fields.password.value.length > 72)
    ) {
      tandai(fields.password, 'Password minimal 8 karakter dan maksimal 72 byte.');
    }

    if (pertama) { pertama.focus(); return false; }
    return true;
  }

  formPengguna.addEventListener('submit', function (e) {
    if (!validasi()) { e.preventDefault(); }
  });

  Object.keys(fields).forEach(function (k) {
    fields[k].addEventListener('input', function () { fields[k].classList.remove('is-invalid'); });
    fields[k].addEventListener('change', function () { fields[k].classList.remove('is-invalid'); });
  });

  /* ---------- Modal tambah / ubah / hapus ---------- */
  function modeTambah() {
    judulModal.textContent = 'Tambah Pengguna';
    btnSimpan.textContent = 'Simpan';
    formPengguna.reset();
    formPengguna.action = '../db/tambah_pengguna.php';
    fieldId.value = '';
    fields.password.required = true;
    sesuaikanNim();
    bersihkanValidasi();
  }

  function modeUbah(data) {
    judulModal.textContent = 'Ubah Pengguna';
    btnSimpan.textContent = 'Simpan Perubahan';
    formPengguna.action = '../db/edit_pengguna.php';
    bersihkanValidasi();
    fieldId.value = data.id || '';
    fields.nama.value = data.nama || '';
    fields.nim.value = data.nim || '';
    fields.username.value = data.username || '';
    fields.kelas.value = data.kelas || '';
    fields.role.value = data.role || '';
    fields.password.value = '';
    fields.password.required = false;
    sesuaikanNim();
  }

  document.getElementById('btn-tambah').addEventListener('click', modeTambah);

  tbody.addEventListener('click', function (e) {
    var ubah = e.target.closest('.btn-ubah');
    var hapus = e.target.closest('.btn-hapus');

    if (ubah) {
      modeUbah(ubah.dataset);
      window.AppModal.show(modalPengguna, ubah);
    }

    if (hapus) {
      hapusId.value = hapus.dataset.id || '';
      hapusRingkasan.textContent = [hapus.dataset.nama, hapus.dataset.nim, hapus.dataset.username, hapus.dataset.role]
        .filter(Boolean).join('  ·  ');
      window.AppModal.show(modalHapus, hapus);
    }
  });

  modalPengguna.addEventListener('modal:shown', function () { fields.nama.focus(); });

  [modalPengguna, modalHapus].forEach(function (m) {
    m.addEventListener('modal:show', function () {
      if (!window.reopenUserModal) {
        area.textContent = '';
      }
    });
    m.addEventListener('modal:hidden', function () { area.textContent = ''; });
  });

  formHapus.addEventListener('submit', function (e) {
    if (!hapusId.value) { e.preventDefault(); }
  });

  fields.role.addEventListener('change', sesuaikanNim);

  terapkanFilter(false, true);
  if (window.reopenUserModal) {
    var isEditing = Boolean(fieldId.value);
    judulModal.textContent = isEditing ? 'Ubah Pengguna' : 'Tambah Pengguna';
    btnSimpan.textContent = isEditing ? 'Simpan Perubahan' : 'Simpan';
    fields.password.required = !isEditing;
    sesuaikanNim();
    window.AppModal.show(modalPengguna, document.getElementById('btn-tambah'));
    window.reopenUserModal = false;
  }
})();