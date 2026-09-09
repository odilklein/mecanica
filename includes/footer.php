<?php
$url_js = (isset($caminho_base) ? $caminho_base : '../../') . 'js/main.js';
?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?php echo $url_js; ?>"></script>
    <?php if (isset($scripts_extra)) echo $scripts_extra; ?>
</body>
</html>