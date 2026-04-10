<?php
/**
 * index.php — Dashboard de synthèse mondiale (F5)
 * AC-13 : top 10 affiché, 2 graphiques Chart.js visibles
 */
require_once __DIR__ . '/connexion.php';

$pageTitle = 'Tableau de bord';

// Top 10 mondial — dernière édition
$lastEditionId = getLastEditionId($pdo);

$stmtTop10 = $pdo->prepare(
    'SELECT u.nom, u.acronyme, p.nom AS pays, p.code_iso, p.continent,
            t.libelle AS type_univ,
            sq.rang, sq.score_global, sq.score_rep_acad,
            sq.score_employeur, sq.score_ratio,
            u.id_univ
     FROM SCORE_QS sq
     JOIN UNIVERSITE u    ON u.id_univ    = sq.id_univ
     JOIN PAYS p          ON p.id_pays    = u.id_pays
     JOIN TYPE_UNIVERSITE t ON t.id_type  = u.id_type
     JOIN EDITION_QS e    ON e.id_edition = sq.id_edition
     WHERE sq.id_edition = :eid
     ORDER BY sq.rang ASC
     LIMIT 10'
);
$stmtTop10->execute([':eid' => $lastEditionId]);
$top10 = $stmtTop10->fetchAll();

// Répartition par continent (dernière édition)
$stmtContinent = $pdo->prepare(
    'SELECT p.continent, COUNT(*) AS nb
     FROM SCORE_QS sq
     JOIN UNIVERSITE u ON u.id_univ = sq.id_univ
     JOIN PAYS p       ON p.id_pays = u.id_pays
     WHERE sq.id_edition = :eid
     GROUP BY p.continent
     ORDER BY nb DESC'
);
$stmtContinent->execute([':eid' => $lastEditionId]);
$continents = $stmtContinent->fetchAll();

// Répartition par type d'université (dernière édition)
$stmtType = $pdo->prepare(
    'SELECT t.libelle, COUNT(*) AS nb
     FROM SCORE_QS sq
     JOIN UNIVERSITE u    ON u.id_univ  = sq.id_univ
     JOIN TYPE_UNIVERSITE t ON t.id_type = u.id_type
     WHERE sq.id_edition = :eid
     GROUP BY t.libelle
     ORDER BY nb DESC'
);
$stmtType->execute([':eid' => $lastEditionId]);
$types = $stmtType->fetchAll();

// Année de la dernière édition
$stmtYear = $pdo->prepare('SELECT annee FROM EDITION_QS WHERE id_edition = :eid');
$stmtYear->execute([':eid' => $lastEditionId]);
$lastYear = $stmtYear->fetchColumn();

// Statistiques globales
$stats = $pdo->prepare(
    'SELECT COUNT(DISTINCT sq.id_univ) AS nb_univ,
            COUNT(DISTINCT u.id_pays) AS nb_pays,
            AVG(sq.score_global) AS avg_score
     FROM SCORE_QS sq
     JOIN UNIVERSITE u ON u.id_univ = sq.id_univ
     WHERE sq.id_edition = :eid'
);
$stats->execute([':eid' => $lastEditionId]);
$globalStats = $stats->fetch();

require_once __DIR__ . '/partials/header.php';
?>

<!-- Hero section -->
<div class="bg-gradient-to-r from-navy to-blue-800 rounded-2xl text-white p-8 mb-8 shadow-xl">
    <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl sm:text-4xl font-black tracking-tight mb-2">
                QS World University Rankings
            </h1>
            <p class="text-blue-200 text-lg">
                Édition <span class="text-accent font-bold"><?= $lastYear ?></span>
                — Outil d'aide à la décision universitaire
            </p>
        </div>
        <div class="flex gap-6 text-center">
            <div>
                <p class="text-3xl font-black text-accent"><?= $globalStats['nb_univ'] ?></p>
                <p class="text-sm text-blue-200">Universités</p>
            </div>
            <div>
                <p class="text-3xl font-black text-accent"><?= $globalStats['nb_pays'] ?></p>
                <p class="text-sm text-blue-200">Pays</p>
            </div>
            <div>
                <p class="text-3xl font-black text-accent"><?= number_format($globalStats['avg_score'], 1) ?></p>
                <p class="text-sm text-blue-200">Score moyen</p>
            </div>
        </div>
    </div>
</div>

<!-- Liens rapides -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
    <?php
    $quickLinks = [
        ['href' => 'recommandation.php', 'icon' => 'fa-solid fa-bullseye',         'label' => 'Recommandation', 'desc' => 'Classement personnalisé'],
        ['href' => 'universite.php',     'icon' => 'fa-solid fa-graduation-cap',   'label' => 'Université',     'desc' => 'Fiche détaillée'],
        ['href' => 'pays.php',           'icon' => 'fa-solid fa-earth-europe',     'label' => 'Pays',           'desc' => 'Explorer par pays'],
        ['href' => 'comparaison.php',    'icon' => 'fa-solid fa-scale-balanced',   'label' => 'Comparaison',    'desc' => 'Côte à côte'],
        ['href' => 'stats.php',          'icon' => 'fa-solid fa-chart-bar',        'label' => 'Statistiques',   'desc' => '8 analyses SQL'],
    ];
    foreach ($quickLinks as $link): ?>
        <a href="<?= $link['href'] ?>"
           class="bg-white rounded-xl p-4 shadow hover:shadow-md hover:border-accent border-2 border-transparent
                  transition-all group text-center">
            <i class="<?= $link['icon'] ?> text-2xl text-navy group-hover:text-accent transition-colors mb-2 block" aria-hidden="true"></i>
            <p class="font-semibold text-navy group-hover:text-accent transition-colors text-sm"><?= $link['label'] ?></p>
            <p class="text-xs text-gray-500 mt-1"><?= $link['desc'] ?></p>
        </a>
    <?php endforeach; ?>
</div>

<!-- Top 10 + Graphiques -->
<div class="grid lg:grid-cols-3 gap-8">

    <!-- Top 10 (occupe 2/3) -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow p-6">
            <h2 class="text-xl font-bold text-navy mb-5 flex items-center gap-2">
                <i class="fa-solid fa-trophy text-accent"></i>
                Top 10 mondial — Édition <?= $lastYear ?>
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                            <th class="px-3 py-3 text-left rounded-tl-lg">Rang</th>
                            <th class="px-3 py-3 text-left">Université</th>
                            <th class="px-3 py-3 text-left">Pays</th>
                            <th class="px-3 py-3 text-right rounded-tr-lg">Score</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($top10 as $i => $row): ?>
                            <tr class="hover:bg-blue-50 transition-colors">
                                <td class="px-3 py-3">
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full font-bold text-sm
                                        <?= $i === 0 ? 'bg-yellow-400 text-white' : ($i === 1 ? 'bg-gray-300 text-gray-700' : ($i === 2 ? 'bg-orange-300 text-white' : 'bg-gray-100 text-gray-600')) ?>">
                                        <?= $row['rang'] ?>
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <a href="universite.php?id=<?= $row['id_univ'] ?>"
                                       class="font-semibold text-navy hover:text-accent transition-colors">
                                        <?= htmlspecialchars($row['nom']) ?>
                                    </a>
                                    <p class="text-xs text-gray-400"><?= htmlspecialchars($row['type_univ']) ?></p>
                                </td>
                                <td class="px-3 py-3 text-gray-600">
                                    <?= htmlspecialchars($row['pays']) ?>
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <span class="font-bold text-navy"><?= number_format($row['score_global'], 1) ?></span>
                                    <div class="w-16 bg-gray-100 rounded-full h-1.5 ml-auto mt-1">
                                        <div class="bg-accent h-1.5 rounded-full"
                                             style="width: <?= $row['score_global'] ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Graphiques (1/3) -->
    <div class="flex flex-col gap-6">

        <!-- Bar chart : répartition par continent -->
        <div class="bg-white rounded-2xl shadow p-6">
            <h3 class="text-base font-bold text-navy mb-4 flex items-center gap-2">
                <i class="fa-solid fa-globe text-accent"></i> Universités par continent
            </h3>
            <div class="radar-wrapper" style="position:relative;height:220px;">
                <canvas id="chartContinent"
                        aria-label="Répartition des universités classées par continent"></canvas>
            </div>
        </div>

        <!-- Doughnut : répartition par type -->
        <div class="bg-white rounded-2xl shadow p-6">
            <h3 class="text-base font-bold text-navy mb-4 flex items-center gap-2">
                <i class="fa-solid fa-building-columns text-accent"></i> Répartition par type
            </h3>
            <div style="position:relative;height:200px;">
                <canvas id="chartType"
                        aria-label="Répartition des universités classées par type"></canvas>
            </div>
        </div>

    </div>
</div>

<script>
// Données PHP → JS (JSON_UNESCAPED_UNICODE pour conserver les accents)
const continentLabels = <?= json_encode(array_column($continents, 'continent'), JSON_UNESCAPED_UNICODE) ?>;
const continentData   = <?= json_encode(array_column($continents, 'nb'),        JSON_UNESCAPED_UNICODE) ?>;
const typeLabels      = <?= json_encode(array_column($types, 'libelle'),         JSON_UNESCAPED_UNICODE) ?>;
const typeData        = <?= json_encode(array_column($types, 'nb'),              JSON_UNESCAPED_UNICODE) ?>;

const navyColor  = '#1E3A5F';
const accentColor = '#E67E22';
const palette = ['#1E3A5F','#E67E22','#2980B9','#27AE60','#8E44AD','#C0392B'];

// Bar chart horizontal — continents
new Chart(document.getElementById('chartContinent'), {
    type: 'bar',
    data: {
        labels: continentLabels,
        datasets: [{
            label: 'Universités classées',
            data: continentData,
            backgroundColor: palette,
            borderRadius: 4,
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ` ${ctx.parsed.x} universite(s)` } }
        },
        scales: {
            x: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// Doughnut — types
new Chart(document.getElementById('chartType'), {
    type: 'doughnut',
    data: {
        labels: typeLabels,
        datasets: [{
            data: typeData,
            backgroundColor: palette,
            borderWidth: 2,
            borderColor: '#fff',
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
        }
    }
});
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
