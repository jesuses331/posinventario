<?php

class Usuario
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listAll(): array
    {
        $stmt = $this->db->query("
            SELECT id, nombre, usuario, email, rol, estado, created_at
            FROM usuarios
            ORDER BY id DESC
        ");
        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, nombre, usuario, email, rol, estado
            FROM usuarios
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM usuarios WHERE email = :email";
        $params = [':email' => $email];
        if ($excludeId) {
            $sql .= " AND id <> :id";
            $params[':id'] = $excludeId;
        }
        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public function usuarioExists(string $usuario, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM usuarios WHERE usuario = :usuario";
        $params = [':usuario' => $usuario];
        if ($excludeId) {
            $sql .= " AND id <> :id";
            $params[':id'] = $excludeId;
        }
        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO usuarios (nombre, usuario, email, password_hash, rol, estado)
            VALUES (:nombre, :usuario, :email, :password_hash, :rol, :estado)
        ");
        $stmt->execute([
            ':nombre' => $data['nombre'],
            ':usuario' => $data['usuario'],
            ':email' => $data['email'],
            ':password_hash' => $data['password_hash'],
            ':rol' => $data['rol'],
            ':estado' => $data['estado'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [
            'nombre = :nombre',
            'usuario = :usuario',
            'email = :email',
            'rol = :rol',
            'estado = :estado',
        ];
        $params = [
            ':id' => $id,
            ':nombre' => $data['nombre'],
            ':usuario' => $data['usuario'],
            ':email' => $data['email'],
            ':rol' => $data['rol'],
            ':estado' => $data['estado'],
        ];
        if (!empty($data['password_hash'])) {
            $fields[] = 'password_hash = :password_hash';
            $params[':password_hash'] = $data['password_hash'];
        }
        $sql = "UPDATE usuarios SET " . implode(',', $fields) . " WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }
}

