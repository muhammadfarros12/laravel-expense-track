<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Expense;
use Carbon\Carbon;
use Exception;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BudgetAIService
{
    public function getBudgetRecomendation(?int $categoryId, int $userId, int $targetMonth, int $targetYear)
    {
        try {
            // get historical spending data
            $historicalData = $this->getHistoricalSpendingData($categoryId, $userId, $targetMonth, $targetYear);

            // create prompt (paling akhir dibuat)
            // if (empty($historicalData)) {
            //     return null;
            // }
            if (($historicalData['total_expense_count'] ?? 0) === 0) {
                Log::info('AI skipped: no expense data', [
                    'user_id' => $userId,
                    'month' => $targetMonth,
                    'year' => $targetYear,
                    'category_id' => $categoryId,
                ]);

                return null;
            }

            $prompt = $this->createPrompt($historicalData, $categoryId, $userId, $targetMonth, $targetYear);

            // get AI response (gemini AI)
            $response = Gemini::generativeModel(model: 'gemini-2.0-flash')->generateContent($prompt);

            return $this->parseAIResponse($response->text(), $historicalData);
        } catch (Exception $e) {
            Log::error('Budget AI Recomendation Error', [
                'message' => $e->getMessage(),
                'user_id' => $userId,
                'month' => $targetMonth,
                'year' => $targetYear,
                'category_id' => $categoryId,
            ]);

            throw new RuntimeException($this->formatAIErrorMessage($e->getMessage()), 0, $e);
        }
    }

    public function getHistoricalSpendingData(?int $categoryId, int $userId, int $targetMonth, int $targetYear)
    {
        $expense = [];
        $monthlyTotal = [];
        $totalExpenseCount = 0;

        // get the last 3 month of data [exclude target og the month]
        for ($i = 1; $i <= 3; $i++) {
            $date = Carbon::createFromDate($targetYear, $targetMonth, 1)->subMonths($i);

            $query = Expense::where('user_id', $userId)
                ->whereYear('date', $date->year)
                ->whereMonth('date', $date->month);

            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            $monthlyExpenses = $query->get();
            $count = $monthlyExpenses->count();
            $total = (float) $monthlyExpenses->sum('amount');

            $expense[] = [
                'month' => $date->format('F Y'),
                'total' => $total,
                'count' => $count,
                'expenses' => $monthlyExpenses->pluck('title')->take(10)->toArray(),
            ];
            $monthlyTotal[] = $total;
            $totalExpenseCount += $count;
        }

        return [
            'expenses' => $expense,
            'average' => ! empty($monthlyTotal) ? array_sum($monthlyTotal) / count($monthlyTotal) : 0,
            'min' => ! empty($monthlyTotal) ? min($monthlyTotal) : 0,
            'max' => ! empty($monthlyTotal) ? max($monthlyTotal) : 0,
            'trend' => $this->calculateTrend($monthlyTotal),
            'months_with_data' => count(array_filter($expense, fn (array $month) => $month['count'] > 0)),
            'total_expense_count' => $totalExpenseCount,
        ];

    }

    private function calculateTrend($monthlyTotal)
    {
        if (count($monthlyTotal) < 2) {
            return 'stable';
        }

        // compare the last two months(most recent first and second most recent -oldest month)
        // $recent = $monthlyTotal[0];
        // $oldest = $monthlyTotal[count($monthlyTotal) - 1];
        $recent = $monthlyTotal[0] ?? 0;
        $oldest = end($monthlyTotal) ?: 0;

        if ($oldest == 0) {
            return $recent > 0 ? 'increasing' : 'stable';
        }

        $percentageChange = (($recent - $oldest) / $oldest) * 100;

        if ($percentageChange > 10) {
            return 'increasing';
        } elseif ($percentageChange < -10) {
            return 'decreasing';
        } else {
            return 'stable';
        }

    }

    // create prompt for gemini AI
    private function createPrompt(array $historicalData, ?int $categoryId, int $userId, int $month, int $year)
    {
        $categoryName = $categoryId
            ? Category::query()
                ->whereKey($categoryId)
                ->where('user_id', $userId)
                ->value('name') ?? 'this category'
            : 'overall spending';

        $targetMonth = Carbon::create($year, $month, 1)->format('F Y');

        $prompt = "You are a financial advisor AI specialized in budgeting. Based on the historical spending data and provide a budget recomendation\n\n";

        $prompt .= "Category: {$categoryName}\n";
        $prompt .= "Target Month: {$targetMonth}\n";

        $prompt .= "Historical Spending (last 3 months):\n";

        foreach ($historicalData['expenses'] as $data) {
            $prompt .= "- {$data['month']}: $".number_format($data['total'], 2)." ({$data['count']} expenses)\n";

            if (! empty($data['expenses'])) {
                $prompt .= '  Top Items: '.implode(', ', array_slice($data['expenses'], 0, 5))."\n";
            }
        }

        $prompt .= "\nSpending Statistic:\n";

        $prompt .= '- Average: $'.number_format($historicalData['average'], 2)."\n";
        $prompt .= '- Min: $'.number_format($historicalData['min'], 2)."\n";
        $prompt .= '- Max: $'.number_format($historicalData['max'], 2)."\n";
        $prompt .= '- Trend: '.$historicalData['trend']."\n\n";

        $prompt .= "Based on the above data, provide:\n";
        $prompt .= "1. A recommended budget for the target month.\n";
        $prompt .= "2. A minimum safe amount.\n";
        $prompt .= "3. A maximum comfortable amount.\n";
        $prompt .= "4. A brief explanation (2-3 sentences) why you recommend this amount.\n";
        $prompt .= "5. One actionable tip to stay within budget \n\n";

        $prompt .= "Return JSON ONLY in this exact format:\n";
        $prompt .= "{\"recommended\":1000000,\"min\":500000,\"max\":650000,\"explanation\":\"...\",\"tip\":\"...\"}\n";

        return $prompt;
    }

    private function parseAIResponse(string $response, array $historicalData)
    {
        try {
            // json
            if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
                $json = json_decode($matches[0], true);

                if (is_array($json) && isset($json['recommended'])) {
                    $normalized = $this->normalizeRecommendation($json, $historicalData);

                    if ($normalized !== null) {
                        return $normalized;
                    }
                }
            }

            return $this->getFallbackRecommendation($historicalData);
        } catch (Exception $e) {
            Log::error('Budget AI Response Parsing Error: '.$e->getMessage());

            return $this->getFallbackRecommendation($historicalData);
        }
    }

    private function calculateConfidence(array $historicalData)
    {
        $monthsWithData = $historicalData['months_with_data'] ?? count(array_filter(
            $historicalData['expenses'] ?? [],
            fn (array $month) => ($month['count'] ?? 0) > 0
        ));

        if ($monthsWithData >= 3) {
            return 'high';
        }

        if ($monthsWithData === 2) {
            return 'medium';
        } else {
            return 'low';
        }

    }

    private function getFallbackRecommendation(array $historicalData)
    {
        $average = $historicalData['average'];
        $recommended = round($average * 1.1, 2);

        return [
            'recommended' => $recommended,
            'min' => round($recommended * 0.95, 2),
            'max' => round($recommended * 1.2, 2),
            'explanation' => 'Based on your historical average spending.',
            'tip' => 'Consider reviewing your expenses to identify areas for savings.',
            'confidence' => $this->calculateConfidence($historicalData),
        ];
    }

    private function normalizeRecommendation(array $json, array $historicalData): ?array
    {
        $recommended = $this->extractNumericValue($json['recommended'] ?? null);

        if ($recommended === null || $recommended <= 0) {
            return null;
        }

        $min = $this->extractNumericValue($json['min'] ?? null) ?? round($recommended * 0.9, 2);
        $max = $this->extractNumericValue($json['max'] ?? null) ?? round($recommended * 1.1, 2);

        $min = max(0, min($min, $recommended));
        $max = max($recommended, $max);

        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }

        $explanation = is_string($json['explanation'] ?? null) && trim($json['explanation']) !== ''
            ? trim($json['explanation'])
            : 'Based on your historical spending patterns.';
        $tip = is_string($json['tip'] ?? null) && trim($json['tip']) !== ''
            ? trim($json['tip'])
            : 'Track your expenses regularly to stay within budget.';

        return [
            'recommended' => round($recommended, 2),
            'min' => round($min, 2),
            'max' => round($max, 2),
            'explanation' => $explanation,
            'tip' => $tip,
            'confidence' => $this->calculateConfidence($historicalData),
        ];
    }

    private function extractNumericValue(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = preg_replace('/[^\d.\-]/', '', $value);

        if ($normalized === null || $normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function formatAIErrorMessage(string $message): string
    {
        $normalizedMessage = strtolower($message);

        if (str_contains($normalizedMessage, 'quota exceeded')) {
            return 'Gemini quota exceeded. Check quota or billing in Google AI Studio, then try again.';
        }

        if (
            str_contains($normalizedMessage, 'api key')
            || str_contains($normalizedMessage, 'unauthenticated')
            || str_contains($normalizedMessage, 'permission denied')
            || str_contains($normalizedMessage, 'invalid argument')
        ) {
            return 'Gemini API key is invalid or unauthorized. Check GEMINI_API_KEY in .env.';
        }

        if (
            str_contains($normalizedMessage, 'could not resolve host')
            || str_contains($normalizedMessage, 'failed to connect')
            || str_contains($normalizedMessage, 'connection refused')
            || str_contains($normalizedMessage, 'timed out')
        ) {
            return 'Unable to reach Gemini API. Check internet access, firewall, or request timeout.';
        }

        if (str_contains($normalizedMessage, 'model') && str_contains($normalizedMessage, 'not found')) {
            return 'Gemini model is unavailable. Check the configured model name in BudgetAIService.';
        }

        return 'Gemini AI request failed. Check the application logs for the full error.';
    }

    // check if the user has enough historical data
    public function hasEnoughHistoricalData(?int $categoryId, int $userId, int $targetMonth, int $targetYear)
    {
        $historicalData = $this->getHistoricalSpendingData($categoryId, $userId, $targetMonth, $targetYear);

        return ($historicalData['total_expense_count'] ?? 0) > 0;
    }
}
