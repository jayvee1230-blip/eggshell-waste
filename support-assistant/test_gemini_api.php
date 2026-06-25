<?php
/**
 * test_gemini_api.php - Diagnostic tool to test Gemini API connectivity
 * Access this file to verify your Gemini API configuration is working
 * 
 * Usage: Visit /support-assistant/test_gemini_api.php in your browser
 */

require_once dirname(__DIR__) . "/config.php";

// Check if this is a JSON API request or HTML request
$isJsonRequest = isset($_GET['json']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

function test_response($success, $message, $data = [])
{
    global $isJsonRequest;
    
    if ($isJsonRequest) {
        header('Content-Type: application/json');
        echo json_encode([
            "success" => $success,
            "message" => $message,
            "data" => $data,
            "timestamp" => date('Y-m-d H:i:s')
        ]);
    } else {
        $status = $success ? '✅ PASS' : '❌ FAIL';
        echo "<div style='padding: 10px; margin: 5px 0; border-left: 4px solid " . ($success ? '#4CAF50' : '#f44336') . "; background: " . ($success ? '#f1f8f4' : '#fef5f5') . ";'>";
        echo "<strong>$status</strong> - $message";
        if (!empty($data)) {
            echo "<pre style='margin: 5px 0; font-size: 12px;'>" . json_encode($data, JSON_PRETTY_PRINT) . "</pre>";
        }
        echo "</div>";
    }
}

// Start HTML output if not JSON
if (!$isJsonRequest) {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>Gemini API Diagnostic Test</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
            .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            h1 { color: #333; border-bottom: 2px solid #2F4F3A; padding-bottom: 10px; }
            .test-section { margin: 20px 0; }
            .test-section h2 { font-size: 16px; color: #555; margin-top: 0; }
            code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>🔍 Gemini API Diagnostic Test</h1>
            <p>This tool checks if your Gemini API configuration is working correctly.</p>
            <div class='test-section'>";
}

// Test 1: Check if API key is set
$apiKey = env('GEMINI_API_KEY');
if (empty($apiKey)) {
    test_response(false, "GEMINI_API_KEY environment variable is not set", [
        "required" => "GEMINI_API_KEY",
        "status" => "missing"
    ]);
} else {
    $maskedKey = substr($apiKey, 0, 10) . '...' . substr($apiKey, -10);
    test_response(true, "GEMINI_API_KEY is configured", [
        "key_preview" => $maskedKey,
        "key_length" => strlen($apiKey)
    ]);
}

// Test 2: Check if model is set
$model = env('GEMINI_MODEL', 'gemini-2.0-flash');
test_response(true, "GEMINI_MODEL is configured", [
    "model" => $model
]);

// Test 3: Check network connectivity
if (!empty($apiKey)) {
    $testUrl = "https://generativelanguage.googleapis.com/v1beta/models/" . urlencode($model) . ":generateContent?key=" . urlencode($apiKey);
    
    $testData = [
        "contents" => [
            [
                "parts" => [
                    ["text" => "Say 'Hello' in one word only."]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.1,
            "maxOutputTokens" => 10
        ]
    ];
    
    $ch = curl_init($testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        test_response(false, "Network request failed (cURL error)", [
            "error" => $curlError,
            "url" => $testUrl
        ]);
    } else {
        $responseData = json_decode($response, true);
        
        if ($httpCode === 200 && isset($responseData['candidates'])) {
            $replyText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? '';
            test_response(true, "Gemini API is working correctly", [
                "http_code" => $httpCode,
                "test_response" => $replyText,
                "latency_ms" => curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000
            ]);
        } else {
            test_response(false, "Gemini API returned an error", [
                "http_code" => $httpCode,
                "error" => $responseData['error']['message'] ?? 'Unknown error',
                "error_code" => $responseData['error']['code'] ?? 'unknown'
            ]);
        }
    }
}

// Test 4: Check log file permissions
$logFile = dirname(__DIR__) . '/logs/gemini_api.log';
$logDir = dirname($logFile);

if (!is_dir($logDir)) {
    test_response(false, "Log directory does not exist", [
        "path" => $logDir,
        "action" => "Will be created on first API call"
    ]);
} else {
    if (is_writable($logDir)) {
        test_response(true, "Log directory is writable", [
            "path" => $logDir
        ]);
    } else {
        test_response(false, "Log directory is not writable", [
            "path" => $logDir,
            "permissions" => substr(sprintf('%o', fileperms($logDir)), -4)
        ]);
    }
}

// Close HTML if not JSON
if (!$isJsonRequest) {
    echo "
            </div>
            <div class='test-section'>
                <h2>📋 Test Results Summary</h2>
                <p>If all tests pass, your Gemini API is properly configured and the support chat should work.</p>
                <p><strong>Log File Location:</strong> <code>" . $logFile . "</code></p>
                <p><strong>API Endpoint:</strong> <code>https://generativelanguage.googleapis.com/v1beta/models/</code></p>
            </div>
        </div>
    </body>
    </html>";
}
?>

