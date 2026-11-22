-- READ-ONLY checks for the per-payment mode backfill on invoices.
-- Each query should return 0 rows (or count 0) if the backfill is complete.

-- 1. Summary counts
SELECT COUNT(*) AS diagnostic_invoices,
       SUM(paid_amount > 0)                                       AS has_initial_payment,
       SUM(paid_amount > 0 AND payment_mode_initial IS NULL)      AS missing_initial_mode,
       SUM(paid_amount_1 IS NOT NULL)                             AS has_phase1,
       SUM(paid_amount_1 IS NOT NULL AND payment_mode_1 IS NULL)  AS missing_phase1_mode,
       SUM(paid_amount_2 IS NOT NULL)                             AS has_phase2,
       SUM(paid_amount_2 IS NOT NULL AND payment_mode_2 IS NULL)  AS missing_phase2_mode
FROM invoices
WHERE invoice_type = 'DIAGNOSTIC';

-- 2. Invoices the backfill missed (paid amount present, mode empty)
SELECT id, invoice_no, paid_amount, paid_amount_1, paid_amount_2,
       payment_mode, payment_mode_initial, payment_mode_1, payment_mode_2
FROM invoices
WHERE invoice_type = 'DIAGNOSTIC'
  AND ((paid_amount > 0 AND payment_mode_initial IS NULL)
    OR (paid_amount_1 IS NOT NULL AND payment_mode_1 IS NULL)
    OR (paid_amount_2 IS NOT NULL AND payment_mode_2 IS NULL))
LIMIT 200;

-- 3. Backfilled mode disagrees with its daily_transactions row
SELECT i.invoice_no, 'initial' AS phase, i.payment_mode_initial AS invoice_mode, dt.payment_mode AS txn_mode
FROM invoices i
JOIN daily_transactions dt ON dt.invoice_reference = i.invoice_no
 AND dt.transaction_type = 'RECEIVED' AND dt.status != 'CANCELLED'
 AND dt.remarks = 'Diagnostic Invoice'
WHERE i.invoice_type = 'DIAGNOSTIC' AND i.payment_mode_initial <> dt.payment_mode
UNION ALL
SELECT i.invoice_no, 'phase1', i.payment_mode_1, dt.payment_mode
FROM invoices i
JOIN daily_transactions dt ON dt.invoice_reference = i.invoice_no
 AND dt.transaction_type = 'RECEIVED' AND dt.status != 'CANCELLED'
 AND dt.remarks LIKE 'Diagnostic Due Payment - Phase 1%'
WHERE i.invoice_type = 'DIAGNOSTIC' AND i.payment_mode_1 <> dt.payment_mode
UNION ALL
SELECT i.invoice_no, 'phase2', i.payment_mode_2, dt.payment_mode
FROM invoices i
JOIN daily_transactions dt ON dt.invoice_reference = i.invoice_no
 AND dt.transaction_type = 'RECEIVED' AND dt.status != 'CANCELLED'
 AND dt.remarks LIKE 'Diagnostic Due Payment - Phase 2%'
WHERE i.invoice_type = 'DIAGNOSTIC' AND i.payment_mode_2 <> dt.payment_mode;

-- 4. More than one active matching transaction row for one phase (ambiguous backfill)
SELECT invoice_reference, remarks, COUNT(*) AS n
FROM daily_transactions
WHERE transaction_type = 'RECEIVED' AND status != 'CANCELLED'
  AND (remarks = 'Diagnostic Invoice' OR remarks LIKE 'Diagnostic Due Payment - Phase %')
GROUP BY invoice_reference, remarks
HAVING COUNT(*) > 1
LIMIT 200;

-- 5. Mixed-mode invoices (the ones this whole change is for)
SELECT invoice_no, payment_mode_initial, payment_mode_1, payment_mode_2, payment_mode AS latest_mode
FROM invoices
WHERE invoice_type = 'DIAGNOSTIC'
  AND ((payment_mode_1 IS NOT NULL AND payment_mode_1 <> payment_mode_initial)
    OR (payment_mode_2 IS NOT NULL AND payment_mode_2 <> payment_mode_1))
LIMIT 50;
