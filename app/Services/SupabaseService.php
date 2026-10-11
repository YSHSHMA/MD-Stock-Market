<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class SupabaseService
{
  private string $url;
  private string $key;

  public function __construct()
  {
    $this->url = config('services.supabase.url') ?? throw new \RuntimeException('Supabase URL missing');
    $this->key = config('services.supabase.key') ?? throw new \RuntimeException('Supabase Key missing');
  }

  public function client(): PendingRequest
  {
    return Http::withHeaders([
      'apikey' => $this->key,
      'Authorization' => 'Bearer ' . $this->key,
    ])->baseUrl($this->url . '/rest/v1');
  }

  /**
   * Example: Fetch all records from a table via API
   */
  public function from(string $table): Response
  {
    return $this->client()->get($table);
  }
}
