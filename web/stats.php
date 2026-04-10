<?php
/**
 * stats.php — Interface analytique pour les 8 requêtes SQL obligatoires (R1–R8)
 * AC-18 : 8 requêtes disponibles, résultats tabulaires
 */
require_once __DIR__ . '/connexion.php';

$pageTitle = 'Statistiques & Analyses';
$editions  = getAllEditions($pdo);
$lastEdId  = getLastEditionId($pdo);

// --- Catalogue des 8 requêtes ---
// Chaque entrée : id, label, description, technique, sql (avec placeholders PDO), params callable
$queries = [

    'R1' => [
        'label'       => 'R1 — Employabilité > Réputation académique',
        'description' => 'Universités dont le score employeur est supérieur au score de réputation académique, avec l\'écart calculé.',
        'technique'   => 'Comparaison inter-colonnes + ORDER BY',
        'needs_edition' => true,
        'run' => function (PDO $pdo, int $edId) {
            $stmt = $pdo->prepare(
                'SELECT u.nom AS universite, p.nom AS pays,
                        sq.score_employeur, sq.score_rep_acad,
                        ROUND(sq.score_employeur - sq.score_rep_acad, 1) AS ecart,
                        sq.rang
                 FROM SCORE_QS sq
                 JOIN UNIVERSITE u ON u.id_univ    = sq.id_univ
                 JOIN PAYS p       ON p.id_pays    = u.id_pays
                 WHERE sq.id_edition = :eid
                   AND sq.score_employeur > sq.score_rep_acad
                 ORDER BY ecart DESC'
            );
            $stmt->execute([':eid' => $edId]);
            return $stmt->fetchAll();
        },
        'columns' => ['Université', 'Pays', 'Score Employeur', 'Score Académique', 'Écart', 'Rang QS'],
        'keys'    => ['universite', 'pays', 'score_employeur', 'score_rep_acad', 'ecart', 'rang'],
    ],

    'R2' => [
        'label'       => 'R2 — Évolution des universités françaises',
        'description' => 'Rang moyen et score global moyen des universités françaises pour chaque édition disponible.',
        'technique'   => 'Agrégation + filtre pays + GROUP BY édition',
        'needs_edition' => false,
        'run' => function (PDO $pdo, int $edId) {
            $stmt = $pdo->query(
                'SELECT e.annee,
                        ROUND(AVG(sq.rang), 1)         AS rang_moyen,
                        ROUND(AVG(sq.score_global), 1) AS score_moyen,
                        COUNT(sq.id_score)             AS nb_universites
                 FROM SCORE_QS sq
                 JOIN UNIVERSITE u ON u.id_univ    = sq.id_univ
                 JOIN PAYS p       ON p.id_pays    = u.id_pays
                 JOIN EDITION_QS e ON e.id_edition = sq.id_edition
                 WHERE p.code_iso = \'FR\'
                 GROUP BY e.id_edition, e.annee
                 ORDER BY e.annee ASC'
            );
            return $stmt->fetchAll();
        },
        'columns' => ['Édition', 'Rang moyen', 'Score moyen', 'Nb universités'],
        'keys'    => ['annee', 'rang_moyen', 'score_moyen', 'nb_universites'],
    ],

    'R3' => [
        'label'       => 'R3 — Top QS non présentes dans CLASSEMENT_REF',
        'description' => 'Universités dans le top 50 QS qui n\'apparaissent pas dans la table de référence CLASSEMENT_REF (autre classement).',
        'technique'   => 'Sous-requête NOT IN',
        'needs_edition' => true,
        'run' => function (PDO $pdo, int $edId) {
            $stmt = $pdo->prepare(
                'SELECT u.nom AS universite, p.nom AS pays, sq.rang, sq.score_global
                 FROM SCORE_QS sq
                 JOIN UNIVERSITE u ON u.id_univ = sq.id_univ
                 JOIN PAYS p       ON p.id_pays = u.id_pays
                 WHERE sq.id_edition = :eid
                   AND sq.rang <= 50
                   AND u.nom NOT IN (
                       SELECT nom_institution FROM CLASSEMENT_REF
                   )
                 ORDER BY sq.rang ASC'
            );
            $stmt->execute([':eid' => $edId]);
            return $stmt->fetchAll();
        },
        'columns' => ['Université', 'Pays', 'Rang QS', 'Score Global'],
        'keys'    => ['universite', 'pays', 'rang', 'score_global'],
    ],

    'R4' => [
        'label'       => 'R4 — Pays homogènes en employabilité (ALL)',
        'description' => 'Pays dont TOUTES les universités classées ont un score employeur supérieur à 80.',
        'technique'   => 'Sous-requête ALL',
        'needs_edition' => true,
        'run' => function (PDO $pdo, int $edId) {
            $stmt = $pdo->prepare(
                'SELECT p.nom AS pays, p.continent,
                        COUNT(sq.id_score) AS nb_universites,
                        ROUND(MIN(sq.score_employeur), 1) AS min_employeur
                 FROM PAYS p
                 JOIN UNIVERSITE u ON u.id_pays = p.id_pays
                 JOIN SCORE_QS sq  ON sq.id_univ = u.id_univ
                 WHERE sq.id_edition = :eid
                   AND sq.score_employeur IS NOT NULL
                   AND 80 < ALL (
                       SELECT sq2.score_employeur
                       FROM SCORE_QS sq2
                       JOIN UNIVERSITE u2 ON u2.id_univ = sq2.id_univ
                       WHERE u2.id_pays = p.id_pays
                         AND sq2.id_edition = :eid2
                         AND sq2.score_employeur IS NOT NULL
                   )
                 GROUP BY p.id_pays, p.nom, p.continent
                 ORDER BY min_employeur DESC'
            );
            $stmt->execute([':eid' => $edId, ':eid2' => $edId]);
            return $stmt->fetchAll();
        },
        'columns' => ['Pays', 'Continent', 'Nb Universités', 'Score min. Employeur'],
        'keys'    => ['pays', 'continent', 'nb_universites', 'min_employeur'],
    ],

    'R5' => [
        'label'       => 'R5 — Score global au-dessus de la moyenne nationale',
        'description' => 'Universités dont le score global dépasse la moyenne des universités du même pays pour l\'édition sélectionnée.',
        'technique'   => 'Sous-requête scalaire corrélée',
        'needs_edition' => true,
        'run' => function (PDO $pdo, int $edId) {
            $stmt = $pdo->prepare(
                'SELECT u.nom AS universite, p.nom AS pays,
                        sq.score_global,
                        ROUND((
                            SELECT AVG(sq2.score_global)
                            FROM SCORE_QS sq2
                            JOIN UNIVERSITE u2 ON u2.id_univ = sq2.id_univ
                            WHERE u2.id_pays = u.id_pays
                              AND sq2.id_edition = :eid2
                        ), 1) AS moy_nationale,
                        ROUND(sq.score_global - (
                            SELECT AVG(sq3.score_global)
                            FROM SCORE_QS sq3
                            JOIN UNIVERSITE u3 ON u3.id_univ = sq3.id_univ
                            WHERE u3.id_pays = u.id_pays
                              AND sq3.id_edition = :eid3
                        ), 1) AS ecart_moy,
                        sq.rang
                 FROM SCORE_QS sq
                 JOIN UNIVERSITE u ON u.id_univ = sq.id_univ
                 JOIN PAYS p       ON p.id_pays = u.id_pays
                 WHERE sq.id_edition = :eid
                   AND sq.score_global > (
                       SELECT AVG(sq4.score_global)
                       FROM SCORE_QS sq4
                       JOIN UNIVERSITE u4 ON u4.id_univ = sq4.id_univ
                       WHERE u4.id_pays = u.id_pays
                         AND sq4.id_edition = :eid4
                   )
                 ORDER BY ecart_moy DESC'
            );
            $stmt->execute([':eid' => $edId, ':eid2' => $edId, ':eid3' => $edId, ':eid4' => $edId]);
            return $stmt->fetchAll();
        },
        'columns' => ['Université', 'Pays', 'Score Global', 'Moy. Nationale', 'Écart', 'Rang'],
        'keys'    => ['universite', 'pays', 'score_global', 'moy_nationale', 'ecart_moy', 'rang'],
    ],

    'R6' => [
        'label'       => 'R6 — Critère le plus discriminant (dispersion)',
        'description' => 'Identifie le critère QS présentant la plus forte dispersion de scores (amplitude MAX–MIN) pour l\'édition sélectionnée.',
        'technique'   => 'STDDEV simulé (MAX-MIN + AVG)',
        'needs_edition' => true,
        'run' => function (PDO $pdo, int $edId) {
            $stmt = $pdo->prepare(
                'SELECT critere, amplitude, moy, max_val, min_val
                 FROM (
                     SELECT \'Rép. Académique\'  AS critere,
                            ROUND(MAX(score_rep_acad)  - MIN(score_rep_acad),  1) AS amplitude,
                            ROUND(AVG(score_rep_acad),  1) AS moy,
                            MAX(score_rep_acad)  AS max_val, MIN(score_rep_acad)  AS min_val
                     FROM SCORE_QS WHERE id_edition = :e1 AND score_rep_acad IS NOT NULL
                     UNION ALL
                     SELECT \'Employeur\',
                            ROUND(MAX(score_employeur) - MIN(score_employeur), 1),
                            ROUND(AVG(score_employeur), 1),
                            MAX(score_employeur), MIN(score_employeur)
                     FROM SCORE_QS WHERE id_edition = :e2 AND score_employeur IS NOT NULL
                     UNION ALL
                     SELECT \'Ratio Étu/Ens\',
                            ROUND(MAX(score_ratio)     - MIN(score_ratio),     1),
                            ROUND(AVG(score_ratio),    1),
                            MAX(score_ratio),     MIN(score_ratio)
                     FROM SCORE_QS WHERE id_edition = :e3 AND score_ratio IS NOT NULL
                     UNION ALL
                     SELECT \'Citations\',
                            ROUND(MAX(score_citations) - MIN(score_citations), 1),
                            ROUND(AVG(score_citations), 1),
                            MAX(score_citations), MIN(score_citations)
                     FROM SCORE_QS WHERE id_edition = :e4 AND score_citations IS NOT NULL
                     UNION ALL
                     SELECT \'Intl. Étudiants\',
                            ROUND(MAX(score_intl_etu)  - MIN(score_intl_etu),  1),
                            ROUND(AVG(score_intl_etu),  1),
                            MAX(score_intl_etu),  MIN(score_intl_etu)
                     FROM SCORE_QS WHERE id_edition = :e5 AND score_intl_etu IS NOT NULL
                     UNION ALL
                     SELECT \'Intl. Enseignants\',
                            ROUND(MAX(score_intl_ens)  - MIN(score_intl_ens),  1),
                            ROUND(AVG(score_intl_ens),  1),
                            MAX(score_intl_ens),  MIN(score_intl_ens)
                     FROM SCORE_QS WHERE id_edition = :e6 AND score_intl_ens IS NOT NULL
                 ) AS dispersions
                 ORDER BY amplitude DESC'
            );
            $stmt->execute([
                ':e1' => $edId, ':e2' => $edId, ':e3' => $edId,
                ':e4' => $edId, ':e5' => $edId, ':e6' => $edId,
            ]);
            return $stmt->fetchAll();
        },
        'columns' => ['Critère', 'Amplitude (MAX-MIN)', 'Moyenne', 'Max', 'Min'],
        'keys'    => ['critere', 'amplitude', 'moy', 'max_val', 'min_val'],
    ],

    'R7' => [
        'label'       => 'R7 — Universités très stables dans le classement',
        'description' => 'Universités ayant un écart de rang inférieur à 10 places sur toutes les éditions disponibles.',
        'technique'   => 'MAX-MIN rang sur toutes éditions',
        'needs_edition' => false,
        'run' => function (PDO $pdo, int $edId) {
            $stmt = $pdo->query(
                'SELECT u.nom AS universite, p.nom AS pays,
                        MIN(sq.rang) AS rang_min,
                        MAX(sq.rang) AS rang_max,
                        MAX(sq.rang) - MIN(sq.rang) AS amplitude_rang,
                        COUNT(sq.id_score) AS nb_editions
                 FROM SCORE_QS sq
                 JOIN UNIVERSITE u ON u.id_univ = sq.id_univ
                 JOIN PAYS p       ON p.id_pays = u.id_pays
                 GROUP BY sq.id_univ, u.nom, p.nom
                 HAVING MAX(sq.rang) - MIN(sq.rang) < 10
                    AND COUNT(sq.id_score) >= 2
                 ORDER BY amplitude_rang ASC, rang_min ASC'
            );
            return $stmt->fetchAll();
        },
        'columns' => ['Université', 'Pays', 'Rang min.', 'Rang max.', 'Amplitude', 'Nb éditions'],
        'keys'    => ['universite', 'pays', 'rang_min', 'rang_max', 'amplitude_rang', 'nb_editions'],
    ],

    'R8' => [
        'label'       => 'R8 — Top 10 meilleur ratio étudiants/enseignants',
        'description' => 'Top 10 universités par score ratio étudiants/enseignants pour l\'édition sélectionnée, avec leur rang général et pays.',
        'technique'   => 'Agrégation + tri + RANK simulé',
        'needs_edition' => true,
        'run' => function (PDO $pdo, int $edId) {
            $stmt = $pdo->prepare(
                'SELECT u.nom AS universite, p.nom AS pays,
                        sq.score_ratio, sq.rang AS rang_global,
                        sq.score_global,
                        RANK() OVER (ORDER BY sq.score_ratio DESC) AS rang_ratio
                 FROM SCORE_QS sq
                 JOIN UNIVERSITE u ON u.id_univ = sq.id_univ
                 JOIN PAYS p       ON p.id_pays = u.id_pays
                 WHERE sq.id_edition = :eid
                   AND sq.score_ratio IS NOT NULL
                 ORDER BY sq.score_ratio DESC
                 LIMIT 10'
            );
            $stmt->execute([':eid' => $edId]);
            return $stmt->fetchAll();
        },
        'columns' => ['Rang Ratio', 'Université', 'Pays', 'Score Ratio', 'Rang QS Global', 'Score Global'],
        'keys'    => ['rang_ratio', 'universite', 'pays', 'score_ratio', 'rang_global', 'score_global'],
    ],
];

// Exécution de la requête sélectionnée
$selectedQuery = filter_input(INPUT_GET, 'requete', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'R1';
if (!array_key_exists($selectedQuery, $queries)) {
    $selectedQuery = 'R1';
}
$editionId = filter_input(INPUT_GET, 'edition', FILTER_VALIDATE_INT) ?: $lastEdId;

$q = $queries[$selectedQuery];
$results = [];
$queryError = '';

try {
    $results = ($q['run'])($pdo, (int)$editionId);
} catch (PDOException $e) {
    error_log('stats.php query error: ' . $e->getMessage());
    $queryError = 'Erreur lors de l\'exécution de la requête. Vérifiez les données en base.';
}

require_once __DIR__ . '/partials/header.php';
?>

<h1 class="text-2xl font-bold text-navy mb-2 flex items-center gap-2">
    <i class="fa-solid fa-chart-bar text-accent"></i> Analyses statistiques
</h1>
<p class="text-gray-500 mb-6">Explorez les 8 requêtes analytiques obligatoires du projet.</p>

<!-- Filtres -->
<div class="bg-white rounded-2xl shadow p-6 mb-6">
    <form method="get" action="stats.php" class="grid sm:grid-cols-3 gap-4 items-end">

        <!-- Sélecteur requête -->
        <div class="sm:col-span-2">
            <label for="requete-select" class="block text-sm font-semibold text-gray-700 mb-2">
                Requête analytique
            </label>
            <select id="requete-select" name="requete"
                    class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:border-accent focus:outline-none text-sm">
                <?php foreach ($queries as $id => $q_item): ?>
                    <option value="<?= $id ?>" <?= $selectedQuery === $id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($q_item['label']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Sélecteur édition -->
        <div>
            <label for="edition-select" class="block text-sm font-semibold text-gray-700 mb-2">
                Édition de référence
            </label>
            <select id="edition-select" name="edition"
                    class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:border-accent focus:outline-none text-sm">
                <?php foreach ($editions as $ed): ?>
                    <option value="<?= $ed['id_edition'] ?>"
                        <?= (int)$editionId === (int)$ed['id_edition'] ? 'selected' : '' ?>>
                        Édition <?= $ed['annee'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">
                <?= $q['needs_edition'] ? 'Utilisée par cette requête.' : 'Non utilisée (résultat sur toutes éditions).' ?>
            </p>
        </div>

        <div class="sm:col-span-3">
            <button type="submit"
                    class="bg-accent hover:bg-orange-600 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors flex items-center gap-2">
                <i class="fa-solid fa-play text-xs"></i> Exécuter la requête
            </button>
        </div>
    </form>
</div>

<!-- Description de la requête -->
<div class="bg-navy text-white rounded-2xl p-5 mb-6">
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-black"><?= htmlspecialchars($q['label']) ?></h2>
            <p class="text-blue-200 mt-1 text-sm"><?= htmlspecialchars($q['description']) ?></p>
        </div>
        <span class="flex-shrink-0 bg-accent text-white text-xs font-bold px-3 py-1.5 rounded-full self-start">
            <?= htmlspecialchars($q['technique']) ?>
        </span>
    </div>
</div>

<!-- Résultats -->
<?php if ($queryError): ?>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">
        ⚠️ <?= htmlspecialchars($queryError) ?>
    </div>

<?php elseif (empty($results)): ?>
    <div class="bg-yellow-50 border-2 border-dashed border-yellow-200 rounded-2xl p-8 text-center">
        <i class="fa-solid fa-triangle-exclamation text-3xl text-yellow-400 mb-3 block"></i>
        <p class="font-semibold text-gray-700">Aucun résultat pour ces paramètres.</p>
        <p class="text-sm text-gray-500 mt-2">Modifiez l'édition ou vérifiez les données en base.</p>
    </div>

<?php else: ?>
    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <!-- En-tête tableau -->
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-bold text-navy"><?= htmlspecialchars($q['label']) ?></h3>
            <span class="text-sm text-gray-500"><?= count($results) ?> résultat(s)</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider">
                        <?php foreach ($q['columns'] as $col): ?>
                            <th class="px-4 py-3 text-left font-semibold"><?= htmlspecialchars($col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($results as $i => $row): ?>
                        <tr class="hover:bg-blue-50 transition-colors <?= $i === 0 ? 'bg-accent/5' : '' ?>">
                            <?php foreach ($q['keys'] as $j => $key): ?>
                                <td class="px-4 py-3
                                    <?= $j === 0 ? 'font-semibold text-navy' : 'text-gray-700' ?>">
                                    <?php
                                    $val = $row[$key] ?? '—';
                                    // Mise en forme : liens pour noms d'université
                                    if ($key === 'universite' && isset($row['id_univ'])) {
                                        echo '<a href="universite.php?id=' . (int)$row['id_univ']
                                           . '" class="text-navy hover:text-accent transition-colors">'
                                           . htmlspecialchars($val) . '</a>';
                                    } elseif (is_numeric($val) && strpos($val, '.') !== false) {
                                        echo '<span class="font-mono">' . number_format((float)$val, 1) . '</span>';
                                    } else {
                                        echo htmlspecialchars((string)$val);
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Navigation rapide entre requêtes -->
<div class="mt-8">
    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Toutes les analyses</h3>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <?php foreach ($queries as $id => $q_item): ?>
            <a href="stats.php?requete=<?= $id ?>&edition=<?= $editionId ?>"
               class="block p-3 rounded-xl border-2 transition-all text-sm
                      <?= $selectedQuery === $id
                          ? 'border-accent bg-accent/10 font-bold text-accent'
                          : 'border-gray-200 bg-white hover:border-navy text-gray-700 hover:text-navy' ?>">
                <span class="font-black text-xs block mb-1"><?= $id ?></span>
                <?= htmlspecialchars(preg_replace('/^R\d+ — /', '', $q_item['label'])) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
