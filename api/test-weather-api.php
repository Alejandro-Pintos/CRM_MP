<?php
/**
 * Script de prueba para verificar la API de OpenWeatherMap
 * 
 * Uso: php test-weather-api.php
 */

require __DIR__ . '/vendor/autoload.php';

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$apiKey = $_ENV['OPENWEATHER_API_KEY'] ?? 'not_set';

echo "\n🌤️  Test de API de OpenWeatherMap\n";
echo str_repeat("=", 50) . "\n\n";

// Verificar si la API key está configurada
if ($apiKey === 'not_set' || $apiKey === 'your_api_key_here') {
    echo "❌ ERROR: API Key no configurada\n";
    echo "\nPasos para configurar:\n";
    echo "1. Registrate en: https://home.openweathermap.org/users/sign_up\n";
    echo "2. Obtén tu API key de: https://home.openweathermap.org/api_keys\n";
    echo "3. Edita el archivo .env y reemplaza:\n";
    echo "   OPENWEATHER_API_KEY=your_api_key_here\n";
    echo "   por tu API key real\n\n";
    exit(1);
}

echo "✅ API Key encontrada: " . substr($apiKey, 0, 8) . "...\n\n";

// Probar con coordenadas de Buenos Aires
$lat = -34.6037;
$lon = -58.3816;

echo "📍 Probando con coordenadas de Buenos Aires:\n";
echo "   Latitud: $lat\n";
echo "   Longitud: $lon\n\n";

$url = "https://api.openweathermap.org/data/2.5/weather?lat=$lat&lon=$lon&appid=$apiKey&lang=es&units=metric";

echo "🔄 Consultando API...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    
    echo "\n✅ ¡Conexión exitosa!\n\n";
    echo "📊 Datos del clima:\n";
    echo str_repeat("-", 50) . "\n";
    echo "🏙️  Ciudad: " . ($data['name'] ?? 'N/A') . "\n";
    echo "🌡️  Temperatura: " . ($data['main']['temp'] ?? 'N/A') . "°C\n";
    echo "💧 Humedad: " . ($data['main']['humidity'] ?? 'N/A') . "%\n";
    echo "☁️  Estado: " . ($data['weather'][0]['main'] ?? 'N/A') . "\n";
    echo "📝 Descripción: " . ($data['weather'][0]['description'] ?? 'N/A') . "\n";
    echo "🌪️  Viento: " . ($data['wind']['speed'] ?? 'N/A') . " m/s\n";
    echo str_repeat("-", 50) . "\n";
    
    echo "\n✨ La API está funcionando correctamente\n";
    echo "   Tu CRM ya puede obtener datos del clima local\n\n";
    
} elseif ($httpCode === 401) {
    echo "\n❌ ERROR 401: API Key inválida\n";
    echo "   La API key proporcionada no es válida\n";
    echo "   Verifica que copiaste la key correctamente\n";
    echo "   Recuerda: Las nuevas keys tardan ~10 min en activarse\n\n";
    
} elseif ($httpCode === 429) {
    echo "\n⚠️  ERROR 429: Límite de llamadas excedido\n";
    echo "   Has superado el límite de 1000 llamadas/día\n";
    echo "   Espera hasta mañana o actualiza tu plan\n\n";
    
} else {
    echo "\n❌ ERROR: Código HTTP $httpCode\n";
    echo "   Respuesta: " . substr($response, 0, 200) . "\n\n";
}

echo str_repeat("=", 50) . "\n";
