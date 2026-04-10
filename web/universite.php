<?php
/**
 * universite.php — Fiche université (F2)
 * AC-14 : radar 6 axes + barres progression + line chart trajectoire
 * Paramètre GET : ?id=X (id_univ)
 */
require_once __DIR__ . '/connexion.php';

$pageTitle = 'Fiche Université';
$univId    = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$univ      = null;
$scores    = [];
$trajectory = [];
$worldAvg  = [];

// Liste de toutes les universités pour le sélecteur
$allUnivs = $pdo->query(
    'SELECT u.id_univ, u.nom, p.nom AS pays
     FROM UNIVERSITE u JOIN PAYS p ON p.id_pays = u.id_pays
     ORDER BY u.nom ASC'
)->fetchAll();

if ($univId !== false && $univId !== null) {
    // Données de base de l'université (dernière édition)
    $lastEditionId = getLastEditionId($pdo);
    $stmtUniv = $pdo->prepare(
        'SELECT u.nom, u.acronyme, u.ville, p.nom AS pays, p.continent,
                t.libelle AS type_univ,
                sq.rang, sq.score_global,
                sq.score_rep_acad, sq.score_employeur, sq.score_ratio,
                sq.score_citations, sq.score_intl_etu, sq.score_intl_ens,
                e.annee
         FROM SCORE_QS sq
         JOIN UNIVERSITE u      ON u.id_univ    = sq.id_univ
         JOIN PAYS p            ON p.id_pays    = u.id_pays
         JOIN TYPE_UNIVERSITE t ON t.id_type    = u.id_type
         JOIN EDITION_QS e      ON e.id_edition = sq.id_edition
         WHERE sq.id_univ = :uid AND sq.id_edition = :eid'
    );
    $stmtUniv->execute([':uid' => $univId, ':eid' => $lastEditionId]);
    $univ = $stmtUniv->fetch();

    if ($univ) {
        $pageTitle = htmlspecialchars($univ['nom']);

        // Trajectoire rang sur toutes les éditions
        $stmtTraj = $pdo->prepare(
            'SELECT e.annee, sq.rang, sq.score_global
             FROM SCORE_QS sq
             JOIN EDITION_QS e ON e.id_edition = sq.id_edition
             WHERE sq.id_univ = :uid
             ORDER BY e.annee ASC'
        );
        $stmtTraj->execute([':uid' => $univId]);
        $trajectory = $stmtTraj->fetchAll();

        // Présence dans CLASSEMENT_REF (ARWU) — comparaison QS vs ARWU
        $stmtArwu = $pdo->prepare(
            'SELECT cr.nom_institution, cr.source
             FROM CLASSEMENT_REF cr
             WHERE cr.nom_institution LIKE :nom
             LIMIT 1'
        );
        // Try with abbreviated name (first 15 chars) for fuzzy match
        $nomShort = '%' . mb_substr($univ['nom'], 0, 20) . '%';
        $stmtArwu->execute([':nom' => $nomShort]);
        $arwuEntry = $stmtArwu->fetch();

        // Count total ARWU entries for reference
        $totalArwu = (int)$pdo->query('SELECT COUNT(*) FROM CLASSEMENT_REF')->fetchColumn();

        // Moyennes mondiales (dernière édition) pour le radar comparatif
        $stmtAvg = $pdo->prepare(
            'SELECT AVG(score_rep_acad) AS avg_rep_acad,
                    AVG(score_employeur) AS avg_employeur,
                    AVG(score_ratio)    AS avg_ratio,
                    AVG(score_citations) AS avg_citations,
                    AVG(score_intl_etu) AS avg_intl_etu,
                    AVG(score_intl_ens) AS avg_intl_ens
             FROM SCORE_QS
             WHERE id_edition = :eid'
        );
        $stmtAvg->execute([':eid' => $lastEditionId]);
        $worldAvg = $stmtAvg->fetch();
    }
}

require_once __DIR__ . '/partials/header.php';
?>

<!-- Sélecteur d'université -->
<div class="bg-white rounded-2xl shadow p-6 mb-6">
    <h1 class="text-2xl font-bold text-navy mb-4 flex items-center gap-2">
        <i class="fa-solid fa-graduation-cap text-accent"></i> Fiche Université
    </h1>
    <form method="get" action="universite.php" class="flex flex-col sm:flex-row gap-3">
        <label for="univ-select" class="sr-only">Choisir une université</label>
        <select id="univ-select" name="id"
                class="flex-1 border-2 border-gray-200 rounded-lg px-4 py-2 focus:border-accent focus:outline-none text-sm">
            <option value="">— Sélectionnez une université —</option>
            <?php foreach ($allUnivs as $u): ?>
                <option value="<?= $u['id_univ'] ?>"
                    <?= (int)$univId === (int)$u['id_univ'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($u['nom']) ?> (<?= htmlspecialchars($u['pays']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit"
                class="bg-accent hover:bg-orange-600 text-white font-semibold px-6 py-2 rounded-lg transition-colors">
            Afficher
        </button>
    </form>
</div>

<?php if (!$univId || !$univ): ?>
    <!-- État vide -->
    <div class="bg-blue-50 border-2 border-dashed border-blue-200 rounded-2xl p-12 text-center">
        <i class="fa-solid fa-graduation-cap text-5xl text-blue-300 mb-4 block"></i>
        <p class="text-lg font-semibold text-navy mb-2">Sélectionnez une université</p>
        <p class="text-gray-500">Choisissez une université dans le menu ci-dessus pour afficher sa fiche complète.</p>
    </div>

<?php else: ?>

    <!-- En-tête université -->
    <div class="bg-gradient-to-r from-navy to-blue-800 rounded-2xl text-white p-6 mb-6 shadow-lg">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black"><?= htmlspecialchars($univ['nom']) ?></h2>
                <p class="text-blue-200 mt-1">
                    <?= htmlspecialchars($univ['ville']) ?> · <?= htmlspecialchars($univ['pays']) ?>
                    · <?= htmlspecialchars($univ['type_univ']) ?>
                </p>
            </div>
            <div class="text-center bg-white/10 rounded-xl p-4">
                <p class="text-4xl font-black text-accent">#<?= $univ['rang'] ?></p>
                <p class="text-sm text-blue-200">Rang QS <?= $univ['annee'] ?></p>
            </div>
        </div>
    </div>

    <!-- Score global -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <?php
        $scoreCards = [
            ['label' => 'Score Global',          'value' => $univ['score_global'],    'color' => 'accent',     'icon' => 'fa-solid fa-star'],
            ['label' => 'Rép. Académique',        'value' => $univ['score_rep_acad'],  'color' => 'blue-600',   'icon' => 'fa-solid fa-book-open'],
            ['label' => 'Rép. Employeur',         'value' => $univ['score_employeur'], 'color' => 'green-600',  'icon' => 'fa-solid fa-briefcase'],
            ['label' => 'Ratio Étu/Ens',          'value' => $univ['score_ratio'],     'color' => 'purple-600', 'icon' => 'fa-solid fa-users'],
        ];
        foreach ($scoreCards as $card): ?>
            <div class="bg-white rounded-xl shadow p-4">
                <div class="flex items-center gap-2 mb-1">
                    <i class="<?= $card['icon'] ?> text-<?= $card['color'] ?> text-sm"></i>
                    <p class="text-xs text-gray-500 uppercase tracking-wide"><?= $card['label'] ?></p>
                </div>
                <p class="text-3xl font-black text-navy mt-1"><?= number_format($card['value'], 1) ?></p>
                <div class="w-full bg-gray-100 rounded-full h-2 mt-2">
                    <div class="bg-<?= $card['color'] ?> h-2 rounded-full transition-all"
                         style="width: <?= $card['value'] ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Radar + Barres de progression -->
    <div class="grid lg:grid-cols-2 gap-6 mb-6">

        <!-- Radar Chart.js — 6 axes -->
        <div class="bg-white rounded-2xl shadow p-6">
            <h3 class="text-lg font-bold text-navy mb-4 flex items-center gap-2">
            <i class="fa-solid fa-chart-area text-accent"></i> Profil des 6 critères QS
        </h3>
            <div class="radar-wrapper" style="position:relative;height:320px;">
                <canvas id="chartRadar" aria-label="Radar des 6 critères QS — <?= htmlspecialchars($univ['nom']) ?>"></canvas>
            </div>
            <p class="text-xs text-gray-400 mt-3 text-center">
                <span class="inline-block w-3 h-3 rounded-full bg-accent mr-1.5"></span>Université
            <span class="inline-block w-3 h-3 rounded-full bg-blue-400 ml-4 mr-1.5"></span>Moyenne mondiale
            </p>
        </div>

        <!-- Barres de progression CSS pour les 6 critères -->
        <div class="bg-white rounded-2xl shadow p-6">
            <h3 class="text-lg font-bold text-navy mb-5 flex items-center gap-2">
            <i class="fa-solid fa-sliders text-accent"></i> Détail des scores vs. moyenne mondiale
        </h3>
            <?php
            $criteria = [
                ['key' => 'score_rep_acad',  'label' => 'Réputation académique', 'weight' => '40%', 'avg_key' => 'avg_rep_acad',  'icon' => 'fa-solid fa-book-open'],
                ['key' => 'score_employeur', 'label' => 'Réputation employeur',  'weight' => '10%', 'avg_key' => 'avg_employeur', 'icon' => 'fa-solid fa-briefcase'],
                ['key' => 'score_ratio',     'label' => 'Ratio étu/enseignants', 'weight' => '20%', 'avg_key' => 'avg_ratio',     'icon' => 'fa-solid fa-users'],
                ['key' => 'score_citations', 'label' => 'Citations/enseignant',  'weight' => '20%', 'avg_key' => 'avg_citations', 'icon' => 'fa-solid fa-quote-left'],
                ['key' => 'score_intl_etu',  'label' => 'Etudiants intern.',     'weight' => '5%',  'avg_key' => 'avg_intl_etu',  'icon' => 'fa-solid fa-plane'],
                ['key' => 'score_intl_ens',  'label' => 'Enseignants intern.',   'weight' => '5%',  'avg_key' => 'avg_intl_ens',  'icon' => 'fa-solid fa-earth-europe'],
            ];
            foreach ($criteria as $c):
                $val = (float)$univ[$c['key']];
                $avg = (float)$worldAvg[$c['avg_key']];
                $isBetter = $val >= $avg;
            ?>
                <div class="mb-4">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm font-medium text-gray-700 flex items-center gap-1.5">
                            <i class="<?= $c['icon'] ?> text-gray-400 w-4 text-center"></i>
                            <?= $c['label'] ?>
                            <span class="text-xs text-gray-400">(<?= $c['weight'] ?>)</span>
                        </span>
                        <div class="text-right">
                            <span class="text-sm font-bold text-navy"><?= number_format($val, 1) ?></span>
                            <span class="text-xs text-gray-400 ml-1">/ moy. <?= number_format($avg, 1) ?></span>
                            <?php if ($isBetter): ?>
                                <span class="text-xs text-green-600 ml-1">↑</span>
                            <?php else: ?>
                                <span class="text-xs text-red-500 ml-1">↓</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="relative h-3 bg-gray-100 rounded-full overflow-hidden">
                        <!-- Barre moyenne mondiale -->
                        <div class="absolute h-3 bg-blue-200 rounded-full transition-all"
                             style="width: <?= min($avg, 100) ?>%"></div>
                        <!-- Barre université -->
                        <div class="progress-bar absolute h-3 rounded-full transition-all
                                    <?= $isBetter ? 'bg-accent' : 'bg-red-400' ?>"
                             style="width: <?= min($val, 100) ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Comparaison QS vs ARWU -->
    <div class="bg-white rounded-2xl shadow p-6 mb-6">
        <h3 class="text-lg font-bold text-navy mb-4 flex items-center gap-2">
            <i class="fa-solid fa-scale-balanced text-accent"></i> Comparaison QS vs ARWU Shanghai
        </h3>
        <div class="grid sm:grid-cols-2 gap-6">
            <!-- QS column -->
            <div class="rounded-xl border-2 border-accent/30 bg-orange-50 p-5">
                <div class="flex items-center gap-3 mb-3">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-accent text-white font-black text-sm">QS</span>
                    <div>
                        <p class="font-bold text-navy text-sm">QS World Rankings</p>
                        <p class="text-xs text-gray-500">Quacquarelli Symonds</p>
                    </div>
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Rang mondial</span>
                        <span class="font-bold text-accent">#<?= $univ['rang'] ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Score global</span>
                        <span class="font-bold text-navy"><?= number_format($univ['score_global'], 1) ?>/100</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Rép. académique</span>
                        <span class="font-bold text-navy"><?= number_format((float)$univ['score_rep_acad'], 1) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Citations/enseignant</span>
                        <span class="font-bold text-navy"><?= number_format((float)$univ['score_citations'], 1) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Édition</span>
                        <span class="font-bold text-navy"><?= $univ['annee'] ?></span>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-accent/20">
                    <p class="text-xs text-gray-500">
                        <i class="fa-solid fa-circle-info mr-1 text-accent"></i>
                        Pondère 6 critères dont la réputation (50%) et les citations (20%)
                    </p>
                </div>
            </div>
            <!-- ARWU column -->
            <div class="rounded-xl border-2 border-blue-200 bg-blue-50 p-5">
                <div class="flex items-center gap-3 mb-3">
                    <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-blue-700 text-white font-black text-xs">ARWU</span>
                    <div>
                        <p class="font-bold text-navy text-sm">ARWU Shanghai</p>
                        <p class="text-xs text-gray-500">Academic Ranking of World Universities</p>
                    </div>
                </div>
                <?php if ($arwuEntry): ?>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Présence dans ARWU</span>
                            <span class="inline-flex items-center gap-1 text-green-700 font-bold">
                                <i class="fa-solid fa-circle-check"></i> Oui
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Source</span>
                            <span class="font-medium text-navy text-xs"><?= htmlspecialchars($arwuEntry['source']) ?></span>
                        </div>
                    </div>
                    <div class="mt-3 p-3 bg-green-100 rounded-lg border border-green-200">
                        <p class="text-xs text-green-800 font-semibold">
                            <i class="fa-solid fa-check-double mr-1"></i>
                            Reconnue dans les 2 classements mondiaux majeurs
                        </p>
                    </div>
                <?php else: ?>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Présence dans ARWU</span>
                            <span class="inline-flex items-center gap-1 text-red-600 font-bold">
                                <i class="fa-solid fa-circle-xmark"></i> Non répertoriée
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Référentiel ARWU</span>
                            <span class="text-gray-500 text-xs"><?= $totalArwu ?> universités</span>
                        </div>
                    </div>
                    <div class="mt-3 p-3 bg-amber-50 rounded-lg border border-amber-200">
                        <p class="text-xs text-amber-800 font-semibold">
                            <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                            Forte en QS mais absente de l'ARWU (axé publication scientifique)
                        </p>
                    </div>
                <?php endif; ?>
                <div class="mt-3 pt-3 border-t border-blue-200">
                    <p class="text-xs text-gray-500">
                        <i class="fa-solid fa-circle-info mr-1 text-blue-500"></i>
                        ARWU mesure : publications Nature/Science, prix Nobel, citations Clarivate
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Line chart trajectoire -->
    <div class="bg-white rounded-2xl shadow p-6">
        <h3 class="text-lg font-bold text-navy mb-4 flex items-center gap-2">
            <i class="fa-solid fa-chart-line text-accent"></i> Trajectoire du rang sur les éditions
        </h3>
        <?php if (count($trajectory) < 2): ?>
            <p class="text-gray-500 text-sm">Données insuffisantes pour afficher la trajectoire.</p>
        <?php else: ?>
            <div style="position:relative;height:250px;">
                <canvas id="chartTrajectory"
                        aria-label="Évolution du rang de <?= htmlspecialchars($univ['nom']) ?> sur les éditions QS"></canvas>
            </div>
        <?php endif; ?>
    </div>

<script>
const univScores = [
    <?= (float)$univ['score_rep_acad'] ?>,
    <?= (float)$univ['score_employeur'] ?>,
    <?= (float)$univ['score_ratio'] ?>,
    <?= (float)$univ['score_citations'] ?>,
    <?= (float)$univ['score_intl_etu'] ?>,
    <?= (float)$univ['score_intl_ens'] ?>
];
const worldAvgScores = [
    <?= round((float)$worldAvg['avg_rep_acad'], 1) ?>,
    <?= round((float)$worldAvg['avg_employeur'], 1) ?>,
    <?= round((float)$worldAvg['avg_ratio'], 1) ?>,
    <?= round((float)$worldAvg['avg_citations'], 1) ?>,
    <?= round((float)$worldAvg['avg_intl_etu'], 1) ?>,
    <?= round((float)$worldAvg['avg_intl_ens'], 1) ?>
];
const radarLabels = [
    'Rep. Academique', 'Employeur', 'Ratio Etu/Ens',
    'Citations', 'Intl. Etudiants', 'Intl. Enseignants'
];

// Radar chart
new Chart(document.getElementById('chartRadar'), {
    type: 'radar',
    data: {
        labels: radarLabels,
        datasets: [
            {
                label: <?= json_encode($univ['nom'], JSON_UNESCAPED_UNICODE) ?>,
                data: univScores,
                backgroundColor: 'rgba(230,126,34,0.2)',
                borderColor: '#E67E22',
                borderWidth: 2,
                pointBackgroundColor: '#E67E22',
                pointRadius: 4,
            },
            {
                label: 'Moyenne mondiale',
                data: worldAvgScores,
                backgroundColor: 'rgba(96,165,250,0.15)',
                borderColor: '#60A5FA',
                borderWidth: 2,
                borderDash: [5, 5],
                pointBackgroundColor: '#60A5FA',
                pointRadius: 3,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            r: {
                beginAtZero: true,
                max: 100,
                ticks: { stepSize: 20, font: { size: 10 } },
                pointLabels: { font: { size: 11 } }
            }
        }
    }
});

<?php if (count($trajectory) >= 2): ?>
// Line chart trajectoire
const trajLabels = <?= json_encode(array_column($trajectory, 'annee')) ?>;
const trajRangs  = <?= json_encode(array_column($trajectory, 'rang')) ?>;

new Chart(document.getElementById('chartTrajectory'), {
    type: 'line',
    data: {
        labels: trajLabels,
        datasets: [{
            label: 'Rang QS',
            data: trajRangs,
            borderColor: '#1E3A5F',
            backgroundColor: 'rgba(30,58,95,0.1)',
            borderWidth: 2,
            pointBackgroundColor: '#E67E22',
            pointRadius: 5,
            tension: 0.3,
            fill: true,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
                y: {
                    reverse: true,
                    beginAtZero: false,
                    title: { display: true, text: 'Rang (plus bas = meilleur)' }
                },
                x: { title: { display: true, text: 'Edition QS' } }
        }
    }
});
<?php endif; ?>
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
