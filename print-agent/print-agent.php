#!/usr/bin/env php
<?php

/**
 * Agente local de impressão ESC/POS (pacote standalone).
 *
 * Não precisa do sistema Laravel neste PC — só PHP + este arquivo.
 *
 * Windows (duplo clique em iniciar.bat) ou:
 *   php print-agent.php
 *   php print-agent.php --url=... --token=... --printer=192.168.1.100
 *
 * Configuração: edite config.ini na mesma pasta (copie de config.example.ini).
 */

declare(strict_types=1);

$baseDir = __DIR__;
$configFile = $baseDir.DIRECTORY_SEPARATOR.'config.ini';
$config = is_file($configFile) ? (parse_ini_file($configFile, false, INI_SCANNER_TYPED) ?: []) : [];

$opts = getopt('', [
    'url::',
    'token::',
    'printer::',
    'port::',
    'interval::',
    'once::',
    'help::',
]);

if (isset($opts['help'])) {
    fwrite(STDOUT, <<<TXT
Agente local de impressao Bella Bistro (ESC/POS)

1) Copie config.example.ini para config.ini e preencha url/token/printer
2) Rode: php print-agent.php
   ou dê duplo clique em iniciar.bat

Argumentos (opcionais; sobrescrevem o config.ini):
  --url=https://app.bellabistro.com.br
  --token=TOKEN_DA_API
  --printer=192.168.1.100
  --port=9100
  --interval=2
  --once

TXT);
    exit(0);
}

$baseUrl = rtrim((string) ($opts['url'] ?? $config['url'] ?? ''), '/');
$token = (string) ($opts['token'] ?? $config['token'] ?? '');
$printerHost = (string) ($opts['printer'] ?? $config['printer'] ?? '');
$printerPort = (int) ($opts['port'] ?? $config['port'] ?? 9100);
$interval = max(1, (int) ($opts['interval'] ?? $config['interval'] ?? 2));
$once = array_key_exists('once', $opts) || ! empty($config['once']);

if ($baseUrl === '' || $token === '' || $printerHost === '') {
    fwrite(STDERR, "Falta configuracao.\n");
    fwrite(STDERR, "Crie config.ini (veja config.example.ini) ou passe --url --token --printer.\n");
    exit(1);
}

fwrite(STDOUT, '['.date('H:i:s')."] Agente iniciado → {$printerHost}:{$printerPort}\n");
fwrite(STDOUT, '['.date('H:i:s')."] Painel: {$baseUrl}\n");
fwrite(STDOUT, '['.date('H:i:s')."] Deixe esta janela aberta. Ctrl+C para parar.\n");

do {
    try {
        $job = claimJob($baseUrl, $token);

        if ($job === null) {
            if (! $once) {
                sleep($interval);
            }
            continue;
        }

        $id = (int) $job['id'];
        fwrite(STDOUT, '['.date('H:i:s')."] Job #{$id} ({$job['type']}) imprimindo...\n");

        try {
            sendEscPos($printerHost, $printerPort, (string) $job['payload']);
            completeJob($baseUrl, $token, $id);
            fwrite(STDOUT, '['.date('H:i:s')."] Job #{$id} OK\n");
        } catch (Throwable $printError) {
            failJob($baseUrl, $token, $id, $printError->getMessage());
            fwrite(STDERR, '['.date('H:i:s')."] Job #{$id} FALHOU: {$printError->getMessage()}\n");
            sleep(2);
        }
    } catch (Throwable $error) {
        fwrite(STDERR, '['.date('H:i:s').'] API: '.$error->getMessage()."\n");
        sleep($interval);
    }
} while (! $once);

exit(0);

/** @return array<string, mixed>|null */
function claimJob(string $baseUrl, string $token): ?array
{
    $response = apiRequest('POST', $baseUrl.'/api/v1/print-jobs/claim', $token);
    $data = $response['data'] ?? null;

    return is_array($data) ? $data : null;
}

function completeJob(string $baseUrl, string $token, int $id): void
{
    apiRequest('POST', $baseUrl.'/api/v1/print-jobs/'.$id.'/complete', $token);
}

function failJob(string $baseUrl, string $token, int $id, string $error): void
{
    apiRequest('POST', $baseUrl.'/api/v1/print-jobs/'.$id.'/fail', $token, [
        'error' => $error,
    ]);
}

function sendEscPos(string $host, int $port, string $text): void
{
    $socket = @fsockopen($host, $port, $errno, $errstr, 5);

    if (! $socket) {
        throw new RuntimeException("Nao conectou em {$host}:{$port} ({$errstr})");
    }

    stream_set_timeout($socket, 5);

    $payload = "\x1B\x40";
    $payload .= "\x1B\x74\x10";
    $payload .= "\x1B\x61\x00";
    $payload .= str_replace(["\r\n", "\r"], "\n", $text);
    if (! str_ends_with($payload, "\n")) {
        $payload .= "\n";
    }
    $payload .= "\n\n\n";
    $payload .= "\x1D\x56\x41\x03";

    $written = @fwrite($socket, $payload);
    fclose($socket);

    if ($written === false || $written === 0) {
        throw new RuntimeException('Falha ao enviar dados para a impressora.');
    }
}

/**
 * @param  array<string, mixed>|null  $body
 * @return array<string, mixed>
 */
function apiRequest(string $method, string $url, string $token, ?array $body = null): array
{
    $headers = [
        'Accept: application/json',
        'Authorization: Bearer '.$token,
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($body !== null) {
            $json = json_encode($body, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException($curlError ?: 'Falha cURL');
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", array_merge($headers, $body ? ['Content-Type: application/json'] : []))."\r\n",
                'content' => $body ? json_encode($body, JSON_UNESCAPED_UNICODE) : '',
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $raw = file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }
        if ($raw === false) {
            throw new RuntimeException('Falha HTTP em '.$url);
        }
    }

    $decoded = json_decode((string) $raw, true);
    if (! is_array($decoded)) {
        throw new RuntimeException("Resposta invalida HTTP {$status}");
    }

    if ($status >= 400) {
        $message = $decoded['message'] ?? $decoded['error'] ?? "HTTP {$status}";
        throw new RuntimeException(is_string($message) ? $message : "HTTP {$status}");
    }

    return $decoded;
}
