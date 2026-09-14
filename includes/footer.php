<?php
if (!isset($caminho_base)) {
    require_once __DIR__ . '/base_url.php';
}
$url_js = $caminho_base . '/js/main.js';
?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo $url_js; ?>"></script>
    <?php if (isset($scripts_extra)) echo $scripts_extra; ?>
</body>
</html>