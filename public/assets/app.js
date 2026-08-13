document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-confirm]').forEach((el) => {
    el.addEventListener('submit', (event) => {
      if (!confirm(el.getAttribute('data-confirm') || 'Are you sure?')) event.preventDefault();
    });
  });
  const rows = document.querySelector('#companion-rows');
  const template = document.querySelector('#companion-template');
  const add = document.querySelector('#add-companion');
  const bindRemove = (root) => root.querySelectorAll('.remove-companion').forEach((button) => {
    button.onclick = () => button.closest('.companion-row').remove();
  });
  if (rows && template && add) {
    bindRemove(rows);
    add.addEventListener('click', () => {
      const index = Number(rows.dataset.nextIndex || 0);
      rows.dataset.nextIndex = String(index + 1);
      const fragment = template.content.cloneNode(true);
      fragment.querySelectorAll('[data-name]').forEach((field) => {
        const key = field.dataset.name;
        field.name = `companions[${index}][${key}]`;
        field.id = `companion_${index}_${key}`;
        field.previousElementSibling?.setAttribute('for', field.id);
        field.removeAttribute('data-name');
      });
      bindRemove(fragment);
      rows.append(fragment);
    });
    if (!rows.children.length) add.click();
  }
});
