<?php

namespace App\Services;

use App\Models\Akademik\AcademicPolicy;

class AcademicPolicyService
{
    private const DEFAULT_POLICIES = [
        'attendance' => [
            'minimum_percentage' => 75,
            'status_weights' => [
                'hadir' => 1,
                'izin' => 0,
                'sakit' => 0,
                'alpa' => 0,
            ],
        ],
        'remedial' => [
            'allowed_krs_detail_statuses' => ['tidak_lulus'],
            'max_attempts' => null,
        ],
        'yudisium' => [
            'require_tugas_akhir_lulus' => false,
        ],
    ];

    public function all(): array
    {
        $stored = AcademicPolicy::query()
            ->get()
            ->keyBy('key')
            ->map(fn(AcademicPolicy $policy) => $policy->value)
            ->all();

        $resolved = [];

        foreach (self::DEFAULT_POLICIES as $key => $defaultValue) {
            $resolved[$key] = $this->mergePolicy($defaultValue, $stored[$key] ?? []);
        }

        return $resolved;
    }

    public function get(string $key): array
    {
        $defaults = self::DEFAULT_POLICIES[$key] ?? [];
        $stored = AcademicPolicy::query()->where('key', $key)->value('value') ?? [];

        return $this->mergePolicy($defaults, $stored);
    }

    public function updateMany(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (!array_key_exists($key, self::DEFAULT_POLICIES)) {
                continue;
            }

            AcademicPolicy::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $this->mergePolicy(self::DEFAULT_POLICIES[$key], $value),
                    'description' => $this->descriptionFor($key),
                ]
            );
        }

        return $this->all();
    }

    private function mergePolicy(array $defaults, mixed $overrides): array
    {
        if (!is_array($overrides)) {
            return $defaults;
        }

        foreach ($overrides as $key => $value) {
            if (is_array($value) && isset($defaults[$key]) && is_array($defaults[$key])) {
                $defaults[$key] = $this->mergePolicy($defaults[$key], $value);
                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }

    private function descriptionFor(string $key): string
    {
        return match ($key) {
            'attendance' => 'Kebijakan presensi minimum dan bobot status kehadiran.',
            'remedial' => 'Kebijakan kelayakan dan batas percobaan remedial.',
            'yudisium' => 'Kebijakan syarat tambahan sebelum yudisium.',
            default => 'Kebijakan akademik.',
        };
    }
}
