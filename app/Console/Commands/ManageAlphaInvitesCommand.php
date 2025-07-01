<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invitation;
use App\Models\User;

final class ManageAlphaInvitesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'itsmint:manage-invites 
                            {action : Action to perform (list|disable|enable|stats|cleanup)}
                            {--code= : Specific invite code to target}
                            {--status= : Filter by status (active|inactive|used|expired)}
                            {--limit=50 : Limit number of results}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage ItsMint alpha testing invitation codes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listInvites(),
            'disable' => $this->disableInvite(),
            'enable' => $this->enableInvite(),
            'stats' => $this->showStats(),
            'cleanup' => $this->cleanup(),
            default => $this->error("Unknown action: {$action}"),
        };
    }

    private function listInvites(): int
    {
        $query = Invitation::query();

        // Apply filters
        if ($status = $this->option('status')) {
            match ($status) {
                'active' => $query->active()->usable(),
                'inactive' => $query->where('is_active', false),
                'used' => $query->where('used_count', '>', 0),
                'expired' => $query->where('expires_at', '<', now()),
            };
        }

        $limit = (int) $this->option('limit');
        $invites = $query->with('usedByUser')
            ->latest()
            ->limit($limit)
            ->get();

        if ($invites->isEmpty()) {
            $this->info('No invites found.');
            return 0;
        }

        $this->info("Found {$invites->count()} invite(s):");
        $this->newLine();

        $headers = ['Code', 'Email', 'Status', 'Uses', 'Expires', 'Used By', 'Created'];
        $rows = [];

        foreach ($invites as $invite) {
            $status = $this->getInviteStatus($invite);

            $rows[] = [
                $invite->code,
                $invite->email ?? 'Any',
                $status,
                "{$invite->used_count}/{$invite->max_uses}",
                $invite->expires_at ? $invite->expires_at->format('Y-m-d') : 'Never',
                $invite->usedByUser?->name ?? '-',
                $invite->created_at->format('Y-m-d H:i'),
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }

    private function disableInvite(): int
    {
        $code = $this->option('code');
        if (!$code) {
            $code = $this->ask('Enter the invite code to disable:');
        }

        $invite = Invitation::byCode($code)->first();
        if (!$invite) {
            $this->error("Invite code '{$code}' not found.");
            return 1;
        }

        if (!$invite->is_active) {
            $this->info("Invite '{$code}' is already disabled.");
            return 0;
        }

        $invite->disable();
        $this->info("✅ Invite '{$code}' has been disabled.");

        return 0;
    }

    private function enableInvite(): int
    {
        $code = $this->option('code');
        if (!$code) {
            $code = $this->ask('Enter the invite code to enable:');
        }

        $invite = Invitation::byCode($code)->first();
        if (!$invite) {
            $this->error("Invite code '{$code}' not found.");
            return 1;
        }

        if ($invite->is_active) {
            $this->info("Invite '{$code}' is already active.");
            return 0;
        }

        $invite->update(['is_active' => true]);
        $this->info("✅ Invite '{$code}' has been enabled.");

        return 0;
    }

    private function showStats(): int
    {
        $total = Invitation::count();
        $active = Invitation::active()->count();
        $used = Invitation::where('used_count', '>', 0)->count();
        $expired = Invitation::where('expires_at', '<', now())->count();
        $usable = Invitation::usable()->count();

        $totalUsers = User::count();
        $alphaUsers = User::whereHas('invitation')->count();

        $this->info('📊 Alpha Invites Statistics');
        $this->newLine();

        $headers = ['Metric', 'Count'];
        $rows = [
            ['Total Invites', $total],
            ['Active Invites', $active],
            ['Usable Invites', $usable],
            ['Used Invites', $used],
            ['Expired Invites', $expired],
            ['Total Users', $totalUsers],
            ['Alpha Users', $alphaUsers],
        ];

        $this->table($headers, $rows);

        // Top usage stats
        $this->newLine();
        $this->info('🎯 Recent Alpha Registrations');

        $recentUsers = User::whereHas('invitation')
            ->with('invitation')
            ->latest()
            ->limit(5)
            ->get();

        if ($recentUsers->isNotEmpty()) {
            $headers = ['Name', 'Email', 'Invite Code', 'Joined'];
            $rows = [];

            foreach ($recentUsers as $user) {
                $invitation = $user->invitation;
                $rows[] = [
                    $user->name,
                    $user->email,
                    $invitation?->code ?? 'N/A',
                    $user->created_at->format('Y-m-d H:i'),
                ];
            }

            $this->table($headers, $rows);
        } else {
            $this->line('No alpha users found.');
        }

        return 0;
    }

    private function cleanup(): int
    {
        $this->info('🧹 Cleaning up expired and exhausted invites...');

        $expired = Invitation::where('expires_at', '<', now())
            ->where('is_active', true)
            ->count();

        $exhausted = Invitation::whereRaw('used_count >= max_uses')
            ->where('is_active', true)
            ->count();

        if ($expired === 0 && $exhausted === 0) {
            $this->info('No cleanup needed. All invites are current.');
            return 0;
        }

        if ($this->confirm("This will disable {$expired} expired and {$exhausted} exhausted invites. Continue?")) {

            // Disable expired
            Invitation::where('expires_at', '<', now())
                ->where('is_active', true)
                ->update(['is_active' => false]);

            // Disable exhausted
            Invitation::whereRaw('used_count >= max_uses')
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $this->info("✅ Cleanup completed:");
            $this->line("• Disabled {$expired} expired invites");
            $this->line("• Disabled {$exhausted} exhausted invites");
        } else {
            $this->info('Cleanup cancelled.');
        }

        return 0;
    }

    private function getInviteStatus(Invitation $invite): string
    {
        if (!$invite->is_active) {
            return '❌ Disabled';
        }

        if ($invite->isExpired()) {
            return '⏰ Expired';
        }

        if ($invite->used_count >= $invite->max_uses) {
            return '✅ Used';
        }

        if ($invite->used_count > 0) {
            return '🔄 Partial';
        }

        return '🟢 Available';
    }
}
