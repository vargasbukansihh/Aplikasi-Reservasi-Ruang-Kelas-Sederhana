(function () {
  'use strict';

  var openers = new WeakMap();

  function emit(modal, eventName) {
    modal.dispatchEvent(new CustomEvent(eventName, { bubbles: false }));
  }

  function show(modal, opener) {
    if (!modal || modal.classList.contains('show')) {
      return;
    }

    emit(modal, 'modal:show');
    openers.set(modal, opener || document.activeElement);
    modal.classList.add('show');
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-hidden', 'false');
    modal.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');

    var firstFocusable = modal.querySelector(
      'input:not([type="hidden"]):not(:disabled), select:not(:disabled), textarea:not(:disabled), button:not(:disabled), a[href]'
    );
    (firstFocusable || modal).focus();
    emit(modal, 'modal:shown');
  }

  function hide(modal) {
    if (!modal || !modal.classList.contains('show')) {
      return;
    }

    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
    modal.removeAttribute('aria-modal');
    document.body.classList.remove('modal-open');
    emit(modal, 'modal:hidden');

    var opener = openers.get(modal);
    if (opener && typeof opener.focus === 'function' && document.contains(opener)) {
      opener.focus();
    }
  }

  window.AppModal = { show: show, hide: hide };

  document.addEventListener('click', function (event) {
    var openButton = event.target.closest('[data-modal-target]');
    if (openButton) {
      show(document.querySelector(openButton.dataset.modalTarget), openButton);
      return;
    }

    var dismissButton = event.target.closest('[data-modal-dismiss]');
    if (dismissButton) {
      hide(dismissButton.closest('.modal'));
      return;
    }

    if (event.target.classList.contains('modal')) {
      hide(event.target);
    }

    var alertButton = event.target.closest('[data-alert-dismiss]');
    if (alertButton) {
      var alert = alertButton.closest('.alert');
      if (alert) {
        alert.remove();
      }
    }
  });

  document.addEventListener('keydown', function (event) {
    var modal = document.querySelector('.modal.show');
    if (!modal) {
      return;
    }

    if (event.key === 'Escape') {
      event.preventDefault();
      hide(modal);
      return;
    }

    if (event.key === 'Tab') {
      var focusable = modal.querySelectorAll(
        'input:not([type="hidden"]):not(:disabled), select:not(:disabled), textarea:not(:disabled), button:not(:disabled), a[href]'
      );
      if (!focusable.length) {
        event.preventDefault();
        modal.focus();
        return;
      }

      var first = focusable[0];
      var last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }
  });
})();
