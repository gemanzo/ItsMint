<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Invitation;
use Carbon\Carbon;

final class CreateAlphaInvitesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'itsmint:create-invites 
                            {--count=1 : Number of invites to create}
                            {--email= : Bind invite to specific email}
                            {--days=30 : Days until expiration}
                            {--uses=1 : Maximum number of uses per invite}
                            {--name= : Name of the person creating the invite}
                            {--inviter-email= : Email of the person creating the invite}
                            {--batch-name= : Name for this batch of invites}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create alpha testing invitation codes for the app';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $count = (int) $this->option('count');
        $email = $this->option('email');
        $days = (int) $this->option('days');
        $uses = (int) $this->option('uses');
        $name = $this->option('name');
        $inviterEmail = $this->option('inviter-email');
        $batchName = $this->option('batch-name');

        if ($count < 1 || $count > 100) {
            $this->error('Count must be between 1 and 100');
            return 1;
        }

        $this->info("Creating {$count} alpha invite(s)...");

        $expiresAt = $days > 0 ? now()->addDays($days) : null;
        $metadata = [];

        if ($batchName) {
            $metadata['batch_name'] = $batchName;
        }

        $invites = [];
        $bar = $this->output->createProgressBar($count);

        for ($i = 0; $i < $count; $i++) {
            $invite = Invitation::createAlphaInvite(
                email: $email,
                inviterName: $name,
                inviterEmail: $inviterEmail,
                maxUses: $uses,
                expiresAt: $expiresAt,
                metadata: $metadata
            );

            $invites[] = $invite;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Display results
        $this->info('✅ Alpha invites created successfully!');
        $this->newLine();

        $headers = ['Code', 'Email', 'Expires', 'Max Uses', 'Status'];
        $rows = [];

        foreach ($invites as $invite) {
            $rows[] = [
                $invite->code,
                $invite->email ?? 'Any',
                $invite->expires_at ? $invite->expires_at->format('Y-m-d H:i') : 'Never',
                $invite->max_uses,
                $invite->is_active ? '✅ Active' : '❌ Inactive'
            ];
        }

        $this->table($headers, $rows);

        if ($count === 1) {
            $invite = $invites[0];
            $this->newLine();
            $this->info('🔗 Registration URL:');
            $this->line(route('register') . '?invite=' . $invite->code);
        }

        // Summary
        $this->newLine();
        $this->info('📊 Summary:');
        $this->line("• Total invites created: {$count}");
        $this->line("• Expires in: " . ($days > 0 ? "{$days} days" : 'Never'));
        $this->line("• Max uses per invite: {$uses}");
        if ($batchName) {
            $this->line("• Batch name: {$batchName}");
        }

        return 0;
    }
}
