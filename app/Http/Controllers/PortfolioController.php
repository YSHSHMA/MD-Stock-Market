<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Http\Resources\TransactionResource;
use App\Http\Requests\StoreTransactionRequest;
use App\Contracts\EloquentTransactionServiceInterface;
use App\Contracts\EloquentTickerServiceInterface;
use App\Contracts\StandardPortfolioCalculatorInterface;
use App\Contracts\SettingServiceInterface;
use App\Contracts\EloquentUserServiceInterface;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PortfolioController extends Controller
{
    public function __construct(
        private readonly EloquentTransactionServiceInterface $transactionService,
        private readonly EloquentTickerServiceInterface $tickerService,
        private readonly SettingServiceInterface $settingService,
        private readonly EloquentUserServiceInterface $userService,
        private readonly StandardPortfolioCalculatorInterface $portfolioCalculator
    ) {}

    public function telegramLink(): JsonResponse
    {
        $token = $this->userService->generateLinkingToken(Auth::id());
        $botName = config('services.telegram.bot_name');

        if (!$botName) {
            return response()->json(['error' => 'Telegram bot not configured'], 500);
        }

        $url = "https://t.me/{$botName}?start={$token}";

        return response()->json(['url' => $url]);
    }

    public function index(): AnonymousResourceCollection
    {
        $transactions = $this->transactionService->getHistory(Auth::id(), 20);

        return TransactionResource::collection($transactions);
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $summary = $this->portfolioCalculator->calculate($user);
        $positions = $this->transactionService->getUserHoldings($user->id);
        $pricedSymbols = collect($summary?->holdings ?? [])->pluck('symbol');

        return response()->json([
            'data' => [
                'currency' => $this->settingService->getCurrencySettings(),
                'totals' => [
                    'value' => $summary?->totalValue ?? 0.0,
                    'cost' => $summary?->totalCost ?? 0.0,
                    'profit_loss' => $summary?->totalPL ?? 0.0,
                    'profit_loss_percent' => $summary?->totalPLPercent ?? 0.0,
                    'day_change' => $summary?->totalDayChange ?? 0.0,
                    'month_change' => $summary?->totalMTDChange ?? 0.0,
                    'year_change' => $summary?->totalYTDChange ?? 0.0,
                ],
                'holdings' => collect($summary?->holdings ?? [])->map(fn ($holding) => [
                    'symbol' => $holding->symbol,
                    'quantity' => $holding->quantity,
                    'current_price' => $holding->currentPrice,
                    'average_cost' => $holding->avgCost,
                    'market_value' => $holding->currentValue,
                    'profit_loss' => $holding->totalPL,
                    'profit_loss_percent' => $holding->totalPLPercent,
                    'day_change' => $holding->dayChange,
                    'last_updated' => $holding->lastUpdated,
                ])->values(),
                'unpriced_symbols' => $positions
                    ->reject(fn ($position) => $pricedSymbols->contains($position->ticker->symbol))
                    ->map(fn ($position) => [
                        'symbol' => $position->ticker->symbol,
                        'quantity' => (float) $position->total_quantity,
                    ])
                    ->values(),
            ],
        ]);
    }

    public function store(StoreTransactionRequest $request): TransactionResource
    {
        $validated = $request->validated();

        $ticker = $this->tickerService->getOrCreateTicker($validated['symbol']);

        $transaction = $this->transactionService->createTransaction(
            Auth::id(),
            $ticker->id,
            $validated['type'],
            (float) $validated['quantity'],
            (float) $validated['price'],
            $validated['date'] ?? null
        );

        return new TransactionResource($transaction);
    }

    public function destroy(Transaction $transaction): JsonResponse
    {
        $deleted = $this->transactionService->deleteTransaction($transaction, Auth::id());

        if (!$deleted) {
            return response()->json(['message' => 'Unauthorized or failed'], 403);
        }

        return response()->json(['message' => 'Transaction deleted']);
    }
}
