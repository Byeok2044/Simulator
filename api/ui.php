<?php
header('Content-Type: application/javascript; charset=UTF-8');
?>
'use strict';
(() => {
    const pageSize = 100;
    function bindResultTabs(form) {
        const tablist = form.querySelector('.results-tabs');
        if (!tablist) return;
        const tabs = [...tablist.querySelectorAll('[role="tab"]')];
        const panels = tabs.map(tab => form.querySelector('#' + tab.getAttribute('aria-controls')));
        function select(index) {
            tabs.forEach((tab, i) => {
                const active = i === index;
                tab.setAttribute('aria-selected', String(active));
                tab.tabIndex = active ? 0 : -1;
                panels[i].hidden = !active;
            });
        }
        tabs.forEach((tab, i) => {
            panels[i].setAttribute('role', 'tabpanel');
            panels[i].setAttribute('aria-labelledby', tab.id);
            panels[i].tabIndex = 0;
            tab.addEventListener('click', () => select(i));
            tab.addEventListener('keydown', event => {
                const next = { ArrowLeft: 1 - i, ArrowRight: 1 - i, Home: 0, End: 1 }[event.key];
                if (next === undefined) return;
                event.preventDefault();
                select(next);
                tabs[next].focus();
            });
        });
        select(tablist.dataset.resultsTab === 'explanation' ? 1 : 0);
        tablist.hidden = false;
    }

    function bindForm() {
        const form = document.getElementById('regression-form');
        if (!form) return;
        bindResultTabs(form);
        const tbody = form.querySelector('#data-table tbody');
        const prototype = tbody.rows[0].cloneNode(true);
        const points = form.elements.namedItem('points');
        const pageInput = form.elements.namedItem('page');
        let rows = JSON.parse(points.value);
        let page = Number(pageInput.value);
        let busy = false;
        const button = action => form.querySelector(`button[value="${action}"]`);
        const status = message => { form.querySelector('#data-status').textContent = message; };
        function syncFile() {
            const name = form.elements.namedItem('csv_name').value;
            button('remove_csv').hidden = !name && !form.elements.namedItem('csv_file').files.length;
            const caption = form.querySelector('#csv-name');
            caption.hidden = !name;
            caption.textContent = name ? `Uploaded: ${name}` : '';
        }
        function clearFile() {
            form.elements.namedItem('csv_file').value = '';
            form.elements.namedItem('csv_name').value = '';
            syncFile();
        }
        form.elements.namedItem('csv_file').addEventListener('change', syncFile);
        function savePage() {
            [...tbody.rows].forEach((tr, offset) => {
                rows[page * pageSize + offset] = [...tr.querySelectorAll('input')].map(input => input.value);
            });
            points.value = JSON.stringify(rows);
            pageInput.value = page;
        }
        function stale(message = 'Data changed. Run to update results.') {
            form.querySelectorAll('[data-model-result]').forEach(section => { section.hidden = true; });
            document.querySelectorAll('[role="alert"]').forEach(alert => { alert.hidden = true; });
            form.querySelectorAll('.status:not(#data-status)').forEach(notice => { notice.hidden = true; });
            status(message);
        }
        function renderRows() {
            page = Math.max(0, Math.min(page, Math.ceil(rows.length / pageSize) - 1));
            const fragment = document.createDocumentFragment();
            rows.slice(page * pageSize, (page + 1) * pageSize).forEach((pair, offset) => {
                const index = page * pageSize + offset;
                const tr = prototype.cloneNode(true);
                tr.querySelector('.row-num').textContent = index + 1;
                tr.querySelectorAll('input').forEach((input, axis) => {
                    input.name = `edit[${index}][${axis}]`;
                    input.value = pair[axis];
                    input.setAttribute('aria-label', `${axis ? 'Y' : 'X'} value, row ${index + 1}`);
                });
                const remove = tr.querySelector('.remove-row');
                remove.value = `remove_${index}`;
                remove.disabled = rows.length <= 2;
                fragment.append(tr);
            });
            tbody.replaceChildren(fragment);
            form.querySelector('.pagination').hidden = rows.length <= pageSize;
            form.querySelector('#page-status').textContent = `Page ${page + 1} of ${Math.ceil(rows.length / pageSize)}`;
            button('previous').disabled = page === 0;
            button('next').disabled = (page + 1) * pageSize >= rows.length;
            button('add').disabled = rows.length >= 5000;
            points.value = JSON.stringify(rows);
            pageInput.value = page;
        }
        // Random linear dataset. Works on the full row state, so it behaves the same
        // after a CSV upload/remove, Sample, Clear, etc.
        function generateData() {
            if (busy) return;
            const total = Math.max(rows.length, 2);
            let slope = Math.random() * 8 - 4;
            if (Math.abs(slope) < 0.5) slope = slope < 0 ? -1.5 : 1.5;
            const intercept = Math.floor(Math.random() * 70) - 20;
            const startX = Math.floor(Math.random() * 10);
            rows = Array.from({ length: total }, (_, i) => {
                const x = +(startX + i * (1.5 + Math.random() * 2)).toFixed(2);
                const noise = (Math.random() - 0.5) * 8;
                return [x, +(slope * x + intercept + noise).toFixed(2)];
            });
            page = 0;
            clearFile();
            form.elements.namedItem('axis_x').value = 'X';
            form.elements.namedItem('axis_y').value = 'Y';
            renderRows();
            stale('Generated a new random dataset. Run to update results.');
        }
        form.querySelector('#btn-generate-data')?.addEventListener('click', generateData);
        // Enter moves down the table; Enter in the last cell runs the model.
        form.addEventListener('keydown', event => {
            const input = event.target;
            if (event.key !== 'Enter' || input.tagName !== 'INPUT' || input.type !== 'text' || input.name === 'predict_x') return;
            const cells = [...form.querySelectorAll('#data-table input[type="text"]')];
            const index = cells.indexOf(input);
            if (index < 0) return;
            event.preventDefault();
            if (index < cells.length - 1) { cells[index + 1].focus(); cells[index + 1].select(); }
            else button('run').click();
        });
        // Numeric-only inputs: digits, one leading minus, one decimal point.
        const cleanNumber = value => {
            let text = value.replace(/[^0-9.\-]/g, '');
            const negative = text.startsWith('-');
            text = text.replace(/-/g, '');
            const dot = text.indexOf('.');
            if (dot !== -1) text = text.slice(0, dot + 1) + text.slice(dot + 1).replace(/\./g, '');
            return (negative ? '-' : '') + text;
        };
        // Clear the prediction result when the Predict X field is emptied.
        form.addEventListener('input', event => {
            if (event.target.name !== 'predict_x') return;
            const output = form.querySelector('.prediction');
            if (output) output.textContent = '';
        });
        form.addEventListener('input', event => {
            const input = event.target;
            if (!input.matches('#data-table input[type="text"], input[name="predict_x"]')) return;
            const cleaned = cleanNumber(input.value);
            if (cleaned === input.value) return;
            const caret = input.selectionStart ?? input.value.length;
            const newCaret = cleanNumber(input.value.slice(0, caret)).length;
            input.value = cleaned;
            input.setSelectionRange(newCaret, newCaret);
        });
        form.addEventListener('input', event => {
            if (event.target.closest('#data-table') || ['axis_x', 'axis_y'].includes(event.target.name)) stale();
        });
        form.addEventListener('click', event => {
            const target = event.target.closest('button[name="action"]');
            if (!target || busy || target.disabled) return;
            const action = target.value;
            if (!['add', 'sample', 'clear', 'previous', 'next'].includes(action) && !action.startsWith('remove_')) return;
            event.preventDefault();
            savePage();
            if (action === 'add') {
                if (rows.length >= 5000) return;
                rows.push(['', '']); page = Math.floor((rows.length - 1) / pageSize);
            } else if (action === 'sample') {
                rows = [50, 55, 60, 62, 68, 75, 78, 85].map((y, index) => [index + 1, y]); page = 0;
            } else if (action === 'clear' || action === 'remove_csv') {
                const imported = form.elements.namedItem('csv_name').value !== '';
                clearFile();
                if (action === 'remove_csv' && !imported) {
                    document.querySelectorAll('[role="alert"]').forEach(alert => { alert.hidden = true; });
                    status('CSV removed.');
                    form.elements.namedItem('csv_file').focus();
                    return;
                }
                rows = [['', ''], ['', '']]; page = 0;
                form.elements.namedItem('zoom').value = '1';
                const prediction = form.elements.namedItem('predict_x');
                if (prediction) prediction.value = '';
            } else if (action.startsWith('remove_')) {
                if (rows.length > 2) rows.splice(Number(action.slice(7)), 1);
            } else page += action === 'next' ? 1 : -1;
            if (['sample', 'clear', 'remove_csv'].includes(action)) {
                clearFile();
                form.elements.namedItem('axis_x').value = 'X';
                form.elements.namedItem('axis_y').value = 'Y';
            }
            renderRows();
            if (action !== 'previous' && action !== 'next') stale(action === 'clear' ? 'Rows cleared.' : action === 'remove_csv' ? 'CSV removed.' : undefined);
            if (action === 'add') tbody.lastElementChild.querySelector('input').focus();
            if (action === 'clear' || action === 'remove_csv') tbody.rows[0].querySelector('input').focus();
        });
        form.addEventListener('submit', async event => {
            if (busy) { event.preventDefault(); return; }
            savePage();
            const action = event.submitter?.value || 'run';
            // Attachments retain the standard browser download behavior.
            if (action === 'download_plot' || !window.fetch) return;
            event.preventDefault();
            const data = new FormData(form);
            data.set('action', action);
            // Do not resend a selected CSV for unrelated plot or prediction actions.
            if (action !== 'import_csv') data.delete('csv_file');
            const openSections = [...form.querySelectorAll('details[open]')].map(details => details.querySelector('summary').textContent);
            const controls = [...form.elements].map(control => [control, control.disabled]);
            busy = true;
            form.setAttribute('aria-busy', 'true');
            controls.forEach(([control]) => { control.disabled = true; });
            const feedback = action === 'explain' ? form.querySelector('.explanation-feedback') : null;
            if (feedback) feedback.textContent = 'Generating explanation…';
            else status(action === 'import_csv' ? 'Importing CSV…' : 'Updating…');
            try {
                const response = await fetch(form.action, { method: 'POST', body: data });
                if (!response.ok) throw new Error('Request failed');
                const document = new DOMParser().parseFromString(await response.text(), 'text/html');
                const updated = document.querySelector('main.container');
                if (!updated?.querySelector('#regression-form')) throw new Error('Invalid response');
                const scrollY = window.scrollY;
                window.document.querySelector('main.container').replaceWith(updated);
                bindForm();
                updated.querySelectorAll('details').forEach(details => {
                    details.open = openSections.includes(details.querySelector('summary').textContent);
                });
                const replacement = updated.querySelector(`button[value="${action}"]`);
                const focusTarget = action === 'explain' ? updated.querySelector('.explanation-text') ?? replacement : replacement;
                if (focusTarget && !focusTarget.disabled) focusTarget.focus({ preventScroll: true });
                window.scrollTo(0, scrollY);
                const error = updated.querySelector('[role="alert"]');
                if (error) { error.tabIndex = -1; error.focus(); }
            } catch {
                if (feedback) feedback.textContent = 'Could not generate an explanation. Please try again.';
                else status('Could not update. Your data is still here; please try again.');
            } finally {
                busy = false;
                form.removeAttribute('aria-busy');
                controls.forEach(([control, disabled]) => { control.disabled = disabled; });
            }
        });
        const predictionError = form.querySelector('.prediction.is-error');
        if (predictionError && predictionError.textContent.trim()) {
            setTimeout(() => { predictionError.textContent = ''; }, 4000);
        }
        syncFile();
    }
    bindForm();
})();
