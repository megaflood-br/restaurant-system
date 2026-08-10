<?php

namespace App\Services;

use App\Models\DeliveryArea;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        $result = $this->requestGeocode($query);

        if ($result !== null) {
            return $result;
        }

        $context = $this->geocodeContextSuffix();

        if ($context !== '') {
            return $this->requestGeocode($query.', '.$context);
        }

        return null;
    }

    private function geocodeContextSuffix(): string
    {
        $parts = array_filter([
            config('digital_menu.city'),
            config('digital_menu.state'),
        ]);

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        $restaurantAddress = trim((string) config('general.address'));

        return $restaurantAddress;
    }

    /** @return array{lat: float, lng: float}|null */
    private function requestGeocode(string $query): ?array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => config('app.name', 'Restaurant System').'/1.0'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'json',
                    'limit' => 1,
                    'countrycodes' => 'br',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $result = $response->json()[0] ?? null;

            if (! $result) {
                return null;
            }

            return [
                'lat' => (float) $result['lat'],
                'lng' => (float) $result['lon'],
            ];
        } catch (\Throwable $exception) {
            Log::warning('Geocoding failed', ['address' => $query, 'error' => $exception->getMessage()]);

            return null;
        }
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
