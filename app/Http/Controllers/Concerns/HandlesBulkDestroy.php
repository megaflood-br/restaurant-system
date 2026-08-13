<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait HandlesBulkDestroy
{
    /** @return list<int> */
    protected function bulkIds(Request $request): array
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        return array_values(array_unique(array_map('intval', $validated['ids'])));
    }

    protected function bulkResultRedirect(string $route, int $deleted, int $skipped, string $singular, string $plural): RedirectResponse
    {
        if ($deleted === 0 && $skipped > 0) {
            return redirect()->route($route)->with(
                'error',
                "Nenhum item excluído. {$skipped} ".($skipped === 1 ? $singular : $plural).' não puderam ser excluídos.'
            );
        }

        if ($deleted === 0) {
            return redirect()->route($route)->with('error', 'Nenhum item selecionado para excluir.');
        }

        $message = $deleted === 1
            ? "1 {$singular} excluído com sucesso."
            : "{$deleted} {$plural} excluídos com sucesso.";

        if ($skipped > 0) {
            $message .= " {$skipped} ignorado(s).";
        }

        return redirect()->route($route)->with('success', $message);
    }
}
