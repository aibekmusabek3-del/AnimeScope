<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#141716">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="AnimeScope: каталог аниме, поиск и личная коллекция.">
    <title>AnimeScope / Каталог аниме</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/favicon.svg') }}">
    @vite('resources/js/main.jsx')
</head>
<body>
    <div id="root"></div>
    <noscript>Для работы каталога необходимо включить JavaScript.</noscript>
</body>
</html>
