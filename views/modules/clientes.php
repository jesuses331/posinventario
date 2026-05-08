<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';
Auth::checkAccess();

$pageTitle = 'Clientes - AbdiSoft CORE';
$active = 'clientes';
$baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') . '/' : '/Filacell/';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

require_once __DIR__ . '/../layout/header.php';
?>

<div class="app-shell">
    <?php require_once __DIR__ . '/../layout/sidebar.php'; ?>
    <main class="content">
        <div class="topbar">
            <div>
                <h1 class="h4 title-strong mb-1">Gestión de Clientes</h1>
                <div class="text-muted" style="font-size:.92rem">Crear, editar y gestionar clientes.</div>
            </div>
            <button class="btn btn-primary" id="btnNuevo" type="button">+ Nuevo Cliente</button>
        </div>

        <div class="card p-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="clientesTable" style="width:100%">
                    <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Teléfono</th>
                        <th>Fecha Registro</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal Nuevo/Editar Cliente -->
<div class="modal fade modal-themed" id="modalCliente" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nuevo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formCliente">
                <div class="modal-body">
                    <input type="hidden" id="clienteId" value="">
                    
                    <div class="mb-3">
                        <label class="form-label form-label-compact">Nombre Completo *</label>
                        <input type="text" class="form-control themed-input" id="nombre" placeholder="Ej: Juan Pérez" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label form-label-compact">Teléfono de Contacto</label>
                        <input type="text" class="form-control themed-input" id="telefono" placeholder="Ej: +58 412 1234567">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-theme-outline" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Cliente</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../layout/footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
(() => {
    const baseUrl = <?php echo json_encode($baseUrl, JSON_UNESCAPED_SLASHES); ?>;
    const apiUrl = baseUrl + 'views/modules/clientes_api.php';
    const csrfToken = <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES); ?>;

    let table;

    function initTable() {
        table = new DataTable('#clientesTable', {
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: apiUrl + '?action=list_datatables',
                type: 'GET',
            },
            columns: [
                { data: 'nombre', className: 'fw-bold text-heading' },
                { data: 'telefono' },
                { data: 'created_at', render: (v) => v ? new Date(v).toLocaleDateString() : '—' },
                {
                    data: null,
                    orderable: false,
                    className: 'text-end',
                    render: (data, type, row) => {
                        return `
                            <div class="d-flex gap-2 justify-content-end">
                                <button class="btn btn-sm btn-theme-outline edit-btn" data-id="${row.id}"><i class="bi bi-pencil"></i></button>
                                <button class="btn btn-sm btn-theme-outline text-danger delete-btn" data-id="${row.id}"><i class="bi bi-trash"></i></button>
                            </div>
                        `;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json',
            },
            pageLength: 10
        });
    }

    function resetForm() {
        document.getElementById('clienteId').value = '';
        document.getElementById('formCliente').reset();
        document.getElementById('modalTitle').textContent = 'Nuevo Cliente';
    }

    async function submitForm(e) {
        e.preventDefault();
        const clienteId = document.getElementById('clienteId').value;
        const nombre = document.getElementById('nombre').value.trim();
        const telefono = document.getElementById('telefono').value.trim();

        if (!nombre) return;

        const action = clienteId ? 'update' : 'create';
        const formData = new FormData();
        formData.append('action', action);
        formData.append('csrf_token', csrfToken);
        formData.append('nombre', nombre);
        formData.append('telefono', telefono);
        if (clienteId) formData.append('id', clienteId);

        try {
            const res = await fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });
            const json = await res.json();
            if (!json.ok) throw new Error(json.message || 'Error');

            bootstrap.Modal.getInstance(document.getElementById('modalCliente')).hide();
            table.ajax.reload(null, false);
            resetForm();
        } catch (e) {
            alert(e.message || 'Error');
        }
    }

    async function editCliente(id) {
        try {
            const res = await fetch(apiUrl + '?action=get&id=' + id);
            const json = await res.json();
            if (!json.ok) throw new Error(json.message || 'Error');

            const cliente = json.data;
            document.getElementById('clienteId').value = cliente.id;
            document.getElementById('nombre').value = cliente.nombre;
            document.getElementById('telefono').value = cliente.telefono || '';
            document.getElementById('modalTitle').textContent = 'Editar Cliente';

            new bootstrap.Modal(document.getElementById('modalCliente')).show();
        } catch (e) {
            alert(e.message || 'Error');
        }
    }

    async function deleteCliente(id) {
        if (!confirm('¿Estás seguro de que deseas eliminar este cliente?')) return;

        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('csrf_token', csrfToken);
        formData.append('id', id);

        try {
            const res = await fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            });
            const json = await res.json();
            if (!json.ok) throw new Error(json.message || 'Error');
            table.ajax.reload(null, false);
        } catch (e) {
            alert(e.message || 'Error');
        }
    }

    if (typeof jQuery !== 'undefined') initTable();
    else document.addEventListener('DOMContentLoaded', initTable);

    document.getElementById('btnNuevo').addEventListener('click', () => {
        resetForm();
        new bootstrap.Modal(document.getElementById('modalCliente')).show();
    });

    document.getElementById('formCliente').addEventListener('submit', submitForm);

    document.addEventListener('click', (e) => {
        const btnEdit = e.target.closest('.edit-btn');
        const btnDelete = e.target.closest('.delete-btn');
        if (btnEdit) editCliente(btnEdit.getAttribute('data-id'));
        if (btnDelete) deleteCliente(btnDelete.getAttribute('data-id'));
    });
})();
</script>
