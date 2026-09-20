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
})();
</script>
