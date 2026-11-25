(function () {
  'use strict';

  var form = document.getElementById('form-filter-laporan');
  var fromDate = document.getElementById('periode-dari');
  var toDate = document.getElementById('periode-sampai');

  function updateEndDateMinimum() {
    toDate.min = fromDate.value;
    toDate.setCustomValidity('');
  }

  form.addEventListener('submit', function (event) {
    if (fromDate.value > toDate.value) {
      event.preventDefault();
      toDate.setCustomValidity('Tanggal akhir harus sama dengan atau setelah tanggal awal.');
      toDate.reportValidity();
    }
  });

  fromDate.addEventListener('change', updateEndDateMinimum);
  toDate.addEventListener('change', function () {
    toDate.setCustomValidity('');
  });
  updateEndDateMinimum();

  document.getElementById('btn-ekspor').addEventListener('click', function () {
    window.print();
  });
})();
