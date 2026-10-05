/*
 * Every date box as dd/mm/yyyy.
 *
 * A browser's own date box follows the computer's region, and on these
 * computers that is mm/dd/yyyy: HR typing 07/01/2026 for the 1st of July got
 * the 7th of January. Each <input type="date"> keeps doing the work (Livewire,
 * forms and validation still read its yyyy-mm-dd value), but HR types into a
 * dd/mm/yyyy box beside it, and the calendar button still opens the picker.
 */
(function () {
    const pad = (n) => String(n).padStart(2, '0');
    const toDmy = (iso) => {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
        return m ? `${m[3]}/${m[2]}/${m[1]}` : '';
    };
    const toIso = (text) => {
        const m = /^\s*(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2}|\d{4})\s*$/.exec(text || '');
        if (!m) return null;
        const d = +m[1], mo = +m[2];
        let y = +m[3];
        if (y < 100) y += 2000;
        const date = new Date(y, mo - 1, d);
        if (date.getFullYear() !== y || date.getMonth() !== mo - 1 || date.getDate() !== d) return null;
        return `${y}-${pad(mo)}-${pad(d)}`;
    };

    function enhance(input) {
        if (input.dataset.dmy) return;
        input.dataset.dmy = '1';

        const text = document.createElement('input');
        text.type = 'text';
        text.inputMode = 'numeric';
        text.placeholder = 'dd/mm/yyyy';
        text.className = input.className;
        text.autocomplete = 'off';
        text.value = toDmy(input.value);
        text.required = input.required;
        if (input.id) text.setAttribute('aria-labelledby', input.id);

        const wrap = document.createElement('span');
        wrap.style.cssText = 'position:relative;display:block;';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(text);
        wrap.appendChild(input);

        // The real box stays in the page (for the picker and for Livewire),
        // out of sight under the button.
        input.required = false;
        input.tabIndex = -1;
        input.setAttribute('aria-hidden', 'true');
        input.style.cssText = 'position:absolute;right:0;bottom:0;width:2.25rem;height:100%;opacity:0;pointer-events:none;';

        const button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('aria-label', 'Pick a date');
        button.innerHTML = '<i class="fas fa-calendar-days"></i>';
        button.style.cssText = 'position:absolute;right:.5rem;top:50%;transform:translateY(-50%);color:#64748b;background:none;border:0;padding:.25rem;cursor:pointer;';
        wrap.appendChild(button);
        text.style.paddingRight = '2.25rem';

        const push = (iso) => {
            if (input.value === iso) return;
            input.value = iso;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };

        text.addEventListener('change', () => {
            if (text.value.trim() === '') { push(''); return; }
            const iso = toIso(text.value);
            if (iso) { push(iso); text.value = toDmy(iso); text.setCustomValidity(''); }
            else { text.setCustomValidity('Use dd/mm/yyyy, e.g. 01/07/2026'); text.reportValidity(); }
        });
        button.addEventListener('click', () => {
            try { input.showPicker(); } catch (e) { input.style.pointerEvents = 'auto'; input.focus(); input.click(); }
        });
        input.addEventListener('change', () => { text.value = toDmy(input.value); });

        // Livewire can set the value from the server; keep the box in step.
        setInterval(() => {
            if (document.activeElement !== text && toDmy(input.value) !== text.value && toIso(text.value) !== input.value) {
                text.value = toDmy(input.value);
            }
        }, 400);
    }

    const scan = (root) => (root.querySelectorAll ? root.querySelectorAll('input[type="date"]') : []).forEach(enhance);
    const start = () => {
        scan(document);
        new MutationObserver((changes) => changes.forEach((c) => c.addedNodes.forEach((n) => {
            if (n.nodeType !== 1) return;
            if (n.matches && n.matches('input[type="date"]')) enhance(n); else scan(n);
        }))).observe(document.body, { childList: true, subtree: true });
    };
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start) : start();
})();
