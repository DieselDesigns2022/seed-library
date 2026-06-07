document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('submit', (event) => {
      if (!confirm(el.getAttribute('data-confirm') || 'Are you sure?')) event.preventDefault();
    });
  });
});
