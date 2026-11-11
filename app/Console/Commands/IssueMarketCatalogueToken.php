<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;

class IssueMarketCatalogueToken extends Command
{
    protected $signature = 'market:token {admin? : Admin id or email; the first admin when left out}
                            {--name=catalogue : A label for the token}
                            {--plain : Print the token alone, for a script to read}';

    protected $description = 'Issue the token a computer inside the Kingdom uses to send the open data catalogue here';

    public function handle(): int
    {
        $needle = $this->argument('admin');

        $admin = Admin::query()
            ->when(is_numeric($needle), fn ($query) => $query->whereKey((int) $needle))
            ->when(is_string($needle) && ! is_numeric($needle), fn ($query) => $query->where('email', $needle))
            ->orderBy('id')
            ->first();

        if ($admin === null) {
            $this->components->error('No admin matched.');

            return self::FAILURE;
        }

        // One token per label: issuing again replaces the old one, so a token
        // that was pasted somewhere it should not have been is revoked by
        // running this once more.
        $admin->tokens()->where('name', (string) $this->option('name'))->delete();

        $token = $admin->createToken((string) $this->option('name'), [Admin::MARKET_CATALOGUE_TOKEN]);

        if ($this->option('plain')) {
            $this->line($token->plainTextToken);

            return self::SUCCESS;
        }

        $this->components->info("Token issued for {$admin->email}. It can send the catalogue and nothing else.");
        $this->line('  <fg=gray>Copy these into the .env of the computer that runs `market:check --push` — the token is shown once:</>');
        $this->newLine();
        $this->line('  MARKET_PUSH_URL='.route('api.market.catalogue'));
        $this->line('  MARKET_PUSH_TOKEN='.$token->plainTextToken);
        $this->newLine();

        return self::SUCCESS;
    }
}
