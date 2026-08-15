<?php

namespace Tests\Unit;

use App\Services\DeliveryFeeService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeliveryFeeServiceTest extends TestCase
{
    public function test_geocode_query_includes_restaurant_city_and_state(): void
    {
        config([
            'digital_menu.city' => 'São Paulo',
            'digital_menu.state' => 'SP',
            'general.delivery_origin_lat' => '-23.5505',
            'general.delivery_origin_lng' => '-46.6333',
        ]);

        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            $this->assertStringContainsStringIgnoringCase('Vila Mariana', (string) ($query['q'] ?? ''));
            $this->assertStringContainsStringIgnoringCase('São Paulo', (string) ($query['q'] ?? ''));
            $this->assertStringContainsString('SP', (string) ($query['q'] ?? ''));
            $this->assertSame('br', $query['countrycodes'] ?? null);
            $this->assertSame('1', (string) ($query['bounded'] ?? ''));
            $this->assertNotEmpty($query['viewbox'] ?? null);

            return Http::response([
                [
                    'lat' => '-23.589',
                    'lon' => '-46.634',
                    'display_name' => 'Vila Mariana, São Paulo, SP, Brasil',
                    'address' => [
                        'suburb' => 'Vila Mariana',
                        'city' => 'São Paulo',
                        'state' => 'São Paulo',
                    ],
                ],
            ], 200);
        });

        $distance = app(DeliveryFeeService::class)->distanceFromAddress('Vila Mariana');

        $this->assertNotNull($distance);
        $this->assertGreaterThan(0, $distance);
        Http::assertSentCount(1);
    }

    public function test_geocode_rejects_results_outside_restaurant_city(): void
    {
        config([
            'digital_menu.city' => 'São Paulo',
            'digital_menu.state' => 'SP',
            'general.delivery_origin_lat' => '-23.5505',
            'general.delivery_origin_lng' => '-46.6333',
        ]);

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'lat' => '-22.9068',
                    'lon' => '-43.1729',
                    'display_name' => 'Centro, Rio de Janeiro, RJ, Brasil',
                    'address' => [
                        'suburb' => 'Centro',
                        'city' => 'Rio de Janeiro',
                        'state' => 'Rio de Janeiro',
                    ],
                ],
            ], 200),
        ]);

        $distance = app(DeliveryFeeService::class)->distanceFromAddress('Centro');

        $this->assertNull($distance);
    }

    public function test_geocode_picks_matching_city_from_multiple_results(): void
    {
        config([
            'digital_menu.city' => 'Campinas',
            'digital_menu.state' => 'SP',
            'general.delivery_origin_lat' => '-22.9056',
            'general.delivery_origin_lng' => '-47.0608',
        ]);

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'lat' => '-23.5505',
                    'lon' => '-46.6333',
                    'display_name' => 'Centro, São Paulo, SP, Brasil',
                    'address' => [
                        'suburb' => 'Centro',
                        'city' => 'São Paulo',
                    ],
                ],
                [
                    'lat' => '-22.9056',
                    'lon' => '-47.0608',
                    'display_name' => 'Centro, Campinas, SP, Brasil',
                    'address' => [
                        'suburb' => 'Centro',
                        'city' => 'Campinas',
                    ],
                ],
            ], 200),
        ]);

        $distance = app(DeliveryFeeService::class)->distanceFromAddress('Centro');

        $this->assertNotNull($distance);
        $this->assertLessThan(1, $distance);
    }

    public function test_geocode_falls_back_to_neighbourhood_when_street_is_unknown(): void
    {
        config([
            'digital_menu.city' => 'Atibaia',
            'digital_menu.state' => 'SP',
            'general.delivery_origin_lat' => '-23.1171',
            'general.delivery_origin_lng' => '-46.5502',
        ]);

        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $q = (string) ($query['q'] ?? '');

            if (str_contains(Str::lower($q), 'lurdes')) {
                return Http::response([], 200);
            }

            if (str_contains(Str::lower(Str::ascii($q)), 'jardim imperial')) {
                $this->assertStringContainsStringIgnoringCase('Jardim', $q);

                return Http::response([
                    [
                        'lat' => '-23.1420',
                        'lon' => '-46.5873',
                        'display_name' => 'Jardim Imperial, Atibaia, SP, Brasil',
                        'address' => [
                            'suburb' => 'Jardim Imperial',
                            'city' => 'Atibaia',
                            'state' => 'São Paulo',
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 200);
        });

        $distance = app(DeliveryFeeService::class)->distanceFromAddress(
            'Rua Lurdes Neves Machado, 550, Jd Imperial, Atibaia, SP, 12940000'
        );

        $this->assertNotNull($distance);
        $this->assertGreaterThan(0, $distance);
    }

    public function test_geocode_normalizes_jd_abbreviation_in_first_query(): void
    {
        config([
            'digital_menu.city' => 'Atibaia',
            'digital_menu.state' => 'SP',
            'general.delivery_origin_lat' => '-23.1171',
            'general.delivery_origin_lng' => '-46.5502',
        ]);

        Http::fake(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $q = (string) ($query['q'] ?? '');

            $this->assertStringNotContainsString('Jd ', $q);
            $this->assertStringContainsString('Jardim Imperial', $q);
            $this->assertMatchesRegularExpression('/12940-000/', $q);

            return Http::response([
                [
                    'lat' => '-23.1420',
                    'lon' => '-46.5873',
                    'display_name' => 'Jardim Imperial, Atibaia, SP, Brasil',
                    'address' => [
                        'suburb' => 'Jardim Imperial',
                        'city' => 'Atibaia',
                    ],
                ],
            ], 200);
        });

        $distance = app(DeliveryFeeService::class)->distanceFromAddress(
            'Jd Imperial, Atibaia, SP, 12940000'
        );

        $this->assertNotNull($distance);
        Http::assertSentCount(1);
    }

    public function test_geocode_strips_reference_and_store_name_from_query(): void
    {
        config([
            'digital_menu.city' => 'São Paulo',
            'digital_menu.state' => 'SP',
            'general.delivery_origin_lat' => '-23.5505',
            'general.delivery_origin_lng' => '-46.6333',
        ]);

        $queries = [];

        Http::fake(function (Request $request) use (&$queries) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $q = (string) ($query['q'] ?? '');
            $queries[] = $q;

            $this->assertStringNotContainsStringIgnoringCase('Mercado', $q);
            $this->assertStringNotContainsStringIgnoringCase('referência', $q);
            $this->assertStringContainsStringIgnoringCase('Machado', $q);

            return Http::response([
                [
                    'lat' => '-23.589',
                    'lon' => '-46.634',
                    'display_name' => 'Rua Machado de Assis, Vila Mariana, São Paulo, SP, Brasil',
                    'address' => [
                        'road' => 'Rua Machado de Assis',
                        'suburb' => 'Vila Mariana',
                        'city' => 'São Paulo',
                    ],
                ],
            ], 200);
        });

        $distance = app(DeliveryFeeService::class)->distanceFromAddress(
            'Rua Machado de Assis, 465, Vila Mariana, referência: Mercado X'
        );

        $this->assertNotNull($distance);
        $this->assertNotEmpty($queries);
        $this->assertStringContainsStringIgnoringCase('Vila Mariana', $queries[0]);
    }

    public function test_geocode_strips_same_line_landmark_without_commas(): void
    {
        config([
            'digital_menu.city' => 'São Paulo',
            'digital_menu.state' => 'SP',
            'general.delivery_origin_lat' => '-23.5505',
            'general.delivery_origin_lng' => '-46.6333',
        ]);

        $queries = [];

        Http::fake(function (Request $request) use (&$queries) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $q = (string) ($query['q'] ?? '');
            $queries[] = $q;

            $this->assertStringNotContainsStringIgnoringCase('em frente', $q);
            $this->assertStringNotContainsStringIgnoringCase('Padaria', $q);

            return Http::response([
                [
                    'lat' => '-23.589',
                    'lon' => '-46.634',
                    'display_name' => 'Rua Machado de Assis, Vila Mariana, São Paulo, SP, Brasil',
                    'address' => [
                        'road' => 'Rua Machado de Assis',
                        'suburb' => 'Vila Mariana',
                        'city' => 'São Paulo',
                    ],
                ],
            ], 200);
        });

        $distance = app(DeliveryFeeService::class)->distanceFromAddress(
            'Rua Machado de Assis 465 Vila Mariana em frente a Padaria Sol'
        );

        $this->assertNotNull($distance);
        $this->assertStringContainsString('465', $queries[0]);
        $this->assertStringContainsStringIgnoringCase('Vila Mariana', $queries[0]);
    }
}
