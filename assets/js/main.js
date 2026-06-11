document.addEventListener('DOMContentLoaded', function () {
    const menuToggle = document.getElementById('menu-toggle');
    const sidebarWrapper = document.getElementById('sidebar-wrapper');

    if (menuToggle && sidebarWrapper) {
        menuToggle.addEventListener('click', function (e) {
            e.preventDefault();
            sidebarWrapper.classList.toggle('toggled');
        });
    }

    const autoCloseAlerts = document.querySelectorAll('.alert-dismissible');
    autoCloseAlerts.forEach(function (alert) {
        setTimeout(function () {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    const confirmButtons = document.querySelectorAll('[data-confirm]');
    confirmButtons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (!confirm(btn.getAttribute('data-confirm') || 'Are you sure?')) {
                e.preventDefault();
            }
        });
    });

    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            const label = this.nextElementSibling;
            if (label && label.classList.contains('custom-file-label')) {
                label.textContent = this.files[0] ? this.files[0].name : 'Choose file';
            }
        });
    });

    const togglePassword = document.querySelectorAll('.toggle-password');
    togglePassword.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = document.querySelector(this.getAttribute('data-target'));
            if (target) {
                const type = target.getAttribute('type') === 'password' ? 'text' : 'password';
                target.setAttribute('type', type);
                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            }
        });
    });

    const datatables = document.querySelectorAll('.datatable');
    if (datatables.length > 0 && typeof DataTable !== 'undefined') {
        datatables.forEach(function (table) {
            new DataTable(table, {
                pageLength: 20,
                responsive: true,
                language: { search: 'Filter:', lengthMenu: 'Show _MENU_ entries' }
            });
        });
    }
});

function showLoader(selector) {
    const el = document.querySelector(selector);
    if (el) {
        el.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    }
}

function fetchJSON(url, data) {
    const params = new URLSearchParams(data);
    return fetch(url + '?' + params.toString())
        .then(function (res) { return res.json(); })
        .catch(function (err) { return { error: err.message }; });
}

function postJSON(url, data) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(function (res) { return res.json(); })
    .catch(function (err) { return { error: err.message }; });
}

function formatCurrency(amount, currency) {
    currency = currency || '₦';
    return currency + parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    var d = new Date(dateStr);
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    return d.getDate() + ' ' + months[d.getMonth()] + ', ' + d.getFullYear();
}

function printElement(elId) {
    var printContents = document.getElementById(elId).innerHTML;
    var originalContents = document.body.innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    location.reload();
}
