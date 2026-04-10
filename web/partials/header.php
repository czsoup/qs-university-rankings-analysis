<?php
/**
 * Partial : en-tête HTML commun à toutes les pages.
 * Usage : require_once __DIR__ . '/partials/header.php';
 * Variable attendue : $pageTitle (string) — titre de la page courante.
 */
$pageTitle = $pageTitle ?? 'QS Rankings';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$nav = [
    'index'          => ['label' => 'Accueil',        'icon' => 'fa-solid fa-house'],
    'recommandation' => ['label' => 'Recommandation', 'icon' => 'fa-solid fa-bullseye'],
    'universite'     => ['label' => 'Université',      'icon' => 'fa-solid fa-graduation-cap'],
    'pays'           => ['label' => 'Pays',            'icon' => 'fa-solid fa-earth-europe'],
    'comparaison'    => ['label' => 'Comparaison',     'icon' => 'fa-solid fa-scale-balanced'],
    'stats'          => ['label' => 'Stats',           'icon' => 'fa-solid fa-chart-bar'],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — QS Rankings</title>

    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        navy:   '#1E3A5F',
                        accent: '#E67E22',
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome 6 Free (CDN) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Chart.js (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <!-- Feuille de style locale — toutes les pages sont dans web/ -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">

<!-- Navigation fixe -->
<nav class="bg-navy shadow-lg sticky top-0 z-50" aria-label="Navigation principale">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">

            <!-- Logo / Brand -->
            <a href="index.php" class="flex items-center gap-2 text-white font-bold text-lg tracking-wide hover:text-accent transition-colors">
                <span class="text-accent font-black text-2xl">QS</span>
                <span class="hidden sm:inline">World Rankings</span>
            </a>

            <!-- Liens desktop -->
            <div class="hidden md:flex items-center gap-1">
                <?php foreach ($nav as $page => $info): ?>
                    <a href="<?= $page ?>.php"
                       class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium transition-colors
                              <?= $currentPage === $page
                                  ? 'bg-accent text-white'
                                  : 'text-gray-200 hover:bg-white/10 hover:text-white' ?>">
                        <i class="<?= $info['icon'] ?> w-4 text-center" aria-hidden="true"></i>
                        <?= $info['label'] ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Menu hamburger mobile -->
            <button id="menu-toggle" class="md:hidden text-gray-200 hover:text-white focus:outline-none"
                    aria-label="Ouvrir le menu" aria-expanded="false" aria-controls="mobile-menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Menu mobile (masqué par défaut) -->
    <div id="mobile-menu" class="hidden md:hidden bg-navy border-t border-white/10">
        <div class="px-2 pt-2 pb-3 space-y-1">
            <?php foreach ($nav as $page => $info): ?>
                <a href="<?= $page ?>.php"
                   class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium
                          <?= $currentPage === $page
                              ? 'bg-accent text-white'
                              : 'text-gray-200 hover:bg-white/10 hover:text-white' ?>">
                    <i class="<?= $info['icon'] ?> w-4 text-center" aria-hidden="true"></i>
                    <?= $info['label'] ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>

<!-- Contenu principal -->
<main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

<script>
document.getElementById('menu-toggle').addEventListener('click', function () {
    const menu = document.getElementById('mobile-menu');
    const isOpen = !menu.classList.contains('hidden');
    menu.classList.toggle('hidden', isOpen);
    this.setAttribute('aria-expanded', String(!isOpen));
});
</script>
