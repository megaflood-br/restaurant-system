<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\FormatsApiResponses;
use App\Http\Controllers\Controller;
use App\Models\PrintJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrintJobApiController extends Controller
{
    use FormatsApiResponses;

    public function pending(Request $request): JsonResponse
    {
        $limit = min(max($request->integer('limit', 10), 1), 50);

        $jobs = PrintJob::query()
            ->where('status', PrintJob::STATUS_PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $jobs->map(fn (PrintJob $job) => $this->jobPayload($job)),
            'pending_count' => PrintJob::query()->where('status', PrintJob::STATUS_PENDING)->count(),
        ]);
    }

    public function claim(Request $request): JsonResponse
    {
        $job = DB::transaction(function () {
            $job = PrintJob::query()
                ->where('status', PrintJob::STATUS_PENDING)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $job) {
                return null;
            }

            $job->update([
                'status' => PrintJob::STATUS_PRINTING,
                'claimed_at' => now(),
                'attempts' => $job->attempts + 1,
            ]);

            return $job->fresh();
        });

        if (! $job) {
            return response()->json(['data' => null, 'message' => 'Nenhum job pendente.']);
        }

        return response()->json(['data' => $this->jobPayload($job)]);
    }

    public function complete(PrintJob $printJob): JsonResponse
    {
        if (! in_array($printJob->status, [PrintJob::STATUS_PENDING, PrintJob::STATUS_PRINTING], true)) {
            return $this->apiError('Job já finalizado.', 409);
        }

        $printJob->update([
            'status' => PrintJob::STATUS_DONE,
            'printed_at' => now(),
            'last_error' => null,
        ]);

        return response()->json(['data' => $this->jobPayload($printJob->fresh())]);
    }

    public function fail(Request $request, PrintJob $printJob): JsonResponse
    {
        $validated = $request->validate([
            'error' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($printJob->status === PrintJob::STATUS_DONE) {
            return $this->apiError('Job já foi concluído.', 409);
        }

        $printJob->update([
            'status' => PrintJob::STATUS_PENDING,
            'claimed_at' => null,
            'last_error' => $validated['error'] ?? 'Falha ao imprimir',
        ]);

        return response()->json(['data' => $this->jobPayload($printJob->fresh())]);
    }

    /** @return array<string, mixed> */
    private function jobPayload(PrintJob $job): array
    {
        return [
            'id' => $job->id,
            'type' => $job->type,
            'order_id' => $job->order_id,
            'payload' => $job->payload,
            'status' => $job->status,
            'attempts' => $job->attempts,
            'last_error' => $job->last_error,
            'claimed_at' => optional($job->claimed_at)?->toIso8601String(),
            'printed_at' => optional($job->printed_at)?->toIso8601String(),
            'created_at' => optional($job->created_at)?->toIso8601String(),
        ];
    }
}
