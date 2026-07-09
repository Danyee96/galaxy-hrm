<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;

class EmployeeIdService
{
    public static function prefixFor(User|int|null $creator): string
    {
        $user = $creator instanceof User ? $creator : User::find($creator);
        $name = strtoupper((string) ($user?->name ?? 'CO'));
        $letters = preg_replace('/[^A-Z]/', '', $name) ?? '';

        if (strlen($letters) >= 2) {
            return substr($letters, 0, 2);
        }

        $fallback = preg_replace('/[^A-Z0-9]/', '', $name) ?: 'CO'.($user?->id ?? '');

        return strtoupper(str_pad(substr($fallback, 0, 2), 2, 'X'));
    }

    public static function normalize(string|int|null $employeeId, User|int|null $creator): string
    {
        $employeeId = trim((string) $employeeId);
        if ($employeeId === '') {
            return '';
        }

        $prefix = self::prefixFor($creator);

        if (preg_match('/^'.preg_quote($prefix, '/').'-/i', $employeeId)) {
            return strtoupper($prefix).'-'.substr($employeeId, strlen($prefix) + 1);
        }

        $employeeId = preg_replace('/^[A-Za-z]{2}-/', '', $employeeId) ?? $employeeId;

        return strtoupper($prefix).'-'.$employeeId;
    }

    public static function generate(User|int|null $creator): string
    {
        $prefix = self::prefixFor($creator);
        $creatorId = $creator instanceof User ? $creator->id : $creator;
        $lastNumber = Employee::where('employee_id', 'like', $prefix.'-%')
            ->when($creatorId, fn ($query) => $query->where('created_by', $creatorId))
            ->pluck('employee_id')
            ->map(function (string $employeeId) use ($prefix) {
                $suffix = substr($employeeId, strlen($prefix) + 1);

                return ctype_digit($suffix) ? (int) $suffix : 0;
            })
            ->max() ?? 0;

        $nextNumber = $lastNumber + 1;

        do {
            $employeeId = $prefix.'-'.$nextNumber;
            $nextNumber++;
        } while (Employee::where('employee_id', $employeeId)->exists());

        return $employeeId;
    }
}
