"""
generate_insertion.py
Génère sql/insertion.sql à partir du vrai CSV QS 2025 (top 50 universités).
Éditions simulées : 2022, 2023, 2024, 2025
    - 2025 : données réelles du CSV
    - 2024 : rangs RANK_2024 du CSV + scores variés ±4 %
    - 2023 : scores variés ±8 %
    - 2022 : scores variés ±12 %
"""

import csv, random, re, io, os, sys

sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
random.seed(42)

# ─── Chemins ────────────────────────────────────────────────────────────────
BASE = os.path.dirname(os.path.abspath(__file__))
CSV_PATH = os.path.join(BASE, 'QS World University Rankings 2025 (Top global universities).csv')
OUT_PATH = os.path.join(BASE, '..', 'sql', 'insertion.sql')
TOP_N = 50

# ─── Utilitaires ─────────────────────────────────────────────────────────────
def parse_rank(s):
    if not s or s.strip() in ('', '-', 'N/A', 'n/a'):
        return None
    s = s.strip().replace('=', '').replace('+', '')
    if '-' in s:
        s = s.split('-')[0]
    try:
        return int(float(s))
    except:
        return None

def parse_score(s):
    if not s or s.strip() in ('', '-', 'N/A', 'n/a', '#N/A'):
        return None
    try:
        v = round(float(s.strip()), 1)
        return max(0.0, min(100.0, v))
    except:
        return None

def vary(score, pct):
    """Applique une variation aléatoire ±pct au score."""
    if score is None:
        return None
    delta = score * pct * (random.random() * 2 - 1)
    return round(max(0.0, min(100.0, score + delta)), 1)

def vary_rank(rank, delta):
    if rank is None:
        return None
    return max(1, rank + random.randint(-delta, delta))

def sql_val(v):
    if v is None:
        return 'NULL'
    if isinstance(v, float):
        return str(v)
    if isinstance(v, int):
        return str(v)
    return "'" + str(v).replace("'", "''") + "'"

# ─── Mapping pays ─────────────────────────────────────────────────────────────
COUNTRY_MAP = {
    'United States':        ('États-Unis',        'US', 'Amérique du Nord'),
    'United Kingdom':       ('Royaume-Uni',        'GB', 'Europe'),
    'Switzerland':          ('Suisse',             'CH', 'Europe'),
    'Singapore':            ('Singapour',          'SG', 'Asie'),
    'Australia':            ('Australie',          'AU', 'Océanie'),
    'China (Mainland)':     ('Chine',              'CN', 'Asie'),
    'Hong Kong SAR':        ('Hong Kong',          'HK', 'Asie'),
    'France':               ('France',             'FR', 'Europe'),
    'Canada':               ('Canada',             'CA', 'Amérique du Nord'),
    'Germany':              ('Allemagne',          'DE', 'Europe'),
    'Japan':                ('Japon',              'JP', 'Asie'),
    'South Korea':          ('Corée du Sud',       'KR', 'Asie'),
    'Netherlands':          ('Pays-Bas',           'NL', 'Europe'),
    'Sweden':               ('Suède',              'SE', 'Europe'),
    'Denmark':              ('Danemark',           'DK', 'Europe'),
    'Belgium':              ('Belgique',           'BE', 'Europe'),
    'New Zealand':          ('Nouvelle-Zélande',   'NZ', 'Océanie'),
    'Taiwan':               ('Taïwan',             'TW', 'Asie'),
    'India':                ('Inde',               'IN', 'Asie'),
    'Russia':               ('Russie',             'RU', 'Europe'),
    'Saudi Arabia':         ('Arabie Saoudite',    'SA', 'Asie'),
    'Malaysia':             ('Malaisie',           'MY', 'Asie'),
    'Brazil':               ('Brésil',             'BR', 'Amérique du Sud'),
    'Spain':                ('Espagne',            'ES', 'Europe'),
    'Italy':                ('Italie',             'IT', 'Europe'),
    'Finland':              ('Finlande',           'FI', 'Europe'),
    'Norway':               ('Norvège',            'NO', 'Europe'),
    'Austria':              ('Autriche',           'AT', 'Europe'),
    'Ireland':              ('Irlande',            'IE', 'Europe'),
    'China':                ('Chine',              'CN', 'Asie'),
}

# ─── Mapping villes ───────────────────────────────────────────────────────────
CITY_MAP = {
    'Massachusetts Institute of Technology (MIT)':              'Cambridge',
    'Imperial College London':                                   'Londres',
    'University of Oxford':                                      'Oxford',
    'Harvard University':                                        'Cambridge',
    'University of Cambridge':                                   'Cambridge',
    'Stanford University':                                       'Stanford',
    'ETH Zurich - Swiss Federal Institute of Technology':        'Zurich',
    'National University of Singapore (NUS)':                    'Singapour',
    'UCL':                                                       'Londres',
    'California Institute of Technology (Caltech)':              'Pasadena',
    'University of Pennsylvania':                                'Philadelphie',
    'University of California, Berkeley (UCB)':                  'Berkeley',
    'The University of Melbourne':                               'Melbourne',
    'Peking University':                                         'Pékin',
    'Nanyang Technological University, Singapore (NTU)':         'Singapour',
    'Cornell University':                                        'Ithaca',
    'The University of Hong Kong':                               'Hong Kong',
    'The University of Sydney':                                  'Sydney',
    'The University of New South Wales (UNSW Sydney)':           'Sydney',
    'Tsinghua University':                                       'Pékin',
    'University of Chicago':                                     'Chicago',
    'Princeton University':                                      'Princeton',
    'Yale University':                                           'New Haven',
    'Université PSL':                                            'Paris',
    'University of Toronto':                                     'Toronto',
    'EPFL':                                                      'Lausanne',
    'The University of Edinburgh':                               'Édimbourg',
    'Technical University of Munich':                            'Munich',
    'McGill University':                                         'Montréal',
    'The Australian National University':                        'Canberra',
    'Seoul National University':                                 'Séoul',
    'Johns Hopkins University':                                  'Baltimore',
    'The University of Tokyo':                                   'Tokyo',
    'Columbia University':                                       'New York',
    'The University of Manchester':                              'Manchester',
    'The Chinese University of Hong Kong (CUHK)':               'Hong Kong',
    'Monash University':                                         'Melbourne',
    'University of British Columbia':                            'Vancouver',
    'Fudan University':                                          'Shanghai',
    "King's College London":                                     'Londres',
    'The University of Queensland':                              'Brisbane',
    'University of California, Los Angeles (UCLA)':              'Los Angeles',
    'New York University (NYU)':                                 'New York',
    'University of Michigan-Ann Arbor':                          'Ann Arbor',
    'Shanghai Jiao Tong University':                             'Shanghai',
    'Institut Polytechnique de Paris':                           'Palaiseau',
    'The Hong Kong University of Science and Technology':        'Hong Kong',
    'Zhejiang University':                                       'Hangzhou',
    'Delft University of Technology':                            'Delft',
    'Kyoto University':                                          'Kyoto',
}

# ─── Type d'université ────────────────────────────────────────────────────────
TECHNO = {
    'Massachusetts Institute of Technology (MIT)',
    'ETH Zurich - Swiss Federal Institute of Technology',
    'California Institute of Technology (Caltech)',
    'EPFL',
    'Technical University of Munich',
    'Nanyang Technological University, Singapore (NTU)',
    'Delft University of Technology',
    'Shanghai Jiao Tong University',
    'The Hong Kong University of Science and Technology',
}
PRIVATE = {
    'Harvard University', 'Stanford University', 'Yale University',
    'Princeton University', 'Cornell University', 'University of Pennsylvania',
    'Columbia University', 'Johns Hopkins University', 'University of Chicago',
    'New York University (NYU)',
}
GRANDE_ECOLE = {
    'Institut Polytechnique de Paris',
}
# Tout le reste → Publique

def get_type(name):
    if name in TECHNO:       return 'Institut technologique'
    if name in GRANDE_ECOLE: return 'Grande École'
    if name in PRIVATE:      return 'Privée'
    return 'Publique'

# ─── Acronymes ────────────────────────────────────────────────────────────────
ACRONYM_MAP = {
    'Massachusetts Institute of Technology (MIT)':              'MIT',
    'Imperial College London':                                   'ICL',
    'University of Oxford':                                      'Oxford',
    'Harvard University':                                        'Harvard',
    'University of Cambridge':                                   'Cambridge',
    'Stanford University':                                       'Stanford',
    'ETH Zurich - Swiss Federal Institute of Technology':        'ETH',
    'National University of Singapore (NUS)':                    'NUS',
    'UCL':                                                       'UCL',
    'California Institute of Technology (Caltech)':              'Caltech',
    'University of Pennsylvania':                                'UPenn',
    'University of California, Berkeley (UCB)':                  'UC Berkeley',
    'The University of Melbourne':                               'UniMelb',
    'Peking University':                                         'PKU',
    'Nanyang Technological University, Singapore (NTU)':         'NTU',
    'Cornell University':                                        'Cornell',
    'The University of Hong Kong':                               'HKU',
    'The University of Sydney':                                  'USyd',
    'The University of New South Wales (UNSW Sydney)':           'UNSW',
    'Tsinghua University':                                       'Tsinghua',
    'University of Chicago':                                     'UChicago',
    'Princeton University':                                      'Princeton',
    'Yale University':                                           'Yale',
    'Université PSL':                                            'PSL',
    'University of Toronto':                                     'UofT',
    'EPFL':                                                      'EPFL',
    'The University of Edinburgh':                               'UoE',
    'Technical University of Munich':                            'TUM',
    'McGill University':                                         'McGill',
    'The Australian National University':                        'ANU',
    'Seoul National University':                                 'SNU',
    'Johns Hopkins University':                                  'JHU',
    'The University of Tokyo':                                   'UTokyo',
    'Columbia University':                                       'Columbia',
    'The University of Manchester':                              'UoM',
    'The Chinese University of Hong Kong (CUHK)':               'CUHK',
    'Monash University':                                         'Monash',
    'University of British Columbia':                            'UBC',
    'Fudan University':                                          'Fudan',
    "King's College London":                                     'KCL',
    'The University of Queensland':                              'UQ',
    'University of California, Los Angeles (UCLA)':              'UCLA',
    'New York University (NYU)':                                 'NYU',
    'University of Michigan-Ann Arbor':                          'UMich',
    'Shanghai Jiao Tong University':                             'SJTU',
    'Institut Polytechnique de Paris':                           'IP Paris',
    'The Hong Kong University of Science and Technology':        'HKUST',
    'Zhejiang University':                                       'ZJU',
    'Delft University of Technology':                            'TU Delft',
    'Kyoto University':                                          'KyotoU',
}

# ─── Lecture CSV ─────────────────────────────────────────────────────────────
with open(CSV_PATH, encoding='utf-8-sig', errors='replace') as f:
    all_rows = list(csv.DictReader(f))

# Prendre les TOP_N premières (rang numérique le plus bas)
def sort_key(r):
    rk = parse_rank(r['RANK_2025'])
    return rk if rk is not None else 9999

all_rows.sort(key=sort_key)
rows = all_rows[:TOP_N]

print(f"  {len(rows)} universités sélectionnées.")

# ─── Construction des entités ─────────────────────────────────────────────────
pays_set = {}    # code_iso → (id, nom, code_iso, continent)
types_set = {}   # libelle → id
univs = []       # list of dicts

pays_id = 1
type_id = 1

for r in rows:
    loc = r['Location'].strip()
    if loc not in COUNTRY_MAP:
        print(f"  PAYS INCONNU : {loc}")
        continue
    nom_pays, code_iso, continent = COUNTRY_MAP[loc]
    if code_iso not in pays_set:
        pays_set[code_iso] = (pays_id, nom_pays, code_iso, continent)
        pays_id += 1

    type_lib = get_type(r['Institution_Name'].strip())
    if type_lib not in types_set:
        types_set[type_lib] = type_id
        type_id += 1

types_list = [(v, k) for k, v in types_set.items()]
types_list.sort()

# ─── Données pour chaque université ──────────────────────────────────────────
univ_list = []
univ_id = 1
for r in rows:
    loc = r['Location'].strip()
    if loc not in COUNTRY_MAP:
        continue
    nom = r['Institution_Name'].strip()
    _, code_iso, _ = COUNTRY_MAP[loc]
    pid = pays_set[code_iso][0]
    tid = types_set[get_type(nom)]
    ville = CITY_MAP.get(nom, 'N/A')
    acro  = ACRONYM_MAP.get(nom, '')

    s2025 = {
        'rang':     parse_rank(r['RANK_2025']),
        'rep_acad': parse_score(r['Academic_Reputation_Score']),
        'employe':  parse_score(r['Employer_Reputation_Score']),
        'ratio':    parse_score(r['Faculty_Student_Score']),
        'cit':      parse_score(r['Citations_per_Faculty_Score']),
        'intl_etu': parse_score(r['International_Students_Score']),
        'intl_ens': parse_score(r['International_Faculty_Score']),
        'global':   parse_score(r['Overall_Score']),
    }

    # 2024 : rang réel du CSV, scores ±4 %
    rank_2024 = parse_rank(r['RANK_2024'])
    s2024 = {
        'rang':     rank_2024 if rank_2024 else vary_rank(s2025['rang'], 4),
        'rep_acad': vary(s2025['rep_acad'], 0.04),
        'employe':  vary(s2025['employe'],  0.04),
        'ratio':    vary(s2025['ratio'],     0.04),
        'cit':      vary(s2025['cit'],       0.04),
        'intl_etu': vary(s2025['intl_etu'],  0.04),
        'intl_ens': vary(s2025['intl_ens'],  0.04),
        'global':   vary(s2025['global'],    0.04),
    }

    # 2023 : ±8 %
    s2023 = {
        'rang':     vary_rank(s2024['rang'], 8),
        'rep_acad': vary(s2024['rep_acad'], 0.08),
        'employe':  vary(s2024['employe'],  0.08),
        'ratio':    vary(s2024['ratio'],     0.08),
        'cit':      vary(s2024['cit'],       0.08),
        'intl_etu': vary(s2024['intl_etu'],  0.08),
        'intl_ens': vary(s2024['intl_ens'],  0.08),
        'global':   vary(s2024['global'],    0.08),
    }

    # 2022 : ±12 %
    s2022 = {
        'rang':     vary_rank(s2023['rang'], 12),
        'rep_acad': vary(s2023['rep_acad'], 0.12),
        'employe':  vary(s2023['employe'],  0.12),
        'ratio':    vary(s2023['ratio'],     0.12),
        'cit':      vary(s2023['cit'],       0.12),
        'intl_etu': vary(s2023['intl_etu'],  0.12),
        'intl_ens': vary(s2023['intl_ens'],  0.12),
        'global':   vary(s2023['global'],    0.12),
    }

    univ_list.append({
        'id':    univ_id,
        'nom':   nom,
        'acro':  acro,
        'ville': ville,
        'pid':   pid,
        'tid':   tid,
        '2022':  s2022,
        '2023':  s2023,
        '2024':  s2024,
        '2025':  s2025,
    })
    univ_id += 1

# ─── Génération SQL ───────────────────────────────────────────────────────────
lines = []
lines.append("-- ==========================================================================")
lines.append("-- QS World University Rankings — DML")
lines.append(f"-- Généré automatiquement depuis : QS World University Rankings 2025.csv")
lines.append(f"-- Top {TOP_N} universités · 4 éditions (2022, 2023, 2024, 2025)")
lines.append(f"-- Éditions 2022/2023 : scores interpolés (±12%/±8%) depuis les données 2025 réelles")
lines.append(f"-- Édition 2024 : rangs réels RANK_2024 du CSV + scores interpolés ±4%")
lines.append(f"-- Édition 2025 : données réelles du CSV QS 2025")
lines.append("-- ==========================================================================")
lines.append("")
lines.append("SET NAMES utf8mb4;")
lines.append("SET FOREIGN_KEY_CHECKS = 0;")
lines.append("")

# PAYS
lines.append("-- ---------------------------------------------------------------------------")
lines.append("-- PAYS")
lines.append("-- ---------------------------------------------------------------------------")
lines.append("INSERT INTO PAYS (id_pays, nom, code_iso, continent) VALUES")
pays_rows = sorted(pays_set.values(), key=lambda x: x[0])
for i, (pid, nom, iso, cont) in enumerate(pays_rows):
    comma = ',' if i < len(pays_rows) - 1 else ';'
    lines.append(f"({pid}, {sql_val(nom)}, {sql_val(iso)}, {sql_val(cont)}){comma}")
lines.append("")

# TYPE_UNIVERSITE
lines.append("-- ---------------------------------------------------------------------------")
lines.append("-- TYPE_UNIVERSITE")
lines.append("-- ---------------------------------------------------------------------------")
lines.append("INSERT INTO TYPE_UNIVERSITE (id_type, libelle) VALUES")
for i, (tid, tlib) in enumerate(types_list):
    comma = ',' if i < len(types_list) - 1 else ';'
    lines.append(f"({tid}, {sql_val(tlib)}){comma}")
lines.append("")

# UNIVERSITE
lines.append("-- ---------------------------------------------------------------------------")
lines.append("-- UNIVERSITE")
lines.append("-- ---------------------------------------------------------------------------")
lines.append("INSERT INTO UNIVERSITE (id_univ, nom, acronyme, ville, id_pays, id_type) VALUES")
for i, u in enumerate(univ_list):
    comma = ',' if i < len(univ_list) - 1 else ';'
    lines.append(f"({u['id']}, {sql_val(u['nom'])}, {sql_val(u['acro'])}, {sql_val(u['ville'])}, {u['pid']}, {u['tid']}){comma}")
lines.append("")

# EDITION_QS
lines.append("-- ---------------------------------------------------------------------------")
lines.append("-- EDITION_QS")
lines.append("-- ---------------------------------------------------------------------------")
lines.append("INSERT INTO EDITION_QS (id_edition, annee) VALUES")
lines.append("(1, 2022),")
lines.append("(2, 2023),")
lines.append("(3, 2024),")
lines.append("(4, 2025);")
lines.append("")

# CLASSEMENT_REF — ARWU Shanghai (universités présentes dans Shanghai mais pas toutes dans QS top 50)
arwu_refs = [
    'Harvard University',
    'Stanford University',
    'Massachusetts Institute of Technology (MIT)',
    'University of Cambridge',
    'University of California, Berkeley (UCB)',
    'Princeton University',
    'Columbia University',
    'University of Chicago',
    'Yale University',
    'Johns Hopkins University',
    'University of California, Los Angeles (UCLA)',
    'Cornell University',
    'University of Michigan-Ann Arbor',
    'University of Toronto',
    'The University of Tokyo',
]
lines.append("-- ---------------------------------------------------------------------------")
lines.append("-- CLASSEMENT_REF (Shanghai ARWU 2024 — pour requête R3 NOT IN)")
lines.append("-- ---------------------------------------------------------------------------")
lines.append("INSERT INTO CLASSEMENT_REF (id_ref, nom_institution, source) VALUES")
for i, nom in enumerate(arwu_refs):
    comma = ',' if i < len(arwu_refs) - 1 else ';'
    lines.append(f"({i+1}, {sql_val(nom)}, 'ARWU Shanghai 2024'){comma}")
lines.append("")

# SCORE_QS
editions = [
    (1, '2022'),
    (2, '2023'),
    (3, '2024'),
    (4, '2025'),
]

total_scores = len(univ_list) * len(editions)
score_idx = 0

for ed_id, ed_year in editions:
    lines.append(f"-- ---------------------------------------------------------------------------")
    lines.append(f"-- SCORE_QS — Édition {ed_year}")
    lines.append(f"-- ---------------------------------------------------------------------------")
    lines.append("INSERT INTO SCORE_QS (rang, score_rep_acad, score_employeur, score_ratio, score_citations, score_intl_etu, score_intl_ens, score_global, id_univ, id_edition) VALUES")
    score_rows = []
    for u in univ_list:
        s = u[ed_year]
        score_rows.append(
            f"({sql_val(s['rang'])}, {sql_val(s['rep_acad'])}, {sql_val(s['employe'])}, "
            f"{sql_val(s['ratio'])}, {sql_val(s['cit'])}, {sql_val(s['intl_etu'])}, "
            f"{sql_val(s['intl_ens'])}, {sql_val(s['global'])}, {u['id']}, {ed_id})"
        )
    for i, sr in enumerate(score_rows):
        comma = ',' if i < len(score_rows) - 1 else ';'
        lines.append(sr + comma)
        score_idx += 1
    lines.append("")

lines.append("SET FOREIGN_KEY_CHECKS = 1;")
lines.append("")

# ─── Écriture ────────────────────────────────────────────────────────────────
with open(OUT_PATH, 'w', encoding='utf-8') as f:
    f.write('\n'.join(lines))

print(f"  Fichier généré : {OUT_PATH}")
print(f"  Pays          : {len(pays_set)}")
print(f"  Types         : {len(types_set)}")
print(f"  Universités   : {len(univ_list)}")
print(f"  Lignes SCORE_QS : {score_idx}")
print(f"  CLASSEMENT_REF  : {len(arwu_refs)}")
