---
name: Multi-stage content loader
overview: "Master plan: efemer.cz keyworded (107) → Instagram stage-1 (rate-limited; dump empty) → KeywordIngest IG → embeddings → taxonomy → materialize."
todos:
  - id: schema-ingest
    content: Base ingest schema + stage-1 load tables
    status: completed
  - id: reset-script
    content: Reset imported content job (DB + env GCS shard)
    status: completed
  - id: stage1-loaders
    content: Katu jobs for efemer.cz / Instagram / dump; contentHash; quality flags
    status: completed
  - id: stage1-gallery-slots
    content: efemer.cz sourceSlot pre_copy/post_copy + galleryIndex on ingest_item_files
    status: completed
  - id: jobs-ctrl-c
    content: Signals watchdog + jobs:kill + ./bin/job TTY wrapper
    status: completed
  - id: migration-enrichment
    content: IngestEnrichmentSchema — cover/role/vision/layoutHints/enrichmentVersion; migrate locally
    status: completed
  - id: models-enrichment
    content: Extend IngestItem / IngestItemFile for enrichment fields + locks
    status: completed
  - id: gemini-client
    content: Gemini REST client + Secrets::GEMINI_API_KEY (+ .secret/gemini.api fallback)
    status: completed
  - id: keyword-analyzer
    content: KeywordAnalyzer prompt, image attach, JSON apply with lock respect
    status: completed
  - id: keyword-job
    content: Jobs.Ingest.KeywordIngest (limit/rekeyword/id/maxImages/dryRun)
    status: completed
  - id: admin-enrichment-light
    content: Admin detail — cover/role/rating/alt + enrichmentVersion
    status: completed
  - id: docs-ingest-stage2
    content: Update ingest.mdc with KeywordIngest + Gemini secret
    status: completed
  - id: smoke-one-item
    content: Smoke KeywordIngest on one local item; check admin
    status: completed
  - id: cover-eval-set
    content: Golden set of ~20 cover judgments for prompt regression
    status: completed
  - id: role-eval-set
    content: Golden set of ~15 posts with expected lead vs support roles
    status: completed
  - id: corpus-tooling
    content: Keyword export CSV/JSON, co-occurrence, admin facets
    status: completed
  - id: qc-prompt-v3
    content: "kw-v3 prompt: cover prefers pre_copy; layout pattern priors; kind funeral/mourning; keyword normalize; allow demote"
    status: completed
  - id: finish-keyword-corpus
    content: "efemer.cz KeywordIngest done (107 kw-v3); eval 100%; export 20261028; Instagram keywording later if needed"
    status: completed
  - id: stage1-body-thin-text
    content: "Investigate empty bodyHtml / thin_text on ~59 efemer posts (blocks hero_text_gallery); fix scraper or accept image-led"
    status: pending
  - id: kw-normalize-typos
    content: Expand typo map (florika→floristika); keep demote calibration soft until junk frames appear
    status: completed
  - id: keyword-qc-loop
    content: KeywordQc inline after each LLM apply (typos/cover/roles/pattern); needs_rekeyword re-queued via KeywordIngest
    status: completed
  - id: embeddings-dedup
    content: Embeddings for clustering/dedup/series; duplicateOfId
    status: pending
  - id: taxonomy-discovery
    content: Pause after keyworded corpus; decide multi-label taxonomy; assign
    status: pending
  - id: stage3-materialize
    content: Pilot then materialize; layoutJson from layoutHints; cover/alts/demote
    status: pending
  - id: ingest-admin-rich
    content: Admin facets, manual cover/role pick (locks), reject visibility, layout preview
    status: pending
  - id: rules-wire-archive
    content: Rules polish; keywords as search index; archive stays stage-1 loop
    status: pending
isProject: false
---

# Multi-stage content loader (master)

Single source of truth for the ingest → LLM → taxonomy → public posts pipeline. (Former “Stage 2 LLM analysis” plan is merged here.)

## Guiding principle: keywords first, taxonomy later

Do **not** lock categories (`ritualy` / `himmelhaus` / `umeni`) during import.

1. **Complete load** of all sources into ingest
2. **Keyword + vision** — keywords, photo ratings, cover, story roles, reject/demote, alt, crop, palette, series, layoutHints
3. **Inspect the corpus** — exports, facets, clusters, embeddings
4. **Decide final category shape** — may be multi-label; may add emerging categories
5. **Assign categories** to ingest items
6. **Pilot materialize**, then full materialize (layout from photo roles)

Today’s three site sections remain provisional until taxonomy is settled.

## Pipeline

```mermaid
flowchart LR
  subgraph stage1 [Stage1 load done]
    E[efemer.cz]
    F[efemer_flowers]
    H[himmlhaus]
  end
  IE[ingest_items]
  stage1 --> IE
  IE --> stage2[Stage2 KeywordIngest]
  stage2 --> KW[keywords cover roles alt]
  KW --> corpus[Export facets embeddings]
  corpus --> review[Taxonomy discovery]
  review --> cats[Assign categories]
  cats --> pilot[Pilot materialize]
  pilot --> stage3[Full materialize]
  stage3 --> posts[posts layoutJson]
```

| Layer | Tables | Role |
|---|---|---|
| Imported | `ingest_items`, `ingest_item_files` | Source mirror + LLM enrichments |
| Internal | `posts`, `post_files`, `events` | After taxonomy + stage 3 |

**Interlink:** `ingest_items.postId` ↔ `posts.ingestItemId` (`ON DELETE SET NULL`).

---

## Stage 1 — load (done)

See [`.cursor/rules/ingest.mdc`](.cursor/rules/ingest.mdc) and [`.cursor/rules/jobs.mdc`](.cursor/rules/jobs.mdc).

- Sources → `ingest_items` / `ingest_item_files` only (not public `posts`)
- GCS `gs://efemer/{DEV|PROD}/…`, contentHash dedupe, quality flags
- efemer.cz **`sourceSlot`**: `pre_copy` | `post_copy`; **`galleryIndex`**
- Instagram slots = `unknown`
- Katu jobs + `./bin/job` (TTY); `jobs:kill`; Ctrl+C watchdog under `-T`
- Admin list/detail at `/admin/ingest`
- Unique `(source, sourceKey)` with normalized keys (no leading `/`)

### Corpus note (efemer.cz)

Typical diary: short Czech text + many photos. Original HTML often has a small gallery **before** `section.copy` (lead prior) and a large gallery **after** (support).

---

## Photo story roles (for layout)

Orthogonal to cover and demote. Cover may also be `lead`; demoted → `role=skip`.

| Role | Meaning | Layout |
|---|---|---|
| `lead` | Integral story beat | Large / interleaved; often `pre_copy` |
| `support` | Atmosphere / sequence | Gallery after text |
| `detail` | Close-up / material | Quieter gallery / lightbox |
| `context` | Establishing place | Buried or paired |
| `skip` | Blurry / meme / dup / off-brand | Omitted publicly |

**Classifier signals:** (1) `sourceSlot`, (2) vision + body, (3) rating ≠ automatic lead, (4) near-dup demote, (5) Instagram order as weak prior.

**`layoutHints` / later `posts.layoutJson` patterns:** `hero_text_gallery`, `text_lead_gallery`, `gallery_essay`, `single`.

---

## Schema

### Already present

`ingest_items`: source keys, title, postedAt, bodyHtml, contentHash, status, kind, keywords, themes, entities, qualityFlags, categories, classificationRaw, embedding, duplicateOfId, postId, eventId.

`ingest_item_files`: storageFileId, position, caption, keywords, contentHash, sourceSlot, galleryIndex.

### Stage 2 enrichment migration (`IngestEnrichmentSchema`)

**`ingest_items` add:** `coverFileId`, `coverRationale`, `coverLocked`, `seriesKey`, `palette`, `layoutHints`, `enrichmentVersion`, `enrichedAt`.

**`ingest_item_files` add:** `rating`, `isCover`, `role`, `roleRationale`, `roleLocked`, `altText`, `orientation`, `focalCrop`, `demote`.

On materialize: copy onto `posts` / `post_files`; write `layoutJson`; respect locks.

---

## Stage 2 — KeywordIngest (next to build)

### Goal

`loaded` → `keyworded` with searchable keywords + layout-ready photo metadata. **No taxonomy / materialize.**

### Architecture

```mermaid
flowchart LR
  job[KeywordIngest] --> pick[Pick items]
  pick --> gemini[Gemini Flash multimodal]
  gemini --> apply[Write enrichment]
  apply --> admin[Admin detail]
```

- **Model:** `gemini-3.8-flash` via `generativelanguage.googleapis.com` REST (no new Composer package)
- **Secret:** `Secrets::GEMINI_API_KEY` (`gemini-api-key` in GSM); local fallback `.secret/gemini.api`; fail fast if missing
- **Per item:** one multimodal call — title + ~4k stripped body + up to **12** images (GCS → GD resize ≤1024 → JPEG ~80 → base64)
- **Skip** videos / failed downloads; note missing positions in prompt
- **Priors:** qualityFlags, sourceSlot, galleryIndex, position

### Classes

| Class | Role |
|---|---|
| `App\Classes\ThirdParty\Google\Gemini` | `generateContent`, 429/503 backoff |
| `App\Classes\Ingest\KeywordAnalyzer` | Prompt, images, parse JSON, apply |
| `App\Classes\Jobs\Ingest\KeywordIngest` | Katu job |

### Prompt / apply

Czech keywords + altText; English enums for roles. Output: item keywords/themes/entities/kind/cover(by position)+rationale/palette/seriesKey/layoutHints; per-file rating/role/alt/orientation/focalCrop/demote.

- demote → `role=skip`; cover among non-skip preferring lead
- Respect `coverLocked` / `roleLocked`
- Store raw JSON in `classificationRaw`; set `enrichmentVersion=kw-v1`, `enrichedAt`, `status=keyworded`
- On error: leave `loaded`, log failure, continue

### Job CLI

```bash
./bin/job jobs:run App.Classes.Jobs.Ingest.KeywordIngest -f
./bin/job jobs:run App.Classes.Jobs.Ingest.KeywordIngest --args='{"limit":5,"rekeyword":true}' -f
./bin/job jobs:run App.Classes.Jobs.Ingest.KeywordIngest --args='{"dryRun":true,"limit":1}' -f
```

Args: `limit` (default 20), `rekeyword`, `id`, `maxImages` (12), `dryRun`. Skip `keyworded` + matching `enrichmentVersion` unless rekeyword. `Signals` between items. Log token usage when present.

### Admin (light, with stage 2)

Cover badge; role + rating + alt on photos; keywords/themes; `enrichmentVersion` / `enrichedAt`. Richer pick/lock UI later (`ingest-admin-rich`).

### Smoke

One real item locally; confirm admin detail.

---

## LLM quality control

### Round 1 (pre–kw-v3) — addressed

Cover sometimes ignored `pre_copy`; `diary`/`product` overused; `gallery_essay` default; typos; rating inflation. Fixed in **kw-v3** prompt + apply rules.

### Round 2 (kw-v3, 21 items, 2026-10-28 afternoon)

| Metric | Result |
|---|---|
| Status | 21 `keyworded` / `kw-v3`, 86 `loaded` |
| Eval cover | **100%** (19/19 scored; *Svatba Mlhových* not in batch yet) |
| Eval roles | **100%** (16/16) |
| Eval patterns | **5/5** pass |
| Cover slot | **21/21 pre_copy** (0 post_copy covers) |
| pre_copy → lead | **21/21** |
| Demote | still **0** (corpus may simply lack junk frames) |
| Rating mean | **0.75** (was ~0.81) — improved |

**Kinds now useful:** wedding 6, installation 5, bouquet 4, portrait 2, gift 2, workshop 1, mourning 1. Examples look right (*Poslední květy* → mourning, *pro mámu* → gift, *Filip*/*čemeřice* → bouquet). Mild ambiguity: *Barbora* as bouquet vs wedding — acceptable for soft kind.

**Keywords:** mostly clean Czech; one leak `přírodní florika` (typo map should add `florika`→`floristika`). Festival casing normalized to lowercase in v3 runs.

**Layout patterns:** `gallery_essay` 16 / `single` 4 / `hero_text_gallery` 1. **Not a prompt bug** — 20/21 keyworded items are `thin_text` with **empty `bodyHtml`**. Only *masopustní veselky* has body (771 chars) → correctly `hero_text_gallery`. Site-wide: **59/107** efemer items have empty body; **48** have text (mostly older posts not yet keyworded). So pattern diversity will improve as long-copy posts get enriched — and/or after a stage-1 body extraction fix.

### Taxonomy implications (unchanged)

Occasion / material / place / medium facets emerging. Don’t lock site sections until full corpus + export co-occurrence.

### Ops note

Prod Instagram archive loop remains **off**.

---

## Later stages

### Next

1. ~~Instagram stage-1 via Instaloader~~ — **400** `@efemer_flowers` + **231** `@himmlhaus` in ingest (private API still 429)
2. **KeywordIngest** remaining Instagram `loaded` (~621) with inline QC
3. Re-export + EvaluateKeywords → embeddings / dedup → taxonomy → materialize
4. Optional: empty `bodyHtml` / `thin_text` on efemer.cz

Prod Instagram archive loop stays **off**. No KeywordIngest prod cron yet.

---

## Implement order

1. ~~Stage 1 + KeywordIngest + light admin~~
2. ~~Eval sets + corpus export + facets~~
3. ~~QC prompt bump `kw-v3` + re-eval~~
4. ~~Finish efemer.cz keywording~~ (107 kw-v3; eval cover/roles 100%)
5. Optional stage-1 bodyHtml / thin_text; Instagram keywording later
6. **Embeddings / dedup**
7. Taxonomy pause → assign
8. Materialize
9. Rich admin + rules polish
