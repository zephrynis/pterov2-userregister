<?php

declare(strict_types=1);

namespace UserRegister\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use UserRegister\Services\Registration;

final class PruneRequests extends Command
{
    protected $signature = 'userregister:prune';
    protected $description = 'Remove expired UserRegister verification requests.';

    public function handle(): int
    {
        $deleted = DB::table(Registration::TABLE)->where('expires_at', '<=', now())->delete();
        $this->info('Removed '.$deleted.' expired verification requests.');

        return self::SUCCESS;
    }
}
