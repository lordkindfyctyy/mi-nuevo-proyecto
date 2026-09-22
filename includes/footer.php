    </main>
    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> <?= APP_NAME ?>. Todos los derechos reservados.</p>
        </div>
    </footer>
    <?php if (isLoggedIn()): ?>
        <?php require_once __DIR__ . '/support_widget.php'; ?>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/main.js?v=<?= @filemtime(__DIR__ . '/../public/assets/js/main.js') ?: time() ?>"></script>
</body>
</html>
