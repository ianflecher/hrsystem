<script>
(() => {
    const page = document.querySelector('.people');
    if (!page) return;
    const errors = {{ Illuminate\Support\Js::from($errors->messages()) }};
    const previousForm = {{ Illuminate\Support\Js::from(old('_form_key')) }};
    const forms = Array.from(page.querySelectorAll('form[method="POST"]'));

    forms.forEach((form, index) => {
        const key = new URL(form.action).pathname + ':' + index;
        const marker = document.createElement('input');
        marker.type = 'hidden'; marker.name = '_form_key'; marker.value = key;
        form.append(marker);
        form.dataset.formKey = key;

        form.addEventListener('submit', event => {
            if (event.defaultPrevented) return;
            if (form.dataset.submitting) { event.preventDefault(); return; }
            form.dataset.submitting = 'true';
            form.setAttribute('aria-busy', 'true');
            const submitter = event.submitter;
            // A disabled submit button is omitted from a native POST. Preserve
            // its action so Approve, Reject, and Cancel retain their meaning.
            if (submitter?.name) {
                const action = document.createElement('input');
                action.type = 'hidden'; action.name = submitter.name; action.value = submitter.value;
                action.dataset.submitAction = 'true'; form.append(action);
            }
            form.querySelectorAll('button').forEach(button => {
                if (!button.disabled) { button.dataset.pendingDisabled = 'true'; button.disabled = true; }
            });
            if (submitter) {
                submitter.dataset.originalLabel = submitter.textContent;
                submitter.textContent = 'Saving…';
            }
        });
    });

    const errorForm = forms.find(form => form.dataset.formKey === previousForm);
    Object.entries(errors).forEach(([name, messages]) => {
        const fields = Array.from(page.querySelectorAll('input, select, textarea')).filter(field => field.name === name);
        const field = fields.find(input => errorForm?.contains(input)) || fields.find(input => input.getAttribute('aria-invalid') === 'true') || fields[0];
        if (!field) return;
        if (!field.id) field.id = 'invalid-field-' + Array.from(page.querySelectorAll('input, select, textarea')).indexOf(field);
        field.setAttribute('aria-invalid', 'true');
        const label = field.closest('label') || field.parentElement;
        if (!label.querySelector('.field-error')) {
            const error = document.createElement('small');
            error.id = field.id + '-error'; error.className = 'field-error'; error.textContent = messages[0];
            label.append(error);
            field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
        }
        page.querySelectorAll('[data-error-link]').forEach(link => {
            if (link.dataset.errorLink !== name) return;
            link.href = '#' + field.id;
            link.addEventListener('click', event => {
                event.preventDefault();
                let ancestor = field.parentElement;
                while (ancestor && ancestor !== page) { if (ancestor.tagName === 'DETAILS') ancestor.open = true; ancestor = ancestor.parentElement; }
                field.focus();
            });
        });
    });
    // A calendar "Mark rest" is one button in a small tile; its refusal reads
    // as a dialog rather than squeezed text beside the button.
    if (errorForm?.classList.contains('rest-save-form') && Object.keys(errors).length) {
        errorForm.querySelectorAll('.field-error').forEach(el => el.remove());
        document.getElementById('people-errors')?.remove();
        const dialog = document.createElement('dialog');
        dialog.className = 'people-dialog';
        const text = document.createElement('p');
        text.textContent = Object.values(errors).map(m => m[0]).join(' ');
        const close = document.createElement('button');
        close.type = 'button'; close.textContent = 'OK';
        close.addEventListener('click', () => dialog.close());
        dialog.append(Object.assign(document.createElement('h3'), { textContent: 'Rest days not saved' }), text, close);
        page.append(dialog);
        dialog.addEventListener('close', () => dialog.remove());
        dialog.showModal();
    }
    if (errorForm) {
        let ancestor = errorForm.parentElement;
        while (ancestor && ancestor !== page) { if (ancestor.tagName === 'DETAILS') ancestor.open = true; ancestor = ancestor.parentElement; }
    }

    // Browser back/forward cache can restore the page with pending buttons.
    window.addEventListener('pageshow', () => {
        forms.forEach(form => {
            delete form.dataset.submitting; form.removeAttribute('aria-busy');
            form.querySelectorAll('[data-pending-disabled]').forEach(button => { button.disabled = false; delete button.dataset.pendingDisabled; });
            form.querySelectorAll('[data-original-label]').forEach(button => { button.textContent = button.dataset.originalLabel; delete button.dataset.originalLabel; });
            form.querySelectorAll('[data-submit-action]').forEach(input => input.remove());
        });
    });

    const schedule = page.querySelector('[data-schedule]');
    if (schedule) {
        const controls = page.querySelectorAll('[data-schedule-view]');
        const setView = view => {
            schedule.dataset.view = view;
            controls.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.scheduleView === view)));
        };
        // Agenda is also the no-JavaScript default, so phones never depend on
        // scripting to avoid a wide calendar.
        setView(window.matchMedia('(max-width: 700px)').matches ? 'agenda' : 'calendar');
        controls.forEach(button => button.addEventListener('click', () => setView(button.dataset.scheduleView)));
    }

    const restEmployee = page.querySelector('[data-shift-rest-employee]');
    if (restEmployee) {
        const currentRestDay = page.querySelector('[data-current-rest-day]');
        const updateRestTargets = () => {
            page.querySelectorAll('[data-shift-rest-target]').forEach(input => {
                input.value = restEmployee.value;
            });
            if (currentRestDay) {
                const option = restEmployee.selectedOptions[0];
                currentRestDay.textContent = 'Weekly rest day: ' + (option?.dataset.restDays || 'None set')
                    + ' · Marked this cutoff: ' + (option?.dataset.cutoffRest || 'none');
            }
            page.querySelectorAll('[data-shift-day-state]').forEach(node => {
                const states = JSON.parse(node.dataset.states || '{}');
                node.textContent = states[restEmployee.value] || 'Default working day';
                node.classList.toggle('rest', node.textContent.includes('rest day'));
                node.classList.toggle('shift--work', ! node.textContent.includes('rest day'));
            });
        };
        // Mark rest only picks a day; the Save button sends the picked days,
        // with the employee shown in the dropdown, in one request.
        const saveForm = page.querySelector('[data-rest-save-form]');
        const pendingText = page.querySelector('[data-rest-pending]');
        const saveButton = page.querySelector('[data-rest-save]');
        const clearButton = page.querySelector('[data-rest-clear]');
        const picked = new Set();
        const refreshPicked = () => {
            saveForm.querySelectorAll('input[name="rest_dates[]"]').forEach(input => input.remove());
            picked.forEach(date => {
                const input = document.createElement('input');
                input.type = 'hidden'; input.name = 'rest_dates[]'; input.value = date;
                saveForm.append(input);
            });
            page.querySelectorAll('[data-rest-toggle]').forEach(button => {
                const on = picked.has(button.dataset.restToggle);
                button.setAttribute('aria-pressed', on ? 'true' : 'false');
                button.textContent = on ? '✓ Rest (unsaved)' : 'Mark rest';
                button.classList.toggle('is-picked', on);
            });
            const name = restEmployee.selectedOptions[0]?.textContent.trim() || '';
            pendingText.textContent = picked.size
                ? `${picked.size} day(s) picked for ${name}: ` + Array.from(picked).sort().map(d => new Date(d + 'T00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric' })).join(', ')
                : 'No days picked yet.';
            saveButton.disabled = picked.size === 0;
            clearButton.hidden = picked.size === 0;
        };
        page.querySelectorAll('[data-rest-toggle]').forEach(button => {
            button.addEventListener('click', () => {
                const date = button.dataset.restToggle;
                picked.has(date) ? picked.delete(date) : picked.add(date);
                refreshPicked();
            });
        });
        clearButton.addEventListener('click', () => { picked.clear(); refreshPicked(); });
        restEmployee.addEventListener('change', () => {
            // Picks belong to one person; switching people starts over.
            picked.clear();
            updateRestTargets();
            refreshPicked();
        });
        updateRestTargets();
        refreshPicked();
    }
})();
</script>
