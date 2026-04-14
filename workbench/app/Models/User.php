<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Lararoxy\Contracts\AuthModel;
use Lararoxy\Contracts\TokenPayload;
use Laravel\Sanctum\Contracts\HasAbilities;
use Laravel\Sanctum\HasApiTokens;
use Workbench\Database\Factories\UserFactory;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements AuthModel
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tokenPayloadClass(): string
    {
        return UserTokenPayload::class;
    }
}

/**
 * Example TokenPayload for the workbench test user.
 */
class UserTokenPayload implements TokenPayload
{
    public function __construct(
        public readonly int $userId,
        public readonly string $email,
        public readonly array $abilities,
    ) {}

    public static function fromAuth(AuthModel $model, ?HasAbilities $accessToken = null): static
    {
        return new static(
            userId: $model->id,
            email: $model->email,
            abilities: $accessToken?->abilities ?? ['*'],
        );
    }

    public function authorize(string $routeName, array $routeParams): bool
    {
        return true;
    }

    public function upstreamHeaders(): array
    {
        return [
            'X-User-Id' => (string) $this->userId,
            'X-User-Email' => $this->email,
        ];
    }

    public function resolve(string $key): mixed
    {
        return match ($key) {
            'user_id' => $this->userId,
            'email' => $this->email,
            default => null,
        };
    }
}
