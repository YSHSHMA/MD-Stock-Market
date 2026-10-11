<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

use App\Contracts\EloquentUserServiceInterface;

final readonly class EloquentUserService implements EloquentUserServiceInterface
{
  public function getOrCreateTelegramUser(int $telegramId, string $username, string $firstName, ?string $lastName = null): User
  {
    return DB::transaction(function () use ($telegramId, $username, $firstName, $lastName) {
      $user = User::where('telegram_id', $telegramId)->first();
      $fullName = trim($firstName . ' ' . ($lastName ?? ''));

      if ($user) {
        $user->update([
          'name' => $fullName,
          'username' => $username,
        ]);
        return $user;
      }

      return User::create([
        'telegram_id' => $telegramId,
        'name' => $fullName,
        'username' => $username,
        'password' => Hash::make(Str::random(16)),
      ]);
    });
  }

  public function findByTelegramId(int $telegramId): ?User
  {
    return User::where('telegram_id', (string) $telegramId)->first();
  }

  public function generateLinkingToken(string $userId): string
  {
    $token = 'lnk_' . Str::random(28);
    // Token valid for 30 minutes
    Cache::put("telegram_link_{$token}", $userId, now()->addMinutes(30));
    return $token;
  }

  public function updateTelegramId(string $token, int $telegramId): bool
  {
    $userId = Cache::pull("telegram_link_{$token}");

    if (!$userId) {
      return false;
    }

    return DB::transaction(function () use ($userId, $telegramId) {
      $targetUser = User::find($userId);

      if (!$targetUser) {
        return false;
      }

      // Check if another user already has this telegram_id
      $existingUser = User::where('telegram_id', (string) $telegramId)
        ->where('id', '!=', $userId)
        ->first();

      if ($existingUser) {
        $existingUser->update(['telegram_id' => null]);
      }

      return $targetUser->update(['telegram_id' => (string) $telegramId]);
    });
  }
}
