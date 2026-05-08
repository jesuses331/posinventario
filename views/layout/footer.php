    <?php $baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') . '/' : '/'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php if (!empty($extraJs) && is_array($extraJs)): ?>
        <?php foreach ($extraJs as $src): ?>
            <script src="<?php echo htmlspecialchars($src); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <script src="<?php echo htmlspecialchars($baseUrl . 'assets/js/main.js'); ?>"></script>
</body>
</html>

