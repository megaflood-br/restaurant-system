<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ficha técnica — {{ $recipe->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; line-height: 1.5; color: #111; background: #fff; padding: 24px; }
        .sheet { max-width: 800px; margin: 0 auto; }
        .header { border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 20px; display: flex; gap: 16px; }
        .header img { width: 120px; height: 120px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; }
        .title { font-size: 22px; font-weight: bold; }
        .meta { color: #444; font-size: 12px; margin-top: 6px; }
        .costs { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 16px 0 24px; }
        .cost-box { border: 1px solid #ddd; border-radius: 8px; padding: 12px; }
        .cost-box strong { display: block; font-size: 18px; margin-top: 4px; }
        .section { margin-bottom: 20px; }
        .section-title { font-size: 14px; font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #ccc; padding-bottom: 4px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #e5e7eb; }
        th { font-size: 11px; text-transform: uppercase; color: #666; }
        .qty, .cost { text-align: right; white-space: nowrap; }
        .prep { white-space: pre-wrap; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; }
        .footer { margin-top: 28px; padding-top: 12px; border-top: 1px dashed #999; font-size: 11px; color: #666; }
        .no-print { max-width: 800px; margin: 0 auto 16px; display: flex; gap: 8px; }
        .no-print button, .no-print a { padding: 10px 16px; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; font-size: 13px; }
        .btn-print { background: #4f46e5; color: #fff; }
        .btn-back { background: #e5e7eb; color: #111; }
        @media print { body { padding: 0; } .no-print { display: none !important; } @page { size: A4; margin: 12mm; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" class="btn-print" onclick="window.print()">Imprimir</button>
        <a href="{{ route('recipes.edit', $recipe) }}" class="btn-back">Voltar</a>
    </div>

    <div class="sheet">
        <div class="header">
            @if ($recipe->image_url)
                <img src="{{ $recipe->image_url }}" alt="{{ $recipe->name }}">
            @endif
            <div>
                <div class="title">Ficha Técnica</div>
                <div class="title">{{ $recipe->name }}</div>
                <div class="meta">
                    @if ($recipe->product) Cardápio: {{ $recipe->product->name }} · @endif
                    Rendimento: {{ $recipe->yield_portions }} porções ·
                    Impresso em {{ now()->format('d/m/Y H:i') }}
                </div>
                @if ($recipe->description)
                    <div class="meta">{{ $recipe->description }}</div>
                @endif
            </div>
        </div>

        <div class="costs">
            <div class="cost-box">
                <span>Custo total</span>
                <strong>R$ {{ number_format($recipe->totalCost(), 2, ',', '.') }}</strong>
            </div>
            <div class="cost-box">
                <span>Custo / porção</span>
                <strong>R$ {{ number_format($recipe->costPerPortion(), 2, ',', '.') }}</strong>
            </div>
            <div class="cost-box">
                <span>Rendimento</span>
                <strong>{{ $recipe->yield_portions }} porções</strong>
            </div>
        </div>

        @forelse ($groupedIngredients as $categoryName => $ingredients)
            <div class="section">
                <div class="section-title">{{ $categoryName }}</div>
                <table>
                    <thead>
                        <tr>
                            <th>Ingrediente</th>
                            <th class="qty">Quantidade</th>
                            <th class="cost">Custo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ingredients as $ingredient)
                            <tr>
                                <td>{{ $ingredient->name }}</td>
                                <td class="qty">{{ $ingredient->formatRecipeQuantity((float) $ingredient->pivot->quantity) }}</td>
                                <td class="cost">R$ {{ number_format($ingredient->lineCost((float) $ingredient->pivot->quantity), 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <p class="meta">Nenhum ingrediente cadastrado.</p>
        @endforelse

        @if ($recipe->preparation_method)
            <div class="section">
                <div class="section-title">Modo de preparo</div>
                <div class="prep">{{ $recipe->preparation_method }}</div>
            </div>
        @endif

        <div class="footer">{{ config('printing.restaurant_name', config('app.name')) }} — Ficha técnica interna</div>
    </div>
</body>
</html>
