<?php
/**
 * ============================================================
 * DASHBOARD - POS Inventario
 * Mantiene el layout original (app-shell + sidebar.php)
 * Contenido con Tailwind CSS + Chart.js
 * ============================================================
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../models/Database.php';
require_once __DIR__ . '/../models/Auth.php';

Auth::checkAccess();

$database = new Database();
$db = $database->getConnection();

if (session_status() === PHP_SESSION_NONE)
    session_start();

// -------------------------------------------------------------------
// CONFIG
// -------------------------------------------------------------------
$config = ['nombre_negocio' => 'POS Inventario', 'moneda' => 'Bs'];
try {
    $stmt = $db->query("SELECT nombre_negocio, moneda FROM configuracion ORDER BY updated_at DESC LIMIT 1");
    $row = $stmt->fetch();
    if ($row)
        $config = array_merge($config, $row);
} catch (Throwable $e) {
}
$moneda = $config['moneda'];

// -------------------------------------------------------------------
// KPIs del día
// -------------------------------------------------------------------
$ventas_dia = 0.0;
$transacciones_total = 0;
$ticket_promedio = 0.0;
$ganancia_neta = 0.0;
$descuentos_dia = 0.0;

$ventas_ayer = 0.0;
$transacciones_ayer = 0;
$ticket_promedio_ayer = 0.0;
$ganancia_ayer = 0.0;
$descuentos_ayer = 0.0;

try {
    // Hoy
    $stmt = $db->prepare("SELECT COUNT(*) AS cant, COALESCE(SUM(total),0) AS total
                          FROM ventas WHERE DATE(fecha) = CURDATE()");
    $stmt->execute();
    $r = $stmt->fetch();
    $ventas_dia = (float) ($r['total'] ?? 0);
    $transacciones_total = (int) ($r['cant'] ?? 0);
    $ticket_promedio = $transacciones_total > 0 ? round($ventas_dia / $transacciones_total, 2) : 0;

    $stmt = $db->prepare("SELECT COALESCE(SUM(vd.subtotal - vd.cantidad * vd.precio_compra),0) AS ganancia
                          FROM ventas_detalle vd
                          JOIN ventas v ON vd.venta_id = v.id
                          WHERE DATE(v.fecha) = CURDATE()");
    $stmt->execute();
    $ganancia_neta = (float) ($stmt->fetch()['ganancia'] ?? 0);

    $stmt = $db->prepare("SELECT COALESCE(SUM(descuento),0) AS descuentos
                          FROM ventas WHERE DATE(fecha) = CURDATE()");
    $stmt->execute();
    $descuentos_dia = (float) ($stmt->fetch()['descuentos'] ?? 0);

    // Ayer
    $stmt = $db->prepare("SELECT COUNT(*) AS cant, COALESCE(SUM(total),0) AS total
                          FROM ventas WHERE DATE(fecha) = CURDATE() - INTERVAL 1 DAY");
    $stmt->execute();
    $r = $stmt->fetch();
    $ventas_ayer = (float) ($r['total'] ?? 0);
    $transacciones_ayer = (int) ($r['cant'] ?? 0);
    $ticket_promedio_ayer = $transacciones_ayer > 0 ? round($ventas_ayer / $transacciones_ayer, 2) : 0;

    $stmt = $db->prepare("SELECT COALESCE(SUM(vd.subtotal - vd.cantidad * vd.precio_compra),0) AS ganancia
                          FROM ventas_detalle vd
                          JOIN ventas v ON vd.venta_id = v.id
                          WHERE DATE(v.fecha) = CURDATE() - INTERVAL 1 DAY");
    $stmt->execute();
    $ganancia_ayer = (float) ($stmt->fetch()['ganancia'] ?? 0);

    $stmt = $db->prepare("SELECT COALESCE(SUM(descuento),0) AS descuentos
                          FROM ventas WHERE DATE(fecha) = CURDATE() - INTERVAL 1 DAY");
    $stmt->execute();
    $descuentos_ayer = (float) ($stmt->fetch()['descuentos'] ?? 0);
} catch (Throwable $e) {
}

// -------------------------------------------------------------------
// CÁLCULO DE PORCENTAJES vs. AYER
// -------------------------------------------------------------------
function calc_pct(float $hoy, float $ayer): array
{
    if ($ayer > 0) {
        $pct = (($hoy - $ayer) / $ayer) * 100;
    } elseif ($hoy > 0) {
        $pct = 100;
    } else {
        $pct = 0;
    }
    return [
        'valor' => round($pct, 1),
        'absoluto' => abs(round($pct, 1)),
        'subio' => $pct >= 0,
    ];
}

$pct_ventas = calc_pct($ventas_dia, $ventas_ayer);
$pct_transacciones = calc_pct($transacciones_total, $transacciones_ayer);
$pct_ticket = calc_pct($ticket_promedio, $ticket_promedio_ayer);
$pct_ganancia = calc_pct($ganancia_neta, $ganancia_ayer);
$pct_descuentos = calc_pct($descuentos_dia, $descuentos_ayer);

// Helper para badge HTML
function badge_tendencia(array $pct, string $label = 'vs. ayer'): string
{
    $color = $pct['subio'] ? 'text-emerald-600 bg-emerald-50' : 'text-rose-600 bg-rose-50';
    $icono = $pct['subio'] ? 'fa-arrow-up' : 'fa-arrow-down';
    return sprintf(
        '<div class="mt-3 flex items-center gap-1.5 text-xs font-medium %s px-2.5 py-1 rounded-full w-fit">
            <i class="fa-solid %s text-[0.6rem]"></i> %s%% %s
        </div>',
        $color,
        $icono,
        number_format($pct['absoluto'], 1),
        htmlspecialchars($label)
    );
}

// -------------------------------------------------------------------
// GRÁFICO: Ventas de los últimos 7 días
// -------------------------------------------------------------------
$dias_semana = [];
$ventas_semanales = [];
$fechas_labels = [];

// Creamos el formateador para español
$formatter = new IntlDateFormatter(
    'es_ES',
    IntlDateFormatter::SHORT,
    IntlDateFormatter::NONE,
    date_default_timezone_get(),
    IntlDateFormatter::GREGORIAN,
    'EEE' // Formato corto: Lun, Mar...
);

for ($i = 6; $i >= 0; $i--) {
    $ts = strtotime("-$i days");
    $fechas_labels[] = date('Y-m-d', $ts);

    // Usamos el formateador en lugar de strftime
    $diaNombre = $formatter->format($ts);
    $dias_semana[] = ucfirst(str_replace('.', '', $diaNombre)); // Quita puntos si los hay (ej: mar.)

    $ventas_semanales[(int) date('d', $ts)] = 0.0;
}
try {
    $stmt = $db->prepare("SELECT DAY(fecha) AS dia, COALESCE(SUM(total),0) AS total
                          FROM ventas
                          WHERE fecha >= CURDATE() - INTERVAL 6 DAY
                          GROUP BY DAY(fecha) ORDER BY DAY(fecha) ASC");
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $ventas_semanales[(int) $row['dia']] = (float) $row['total'];
    }
} catch (Throwable $e) {
}
$ventas_semanales_ordered = [];
foreach ($fechas_labels as $f) {
    $dia = (int) date('d', strtotime($f));
    $ventas_semanales_ordered[] = $ventas_semanales[$dia] ?? 0;
}

// -------------------------------------------------------------------
// GRÁFICO: Métodos de pago del día
// -------------------------------------------------------------------
$metodos_pago_raw = [];
try {
    $stmt = $db->prepare("SELECT metodo_pago, COUNT(*) AS cantidad
                          FROM ventas WHERE DATE(fecha) = CURDATE()
                          GROUP BY metodo_pago");
    $stmt->execute();
    $metodos_pago_raw = $stmt->fetchAll();
} catch (Throwable $e) {
}
// Mapear valores reales de la BD (efectivo, qr, mixto) a etiquetas legibles
$mapa_metodos = [
    'efectivo' => 'Efectivo',
    'qr' => 'QR',
    'mixto' => 'Mixto',
    'tarjeta' => 'Tarjeta',
];
$metodos_pago = [];
foreach ($metodos_pago_raw as $row) {
    $label = $mapa_metodos[$row['metodo_pago']] ?? ucfirst($row['metodo_pago']);
    $metodos_pago[$label] = (int) $row['cantidad'];
}
if (!$metodos_pago)
    $metodos_pago = ['Efectivo' => 0];

// -------------------------------------------------------------------
// TABLA: Últimas 5 ventas
// -------------------------------------------------------------------
$ventas_recientes = [];
try {
    $stmt = $db->prepare("SELECT v.id, v.total, v.metodo_pago,
                                 CASE WHEN v.estado_pago = 'cobrado' THEN 'Completado'
                                      WHEN v.estado_pago = 'pendiente' THEN 'Pendiente'
                                      ELSE v.estado_pago END AS estado,
                                 DATE_FORMAT(v.fecha, '%h:%i %p') AS hora,
                                 COALESCE(c.nombre, 'Mostrador') AS cliente
                          FROM ventas v
                          LEFT JOIN clientes c ON v.cliente_id = c.id
                          ORDER BY v.fecha DESC LIMIT 5");
    $stmt->execute();
    $ventas_recientes = $stmt->fetchAll();
} catch (Throwable $e) {
}

// -------------------------------------------------------------------
// TABLA: 5 productos con stock más crítico
// -------------------------------------------------------------------
$productos_criticos = [];
try {
    $stmt = $db->prepare("SELECT nombre, stock_actual, stock_minimo
                          FROM productos
                          WHERE estado = 1 AND stock_actual <= stock_minimo
                          ORDER BY (stock_actual - stock_minimo) ASC, stock_actual ASC
                          LIMIT 5");
    $stmt->execute();
    $productos_criticos = $stmt->fetchAll();
} catch (Throwable $e) {
}

// -------------------------------------------------------------------
// Variables para el layout original
// -------------------------------------------------------------------
$pageTitle = 'Dashboard - ' . $config['nombre_negocio'];
$active = 'dashboard';

// FontAwesome vía extraCss (se inyecta en <head> por header.php)
$extraCss = ['https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css'];

require_once __DIR__ . '/layout/header.php';
$baseUrl = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') . '/' : '/';
?>

<div class="app-shell">

    <?php require_once __DIR__ . '/layout/sidebar.php'; ?>

    <main class="content">

        <!-- ============================================================ -->
        <!-- TOPBAR (Título + acciones)                                    -->
        <!-- ============================================================ -->
        <div class="topbar mb-4">
            <div>
                <h1 class="fw-bold mb-0 text-xl md:text-2xl" style="color:#1a202c;">Dashboard</h1>
                <small class="text-muted"><?= htmlspecialchars($config['nombre_negocio']) ?> ·
                    <?= date('d/m/Y') ?></small>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-white shadow-sm" href="<?= $baseUrl ?>views/modules/inventario.php">
                    <i class="fa-solid fa-box"></i> Ver Stock
                </a>
                <a class="btn btn-primary shadow-sm" href="<?= $baseUrl ?>views/modules/pos.php">
                    <i class="fa-solid fa-plus"></i> Nueva Venta
                </a>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- KPI CARDS (grid responsivo con Tailwind)                      -->
        <!-- ============================================================ -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-4">

            <div
                class="kpi-card bg-white rounded-2xl shadow-sm border border-gray-100 p-5 md:p-6 hover:-translate-y-1 hover:shadow-lg transition-all duration-300 cursor-default">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Ventas del Día</p>
                        <p class="text-xl md:text-2xl font-extrabold text-gray-800 mt-1 tracking-tight"><?= $moneda ?>
                            <?= number_format($ventas_dia, 2) ?>
                        </p>
                    </div>
                    <div
                        class="w-11 h-11 md:w-12 md:h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg md:text-xl">
                        <i class="fa-solid fa-dollar-sign"></i>
                    </div>
                </div>
                <?= badge_tendencia($pct_ventas) ?>
            </div>

            <div
                class="kpi-card bg-white rounded-2xl shadow-sm border border-gray-100 p-5 md:p-6 hover:-translate-y-1 hover:shadow-lg transition-all duration-300 cursor-default">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Transacciones</p>
                        <p class="text-xl md:text-2xl font-extrabold text-gray-800 mt-1 tracking-tight">
                            <?= number_format($transacciones_total) ?>
                        </p>
                    </div>
                    <div
                        class="w-11 h-11 md:w-12 md:h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-lg md:text-xl">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                </div>
                <?= badge_tendencia($pct_transacciones) ?>
            </div>

            <div
                class="kpi-card bg-white rounded-2xl shadow-sm border border-gray-100 p-5 md:p-6 hover:-translate-y-1 hover:shadow-lg transition-all duration-300 cursor-default">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Ticket Promedio</p>
                        <p class="text-xl md:text-2xl font-extrabold text-gray-800 mt-1 tracking-tight"><?= $moneda ?>
                            <?= number_format($ticket_promedio, 2) ?>
                        </p>
                    </div>
                    <div
                        class="w-11 h-11 md:w-12 md:h-12 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center text-lg md:text-xl">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </div>
                </div>
                <?= badge_tendencia($pct_ticket) ?>
            </div>

            <div
                class="kpi-card bg-white rounded-2xl shadow-sm border border-gray-100 p-5 md:p-6 hover:-translate-y-1 hover:shadow-lg transition-all duration-300 cursor-default">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Ganancia Neta</p>
                        <p class="text-xl md:text-2xl font-extrabold text-gray-800 mt-1 tracking-tight"><?= $moneda ?>
                            <?= number_format($ganancia_neta, 2) ?>
                        </p>
                    </div>
                    <div
                        class="w-11 h-11 md:w-12 md:h-12 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-lg md:text-xl">
                        <i class="fa-solid fa-coins"></i>
                    </div>
                </div>
                <?= badge_tendencia($pct_ganancia) ?>
            </div>

            <div
                class="kpi-card bg-white rounded-2xl shadow-sm border border-gray-100 p-5 md:p-6 hover:-translate-y-1 hover:shadow-lg transition-all duration-300 cursor-default">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Descuentos Otorgados</p>
                        <p class="text-xl md:text-2xl font-extrabold text-rose-600 mt-1 tracking-tight"><?= $moneda ?>
                            <?= number_format($descuentos_dia, 2) ?>
                        </p>
                    </div>
                    <div
                        class="w-11 h-11 md:w-12 md:h-12 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center text-lg md:text-xl">
                        <i class="fa-solid fa-tags"></i>
                    </div>
                </div>
                <?= badge_tendencia($pct_descuentos) ?>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- GRÁFICOS (2 columnas)                                         -->
        <!-- ============================================================ -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">

            <!-- Col A: Ventas semanales -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 md:p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                            <i class="fa-solid fa-chart-line text-emerald-500"></i> Ventas de la Semana
                        </h2>
                        <p class="text-xs text-gray-400 mt-0.5">Últimos 7 días</p>
                    </div>
                    <span class="text-xs font-medium text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full">
                        +<?= $transacciones_total ?> ventas
                    </span>
                </div>
                <div class="relative" style="height:300px;">
                    <canvas id="graficoVentas"></canvas>
                </div>
            </div>

            <!-- Col B: Métodos de pago -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 md:p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                            <i class="fa-solid fa-chart-pie text-blue-500"></i> Distribución de Pagos
                        </h2>
                        <p class="text-xs text-gray-400 mt-0.5">Métodos utilizados hoy</p>
                    </div>
                </div>
                <div class="relative flex items-center justify-center" style="height:300px;">
                    <canvas id="graficoPagos"></canvas>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TABLA + ALERTAS (2 columnas asimétricas)                      -->
        <!-- ============================================================ -->
        <div class="grid grid-cols-1 xl:grid-cols-5 gap-4">

            <!-- Ventas Recientes (3/5) -->
            <div class="xl:col-span-3 bg-white rounded-2xl shadow-sm border border-gray-100 p-5 md:p-6 overflow-x-auto">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                        <i class="fa-regular fa-clock text-indigo-500"></i> Ventas Recientes
                    </h2>
                    <a href="<?= $baseUrl ?>views/modules/historial_ventas.php"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-800 transition-colors">
                        Ver todo <i class="fa-solid fa-arrow-right ml-1 text-[0.6rem]"></i>
                    </a>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-400 uppercase text-xs tracking-wider border-b border-gray-100">
                            <th class="pb-3 font-semibold">Folio</th>
                            <th class="pb-3 font-semibold">Cliente</th>
                            <th class="pb-3 font-semibold">Total</th>
                            <th class="pb-3 font-semibold hidden sm:table-cell">Método</th>
                            <th class="pb-3 font-semibold">Estado</th>
                            <th class="pb-3 font-semibold hidden sm:table-cell">Hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$ventas_recientes): ?>
                            <tr>
                                <td colspan="6" class="py-8 text-center text-gray-400 text-sm">No hay ventas registradas
                                    hoy.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ventas_recientes as $v): ?>
                                <tr class="border-b border-gray-50 hover:bg-gray-50 transition-colors">
                                    <td class="py-3 font-mono text-xs text-gray-500">
                                        #<?= str_pad((string) ($v['id'] ?? ''), 5, '0', STR_PAD_LEFT) ?></td>
                                    <td class="py-3 text-gray-700 font-medium">
                                        <?= htmlspecialchars($v['cliente'] ?? 'Mostrador') ?>
                                    </td>
                                    <td class="py-3 font-semibold text-gray-800"><?= $moneda ?>
                                        <?= number_format((float) ($v['total'] ?? 0), 2) ?>
                                    </td>
                                    <td class="py-3 hidden sm:table-cell">
                                        <?php
                                        $icono = match ($v['metodo_pago'] ?? '') {
                                            'Efectivo' => '<i class="fa-solid fa-money-bill-wave text-emerald-500"></i>',
                                            'QR' => '<i class="fa-solid fa-qrcode text-blue-500"></i>',
                                            'Tarjeta' => '<i class="fa-regular fa-credit-card text-purple-500"></i>',
                                            default => '<i class="fa-solid fa-circle text-gray-300"></i>',
                                        };
                                        ?>
                                        <span class="inline-flex items-center gap-1.5 text-xs text-gray-500">
                                            <?= $icono ?>         <?= htmlspecialchars($v['metodo_pago'] ?? '') ?>
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <?php
                                        $estado = $v['estado'] ?? 'Pendiente';
                                        $badge = match ($estado) {
                                            'Completado' => 'bg-emerald-100 text-emerald-700',
                                            'Pendiente' => 'bg-amber-100 text-amber-700',
                                            'Cancelado' => 'bg-red-100 text-red-700',
                                            default => 'bg-gray-100 text-gray-600',
                                        };
                                        ?>
                                        <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold <?= $badge ?>">
                                            <?= htmlspecialchars($estado) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 hidden sm:table-cell text-xs text-gray-400">
                                        <?= htmlspecialchars($v['hora'] ?? '') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Alertas de Inventario (2/5) -->
            <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-5 md:p-6">
                <div class="flex items-center justify-between mb-1">
                    <h2 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-red-500"></i> Alertas de Inventario
                    </h2>
                    <span class="text-xs font-bold text-red-600 bg-red-50 px-2.5 py-1 rounded-full">
                        <?= count($productos_criticos) ?> alertas
                    </span>
                </div>
                <p class="text-xs text-gray-400 mb-4">Productos con stock por debajo del mínimo</p>

                <?php if (!$productos_criticos): ?>
                    <div class="flex flex-col items-center justify-center py-8 text-gray-400">
                        <i class="fa-solid fa-check-circle text-3xl text-emerald-400 mb-2"></i>
                        <p class="text-sm font-medium">Todo el stock está al día</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($productos_criticos as $p): ?>
                            <?php
                            $stock = (int) ($p['stock_actual'] ?? 0);
                            $minimo = (int) ($p['stock_minimo'] ?? 5);
                            $pct = $minimo > 0 ? min(round(($stock / $minimo) * 100), 100) : 0;
                            $critico = $stock <= 2;
                            ?>
                            <div
                                class="flex items-center gap-3 p-3 rounded-xl <?= $critico ? 'bg-red-50 ring-1 ring-red-200' : 'bg-orange-50 ring-1 ring-orange-200' ?>">
                                <div
                                    class="w-9 h-9 rounded-lg <?= $critico ? 'bg-red-200 text-red-700' : 'bg-orange-200 text-orange-700' ?> flex items-center justify-center flex-shrink-0">
                                    <i class="fa-solid fa-cube text-sm"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-800 truncate">
                                        <?= htmlspecialchars($p['nombre'] ?? '') ?>
                                    </p>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-xs font-bold <?= $critico ? 'text-red-600' : 'text-orange-600' ?>">
                                            Stock: <?= $stock ?>
                                        </span>
                                        <span class="text-xs text-gray-400">/ mín. <?= $minimo ?></span>
                                    </div>
                                    <div class="w-full h-1.5 rounded-full bg-gray-200 mt-1.5 overflow-hidden">
                                        <div class="h-full rounded-full <?= $critico ? 'bg-red-400' : 'bg-orange-400' ?>"
                                            style="width:<?= $pct ?>%"></div>
                                    </div>
                                </div>
                                <span
                                    class="text-xs font-extrabold <?= $critico ? 'text-red-600' : 'text-orange-600' ?> flex-shrink-0"><?= $pct ?>%</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="mt-4 pt-3 border-t border-gray-100">
                    <a href="<?= $baseUrl ?>views/modules/inventario.php"
                        class="text-xs font-medium text-gray-500 hover:text-gray-800 transition-colors flex items-center gap-1.5">
                        <i class="fa-solid fa-box"></i> Ir a Inventario
                        <i class="fa-solid fa-arrow-right ml-auto text-[0.6rem]"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- CDNs + Chart.js init (carga síncrona dentro del body)         -->
        <!-- ============================================================ -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            (function () {
                'use strict';

                const ventasData = <?= json_encode($ventas_semanales_ordered) ?>;
                const etiquetas = <?= json_encode($dias_semana) ?>;
                const pagosValores = <?= json_encode(array_values($metodos_pago)) ?>;
                const pagosLabels = <?= json_encode(array_keys($metodos_pago)) ?>;

                // Gráfico de líneas
                const ctxV = document.getElementById('graficoVentas');
                if (ctxV) {
                    new Chart(ctxV, {
                        type: 'line',
                        data: {
                            labels: etiquetas,
                            datasets: [{
                                label: 'Ventas (<?= $moneda ?>)',
                                data: ventasData,
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16,185,129,0.08)',
                                borderWidth: 3,
                                pointBackgroundColor: '#10b981',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 5,
                                pointHoverRadius: 8,
                                tension: 0.4,
                                fill: true,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            animation: { duration: 1200, easing: 'easeOutQuart' },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: '#13243d',
                                    titleColor: '#fff',
                                    bodyColor: '#e2e8f0',
                                    padding: 12,
                                    cornerRadius: 8,
                                    callbacks: {
                                        label: function (ctx) {
                                            return '<?= $moneda ?> ' + ctx.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 });
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { color: 'rgba(0,0,0,0.04)' },
                                    ticks: { callback: function (v) { return '<?= $moneda ?>' + v.toLocaleString(); } }
                                },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                }

                // Gráfico de dona
                const ctxP = document.getElementById('graficoPagos');
                if (ctxP) {
                    new Chart(ctxP, {
                        type: 'doughnut',
                        data: {
                            labels: pagosLabels,
                            datasets: [{
                                data: pagosValores,
                                backgroundColor: ['#10b981', '#3b82f6', '#8b5cf6'],
                                borderWidth: 0,
                                hoverOffset: 14,
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '70%',
                            animation: { animateRotate: true, duration: 1000, easing: 'easeOutCirc' },
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { padding: 16, usePointStyle: true, pointStyle: 'circle', font: { size: 12 } }
                                },
                                tooltip: {
                                    backgroundColor: '#13243d',
                                    padding: 12,
                                    cornerRadius: 8,
                                    callbacks: {
                                        label: function (ctx) {
                                            const total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                            const pct = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                            return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            })();
        </script>

        <style>
            /* Animación fade-in para tarjetas KPI */
            .kpi-card {
                animation: fadeInUp 0.5s ease-out both;
            }

            .kpi-card:nth-child(2) {
                animation-delay: 0.1s;
            }

            .kpi-card:nth-child(3) {
                animation-delay: 0.2s;
            }

            .kpi-card:nth-child(4) {
                animation-delay: 0.3s;
            }

            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(20px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        </style>

    </main>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>