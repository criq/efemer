---
name: Multi-stage content loader
overview: "Master plan: efemer.cz keyworded → IG stage-1 done → KeywordIngest IG in progress → MaterializePosts (invitations ephemeral on /udalosti; Wanderlust + diary pilots) → finish IG → export/eval → embeddings → taxonomy → broader posts."
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
  - id: migration-timeframe
    content: "IngestTimeframeSchema — startsAt/endsAt/place/isInvitation on ingest_items; LLM-filled via KeywordAnalyzer timeframe JSON"
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
    content: Admin detail — cover/role/rating/alt + enrichmentVersion + timeframe fields
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
    content: "kw-v3 prompt: cover prefers pre_copy; layout pattern priors; keyword normalize; allow demote; mourning removed"
    status: completed
  - id: finish-keyword-corpus
    content: "efemer.cz KeywordIngest done (107 kw-v3); eval 100%; export 20261028"
    status: completed
  - id: stage1-instagram-dump
    content: "IG stage-1 via Instaloader — 400 efemer_flowers + 231 himmlhaus; ImportInstagramDump idempotent"
    status: completed
  - id: llm-timeframe-events
    content: "LLM timeframe block (isInvitation/startsAt/endsAt/place); drop mourning kind; prefer workshop for invites; Gemini sole parser (no regex heuristics)"
    status: completed
  - id: ig-keyword-corpus
    content: "KeywordIngest remaining Instagram loaded (~551); same kw-v3 + timeframe + inline QC"
    status: in_progress
  - id: stage1-body-thin-text
    content: "Investigate empty bodyHtml / thin_text on ~59 efemer posts (blocks hero_text_gallery); fix scraper or accept image-led"
    status: pending
  - id: kw-normalize-typos
    content: "Typo map florika→floristika + košilela→košile done; keep expanding as leaks appear"
    status: completed
  - id: keyword-qc-loop
    content: KeywordQc inline after each LLM apply (typos/cover/roles/pattern only — no kind/date heuristics); needs_rekeyword re-queued via KeywordIngest
    status: completed
  - id: ig-kind-soft-labels
    content: "himmlhaus mostly diary is OK for house journal; invitations live on @efemer_flowers as workshop+isInvitation. Optional later: soft seriesKey for ritual calendar cycles"
    status: completed
  - id: corpus-reexport-after-ig
    content: "After IG KeywordIngest finishes: ExportCorpus + EvaluateKeywords; spot-check demote/text-slide skips; optional IG invite eval rows"
    status: pending
  - id: event-materialize
    content: "Superseded: invitations are posts (MaterializePosts), not Event entity"
    status: cancelled
  - id: embeddings-dedup
    content: Embeddings for clustering/dedup/series; duplicateOfId (seriesKey nearly unused so far — do not lean on it yet)
    status: pending
  - id: taxonomy-discovery
    content: "Pause after full keyworded corpus; multi-label facets (occasion/material/place/medium + event); do not map kind→section 1:1"
    status: pending
  - id: stage3-materialize
    content: "PostMaterializer: title/perex/files/layoutJson; invitations → udalosti (ephemeral); pilot Wanderlust #427 + diary #621"
    status: completed
  - id: ingest-admin-rich
    content: Manual cover/role pick (locks), reject visibility, layout preview (list facets already shipped)
    status: pending
  - id: rules-wire-archive
    content: Rules polish; keywords as search index; Prefer Instaloader dump when API 429; archive loop stays off
    status: pending
isProject: false
---

# Multi-stage content loader (master)

Single source of truth for the ingest → LLM → taxonomy → public posts pipeline. (Former “Stage 2 LLM analysis” plan is merged here.)

## Guiding principle: keywords first, taxonomy later

Do **not** lock categories (`ritualy` / `himmelhaus` / `umeni`) during import.

1. **Complete load** of all sources into ingest
2. **Keyword + vision + timeframe** — keywords, photo ratings, cover, roles, demote, alt, crop, palette, series, layoutHints, **and** LLM `timeframe` (`isInvitation` / `startsAt` / `endsAt` / `place`)
3. **Inspect the corpus** — exports, facets, clusters, embeddings
4. **Decide final category shape** — may be multi-label; may add emerging categories
5. **Assign categories** to ingest items
6. **Pilot materialize** events (from invitations) + posts, then full materialize

Today’s three site sections remain provisional until taxonomy is settled.

**LLM over regex:** categorization and date/event parsing are Gemini’s job (text + poster OCR). PHP/`KeywordQc` only enforce structural invariants (enums, cover/role locks, typo cleanup). No invitation/kind/date heuristics in PHP.

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
  stage2 --> KW[keywords cover roles timeframe]
  KW --> corpus[Export facets embeddings]
  corpus --> review[Taxonomy discovery]
  review --> cats[Assign categories]
  KW --> events[Event materialize invites]
  cats --> pilot[Pilot post materialize]
  pilot --> stage3[Full materialize]
  stage3 --> posts[posts layoutJson]
  events --> ev[events homepage hero]
```

| Layer | Tables | Role |
|---|---|---|
| Imported | `ingest_items`, `ingest_item_files` | Source mirror + LLM enrichments |
| Internal | `posts`, `post_files`, `events` | After taxonomy + stage 3 / event materialize |

**Interlink:** `ingest_items.postId` ↔ `posts.ingestItemId`; `ingest_items.eventId` ↔ `events` (`ON DELETE SET NULL`).

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
- Stage-1 stores **IG upload time** as `postedAt` only; content dates come from Stage 2 LLM

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

### Stage 2 enrichment (`IngestEnrichmentSchema`)

**`ingest_items` add:** `coverFileId`, `coverRationale`, `coverLocked`, `seriesKey`, `palette`, `layoutHints`, `enrichmentVersion`, `enrichedAt`.

**`ingest_item_files` add:** `rating`, `isCover`, `role`, `roleRationale`, `roleLocked`, `altText`, `orientation`, `focalCrop`, `demote`.

### Timeframe / events (`IngestTimeframeSchema`)

**`ingest_items` add:** `startsAt`, `endsAt`, `place`, `isInvitation`.

Filled only by KeywordAnalyzer `timeframe` JSON (Gemini sees caption + poster images + `postedAt` as year reference). Used later to materialize `events` and drive homepage hero.

On materialize: copy onto `posts` / `post_files`; write `layoutJson`; respect locks. Invitations → `events`.

---

## Stage 2 — KeywordIngest

### Goal

`loaded` → `keyworded` with searchable keywords + layout-ready photo metadata + **event timeframe**. **No taxonomy / post materialize yet.**

### Soft `kind` (no `mourning`)

`diary | wedding | gift | portrait | workshop | installation | bouquet | product | other`

- **workshop** = public gathering / slavnost / pouť / walk / invite (“zveme”, “sraz”, tickets, …) — even cemetery / All Saints walks
- **gift** = named tribute / farewell / birthday bouquet (e.g. *Poslední květy pro Helenu…*)
- **diary** = personal journal / house chronicle (`@himmlhaus` dated entries are fine as diary)

### Architecture

```mermaid
flowchart LR
  job[KeywordIngest] --> pick[Pick items]
  pick --> gemini[Gemini Flash multimodal]
  gemini --> apply[Write enrichment + timeframe]
  apply --> qc[KeywordQc structural]
  qc --> admin[Admin detail]
```

- **Model:** `gemini-3.8-flash` via `generativelanguage.googleapis.com` REST
- **Secret:** `Secrets::GEMINI_API_KEY` (`gemini-api-key` in GSM); local fallback `.secret/gemini.api`
- **Per item:** one multimodal call — title + body + up to **12** images + **Posted at** reference
- **Output adds** `timeframe: { isInvitation, startsAt, endsAt, place }`

### Job CLI

```bash
./bin/job jobs:run App.Classes.Jobs.Ingest.KeywordIngest -f
./bin/job jobs:run App.Classes.Jobs.Ingest.KeywordIngest --args='{"limit":5,"rekeyword":true}' -f
./bin/job jobs:run App.Classes.Jobs.Ingest.KeywordIngest --args='{"id":427,"rekeyword":true}' -f
```

---

## LLM quality control

### Round 1–2 (kw-v3, efemer) — done

Eval cover/roles/patterns **100%**. Demote later appeared on IG. Typo map: `florika`→`floristika`, `košilela`→`košile`.

### Round 3 (IG mid-run, morning 2026-10-29)

~64 IG keyworded; demote working on text slides; `@himmlhaus` diary-heavy; invites mis-tagged as mourning/diary until timeframe + kind prompt fix.

### Round 4 (timeframe + invite re-enrich, afternoon 2026-10-29)

| Metric | Result |
|---|---|
| Status | **107** efemer + **80** IG `keyworded`; **551** IG still `loaded` |
| Soft kinds | diary 73, wedding 37, workshop 31, gift 15, portrait 11, installation 10, bouquet 10 — **no mourning** |
| Invitations | **28** `isInvitation=1` (almost all `@efemer_flowers` workshops) with LLM `startsAt` / often `endsAt` + `place` |
| Timeframe JSON | **57** items with Gemini `timeframe` block (re-enriched set); older efemer keyworded still lack it until optional rekeyword |
| `@himmlhaus` | 46 keyworded — diary house journal with content dates/ranges + places (Himmlhaus, Amtmann, Black Gables, …); **0** invitations (expected) |
| Cover / demote | still solid; demote on IG carousel text slides |

**Examples that work:** Wanderlust `#427` → workshop + invite + `2026-11-01 09:45` + Hraničář/Čermná; Kupalo `#1044`; Patience Gray / Marcel / Po Svaté Sofii series; `@himmlhaus` Weinlese `#621` → diary + `2025-09-19`–`2025-10-10`.

**Taxonomy / product implications**

- Soft `kind` ≠ site section. `@himmlhaus` diary stays journal until taxonomy.
- **`isInvitation` + timeframe`** is the bridge to public **`events`** and homepage hero — next concrete product step after finishing IG KeywordIngest.
- Remaining ~551 IG `loaded` should get timeframe in the same KeywordIngest pass (no second job).

### Ops note

Prod Instagram archive loop remains **off**. Prefer Instaloader dump + Import when private API 429. Stage-1 re-runs are upsert/idempotent (hash skip). No KeywordIngest prod cron yet.

---

## Later stages

### Next

1. **Finish KeywordIngest** on remaining IG `loaded` (~551) — includes timeframe for free
2. **Post materialize** (`MaterializePosts`) — universal; invitations as ephemeral `udalosti` posts; Wanderlust pilot
3. Optional: rekeyword older efemer.cz items that lack `timeframe` JSON
4. ExportCorpus + EvaluateKeywords; optional IG invite/cover eval rows
5. Embeddings / dedup → taxonomy pause → broader post materialize
6. Optional: empty `bodyHtml` / `thin_text` on efemer.cz (~59)

---

## Implement order

1. ~~Stage 1 + KeywordIngest + light admin~~
2. ~~Eval sets + corpus export + facets~~
3. ~~QC prompt bump `kw-v3` + re-eval~~
4. ~~Finish efemer.cz keywording~~
5. ~~IG stage-1 Instaloader~~
6. ~~LLM timeframe + drop mourning + invite re-enrich~~
7. **Finish IG KeywordIngest** (in progress)
8. **Post materialize** (unified posts; invitations ephemeral on `/udalosti`)
9. Re-export / eval → embeddings / dedup
10. Taxonomy pause → assign → broader materialize
11. Rich admin + rules polish; drop legacy `events` UI; optional efemer bodyHtml fix
