<div>
    <label for="name" class="block text-sm font-medium text-gray-700">Nome</label>
    <input type="text" name="name" id="name" value="{{ old('name', $user->name ?? '') }}" required
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
    <input type="email" name="email" id="email" value="{{ old('email', $user->email ?? '') }}" required
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="role" class="block text-sm font-medium text-gray-700">Perfil</label>
    <select name="role" id="role" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @foreach ($roles as $value => $label)
            <option value="{{ $value }}" @selected(old('role', $user->role ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @error('role')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    <p class="mt-1 text-xs text-gray-500">Garçom acessa apenas a área de pedidos (/garcom).</p>
</div>

<div>
    <label for="password" class="block text-sm font-medium text-gray-700">
        Senha @isset($user)<span class="text-gray-400 font-normal">(deixe vazio para manter)</span>@endisset
    </label>
    <input type="password" name="password" id="password" @empty($user) required @endempty
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirmar senha</label>
    <input type="password" name="password_confirmation" id="password_confirmation"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
</div>
