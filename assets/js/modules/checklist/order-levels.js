/*
 * Default sort order of a checklist (admin/checklist/new and edit): the form has a level for every choice, but at
 * first only one empty dropdown shows. After a choice the next one appears, without the choices made above it, until
 * nothing is left; setting a level back to "—" hides the levels below it (and clears them, so they aren't saved).
 */
document.addEventListener('DOMContentLoaded', function () {
    const container = document.querySelector('.js-order-levels');
    if (!container) {
        return;
    }
    const levels = Array.from(container.querySelectorAll('.js-order-level'));
    const selectOf = level => level.querySelector('select[name$="[orderBy' + (levels.indexOf(level) + 1) + ']"]');

    function update() {
        const taken = [];
        let showNext = true;

        levels.forEach(function (level, index) {
            const select = selectOf(level);
            // Choices of the levels above are not offered again
            Array.from(select.options).forEach(function (option) {
                const usedAbove = option.value !== '' && taken.includes(option.value);
                option.hidden = usedAbove;
                option.disabled = usedAbove;
            });
            const left = Array.from(select.options).some(option => option.value !== '' && !option.disabled);

            const visible = showNext && (index === 0 || left);
            level.hidden = !visible;
            if (!visible || (select.value !== '' && taken.includes(select.value))) {
                select.value = '';
            }

            if (visible && select.value !== '') {
                taken.push(select.value);
            }
            // The direction only matters once something is chosen
            level.querySelector('.js-order-dir').hidden = select.value === '';
            // The next level only after a choice in this one
            showNext = visible && select.value !== '';
        });
    }

    container.addEventListener('change', update);
    update();
});
