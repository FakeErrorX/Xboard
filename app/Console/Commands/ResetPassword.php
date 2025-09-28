<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Utils\Helper;
use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ResetPassword extends Command
{
    protected $builder;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reset:password {email} {password?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset user password';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $password = $this->argument('password') ;
        $user = User::where('email', $this->argument('email'))->first();
        if (!$user) abort(500, 'Email does not exist');
        $password = $password ?? Helper::guid(false);
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->password_algo = null;
        if (!$user->save()) abort(500, 'Reset failed');
        $this->info("!!!Reset successful!!!");
        $this->info("New password: {$password}, please change the password as soon as possible.");
    }
}
