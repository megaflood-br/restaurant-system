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

        $context = $this->geocodeContextSuffix();
        $restrictToCity = $this->restaurantCity() !== '';

        // Always search inside the restaurant city first (never Brazil-wide).
        if ($context !== '') {
            $scoped = $this->requestGeocode($query.', '.$context, $restrictToCity);

            if ($scoped !== null) {
                return $scoped;
            }

            // Retry the raw text, still filtered/biased to the restaurant city.
            if ($restrictToCity) {
                return $this->requestGeocode($query, true);
            }
        }

        return $this->requestGeocode($query, false);
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

    /** @return array{lat: float, lng: float}|null */
    private function requestGeocode(string $query, bool $restrictToCity): ?array
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
                $params['bounded'] = 1;
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
