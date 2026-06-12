<?php if (isset($_SESSION['user_id'])): ?>
        </div>
    </div>
</div>
<?php else: ?>
</div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
// Auto-inject CSRF token into all forms
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = '<?= generateCsrfToken() ?>';
    document.querySelectorAll('form').forEach(function(form) {
        if (!form.querySelector('[name="csrf_token"]')) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf_token';
            input.value = csrfToken;
            form.appendChild(input);
        }
    });
});
</script>
<?= isset($extraScripts) ? $extraScripts : '' ?>
</body>
</html>
