<?php

namespace App\Console\Commands;

use App\Bot\Commands;
use Illuminate\Console\Command;

class BotRegisterCommands extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:register-commands';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Registers Discord Bot slash commands';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Commands::registerCommands();
    }
}
