@php($isEditing = $method !== 'POST')

<form method="POST" action="{{ $action }}" class="max-w-2xl rounded-2xl border border-slate-200 bg-white p-6 sm:p-8">
    @csrf
    @if ($isEditing)
        @method($method)
    @endif

    <div class="space-y-6">
        <div>
            <label for="name" class="oq-label">Full name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" autocomplete="name" maxlength="255" required autofocus class="oq-input">
        </div>
        <div>
            <label for="email" class="oq-label">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" autocomplete="email" maxlength="255" required class="oq-input">
        </div>
        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <label for="password" class="oq-label">{{ $isEditing ? 'New password' : 'Password' }}</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="12" @required(! $isEditing) aria-describedby="password-help" class="oq-input">
                <p id="password-help" class="mt-2 text-xs leading-5 text-slate-500">{{ $isEditing ? 'Leave blank to keep the current password. Otherwise, use at least 12 characters.' : 'Use at least 12 characters.' }}</p>
            </div>
            <div>
                <label for="password_confirmation" class="oq-label">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" @required(! $isEditing) class="oq-input">
            </div>
        </div>
    </div>

    <div class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-6">
        <a href="{{ route('admin.users.index') }}" class="oq-button-secondary">Cancel</a>
        <button type="submit" class="oq-button-primary">{{ $isEditing ? 'Save changes' : 'Create administrator' }}</button>
    </div>
</form>
