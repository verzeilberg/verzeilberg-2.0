/*
 * Contact message list (admin/contact/index): tick messages, or all of them, and delete them at once.
 * "Select all" in the header ticks this page; then a link offers every message, also those on other pages
 * (the form then sends all=1). The form asks for confirmation through data-confirm (core/common.js).
 */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('contactBulkForm');
    if (!form) {
        return;
    }

    const total = parseInt(form.dataset.total, 10);
    const selectAll = document.getElementById('contactSelectAll');
    const checkboxes = Array.from(document.querySelectorAll('.contact-checkbox'));
    const deleteButton = document.getElementById('contactBulkDelete');
    const allInput = document.getElementById('contactBulkAll');
    const allNotice = document.getElementById('contactBulkAllNotice');
    const allSelected = document.getElementById('contactBulkAllSelected');

    function plural(count) {
        return count === 1 ? '1 contactformulier' : count + ' contactformulieren';
    }

    function update() {
        const checked = checkboxes.filter(cb => cb.checked).length;
        const pageAll = checked === checkboxes.length;
        const everything = allInput.value === '1';
        const count = everything ? total : checked;

        selectAll.checked = pageAll;
        selectAll.indeterminate = checked > 0 && !pageAll;

        // Only worth offering when there are more messages than on this page
        allNotice.classList.toggle('d-none', !pageAll || everything || total <= checkboxes.length);
        allSelected.classList.toggle('d-none', !everything);

        deleteButton.disabled = count === 0;
        deleteButton.querySelector('span').textContent = count === 0
            ? 'Geselecteerde verwijderen'
            : (everything ? 'Alle ' + total + ' verwijderen' : plural(count) + ' verwijderen');
        form.dataset.confirm = everything
            ? 'Weet je zeker dat je alle ' + total + ' contactformulieren wilt verwijderen? Dit kan niet ongedaan worden gemaakt.'
            : 'Weet je zeker dat je ' + plural(count) + ' wilt verwijderen? Dit kan niet ongedaan worden gemaakt.';
    }

    selectAll.addEventListener('change', function () {
        checkboxes.forEach(cb => { cb.checked = selectAll.checked; });
        allInput.value = '0';
        update();
    });

    checkboxes.forEach(cb => cb.addEventListener('change', function () {
        allInput.value = '0';
        update();
    }));

    document.getElementById('contactBulkSelectAll').addEventListener('click', function () {
        allInput.value = '1';
        update();
    });

    document.getElementById('contactBulkClear').addEventListener('click', function () {
        checkboxes.forEach(cb => { cb.checked = false; });
        allInput.value = '0';
        update();
    });

    update();
});
