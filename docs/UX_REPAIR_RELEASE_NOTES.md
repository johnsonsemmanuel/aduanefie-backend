# Marketplace flow repair batch — 25 November 2026

Review branch: `codex/marketplace-flow-audit`, based on backend `bb903b45`.

Production group-buy discovery returned HTTP 500. Railway logs identify PostgreSQL error 42883 (`json <> unknown`), and read-only schema inspection confirms `items.bulk_pricing_tiers` is `json`. A shared `Item::bulkEligible()` scope replaces the two invalid comparisons in group listing and module compatibility checks. PostgreSQL evaluates guarded array length; MOQ and active relational pricing tiers retain their eligibility rules. No migration is required.

Admin, supplier, marketer and rider layouts now include one shared foundation stylesheet. It supplies brand/ink/surface tokens, focus visibility, mobile field sizing and touch targets. Marketer tab links expose the current page. Portal login branding uses the configured backend logo with a visible text wordmark, replacing a frontend asset URL verified to return 404.

Verification: full feature suite, 64 tests / 3,144 assertions; new eligibility tests exercise MOQ, populated/empty/null JSON, active/inactive relational tiers and PostgreSQL query generation. The replacement PostgreSQL JSON expression was also executed successfully in a read-only production query. Portal login render tests passed after branding changes.

No production deployment or data changes were performed. The shared stylesheet needs visual acceptance on authenticated dashboard pages before release. Partners/logistics returned Apache headers while API/admin returned Railway headers; compare portal release/database identities before rollout. Do not assume a Railway deployment updates the Apache portals.

The broader frontend audit and remaining commercial launch criteria are in the frontend branch `codex/marketplace-ux-audit`, `docs/UX_FLOW_AUDIT_2026_11_25.md`.

## Approved production rollout

The user explicitly authorised production deployment while the platform remains closed ahead of its January relaunch. Backend `cc3d1f42` was pushed to main and deployed successfully on Railway. The authorised live group-buy listing changed from HTTP 500 to HTTP 200.

The legacy Apache portals were confirmed to use MySQL (`trade_buyer_profiles` absent), rather than the current Railway/PostgreSQL database. To avoid further split-system writes, the host-scoped rules in `deploy/legacy-portal-redirects.htaccess` were installed above the existing rules in both `public_html/.htaccess` and `public_html/public/.htaccess`. Partners/marketer entry and deep role links now redirect to the current operations host; logistics routes redirect to its rider entry. Invalid admin routes and non-GET/HEAD requests on the retired hosts return 404. Existing HTML forms on the current host post to that current host.

Original server files are preserved under `backups/codex-portal-alignment-20261125T211800Z`. No legacy database records or accounts were migrated or deleted. Users must have the appropriate account on the current platform; legacy-only accounts need a deliberate migration/provisioning review before relaunch.

Live checks passed for both portal roots, role login links, a marketer deep link, cross-role /admin denial and rejection of stale login POSTs. Full local suite: 71 tests, 3,159 assertions. Private preview and RFQ transaction flags were not opened.
