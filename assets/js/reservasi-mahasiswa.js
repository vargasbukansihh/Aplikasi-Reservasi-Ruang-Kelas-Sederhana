(function () {
  'use strict';

  var form = document.getElementById('form-reservasi');
  var modalForm = document.getElementById('modal-reservasi');
  var timeError = document.getElementById('err-jam-reservasi');
  var roomField = document.getElementById('field-ruang');
  var dateField = document.getElementById('field-tanggal');
  var startField = document.getElementById('field-jam-mulai');
  var endField = document.getElementById('field-jam-selesai');
  var purposeField = document.getElementById('field-keperluan');

  function updateStartMinimum() {
    startField.min = dateField.value === dateField.dataset.today
      ? dateField.dataset.currentTime
      : '';
  }

  function clearFormValidation() {
    [roomField, dateField, startField, endField, purposeField].forEach(function (field) {
      field.classList.remove('is-invalid');
    });
    timeError.classList.remove('d-block');
  }

  function validateReservation() {
    clearFormValidation();
    var isValid = true;
    [roomField, dateField, startField, endField, purposeField].forEach(function (field) {
      if (!field.checkValidity()) {
        field.classList.add('is-invalid');
        isValid = false;
      }
    });

    if (purposeField.value.trim().length < 3) {
      purposeField.classList.add('is-invalid');
      isValid = false;
    }
    if (startField.value && endField.value && endField.value <= startField.value) {
      endField.classList.add('is-invalid');
      timeError.classList.add('d-block');
      isValid = false;
    }
    if (!isValid) {
      var firstInvalid = form.querySelector('.is-invalid');
      if (firstInvalid) {
        firstInvalid.focus();
      }
    }
    return isValid;
  }

  function parseQueryParameters() {
    var params = new URLSearchParams(window.location.search);
    var room = params.get('ruang');
    var date = params.get('tanggal');
    var time = params.get('jam');
    if (!room && !date && !time) {
      return false;
    }

    if (room && Array.prototype.some.call(roomField.options, function (option) {
      return option.value === room;
    })) {
      roomField.value = room;
    }
    if (date) {
      dateField.value = date;
      updateStartMinimum();
    }
    if (time) {
      var times = time.split(/[–-]/);
      if (times.length === 2) {
        startField.value = times[0].trim();
        endField.value = times[1].trim();
      }
    }
    return true;
  }

  document.getElementById('btn-ajukan-reservasi').addEventListener('click', function () {
    form.reset();
    clearFormValidation();
    updateStartMinimum();
  });

  form.addEventListener('submit', function (event) {
    if (!validateReservation()) {
      event.preventDefault();
      return;
    }
  });

  [roomField, dateField, startField, endField, purposeField].forEach(function (field) {
    field.addEventListener('input', function () {
      field.classList.remove('is-invalid');
      if (field === startField || field === endField) {
        timeError.classList.remove('d-block');
        startField.classList.remove('is-invalid');
        endField.classList.remove('is-invalid');
      }
    });
    dateField.addEventListener('change', updateStartMinimum);
    field.addEventListener('change', function () {
      field.classList.remove('is-invalid');
      if (field === startField || field === endField) {
        timeError.classList.remove('d-block');
        startField.classList.remove('is-invalid');
        endField.classList.remove('is-invalid');
      }
    });
  });

  modalForm.addEventListener('modal:shown', function () {
    roomField.focus();
  });
  modalForm.addEventListener('modal:hidden', clearFormValidation);

  var shouldOpenModal = parseQueryParameters();
  updateStartMinimum();
  if (window.reopenReservationModal || shouldOpenModal) {
    window.AppModal.show(modalForm, document.getElementById('btn-ajukan-reservasi'));
  }
})();
