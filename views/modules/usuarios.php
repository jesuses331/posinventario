<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Auth.php';
Auth::checkAccess('admin');

$pageTitle = 'Usuarios - AbdiSoft CORE';
$active = 'usuarios';
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
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="h4 title-strong mb-1">Usuarios</h1>
                <div class="text-muted" style="font-size:.92rem">Administra cuentas, roles y estado.</div>
            </div>
            <button class="btn btn-primary" id="btnNuevo" type="button">Nuevo Usuario</button>
        </div>

        <div class="card p-3">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" id="tablaUsuarios">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Usuario</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

<!-- Modal Usuario -->
<div class="modal fade modal-themed" id="modalUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formUsuario">
                <div class="modal-body">
                    <input type="hidden" name="id" id="u_id" value="0">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

                    <div class="mb-2">
                        <label class="form-label form-label-compact">Nombre</label>
                        <input class="form-control themed-input" name="nombre" id="u_nombre" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-compact">Usuario</label>
                        <input class="form-control themed-input" name="usuario" id="u_usuario" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-compact">Email</label>
                        <input type="email" class="form-control themed-input" name="email" id="u_email" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label form-label-compact">Rol</label>
                            <select class="form-select themed-input" name="rol" id="u_rol">
                                <option value="cajero">Cajero</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label form-label-compact">Estado</label>
                            <select class="form-select themed-input" name="estado" id="u_estado">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label form-label-compact">Contraseña</label>
                        <input type="password" class="form-control themed-input" name="password" id="u_password" placeholder="Dejar vacío para no cambiar">
                    </div>

                    <div class="alert alert-danger mt-3 d-none" id="userError" style="background: rgba(220,53,69,.1); border-color: #dc3545; color:#ffb3b3;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-theme-outline" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardar">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(() => {
    const baseUrl = <?php echo json_encode($baseUrl, JSON_UNESCAPED_SLASHES); ?>;
    const apiUrl = baseUrl + 'views/modules/usuarios_api.php';
    const csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES); ?>;

    const tbody = document.querySelector('#tablaUsuarios tbody');
    const modalEl = document.getElementById('modalUsuario');
    const modal = new bootstrap.Modal(modalEl);
    const form = document.getElementById('formUsuario');
    const errorBox = document.getElementById('userError');

    function escapeHtml(str) {
        return String(str ?? '')
            .replaceAll('&','&amp;')
            .replaceAll('<','&lt;')
            .replaceAll('>','&gt;')
            .replaceAll('"','&quot;')
            .replaceAll("'","&#039;");
    }
    function showError(msg) {
        errorBox.textContent = msg || 'Error';
        errorBox.classList.remove('d-none');
    }
    function clearError() {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
    }

    async function cargarLista() {
        tbody.innerHTML = '<tr><td colspan="9" class="text-muted">Cargando...</td></tr>';
        try {
            const res = await fetch(apiUrl + '?action=list', { credentials: 'same-origin' });
            const json = await res.json();
            if (!json.ok) throw new Error(json.message || 'Error cargando');
            const rows = json.data || [];
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-muted">Sin usuarios.</td></tr>';
                return;
            }
            tbody.innerHTML = '';
            for (const u of rows) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(u.id)}</td>
                    <td>${escapeHtml(u.nombre)}</td>
                    <td>${escapeHtml(u.usuario)}</td>
                    <td>${escapeHtml(u.email)}</td>
                    <td>${escapeHtml(u.rol)}</td>
                    <td>${u.estado == 1 ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>'}</td>
                    <td>${escapeHtml(u.created_at ?? '')}</td>
                    <td><button class="btn btn-sm btn-theme-outline btn-edit" data-id="${escapeHtml(u.id)}">Editar</button></td>
                `;
                tr.querySelector('.btn-edit').addEventListener('click', () => editar(u.id));
                tbody.appendChild(tr);
            }
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-danger">Error cargando usuarios.</td></tr>';
        }
    }

    async function editar(id) {
        clearError();
        document.getElementById('modalTitle').textContent = 'Editar Usuario #' + id;
        try {
            const res = await fetch(apiUrl + '?action=get&id=' + encodeURIComponent(id), { credentials: 'same-origin' });
            const json = await res.json();
            if (!json.ok) throw new Error(json.message || 'Error');
            const u = json.data;
            document.getElementById('u_id').value = u.id;
            document.getElementById('u_nombre').value = u.nombre;
            document.getElementById('u_usuario').value = u.usuario;
            document.getElementById('u_email').value = u.email;
            document.getElementById('u_rol').value = u.rol;
            document.getElementById('u_estado').value = String(u.estado);
            document.getElementById('u_password').value = '';
            modal.show();
        } catch (e) {
            alert(e.message || 'Error');
        }
    }

    document.getElementById('btnNuevo').addEventListener('click', () => {
        clearError();
        document.getElementById('modalTitle').textContent = 'Nuevo Usuario';
        document.getElementById('u_id').value = 0;
        document.getElementById('u_nombre').value = '';
        document.getElementById('u_usuario').value = '';
        document.getElementById('u_email').value = '';
        document.getElementById('u_rol').value = 'cajero';
        document.getElementById('u_estado').value = '1';
        document.getElementById('u_password').value = '';
        modal.show();
    });

    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        clearError();
        const fd = new FormData(form);
        fd.append('action', 'save');

        const btn = document.getElementById('btnGuardar');
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
            modal.hide();
            await cargarLista();
        } catch (e) {
            showError(e.message || 'Error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Guardar';
        }
    });

    cargarLista();
})();
</script>


