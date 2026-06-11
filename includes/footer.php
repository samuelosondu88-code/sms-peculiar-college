<?php if (isset($_SESSION['user_id'])): ?>
        </div>
    </div>
</div>
<?php else: ?>
</div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="/assets/js/main.js"></script>
<?= isset($extraScripts) ? $extraScripts : '' ?>
</body>
</html>
