<?php
/**
 * ========================================
 * ROLE MANAGER - Sistema de Roles e Permissoes
 * ========================================
 * Gerencia roles de usuarios e permissoes de acesso
 */

class RoleManager {
    private $conexao;
    private $userIdColumn = 'id_usuario';
    private $userNameColumn = 'nome_usuario';
    private $hasRoleColumn = false;
    private $hasPerfilColumn = false;

    const ROLES = [
        'admin' => 'Administrador',
        'manager' => 'Gerenciador',
        'analyst' => 'Analista',
        'support' => 'Suporte',
        'editor' => 'Editor',
        'viewer' => 'Visualizador'
    ];

    const PERMISSIONS = [
        'dashboard.view' => 'Visualizar Dashboard',
        'dashboard.manage' => 'Gerenciar Dashboard',
        'testimonials.view' => 'Visualizar Depoimentos',
        'testimonials.edit' => 'Editar Depoimentos',
        'testimonials.delete' => 'Deletar Depoimentos',
        'testimonials.create' => 'Criar Depoimentos',
        'users.manage' => 'Gerenciar Usuarios',
        'users.view' => 'Visualizar Usuarios',
        'reports.view' => 'Visualizar Relatorios',
        'reports.export' => 'Exportar Relatorios',
        'settings.manage' => 'Gerenciar Configuracoes',
        'audit.view' => 'Visualizar Auditoria',
        'backup.manage' => 'Gerenciar Backups',
        'email.send' => 'Enviar Emails',
        'logs.view' => 'Visualizar Logs',
    ];

    const ROLE_PERMISSIONS = [
        'admin' => [
            'dashboard.view', 'dashboard.manage', 'testimonials.view', 'testimonials.edit',
            'testimonials.delete', 'testimonials.create', 'users.manage', 'users.view',
            'reports.view', 'reports.export', 'settings.manage', 'audit.view',
            'backup.manage', 'email.send', 'logs.view'
        ],
        'manager' => [
            'dashboard.view', 'dashboard.manage', 'testimonials.view', 'testimonials.edit',
            'testimonials.create', 'users.view', 'reports.view', 'reports.export',
            'audit.view', 'email.send'
        ],
        'analyst' => [
            'dashboard.view', 'reports.view', 'reports.export', 'audit.view'
        ],
        'support' => [
            'dashboard.view', 'testimonials.view', 'testimonials.edit',
            'users.view', 'reports.view'
        ],
        'editor' => [
            'testimonials.view', 'testimonials.edit', 'testimonials.create',
            'reports.view'
        ],
        'viewer' => [
            'dashboard.view', 'reports.view', 'testimonials.view'
        ]
    ];

    public function __construct($conexao) {
        $this->conexao = $conexao;
        $this->detectUserColumns();
        $this->initializeDatabase();
        $this->detectUserColumns();
    }

    private function hasColumn(string $table, string $column): bool {
        $safeColumn = $this->conexao->real_escape_string($column);
        $result = $this->conexao->query("SHOW COLUMNS FROM {$table} LIKE '{$safeColumn}'");
        return $result instanceof mysqli_result && $result->num_rows > 0;
    }

    private function detectUserColumns(): void {
        if ($this->hasColumn('usuarios', 'id_usuario')) {
            $this->userIdColumn = 'id_usuario';
        } elseif ($this->hasColumn('usuarios', 'id')) {
            $this->userIdColumn = 'id';
        }

        if ($this->hasColumn('usuarios', 'nome_usuario')) {
            $this->userNameColumn = 'nome_usuario';
        } elseif ($this->hasColumn('usuarios', 'nome')) {
            $this->userNameColumn = 'nome';
        } elseif ($this->hasColumn('usuarios', 'usuario')) {
            $this->userNameColumn = 'usuario';
        } else {
            $this->userNameColumn = $this->userIdColumn;
        }

        $this->hasRoleColumn = $this->hasColumn('usuarios', 'role');
        $this->hasPerfilColumn = $this->hasColumn('usuarios', 'perfil');
    }

    /**
     * Inicializar banco de dados com colunas necessarias.
     */
    private function initializeDatabase(): void {
        if (!$this->hasRoleColumn) {
            $this->conexao->query("ALTER TABLE usuarios ADD COLUMN role VARCHAR(50) DEFAULT 'viewer' AFTER senha");
        }

        if (!$this->hasColumn('usuarios', 'permissions')) {
            $this->conexao->query("ALTER TABLE usuarios ADD COLUMN permissions JSON AFTER role");
        }

        if (!$this->hasColumn('usuarios', 'last_role_update')) {
            $this->conexao->query(
                "ALTER TABLE usuarios ADD COLUMN last_role_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER permissions"
            );
        }
    }

    private function normalizeRole(?string $role, ?string $perfil): string {
        $role = strtolower(trim((string) $role));
        if ($role !== '' && array_key_exists($role, self::ROLES)) {
            return $role;
        }

        $perfil = strtolower(trim((string) $perfil));
        if ($perfil === 'admin') {
            return 'admin';
        }
        if ($perfil === 'gerente' || $perfil === 'manager') {
            return 'manager';
        }
        if ($perfil === 'analista' || $perfil === 'analyst') {
            return 'analyst';
        }
        if ($perfil === 'suporte' || $perfil === 'support') {
            return 'support';
        }
        if ($perfil === 'editor') {
            return 'editor';
        }

        return 'viewer';
    }

    private function getUserData($userId): ?array {
        $campos = [];
        if ($this->hasRoleColumn) {
            $campos[] = 'role';
        } else {
            $campos[] = "'viewer' AS role";
        }

        if ($this->hasPerfilColumn) {
            $campos[] = 'perfil';
        } else {
            $campos[] = "'usuario' AS perfil";
        }

        $camposSql = implode(', ', $campos);
        $sql = "SELECT {$camposSql} FROM usuarios WHERE {$this->userIdColumn} = ? LIMIT 1";
        $stmt = $this->conexao->prepare($sql);
        if (!$stmt) {
            return null;
        }

        $id = (int) $userId;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return $row ?: null;
    }

    /**
     * Verificar se usuario tem permissao.
     */
    public function checkPermission($userId, $permission) {
        if (empty($userId)) {
            return false;
        }

        $row = $this->getUserData($userId);
        if (!$row) {
            return false;
        }

        $userRole = $this->normalizeRole($row['role'] ?? null, $row['perfil'] ?? null);
        $allowedPermissions = self::ROLE_PERMISSIONS[$userRole] ?? [];

        return in_array($permission, $allowedPermissions, true);
    }

    /**
     * Verificar se tem qualquer uma das permissoes.
     */
    public function hasAnyPermission($userId, array $permissions) {
        foreach ($permissions as $permission) {
            if ($this->checkPermission($userId, $permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obter role do usuario.
     */
    public function getUserRole($userId) {
        if (empty($userId)) {
            return 'viewer';
        }

        $row = $this->getUserData($userId);
        if (!$row) {
            return 'viewer';
        }

        return $this->normalizeRole($row['role'] ?? null, $row['perfil'] ?? null);
    }

    /**
     * Atribuir role a um usuario.
     */
    public function assignRole($userId, $role) {
        if (!array_key_exists($role, self::ROLES)) {
            throw new Exception('Role invalido: ' . $role);
        }

        if (!$this->hasRoleColumn) {
            $this->initializeDatabase();
            $this->detectUserColumns();
        }

        $sql = "UPDATE usuarios SET role = ?, last_role_update = NOW() WHERE {$this->userIdColumn} = ?";
        $stmt = $this->conexao->prepare($sql);
        if (!$stmt) {
            throw new Exception('Erro ao preparar statement: ' . $this->conexao->error);
        }

        $id = (int) $userId;
        $stmt->bind_param('si', $role, $id);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    /**
     * Obter todos os roles disponiveis.
     */
    public function getAllRoles() {
        return self::ROLES;
    }

    /**
     * Obter todas as permissoes.
     */
    public function getAllPermissions() {
        return self::PERMISSIONS;
    }

    /**
     * Obter permissoes de um role.
     */
    public function getRolePermissions($role) {
        return self::ROLE_PERMISSIONS[$role] ?? [];
    }

    /**
     * Verificar se usuario e admin.
     */
    public function isAdmin($userId) {
        return $this->getUserRole($userId) === 'admin';
    }

    /**
     * Verificar se usuario e manager ou admin.
     */
    public function isManager($userId) {
        $role = $this->getUserRole($userId);
        return in_array($role, ['admin', 'manager'], true);
    }

    /**
     * Listar usuarios por role.
     */
    public function getUsersByRole($role) {
        if (!$this->hasRoleColumn) {
            return [];
        }

        $sql = "SELECT {$this->userIdColumn} AS id_usuario, {$this->userNameColumn} AS nome_usuario, email, role
                FROM usuarios
                WHERE role = ?
                ORDER BY {$this->userNameColumn}";

        $stmt = $this->conexao->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('s', $role);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $users;
    }

    /**
     * Contar usuarios por role.
     */
    public function countUsersByRole($role) {
        $users = $this->getUsersByRole($role);
        return count($users);
    }
}
?>
