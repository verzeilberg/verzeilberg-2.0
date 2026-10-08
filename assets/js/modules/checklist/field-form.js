/* Checklist field form (admin/checklist/field_form): shows the options that fit the chosen field type and the "show only when" answer checkboxes. */
document.addEventListener('DOMContentLoaded', function() {
    const fieldTypeSelect = document.querySelector('select[name$="[fieldType]"]');
    const optionField = document.getElementById('optionField');
    const answersField = document.getElementById('answers');
    const introTextField = document.getElementById('introTextField');
    const requiredField = document.querySelector('[data-field="required"]');
    const requiredInput = document.getElementById('requiredSwitch');
    const showInOverviewField = document.querySelector('[data-field="showInOverview"]');
    const showInOverviewInput = showInOverviewField?.querySelector('input[type="checkbox"]');
    const defaultValueField = document.querySelector('[data-field="defaultValue"]');
    const placeholderField = document.querySelector('[data-field="placeholder"]');

    // The form type ("text", "fieldset", ...) of the chosen field type; empty when none is chosen.
    // Read from data-form-type, so nothing depends on database ids.
    const selectedFormType = function() {
        return fieldTypeSelect?.selectedOptions[0]?.dataset.formType || '';
    };

    let isInitialLoad = true;

    function resetOptionFields() {
        // Only reset on field type change, not on initial load
        if (!isInitialLoad) {
            optionField.querySelectorAll('input[type=checkbox]').forEach(cb => cb.checked = false);
            optionField.querySelectorAll('input[type=text], input[type=number]').forEach(input => input.value = '');
            document.getElementById('textareaMeasurements')?.querySelectorAll('input').forEach(input => input.value = '');
        }

        // Hide conditional fields
        document.querySelectorAll('.maxlength-value-field, .prependappend-fields, .measurements-fields').forEach(el => el.style.display = 'none');
    }

    function getOptionsByFieldType() {
        const formType = selectedFormType();

        resetOptionFields();

        // Always hide all options first
        optionField.style.display = 'none';
        optionField.querySelectorAll('.option').forEach(opt => opt.style.display = 'none');

        if (!formType) {
            return;
        }

        // Show only the options for the selected field type
        const elements = optionField.querySelectorAll('.options-' + formType);
        elements.forEach(opt => opt.style.display = 'flex');

        // Show/hide the options fieldset depending on whether any options are visible
        const hasVisibleOptions = elements.length > 0;
        optionField.style.display = hasVisibleOptions ? 'block' : 'none';
        document.getElementById('optionsFieldset')?.style.setProperty('display', hasVisibleOptions ? 'block' : 'none');

        setupConditionalFields();

        isInitialLoad = false;
    }

    function setupConditionalFields() {
        // Maxlength: show/hide maxLengthValue
        const maxlengthCheckbox = document.querySelector('input[name*="maxlength"]');
        if (maxlengthCheckbox) {
            maxlengthCheckbox.addEventListener('change', function() {
                document.querySelectorAll('.maxlength-value-field').forEach(el => {
                    el.style.display = this.checked ? 'block' : 'none';
                });
            });
            document.querySelectorAll('.maxlength-value-field').forEach(el => {
                el.style.display = maxlengthCheckbox.checked ? 'block' : 'none';
            });
        }

        // Measurements: show/hide textareaMeasurements
        const measurementsCheckbox = document.querySelector('input[name*="measurements"]');
        if (measurementsCheckbox) {
            measurementsCheckbox.addEventListener('change', function() {
                document.querySelectorAll('.measurements-fields').forEach(el => {
                    el.style.display = this.checked ? 'block' : 'none';
                });
            });
            document.querySelectorAll('.measurements-fields').forEach(el => {
                el.style.display = measurementsCheckbox.checked ? 'block' : 'none';
            });
        }

        // Prepend/Append: show/hide preapp and apppreinput
        const prependappendCheckbox = document.querySelector('input[name*="prependappend"]');
        if (prependappendCheckbox) {
            prependappendCheckbox.addEventListener('change', function() {
                document.querySelectorAll('.prependappend-fields').forEach(el => {
                    el.style.display = this.checked ? 'flex' : 'none';
                });
            });
            document.querySelectorAll('.prependappend-fields').forEach(el => {
                el.style.display = prependappendCheckbox.checked ? 'flex' : 'none';
            });
        }
    }

    // Answers only for choice fields
    function showAnswers() {
        if (answersField) {
            answersField.style.display = ['select', 'radio', 'checkbox'].includes(selectedFormType()) ? 'block' : 'none';
        }
    }

    function toggleTypeDependentFields() {
        const formType = selectedFormType();

        // Default value: not for fieldsets and files
        if (defaultValueField) {
            defaultValueField.style.display = (!formType || formType === 'file' || formType === 'fieldset') ? 'none' : '';
        }

        // Placeholder: only for the types listed in data-form-types
        if (placeholderField) {
            placeholderField.style.display = placeholderField.dataset.formTypes.split(',').includes(formType) ? '' : 'none';
        }

        if (!formType) {
            // No type chosen yet: required and show in overview only make sense once the type is known
            if (introTextField) introTextField.style.display = 'none';
            if (requiredField) requiredField.style.display = 'none';
            if (showInOverviewField) showInOverviewField.style.display = 'none';
        } else if (formType === 'fieldset') {
            // A fieldset shows its intro text and has no value: not required, no overview column
            if (introTextField) introTextField.style.display = 'block';
            if (requiredField) requiredField.style.display = 'none';
            if (requiredInput) requiredInput.checked = false;
            if (showInOverviewField) showInOverviewField.style.display = 'none';
            if (showInOverviewInput) showInOverviewInput.checked = false;
        } else {
            if (introTextField) introTextField.style.display = 'none';
            if (requiredField) requiredField.style.display = '';
            if (showInOverviewField) showInOverviewField.style.display = '';
        }
    }

    // Required message toggle
    const requiredMessageField = document.querySelector('.required-message-field');

    function toggleRequiredMessage() {
        if (requiredInput && requiredMessageField) {
            requiredMessageField.style.display = requiredInput.checked ? 'block' : 'none';
        }
    }

    if (requiredInput) {
        requiredInput.addEventListener('change', toggleRequiredMessage);
        toggleRequiredMessage();
    }

    if (fieldTypeSelect) {
        fieldTypeSelect.addEventListener('change', function() {
            getOptionsByFieldType();
            showAnswers();
            toggleTypeDependentFields();
        });

        // Initial state for the current selection
        getOptionsByFieldType();
        showAnswers();
        toggleTypeDependentFields();
    }
});

// "Show only when": checkboxes with the answers of the chosen choice field, stored as JSON in the hidden conditionValues input
document.addEventListener('DOMContentLoaded', function() {
    const select = document.querySelector('select[name$="[conditionField]"]');
    const hidden = document.querySelector('input[name$="[conditionValues]"]');
    const list = document.getElementById('conditionValuesList');
    if (!select || !hidden || !list) return;

    const sources = JSON.parse(select.dataset.conditionSources || '{}');
    let selected = [];
    try { selected = JSON.parse(hidden.value || '[]'); } catch (e) { selected = []; }

    const sync = function() {
        hidden.value = JSON.stringify([...list.querySelectorAll('input:checked')].map(cb => cb.value));
    };
    const render = function() {
        list.innerHTML = '';
        const source = sources[select.value];
        if (!source) {
            hidden.value = '';
            return;
        }
        Object.entries(source.answers).forEach(function([value, label], index) {
            const id = 'conditionValue' + index;
            const wrapper = document.createElement('div');
            wrapper.className = 'form-check';
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.className = 'form-check-input';
            input.id = id;
            input.value = value;
            input.checked = selected.includes(value);
            input.addEventListener('change', sync);
            const labelEl = document.createElement('label');
            labelEl.className = 'form-check-label';
            labelEl.htmlFor = id;
            labelEl.textContent = label;
            wrapper.append(input, labelEl);
            list.append(wrapper);
        });
        sync();
    };

    select.addEventListener('change', function() {
        selected = [];
        render();
    });
    render();
});
