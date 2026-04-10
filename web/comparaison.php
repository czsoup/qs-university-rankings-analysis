<?php
/**
 * comparaison.php — Comparateur côte-à-côte (F3)
 * AC-16 : radar superposé 2 datasets + tableau comparatif + double line chart rangs
 */
require_once __DIR__ . '/connexion.php';

$pageTitle = 'Comparaison d\'universités';

// Liste universités pour les sélecteurs
$allUnivs = $pdo->query(
    'SELECT u.id_univ, u.nom, p.nom AS pays
     FROM UNIVERSITE u JOIN PAYS p ON p.id_pays = u.id_pays
     ORDER BY u.nom ASC'
)->fetchAll();

$id1 = filter_input(INPUT_GET, 'id1', FILTER_VALIDATE_INT);
$id2 = filter_input(INPUT_GET, 'id2', FILTER_VALIDATE_INT);

$univ1 = null;
$univ2 = null;
$traj1 = [];
$traj2 = [];

function fetchUnivData(PDO $pdo, int $univId, int $editionId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT u.id_univ, u.nom, u.acronyme, p.nom AS pays, p.code_iso,
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
    $stmt->execute([':uid' => $univId, ':eid' => $editionId]);
    return $stmt->fetch() ?: null;
}

function fetchTrajectory(PDO $pdo, int $univId): array
{
    $stmt = $pdo->prepare(
        'SELECT e.annee, sq.rang, sq.score_global
         FROM SCORE_QS sq
         JOIN EDITION_QS e ON e.id_edition = sq.id_edition
         WHERE sq.id_univ = :uid
         ORDER BY e.annee ASC'
    );
    $stmt->execute([':uid' => $univId]);
    return $stmt->fetchAll();
}

if ($id1 && $id2 && $id1 !== $id2) {
    $lastEditionId = getLastEditionId($pdo);
    $univ1 = fetchUnivData($pdo, $id1, $lastEditionId);
    $univ2 = fetchUnivData($pdo, $id2, $lastEditionId);
    $traj1 = fetchTrajectory($pdo, $id1);
    $traj2 = fetchTrajectory($pdo, $id2);
}

require_once __DIR__ . '/partials/header.php';

$criteria = [
    ['key' => 'score_rep_acad',  'label' => 'Rép. Académique', 'weight' => '40%'],
    ['key' => 'score_employeur', 'label' => 'Employeur',        'weight' => '10%'],
    ['key' => 'score_ratio',     'label' => 'Ratio Étu/Ens',   'weight' => '20%'],
    ['key' => 'score_citations', 'label' => 'Citations',        'weight' => '20%'],
    ['key' => 'score_intl_etu',  'label' => 'Intl. Etudiants', 'weight' => '5%'],
    ['key' => 'score_intl_ens',  'label' => 'Intl. Enseignants','weight' => '5%'],
];
?>

<h1 class="text-2xl font-bold text-navy mb-6 flex items-center gap-2">
    <i class="fa-solid fa-scale-balanced text-accent"></i> Comparaison d'universités
</h1>

<!-- Sélecteur des deux universités -->
<div class="bg-white rounded-2xl shadow p-6 mb-6">
    <form method="get" action="comparaison.php" class="grid sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
        <div class="lg:col-span-2">
            <label for="id1-select" class="block text-sm font-semibold text-gray-700 mb-1">
                Université A <span class="text-accent">(orange)</span>
            </label>
            <select id="id1-select" name="id1"
                    class="w-full border-2 border-accent/40 rounded-lg px-3 py-2 focus:border-accent focus:outline-none text-sm">
                <option value="">— Sélectionner —</option>
                <?php foreach ($allUnivs as $u): ?>
                    <option value="<?= $u['id_univ'] ?>"
                        <?= (int)$id1 === (int)$u['id_univ'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['nom']) ?> (<?= htmlspecialchars($u['pays']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="text-center text-gray-400 font-bold text-lg hidden lg:block self-center">VS</div>
        <div class="lg:col-span-2">
            <label for="id2-select" class="block text-sm font-semibold text-gray-700 mb-1">
                Université B <span class="text-blue-500">(bleu)</span>
            </label>
            <select id="id2-select" name="id2"
                    class="w-full border-2 border-blue-200 rounded-lg px-3 py-2 focus:border-blue-400 focus:outline-none text-sm">
                <option value="">— Sélectionner —</option>
                <?php foreach ($allUnivs as $u): ?>
                    <option value="<?= $u['id_univ'] ?>"
                        <?= (int)$id2 === (int)$u['id_univ'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($u['nom']) ?> (<?= htmlspecialchars($u['pays']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit"
                class="sm:col-span-2 lg:col-span-1 bg-navy hover:bg-blue-900 text-white font-semibold
                       px-4 py-2.5 rounded-lg transition-colors text-sm">
            Comparer
        </button>
    </form>
</div>

<?php if (!$id1 || !$id2): ?>
    <div class="bg-blue-50 border-2 border-dashed border-blue-200 rounded-2xl p-12 text-center">
        <i class="fa-solid fa-scale-balanced text-5xl text-blue-300 mb-4 block"></i>
        <p class="text-lg font-semibold text-navy mb-2">Sélectionnez deux universités</p>
        <p class="text-gray-500">Choisissez deux universités différentes pour les comparer côte-à-côte.</p>
    </div>

<?php elseif ($id1 === $id2): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-sm text-yellow-800">
        ⚠️ Sélectionnez deux universités <strong>différentes</strong> pour lancer la comparaison.
    </div>

<?php elseif (!$univ1 || !$univ2): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-sm text-yellow-800">
        ⚠️ Données manquantes pour une ou les deux universités dans la dernière édition.
    </div>

<?php else: ?>

    <!-- En-têtes des deux universités -->
    <div class="grid sm:grid-cols-2 gap-4 mb-6">
        <div class="bg-gradient-to-br from-accent to-orange-600 text-white rounded-2xl p-5 shadow">
            <p class="font-black text-xl"><?= htmlspecialchars($univ1['nom']) ?></p>
            <p class="text-orange-100 text-sm mt-1"><?= htmlspecialchars($univ1['pays']) ?> · <?= htmlspecialchars($univ1['type_univ']) ?></p>
            <p class="text-3xl font-black mt-3">#<?= $univ1['rang'] ?> <span class="text-sm font-normal text-orange-200">Rang QS <?= $univ1['annee'] ?></span></p>
            <p class="text-xl font-bold mt-1"><?= number_format($univ1['score_global'], 1) ?> <span class="text-sm font-normal text-orange-200">score global</span></p>
        </div>
        <div class="bg-gradient-to-br from-blue-600 to-blue-800 text-white rounded-2xl p-5 shadow">
            <p class="font-black text-xl"><?= htmlspecialchars($univ2['nom']) ?></p>
            <p class="text-blue-200 text-sm mt-1"><?= htmlspecialchars($univ2['pays']) ?> · <?= htmlspecialchars($univ2['type_univ']) ?></p>
            <p class="text-3xl font-black mt-3">#<?= $univ2['rang'] ?> <span class="text-sm font-normal text-blue-300">Rang QS <?= $univ2['annee'] ?></span></p>
            <p class="text-xl font-bold mt-1"><?= number_format($univ2['score_global'], 1) ?> <span class="text-sm font-normal text-blue-300">score global</span></p>
        </div>
    </div>

    <!-- Radar superposé + Tableau comparatif -->
    <div class="grid lg:grid-cols-2 gap-6 mb-6">

        <!-- Radar superposé Chart.js (2 datasets) -->
        <div class="bg-white rounded-2xl shadow p-6">
            <h3 class="text-lg font-bold text-navy mb-4 flex items-center gap-2">
            <i class="fa-solid fa-chart-area text-accent"></i> Radar comparatif — 6 critères QS
        </h3>
            <div class="radar-wrapper" style="position:relative;height:320px;">
                <canvas id="chartRadarComp"
                        aria-label="Comparaison radar des 6 critères QS entre <?= htmlspecialchars($univ1['nom']) ?> et <?= htmlspecialchars($univ2['nom']) ?>">
                </canvas>
            </div>
            <div class="flex justify-center gap-6 mt-3 text-sm">
                <span class="flex items-center gap-1.5">
                    <span class="inline-block w-4 h-1 rounded bg-accent"></span>
                    <?= htmlspecialchars($univ1['acronyme'] ?: $univ1['nom']) ?>
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="inline-block w-4 h-1 rounded bg-blue-500"></span>
                    <?= htmlspecialchars($univ2['acronyme'] ?: $univ2['nom']) ?>
                </span>
            </div>
        </div>

        <!-- Tableau comparatif 6 critères -->
        <div class="bg-white rounded-2xl shadow p-6">
            <h3 class="text-lg font-bold text-navy mb-4 flex items-center gap-2">
            <i class="fa-solid fa-table-list text-accent"></i> Tableau comparatif des scores
        </h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider">
                        <th class="px-3 py-2 text-left rounded-tl">Critère</th>
                        <th class="px-3 py-2 text-center text-accent">A</th>
                        <th class="px-3 py-2 text-center text-blue-600">B</th>
                        <th class="px-3 py-2 text-right rounded-tr">Poids</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($criteria as $c):
                        $v1 = (float)$univ1[$c['key']];
                        $v2 = (float)$univ2[$c['key']];
                        $a1Better = $v1 >= $v2;
                        $a2Better = $v2 > $v1;
                    ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2.5 font-medium text-gray-700"><?= $c['label'] ?></td>
                            <td class="px-3 py-2.5 text-center">
                                <span class="font-bold <?= $a1Better ? 'text-accent' : 'text-gray-500' ?>">
                                    <?= number_format($v1, 1) ?>
                                    <?= $a1Better ? '↑' : '' ?>
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-center">
                                <span class="font-bold <?= $a2Better ? 'text-blue-600' : 'text-gray-500' ?>">
                                    <?= number_format($v2, 1) ?>
                                    <?= $a2Better ? '↑' : '' ?>
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-right text-gray-400 text-xs"><?= $c['weight'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <!-- Ligne score global -->
                    <tr class="bg-gray-50 font-bold">
                        <td class="px-3 py-2.5">Score Global</td>
                        <td class="px-3 py-2.5 text-center text-accent">
                            <?= number_format($univ1['score_global'], 1) ?>
                            <?= $univ1['score_global'] >= $univ2['score_global'] ? ' ↑' : '' ?>
                        </td>
                        <td class="px-3 py-2.5 text-center text-blue-600">
                            <?= number_format($univ2['score_global'], 1) ?>
                            <?= $univ2['score_global'] > $univ1['score_global'] ? ' ↑' : '' ?>
                        </td>
                        <td class="px-3 py-2.5 text-right text-gray-400 text-xs">100%</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Double line chart : évolution des rangs sur 4 éditions -->
    <div class="bg-white rounded-2xl shadow p-6">
        <h3 class="text-lg font-bold text-navy mb-4 flex items-center gap-2">
            <i class="fa-solid fa-chart-line text-accent"></i> Évolution des rangs sur les 4 éditions
        </h3>
        <?php
        $years1 = array_column($traj1, 'annee');
        $years2 = array_column($traj2, 'annee');
        $allYears = array_values(array_unique(array_merge($years1, $years2)));
        sort($allYears);

        // Rang par année (null si absent)
        $rangs1ByYear = array_column($traj1, 'rang', 'annee');
        $rangs2ByYear = array_column($traj2, 'rang', 'annee');
        $rangs1 = array_map(fn($y) => $rangs1ByYear[$y] ?? null, $allYears);
        $rangs2 = array_map(fn($y) => $rangs2ByYear[$y] ?? null, $allYears);
        ?>
        <?php if (count($allYears) < 2): ?>
            <p class="text-gray-500 text-sm">Données insuffisantes pour afficher la trajectoire.</p>
        <?php else: ?>
            <div style="position:relative;height:280px;">
                <canvas id="chartDoubleRank"
                        aria-label="Évolution des rangs QS sur les éditions — comparaison de deux universités"></canvas>
            </div>
            <p class="text-xs text-gray-400 mt-2 text-center">L'axe Y est inversé : rang 1 = meilleur = en haut</p>
        <?php endif; ?>
    </div>

<script>
const radarLabels = [
    'Rep. Academique', 'Employeur', 'Ratio Etu/Ens',
    'Citations', 'Intl. Etudiants', 'Intl. Enseignants'
];
const scores1 = [
    <?= (float)$univ1['score_rep_acad']  ?>,
    <?= (float)$univ1['score_employeur'] ?>,
    <?= (float)$univ1['score_ratio']     ?>,
    <?= (float)$univ1['score_citations'] ?>,
    <?= (float)$univ1['score_intl_etu']  ?>,
    <?= (float)$univ1['score_intl_ens']  ?>
];
const scores2 = [
    <?= (float)$univ2['score_rep_acad']  ?>,
    <?= (float)$univ2['score_employeur'] ?>,
    <?= (float)$univ2['score_ratio']     ?>,
    <?= (float)$univ2['score_citations'] ?>,
    <?= (float)$univ2['score_intl_etu']  ?>,
    <?= (float)$univ2['score_intl_ens']  ?>
];

// Radar superposé
new Chart(document.getElementById('chartRadarComp'), {
    type: 'radar',
    data: {
        labels: radarLabels,
        datasets: [
            {
                label: <?= json_encode($univ1['acronyme'] ?: $univ1['nom'], JSON_UNESCAPED_UNICODE) ?>,
                data: scores1,
                backgroundColor: 'rgba(230,126,34,0.2)',
                borderColor: '#E67E22',
                borderWidth: 2,
                pointBackgroundColor: '#E67E22',
                pointRadius: 4,
            },
            {
                label: <?= json_encode($univ2['acronyme'] ?: $univ2['nom'], JSON_UNESCAPED_UNICODE) ?>,
                data: scores2,
                backgroundColor: 'rgba(59,130,246,0.15)',
                borderColor: '#3B82F6',
                borderWidth: 2,
                pointBackgroundColor: '#3B82F6',
                pointRadius: 4,
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

<?php if (count($allYears) >= 2): ?>
// Double line chart rangs
new Chart(document.getElementById('chartDoubleRank'), {
    type: 'line',
    data: {
        labels: <?= json_encode($allYears) ?>,
        datasets: [
            {
                label: <?= json_encode($univ1['acronyme'] ?: $univ1['nom'], JSON_UNESCAPED_UNICODE) ?>,
                data: <?= json_encode($rangs1) ?>,
                borderColor: '#E67E22',
                backgroundColor: 'rgba(230,126,34,0.1)',
                borderWidth: 2,
                pointBackgroundColor: '#E67E22',
                pointRadius: 5,
                tension: 0.3,
                spanGaps: true,
            },
            {
                label: <?= json_encode($univ2['acronyme'] ?: $univ2['nom'], JSON_UNESCAPED_UNICODE) ?>,
                data: <?= json_encode($rangs2) ?>,
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59,130,246,0.1)',
                borderWidth: 2,
                pointBackgroundColor: '#3B82F6',
                pointRadius: 5,
                tension: 0.3,
                spanGaps: true,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top', labels: { boxWidth: 12 } }
        },
        scales: {
            y: {
                reverse: true,
                title: { display: true, text: 'Rang QS (plus bas = meilleur)' }
            },
            x: { title: { display: true, text: 'Edition QS' } }
        }
    }
});
<?php endif; ?>
</script>

<?php endif; ?>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
