<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'omiquiz:create-admin';

    protected $description = 'Create an omiquiz administrator using private password prompts';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run this command in an interactive terminal to enter the password securely.');

            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Administrator name'));
        $email = Str::lower(trim((string) $this->ask('Administrator email')));
        $password = (string) $this->secret('Password (at least 12 characters)');
        $confirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            $this->error('An account with that email already exists. No account was changed.');

            return self::FAILURE;
        }

        try {
            $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
            $user->role = User::ROLE_ADMIN;
            $user->save();
        } catch (UniqueConstraintViolationException $exception) {
            $this->error('An account with that email already exists. No account was changed.');

            return self::FAILURE;
        }

        $this->info('Administrator created. Sign in at /login.');

        return self::SUCCESS;
    }
}
