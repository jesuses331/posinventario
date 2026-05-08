<?php

class Categoria {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function countAll(string $search = ''): int {
        if ($search === '') {
            $stmt = $this->db->query("SELECT COUNT(*) AS c FROM categorias WHERE estado = 1");
            return (int)($stmt->fetch()['c'] ?? 0);
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS c
            FROM categorias
            WHERE estado = 1 AND (LOWER(COALESCE(nombre, '')) LIKE :q1)
        ");
        $q = '%' . mb_strtolower($search, 'UTF-8') . '%';
        $stmt->execute([':q1' => $q]);
        return (int)($stmt->fetch()['c'] ?? 0);
    }

    public function listDataTables(int $start, int $length, string $search, string $orderBy, string $orderDir): array {
        $allowedOrder = [
            'id' => 'id',
            'nombre' => 'nombre',
            'descripcion' => 'descripcion',
            'created_at' => 'created_at',
        ];
        $orderCol = $allowedOrder[$orderBy] ?? 'id';
        $dir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $start = max(0, $start);
        $length = max(10, min(200, $length));

        $params = [];
        $where = "WHERE estado = 1";
        if ($search !== '') {
            $where .= " AND (nombre LIKE :q1 OR descripcion LIKE :q2)";
            $q = '%' . $search . '%';
            $params[':q1'] = $q;
            $params[':q2'] = $q;
        }

        $sql = "
            SELECT id, nombre, descripcion, estado, created_at
            FROM categorias
            $where
            ORDER BY $orderCol $dir
            LIMIT :limit OFFSET :offset
        ";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $length, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $start, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM categorias WHERE id = :id AND estado = 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getAll(): array {
        $stmt = $this->db->query("SELECT id, nombre, descripcion FROM categorias WHERE estado = 1 ORDER BY nombre ASC");
        return $stmt->fetchAll() ?: [];
    }

    public function create(string $nombre, ?string $descripcion = null): int {
        $stmt = $this->db->prepare("
            INSERT INTO categorias (nombre, descripcion, estado)
            VALUES (:nombre, :descripcion, 1)
        ");
        $stmt->execute([
            ':nombre' => $nombre,
            ':descripcion' => $descripcion,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, string $nombre, ?string $descripcion = null): bool {
        $stmt = $this->db->prepare("
            UPDATE categorias
            SET nombre = :nombre, descripcion = :descripcion
            WHERE id = :id
        ");
        return $stmt->execute([
            ':id' => $id,
            ':nombre' => $nombre,
            ':descripcion' => $descripcion,
        ]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("UPDATE categorias SET estado = 0 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
