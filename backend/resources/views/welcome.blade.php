<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <div class="min-h-screen bg-gray-50 flex items-center justify-center">
        <div class="text-center">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">{{ config('app.name', 'Laravel') }}</h1>
            <p class="text-gray-600 mb-8">客户分组管理系统</p>
            <a href="/customer-groups" class="btn btn-primary">进入管理</a>
        </div>
    </div>
</body>
</html>
