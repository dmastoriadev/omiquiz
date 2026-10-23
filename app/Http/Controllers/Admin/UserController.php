<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::admins()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function create(): View
    {
        return view('admin.users.create', ['user' => new User]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(12)],
        ]);

        $user = new User($validated);
        $user->role = User::ROLE_ADMIN;
        $user->save();

        return redirect()->route('admin.users.index')->with('success', 'Administrator created.');
    }

    public function edit(User $user): View
    {
        abort_unless($user->isAdmin(), 404);

        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isAdmin(), 404);

        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'string', 'confirmed', PasswordRule::min(12)],
        ]);

        $user->fill(collect($validated)->only(['name', 'email'])->all());
        $user->role = User::ROLE_ADMIN;

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
            $user->remember_token = Str::random(60);
        }

        $user->save();

        return redirect()->route('admin.users.index')->with('success', 'Administrator updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->isAdmin(), 404);

        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own administrator account.',
            ]);
        }

        DB::transaction(function () use ($user): void {
            // All deletion requests acquire the administrator rows in ID order.
            $admins = User::admins()->orderBy('id')->lockForUpdate()->get();
            $target = $admins->firstWhere('id', $user->id);

            abort_unless($target, 404);

            if ($admins->count() <= 1) {
                throw ValidationException::withMessages([
                    'user' => 'At least one administrator must remain.',
                ]);
            }

            $target->delete();
        });

        return redirect()->route('admin.users.index')->with('success', 'Administrator deleted.');
    }

    public function sendResetPasswordEmail(User $user): RedirectResponse
    {
        abort_unless($user->isAdmin(), 404);

        $status = Password::sendResetLink(['email' => $user->email]);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', 'Password reset email sent.')
            : back()->withErrors(['email' => __($status)]);
    }
}
