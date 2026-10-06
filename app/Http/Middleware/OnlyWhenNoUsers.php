<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registrasi web hanya untuk pemilik pertama (saat belum ada pengguna).
 * Akun berikutnya dibuat owner lewat `php artisan user:add`.
 */
class OnlyWhenNoUsers
{
    public function handle(Request $request, Closure $next): Response
    {
        if (User::query()->exists()) {
            if ($request->isMethod('get')) {
                abort(404);
            }

            return redirect()->route('login')
                ->withErrors(['email' => 'Registrasi ditutup. Minta owner membuatkan akun untuk Anda.']);
        }

        return $next($request);
    }
}
