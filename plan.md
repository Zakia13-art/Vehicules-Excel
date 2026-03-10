# Plan: Reimplementation of Email Report Feature

## Context
- **Current state**: `tableau.php` and `synthese.php` both have a simple modal that sends the page HTML to `send_email.php` (which converts HTML→PDF via mPDF). This approach has rendering issues since mPDF doesn't fully support the page's CSS.
- **Old approach** (from first commit): `send_report.php` generates PDFs server-side by reading Excel files, calculating values, and building clean mPDF-compatible HTML. It had a "send panel" UI with checkboxes to choose which reports to include (flotte, boutcherafin detail, synthese).
- **Problem**: The current `send_report.php` already exists but uses **old calculation formulas** that don't match the new implementations in `tableau.php`/`synthese.php`.

## New Calculation Formulas (from current tableau.php/synthese.php)
These must be replicated exactly in `send_report.php`:

| Column | Formula | Thresholds |
|--------|---------|------------|
| **A - Note /100** | `100 - (B×2) - (C×10) - (F×5)`, min 0 | ≥80 vert, 60-79 orange, <60 rouge |
| **B - Alertes CRIT** | Count of infractions per vehicle | ≤2 vert, 3-10 orange, >10 rouge |
| **C - Alertes /100km** | `B × 100 / km` | <0.5 vert, 0.5-1 orange, >1 rouge |
| **D - Heures** | Parsed from duration string | <40h vert, 40-50h orange, >50h rouge |
| **E - Km** | Parsed from kilomètre field | <4000 vert, 4000-5000 orange, >5000 rouge |
| **F - Charge** | `scoreD + scoreE` (sum, where D/E scores are 1/2/3) | =2 Faible/vert, 3-4 Moyenne/orange, ≥5 Élevée/rouge |
| **G - Risque** | CIMAT matrix on A,B,C values | ≥85+<15+<1→Faible, ≥70+<15+<1→Modéré, ≥55+<15+<1→Élevé, else→Critique |

**Data reading**: Uses infraction files (`*co-conduite*infraction*.xlsx`, sheet matching 'rapport') and kilométrage file (`*Kilom*.xlsx`, sheet matching 'Kilométrage').

## Changes

### 1. Update `send_report.php` — Align calculations with new formulas
**File**: `send_report.php`

- **Replace file reading logic**: Use `readExcelBySheetName()` approach (fuzzy sheet name matching with `stripos`) instead of exact sheet name matching. Read infraction files (filter `*infraction*` from eco files) and kilométrage file.
- **Replace all calculation functions** (`sNote`, `sHeures`, `sCharge`, `sRisque`, etc.) with exact copies from current `tableau.php`:
  - `scoreNoteConduite()` — formula: `100 - (B*2) - (C*10) - (F*5)`
  - `scoreAlertesCritiques()` — thresholds: ≤2/≤10/>10
  - `scoreAlertesParKm()` — formula: `B*100/km`, thresholds: <0.5/≤1/>1
  - `scoreHeuresConducte()` — thresholds: <40h/40-50h/>50h, scores 1/2/3
  - `scoreKilometrage()` — thresholds: <4000/4000-5000/>5000, scores 1/2/3
  - `scoreChargeConducte()` — sum of D+E scores, thresholds: =2/3-4/≥5
  - `scoreRisqueGlobal()` — CIMAT matrix
- **Update calculation order**: B,C,D,E → F → A → G (dependencies)
- **Update PDF HTML builders**:
  - `buildHtmlFlotte()` — match tableau.php column order: Véhicule, Note/100(A), Alertes(B), Alertes/100km(C), Heures(D), Km(E), Charge(F), Risque(G)
  - `buildHtmlSynthese()` — match synthese.php: Total Note /100, Total Alertes CRIT, Total Km, Moy. Infr. /100km, Infractions Sign (Rouge/Orange/Vert counts are vehicle counts by alertes_s status)
  - `buildHtmlBoutch()` — keep as-is (detail view)
- **Update risque labels**: 'Élevé' → match CIMAT: Faible/Modéré/Élevé/Critique

### 2. Update `tableau.php` — Replace modal with send panel + checkboxes
**File**: `tableau.php`

- **Remove** the current simple modal (`#emailModal`) and its JavaScript (`sendReport`, `closeModal`, `confirmSend` functions)
- **Remove** the current `send_email.php`-based approach
- **Add** a send panel (slide-in overlay) with:
  - Email input field (pre-filled with `MAIL_TO`)
  - Checkboxes for report selection:
    - ✅ Rapport Flotte Transport BOUTCHERAFIN (`flotte`)
    - ✅ BOUTCHERAFIN — Détail (`boutcherafin`)
    - ✅ Rapport Par Société (`synthese`)
  - "Tout sélectionner" button
  - Submit → `send_report.php` via GET (same as old approach)
- **Update** the send button to open the panel instead of the modal
- **Add** CSS for the send panel (`.send-panel`, `.send-box`, etc.)

### 3. Update `synthese.php` — Add same send panel
**File**: `synthese.php`

- **Remove** the current simple modal and JavaScript
- **Add** the same send panel as tableau.php (identical UI)
- **Update** the send button to open the panel

### 4. No changes needed to `send_email.php`
The `send_email.php` file can remain as fallback but will no longer be called from tableau.php/synthese.php.

## Files Modified
1. `send_report.php` — Major update: align all calculations with CIMAT formulas
2. `tableau.php` — Replace modal with send panel + checkboxes targeting `send_report.php`
3. `synthese.php` — Replace modal with send panel + checkboxes targeting `send_report.php`
