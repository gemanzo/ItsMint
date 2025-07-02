<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'email',
        'invited_by_name',
        'invited_by_email',
        'max_uses',
        'used_count',
        'expires_at',
        'used_at',
        'used_by_user_id',
        'metadata',
        'is_active',
        'type',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function usedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }

    // Helper methods
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->is_active
            && ! $this->isExpired()
            && $this->used_count < $this->max_uses;
    }

    public function canBeUsedBy(?string $email = null): bool
    {
        if (! $this->isUsable()) {
            return false;
        }

        // If invitation is bound to specific email
        if ($this->email && $email && $this->email !== $email) {
            return false;
        }

        return true;
    }

    public function markAsUsed(User $user): void
    {
        $this->increment('used_count');
        $this->update([
            'used_at' => now(),
            'used_by_user_id' => $user->id,
        ]);
    }

    public function disable(): void
    {
        $this->update(['is_active' => false]);
    }

    // Static methods for creating invitations
    public static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public static function createAlphaInvite(
        ?string $email = null,
        ?string $inviterName = null,
        ?string $inviterEmail = null,
        int $maxUses = 1,
        ?Carbon $expiresAt = null,
        array $metadata = []
    ): self {
        return self::create([
            'code' => self::generateCode(),
            'email' => $email,
            'invited_by_name' => $inviterName,
            'invited_by_email' => $inviterEmail,
            'max_uses' => $maxUses,
            'expires_at' => $expiresAt ?? now()->addDays(30),
            'metadata' => $metadata,
            'type' => 'alpha',
        ]);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeUsable($query)
    {
        return $query->active()
            ->notExpired()
            ->whereRaw('used_count < max_uses');
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', strtoupper($code));
    }
}
