<?php

namespace App\Services\ArcherJourney;

use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class FeatureFlagService
{
    public const CACHE_KEY = 'archer_journey.feature_flags';

    public function enabled(string $key, User|int|null $user = null): bool
    {
        $flag = $this->all()[$key] ?? null;
        if (! $flag || ! $flag['is_enabled']) {
            return false;
        }

        $rollout = (int) $flag['rollout_percentage'];
        if ($rollout >= 100 || $user === null) {
            return true;
        }

        if ($rollout <= 0) {
            return false;
        }

        $userId = $user instanceof User ? $user->id : $user;
        $bucket = abs(crc32($key.':'.$userId)) % 100;

        return $bucket < $rollout;
    }

    public function config(string $key, ?string $path = null, mixed $default = null): mixed
    {
        $config = $this->all()[$key]['config'] ?? [];

        return $path === null ? $config : data_get($config, $path, $default);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected function all(): array
    {
        if (! Schema::hasTable('feature_flags')) {
            return [];
        }

        return Cache::remember(self::CACHE_KEY, now()->addMinute(), fn () => FeatureFlag::query()
            ->get()
            ->mapWithKeys(fn (FeatureFlag $flag) => [
                $flag->key => [
                    'is_enabled' => $flag->is_enabled,
                    'rollout_percentage' => $flag->rollout_percentage,
                    'config' => $flag->config ?? [],
                ],
            ])
            ->all());
    }
}
