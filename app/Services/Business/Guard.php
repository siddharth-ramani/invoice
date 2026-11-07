<?php

namespace App\Services\Business;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Guard
{
    public static function vendor(User $actor): User
    {
        abort_unless($actor->role === 'vendor', 403);

        return User::whereKey($actor->id)->where('role', 'vendor')->lockForUpdate()->firstOrFail();
    }

    public static function version(Model $model): string
    {
        $attrs = $model->getAttributes();
        ksort($attrs);

        return hash_hmac('sha256', json_encode($attrs, JSON_THROW_ON_ERROR), config('app.key'));
    }

    public static function assertVersion(Model $model, string $version): void
    {
        if (! hash_equals(self::version($model), $version)) {
            self::fail('original_version', 'This record changed in another tab. Reload and review the latest saved version.');
        }
    }

    public static function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }

    public static function admin(): ?int
    {
        return request()->hasSession() ? request()->session()->get('bizpilot.impersonation.admin_id') : null;
    }
}
