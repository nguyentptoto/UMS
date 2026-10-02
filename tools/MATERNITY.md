# Maternity Uniforms

Entry point: UMS > Dong phuc bau (requires `manage_options`, like other HCNS
administration modules). No email is sent by this module.

## Workflow

### Employee Requests

The existing request form and its three reasons remain unchanged. Products
are detected on the server; no maternity checkbox, new reason or request type
is added. Apply the rules to the recipient, not the employee creating the form.

- Reasons 1 and 2: maternity lines use the free pregnancy allowance, not a
  regular periodic advance. Check cumulative 3/3/3/1 limits on submission and
  again at final approval, including quantities awaiting receipt under a
  separate HCNS approval. Employees may request the unused balance later.
- Reason 3, salary or direct payment: a purchase at full current price. This
  also identifies the employee as pregnant, but never consumes the free
  allowance or creates/extends a periodic lock. Maternity advances remain
  disallowed; ordinary products retain the existing reason/payment rules.
- Submitting or partially approving a request does not create an episode,
  change maternity status, or issue stock. On successful final approval, reuse
  the open episode or create one automatically and set `is_maternity`.
- The existing final-approval operation also issues stock. Its local date is
  therefore the free receipt date and determines the blocked cycle. This is
  not a separate physical pickup confirmation. For manual HCNS issues, the
  explicitly entered actual receipt date still applies.
- Final approval commits the request, episode, flag, receipt/purchase audit
  and factory stock movements in one transaction. Mixed regular/maternity
  requests are supported. A failure rolls back the entire operation; repeating
  a completed approval does not issue stock or consume allowance twice.
- Requests link to `maternity_episode_id`. Requests from a closed pregnancy
  cannot silently reopen it or move into a new pregnancy. History records the
  source request ID and warehouse. Existing completed requests are not replayed.

### Manual HCNS Processing

The HCNS module remains available; automatic request processing does not
require a prior manual registration.

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
direct payment. Full positive inventory `base_price` is required and rechecked
on issue. Purchasing staff must maintain that price. Purchases register an
episode if needed, but do not activate or extend the free-issue period lock.
Manual employee issues of maternity goods must use the maternity receipt
workflow or a valid paid request.

## Storage and Checks

`UMS_Maternity::ensure_schema()` creates two InnoDB tables with the configured
WordPress prefix: `uniform_maternity_episodes`, `uniform_maternity_events`.
It also adds nullable `maternity_episode_id` to `uniform_requests`, guarded by
the `ums_maternity_request_schema` option. No new request reason is stored.
Existing inventory, personnel and allowance rows are not migrated or deleted.
Previously recorded maternity flags alone do not invent a historical receipt.

Run `php tools/test-maternity.php` for isolated SQLite-backed tests. They cover
calendar boundaries, caps, episode lifecycle, receipt rollback/idempotency,
warehouse isolation, paid purchases, report and Sheet preview integration,
automatic final approval, cumulative pending requests, mixed-request rollback,
HCNS reservations, and stale requests after a pregnancy ends.
They do not replace a WordPress/MySQL deployment smoke test or browser testing.
The test adapter strips `FOR UPDATE`; real concurrent InnoDB locking still
requires a MySQL integration test. Production final approval uses locking reads
for the episode and ledger so a waiting transaction cannot use a stale snapshot.
