<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('mentionai:create-admin {--name= : Full name} {--email= : Email address} {--password= : Password (min 10 characters)}')]
#[Description('Create an active administrator for the /admin panel')]
class CreateAdminCommand extends Command
{
    public function handle(): int
    {
        $interactive = $this->input->isInteractive();

        $name = $this->option('name') ?: ($interactive ? text('Name', required: true) : null);
        $email = $this->option('email') ?: ($interactive ? text('Email', required: true) : null);
        $password = $this->option('password') ?: ($interactive ? password('Password (min 10 characters)', required: true) : null);

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', Password::min(10)],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_admin' => true,
            'is_active' => true,
        ]);

        $this->components->info("Administrator {$email} created.");

        return self::SUCCESS;
    }
}
