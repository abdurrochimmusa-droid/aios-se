<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserAddCommand extends Command
{
    protected $signature = 'user:add
        {email : Alamat email akun baru}
        {--name= : Nama tampilan (bawaan: bagian depan email)}
        {--role=viewer : Peran: owner, manager, approver, viewer}
        {--password= : Kata sandi (bawaan: acak 16 karakter, ditampilkan sekali)}';

    protected $description = 'Buatkan akun AIOS-SE (dijalankan owner)';

    public function handle(): int
    {
        $role = UserRole::tryFrom((string) $this->option('role'));

        if ($role === null) {
            $this->error('Peran tidak dikenal: owner, manager, approver, viewer.');

            return 1;
        }

        $email = strtolower((string) $this->argument('email'));

        if (User::where('email', $email)->exists()) {
            $this->error("Email {$email} sudah terdaftar.");

            return 1;
        }

        $password = (string) ($this->option('password') ?: Str::random(16));

        $user = User::create([
            'name' => (string) ($this->option('name') ?: str($email)->before('@')->toString()),
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
        ]);

        $this->info("Akun {$user->email} dibuat dengan peran {$role->value}.");

        if ($this->option('password') === null) {
            $this->line("Kata sandi (ditampilkan sekali): {$password}");
        }

        return 0;
    }
}
