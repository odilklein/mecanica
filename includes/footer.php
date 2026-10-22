<?php
if (!isset($caminho_base)) {
    require_once __DIR__ . '/base_url.php';
    $caminho_base = $GLOBALS['caminho_base'] ?? '';
}
?>
                    </div>
                    <!-- / container-xxl -->

                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl">
                            <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                                <div class="text-body">
                                    &copy; <?php echo date('Y'); ?> <?php echo esc(EMPRESA_NOME); ?> &mdash; <?php echo esc(EMPRESA_SLOGAN); ?>
                                </div>
                                <div class="text-body-secondary"><?php echo esc(EMPRESA_CIDADE); ?></div>
                            </div>
                        </div>
                    </footer>

                    <div class="content-backdrop fade"></div>
                </div>
                <!-- / Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
        <!-- Área de arraste para abrir o menu em telas pequenas -->
        <div class="drag-target"></div>
    </div>
    <!-- / Layout wrapper -->

    <script src="<?php echo $caminho_base; ?>/assets/vendor/js/bootstrap.js"></script>
    <script src="<?php echo $caminho_base; ?>/assets/vendor/js/menu.js"></script>
    <script src="<?php echo $caminho_base; ?>/js/main.js"></script>
    <script src="<?php echo $caminho_base; ?>/js/toast.js"></script>
    <script src="<?php echo $caminho_base; ?>/js/shell.js"></script>
    <?php // A configuração do CRUD precisa existir ANTES de crud.js carregar. ?>
    <?php if (isset($scripts_config)) echo $scripts_config; ?>
    <script src="<?php echo $caminho_base; ?>/js/crud.js"></script>
    <?php if (isset($scripts_extra)) echo $scripts_extra; ?>
</body>
</html>
