# Maternity Uniforms

Entry point: UMS > Dong phuc bau (requires `manage_options`, like other HCNS
administration modules). No email is sent by this module.

## Workflow

1. Register an active female employee from the TVN organization chart and the
   date HCNS recorded the pregnancy. Only one open episode per employee.
2. Approve actual products/sizes and quantities, with independent caps of
   3 dresses, 3 trousers, 3 shirts, and 1 jacket. Approval alone does not deduct
   stock or block a periodic cycle.
3. Confirm receipt of the approved quantities and the actual receipt date.
   This deducts stock from the episode's factory and creates movement history
   in one transaction. Insufficient stock cancels the whole operation. Free
   issues may be split across multiple receipts up to the cumulative cap for
   each group during the episode. One dress received leaves two free dresses;
   it does not reduce the trousers, shirts or jacket balance. Each receipt
   consumes its approval, and each supplement requires a new approval.
   Adjust approval before confirming if quantities differ.
4. Record the date the employee returns to work. This clears `is_maternity`
   without deleting the receipt, audit log, or blocked period. A later pregnancy
   requires a new episode, dated after the previous return date.

## Period Rules

- First free receipt through May 1 inclusive: block T4 of the same year.
- May 2 through August 30 inclusive: block T9 of the same year.
- August 31 onward: block T4 of the following year.
- Further receipts retain the first receipt date and its blocked cycle; they
  never activate a second cycle lock. Receipt events are the cumulative ledger,
  including those recorded by the previous single-issue implementation.
- The lock covers all periodic products, including winter jackets/coats.
- Only that next cycle is locked; no additional cycles are inferred from a
  still-open maternity flag. Normal allowance rules apply outside the lock.
- Locks follow employee code even after a factory transfer or end of maternity.
- Excel and Google Sheet calculations share this logic. Stale previews cannot
  finalize positive quantities for a newly blocked employee. PR totals exclude
  blocked employees from previously finalized periodic batches as well.

## Product and Payment Data

Maternity variants are identified from the existing inventory product name
using accent-insensitive matching: `vay bau`, `quan bau`, `ao bau`,
`ao khoac bau`. Sizes remain individual inventory items. Ensure maternity
products have explicit maternity names. They are excluded from regular
periodic allowance products.

Extra purchases use the existing request/approval flow: reason 3, salary or
direct payment. Advance payment and free reasons are rejected for maternity
products. Full positive inventory `base_price` is required and rechecked on
issue. Purchasing staff must maintain that price. Purchases do not activate or
extend the free-issue period lock. Manual employee issues of maternity goods
must use the maternity receipt workflow or a valid paid request.

## Storage and Checks

`UMS_Maternity::ensure_schema()` creates two InnoDB tables with the configured
WordPress prefix: `uniform_maternity_episodes`, `uniform_maternity_events`.
Existing inventory, personnel and allowance rows are not migrated or deleted.
Previously recorded maternity flags alone do not invent a historical receipt.

Run `php tools/test-maternity.php` for isolated SQLite-backed tests. They cover
calendar boundaries, caps, episode lifecycle, receipt rollback/idempotency,
warehouse isolation, paid purchases, report and Sheet preview integration.
They do not replace a WordPress/MySQL deployment smoke test or browser testing.
