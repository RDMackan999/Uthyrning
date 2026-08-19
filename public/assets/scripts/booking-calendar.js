(function () {
    'use strict';

    const calendar = document.querySelector('[data-booking-calendar]');

    if (!(calendar instanceof HTMLElement)) {
        return;
    }

    const startInput = document.querySelector('#booking-start-date');
    const endInput = document.querySelector('#booking-end-date');
    const feedback = calendar.querySelector('[data-calendar-feedback]');
    const selectionText = calendar.querySelector('[data-calendar-selection]');
    const previousButton = calendar.querySelector('[data-calendar-prev]');
    const nextButton = calendar.querySelector('[data-calendar-next]');
    const clearButton = calendar.querySelector('[data-calendar-clear]');
    const monthPanels = Array.from(calendar.querySelectorAll('[data-calendar-month]'));
    const dayButtons = Array.from(calendar.querySelectorAll('[data-calendar-date]'));
    const minDate = calendar.dataset.minDate || '';
    const maxDate = calendar.dataset.maxDate || '';
    const availableByDate = new Map();
    let activeMonthIndex = Number.parseInt(calendar.dataset.activeMonth || '0', 10);

    if (!(startInput instanceof HTMLInputElement) || !(endInput instanceof HTMLInputElement)) {
        return;
    }

    calendar.classList.add('is-enhanced');
    startInput.min = minDate;
    startInput.max = maxDate;
    endInput.min = minDate;
    endInput.max = maxDate;

    dayButtons.forEach((button) => {
        if (!(button instanceof HTMLButtonElement)) {
            return;
        }

        const date = button.dataset.calendarDate || '';
        button.dataset.calendarBaseLabel = button.getAttribute('aria-label') || date;
        availableByDate.set(date, button.dataset.calendarAvailable === '1');

        button.addEventListener('click', () => {
            if (button.getAttribute('aria-disabled') === 'true') {
                showMessage('Datumet är inte tillgängligt.');
                return;
            }

            selectDate(date);
        });
    });

    startInput.addEventListener('change', syncFromInputs);
    endInput.addEventListener('change', syncFromInputs);

    if (previousButton instanceof HTMLButtonElement) {
        previousButton.addEventListener('click', () => {
            showMonth(activeMonthIndex - 1);
        });
    }

    if (nextButton instanceof HTMLButtonElement) {
        nextButton.addEventListener('click', () => {
            showMonth(activeMonthIndex + 1);
        });
    }

    if (clearButton instanceof HTMLButtonElement) {
        clearButton.addEventListener('click', () => {
            startInput.value = '';
            endInput.value = '';
            showMessage('');
            updateSelectionState();
            startInput.focus();
        });
    }

    showMonth(activeMonthIndex);
    syncFromInputs();

    function selectDate(date) {
        const startDate = startInput.value;
        const endDate = endInput.value;

        if (startDate === '' || endDate !== '' || date < startDate) {
            startInput.value = date;
            endInput.value = '';
            showMessage('Startdatum valt. Välj slutdatum.');
            updateSelectionState();
            return;
        }

        if (!isRangeAvailable(startDate, date)) {
            endInput.value = '';
            showMessage('Vald period innehåller blockerade dagar. Välj en annan period.');
            updateSelectionState();
            return;
        }

        endInput.value = date;
        showMessage('');
        updateSelectionState();
    }

    function syncFromInputs() {
        const startDate = startInput.value;
        const endDate = endInput.value;

        if (startDate !== '' && !isDateInAllowedRange(startDate)) {
            showMessage('Startdatum ligger utanför bokningsperioden.');
            updateSelectionState();
            return;
        }

        if (endDate !== '' && !isDateInAllowedRange(endDate)) {
            showMessage('Slutdatum ligger utanför bokningsperioden.');
            updateSelectionState();
            return;
        }

        if (startDate !== '' && endDate !== '' && endDate < startDate) {
            showMessage('Slutdatum måste vara samma dag eller efter startdatum.');
            updateSelectionState();
            return;
        }

        if (startDate !== '' && endDate !== '' && !isRangeAvailable(startDate, endDate)) {
            showMessage('Vald period innehåller blockerade dagar. Välj en annan period.');
            updateSelectionState();
            return;
        }

        showMessage('');
        updateSelectionState();
    }

    function updateSelectionState() {
        const startDate = startInput.value;
        const endDate = endInput.value;

        dayButtons.forEach((button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            const date = button.dataset.calendarDate || '';
            const isStart = startDate !== '' && date === startDate;
            const isEnd = endDate !== '' && date === endDate;
            const isRange = startDate !== '' && endDate !== '' && date >= startDate && date <= endDate;
            const status = button.querySelector('[data-calendar-day-status]');

            button.classList.toggle('is-selected-start', isStart);
            button.classList.toggle('is-selected-end', isEnd);
            button.classList.toggle('is-selected-range', isRange && !isStart && !isEnd);
            button.classList.toggle('is-selected', isStart || isEnd || isRange);

            if (status instanceof HTMLElement) {
                const baseLabel = button.dataset.calendarBaseLabel || date;

                if (isStart && isEnd) {
                    status.textContent = 'Vald dag';
                    button.setAttribute('aria-label', baseLabel + ', vald dag');
                } else if (isStart) {
                    status.textContent = 'Start';
                    button.setAttribute('aria-label', baseLabel + ', valt startdatum');
                } else if (isEnd) {
                    status.textContent = 'Slut';
                    button.setAttribute('aria-label', baseLabel + ', valt slutdatum');
                } else if (isRange) {
                    status.textContent = 'Vald period';
                    button.setAttribute('aria-label', baseLabel + ', ingår i vald period');
                } else {
                    status.textContent = availableByDate.get(date) === true ? 'Ledigt' : 'Ej tillgängligt';
                    button.setAttribute('aria-label', baseLabel);
                }
            }
        });

        if (selectionText instanceof HTMLElement) {
            if (startDate !== '' && endDate !== '') {
                selectionText.textContent = 'Vald period: ' + startDate + ' till ' + endDate + '.';
            } else if (startDate !== '') {
                selectionText.textContent = 'Valt startdatum: ' + startDate + '. Välj slutdatum.';
            } else {
                selectionText.textContent = 'Inget datum valt.';
            }
        }
    }

    function showMonth(index) {
        if (monthPanels.length === 0) {
            return;
        }

        activeMonthIndex = Math.max(0, Math.min(index, monthPanels.length - 1));

        monthPanels.forEach((panel, panelIndex) => {
            if (panel instanceof HTMLElement) {
                panel.hidden = panelIndex !== activeMonthIndex;
            }
        });

        if (previousButton instanceof HTMLButtonElement) {
            previousButton.disabled = activeMonthIndex === 0;
        }

        if (nextButton instanceof HTMLButtonElement) {
            nextButton.disabled = activeMonthIndex >= monthPanels.length - 1;
        }
    }

    function showMessage(message) {
        if (feedback instanceof HTMLElement) {
            feedback.textContent = message;
        }
    }

    function isDateInAllowedRange(date) {
        return date >= minDate && date <= maxDate && availableByDate.has(date);
    }

    function isRangeAvailable(startDate, endDate) {
        let current = parseDate(startDate);
        const end = parseDate(endDate);

        if (current === null || end === null || current > end) {
            return false;
        }

        while (current <= end) {
            const date = formatDate(current);

            if (availableByDate.get(date) !== true) {
                return false;
            }

            current.setUTCDate(current.getUTCDate() + 1);
        }

        return true;
    }

    function parseDate(date) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) {
            return null;
        }

        const parsed = new Date(date + 'T00:00:00Z');

        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    function formatDate(date) {
        return date.toISOString().slice(0, 10);
    }
})();
