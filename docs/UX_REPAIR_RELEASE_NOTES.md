# Marketplace flow repair batch — 25 November 2026

Review branch: `codex/marketplace-flow-audit`, based on backend `bb903b45`.

Production group-buy discovery returned HTTP 500. Railway logs identify PostgreSQL error 42883 (`json <> unknown`), and read-only schema inspection confirms `items.bulk_pricing_tiers` is `json`. A shared `Item::bulkEligible()` scope replaces the two invalid comparisons in group listing and module compatibility checks. PostgreSQL evaluates guarded array length; MOQ and active relational pricing tiers retain their eligibility rules. No migration is required.

Admin, supplier, marketer and rider layouts now include one shared foundation stylesheet. It supplies brand/ink/surface tokens, focus visibility, mobile field sizing and touch targets. Marketer tab links expose the current page. Portal login branding uses the configured backend logo with a visible text wordmark, replacing a frontend asset URL verified to return 404.

Verification: full feature suite, 64 tests / 3,144 assertions; new eligibility tests exercise MOQ, populated/empty/null JSON, active/inactive relational tiers and PostgreSQL query generation. The replacement PostgreSQL JSON expression was also executed successfully in a read-only production query. Portal login render tests passed after branding changes.

No production deployment or data changes were performed. The shared stylesheet needs visual acceptance on authenticated dashboard pages before release. Partners/logistics returned Apache headers while API/admin returned Railway headers; compare portal release/database identities before rollout. Do not assume a Railway deployment updates the Apache portals.

The broader frontend audit and remaining commercial launch criteria are in the frontend branch `codex/marketplace-ux-audit`, `docs/UX_FLOW_AUDIT_2026_11_25.md`.
