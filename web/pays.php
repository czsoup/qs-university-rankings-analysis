<?php
/**
 * pays.php — Exploration par pays (F4)
 * AC-17 : liste universités + bar chart évolution rang moyen par édition
 * Paramètre GET : ?pays=XX (code_iso) ou ?id_pays=N
 */
require_once __DIR__ . '/connexion.php';

$pageTitle = 'Exploration par pays';

// Liste de tous les pays présents dans la BDD
$allPays = $pdo->query(
    'SELECT DISTINCT p.id_pays, p.nom, p.code_iso, p.continent
     FROM PAYS p
     JOIN UNIVERSITE u ON u.id_pays = p.id_pays
     ORDER BY p.nom ASC'
)->fetchAll();

$codeIso    = filter_input(INPUT_GET, 'pays', FILTER_SANITIZE_SPECIAL_CHARS) ?: null;
$paysData   = null;
$univList   = [];
$rankEvol   = [];
$homogene   = null;

if ($codeIso) {
    // Infos du pays sélectionné
    $stmtPays = $pdo->prepare(
        'SELECT id_pays, nom, code_iso, continent FROM PAYS WHERE code_iso = :iso'
    );
    $stmtPays->execute([':iso' => strtoupper($codeIso)]);
    $paysData = $stmtPays->fetch();

    if ($paysData) {
        $pageTitle = htmlspecialchars($paysData['nom']);
        $lastEditionId = getLastEditionId($pdo);

        // Liste des universités du pays (dernière édition)
        $stmtUnivs = $pdo->prepare(
            'SELECT u.id_univ, u.nom, u.acronyme, u.ville,
                    t.libelle AS type_univ,
                    sq.rang, sq.score_global, sq.score_employeur,
                    sq.score_rep_acad, sq.score_ratio
             FROM SCORE_QS sq
             JOIN UNIVERSITE u      ON u.id_univ  = sq.id_univ
             JOIN TYPE_UNIVERSITE t ON t.id_type  = u.id_type
             WHERE u.id_pays = :pid AND sq.id_edition = :eid
             ORDER BY sq.rang ASC'
        );
        $stmtUnivs->execute([':pid' => $paysData['id_pays'], ':eid' => $lastEditionId]);
        $univList = $stmtUnivs->fetchAll();

        // R2 — Évolution rang moyen + score moyen par édition (utilisé ici côté pays)
        $stmtEvol = $pdo->prepare(
            'SELECT e.annee,
                    ROUND(AVG(sq.rang), 1)         AS rang_moyen,
                    ROUND(AVG(sq.score_global), 1) AS score_moyen,
                    COUNT(sq.id_score)             AS nb_univ
             FROM SCORE_QS sq
             JOIN UNIVERSITE u  ON u.id_univ    = sq.id_univ
             JOIN EDITION_QS e  ON e.id_edition = sq.id_edition
             WHERE u.id_pays = :pid
             GROUP BY e.id_edition, e.annee
             ORDER BY e.annee ASC'
        );
        $stmtEvol->execute([':pid' => $paysData['id_pays']]);
        $rankEvol = $stmtEvol->fetchAll();

        // R4 — Homogénéité employabilité : toutes universités du pays > 80 (dernière édition)
        $stmtHomog = $pdo->prepare(
            'SELECT CASE WHEN 80 < ALL (
                SELECT sq2.score_employeur
                FROM SCORE_QS sq2
                JOIN UNIVERSITE u2 ON u2.id_univ = sq2.id_univ
                WHERE u2.id_pays = :pid AND sq2.id_edition = :eid
                  AND sq2.score_employeur IS NOT NULL
            ) THEN 1 ELSE 0 END AS est_homogene'
        );
        $stmtHomog->execute([':pid' => $paysData['id_pays'], ':eid' => $lastEditionId]);
        $homogene = (bool)$stmtHomog->fetchColumn();
    }
}

require_once __DIR__ . '/partials/header.php';
?>

<h1 class="text-2xl font-bold text-navy mb-6 flex items-center gap-2">
    <i class="fa-solid fa-earth-europe text-accent"></i> Exploration par pays
</h1>

<!-- Sélecteur de pays -->
<div class="bg-white rounded-2xl shadow p-6 mb-6">
    <form method="get" action="pays.php" class="flex flex-col sm:flex-row gap-3">
        <label for="pays-select" class="sr-only">Choisir un pays</label>
        <select id="pays-select" name="pays"
                class="flex-1 border-2 border-gray-200 rounded-lg px-4 py-2 focus:border-accent focus:outline-none text-sm">
            <option value="">— Sélectionnez un pays —</option>
            <?php foreach ($allPays as $p): ?>
                <option value="<?= htmlspecialchars($p['code_iso']) ?>"
                    <?= $codeIso === $p['code_iso'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['nom']) ?> (<?= htmlspecialchars($p['continent']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit"
                class="bg-accent hover:bg-orange-600 text-white font-semibold px-6 py-2 rounded-lg transition-colors">
            Explorer
        </button>
    </form>
</div>

<?php if (!$codeIso || !$paysData): ?>
    <div class="bg-blue-50 border-2 border-dashed border-blue-200 rounded-2xl p-12 text-center">
        <i class="fa-solid fa-earth-europe text-5xl text-blue-300 mb-4 block"></i>
        <p class="text-lg font-semibold text-navy mb-2">Sélectionnez un pays</p>
        <p class="text-gray-500">Choisissez un pays pour explorer ses universités classées et leur évolution.</p>
    </div>

<?php elseif (empty($univList)): ?>
    <div class="bg-yellow-50 border-2 border-dashed border-yellow-200 rounded-2xl p-8 text-center">
        <i class="fa-solid fa-triangle-exclamation text-4xl text-yellow-400 mb-3 block"></i>
        <p class="font-semibold text-gray-700">Aucune université classée pour ce pays dans la dernière édition.</p>
        <p class="text-sm text-gray-500 mt-2">Essayez un autre pays.</p>
    </div>

<?php else: ?>

    <!-- En-tête pays -->
    <div class="bg-gradient-to-r from-navy to-blue-800 rounded-2xl text-white p-6 mb-6 shadow-lg">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black"><?= htmlspecialchars($paysData['nom']) ?></h2>
                <p class="text-blue-200"><?= htmlspecialchars($paysData['continent']) ?></p>
            </div>
            <div class="flex gap-6 text-center">
                <div class="bg-white/10 rounded-xl p-3">
                    <p class="text-2xl font-black text-accent"><?= count($univList) ?></p>
                    <p class="text-xs text-blue-200">Universités classées</p>
                </div>
                <?php if (!empty($rankEvol)): ?>
                    <div class="bg-white/10 rounded-xl p-3">
                        <p class="text-2xl font-black text-accent">
                            #<?= number_format((float)end($rankEvol)['rang_moyen'], 0) ?>
                        </p>
                        <p class="text-xs text-blue-200">Rang moyen (dernière éd.)</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Indicateur homogénéité employabilité (R4) -->
    <?php if ($homogene !== null): ?>
        <div class="rounded-xl p-4 mb-6 flex items-center gap-3
                    <?= $homogene ? 'bg-green-50 border border-green-200' : 'bg-orange-50 border border-orange-200' ?>">
            <i class="<?= $homogene ? 'fa-solid fa-circle-check text-green-500' : 'fa-solid fa-triangle-exclamation text-orange-400' ?> text-xl flex-shrink-0"></i>
            <div>
                <p class="font-semibold <?= $homogene ? 'text-green-800' : 'text-orange-800' ?>">
                    <?= $homogene
                        ? 'Pays homogène en employabilité'
                        : 'Pays non homogène en employabilité' ?>
                </p>
                <p class="text-sm <?= $homogene ? 'text-green-600' : 'text-orange-600' ?>">
                    <?= $homogene
                        ? 'Toutes les universités classées ont un score employeur > 80 (dernière édition).'
                        : 'Au moins une université a un score employeur ≤ 80 dans la dernière édition.' ?>
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Liste universités + graphique évolution -->
    <div class="grid lg:grid-cols-3 gap-6">

        <!-- Tableau universités (2/3) -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow p-6">
                <h3 class="text-lg font-bold text-navy mb-4">
                    Universités classées — <?= htmlspecialchars($paysData['nom']) ?>
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                                <th class="px-3 py-3 text-left rounded-tl-lg">Rang</th>
                                <th class="px-3 py-3 text-left">Université</th>
                                <th class="px-3 py-3 text-right">Score Global</th>
                                <th class="px-3 py-3 text-right rounded-tr-lg">Employabilité</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($univList as $u): ?>
                                <tr class="hover:bg-blue-50 transition-colors">
                                    <td class="px-3 py-3">
                                        <span class="inline-flex items-center justify-center w-8 h-8
                                                     bg-navy text-white rounded-full text-xs font-bold">
                                            <?= $u['rang'] ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-3">
                                        <a href="universite.php?id=<?= $u['id_univ'] ?>"
                                           class="font-semibold text-navy hover:text-accent transition-colors">
                                            <?= htmlspecialchars($u['nom']) ?>
                                        </a>
                                        <p class="text-xs text-gray-400">
                                            <?= htmlspecialchars($u['ville']) ?> · <?= htmlspecialchars($u['type_univ']) ?>
                                        </p>
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <span class="font-bold"><?= number_format($u['score_global'], 1) ?></span>
                                        <div class="w-16 bg-gray-100 rounded-full h-1.5 ml-auto mt-1">
                                            <div class="bg-accent h-1.5 rounded-full"
                                                 style="width: <?= $u['score_global'] ?>%"></div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <span class="<?= (float)$u['score_employeur'] > 80 ? 'text-green-600 font-bold' : 'text-gray-600' ?>">
                                            <?= number_format((float)$u['score_employeur'], 1) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Bar chart évolution rang moyen (1/3) -->
        <div>
            <div class="bg-white rounded-2xl shadow p-6">
                <h3 class="text-base font-bold text-navy mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-chart-column text-accent"></i> Rang moyen par édition
                </h3>
                <?php if (count($rankEvol) < 2): ?>
                    <p class="text-gray-500 text-sm">Données insuffisantes.</p>
                <?php else: ?>
                    <div style="position:relative;height:280px;">
                        <canvas id="chartRankEvol"
                                aria-label="Évolution du rang moyen des universités de <?= htmlspecialchars($paysData['nom']) ?>"></canvas>
                    </div>
                    <p class="text-xs text-gray-400 mt-2 text-center">Plus bas = meilleur classement</p>
                <?php endif; ?>
            </div>

            <!-- Données de synthèse -->
            <?php if (!empty($rankEvol)): ?>
                <div class="bg-white rounded-2xl shadow p-6 mt-4">
                    <h3 class="text-base font-bold text-navy mb-3">Évolution détaillée</h3>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-gray-500 text-xs uppercase">
                                <th class="text-left py-1">Édition</th>
                                <th class="text-right py-1">Rang moy.</th>
                                <th class="text-right py-1">Score moy.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($rankEvol as $r): ?>
                                <tr>
                                    <td class="py-2 font-medium"><?= $r['annee'] ?></td>
                                    <td class="py-2 text-right">#<?= $r['rang_moyen'] ?></td>
                                    <td class="py-2 text-right text-accent font-bold"><?= $r['score_moyen'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php if (count($rankEvol) >= 2): ?>
<script>
new Chart(document.getElementById('chartRankEvol'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($rankEvol, 'annee')) ?>,
        datasets: [{
            label: 'Rang moyen',
            data: <?= json_encode(array_column($rankEvol, 'rang_moyen')) ?>,
            backgroundColor: ['#1E3A5F','#E67E22','#2980B9','#27AE60'],
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ` Rang moyen : #${ctx.parsed.y}` } }
        },
        scales: {
            y: {
                reverse: true,
                title: { display: true, text: 'Rang moyen' }
            }
        }
    }
});
</script>
<?php endif; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
