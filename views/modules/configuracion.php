<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Auth.php';
Auth::checkAccess('admin');

$pageTitle = 'Configuración - AbdiSoft CORE';
$active = 'config';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') . '/' : '/';

$extraJs = [
    'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js',
];
require_once __DIR__ . '/../layout/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="content">
        <div class="card p-4" style="max-width:640px;">
            <h1 class="h4 title-strong mb-3">Configuración General</h1>
            <form id="formCfg">
                <input type="hidden" name="id" id="c_id">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                <div class="mb-4">
                    <label class="form-label form-label-compact d-block">Logo del sistema</label>
                    <div class="d-flex align-items-center gap-3">
                        <div id="logoPreview" class="logo-preview-box d-flex align-items-center justify-content-center border rounded bg-light" style="width: 80px; height: 80px; overflow: hidden;">
                            <span class="text-muted small">Cargando...</span>
                        </div>
                        <div class="flex-grow-1">
                            <input type="file" class="form-control themed-input" id="c_logo" name="logo" accept="image/*">
                            <div class="text-muted small mt-1">PNG, JPG o SVG. Recomendado 200x200px.</div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label form-label-compact">Nombre del negocio</label>
                    <input class="form-control themed-input" id="c_nombre" name="nombre_negocio" required>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label form-label-compact">Moneda</label>
                        <input class="form-control themed-input" id="c_moneda" name="moneda" value="Bs">
                    </div>
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="c_usa_v" name="usa_vencimientos" value="1">
                            <label class="form-check-label text-muted" for="c_usa_v">
                                Usar control de vencimientos
                            </label>
                        </div>
                    </div>
                </div>

                <?php if (($_SESSION['user_usuario'] ?? '') === 'desarrollador'): ?>
                <div class="mb-3">
                    <label class="form-label form-label-compact">Estado del Sistema (Plan)</label>
                    <select class="form-select themed-input" id="c_plan" name="plan_sistema">
                        <option value="demo">Demo</option>
                        <option value="mensual">Mensual</option>
                        <option value="trimestral">Trimestral</option>
                        <option value="semestral">Semestral</option>
                        <option value="anual">Anual</option>
                    </select>
                    <div class="text-muted small mt-1">Este ajuste solo es visible para el desarrollador.</div>
                </div>
                <?php endif; ?>

                <div class="alert alert-danger d-none" id="cfgError" style="background: rgba(220,53,69,.1); border-color: #dc3545; color:#ffb3b3;"></div>
                <div class="alert alert-success d-none" id="cfgOk" style="background: rgba(25,135,84,.1); border-color: #198754; color:#9be7c4;"></div>

                <button class="btn btn-primary" type="submit" id="btnGuardarCfg">Guardar cambios</button>
            </form>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

<script>
(() => {
    const baseUrl = <?php echo json_encode($baseUrl, JSON_UNESCAPED_SLASHES); ?>;
    const apiUrl = baseUrl + 'views/modules/configuracion_api.php';
    const csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES); ?>;

    const form = document.getElementById('formCfg');
    const eId = document.getElementById('c_id');
    const eNombre = document.getElementById('c_nombre');
    const eMoneda = document.getElementById('c_moneda');
    const eUsaV = document.getElementById('c_usa_v');
    const ePlan = document.getElementById('c_plan');
    const err = document.getElementById('cfgError');
    const ok = document.getElementById('cfgOk');

    function showErr(msg) {
        err.textContent = msg || 'Error';
        err.classList.remove('d-none');
        ok.classList.add('d-none');
    }
    function showOk(msg) {
        ok.textContent = msg || 'Guardado';
        ok.classList.remove('d-none');
        err.classList.add('d-none');
    }
    function clearAlerts() {
        err.classList.add('d-none');
        ok.classList.add('d-none');
    }

    async function cargar() {
        clearAlerts();
        try {
            const res = await fetch(apiUrl + '?action=get', { credentials: 'same-origin' });
            const json = await res.json();
            if (!json.ok) throw new Error(json.message || 'Error cargando');
            const c = json.data;
            if (eId) eId.value = c.id ?? '';
            if (eNombre) eNombre.value = c.nombre_negocio ?? '';
            if (eMoneda) eMoneda.value = c.moneda ?? 'Bs';
            if (eUsaV) eUsaV.checked = (c.usa_vencimientos ?? 1) == 1;
            if (ePlan) ePlan.value = c.plan_sistema ?? 'demo';

            const logoPreview = document.getElementById('logoPreview');
            if (c.logo_path) {
                logoPreview.innerHTML = `<img src="${baseUrl}${c.logo_path}" style="width: 100%; height: 100%; object-fit: contain;">`;
            } else {
                logoPreview.innerHTML = `<span class="text-muted small">Sin logo</span>`;
            }
        } catch (e) {
            showErr(e.message || 'Error cargando configuración');
        }
    }

    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        clearAlerts();
        const fd = new FormData(form);
        if (eUsaV && eUsaV.checked) fd.set('usa_vencimientos', '1');
        else fd.set('usa_vencimientos', '0');
        fd.append('action', 'save');

        const btn = document.getElementById('btnGuardarCfg');
        btn.disabled = true;
        btn.textContent = 'Guardando...';

        try {
            const res = await fetch(apiUrl, {
                method: 'POST',
                body: fd,
                headers: { 'X-CSRF-Token': csrfToken },
                credentials: 'same-origin'
            });
            const json = await res.json();
            if (!json.ok) throw new Error(json.message || 'Error guardando');
            showOk(json.message || 'Configuración guardada');
            if (json.logo_path) {
                document.getElementById('logoPreview').innerHTML = `<img src="${baseUrl}${json.logo_path}" style="width: 100%; height: 100%; object-fit: contain;">`;
            }
        } catch (e) {
            showErr(e.message || 'Error guardando');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Guardar cambios';
        }
    });

    cargar();
})();
</script>
