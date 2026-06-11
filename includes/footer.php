<?php if (isset($_SESSION['user_id'])): ?>
        </div>
    </div>
</div>
<?php else: ?>
</div>
<?php endif; ?>
<script src="/assets/js/bootstrap.bundle.min.js"></script>
<script src="/assets/vendors/chart.js/chart.umd.min.js"></script>
<script src="/assets/js/main.js"></script>
<?= isset($extraScripts) ? $extraScripts : '' ?>
</body>
</html>
