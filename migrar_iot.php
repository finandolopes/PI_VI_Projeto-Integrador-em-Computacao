<?php
include_once 'php/conexao.php';

// Obtém o nome do banco atual
$dbname = $conexao->query("SELECT DATABASE()")->fetch_row()[0];

/**
 * Adiciona uma coluna apenas se ela ainda não existir,
 * compatível com MySQL 5.x / MariaDB.
 */
function addColumnIfMissing($conexao, $dbname, $table, $column, $definition) {
    $stmt = $conexao->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->bind_param('sss', $dbname, $table, $column);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_row()[0];

    if ($exists) {
        echo "⏭ Já existe: <code>{$table}.{$column}</code><br>";
        return;
    }

    $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
    if ($conexao->query($sql)) {
        echo "✔ Adicionado: <code>{$table}.{$column}</code><br>";
    } else {
        echo "✘ ERRO em <code>{$table}.{$column}</code>: " . htmlspecialchars($conexao->error) . "<br>";
    }
}

addColumnIfMissing($conexao, $dbname, 'requisicoes',  'data_conclusao', 'DATETIME DEFAULT NULL');
addColumnIfMissing($conexao, $dbname, 'iot_checkins', 'confirmado',     'TINYINT(1) NOT NULL DEFAULT 0');
addColumnIfMissing($conexao, $dbname, 'iot_checkins', 'confirmado_em',  'DATETIME DEFAULT NULL');

echo "<hr><strong>Migração concluída.</strong> Pode apagar este arquivo.";
