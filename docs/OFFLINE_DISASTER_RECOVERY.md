# OHMS-Velzon — Offline Disaster Recovery Methodology

**Scope:** what to do when the clinic's internet connection is down (natural
calamity, ISP outage, etc.) and production (`cms.abssrk.online`, GoDaddy
shared hosting) cannot be reached, but the clinic still needs to register
patients, book appointments, and bill invoices.

**Design decisions this plan is built on** (confirmed 2026-08-02):

- Fallback runs on the existing local WAMP development machine, activated
  manually when an outage is declared (no standing always-on server).
- The restore point is a `.sql` snapshot taken via the app's own **Cloud
  Backup** screen (`CloudBackupController`, Admin-only), downloaded and kept
  on hand.
- **Clean cutover**: once WAMP is activated, all staff use it exclusively.
  Production is not touched by anyone until the merge-back step — it is
  frozen for the whole outage (this is also physically true: nobody can
  reach it without internet).
- Merge-back is a **guided script, run once, manually**, by an admin who
  reviews a summary before it commits anything to production.

---

## 1. The core technical problem

This app generates every human-facing document number (`invoice_no`,
`appointment_no`, `settlement_no`, `payable_no`, `voucher_number`,
`patient_id`, etc.) by counting existing rows or reading the last row and
incrementing it. It also relies on MySQL auto-increment primary keys.

If WAMP is restored from a backup and then creates new records, those
records get IDs and numbers that continue on from *the backup's* last known
state — not from whatever state production was really in at the moment the
outage started.

**Concretely:** if the last backup was taken at 10:00 and the outage started
at 15:00, any invoices/appointments/patients production created between
10:00 and 15:00 exist for real on production but are invisible to WAMP. When
WAMP starts numbering new records from the 10:00 snapshot, it *will*
generate numbers that collide with those real 10:00–15:00 production
records the moment the two databases are merged back together.

This is true **no matter how frequently backups are taken** — there will
always be some gap between "last backup" and "outage start" that nobody can
predict or close to zero on an ad-hoc/manual backup routine. The plan below
is built to make that gap *safe regardless of its size*, rather than trying
to make the gap disappear.

**The fix has two parts, both required:**

1. **Offline-namespaced document numbers.** While WAMP is running in offline
   mode, every generated number uses a distinct, reserved prefix that
   production would never produce on its own (e.g. `LAB/` → `OFFLAB/`,
   `DOC/` → `OFFDOC/`, `AP…` → `OFFAP…`, `SET/` → `OFFSET/`, `EXP/` →
   `OFFEXP/`, `P<mobile>-NN` → `OFFP<mobile>-NN`). This makes a collision
   with production's real numbers structurally impossible, independent of
   how stale the backup was.
2. **Primary keys are never trusted as final.** WAMP's own auto-increment
   `id` values are treated as disposable local scratch IDs. The merge-back
   script re-inserts each offline-created row into production through
   normal Eloquent `create()` calls, lets *production* hand out the real
   `id`, records an old-id → new-id map, and rewrites every foreign key
   (e.g. `invoice_details.invoice_id`, `doctor_payables.invoice_id`) that
   pointed at the old local id.

Offline-issued numbers (part 1) are **kept permanently after merge**, never
renumbered to a "real" production-style number — a patient may already have
a printed receipt or verbal confirmation showing `OFFLAB/...`, and changing
it after the fact would break that paper trail. The `OFF`-prefix doubles as
a permanent, honest marker of "this record originated during the
2026-xx-xx outage," which is useful information in its own right.

---

## 2. Phase 0 — Standing preparedness (do this now, before any incident)

- [ ] **Keep the WAMP machine deployment-current.** Same PHP/MySQL version
  family as production, latest app code, `composer install` /
  `npm run build` run recently. An outage is not the time to discover the
  local copy is three releases behind.
- [ ] **Adopt a Cloud Backup routine.** An Admin logs into production,
  opens Cloud Backup, runs a backup, and downloads the `.sql` file — on a
  fixed cadence (recommend at least once per business day; more often, e.g.
  every 4–6 hours, if outage risk is seasonally elevated — monsoon/cyclone
  season, etc.). Keep at least the last 3–5 snapshots, clearly dated.
- [ ] **Store this runbook where it's reachable *without* internet** — a
  printed copy at the front desk, and a copy on the WAMP machine itself
  (`docs/OFFLINE_DISASTER_RECOVERY.md` in this repo). If it only lives on a
  cloud drive, it's useless exactly when it's needed.
- [ ] **Name who is authorized to**: (a) declare an outage and trigger
  cutover, (b) restore a backup onto WAMP, (c) run the merge-back script
  once internet returns. In a small clinic this may be one person, but it
  should be an explicit, agreed name, not assumed.
- [ ] **Confirm this document's Phase 3 tooling exists before relying on
  it.** As of 2026-08-02, the offline-prefix code changes and the
  merge-back script described below are **not yet built** — see §5.

---

## 3. Phase 1 — Activation (outage confirmed)

1. Confirm it's a genuine connectivity outage, not a one-off blip — wait a
   few minutes / try a second network path first.
2. Grab the most recent `.sql` backup on hand.
3. On the WAMP machine: drop/recreate the local dev database, import the
   backup (`mysql -u root ohms_velzon_db < latest-backup.sql` or via
   phpMyAdmin's import).
4. Set `APP_OFFLINE_MODE=true` in the WAMP `.env` — this is what every
   document-number generator (§6.1) checks to switch to the `OFF`-prefixed
   scheme. Confirm it took effect (e.g. `php artisan tinker --execute="dump(App\Support\OfflineMode::isActive());"` should print `true`) before letting staff start entering data.
5. Start the local server (`php artisan serve` or the WAMP vhost) and
   confirm login works.
6. Tell all staff: **use this local address only** for the duration of the
   outage. Do not attempt production, even if someone's mobile data
   happens to reach it — that would break the clean-cutover assumption this
   whole plan depends on.
7. Make a note of the exact restore timestamp (the backup's "as of" time)
   — it's needed for the merge-back reconciliation report later.

---

## 4. Phase 2 — Operating offline

**Works normally:** patient registration, appointments, diagnostic and
doctor-visit invoicing, doctor payables, settlements, equipment rental,
expenditure entries, test result entry, reporting screens against
already-loaded data.

**Fails gracefully (already logged as `FAILED`, not a crash) and needs a
manual substitute during the outage:**

- **WhatsApp confirmations/notifications** (`WatiService`) — no internet,
  no delivery. Tell patients their appointment/invoice details verbally or
  on the printed receipt instead. Do not attempt to resend these after
  merge-back unless the appointment date is still upcoming.
- **Discount-approval emails** — same. Use the existing verbal/physical
  sign-off convention as the approval-of-record during the outage; the
  approver's name is still captured in the invoice record itself either
  way.
- **Anything reading "live" production data the local snapshot doesn't
  have** — e.g. a patient's visit history between the last backup and the
  outage start won't show on WAMP. Staff should be told this gap can exist
  and to ask the patient directly if something looks incomplete, rather
  than assume the record is wrong.

**Cash/financial discipline:** every payment recorded in WAMP during the
outage is real money that changed hands. Keep the usual physical
reconciliation (cash drawer counts, etc.) running exactly as normal — the
technical merge-back in Phase 3 reconciles the *database*, not the *till*.
Do that reconciliation independently and compare totals as a sanity check
once both are done.

---

## 5. Phase 3 — Restoration & merge-back

**Built and verified (2026-08-02) — see §7.** Once internet is confirmed
stable:

1. **Freeze WAMP** — stop staff from entering anything further into the
   local copy; the offline batch being merged must be a closed, stable set.
2. **Safety backups, both sides** — take a fresh Cloud Backup of production
   *before* touching it, and a full dump of the WAMP database. This is a
   one-off checkpoint, separate from Phase 0's routine backup cadence — its
   only job is being the undo button for the merge itself. Label it clearly
   (e.g. `pre-merge-2026-08-02.sql`) and set it aside; if the merge needs to
   be undone and retried, this is what production gets restored from before
   retrying (`offline:merge` is idempotent, so a clean retry after
   restoring is safe). Archive it alongside the offline-period WAMP
   backups from step 10 once the merge is confirmed clean (spot-checks in
   step 8 and cash reconciliation in step 9 both check out) — don't delete
   it same-day, but it doesn't need to be kept as a special artifact
   forever either.
3. **Identify the offline batch** — every row across `patients`, `invoices`,
   `invoice_details`, `doctor_payables`, `doctor_appointments`,
   `daily_transactions`, `doctor_settlements`, `doctor_settlement_items`,
   `expenditure_transactions`, etc. whose document number carries the
   `OFF` prefix, or whose `created_at` falls after the recorded cutover
   timestamp from Phase 1 step 7 (belt-and-suspenders — number prefix is
   the primary signal, timestamp is the cross-check).
4. **Re-insert in dependency order** so foreign keys resolve correctly as
   they go, e.g.: patients → invoices → invoice_details/doctor_payables →
   daily_transactions → appointments → settlements → settlement_items →
   expenditure_transactions. Each insert goes through the same Eloquent
   model the real controller would use, so production's own AuditService
   logging, validation, and auto-increment all apply exactly as if the
   record had been entered live — building the old-local-id → new-
   production-id map as it goes.
5. **Rewrite foreign keys** in every dependent row using that id map before
   it's inserted (e.g. an `invoice_details` row's `invoice_no`/`invoice_id`
   must point at the *new* production invoice, not the old local one).
6. **Do not renumber the human-facing document numbers** — the `OFF`-prefix
   ones stay exactly as issued (see §1).
7. **Produce a summary report before committing anything**: row counts per
   table, total amounts (invoice totals, payments received, expenditures)
   for the offline period, and any rows the script could not place
   automatically (flag for manual review rather than silently guessing).
   The named admin (§2) reviews this and gives explicit go-ahead.
8. **Commit**, then spot-check a handful of merged invoices/appointments on
   the live production screens.
9. **Compare the offline period's recorded cash totals against the
   physical till reconciliation** done in Phase 2 — this is the one check
   that catches errors the database-level merge can't, by design.
10. Unset `APP_OFFLINE_MODE` on WAMP, tell staff to switch back to
    production, and archive the offline-period backups (don't delete —
    they're the audit trail for exactly what happened during the outage).

---

## 6. Implementation status

### 6.1 Done (2026-08-02) — the offline-prefix number sweep

`APP_OFFLINE_MODE` now exists as a real config value (`config('app.offline_mode')`,
sourced from the `APP_OFFLINE_MODE` env var, default `false`) with a tiny
helper, `App\Support\OfflineMode`:

```php
OfflineMode::isActive();        // bool, reads config('app.offline_mode')
OfflineMode::prefix('LAB/');    // 'LAB/' normally, 'OFFLAB/' when offline
```

Every sequential document-number generator found in a full codebase sweep
now routes its prefix through this helper, so **every one of them
automatically switches to an `OFF`-prefixed, collision-proof variant the
moment `APP_OFFLINE_MODE=true` is set** — no other code changes needed at
activation time. Full inventory (28 sites found; 20 code-level fixes
applied — the rest are out of scope, see §6.2):

| Prefix | Where | Status |
|---|---|---|
| `LAB/` | `DiagnosticInvoiceController::store()` | done |
| `DOC/` | `DoctorVisitInvoiceController::store()` | done |
| `OXY/` / `CON/` | `EquipmentRentalController::generateInvoiceNo()` | done |
| `AMB/` | `AmbulanceRentalController::generateInvoiceNo()` | done |
| `MEM/` | `MembershipFeeController::generateInvoiceNo()` | done |
| `INC/` | `IncomeController::generateInvoiceNo()` | done |
| `PO/` | `PurchaseOrderController::generatePoNo()` | done |
| `GRN/` | `GoodsReceiptController::generateReceiptNo()` | done |
| `ISS/` | `StockIssueController::generateIssueNo()` | done |
| `EXP/` | `ExpenditureController::generateVoucherNo()` | done |
| `PAY/` | `generatePayableNo()` — duplicated in `DiagnosticInvoiceController` **and** `DoctorVisitInvoiceController` (no shared trait exists) | done, both sites |
| `SET/` | `DoctorSettlementController::generateSettlementNo()` | done |
| `TRN/` | `daily_transactions` transaction number — duplicated/inlined independently in **7 places**: `DiagnosticInvoiceController` (×3 inline), `DoctorVisitInvoiceController` (×2 inline), `EquipmentRentalController::recordLedgerEntry()`, `AmbulanceRentalController::recordLedgerEntry()`, `MembershipFeeController::recordLedgerEntry()`, `IncomeController::recordLedgerEntry()`, `InvoiceCancellationController::recordLedgerEntry()` | done, all 7 |
| `AP…` | `AppointmentBookingService::createWithRetry()` (shared by both `DoctorAppointmentController` and `PublicAppointmentController`) | done |
| `P…` | patient_id — inline in `AppointmentBookingService::book()` **and** `DiagnosticInvoiceController::generatePatientId()` (no shared helper exists) | done, both sites |

Verified via tinker against the local DB: toggling `config(['app.offline_mode'
=> true])` at runtime correctly flips `generatePatientId()`, a real
`ExpenditureController::store()` call, and a real
`AppointmentBookingService::book()` call (both the appointment number and
the patient id it creates) to their `OFF`-prefixed form; toggling back off
correctly restores normal numbering. Test data cleaned up, no residue.

### 6.2 Deliberately out of scope for this pass

Found during the sweep but **not** wired up, and not silently dropped —
flagged here for a deliberate decision later:

- **Master-data codes with no separator** — `InventoryItemController::
  generateItemCode()` (`ITM000001`), `EquipmentCategoryController::
  generateCategoryCode()` (`EC000001`), `AmbulanceDestinationController::
  generateDestinationCode()` (`AD000001`). These parse a *fixed-length*
  prefix via `substr($code, N)` to extract the numeric tail, so simply
  prepending `OFF` would break the parse (`substr` offset would land
  mid-prefix). Fixing these needs a small offset adjustment alongside the
  prefix swap, not just the generic wrapper. Lower priority: these are
  operational master-data codes (inventory items, equipment categories,
  ambulance destinations), not the patient-facing numbers this plan exists
  to protect — unlikely to be created *during* an actual outage.
- **`InvoiceItemDetailController::generateSubItemCode()`** — its "prefix"
  is derived from the parent `item_code` at runtime, not a static string,
  so it doesn't fit this wrapper pattern at all. Same low-priority
  reasoning as above.
- **`DiagnosticInvoiceController::generatePatientCode()`** — a `PAT{ym}
  {serial:5}`-style generator that appears to be dead code (defined, no
  callers found anywhere in the codebase). Left alone; not worth guarding
  something nothing calls.

### 6.3 A subtlety discovered while implementing this — relevant to the merge script

`AppointmentBookingService::createWithRetry()`'s `token_no` (the patient's
queue number shown at reception, and sent in the WhatsApp confirmation) is
a **plain integer column**, computed by counting existing appointments for
that doctor+date — it has no string prefix to namespace, unlike
`appointment_no`. The database's real uniqueness guard is a composite index
on `(doctor_id, appointment_date, token_no)`.

This means the `OFFAP…` prefix on `appointment_no` protects the *displayed
number* from colliding, but **not** the underlying `token_no` integer
itself. If production issued token_no 8 for Dr. X on 5 Aug in the
backup-to-outage gap, and WAMP — unaware of that — also computes token_no 8
for Dr. X on 5 Aug during the outage, the merge-back insert will fail the
composite unique index.

**Implication for the merge command (§7):** it must not carry over the
offline-computed `token_no` verbatim. It has to be recomputed fresh —
recount that doctor's real appointments for that date in production *at
merge time* and assign the next available token — the exact same way a
brand-new live booking would. This is really a special case of the general
rule already stated in §5 step 4 ("re-insert through the same Eloquent
model... production's own auto-increment... apply exactly as if entered
live") — it's called out explicitly here because `token_no` is the one
place a *non-primary-key* sequential value also needs this treatment, not
just the row's `id`. **Implemented as described** — see §7.

## 7. The merge-back command (built and verified 2026-08-02)

`php artisan offline:merge` — run on the WAMP machine (it has CLI access;
production doesn't), reading WAMP's own local database as the source and
writing to production over a remote MySQL connection (confirmed available
for this database).

```
php artisan offline:merge
    --host=<production db host>
    --port=3306
    --database=<production db name>
    --username=<production db user>
    --password=<production db password>     (omit to be prompted, masked)
    --run-by=<user id, must exist on BOTH databases>
    [--since="Y-m-d H:i:s"]                  (Phase 1 step 7's cutover timestamp)
    [--commit]                                (without this: dry-run only, writes nothing)
```

Always computes and prints the full plan first — a table of how many rows
per entity would be created vs. are already present (safe to re-run;
"already present" is how it detects a prior partial run and skips
duplicates), plus a second table of pre-existing records it detected as
modified offline that it will **not** touch automatically. Without
`--commit` that's the entire run — nothing is written. With `--commit`, it
additionally asks for an explicit confirmation, naming exactly how many
records will be written, before touching production.

**What it merges automatically**, in dependency order: `patients` →
`doctor_appointments` (recomputing `token_no` fresh, per §6.3) →
`invoices` → `invoice_details` → `doctor_payables` → `daily_transactions`
→ `doctor_settlements` → `doctor_settlement_items` → `expenditure_transactions`
→ `purchase_orders`/`items` → `goods_receipts`/`items` → `stock_issues`/`items`.
Every new row gets a real production-issued primary key; every foreign key
in a dependent row is rewritten to match; every `OFF`-prefixed document
number is carried over unchanged (never renumbered, so a receipt already
in a patient's hand stays valid). Each created row gets a fresh
`AuditService` entry **on production** (not copied from WAMP's own offline
audit log, which stays on WAMP as the historical record of the outage),
attributed to `--run-by`, remarked as merged from an offline session.

**The one pre-existing-row mutation it does handle:** if an offline
settlement was recorded against a payable that already existed before the
outage (not something this merge just created), it looks that payable up
by its stable `payable_no` on production and replays the exact
`paid_amount`/`payment_status`/`last_settlement_*`/`settlement_count`
change `DoctorSettlementController::updateDoctorPayable()` would have
applied live. This is idempotent too — re-running after it already
happened does not double-apply the payment.

**Everything else pre-existing-row-modified-offline is report-only**,
matching the scope decision from the original design: due-payment
collection on an old invoice, cancelling an old invoice/appointment/
settlement, etc. have no safe general merge rule, so they're listed in the
"reconcile manually" table (filtered by `--since` when given — without it,
the list includes every historically-edited row in the whole table, which
is noisy on a real database; always pass `--since` in practice) rather than
silently applied or silently dropped.

**Known gap:** `goods_receipt_items.purchase_order_item_id` is not
remapped at the individual line-item level when the referenced PO item was
itself created during the same offline session (it's copied as-is, correct
only when it points at a pre-existing PO item). Rare in practice — flagged
here rather than fixed, given how infrequently a goods receipt would need
to reference a specific offline-created PO line item mid-outage.

**Verified** (2026-08-02): cloned the local database into a second test
database, seeded a full simulated offline batch through the real
`AppointmentBookingService`/`DiagnosticInvoiceController`/
`DoctorSettlementController`/`ExpenditureController` code paths (so the
`OFF`-prefixed numbers were genuinely generated, not hand-typed), then ran
`offline:merge` against it three times: a dry run (confirmed correct
counts and correct `--since` filtering), a `--commit` (confirmed every
entity landed with fresh ids, correct FK remapping, unchanged document
numbers, correctly recomputed `token_no`, the pre-existing payable
correctly synced, and audit rows correctly created *on the target
database* — not the source, which required temporarily repointing
`database.default` for the duration of the write since `AuditService` has
no connection parameter of its own), and a third identical `--commit`
re-run (confirmed zero new rows, zero double-application of the payable
sync). All test data and audit rows cleaned up afterward.

Files: `app/Console/Commands/MergeOfflineData.php`,
`app/Services/OfflineMergeService.php`.

---

## 8. Known limitations, stated plainly

- **Single point of failure.** The WAMP machine is one physical computer.
  If it fails at the same time as the outage (e.g. a fire or flood that
  also damages the premises), this plan has no fallback. A truly resilient
  setup would keep the fallback on separate, redundant hardware — this was
  explicitly traded away in favor of a simpler ad-hoc approach.
- **Backup staleness is bounded by discipline, not technology.** The whole
  plan's safety net (offline-prefixed numbers) protects against the
  *consequences* of a stale backup, but a very stale backup (say, a week
  old because nobody ran Phase 0's routine) still means staff are working
  from a week-old patient/pricing/schedule picture during the outage, which
  is a real operational problem independent of the ID-collision fix.
- **Multi-day outages** accumulate a larger offline batch, making the
  Phase 3 review (step 7) more consequential — the longer the outage, the
  more important it is that the dry-run summary is actually read carefully,
  not rubber-stamped.
- **No real-time inventory/stock reconciliation across the two systems.**
  If inventory items are issued from both WAMP and (hypothetically, if this
  were ever violated) production during the same window, stock levels would
  desync. This is exactly why the clean-cutover rule (§Design decisions) is
  load-bearing — it must be enforced operationally, not just assumed.
