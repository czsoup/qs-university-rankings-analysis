<?php
/**
 * recommandation.php — Moteur de recommandation personnalisé (F1 + bonus)
 * AC-15 : sliders sum=100%, top 10 personnalisé
 * Bonus +2 pts : requête score pondéré côté SQL
 */
require_once __DIR__ . '/connexion.php';

$pageTitle = 'Recommandation personnalisée';
$editions  = getAllEditions($pdo);
$results   = [];
$submitted = false;
$errorMsg  = '';

// Poids par défaut (en pourcentage)
$defaults = [
    'w_rep_acad'  => 40,
    'w_employeur' => 10,
    'w_ratio'     => 20,
    'w_citations' => 20,
    'w_intl_etu'  => 5,
    'w_intl_ens'  => 5,
];

$weights = $defaults;
$anneeSelected = '';
$normalizedInfo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = true;

    // Récupération des poids bruts
    $weightKeys = array_keys($defaults);
    $total = 0;
    foreach ($weightKeys as $key) {
        $val = filter_input(INPUT_POST, $key, FILTER_VALIDATE_FLOAT);
        if ($val === false || $val === null || $val < 0 || $val > 100) {
            $val = 0;
        }
        $weights[$key] = $val;
        $total += $val;
    }

    $anneeSelected = filter_input(INPUT_POST, 'annee', FILTER_VALIDATE_INT);

    if (!$anneeSelected) {
        $errorMsg = "Veuillez sélectionner une édition de référence.";
    } elseif ($total <= 0) {
        $errorMsg = "Au moins un critère doit avoir un poids supérieur à 0.";
    } else {
        // Auto-normalisation : ramener la somme à exactement 100
        if (abs($total - 100) > 0.5) {
            foreach ($weightKeys as $key) {
                $weights[$key] = round($weights[$key] / $total * 100, 1);
            }
            $normalizedInfo = "Les poids ont été normalisés automatiquement à 100 % (total saisi : " . round($total) . " %).";
        }
        // Requête bonus : score pondéré personnalisé (AC-15 + +2 pts)
        $sql = '
            SELECT u.id_univ, u.nom AS universite, u.acronyme,
                   p.nom AS pays, p.code_iso,
                   t.libelle AS type_univ,
                   sq.rang,
                   ROUND(
                       (:w1 * COALESCE(sq.score_rep_acad,  0)
                      + :w2 * COALESCE(sq.score_employeur, 0)
                      + :w3 * COALESCE(sq.score_ratio,     0)
                      + :w4 * COALESCE(sq.score_citations, 0)
                      + :w5 * COALESCE(sq.score_intl_etu,  0)
                      + :w6 * COALESCE(sq.score_intl_ens,  0)
                       ) / 100,
                   2) AS score_perso,
                   sq.score_global
            FROM SCORE_QS sq
            JOIN UNIVERSITE u      ON u.id_univ    = sq.id_univ
            JOIN PAYS p            ON p.id_pays    = u.id_pays
            JOIN TYPE_UNIVERSITE t ON t.id_type    = u.id_type
            JOIN EDITION_QS e      ON e.id_edition = sq.id_edition
            WHERE e.annee = :annee
            ORDER BY score_perso DESC
            LIMIT 10
        ';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':w1'    => $weights['w_rep_acad'],
            ':w2'    => $weights['w_employeur'],
            ':w3'    => $weights['w_ratio'],
            ':w4'    => $weights['w_citations'],
            ':w5'    => $weights['w_intl_etu'],
            ':w6'    => $weights['w_intl_ens'],
            ':annee' => $anneeSelected,
        ]);
        $results = $stmt->fetchAll();
    }
}

require_once __DIR__ . '/partials/header.php';
?>

<h1 class="text-2xl font-bold text-navy mb-2 flex items-center gap-2">
    <i class="fa-solid fa-bullseye text-accent"></i> Recommandation personnalisée
</h1>
<p class="text-gray-500 mb-6">Définissez vos priorités et découvrez les universités qui vous correspondent le mieux.</p>

<div class="grid lg:grid-cols-2 gap-8">

    <!-- Formulaire de pondération -->
    <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="text-lg font-bold text-navy mb-5">Vos critères de sélection</h2>

        <form method="post" action="recommandation.php" id="form-reco">

            <!-- Sélecteur édition -->
            <div class="mb-6">
                <label for="annee-select" class="block text-sm font-semibold text-gray-700 mb-2">
                    Édition de référence
                </label>
                <select id="annee-select" name="annee"
                        class="w-full border-2 border-gray-200 rounded-lg px-4 py-2.5 focus:border-accent focus:outline-none text-sm">
                    <?php foreach ($editions as $ed): ?>
                        <option value="<?= $ed['annee'] ?>"
                            <?= (string)$anneeSelected === (string)$ed['annee'] ? 'selected' : '' ?>>
                            Édition <?= $ed['annee'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Indicateur de somme + bouton reset -->
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <p class="text-sm font-semibold text-gray-700">Total des poids :</p>
                    <span id="total-display" class="font-black text-lg transition-colors text-green-600">100%</span>
                    <span id="total-hint" class="text-xs text-gray-400">(auto-normalisé à 100 % à la soumission)</span>
                </div>
                <button type="button" id="btn-reset"
                        class="text-xs text-navy border border-navy/30 rounded-lg px-3 py-1.5 hover:bg-navy hover:text-white transition-colors flex items-center gap-1.5">
                    <i class="fa-solid fa-rotate-left"></i> Poids QS
                </button>
            </div>

            <!-- Barre de progression globale (somme) — indicative, non bloquante -->
            <div class="w-full bg-gray-100 rounded-full h-2.5 mb-5 overflow-hidden">
                <div id="total-bar" class="h-2.5 rounded-full transition-all bg-green-500"
                     style="width: 100%"></div>
            </div>

            <!-- Sliders -->
            <?php
            $sliders = [
                ['name' => 'w_rep_acad',  'label' => 'Réputation académique', 'pct' => '40%', 'icon' => 'fa-solid fa-book-open'],
                ['name' => 'w_employeur', 'label' => 'Réputation employeur',  'pct' => '10%', 'icon' => 'fa-solid fa-briefcase'],
                ['name' => 'w_ratio',     'label' => 'Ratio étu/enseignants', 'pct' => '20%', 'icon' => 'fa-solid fa-users'],
                ['name' => 'w_citations', 'label' => 'Citations/enseignant',  'pct' => '20%', 'icon' => 'fa-solid fa-quote-left'],
                ['name' => 'w_intl_etu',  'label' => 'Etudiants intern.',     'pct' => '5%',  'icon' => 'fa-solid fa-plane'],
                ['name' => 'w_intl_ens',  'label' => 'Enseignants intern.',   'pct' => '5%',  'icon' => 'fa-solid fa-earth-europe'],
            ];
            foreach ($sliders as $s):
                $val = (float)$weights[$s['name']];
            ?>
                <div class="mb-5">
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="slider-<?= $s['name'] ?>"
                               class="text-sm font-medium text-gray-700 flex items-center gap-1.5">
                            <i class="<?= $s['icon'] ?> text-gray-400 w-4 text-center" aria-hidden="true"></i>
                            <?= $s['label'] ?>
                            <span class="text-xs text-gray-400">(poids QS : <?= $s['pct'] ?>)</span>
                        </label>
                        <span id="val-<?= $s['name'] ?>"
                              class="text-sm font-bold text-navy w-12 text-right">
                            <?= number_format($val, 0) ?>%
                        </span>
                    </div>
                    <input type="range"
                           id="slider-<?= $s['name'] ?>"
                           name="<?= $s['name'] ?>"
                           min="0" max="100" step="1"
                           value="<?= $val ?>"
                           class="w-full accent-orange-500 h-2 rounded-full cursor-pointer"
                           aria-label="<?= $s['label'] ?>">
                </div>
            <?php endforeach; ?>

            <?php if ($errorMsg): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4 text-sm text-red-700 flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($errorMsg) ?>
                </div>
            <?php endif; ?>

            <button type="submit"
                    class="w-full bg-accent hover:bg-orange-600 text-white font-bold py-3 rounded-xl
                           transition-colors text-base shadow-md flex items-center justify-center gap-2">
                <i class="fa-solid fa-magnifying-glass"></i> Calculer mon top 10
            </button>
            <p class="text-center text-xs text-gray-400 mt-2">
                Si le total ne fait pas 100 %, les poids seront normalisés automatiquement.
            </p>
        </form>
    </div>

    <!-- Résultats -->
    <div>
        <?php if (!$submitted): ?>
            <div class="bg-blue-50 border-2 border-dashed border-blue-200 rounded-2xl p-12 text-center h-full flex flex-col items-center justify-center">
                <i class="fa-solid fa-bullseye text-5xl text-blue-300 mb-4"></i>
                <p class="text-lg font-semibold text-navy mb-2">Définissez vos priorités</p>
                <p class="text-gray-500 text-sm">Ajustez les curseurs et cliquez sur "Calculer" pour obtenir votre classement personnalisé.</p>
            </div>

        <?php elseif ($errorMsg): ?>
            <div class="bg-red-50 border-2 border-dashed border-red-200 rounded-2xl p-12 text-center">
                <i class="fa-solid fa-circle-xmark text-4xl text-red-400 mb-3 block"></i>
                <p class="font-semibold text-red-700">Correction nécessaire</p>
                <p class="text-sm text-red-500 mt-2"><?= htmlspecialchars($errorMsg) ?></p>
            </div>

        <?php elseif (empty($results)): ?>
            <div class="bg-yellow-50 border-2 border-dashed border-yellow-200 rounded-2xl p-8 text-center">
                <i class="fa-solid fa-triangle-exclamation text-4xl text-yellow-400 mb-3 block"></i>
                <p class="font-semibold text-gray-700">Aucun résultat pour ces critères.</p>
            </div>

        <?php else: ?>
            <div class="bg-white rounded-2xl shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-navy">Votre top 10 personnalisé</h2>
                    <span class="text-xs bg-accent/10 text-accent font-semibold px-3 py-1 rounded-full">
                        Édition <?= htmlspecialchars($anneeSelected) ?>
                    </span>
                </div>

                <?php if ($normalizedInfo): ?>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg px-3 py-2 mb-4 text-xs text-blue-700 flex items-center gap-2">
                        <i class="fa-solid fa-circle-info flex-shrink-0"></i>
                        <?= htmlspecialchars($normalizedInfo) ?>
                    </div>
                <?php endif; ?>

                <!-- Résumé des poids appliqués -->
                <div class="flex flex-wrap gap-2 mb-5">
                    <?php
                    $weightLabels = [
                        'w_rep_acad' => 'Acad.',
                        'w_employeur' => 'Empl.',
                        'w_ratio'    => 'Ratio',
                        'w_citations'=> 'Cit.',
                        'w_intl_etu' => 'Int.Étu',
                        'w_intl_ens' => 'Int.Ens',
                    ];
                    foreach ($weightLabels as $k => $lbl):
                        if ($weights[$k] > 0): ?>
                            <span class="text-xs bg-navy text-white px-2 py-1 rounded-full">
                                <?= $lbl ?> : <?= number_format($weights[$k], 0) ?>%
                            </span>
                        <?php endif;
                    endforeach; ?>
                </div>

                <ol class="space-y-3">
                    <?php foreach ($results as $i => $r): ?>
                        <li class="flex items-center gap-3 p-3 rounded-xl hover:bg-blue-50 transition-colors">
                            <span class="flex-shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-full
                                         font-black text-sm
                                         <?= $i === 0 ? 'bg-yellow-400 text-white' : ($i === 1 ? 'bg-gray-300 text-gray-700' : ($i === 2 ? 'bg-orange-300 text-white' : 'bg-gray-100 text-gray-500')) ?>">
                                <?= $i + 1 ?>
                            </span>
                            <div class="flex-1 min-w-0">
                                <a href="universite.php?id=<?= $r['id_univ'] ?>"
                                   class="font-semibold text-navy hover:text-accent transition-colors truncate block text-sm">
                                    <?= htmlspecialchars($r['universite']) ?>
                                </a>
                                <p class="text-xs text-gray-400"><?= htmlspecialchars($r['pays']) ?> · Rang QS #<?= $r['rang'] ?></p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                <p class="text-lg font-black text-accent"><?= $r['score_perso'] ?></p>
                                <p class="text-xs text-gray-400">score perso.</p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const sliders   = document.querySelectorAll('input[type="range"]');
    const totalDisp = document.getElementById('total-display');
    const totalBar  = document.getElementById('total-bar');
    const btnReset  = document.getElementById('btn-reset');

    // Poids par défaut QS officiels
    const QS_DEFAULTS = {
        w_rep_acad:  40,
        w_employeur: 10,
        w_ratio:     20,
        w_citations: 20,
        w_intl_etu:   5,
        w_intl_ens:   5,
    };

    function updateTotal() {
        let sum = 0;
        sliders.forEach(s => {
            const val = parseFloat(s.value) || 0;
            document.getElementById('val-' + s.name).textContent = val.toFixed(0) + '%';
            sum += val;
        });
        sum = Math.round(sum * 10) / 10;
        totalDisp.textContent = sum + '%';

        // Barre visuelle : proportionnelle, max 100%
        totalBar.style.width = Math.min(sum, 100) + '%';

        // Couleur indicative seulement (pas bloquant)
        const ok = Math.abs(sum - 100) <= 5;
        totalDisp.className = 'font-black text-lg transition-colors ' +
            (sum === 0 ? 'text-gray-400' : ok ? 'text-green-600' : 'text-amber-500');
        totalBar.className = 'h-2.5 rounded-full transition-all ' +
            (ok ? 'bg-green-500' : sum > 100 ? 'bg-amber-400' : 'bg-blue-400');
    }

    // Bouton reset : repasser aux poids QS officiels
    if (btnReset) {
        btnReset.addEventListener('click', () => {
            sliders.forEach(s => {
                if (QS_DEFAULTS[s.name] !== undefined) {
                    s.value = QS_DEFAULTS[s.name];
                }
            });
            updateTotal();
        });
    }

    sliders.forEach(s => s.addEventListener('input', updateTotal));
    updateTotal();
})();
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
