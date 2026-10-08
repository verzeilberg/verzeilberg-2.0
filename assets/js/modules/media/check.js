/*
 * Media check (admin/media/check): "select all" checkboxes, the delete button only works with a selection,
 * and the #missing tab opens when the page is loaded with that hash (after removing records).
 */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-media-check').forEach(function (form) {
        const selectAll = form.querySelector('.js-select-all');
        const boxes = form.querySelectorAll('.js-select');
        const action = form.querySelector('.js-selection-action');

        const update = function () {
            const checked = [...boxes].filter(box => box.checked).length;
            action.disabled = checked === 0;
            selectAll.checked = checked > 0 && checked === boxes.length;
            selectAll.indeterminate = checked > 0 && checked < boxes.length;
        };

        selectAll.addEventListener('change', function () {
            boxes.forEach(box => { box.checked = selectAll.checked; });
            update();
        });
        boxes.forEach(box => box.addEventListener('change', update));
    });

    const tab = window.location.hash && document.querySelector('[data-bs-target="' + window.location.hash + '"]');
    if (tab) {
        bootstrap.Tab.getOrCreateInstance(tab).show();
    }
});
