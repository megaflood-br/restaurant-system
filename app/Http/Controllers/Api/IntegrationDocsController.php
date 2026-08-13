<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\PaymentMethod;
use Illuminate\Http\JsonResponse;

class IntegrationDocsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $base = url('/api/v1');

        return response()->json([
            'name' => config('app.name').' Integration API',
            'version' => '1.0',
            'authentication' => [
                'type' => 'bearer',
                'header' => 'Authorization: Bearer {token}',
                'alternative' => 'X-Api-Key: {token}',
            ],
            'endpoints' => [
                'menu' => ['GET', "{$base}/menu"],
                'menu_image_today' => ['GET', "{$base}/menu/image/today"],
                'orders' => [
                    'list' => ['GET', "{$base}/orders?status=&type=&phone=&today=&open=&limit="],
                    'show' => ['GET', "{$base}/orders/{id}"],
                    'by_phone' => ['GET', "{$base}/orders/by-phone/{phone}"],
                    'create' => ['POST', "{$base}/orders"],
                    'create_example' => [
                        'type' => 'delivery',
                        'phone' => '5511987654321',
                        'address' => 'Rua das Flores, 123',
                        'customer_name' => 'Maria Silva',
                        'payment_method' => 'pix',
                        'items' => [
                            ['product_id' => 1, 'quantity' => 2],
                        ],
                    ],
                    'payment_methods' => PaymentMethod::labels(),
                    'payment_method_aliases' => PaymentMethod::aliases(),
                    'payment_method_note' => 'Aceita código (debit), rótulo (Cartão de débito) ou alias em português (debito). Números 1–5 também funcionam.',
                    'create_aliases' => [
                        'phone' => 'customer_phone',
                        'client_phone_number' => 'customer_phone',
                        'remoteJid' => 'customer_phone',
                        'address' => 'delivery_address',
                        'name' => 'customer_name',
                        'pushName' => 'customer_name',
                        'payment' => 'payment_method',
                    ],
                    'n8n_phone_hint' => 'No n8n use expressão, ex.: {{ $json.phone }} ou {{ $json.remoteJid.split("@")[0] }}. Não envie o texto literal "client_phone_number".',
                    'update_status' => ['PATCH', "{$base}/orders/{id}/status"],
                ],
                'customers' => [
                    'list' => ['GET', "{$base}/customers?search=&limit="],
                    'show' => ['GET', "{$base}/customers/{id}"],
                    'by_phone' => ['GET', "{$base}/customers/by-phone/{phone}"],
                    'create' => ['POST', "{$base}/customers"],
                ],
                'comandas' => [
                    'overview' => ['GET', "{$base}/comandas"],
                    'show' => ['GET', "{$base}/comandas/{number}"],
                ],
                'whatsapp' => [
                    'connection' => ['GET', "{$base}/whatsapp/connection"],
                    'messages' => ['GET', "{$base}/whatsapp/messages?phone=&direction=&limit="],
                    'send' => ['POST', "{$base}/whatsapp/messages"],
                    'log_inbound' => ['POST', "{$base}/whatsapp/inbound"],
                ],
                'print_jobs' => [
                    'pending' => ['GET', "{$base}/print-jobs/pending"],
                    'claim' => ['POST', "{$base}/print-jobs/claim"],
                    'complete' => ['POST', "{$base}/print-jobs/{id}/complete"],
                    'fail' => ['POST', "{$base}/print-jobs/{id}/fail", ['error' => 'opcional']],
                    'note' => 'Use o script scripts/print-agent.php no PC do restaurante com driver=agent.',
                ],
            ],
            'webhooks' => [
                'evolution_inbound' => url('/api/webhooks/evolution'),
            ],
            'notes' => [
                'Configure o atendimento e mensagens no n8n.',
                'Mensagens recebidas pela Evolution API são registradas e encaminhadas ao webhook n8n (se configurado).',
                'O envio via POST /whatsapp/messages usa a Evolution API apenas se habilitada no .env.',
            ],
        ]);
    }
}
