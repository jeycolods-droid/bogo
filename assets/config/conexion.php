<?php
// Credenciales y configuraciones principales del proyecto
// MODIFICADO PARA RENDER.COM: Lee las credenciales de las Variables de Entorno.

return [
    'telegram' => [
        // Esta variable la configurarás en Render con tu token.
        'bot_token' => getenv('TELEGRAM_BOT_TOKEN'), 
        
        // Esta variable la configurarás en Render con tu chat ID.
        'chat_id' => getenv('TELEGRAM_CHAT_ID'),   
    ],
    'db' => [
        // Estas 4 variables las obtendrás de la base de datos que crees en Render.
        'host' => getenv('DB_HOST'),
        'dbname' => getenv('DB_NAME'),
        'user' => getenv('DB_USER'),
        'password' => getenv('DB_PASSWORD'),
    ],
    // Esta URL la construiremos con la dirección de tu proyecto en Render.
    'base_url' => getenv('BASE_URL') 
];
?>