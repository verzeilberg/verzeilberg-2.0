/* Checklist item form (admin item_form and public checklist/form, via _item_form_scripts.html.twig):
 * field info popovers, date pickers and the "show only when" conditions. Needs flatpickr and components/popovers.js. */
document.addEventListener('DOMContentLoaded', function() {
    // Field info popovers (started by components/popovers.js): only one open at a time, close on a click elsewhere or Escape
    const infoTriggers = document.querySelectorAll('.js-field-info');
    const hideInfo = function(except) {
        infoTriggers.forEach(function(trigger) {
            if (trigger !== except) {
                bootstrap.Popover.getInstance(trigger)?.hide();
            }
        });
    };
    document.addEventListener('click', function(event) {
        const trigger = event.target.closest('.js-field-info');
        if (trigger) {
            event.preventDefault();
            hideInfo(trigger);
        } else if (!event.target.closest('.popover')) {
            hideInfo(null);
        }
    });
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            hideInfo(null);
        }
    });

    document.querySelectorAll('.js-datepicker').forEach(function(input) {
        flatpickr(input, {
            dateFormat: input.dataset.dateFormat || 'Y-m-d',
            locale: {firstDayOfWeek: 1},
            allowInput: true,
        });
    });
});

// "Show only when" conditions: a field or fieldset with data-condition-field is shown when that choice field
// has one of data-condition-values. Same rules as App\Service\CheckListConditionEvaluator:
// inside a hidden fieldset, or depending on a hidden field, also means hidden.
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[name="check_list_item"]');
    if (!form) return;
    const conditional = [...form.querySelectorAll('[data-condition-field]')];
    if (!conditional.length) return;

    const wrapperOf = function(name) {
        const input = form.querySelector('[name="check_list_item[field_' + name + ']"], [name="check_list_item[field_' + name + '][]"]');
        return input ? input.closest('[data-field-wrapper]') : null;
    };
    const isHidden = function(el) {
        for (let node = el; node; node = node.parentElement?.closest('[data-field-wrapper]')) {
            if (node.dataset.conditionHidden === '1') return true;
        }
        return false;
    };
    const valueOf = function(name) {
        const inputs = form.querySelectorAll('[name="check_list_item[field_' + name + ']"], [name="check_list_item[field_' + name + '][]"]');
        const values = [];
        inputs.forEach(function(input) {
            if (input.type === 'radio' || input.type === 'checkbox') {
                if (input.checked) values.push(input.value);
            } else if (input.value !== '') {
                values.push(input.value);
            }
        });
        return values;
    };
    const update = function() {
        // A few passes, so conditions that depend on other conditional fields settle
        for (let pass = 0; pass < 5; pass++) {
            let changed = false;
            conditional.forEach(function(el) {
                const source = wrapperOf(el.dataset.conditionField);
                const allowed = JSON.parse(el.dataset.conditionValues || '[]');
                const visible = source !== null && !isHidden(source)
                    && valueOf(el.dataset.conditionField).some(value => allowed.includes(value));
                const flag = visible ? '0' : '1';
                if (el.dataset.conditionHidden !== flag) {
                    el.dataset.conditionHidden = flag;
                    el.classList.toggle('d-none', !visible);
                    changed = true;
                }
            });
            if (!changed) break;
        }
    };
    form.addEventListener('change', update);
    update();
});
