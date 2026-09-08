<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;

class IssueTaskApiToken extends Command
{
    protected $signature = 'tasks:token {employee : Employee id or name}
                            {--team : Also allow reading the whole team\'s weekly progress}
                            {--name=personal-site : A label for the token}';

    protected $description = 'Issue an API token scoped to one employee for the owner\'s personal site';

    public function handle(): int
    {
        $needle = (string) $this->argument('employee');

        $employee = Employee::query()
            ->when(is_numeric($needle), fn ($query) => $query->whereKey((int) $needle))
            ->when(! is_numeric($needle), fn ($query) => $query->where('name', 'like', "%{$needle}%"))
            ->first();

        if ($employee === null) {
            $this->components->error("No employee matched [{$needle}].");

            return self::FAILURE;
        }

        $abilities = ['tasks:self'];

        if ($this->option('team')) {
            $abilities[] = 'team:read';
        }

        $token = $employee->createToken((string) $this->option('name'), $abilities);

        $this->components->info("Token issued for {$employee->name} (#{$employee->id}).");
        $this->components->bulletList($abilities);
        $this->newLine();
        $this->line('  <fg=gray>Copy this into the personal site .env — it is shown once:</>');
        $this->newLine();
        $this->line('  COMPANY_TASKS_TOKEN='.$token->plainTextToken);
        $this->newLine();

        return self::SUCCESS;
    }
}
