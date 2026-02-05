/**
 * SEO Central - JavaScript
 */

const BASE_URL = document.querySelector('link[rel="stylesheet"]').href.replace('/assets/style.css', '');

// --- Refresh ---

function refreshAll() {
    const btn = document.getElementById('btn-refresh-all');
    const status = document.getElementById('refresh-status');
    if (!btn) return;

    btn.disabled = true;
    btn.textContent = 'Rafraichissement...';
    if (status) status.textContent = '';

    fetch(BASE_URL + '/refresh.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'refresh_all' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'ok') {
            if (status) status.textContent = data.refreshed + ' site(s) rafraichi(s)';
            setTimeout(() => location.reload(), 1000);
        } else {
            if (status) status.textContent = 'Erreur : ' + (data.error || 'inconnue');
            btn.disabled = false;
            btn.textContent = 'Rafraichir tout';
        }
    })
    .catch(err => {
        if (status) status.textContent = 'Erreur reseau';
        btn.disabled = false;
        btn.textContent = 'Rafraichir tout';
    });
}

function refreshSite(siteId) {
    const btn = document.getElementById('btn-refresh-site');
    const status = document.getElementById('refresh-status');
    if (!btn) return;

    btn.disabled = true;
    btn.textContent = 'Rafraichissement...';
    if (status) status.textContent = '';

    fetch(BASE_URL + '/refresh.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'refresh_site', site_id: siteId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'ok') {
            if (status) status.textContent = 'Rafraichi';
            setTimeout(() => location.reload(), 1000);
        } else {
            if (status) status.textContent = 'Erreur : ' + (data.error || 'inconnue');
            btn.disabled = false;
            btn.textContent = 'Rafraichir';
        }
    })
    .catch(err => {
        if (status) status.textContent = 'Erreur reseau';
        btn.disabled = false;
        btn.textContent = 'Rafraichir';
    });
}

// --- Delete ---

function deleteSite(siteId, domain) {
    if (!confirm('Supprimer le site "' + domain + '" et toutes ses donnees ?')) {
        return;
    }

    fetch(BASE_URL + '/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ site_id: siteId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'ok') {
            window.location.href = BASE_URL + '/dashboard.php';
        } else {
            alert('Erreur : ' + (data.error || 'inconnue'));
        }
    })
    .catch(err => {
        alert('Erreur reseau');
    });
}

// --- Checkboxes & Copy ---

function toggleAllChecks(master) {
    document.querySelectorAll('.row-check').forEach(function(cb) {
        cb.checked = master.checked;
    });
}

function getSelectedRows() {
    var rows = [];
    document.querySelectorAll('.row-check:checked').forEach(function(cb) {
        rows.push(cb.closest('tr'));
    });
    return rows;
}

function copySelectedDomains() {
    console.log('copySelectedDomains called');
    var rows = getSelectedRows();
    console.log('Selected rows:', rows.length);
    if (rows.length === 0) {
        alert('Aucun site selectionne.');
        return;
    }
    var text = rows.map(function(tr) {
        return tr.dataset.domain;
    }).join('\n');
    console.log('Text to copy:', text);
    copyToClipboard(text);
}

function copySelectedAll() {
    var rows = getSelectedRows();
    if (rows.length === 0) {
        alert('Aucun site selectionne.');
        return;
    }
    var text = rows.map(function(tr) {
        return [tr.dataset.domain, tr.dataset.thematic, tr.dataset.kw, tr.dataset.traffic].join('\t');
    }).join('\n');
    copyToClipboard(text);
}

function copyToClipboard(text) {
    // Fallback pour HTTP (execCommand) + moderne (clipboard API)
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();

    var success = false;
    try {
        success = document.execCommand('copy');
    } catch (e) {
        success = false;
    }

    document.body.removeChild(textarea);

    if (success) {
        showCopyFeedback();
    } else if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(showCopyFeedback);
    } else {
        alert('Copie impossible sur ce navigateur.');
    }
}

function showCopyFeedback() {
    var msg = document.createElement('span');
    msg.textContent = 'Copie !';
    msg.className = 'copy-feedback';
    var filters = document.querySelector('.filters');
    if (filters) {
        filters.appendChild(msg);
        setTimeout(function() { msg.remove(); }, 2000);
    }
}

// --- Filter ---

function filterByThematic(thematicId) {
    const url = new URL(window.location.href);
    if (thematicId > 0) {
        url.searchParams.set('thematic', thematicId);
    } else {
        url.searchParams.delete('thematic');
    }
    window.location.href = url.toString();
}

// --- Table sorting ---

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.data-table').forEach(function(table) {
        const allHeaders = table.querySelectorAll('th');
        allHeaders.forEach(function(header, index) {
            if (header.dataset.sort) {
                header.addEventListener('click', function() {
                    sortTable(table, index, header.dataset.sort, header);
                });
            }
        });
    });
});

function sortTable(table, colIndex, type, headerEl) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr:not(.empty-row)'));

    if (rows.length === 0) return;

    // Determine sort direction
    const isAsc = headerEl.classList.contains('sort-asc');
    const direction = isAsc ? -1 : 1;

    // Remove sort classes from all headers
    table.querySelectorAll('th').forEach(th => {
        th.classList.remove('sort-asc', 'sort-desc');
    });

    // Set sort class on clicked header
    headerEl.classList.add(isAsc ? 'sort-desc' : 'sort-asc');

    rows.sort(function(a, b) {
        let aVal = a.cells[colIndex].textContent.trim();
        let bVal = b.cells[colIndex].textContent.trim();

        if (type === 'number') {
            aVal = parseFloat(aVal.replace(/\s/g, '').replace(',', '.')) || 0;
            bVal = parseFloat(bVal.replace(/\s/g, '').replace(',', '.')) || 0;
            return (aVal - bVal) * direction;
        }

        if (type === 'date') {
            // Format dd/mm/yyyy hh:mm
            aVal = parseDateStr(aVal);
            bVal = parseDateStr(bVal);
            return (aVal - bVal) * direction;
        }

        // String sort
        return aVal.localeCompare(bVal, 'fr') * direction;
    });

    rows.forEach(row => tbody.appendChild(row));
}

function parseDateStr(str) {
    if (!str || str === '-') return 0;
    // dd/mm/yyyy hh:mm
    const parts = str.match(/(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})/);
    if (!parts) return 0;
    return new Date(parts[3], parts[2] - 1, parts[1], parts[4], parts[5]).getTime();
}
