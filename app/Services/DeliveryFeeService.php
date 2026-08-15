<?php

namespace App\Services;

use App\Models\DeliveryArea;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeliveryFeeService
{
    public function areaForDistance(float $km): ?DeliveryArea
    {
        return DeliveryArea::active()
            ->ordered()
            ->get()
            ->first(function (DeliveryArea $area) use ($km) {
                $min = (float) $area->min_km;
                $max = $area->max_km !== null ? (float) $area->max_km : null;

                if ($km < $min) {
                    return false;
                }

                if ($max !== null && $km > $max) {
                    return false;
                }

                return true;
            });
    }

    public function feeForDistance(float $km): ?float
    {
        $area = $this->areaForDistance($km);

        return $area ? (float) $area->fee : null;
    }

    public function distanceFromAddress(string $address): ?float
    {
        $originLat = config('general.delivery_origin_lat');
        $originLng = config('general.delivery_origin_lng');

        if (! filled($originLat) || ! filled($originLng)) {
            return null;
        }

        $destination = $this->geocode($address);

        if ($destination === null) {
            return null;
        }

        return $this->haversineKm(
            (float) $originLat,
            (float) $originLng,
            $destination['lat'],
            $destination['lng'],
        );
    }

    public function quoteForAddress(string $address): ?array
    {
        $resolved = $this->resolveAddressQuote($address);

        return $resolved['quote'] ?? null;
    }

    /** @return array{quote: ?array, reason: ?string, distance_km: ?float} */
    public function diagnoseAddress(string $address): array
    {
        return $this->resolveAddressQuote($address);
    }

    /** @return array{quote: ?array, reason: ?string, distance_km: ?float} */
    private function resolveAddressQuote(string $address): array
    {
        $originLat = config('general.delivery_origin_lat');
        $originLng = config('general.delivery_origin_lng');

        if (! filled($originLat) || ! filled($originLng)) {
            return [
                'quote' => null,
                'reason' => 'missing_origin',
                'distance_km' => null,
            ];
        }

        $destination = $this->geocode($address);

        if ($destination === null) {
            return [
                'quote' => null,
                'reason' => 'geocode_failed',
                'distance_km' => null,
            ];
        }

        $km = $this->haversineKm(
            (float) $originLat,
            (float) $originLng,
            $destination['lat'],
            $destination['lng'],
        );

        $area = $this->areaForDistance($km);

        if (! $area) {
            return [
                'quote' => null,
                'reason' => 'out_of_range',
                'distance_km' => round($km, 1),
            ];
        }

        return [
            'quote' => [
                'distance_km' => round($km, 1),
                'delivery_area_id' => $area->id,
                'delivery_area_name' => $area->name,
                'delivery_fee' => (float) $area->fee,
            ],
            'reason' => null,
            'distance_km' => round($km, 1),
        ];
    }

    /** @return array{lat: float, lng: float}|null */
    private function geocode(string $address): ?array
    {
        $query = trim($address);

        if ($query === '') {
            return null;
        }

        $restrictToCity = $this->restaurantCity() !== '';
        $candidates = $this->geocodeCandidates($query);
        $attempted = 0;

        foreach ($candidates as $candidate) {
            if ($attempted > 0) {
                $this->throttleNominatim();
            }

            $attempted++;

            // Prefer city-scoped + bounded search; last passes loosen the box a bit.
            $bounded = $attempted <= 2;
            $hit = $this->requestGeocode($candidate, $restrictToCity, $bounded);

            if ($hit !== null) {
                return $hit;
            }
        }

        return null;
    }

    /**
     * Builds progressive queries: cleaned address → without number → neighbourhood/city → CEP.
     * Many Brazilian streets are missing from OSM; bairro/CEP still yield a usable point.
     * Clients often append loja/referência on the same line — strip that for geocode only.
     *
     * @return list<string>
     */
    private function geocodeCandidates(string $address): array
    {
        $context = $this->geocodeContextSuffix();
        $candidates = [];

        $push = function (string $value) use (&$candidates): void {
            $value = trim($value, " \t,");
            if ($value === '' || in_array($value, $candidates, true)) {
                return;
            }
            $candidates[] = $value;
        };

        $bases = [];
        $cleaned = $this->stripReferenceNoise($address);
        $structured = $this->structureSameLineAddress($cleaned);

        foreach ([$structured, $cleaned, $address] as $variant) {
            $normalized = $this->normalizeAddressQuery($variant);
            if ($normalized !== '' && ! in_array($normalized, $bases, true)) {
                $bases[] = $normalized;
            }
        }

        foreach ($bases as $normalized) {
            // City-scoped query first (more precise for Nominatim).
            if ($context !== '') {
                $push($normalized.', '.$context);
            }
            $push($normalized);

            $withoutNumber = $this->stripLeadingStreetNumber($normalized);
            if ($withoutNumber !== $normalized) {
                if ($context !== '') {
                    $push($withoutNumber.', '.$context);
                }
                $push($withoutNumber);
            }

            $areaQuery = $this->neighbourhoodCityQuery($normalized);
            if ($areaQuery !== null) {
                if ($context !== '' && ! str_contains(
                    $this->normalizePlaceName($areaQuery),
                    $this->normalizePlaceName($this->restaurantCity())
                )) {
                    $push($areaQuery.', '.$context);
                }
                $push($areaQuery);
            }
        }

        $cep = $this->extractCep($bases[0] ?? '') ?? $this->extractCep($address);
        if ($cep !== null) {
            if ($context !== '') {
                $push($cep.', '.$context);
            }
            $push($cep);
        }

        return $candidates;
    }

    /**
     * Removes landmarks / store names / "referência" notes that break Nominatim.
     * The full original string is still kept on the order for the driver.
     */
    private function stripReferenceNoise(string $address): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $address) ?? $address);

        $cutFromMarkers = [
            '/\b(?:refer[eê]ncia|referencia|ref\.?)\s*[:\-]?\s*.+$/iu',
            '/\b(?:em frente)\b.+$/iu',
            '/\b(?:pr[oó]ximo)\b.+$/iu',
            '/\b(?:perto)\b.+$/iu',
            '/\b(?:ao lado)\b.+$/iu',
            '/\b(?:atr[aá]s)\b.+$/iu',
            '/\b(?:esquina)\b.+$/iu',
            '/\b(?:ponto de refer[eê]ncia)\b.+$/iu',
        ];

        foreach ($cutFromMarkers as $pattern) {
            $value = preg_replace($pattern, '', $value) ?? $value;
        }

        // "Rua X, 10 - Mercado Y" / "Rua X, 10 / loja Z"
        $value = preg_replace(
            '/\s*[-–—\/]\s*(?:loja|mercado|padaria|farm[aá]cia|supermercado|shopping|posto|igreja|escola|condom[ií]nio|ponto|local|pr[oó]ximo|refer).+$/iu',
            '',
            $value
        ) ?? $value;

        // Parenthetical notes with landmark words
        $value = preg_replace(
            '/\s*\([^)]*(?:loja|mercado|padaria|refer|pr[oó]ximo|frente|shopping|farm[aá]cia)[^)]*\)\s*/iu',
            ' ',
            $value
        ) ?? $value;

        // Same line after house number: "... 465 Vila Mariana Mercado X"
        if (preg_match('/^(.+?\b\d+[A-Za-z\-\/]*)\b(.*)$/u', $value, $matches) === 1) {
            $head = rtrim($matches[1], " \t,");
            $tail = ltrim($matches[2], " \t,");
            $tailClean = preg_replace(
                '/\b(?:loja|mercado|padaria|farm[aá]cia|supermercado|shopping|posto|igreja|escola|condom[ií]nio)\b.+$/iu',
                '',
                $tail
            ) ?? $tail;
            $tailClean = trim($tailClean, " \t,;-–—/");
            $value = $tailClean !== '' ? $head.' '.$tailClean : $head;
        }

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value, " \t,");
    }

    /**
     * "Rua Foo 123 Bairro Bar" → "Rua Foo, 123, Bairro Bar" so neighbourhood fallback works.
     */
    private function structureSameLineAddress(string $address): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $address) ?? $address);

        if ($value === '' || substr_count($value, ',') >= 1) {
            return $value;
        }

        if (preg_match(
            '/^((?:rua|r\.?|avenida|av\.?|alameda|al\.?|travessa|tv\.?|estrada|rodovia|praça|praca|largo|viela|beco)\s+.+?)\s+(\d+[A-Za-z\-\/]*)\s+(.+)$/iu',
            $value,
            $matches
        ) === 1) {
            return trim($matches[1]).', '.$matches[2].', '.trim($matches[3]);
        }

        return $value;
    }

    private function normalizeAddressQuery(string $address): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $address) ?? $address);

        // Expand common Brazilian abbreviations so Nominatim can match suburbs.
        $replacements = [
            '/\bJds?\b\.?/iu' => 'Jardim',
            '/\bJrd\b\.?/iu' => 'Jardim',
            '/\bJd(im)?\b\.?/iu' => 'Jardim',
            '/\bVl\b\.?/iu' => 'Vila',
            '/\bPq\b\.?/iu' => 'Parque',
            '/\bRes\b\.?/iu' => 'Residencial',
            '/\bAv\b\.?/iu' => 'Avenida',
            '/\bR\b\.?(?=\s+[A-Za-zÀ-ú])/u' => 'Rua',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value) ?? $value;
        }

        // Format CEP 12940000 → 12940-000
        $value = preg_replace('/\b(\d{5})(\d{3})\b/', '$1-$2', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function stripLeadingStreetNumber(string $address): string
    {
        // "Rua X, 550, Bairro" or "Rua X 550, Bairro"
        $stripped = preg_replace('/^(.+?)(?:,\s*|\s+)(\d+[A-Za-z\-\/]*)\b,?\s*/u', '$1, ', $address, 1);

        return trim((string) $stripped, " \t,");
    }

    private function neighbourhoodCityQuery(string $address): ?string
    {
        $parts = array_values(array_filter(array_map(
            static fn (string $part) => trim($part),
            explode(',', $address)
        ), static fn (string $part) => $part !== ''));

        if (count($parts) < 2) {
            return null;
        }

        // Drop street (and optional number) so we geocode by bairro/cidade.
        while ($parts !== [] && $this->looksLikeStreetOrNumber($parts[0])) {
            array_shift($parts);
        }

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }

    private function looksLikeStreetOrNumber(string $part): bool
    {
        if (preg_match('/^\d+[A-Za-z\-\/]*$/u', $part) === 1) {
            return true;
        }

        return (bool) preg_match(
            '/^(rua|r|avenida|av|alameda|al|travessa|tv|estrada|rodovia|praça|praca|largo|viela|beco)\b/iu',
            $part
        );
    }

    private function extractCep(string $address): ?string
    {
        if (preg_match('/\b(\d{5})-?(\d{3})\b/', $address, $matches) !== 1) {
            return null;
        }

        return $matches[1].'-'.$matches[2];
    }

    private function geocodeContextSuffix(): string
    {
        $city = $this->restaurantCity();
        $state = trim((string) config('digital_menu.state'));

        if ($city !== '') {
            return implode(', ', array_filter([$city, $state !== '' ? $state : null, 'Brasil']));
        }

        return trim((string) config('general.address'));
    }

    private function restaurantCity(): string
    {
        return trim((string) config('digital_menu.city'));
    }

    private function throttleNominatim(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        // Nominatim usage policy: max 1 request/second.
        usleep(1_100_000);
    }

    /** @return array{lat: float, lng: float}|null */
    private function requestGeocode(string $query, bool $restrictToCity, bool $bounded = true): ?array
    {
        try {
            $params = [
                'q' => $query,
                'format' => 'json',
                'limit' => 5,
                'countrycodes' => 'br',
                'addressdetails' => 1,
            ];

            $originLat = config('general.delivery_origin_lat');
            $originLng = config('general.delivery_origin_lng');

            if (filled($originLat) && filled($originLng)) {
                $lat = (float) $originLat;
                $lng = (float) $originLng;
                // ~30 km box around the restaurant to keep results local.
                $delta = 0.3;
                $params['viewbox'] = implode(',', [
                    $lng - $delta,
                    $lat + $delta,
                    $lng + $delta,
                    $lat - $delta,
                ]);
                if ($bounded) {
                    $params['bounded'] = 1;
                }
            }

            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => config('app.name', 'Restaurant System').'/1.0'])
                ->get('https://nominatim.openstreetmap.org/search', $params);

            if (! $response->successful()) {
                return null;
            }

            $results = $response->json();

            if (! is_array($results) || $results === []) {
                return null;
            }

            $expectedCity = $this->normalizePlaceName($this->restaurantCity());

            foreach ($results as $result) {
                if (! is_array($result) || ! isset($result['lat'], $result['lon'])) {
                    continue;
                }

                if ($restrictToCity && $expectedCity !== '' && ! $this->resultMatchesCity($result, $expectedCity)) {
                    continue;
                }

                return [
                    'lat' => (float) $result['lat'],
                    'lng' => (float) $result['lon'],
                ];
            }

            return null;
        } catch (\Throwable $exception) {
            Log::warning('Geocoding failed', ['address' => $query, 'error' => $exception->getMessage()]);

            return null;
        }
    }

    private function resultMatchesCity(array $result, string $expectedCity): bool
    {
        $address = is_array($result['address'] ?? null) ? $result['address'] : [];

        $candidates = array_filter([
            $address['city'] ?? null,
            $address['town'] ?? null,
            $address['municipality'] ?? null,
            $address['city_district'] ?? null,
            $address['county'] ?? null,
            $result['display_name'] ?? null,
        ], fn ($value) => filled($value));

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizePlaceName((string) $candidate);

            if ($normalized === $expectedCity || str_contains($normalized, $expectedCity)) {
                return true;
            }
        }

        return false;
    }

    private function normalizePlaceName(string $name): string
    {
        $ascii = Str::lower(Str::ascii(trim($name)));

        return preg_replace('/[^a-z0-9]+/', '', $ascii) ?? '';
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}
