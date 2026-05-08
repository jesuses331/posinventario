<aside class="sidebar">
    <div class="brand">
        <div class="d-flex align-items-center">
            <?php if ($logoPath): ?>
                <img src="<?php echo htmlspecialchars($baseUrl . $logoPath); ?>" alt="Logo"
                    style="width: 40px; height: 40px; object-fit: contain; border-radius: 8px;">
            <?php else: ?>
                <div class="logo">A</div>
            <?php endif; ?>
            <div>
                <div class="title"><?php echo htmlspecialchars($nombreNegocio ?? 'AbdiSoft CORE'); ?></div>
                <div class="subtitle">
                    <?php echo ucfirst($planSistema ?? 'demo'); ?>
                    <?php if ($fechaExpiracion): ?>
                        • <?php echo $fechaExpiracion; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="flex-grow-1 overflow-auto py-3">
        <?php if (Auth::isAdmin()): ?>
            <div class="section-label">Principal</div>
            <nav class="nav flex-column px-2">
                <a class="nav-link <?php echo ($active == 'dashboard') ? 'active' : ''; ?>"
                    href="<?php echo htmlspecialchars($baseUrl . 'views/dashboard.php'); ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a class="nav-link <?php echo ($active == 'reportes') ? 'active' : ''; ?>"
                    href="<?php echo htmlspecialchars($baseUrl . 'views/modules/reportes.php'); ?>">
                    <i class="bi bi-bar-chart"></i> Reportes
                </a>
            </nav>
        <?php endif; ?>

        <div class="section-label">Operaciones</div>
        <nav class="nav flex-column px-2">
            <a class="nav-link <?php echo ($active == 'pos') ? 'active' : ''; ?>"
                href="<?php echo htmlspecialchars($baseUrl . 'views/modules/pos.php'); ?>">
                <i class="bi bi-cart-plus"></i> Nueva Venta
            </a>

            <?php if (Auth::isAdmin()): ?>
                <a class="nav-link <?php echo ($active == 'compras') ? 'active' : ''; ?>"
                    href="<?php echo htmlspecialchars($baseUrl . 'views/modules/compras.php'); ?>">
                    <i class="bi bi-bag-plus"></i> Nueva Compra
                </a>
            <?php endif; ?>

            <a class="nav-link <?php echo ($active == 'historial_ventas') ? 'active' : ''; ?>"
                href="<?php echo htmlspecialchars($baseUrl . 'views/modules/historial_ventas.php'); ?>">
                <i class="bi bi-receipt"></i> Historial Ventas
            </a>

            <?php if (Auth::isAdmin()): ?>
                <a class="nav-link <?php echo ($active == 'historial_compras') ? 'active' : ''; ?>"
                    href="<?php echo htmlspecialchars($baseUrl . 'views/modules/historial_compras.php'); ?>">
                    <i class="bi bi-bag-check"></i> Historial Compras
                </a>
            <?php endif; ?>

            <a class="nav-link <?php echo ($active == 'cotizaciones') ? 'active' : ''; ?>"
                href="<?php echo htmlspecialchars($baseUrl . 'views/modules/cotizaciones.php'); ?>">
                <i class="bi bi-file-text"></i> Cotizaciones
            </a>

            <a class="nav-link <?php echo ($active == 'arqueo') ? 'active' : ''; ?>"
                href="<?php echo htmlspecialchars($baseUrl . 'views/modules/arqueo.php'); ?>">
                <i class="bi bi-safe"></i> Arqueo de Caja
            </a>
        </nav>

        <div class="section-label">Gestión</div>
        <nav class="nav flex-column px-2">
            <a class="nav-link <?php echo ($active == 'inventario') ? 'active' : ''; ?>"
                href="<?php echo htmlspecialchars($baseUrl . 'views/modules/inventario.php'); ?>">
                <i class="bi bi-box-seam"></i> Inventario
            </a>
            <a class="nav-link <?php echo ($active == 'clientes') ? 'active' : ''; ?>"
                href="<?php echo htmlspecialchars($baseUrl . 'views/modules/clientes.php'); ?>">
                <i class="bi bi-people"></i> Clientes
            </a>
            <?php if (Auth::isAdmin()): ?>
                <a class="nav-link <?php echo ($active == 'usuarios') ? 'active' : ''; ?>"
                    href="<?php echo htmlspecialchars($baseUrl . 'views/modules/usuarios.php'); ?>">
                    <i class="bi bi-person-gear"></i> Usuarios
                </a>
            <?php endif; ?>
        </nav>

        <?php if (Auth::isAdmin()): ?>
            <div class="section-label">Sistema</div>
            <nav class="nav flex-column px-2">
                <a class="nav-link <?php echo ($active == 'config') ? 'active' : ''; ?>"
                    href="<?php echo htmlspecialchars($baseUrl . 'views/modules/configuracion.php'); ?>">
                    <i class="bi bi-gear"></i> Configuración
                </a>
            </nav>
        <?php endif; ?>
    </div>

    <div class="p-3 border-top">
        <div class="d-flex align-items-center justify-content-between">
            <div class="overflow-hidden">
                <div class="fw-bold title-strong text-truncate" style="font-size: 0.9rem;">
                    <?php echo htmlspecialchars($_SESSION['user_nombre'] ?? ''); ?>
                </div>
                <div class="text-muted text-truncate" style="font-size: 0.75rem; text-transform: capitalize;">
                    <?php echo htmlspecialchars($_SESSION['user_rol'] ?? ''); ?>
                </div>
            </div>
            <a class="btn btn-outline-light btn-sm px-2 py-1"
                href="<?php echo htmlspecialchars($baseUrl . 'views/logout.php'); ?>" style="font-size: 0.7rem;">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</aside>