<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Domain Monitor — Сервіс автоматичного моніторингу</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-dots { background-image: radial-gradient(#e5e7eb 1px, transparent 1px); background-size: 20px 20px; }
    </style>
</head>
<body class="antialiased bg-gray-50 bg-dots">
<div class="relative min-h-screen flex flex-col items-center justify-center">

    @if (Route::has('login'))
        <div class="absolute top-0 right-0 p-6 text-right z-10">
            @auth
                <a href="{{ url('/domains') }}" class="font-semibold text-gray-600 hover:text-blue-600 transition">Панель керування</a>
            @else
                <a href="{{ route('login') }}" class="font-semibold text-gray-600 hover:text-blue-600 transition">Увійти</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="ml-6 font-semibold px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition shadow-md">Реєстрація</a>
                @endif
            @endauth
        </div>
    @endif

    <div class="max-w-4xl mx-auto px-6 text-center">
        <div class="mb-8 flex justify-center">
            <div class="p-3 bg-blue-100 rounded-2xl shadow-inner">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
            </div>
        </div>

        <h1 class="text-5xl md:text-6xl font-bold text-gray-900 mb-6 tracking-tight">
            Моніторинг доменів <span class="text-blue-600">24/7</span>
        </h1>

        <p class="text-xl text-gray-600 mb-10 leading-relaxed max-w-2xl mx-auto">
            Професійний інструмент для відстеження доступності ваших ресурсів. Автоматичні перевірки, детальна історія логів та миттєві сповіщення.
        </p>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="{{ route('register') }}" class="w-full sm:w-auto px-8 py-4 bg-gray-900 text-white text-lg font-semibold rounded-xl hover:bg-gray-800 transition shadow-xl">
                Почати роботу
            </a>
            <div class="flex items-center text-gray-500 font-medium">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                Воркер налаштовано
            </div>
        </div>

        <div class="mt-20 grid grid-cols-1 md:grid-cols-3 gap-8 text-left">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="text-blue-600 mb-3 font-bold uppercase text-xs tracking-widest">Автоматизація</div>
                <h3 class="text-lg font-semibold mb-2">Розумні інтервали</h3>
                <p class="text-gray-500 text-sm">Налаштовуйте перевірки від 1 хвилини до 24 годин — система зробить все сама.</p>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="text-blue-600 mb-3 font-bold uppercase text-xs tracking-widest">Логування</div>
                <h3 class="text-lg font-semibold mb-2">Детальна історія</h3>
                <p class="text-gray-500 text-sm">Зберігаємо кожен HTTP-код відповіді та час затримки сервера.</p>
            </div>
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="text-blue-600 mb-3 font-bold uppercase text-xs tracking-widest">API</div>
                <h3 class="text-lg font-semibold mb-2">GET та HEAD методи</h3>
                <p class="text-gray-500 text-sm">Вибирайте найбільш зручний метод запиту для кожного вашого домену.</p>
            </div>
        </div>
    </div>

    <footer class="mt-auto py-8 text-gray-400 text-sm font-medium">
        Laravel v{{ Illuminate\Foundation\Application::VERSION }} (PHP v{{ PHP_VERSION }}) • 2026
    </footer>
</div>
</body>
</html>
