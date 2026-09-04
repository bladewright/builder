<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Sign in') }} | Bladewright</title>
    <link rel="stylesheet" href="@bwasset('bladewright.css')">
    {{-- **Decided before rendering**, and dark unless somebody said otherwise. --}}
    <script>
        try {
            document.documentElement.dataset.bwTheme = localStorage.getItem('bw-theme') || 'dark'
        } catch (e) {}
    </script>
</head>
<body class="grid min-h-screen place-items-center">
    <main class="w-[min(28rem,100%)] px-5 py-8">
        <div class="mb-5 flex items-baseline gap-2.5">
            <strong class="text-lg tracking-wide">Bladewright</strong>
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ config('app.name') }}</span>
        </div>

        @isset($denied)
            {{-- Signed in already. What is missing is the role. --}}
            <div class="mt-4 rounded-xl border border-gray-200 bg-white p-6 first:mt-0 dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100">{{ $denied }}</div>
                <p class="mt-1 text-[0.8125rem] text-gray-500 dark:text-gray-400">
                    {{ __('Ask the site administrator to give you a role.') }}
                </p>
                <form method="post" action="{{ route('bladewright.admin.logout') }}">
                    @csrf
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <span class="flex-1"></span>
                        <button type="submit" class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-[0.8125rem] font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Sign in as someone else') }}</button>
                    </div>
                </form>
            </div>
        @else
            <div class="mt-4 rounded-xl border border-gray-200 bg-white p-6 first:mt-0 dark:border-gray-800 dark:bg-gray-900">
                @if ($errors->any())
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100">{{ $errors->first() }}</div>
                @endif

                <form method="post" action="{{ route('bladewright.admin.login') }}">
                    @csrf

                    <div class="flex flex-col items-stretch gap-1.5 py-3">
                        <label class="text-[0.8125rem] font-semibold text-gray-500 dark:text-gray-400" for="bw-email">{{ __('Email address') }}</label>
                        <input id="bw-email" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:outline-2 focus:outline-offset-1 focus:outline-bw-accent dark:border-gray-700 dark:bg-gray-900 flex-1" type="email" name="email"
                               value="{{ old('email') }}" required autofocus autocomplete="username">
                    </div>

                    <div class="flex flex-col items-stretch gap-1.5 py-3">
                        <label class="text-[0.8125rem] font-semibold text-gray-500 dark:text-gray-400" for="bw-password">{{ __('Password') }}</label>
                        <input id="bw-password" class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:outline-2 focus:outline-offset-1 focus:outline-bw-accent dark:border-gray-700 dark:bg-gray-900 flex-1" type="password" name="password"
                               required autocomplete="current-password">
                    </div>

                    <div class="flex flex-col items-stretch gap-1.5 py-3">
                        <label class="text-[0.8125rem] font-semibold text-gray-500 dark:text-gray-400" for="bw-remember">{{ __('Stay signed in') }}</label>
                        <input id="bw-remember" type="checkbox" name="remember" value="1">
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <span class="flex-1"></span>
                        <button type="submit" class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-linear-to-tl from-bw-accent-2 to-bw-accent px-3 py-1.5 text-[0.8125rem] font-semibold text-white shadow-xs transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-bw-accent">{{ __('Sign in') }}</button>
                    </div>
                </form>
            </div>
        @endisset

        <p class="mt-5 text-center text-[0.8125rem] text-gray-500 dark:text-gray-400">{{ __('This is the admin for this site.') }}</p>
    </main>
</body>
</html>
