/* AI Story Studio — small progressive enhancements, no dependencies. */
(function () {
    'use strict';

    document.documentElement.classList.add('js');

    const toPersian = (value) => String(value).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);

    /* ---------- Wizard ---------- */
    function initWizard(form) {
        const steps = Array.from(form.querySelectorAll('.wizard-step'));
        const markers = Array.from(document.querySelectorAll('[data-step-marker]'));
        const prev = form.querySelector('[data-wizard-prev]');
        const next = form.querySelector('[data-wizard-next]');
        const submit = form.querySelector('[data-wizard-submit]');
        let current = 0;

        const firstError = steps.findIndex((step) => step.querySelector('.error'));
        if (firstError >= 0) current = firstError;

        function show(index) {
            current = Math.max(0, Math.min(index, steps.length - 1));
            steps.forEach((step, i) => step.classList.toggle('is-active', i === current));
            markers.forEach((marker, i) => {
                marker.classList.toggle('is-active', i === current);
                marker.classList.toggle('is-done', i < current);
                marker.classList.toggle('has-error', !!steps[i].querySelector('.error'));
            });
            prev.hidden = current === 0;
            next.hidden = current === steps.length - 1;
            submit.hidden = current !== steps.length - 1;
            if (current === steps.length - 1) fillReview();
            const focusable = steps[current].querySelector('input:not([type=hidden]), textarea, select');
            if (focusable && index !== undefined) focusable.focus({ preventScroll: true });
        }

        function validStep(index) {
            const fields = steps[index].querySelectorAll('input, textarea, select');
            for (const field of fields) {
                if (!field.checkValidity()) {
                    field.reportValidity();
                    return false;
                }
            }
            return true;
        }

        function fillReview() {
            form.querySelectorAll('[data-review]').forEach((target) => {
                const name = target.dataset.review;
                const field = form.elements[name];
                if (!field) return;
                let text = '';
                if (field instanceof RadioNodeList || field.type === 'radio') {
                    const checked = form.querySelector(`input[name="${name}"]:checked`);
                    text = checked ? checked.dataset.label || checked.value : '';
                } else if (field.tagName === 'SELECT') {
                    text = field.options[field.selectedIndex]?.text || '';
                } else if (name === 'original_text') {
                    text = toPersian(field.value.trim().length) + ' نویسه';
                } else {
                    text = field.value;
                }
                target.textContent = text || '—';
            });
        }

        prev.addEventListener('click', () => show(current - 1));
        next.addEventListener('click', () => { if (validStep(current)) show(current + 1); });
        markers.forEach((marker, i) => marker.querySelector('button').addEventListener('click', () => {
            if (i <= current || validStep(current)) show(i);
        }));

        // Enter in a single-line input should advance, not submit, until the last step.
        form.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && event.target.tagName === 'INPUT' && current < steps.length - 1) {
                event.preventDefault();
                next.click();
            }
        });

        show(current);
    }

    /* ---------- Custom style instructions ---------- */
    function initStyleToggle(form) {
        const box = form.querySelector('[data-custom-style]');
        if (!box) return;
        const textarea = box.querySelector('textarea');
        const update = () => {
            const checked = form.querySelector('input[name="visual_style"]:checked');
            const custom = checked && checked.value === 'custom';
            box.querySelector('[data-required-mark]').hidden = !custom;
            textarea.required = custom;
        };
        form.querySelectorAll('input[name="visual_style"]').forEach((input) => input.addEventListener('change', update));
        update();
    }

    /* ---------- Character counter ---------- */
    function initCounters() {
        document.querySelectorAll('[data-counter-for]').forEach((counter) => {
            const field = document.getElementById(counter.dataset.counterFor);
            if (!field) return;
            const max = Number(field.getAttribute('maxlength')) || 0;
            const update = () => {
                counter.textContent = toPersian(field.value.length) + (max ? ' / ' + toPersian(max) : '') + ' نویسه';
            };
            field.addEventListener('input', update);
            update();
        });
    }

    /* ---------- Confirmations ---------- */
    function initConfirms() {
        document.querySelectorAll('form[data-confirm]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!window.confirm(form.dataset.confirm)) event.preventDefault();
            });
        });
    }

    /* ---------- Live progress (polling) ---------- */
    let formDirty = false;
    document.addEventListener('input', (event) => {
        if (event.target.closest('form')) formDirty = true;
    });

    function initProgress(root) {
        const url = root.dataset.progressUrl;
        const initialSignature = root.dataset.signature;
        const toast = root.querySelector('[data-refresh-toast]');
        let timer = null;

        root.querySelector('[data-refresh-now]')?.addEventListener('click', () => window.location.reload());

        function render(data) {
            root.querySelector('[data-progress-value]').textContent = toPersian(data.progress) + '٪';
            root.querySelector('[data-progress-bar]').style.width = data.progress + '%';
            const pill = root.querySelector('[data-status-pill]');
            if (pill) {
                pill.textContent = data.paused ? 'متوقف' : data.status_label;
                pill.className = 'pill tone-' + data.status_tone;
            }
            data.stages.forEach((stage) => {
                const item = root.querySelector(`[data-stage="${stage.key}"]`);
                if (!item) return;
                item.className = 'st-' + stage.state;
                item.querySelector('.dot').textContent = stage.state === 'done' ? '✓' : (stage.state === 'failed' ? '!' : '');
                item.querySelector('.count').textContent = stage.total ? toPersian(stage.done) + '/' + toPersian(stage.total) : '';
            });

            if (data.signature !== initialSignature) {
                const editing = formDirty || (document.activeElement && ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName));
                const playing = Array.from(document.querySelectorAll('audio, video')).some((m) => !m.paused);
                if (!editing && !playing) {
                    window.location.reload();
                    return;
                }
                if (toast) toast.hidden = false;
            }
            schedule(data.working || data.running);
        }

        function poll() {
            fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then((response) => (response.ok ? response.json() : Promise.reject(response.status)))
                .then(render)
                .catch(() => schedule(true, 10000));
        }

        function schedule(active, delay) {
            clearTimeout(timer);
            if (document.hidden) return;
            timer = setTimeout(poll, delay || (active ? 3000 : 20000));
        }

        document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
        schedule(root.dataset.working === '1', root.dataset.working === '1' ? 2500 : 6000);
    }

    /* ---------- Storyboard drag & drop ordering ---------- */
    function initReorder(container) {
        const url = container.dataset.reorderUrl;
        const token = document.querySelector('meta[name="csrf-token"]').content;
        let dragged = null;

        container.addEventListener('dragstart', (event) => {
            const card = event.target.closest('.scene-card');
            if (!card || event.target.closest('details[open], input, textarea, select, button, audio, video')) return;
            dragged = card;
            card.classList.add('dragging');
            event.dataTransfer.effectAllowed = 'move';
        });
        container.addEventListener('dragover', (event) => {
            if (!dragged) return;
            event.preventDefault();
            const target = event.target.closest('.scene-card');
            container.querySelectorAll('.drop-before').forEach((el) => el.classList.remove('drop-before'));
            if (target && target !== dragged) target.classList.add('drop-before');
        });
        container.addEventListener('drop', (event) => {
            if (!dragged) return;
            event.preventDefault();
            const target = event.target.closest('.scene-card');
            if (target && target !== dragged) container.insertBefore(dragged, target);
            finish(true);
        });
        container.addEventListener('dragend', () => finish(false));

        function finish(save) {
            container.querySelectorAll('.drop-before').forEach((el) => el.classList.remove('drop-before'));
            if (!dragged) return;
            dragged.classList.remove('dragging');
            dragged = null;
            if (!save) return;
            const order = Array.from(container.querySelectorAll('.scene-card')).map((card) => card.dataset.sceneId);
            container.querySelectorAll('.scene-num').forEach((num, i) => { num.textContent = toPersian(i + 1); });
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                credentials: 'same-origin',
                body: JSON.stringify({ order }),
            }).then((response) => { if (!response.ok) window.location.reload(); });
        }
    }

    /* Mixed Persian/English content: let each field pick its direction from what is typed. */
    function initBidi() {
        document.querySelectorAll('input:not([type]), input[type=text], input[type=search], textarea').forEach((field) => {
            if (!field.hasAttribute('dir') && !field.classList.contains('ltr-input')) field.setAttribute('dir', 'auto');
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        initBidi();
        document.querySelectorAll('form[data-wizard]').forEach((form) => {
            initWizard(form);
            initStyleToggle(form);
        });
        initCounters();
        initConfirms();
        document.querySelectorAll('[data-progress-url]').forEach(initProgress);
        document.querySelectorAll('[data-reorder-url]').forEach(initReorder);
    });
})();
