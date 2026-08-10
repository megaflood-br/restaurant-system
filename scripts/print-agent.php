#!/usr/bin/env php
<?php

/**
 * Agente local de impressão ESC/POS.
 *
 * Roda no PC do restaurante (mesma rede da impressora). O painel na nuvem
 * enfileira cupons; este script busca e imprime em 192.168.x.x:9100.
 *
 * Uso:
 *   php scripts/print-agent.php ^
 *     --url=https://app.bellabistro.com.br ^
 *     --token=SEU_TOKEN_API ^
 *     --printer=192.168.1.100 ^
 *     --port=9100
 *
 * No Windows: deixe uma janela aberta com esse comando, ou crie um atalho
 * que inicia com o Windows.
 */

declare(strict_types=1);

$opts = getopt('', [
    'url:',
    'token:',
    'printer:',
    'port::',
    'interval::',
    'once::',
    'help::',
]);

if (isset($opts['help']) || ! isset($opts['url'], $opts['token'], $opts['printer'])) {
    fwrite(STDOUT, <<<TXT
Agente local de impressao (ESC/POS)

Obrigatorio:
  --url=https://seu-painel.com
  --token=TOKEN_DA_API_INTEGRACAO
  --printer=192.168.1.100

Opcional:
  --port=9100
  --interval=2
  --once          (processa um ciclo e sai)

TXT);
    exit(isset($opts['help']) ? 0 : 1);
}

$baseUrl = rtrim((string) $opts['url'], '/');
$token = (string) $opts['token'];
$printerHost = (string) $opts['printer'];
$printerPort = (int) ($opts['port'] ?? 9100);
$interval = max(1, (int) ($opts['interval'] ?? 2));
$once = array_key_exists('once', $opts);

fwrite(STDOUT, '['.date('H:i:s')."] Agente iniciado → {$printerHost}:{$printerPort} via {$baseUrl}\n");

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
