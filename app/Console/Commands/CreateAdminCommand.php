<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('admin:create {email? : The admin email address} {--name= : The admin display name} {--force : Reset the password if the admin already exists}')]
#[Description('Create the platform administrator account')]
class CreateAdminCommand extends Command
{
    private const MIN_PASSWORD_LENGTH = 8;

    public function handle(): int
    {
        $email = Str::lower(trim((string) ($this->argument('email') ?? $this->ask('Email address'))));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            $this->error('A valid email address is required.');

            return self::FAILURE;
        }

        $existingAdmin = Admin::query()->where('email', $email)->first();

        if ($existingAdmin !== null && ! $this->option('force')) {
            $this->error("An admin with the email {$email} already exists. Use --force to reset it.");

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?? $this->ask('Name', $existingAdmin?->name ?? 'Administrator')));
        $password = (string) $this->secret('Password');

        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $this->error('The password must be at least '.self::MIN_PASSWORD_LENGTH.' characters.');

            return self::FAILURE;
        }

        if ($password !== (string) $this->secret('Confirm password')) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        Admin::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name !== '' ? $name : 'Administrator', 'password' => $password],
        );

        $this->info($existingAdmin === null ? "Admin {$email} created." : "Admin {$email} updated.");

        return self::SUCCESS;
    }
}
