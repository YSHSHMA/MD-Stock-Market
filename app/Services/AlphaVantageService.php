<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AlphaVantageServiceInterface;
use App\DTOs\StockQuote;
use App\DTOs\StockSearchMatch;
use App\Exceptions\Domain\ApiRateLimitExceededException;
use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\TickerNotFoundException;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelData\DataCollection;

final readonly class AlphaVantageService implements AlphaVantageServiceInterface
{
  private string $apiKey;
  private string $baseUrl;

  public function __construct()
  {
    $this->apiKey = (string) config('services.alphavantage.key');
    $this->baseUrl = 'https://www.alphavantage.co/query';
  }

  /**
   * @return Collection<int, StockSearchMatch>
   */
  public function searchTicker(string $keywords): Collection
  {
    $data = $this->request([
      'function' => 'SYMBOL_SEARCH',
      'keywords' => $keywords,
    ]);

    if (!$data || !isset($data['bestMatches'])) {
      return collect();
    }

    return StockSearchMatch::collect($data['bestMatches'], DataCollection::class)->toCollection();
  }

  public function getQuote(string $symbol): ?StockQuote
  {
    $symbol = strtoupper($symbol);

    // Cache quotes for 5 minutes to avoid redundant API calls and respect rate limits
    return cache()->remember("quote_{$symbol}", 300, function () use ($symbol) {
      $data = $this->request([
        'function' => 'GLOBAL_QUOTE',
        'symbol' => $symbol,
      ]);

      if (!$data || !isset($data['Global Quote']) || empty($data['Global Quote'])) {
        throw new TickerNotFoundException("Ticker '{$symbol}' not found on AlphaVantage.");
      }

      try {
        return StockQuote::from($data);
      } catch (Exception $e) {
        Log::error("Failed to parse AlphaVantage quote", ['symbol' => $symbol, 'error' => $e->getMessage()]);
        return null;
      }
    });
  }

  protected function request(array $params): ?array
  {
    try {
      $response = Http::timeout(config('settings.alphavantage.timeout', 15))
        ->connectTimeout(config('settings.alphavantage.connect_timeout', 5))
        ->get($this->baseUrl, array_merge($params, [
          'apikey' => $this->apiKey,
        ]));

      if ($response->failed()) {
        $msg = "AlphaVantage API request failed with status: " . $response->status();
        Log::error($msg, ['params' => $params]);
        throw new DomainException($msg);
      }

      $data = $response->json();

      // 🚀 Handle Rate Limit strictly
      if (isset($data['Note']) && str_contains($data['Note'], 'frequency')) {
        Log::warning("AlphaVantage API Rate Limit Hit", ['note' => $data['Note']]);
        throw new ApiRateLimitExceededException("AlphaVantage rate limit exceeded: " . $data['Note']);
      }

      if (isset($data['Error Message'])) {
        $msg = "AlphaVantage API error message: " . $data['Error Message'];
        Log::error($msg, ['params' => $params]);
        throw new DomainException($msg);
      }

      return $data;
    } catch (Exception $e) {
      if ($e instanceof DomainException) {
        throw $e;
      }
      Log::error("AlphaVantage Service Exception", ['error' => $e->getMessage()]);
      throw new DomainException("Service unavailable: " . $e->getMessage(), 0, $e);
    }
  }
}
