# -*- coding: utf-8 -*-
"""
Generate MCD and MLD diagrams as PNG files.
Run: python rapport/generate_schemas.py
Output: rapport/mcd.png  and  rapport/mld.png
"""
import matplotlib
matplotlib.use('Agg')
import matplotlib.pyplot as plt
import matplotlib.patches as mpatches
from matplotlib.patches import FancyBboxPatch
import os
import sys
sys.stdout.reconfigure(encoding='utf-8', errors='replace')

OUT_DIR = os.path.dirname(os.path.abspath(__file__))

NAVY   = '#1E3A5F'
ACCENT = '#E67E22'
LIGHT  = '#EAF0FB'
GRAY   = '#6B7280'
WHITE  = '#FFFFFF'
GREEN  = '#27AE60'
PURPLE = '#7C3AED'

# =============================================================================
# SHARED: draw a UML entity box, returns anchor points
# =============================================================================
def draw_entity(ax, cx, cy, title, attrs, width=3.4, row_h=0.38,
                header_h=0.52, hdr_color=NAVY):
    n = len(attrs)
    total_h = header_h + n * row_h + 0.14
    x0 = cx - width / 2
    y0 = cy - total_h / 2

    # shadow
    ax.add_patch(FancyBboxPatch((x0+0.04, y0-0.04), width, total_h,
                                boxstyle='round,pad=0.04', linewidth=0,
                                facecolor='#00000022', zorder=1))
    # body
    ax.add_patch(FancyBboxPatch((x0, y0), width, total_h,
                                boxstyle='round,pad=0.04', linewidth=1.5,
                                edgecolor=NAVY, facecolor=WHITE, zorder=2))
    # header
    ax.add_patch(FancyBboxPatch((x0, y0+total_h-header_h), width, header_h,
                                boxstyle='round,pad=0.0', linewidth=0,
                                facecolor=hdr_color, zorder=3))
    ax.text(cx, y0+total_h-header_h/2, title,
            ha='center', va='center', fontsize=10, fontweight='bold',
            color=WHITE, zorder=4)
    ax.plot([x0, x0+width], [y0+total_h-header_h]*2,
            color=NAVY, lw=1.2, zorder=4)

    for i, (attr, is_pk, note) in enumerate(attrs):
        ry = y0 + total_h - header_h - (i+0.5)*row_h - 0.07
        bg = '#F9FAFB' if i % 2 == 0 else WHITE
        ax.add_patch(plt.Rectangle((x0, ry - row_h/2), width, row_h,
                                   facecolor=bg, zorder=2, linewidth=0))
        prefix = '#' if is_pk else '  '
        c = ACCENT if is_pk else '#1F2937'
        w = 'bold' if is_pk else 'normal'
        ax.text(x0+0.14, ry, f'{prefix} {attr}',
                ha='left', va='center', fontsize=8, color=c,
                fontweight=w, zorder=5)
        if note:
            ax.text(cx+width/2-0.12, ry, note,
                    ha='right', va='center', fontsize=6.8, color=GRAY,
                    fontstyle='italic', zorder=5)

    return {
        'top':   (cx, y0+total_h),
        'bot':   (cx, y0),
        'left':  (x0, cy),
        'right': (x0+width, cy),
        'cx': cx, 'cy': cy,
        'x0': x0, 'x1': x0+width,
        'y0': y0, 'y1': y0+total_h,
    }


def arrow(ax, p1, p2, c1='', c2='', color=NAVY, rad=0.0):
    ax.annotate('', xy=p2, xytext=p1,
                arrowprops=dict(arrowstyle='->', color=color, lw=1.5,
                                connectionstyle=f'arc3,rad={rad}'), zorder=6)
    dx = p2[0]-p1[0]; dy = p2[1]-p1[1]
    norm = max((dx**2+dy**2)**0.5, 0.01)
    px = -dy/norm*0.18; py = dx/norm*0.18
    if c1:
        ax.text(p1[0]+dx*0.14+px, p1[1]+dy*0.14+py, c1,
                fontsize=8, color=ACCENT, fontweight='bold',
                ha='center', va='center', zorder=7)
    if c2:
        ax.text(p2[0]-dx*0.14+px, p2[1]-dy*0.14+py, c2,
                fontsize=8, color=ACCENT, fontweight='bold',
                ha='center', va='center', zorder=7)


# =============================================================================
# MCD
# =============================================================================
def draw_mcd():
    fig, ax = plt.subplots(figsize=(16, 10))
    ax.set_xlim(-0.5, 15.5)
    ax.set_ylim(-0.8, 10)
    ax.axis('off')
    fig.patch.set_facecolor('#F8FAFC')
    ax.set_facecolor('#F8FAFC')

    ax.text(7.5, 9.6, 'Modele Conceptuel de Donnees (MCD)',
            ha='center', fontsize=15, fontweight='bold', color=NAVY)
    ax.text(7.5, 9.2, 'QS World University Rankings — Projet BD n°6',
            ha='center', fontsize=9, color=GRAY)

    # Entities
    PAYS = draw_entity(ax, 1.9, 6.8, 'PAYS', [
        ('id_pays',   True,  'INT UNSIGNED'),
        ('nom',       False, 'VARCHAR(100)'),
        ('code_iso',  False, 'CHAR(2) UNIQUE'),
        ('continent', False, 'VARCHAR(50)'),
    ], width=3.4, hdr_color=NAVY)

    TYPE = draw_entity(ax, 13.1, 6.8, 'TYPE_UNIVERSITE', [
        ('id_type', True,  'INT UNSIGNED'),
        ('libelle', False, 'VARCHAR(60) UNIQUE'),
    ], width=3.4, hdr_color=NAVY)

    UNIV = draw_entity(ax, 7.5, 7.0, 'UNIVERSITE', [
        ('id_univ',  True,  'INT UNSIGNED'),
        ('nom',      False, 'VARCHAR(200)'),
        ('acronyme', False, 'VARCHAR(20)'),
        ('ville',    False, 'VARCHAR(100)'),
        ('*id_pays', False, 'FK PAYS'),
        ('*id_type', False, 'FK TYPE_UNIVERSITE'),
    ], width=3.8, hdr_color=ACCENT)

    EDIT = draw_entity(ax, 1.9, 2.6, 'EDITION_QS', [
        ('id_edition', True,  'INT UNSIGNED'),
        ('annee',      False, 'YEAR UNIQUE'),
    ], width=3.2, hdr_color=NAVY)

    SCORE = draw_entity(ax, 7.5, 2.4, 'SCORE_QS', [
        ('id_score',        True,  'INT UNSIGNED'),
        ('rang',            False, 'SMALLINT UNSIGNED'),
        ('score_rep_acad',  False, 'DECIMAL(5,1) [40%]'),
        ('score_employeur', False, 'DECIMAL(5,1) [10%]'),
        ('score_ratio',     False, 'DECIMAL(5,1) [20%]'),
        ('score_citations', False, 'DECIMAL(5,1) [20%]'),
        ('score_intl_etu',  False, 'DECIMAL(5,1) [5%]'),
        ('score_intl_ens',  False, 'DECIMAL(5,1) [5%]'),
        ('score_global',    False, 'DECIMAL(5,1) NOT NULL'),
        ('*id_univ',        False, 'FK UNIVERSITE'),
        ('*id_edition',     False, 'FK EDITION_QS'),
    ], width=4.4, hdr_color=GREEN)

    REF = draw_entity(ax, 13.1, 2.6, 'CLASSEMENT_REF', [
        ('id_ref',          True,  'INT UNSIGNED'),
        ('nom_institution', False, 'VARCHAR(200)'),
        ('source',          False, 'VARCHAR(100)'),
    ], width=3.4, hdr_color=PURPLE)

    # Arrows
    arrow(ax, PAYS['right'],  UNIV['left'],  c1='1,1', c2='1,n', color=NAVY)
    arrow(ax, TYPE['left'],   UNIV['right'], c1='1,1', c2='1,n', color=NAVY)
    arrow(ax, UNIV['bot'],    SCORE['top'],  c1='1,n', c2='1,1', color=GREEN)
    arrow(ax, EDIT['right'],  SCORE['left'], c1='1,n', c2='1,1', color=GREEN)

    # UNIQUE badge
    bx, bw, bh = 5.5, 4.0, 0.5
    ax.add_patch(FancyBboxPatch((bx, -0.65), bw, bh,
                                boxstyle='round,pad=0.1', linewidth=1.2,
                                edgecolor=GREEN, facecolor='#ECFDF5', zorder=6))
    ax.text(bx+bw/2, -0.4, 'UNIQUE (id_univ, id_edition)',
            ha='center', va='center', fontsize=8, color=GREEN,
            fontweight='bold', zorder=7)

    ax.text(0.0, -0.4, '# = Cle primaire   * = Cle etrangere   [x%] = poids QS',
            fontsize=7.5, color=GRAY)

    legend_items = [
        mpatches.Patch(color=NAVY,   label='Entite principale'),
        mpatches.Patch(color=ACCENT, label='UNIVERSITE (entite centrale)'),
        mpatches.Patch(color=GREEN,  label='SCORE_QS (table de faits)'),
        mpatches.Patch(color=PURPLE, label='CLASSEMENT_REF (auxiliaire)'),
    ]
    ax.legend(handles=legend_items, loc='lower right', fontsize=8.5,
              framealpha=0.95, edgecolor='#CBD5E1')

    plt.tight_layout(pad=0.4)
    out = os.path.join(OUT_DIR, 'mcd.png')
    plt.savefig(out, dpi=150, bbox_inches='tight', facecolor='#F8FAFC')
    plt.close()
    print('MCD saved:', out)


# =============================================================================
# MLD  — compact 2-column layout, well-spaced
# =============================================================================
def draw_mld():
    fig, ax = plt.subplots(figsize=(18, 13))
    ax.set_xlim(0, 18)
    ax.set_ylim(-0.9, 13)
    ax.axis('off')
    fig.patch.set_facecolor('#F8FAFC')
    ax.set_facecolor('#F8FAFC')

    ax.text(9, 12.6, 'Modele Logique de Donnees (MLD)',
            ha='center', fontsize=15, fontweight='bold', color=NAVY)
    ax.text(9, 12.2, 'QS World University Rankings — Projet BD n°6',
            ha='center', fontsize=9, color=GRAY)

    def mld_box(cx, cy, tname, cols, width=5.2, row_h=0.35, hdr_h=0.48,
                hdr_color=NAVY):
        total_h = hdr_h + len(cols)*row_h + 0.08
        x0 = cx - width/2
        y0 = cy - total_h/2
        ax.add_patch(FancyBboxPatch((x0+0.05, y0-0.05), width, total_h,
                                    boxstyle='round,pad=0.04', linewidth=0,
                                    facecolor='#00000018', zorder=1))
        ax.add_patch(FancyBboxPatch((x0, y0), width, total_h,
                                    boxstyle='round,pad=0.04', linewidth=1.5,
                                    edgecolor=NAVY, facecolor=WHITE, zorder=2))
        ax.add_patch(FancyBboxPatch((x0, y0+total_h-hdr_h), width, hdr_h,
                                    boxstyle='round,pad=0.0', linewidth=0,
                                    facecolor=hdr_color, zorder=3))
        ax.text(cx, y0+total_h-hdr_h/2, tname,
                ha='center', va='center', fontsize=9.5, fontweight='bold',
                color=WHITE, zorder=4)
        ax.plot([x0, x0+width], [y0+total_h-hdr_h]*2, color=NAVY, lw=1, zorder=4)

        c1w = width * 0.42
        for i, (cname, ctype, cnote, is_pk, is_fk) in enumerate(cols):
            ry = y0 + total_h - hdr_h - (i+1)*row_h
            bg = '#F9FAFB' if i % 2 == 0 else WHITE
            ax.add_patch(plt.Rectangle((x0, ry), width, row_h,
                                       facecolor=bg, zorder=2, linewidth=0))
            mid = ry + row_h/2
            pref = '#' if is_pk else ('*' if is_fk else ' ')
            col_c = ACCENT if is_pk else (GREEN if is_fk else '#111827')
            wgt = 'bold' if (is_pk or is_fk) else 'normal'
            ax.text(x0+0.12, mid, f'{pref} {cname}',
                    ha='left', va='center', fontsize=8, color=col_c,
                    fontweight=wgt, zorder=5)
            ax.text(x0+c1w+0.1, mid, f'{ctype}  {cnote}',
                    ha='left', va='center', fontsize=7, color=GRAY,
                    fontstyle='italic', zorder=5)
        ax.plot([x0+c1w, x0+c1w], [y0, y0+total_h-hdr_h],
                color='#E5E7EB', lw=0.8, zorder=4)

        return {'cx':cx,'cy':cy,'x0':x0,'x1':x0+width,'y0':y0,'y1':y0+total_h,
                'top':(cx,y0+total_h),'bot':(cx,y0),
                'left':(x0,cy),'right':(x0+width,cy)}

    TW = 5.2
    P  = mld_box(3.0, 10.5, 'PAYS', [
        ('id_pays',   'INT UNSIGNED', 'PK AUTO_INCREMENT', True,  False),
        ('nom',       'VARCHAR(100)', 'NOT NULL',           False, False),
        ('code_iso',  'CHAR(2)',      'NOT NULL UNIQUE',    False, False),
        ('continent', 'VARCHAR(50)',  'NOT NULL',           False, False),
    ], width=TW, hdr_color=NAVY)

    T  = mld_box(15.0, 10.5, 'TYPE_UNIVERSITE', [
        ('id_type', 'INT UNSIGNED', 'PK AUTO_INCREMENT', True,  False),
        ('libelle', 'VARCHAR(60)',  'NOT NULL UNIQUE',   False, False),
    ], width=TW, hdr_color=NAVY)

    U  = mld_box(9.0, 7.8, 'UNIVERSITE', [
        ('id_univ',  'INT UNSIGNED',  'PK AUTO_INCREMENT',           True,  False),
        ('nom',      'VARCHAR(200)',  'NOT NULL',                    False, False),
        ('acronyme', 'VARCHAR(20)',   'DEFAULT NULL',                False, False),
        ('ville',    'VARCHAR(100)',  'NOT NULL',                    False, False),
        ('id_pays',  'INT UNSIGNED',  'FK PAYS(id_pays)',            False, True),
        ('id_type',  'INT UNSIGNED',  'FK TYPE_UNIVERSITE(id_type)', False, True),
    ], width=TW+0.8, hdr_color=ACCENT)

    E  = mld_box(3.0, 3.4, 'EDITION_QS', [
        ('id_edition', 'INT UNSIGNED', 'PK AUTO_INCREMENT', True,  False),
        ('annee',      'YEAR',         'NOT NULL UNIQUE',   False, False),
    ], width=TW, hdr_color=NAVY)

    S  = mld_box(9.0, 3.6, 'SCORE_QS', [
        ('id_score',        'INT UNSIGNED',      'PK AUTO_INCREMENT',          True,  False),
        ('rang',            'SMALLINT UNSIGNED', 'NOT NULL',                   False, False),
        ('score_rep_acad',  'DECIMAL(5,1)',      'NULL  [40%]',                False, False),
        ('score_employeur', 'DECIMAL(5,1)',      'NULL  [10%]',                False, False),
        ('score_ratio',     'DECIMAL(5,1)',      'NULL  [20%]',                False, False),
        ('score_citations', 'DECIMAL(5,1)',      'NULL  [20%]',                False, False),
        ('score_intl_etu',  'DECIMAL(5,1)',      'NULL  [5%]',                 False, False),
        ('score_intl_ens',  'DECIMAL(5,1)',      'NULL  [5%]',                 False, False),
        ('score_global',    'DECIMAL(5,1)',      'NOT NULL  CHECK 0-100',       False, False),
        ('id_univ',         'INT UNSIGNED',      'FK UNIVERSITE(id_univ)',      False, True),
        ('id_edition',      'INT UNSIGNED',      'FK EDITION_QS(id_edition)',   False, True),
    ], width=TW+1.0, hdr_color=GREEN)

    R  = mld_box(15.0, 3.4, 'CLASSEMENT_REF', [
        ('id_ref',          'INT UNSIGNED',  'PK AUTO_INCREMENT', True,  False),
        ('nom_institution', 'VARCHAR(200)',  'NOT NULL',           False, False),
        ('source',          'VARCHAR(100)',  'NOT NULL',           False, False),
    ], width=TW, hdr_color=PURPLE)

    # FK arrows
    def fk_arrow(p1, p2, color=NAVY):
        ax.annotate('', xy=p2, xytext=p1,
                    arrowprops=dict(arrowstyle='->', color=color, lw=1.6,
                                   connectionstyle='arc3,rad=0.05'), zorder=7)

    fk_arrow(U['left'],  (P['x1'], P['cy']), NAVY)
    fk_arrow(U['right'], (T['x0'], T['cy']), NAVY)
    fk_arrow(S['top'],   U['bot'],           GREEN)
    fk_arrow(S['left'],  (E['x1'], E['cy']), GREEN)

    # Constraints note
    ax.add_patch(FancyBboxPatch((0.1, -0.82), 13.0, 0.78,
                                boxstyle='round,pad=0.1', linewidth=1,
                                edgecolor='#CBD5E1', facecolor='#F1F5F9', zorder=5))
    ax.text(0.35, -0.45,
            '# = Cle primaire (PK)   * = Cle etrangere (FK)\n'
            'UNIQUE (id_univ, id_edition) sur SCORE_QS   CHECK score_global BETWEEN 0 AND 100',
            ha='left', va='center', fontsize=8, color=GRAY, zorder=6)

    legend_items = [
        mpatches.Patch(color=NAVY,   label='Entite de reference'),
        mpatches.Patch(color=ACCENT, label='UNIVERSITE (entite centrale)'),
        mpatches.Patch(color=GREEN,  label='SCORE_QS (table de faits)'),
        mpatches.Patch(color=PURPLE, label='CLASSEMENT_REF (auxiliaire)'),
    ]
    ax.legend(handles=legend_items, loc='lower right', fontsize=9,
              framealpha=0.95, edgecolor='#CBD5E1')

    plt.tight_layout(pad=0.4)
    out = os.path.join(OUT_DIR, 'mld.png')
    plt.savefig(out, dpi=150, bbox_inches='tight', facecolor='#F8FAFC')
    plt.close()
    print('MLD saved:', out)


if __name__ == '__main__':
    draw_mcd()
    draw_mld()
    print('Done.')
