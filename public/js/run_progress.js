/**
 * Drive a persistent migration run through small AJAX batches.
 *
 * This follows GLPI DataInjection's batch pattern while the server remains
 * authoritative for the offset, counters and resumable row trace.
 *
 * @param {HTMLFormElement} worker
 */
function startTicketMigrationRun(worker) {
    if (!worker) {
        return;
    }

    const errorAlert = document.querySelector('[data-batch-error]');
    const completeAlert = document.querySelector('[data-batch-complete]');
    const progressBar = document.querySelector('[data-progress-bar]');

    function text(tag, value, className) {
        const node = document.createElement(tag);
        node.textContent = value;
        if (className) {
            node.className = className;
        }
        return node;
    }

    function renderItems(items) {
        const body = document.querySelector('[data-run-items]');
        body.replaceChildren();
        items.forEach((item) => {
            const row = document.createElement('tr');
            row.append(text('td', item.row_number));
            row.append(text('td', item.external_id || '—'));
            const statusCell = document.createElement('td');
            statusCell.append(text('span', item.status_label, 'badge ' + (
                item.status === 'success' ? 'bg-success' :
                    item.status === 'failed' ? 'bg-danger' :
                        item.status === 'changed' ? 'bg-warning text-dark' : 'bg-info'
            )));
            row.append(statusCell);
            const ticketCell = document.createElement('td');
            if (item.ticket_url) {
                const link = text('a', '#' + item.tickets_id);
                link.href = item.ticket_url;
                ticketCell.append(link);
            } else {
                ticketCell.textContent = '—';
            }
            row.append(ticketCell);
            row.append(text('td', item.message_label));
            body.append(row);
        });
    }

    function update(response) {
        const progress = response.total_rows
            ? Math.min(100, Math.round(100 * response.processed_rows / response.total_rows))
            : 100;
        progressBar.style.width = progress + '%';
        progressBar.textContent = progress + '%';
        progressBar.closest('.progress').setAttribute('aria-valuenow', progress);
        const label = document.querySelector('[data-progress-text]');
        label.textContent = label.dataset.template
            .replace('%1$s', response.processed_rows)
            .replace('%2$s', response.total_rows);
        ['success_count', 'skipped_count', 'changed_count', 'failed_count'].forEach((key) => {
            document.querySelector('[data-counter="' + key + '"]').textContent = response[key];
        });
        document.querySelector('[data-run-status]').textContent = response.status_label || response.status;
        renderItems(response.items || []);
    }

    function stopAnimation() {
        progressBar.classList.remove('progress-bar-animated');
    }

    function processBatch() {
        $.ajax({
            url: worker.dataset.url,
            type: 'POST',
            dataType: 'json',
            data: $(worker).serialize(),
            success: function (response) {
                if (response.busy) {
                    window.setTimeout(processBatch, 1500);
                    return;
                }
                if (response.error) {
                    showError(response.error);
                    return;
                }
                update(response);
                if (response.finished) {
                    stopAnimation();
                    completeAlert.classList.remove('d-none');
                    return;
                }
                window.setTimeout(processBatch, 500);
            },
            error: function (xhr) {
                if (xhr.status === 409) {
                    window.setTimeout(processBatch, 1500);
                    return;
                }
                const response = xhr.responseJSON || {};
                if (response.status === 'paused' && response.error) {
                    window.location.reload();
                    return;
                }
                showError(response.error || worker.dataset.errorLabel);
            },
        });
    }

    function showError(message) {
        stopAnimation();
        errorAlert.textContent = message;
        errorAlert.classList.remove('d-none');
    }

    processBatch();
}
