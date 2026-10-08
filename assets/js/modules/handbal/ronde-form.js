/* Handbal round form (handbal/beheer/ronde_form): date picker limited to the chosen season. Needs flatpickr + its nl locale. */
const config = document.currentScript.dataset;

document.addEventListener('DOMContentLoaded', function() {
    const datumInput = document.getElementById('handbal_ronde_datum');
    const seizoenSelect = document.getElementById('handbal_ronde_seizoen');

    if (!datumInput || !seizoenSelect) return;

    // Initialize flatpickr
    const fp = flatpickr(datumInput, {
        dateFormat: "d-m-Y",
        locale: "nl",
        allowInput: true,
        minDate: null,
        maxDate: null,
    });

    // Start and end date (d-m-Y) per season id, from data-seasons: [{id, start, end}, …]
    const seasonsData = {};
    JSON.parse(config.seasons || '[]').forEach(function(season) {
        seasonsData[season.id] = { start: season.start, end: season.end };
    });

    // Helper to parse d-m-Y date string to Date object (at midnight)
    function parseDateDMY(dateStr) {
        const parts = dateStr.split('-');
        return new Date(parts[2], parts[1] - 1, parts[0]);
    }

    function updateDatePicker(seizoenId) {
        const season = seasonsData[seizoenId];
        if (season) {
            fp.set('minDate', season.start);
            fp.set('maxDate', season.end);

            // Always ensure selected date is within the new season's range
            const currentDate = new Date();
            currentDate.setHours(0, 0, 0, 0);
            const minDate = parseDateDMY(season.start);
            const maxDate = parseDateDMY(season.end);

            if (fp.selectedDates[0]) {
                // Date already selected - adjust if outside new season range
                const selectedDate = fp.selectedDates[0];
                if (selectedDate < minDate) {
                    fp.setDate(season.start, false);
                } else if (selectedDate > maxDate) {
                    fp.setDate(season.end, false);
                }
            } else {
                // No date selected yet - set default based on current date
                if (currentDate < minDate) {
                    // Current date is before season -> use start date
                    fp.setDate(season.start, false);
                } else if (currentDate > maxDate) {
                    // Current date is after season -> use end date
                    fp.setDate(season.end, false);
                } else {
                    // Current date within season -> use current date
                    fp.setDate(currentDate, false);
                }
            }
        } else {
            fp.set('minDate', null);
            fp.set('maxDate', null);
        }
    }

    // Validate manual input - prevent dates outside range
    datumInput.addEventListener('blur', function() {
        const season = seasonsData[seizoenSelect.value];
        if (!season || !this.value) return;

        const selectedDate = fp.selectedDates[0];
        if (selectedDate) {
            const minDate = parseDateDMY(season.start);
            const maxDate = parseDateDMY(season.end);

            if (selectedDate < minDate || selectedDate > maxDate) {
                // Reset to valid date
                fp.setDate(fp.config.minDate, false);
                this.reportValidity();
            }
        }
    });

    // Initial update if season is pre-selected
    if (seizoenSelect.value) {
        updateDatePicker(seizoenSelect.value);
    }

    // Update on season change
    seizoenSelect.addEventListener('change', function() {
        updateDatePicker(this.value);
    });
});
