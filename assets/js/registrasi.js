(function () {
  'use strict';

  var form = document.getElementById('form-registrasi');
  var nameField = document.getElementById('nama-registrasi');
  var nimField = document.getElementById('nim-registrasi');
  var usernameField = document.getElementById('username-registrasi');
  var emailField = document.getElementById('email-registrasi');
  var classField = document.getElementById('kelas-registrasi');
  var passwordField = document.getElementById('password-registrasi');
  var confirmField = document.getElementById('konfirmasi-password');
  var termsField = document.getElementById('setuju-ketentuan');
  var message = document.getElementById('pesan-registrasi');
  var fields = [
    nameField,
    nimField,
    usernameField,
    emailField,
    classField,
    passwordField,
    confirmField,
    termsField
  ];

  function clearInvalid(field) {
    field.classList.remove('is-invalid');
  }

  fields.forEach(function (field) {
    field.addEventListener(field.type === 'checkbox' ? 'change' : 'input', function () {
      clearInvalid(field);
      message.hidden = true;
      message.textContent = '';
    });
  });

  form.addEventListener('submit', function (event) {
    var firstInvalid = null;
    [nameField, nimField, usernameField, emailField, classField, passwordField, confirmField].forEach(function (field) {
      var value = field.value.trim();
      var isOptional = field === classField;
      var invalid = (!isOptional && !value) || (value && !field.validity.valid);
      if (field === confirmField && value && value !== passwordField.value) {
        invalid = true;
      }

      field.classList.toggle('is-invalid', invalid);
      if (invalid) {
        firstInvalid = firstInvalid || field;
      }
    });

    termsField.classList.toggle('is-invalid', !termsField.checked);
    if (!termsField.checked) {
      firstInvalid = firstInvalid || termsField;
    }

    if (firstInvalid) {
      event.preventDefault();
      firstInvalid.focus();
    }
  });
})();
