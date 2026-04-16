<?php

namespace App\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FrankfurterService
{
    private const BASE_URL = 'https://api.frankfurter.dev/v2';
    private const HTTP_TIMEOUT_SECONDS = 2;
    private const HTTP_CONNECT_TIMEOUT_SECONDS = 1;
    private const STALE_CACHE_TTL_DAYS = 7;

    /**
     * @var array<string, array{name: string, symbol: string|null}>|null
     */
    private ?array $currenciesCache = null;

    /**
     * @var array<string, array{date: string|null, base: string, rates: array<string, float>}>
     */
    private array $ratesCache = [];

    /**
     * @return array<string, array{name: string, symbol: string|null}>
     */
    public function currencies(): array
    {
        if ($this->currenciesCache !== null) {
            return $this->currenciesCache;
        }

        return $this->currenciesCache = Cache::remember('frankfurter.currencies', now()->addDay(), function (): array {
            try {
                $response = Http::connectTimeout(self::HTTP_CONNECT_TIMEOUT_SECONDS)
                    ->timeout(self::HTTP_TIMEOUT_SECONDS)
                    ->get(self::BASE_URL . '/currencies');
            } catch (\Throwable) {
                return Cache::get('frankfurter.currencies.stale', $this->fallbackCurrencies());
            }

            if (! $response->successful()) {
                return Cache::get('frankfurter.currencies.stale', $this->fallbackCurrencies());
            }

            $currencies = collect($response->json())
                ->mapWithKeys(fn (array $currency): array => [
                    $currency['iso_code'] => [
                        'name' => $currency['name'],
                        'symbol' => $currency['symbol'] ?? null,
                    ],
                ])
                ->sortKeys()
                ->all();

            Cache::put('frankfurter.currencies.stale', $currencies, now()->addDays(self::STALE_CACHE_TTL_DAYS));

            return $currencies;
        });
    }

    /**
     * @return array<string, string>
     */
    public function currencyOptions(): array
    {
        return collect($this->currencies())
            ->mapWithKeys(fn (array $currency, string $code): array => [
                $code => "{$code} - {$currency['name']}",
            ])
            ->all();
    }

    /**
     * @param  array<string, float>  $rates
     * @return LengthAwarePaginator<
     *     string,
     *     array{code: string, name: string, symbol: string|null, rate: float|null, converted: float|null}
     * >
     */
    public function paginatedCurrencyRows(
        string $baseCurrency,
        array $rates,
        float $amount = 1.0,
        ?string $search = null,
        ?string $sortColumn = null,
        ?string $sortDirection = null,
        int $page = 1,
        int | string | null $perPage = 10,
    ): LengthAwarePaginator {
        $rows = $this->currencyRows(
            baseCurrency: $baseCurrency,
            rates: $rates,
            amount: $amount,
            search: $search,
            sortColumn: $sortColumn,
            sortDirection: $sortDirection,
        );

        $total = $rows->count();
        $perPage = $perPage === 'all'
            ? max($total, 1)
            : max((int) $perPage, 1);
        $page = max($page, 1);

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage),
            total: $total,
            perPage: $perPage,
            currentPage: $page,
        );
    }

    /**
     * @param  array<string, float>  $rates
     * @return Collection<
     *     string,
     *     array{code: string, name: string, symbol: string|null, rate: float|null, converted: float|null}
     * >
     */
    public function currencyRows(
        string $baseCurrency,
        array $rates,
        float $amount = 1.0,
        ?string $search = null,
        ?string $sortColumn = null,
        ?string $sortDirection = null,
    ): Collection {
        $baseCurrency = strtoupper($baseCurrency);
        $search = str(trim($search ?? ''))->lower()->toString();
        $sortColumn = in_array($sortColumn, ['code', 'name', 'rate', 'converted'], true)
            ? $sortColumn
            : 'code';

        return collect($this->currencies())
            ->reject(fn (array $currency, string $code): bool => $code === $baseCurrency)
            ->mapWithKeys(function (array $currency, string $code) use ($amount, $rates): array {
                $rate = $rates[$code] ?? null;

                return [
                    $code => [
                        'code' => $code,
                        'name' => $currency['name'],
                        'symbol' => $currency['symbol'] ?? null,
                        'rate' => $rate,
                        'converted' => $rate !== null ? $amount * $rate : null,
                    ],
                ];
            })
            ->filter(fn (array $row): bool => blank($search)
                || str($row['code'])->lower()->contains($search)
                || str($row['name'])->lower()->contains($search))
            ->sortBy($sortColumn, SORT_REGULAR, $sortDirection === 'desc');
    }

    /**
     * @return array{date: string|null, base: string, rates: array<string, float>}
     */
    public function latestRates(string $base, bool $refresh = false): array
    {
        $base = strtoupper($base);
        $cacheKey = "frankfurter.rates.{$base}";
        $staleCacheKey = "{$cacheKey}.stale";

        if (! $refresh && isset($this->ratesCache[$base])) {
            return $this->ratesCache[$base];
        }

        if ($refresh) {
            Cache::forget($cacheKey);
            unset($this->ratesCache[$base]);
        }

        return $this->ratesCache[$base] = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($base, $staleCacheKey): array {
            try {
                $response = Http::connectTimeout(self::HTTP_CONNECT_TIMEOUT_SECONDS)
                    ->timeout(self::HTTP_TIMEOUT_SECONDS)
                    ->get(self::BASE_URL . '/rates', [
                        'base' => $base,
                    ]);
            } catch (\Throwable) {
                return Cache::get($staleCacheKey, [
                    'date' => null,
                    'base' => $base,
                    'rates' => [],
                ]);
            }

            if (! $response->successful()) {
                return Cache::get($staleCacheKey, [
                    'date' => null,
                    'base' => $base,
                    'rates' => [],
                ]);
            }

            $rates = collect($response->json())
                ->mapWithKeys(fn (array $rate): array => [
                    $rate['quote'] => (float) $rate['rate'],
                ])
                ->sortKeys()
                ->all();

            $data = [
                'date' => $response->json('0.date'),
                'base' => $base,
                'rates' => $rates,
            ];

            Cache::put($staleCacheKey, $data, now()->addDays(self::STALE_CACHE_TTL_DAYS));

            return $data;
        });
    }

    public function convert(float $amount, string $from, string $to): ?float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return $amount;
        }

        $rates = $this->latestRates($from)['rates'];

        if (! isset($rates[$to])) {
            return null;
        }

        return $amount * $rates[$to];
    }

    /**
     * @return array<string, array{name: string, symbol: string|null}>
     */
    private function fallbackCurrencies(): array
    {
        return [
            'BAM' => ['name' => 'Bosnia and Herzegovina Convertible Mark', 'symbol' => 'KM'],
            'EUR' => ['name' => 'Euro', 'symbol' => '€'],
            'USD' => ['name' => 'United States Dollar', 'symbol' => '$'],
            'GBP' => ['name' => 'British Pound', 'symbol' => '£'],
            'CHF' => ['name' => 'Swiss Franc', 'symbol' => 'Fr'],
            'RSD' => ['name' => 'Serbian Dinar', 'symbol' => 'дин'],
            'HRK' => ['name' => 'Croatian Kuna', 'symbol' => 'kn'],
        ];
    }
}
