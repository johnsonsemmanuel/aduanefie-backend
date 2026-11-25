# Aduanefie — Phase 3 Commercial Readiness Tracker

Last updated: 2026-11-25T15:20Z
Repositories:
- **Frontend**: `https://github.com/johnsonsemmanuel/aduanefie.git`
  - **Deployed `origin/main`**: `1d4a83e0c0d12851deaf606023050b957f6b2e01` (Vercel `aduanefie-production` `dpl_w1LvZykh6ULGbsVoiEprLVC69CeQ`, **Ready**)
  - **Next staged local commits** (awaiting push approval): Edge `/coming-soon` preview-access gate + `FloatingCartButton` persisted-cart `#418` fix + tracker update
- **Backend**: `https://github.com/johnsonsemmanuel/aduanefie-backend.git`
  - **Deployed `backend/main`**: `d80a57c53bd438db7ff04463bc9c50b1727715fa` (GitHub Actions Backend CI `✓` 1m 10s; Railway `https://api.aduanefie.com` **Live**)
  - **Next staged local commit** (awaiting push approval): `07394c3f` (`EnsurePreviewAccess` + `PreviewAccessService` HMAC API gate preserving payment callbacks & webhooks; **51/51 tests, 3,034 assertions**)

---

## Status Legend
- `Not started` | `In progress` | `Implemented` | `Verified` | `Blocked` | `Awaiting business approval` | `Intentionally deferred`

---

## Gate 6 Live Production Verification (`d80a57c5` + `1d4a83e0` — 2026-11-25)

| Check | Endpoint / Route | Live Result | Status |
|---|---|---|---|
| **Deployed SHAs** | Backend `d80a57c5`, Frontend `1d4a83e0` | Backend CI `✓` (1m 10s), live on `api.aduanefie.com`; Frontend CI `✓` (3m 5s), Vercel `dpl_w1LvZykh6ULGbsVoiEprLVC69CeQ` `Ready` on `aduanefie.com` | `Verified` |
| **Personalised Products API (C1)** | `GET /api/v1/items/for-you` (`zoneId=[3]`, `moduleId=1`, `offset=1` & `2`; plus live browser `?limit=8&offset=1`) | `HTTP 200` (`total_size: 12`, Page 1: `Pineapple`, `Apple`; Page 2: `Garden Eggs`, `Tomatoes`); `0` HTTP 500s in live browser | `Verified` |
| **Feature Flags & Footer (C3)** | `GET /api/v1/config` & `/trade` UI | `feature_flags` live (`rfq_new_commitments.allow_new_transactions: false`, `domestic_wholesale.allow_new_transactions: true`); `/trade` displays paused RFQ info Alert, `"Submit Sourcing RFQ"` disabled, `"Shop Wholesale Market"` enabled | `Verified` |
| **RFQ Restrictions & Privacy (C4)** | `POST /api/v1/trade/rfqs`, `/quotes`, `/accept`; `GET /api/v1/trade/sourcing` | All 3 mutation endpoints return `HTTP 403` (`rfq_commitments_disabled`); `GET /api/v1/trade/sourcing` returns `HTTP 200` with buyer PII & rival quotes redacted | `Verified` |
| **Logo & Navigation** | `/`, `/trade`, `/store-registration`, logo click -> `/?module=2` | Navbar `<img alt="logo">` visible (`86.48 x 50`, `api.aduanefie.com/.../2024-07-10-668e19c140e31.png`, `HTTP 200`) across all routes, reloads, and client-side nav | `Verified` |
| **Farmer Registration UI & CSP** | `https://aduanefie.com/store-registration` | `0` console/CSP errors; Google Maps `maps/api/js` `HTTP 200`; 3-step Vendor Application form & optional cover photo uploader render cleanly | `Verified` |
| **Hydration (`#418`)** | `/trade`, `/store-registration`, client nav `/?module=2`, and `/` | `0` console errors on `/trade`, `/store-registration`, and client nav; `0` errors on `/` when cart is empty, **1 remaining `#418` observed on `/` reload when `redux-persist` `cartList` is non-empty** — traced to `FloatingCartButton.jsx` rendering synchronously from rehydrated `cartList` before mount; **fixed locally** with `hasMounted` guard | `In progress` (fix staged locally) |
| **Public-Access Gate Audit** | Website SSR & `/api/v1/*` | **Gap identified on current live deployment**: `ComingSoonGate.js` is currently a client-only modal (with fallback code in client JS); SSR HTML and direct `/api/v1/*` calls are not yet server-gated. **End-to-end fix implemented & tested locally** (`07394c3f` backend HMAC `EnsurePreviewAccess` + frontend `/coming-soon` Edge redirect, preserving `/payment/*` callbacks & `/api/v1/sms/inbound/*` webhooks) | `Implemented` (staged locally, awaiting push approval) |

---

## Requirements Matrix

| ID | Priority | Batch | Requirement | Implementation Status | Test Status | Deployment Status | Verification Status | Key Files & Commits | Remaining Limitations / Blockers |
|---|---|---|---|---|---|---|---|---|---|
| **P3-01** | P0 | Batch A | Payment idempotency & post-commit notification safety | `Implemented` | `Verified` (13/13 tests) | `d80a57c5` **Deployed** | `Verified` | `PaystackController`, `FlutterwaveV3Controller`, `StripePaymentController`, `Processor`, `app/helpers.php` | At-most-once in-process mail/push after commit |
| **P3-02** | P0 | Batch A | Marketer bounty abuse prevention & store reinstatement | `Implemented` | `Verified` (5/5 tests) | `d80a57c5` **Deployed** | `Verified` | `MarketerCommissionService`, `config/marketer.php` | Optional read-only `auditPrematureBounties()` check on prod DB |
| **P3-03** | P0 | Batch A | **Retail delivery serviceability restrictions** | `Implemented` (`OrderController` excludes Module 2 domestic wholesale) | `Verified` (5 config states + wholesale separation) | Code deployed (`d80a57c5`); **INACTIVE in production** (`BusinessSetting` untouched) | **`Awaiting business approval` (NOT Ready)** | `OrderController.php`, [RETAIL_DELIVERY_ZONE_SHORTLIST.md](file:///home/emmanuel-johnson-excellent/Documents/Esper%20Partner%20Works/Aduanefie%20Live/docs/RETAIL_DELIVERY_ZONE_SHORTLIST.md) | **Operational Blocker**: Approve `retail_delivery_zone_ids` and `max_retail_delivery_distance_km` from the 132-zone shortlist |
| **P3-04** | P0 | Batch A/B | Farmer registration optional cover photo & nullable tax fallback | `Implemented` | `Verified` (3/3 tests + live `/store-registration` CSP/Maps check) | `1d4a83e0` + `d80a57c5` **Deployed** | `Verified` | `ValidationSchemaForRestaurant.js`, `VendorLoginController`, `MarketerPortalController` | Complete (non-destructive) |
| **P3-05** | P0 | Batch A | Google Maps CSP & root `/` marketplace rendering | `Implemented` | `Verified` | `1d4a83e0` **Deployed** | `Verified` | `next.config.js`, `pages/index.js`, `middleware.js` | Complete |
| **P3-06** | P1 | Batch B | Marketer data isolation | `Implemented` | `Verified` | `9b50494c` **Deployed** | `Verified` | `resources/views/marketer/_nav.blade.php`, `MarketerPortalController` | Complete |
| **P3-07** | P1 | Batch B | Missing logo & `/` navbar consistency + Redux deduplication | `Implemented` | `Verified` (live browser across `/`, `/trade`, `/store-registration`, client nav) | `1d4a83e0` **Deployed** | `Verified` | `pages/_app.js`, `MainLayout.js`, `HeaderComponent`, `FooterComponent`, `LogoSide` | Complete |
| **P3-08 (C1)** | P1 | Batch C | Personalised products API HTTP 500 (`/api/v1/items/for-you`) | `Implemented` (`7f130406`) | `Verified` (`ForYouItemsTest` 3/3) | `d80a57c5` **Deployed** | **`Verified` (live `HTTP 200` on `zoneId=[3]` & browser)** | `ItemController.php`, `app/CentralLogics/helpers.php` | Complete |
| **P3-09 (C2)** | P1 | Batch C | React hydration errors (`#418` on mobile & desktop) | `Implemented` (`93d0b7bf`, `9d744bb3` + `FloatingCartButton.jsx` fix) | `Verified` (0 errors on `/trade`, `/store-registration`, empty-cart `/`; persisted-cart `FloatingCartButton` fix built & type-checked) | `1d4a83e0` deployed; `FloatingCartButton` fix staged locally | `In progress` (awaiting push of `FloatingCartButton` fix) | `HomePageComponents.js`, `MainLayout.js`, `src/theme/*`, `FloatingCartButton.jsx` | Deploy `FloatingCartButton.jsx` `hasMounted` fix |
| **P3-10 (C3)** | P1 | Batch C | Centralised `BusinessSetting` feature management & footer | `Implemented` (`1139d603`, `ef33f2ed`, `54814574`, `9d744bb3`) | `Verified` | `d80a57c5` + `1d4a83e0` **Deployed** | **`Verified` (live API & `/trade` browser)** | `FeatureFlagService.php`, `ConfigController.php`, `src/utils/featureFlags.js`, `FooterMiddle.js`, `TradeRfqSection.js` | Complete |
| **P3-11 (C4)** | P1 | Batch C | **RFQ lifecycle, privacy & fulfilment gap protection** | `Implemented` (`993d2e3c`, `ef33f2ed`) | `Verified` (`FeatureManagementAndRfqLifecycleTest` 3/3) | `d80a57c5` + `1d4a83e0` **Deployed** (`allow_new_transactions = false`) | **`Blocked` (NOT Ready — fulfilment workflow gap; restrictions `Verified` live `403`)** | `TradeController.php`, `Admin/TradeRfqController.php`, `TradeRfqSection.js` | **Operational Blocker**: Awarded quotes do not yet create `orders` or `payment_requests`/escrow; new commitments remain safely disabled |
| **P3-12** | P1 | Batch D | Auth defects, rider branding & shared dashboard consistency | `Implemented` | `Verified` | `8506c717` **Deployed** | `Verified` | Backend `bd55799e..8506c717` | Complete |
| **P3-13** | P0 | Batch A/D | End-to-end commercial workflow automated tests | `Implemented` (`d80a57c5`) | `Verified` (`51/51` backend tests, `3,034` assertions) | `d80a57c5` **Deployed** | `Verified` | `CommercialWorkflowLifecycleTest.php` | Complete |
| **P3-14** | P0 | Security | **Server & Edge Private Preview Access Gate** (keep site & API closed to public while preserving payment/SMS callbacks) | `Implemented` (backend `07394c3f` + frontend `/coming-soon` Edge gate) | `Verified` (`PreviewAccessGateTest` passed; `tsc` + `next build` passed) | Staged locally (awaiting push approval) | `Implemented` | `PreviewAccessService.php`, `EnsurePreviewAccess.php`, `WaitlistController.php`, `config/portals.php`, `pages/coming-soon.js`, `middleware.js`, `ComingSoonGate.js`, `MainApi.js` | Deploy **Frontend first, then Backend `07394c3f`** upon approval (existing preview testers will re-enter Owner Access code once to mint 30-day HMAC token) |
| **P3-15** | P1 | Batch E | **Controlled Cross-Border Trade: Access Control & Capability Assessment** | `Implemented` (Local Batch 1 Access Control) | `Verified` (`TradeAccessControlTest` 17/17, overall 39/39 tests, 253 assertions; verified on MySQL 8.0.46) | Staged locally on `phase3-backend-sync` behind disabled flag `trade_access_applications` | **`Awaiting business approval` (Review before Push)** | `TradeBuyerProfile.php`, `TradeBuyerAuditLog.php`, `EnsureApprovedTradeBuyer.php`, `TradeAccessController.php`, `TradeBuyerReviewController.php`, `FeatureFlagService.php`, `2026_11_25_000002_create_trade_buyer_profiles_table.php`, `TradeAccessControlTest.php` | **Local Only**: Flag disabled (`allow_new_transactions: false`, `public_visible: false`, `route_enabled: false`). Zero financial commitments. Public application & buyer workspace remain hidden. Catalog privacy unresolved pending supply batch design. |

---

# Controlled Cross-Border Trade Capability Assessment & Architecture Plan (P3-15)

## 1. Executive Summary & Intent
Aduanefie aims to facilitate a **private, approval-based intra-African agricultural trade experience** connecting African institutional buyers (processors, aggregators, hotel chains, supermarket groups, regional food traders) with Ghanaian farmers and agricultural cooperatives.

To protect platform trust, supplier relationships, and commercial liquidity, the platform **must not** expose open self-service export checkouts, automated carrier dispatch, or unverified crop pre-commitments. 

The capability assessment below investigates Aduanefie's existing codebase (`aduanefie-backend` and `aduanefie` frontend), evaluates essential operational controls, and designs the **smallest safe experience** divided into three distinct operational decision gates:
1. **Buyer Approval Gate**: Aduanefie reviews and approves buyer identity, registered business standing, target destination country, and sourcing requirements before any private supply can be discovered.
2. **Product Readiness Gate**: Verification of farm plot authenticity, harvest stage, crop quality, and aggregation hub readiness before supply is matched.
3. **Shipment Coordination Gate**: Corridor feasibility, freight quotations, and phytosanitary/customs clearance readiness evaluated by Aduanefie before any allocation is confirmed.

---

## 2. Capability Matrix Across Existing Infrastructure

| Capability / Functional Area | Classification | Exact Code Paths & Database Schema | Test Evidence | Real Enforced Workflow vs Cosmetic/Partial Asset |
|---|---|---|---|---|
| **1. Buyer Identity, Organisations, Roles & Verification** | **Missing** | `app/Models/User.php`, `database/migrations/2026_11_21_000003_add_organizer_verification_to_users_table.php`, `database/migrations/2026_01_01_000014_create_buyer_sourcing_requests_table.php` (orphan table), `app/Models/BuyerSourcingRequest.php` | `FeatureManagementAndRfqLifecycleTest` verifies user token auth on RFQ submission, but zero buyer organization tests exist. | **Partial / Cosmetic**: `users` table only supports standard retail consumer fields and `organizer_verification_status` (used exclusively for domestic group buys). No corporate buyer entity, tax/TIN verification, import license document storage, or approval/revocation state machine exists. |
| **2. Products, Wholesale Listings, Origin, Weights & Bulk Tiers** | **Reusable with changes** | `app/Models/Item.php` (`origin_country`, `origin_farm`, `weight_kg`, `hs_code`, `bulk_pricing_tiers`, `moq`), `app/Models/BulkPricingTier.php`, `app/Models/Module.php` (`WHOLESALE_MODULE_ID = 2`), `OrderController.php:323-324`, `tradeData.js:getWholesaleMarketHref` | `CommercialWorkflowLifecycleTest` (asserts wholesale module isolation and inventory stock deduction). | **Enforced for Domestic Wholesale**: Module 2 cleanly separates domestic wholesale from retail delivery rules. Product weight and bulk pricing tiers calculate correctly. **Gap**: `Item.certification` is a static, universal string (e.g. `'Organic'`), not destination-verified. No corridor-specific eligibility logic exists. |
| **3. Live Farming, Harvest Forecasts & Crop Tracking** | **Reusable with changes** | `app/Models/FarmPlot.php` (`size_acres`, `planting_date`, `expected_harvest_date`, `status`), `app/Models/GrowthUpdate.php`, `app/Models/Item.php:plot_id`, `FarmerController.php` (`plots`, `update_growth`, `update_dates`, `update_farming_status`) | `BatchBFarmerAcquisitionAndIsolationTest` asserts plot creation and growth update logs. | **Enforced for Farm Operations**: Plots and progress updates track real cultivation stages. **Critical Gap**: `FarmerController::transitionPreOrderOrders` assumes consumer pre-orders. `Item.stock` is a single integer; there is no separate representation of forecast quantity vs spot stock vs non-binding interest. |
| **4. RFQ, Quotes & Negotiation Lifecycle** | **Reusable as-is (Historical/Read) / Unsafe (Mutations)** | `app/Http/Controllers/Api/V1/TradeController.php` (`requests`, `store_rfq`, `store_quote`, `accept_quote`, `esperworks_invoice`, `esperworks_contract`), `Admin/TradeRfqController.php`, `FeatureFlagService.php:rfq_new_commitments` | `FeatureManagementAndRfqLifecycleTest` (verifies PII/quote redaction on `GET /trade/sourcing` and `HTTP 403` on all 3 mutations when flag is disabled). | **Enforced Privacy & Guard**: Supplier privacy and rival quote redaction are fully enforced. New commitments are safely locked (`403`). **Unsafe/Deferred**: `accept_quote` does not create an order, hold funds, or bind inventory; EsperWorks contract integration is an external mock fallback. |
| **5. Cross-Border Logistics, Shipping Rates & Customs** | **Reusable with changes (Feasibility) / Missing (Dispatch)** | `database/migrations/2026_11_12_000010_create_cross_border_reference_tables.php` (`shipping_methods`, `shipping_rates`, `currency_rates`, `customs_duties`), `app/Services/CrossBorder/ShippingQuoteService.php`, `Api/V1/CrossBorderController.php`, `Admin/MarketRateController.php`, `Admin/OrderController.php:update_trade_compliance` | `CommercialWorkflowLifecycleTest` verifies domestic fulfilment; unit tests verify lane pricing. | **Enforced Rate Calculator**: Server-side volumetric weight and lane tariffs calculate duty and freight accurately without trusting client inputs. **Missing**: Automated freight booking, carrier API webhooks, and customs document clearing. Works only as an internal feasibility estimator. |
| **6. Remote Marketer Permissions & Assisted Updates** | **Reusable as-is (Onboarding) / Policy-Restricted** | `app/Http/Controllers/MarketerPortalController.php` (`dashboard`, `showRegisterFarmer`, `storeRegisterFarmer`, `verifyStore`), `app/Services/MarketerCommissionService.php`, `tests/Feature/BatchBFarmerAcquisitionAndIsolationTest.php` | `BatchBFarmerAcquisitionAndIsolationTest` verifies marketer territory isolation and registration. | **Enforced Governance**: Marketers can register new farmers (`status = 0`, requiring admin approval) and view territorial stats. Marketers **cannot** edit crop listings, declare export supply, or adjust allocations. This strict boundary protects against ghost supply. |
| **7. Navigation, Module Selection & Historical Access** | **Reusable with changes** | `DefaultBrowseContextMiddleware.php`, `app/Services/Markets/ZoneAccessService.php`, `FeatureFlagService.php`, `Aduanefie/frontend/pages/trade/index.js`, `src/components/trade/tradeData.js` | `FeatureManagementAndRfqLifecycleTest` verifies historical read access while mutations are gated. | **Enforced Routing & Flags**: `FeatureFlagService` reliably protects historical access. **Gap**: Public navigation currently conflates retail, domestic wholesale, and cross-border trade. Needs clear separation: `Shop` (Retail vs Wholesale) and contextual `Trade across Africa`. |

---

## 3. Investigation of Essential Controls

### 3.1 Backend Access Gating & Supplier Protection
- **The Scrape & Disintermediation Risk**:
  In open agricultural directories, unverified brokers harvest supplier phone numbers, bypass the platform, and leave farmers vulnerable to payment default. Currently, public endpoints like `GET /api/v1/items/search` and `GET /api/v1/stores/details/{id}` expose supplier contact details and coordinates.
- **Backend Access Control Requirements**:
  1. **Trade Access Middleware (`EnsureApprovedTradeBuyer`)**: Protects all private trade endpoints (`/api/v1/trade/supply/*`, `/api/v1/trade/requests/*`). Requests from unauthenticated or unapproved users fail closed with `HTTP 403 trade_access_required`.
  2. **Supplier Data Masking**: Supply discovery displays verified aggregator or cooperative IDs, administrative region (e.g. "Bono East", "Volta Region"), crop grade, and certification badge, but strictly redacts supplier name, direct phone, and farm coordinates until Aduanefie operations issues a feasibility-approved match.
  3. **Revocation & Audit Logging**: Any change in buyer verification status (`pending` -> `approved` -> `revoked` -> `suspended`) must be recorded in an immutable `trade_buyer_audit_logs` table with admin user ID, timestamp, and justification. Revocation instantly invalidates cached buyer permissions.

### 3.2 Inventory & Commitment Separation (Four Distinct Quantities)
To prevent oversubscription, commercial disputes, and fraudulent claims, the backend must never overload the single `Item.stock` integer. The system must explicitly distinguish four separate inventory states:

```
[ Active Farm Plot ] ---> (1) Forecast Quantity (Expected harvest date, yield est., non-binding)
                                      │
[ Harvest & Hub Intake ] ---> (2) Available Stock (Physical, graded, warehouse-receipted)
                                      │
[ Approved Buyer Sourcing ] -> (3) Non-binding Buyer Interest (Requested qty & window, 0 money held)
                                      │
[ Aduanefie Operations Review ] -> (4) Confirmed Allocation (Feasibility approved, quota locked)
```

1. **Forecast Quantity**: Crop in production on a verified `FarmPlot` (e.g., 10,000 kg yellow maize expected 2026-12-20). Carries an estimated confidence interval and harvest window.
2. **Available Spot Stock**: Harvested, dried, and graded produce physically deposited at an Aduanefie Aggregation Hub or verified warehouse.
3. **Non-binding Buyer Interest**: Quantity requested by an approved buyer for a specific destination and timeframe. **Carries zero financial commitment, zero payment transaction, and zero penalty**.
4. **Confirmed Allocation**: Quantity tentatively reserved by Aduanefie operations following commercial feasibility review.

#### Concurrency, Oversubscription & Reduction Controls:
- **No Direct Self-Service Decrement**: Buyer requests do not directly lock inventory. Requests enter an operational review queue.
- **Yield Reduction & Crop Failure Handling**: If an in-production plot suffers pest damage or weather loss, the farmer updates the plot yield. The backend automatically marks affected matching buyer interest requests as `yield_adjusted_review_required` and alerts Aduanefie operations to re-balance allocations, avoiding unilateral buyer cancellations or breach of contract.
- **Financial Commitment Gate**: Online card/mobile money checkouts are strictly prevented on trade supply. Binding commercial terms require explicit proforma issuance and offline escrow/letter-of-credit coordination.

### 3.3 Destination Eligibility vs False Universal Export Labelling
- **The Regulatory Pitfall**: Produce cannot be declared "Export Ready" globally. Phytosanitary tolerances, Maximum Residue Limits (MRLs), aflatoxin thresholds, and fumigation protocols vary fundamentally between destinations:
  - ECOWAS Trade Liberalization Scheme (ETLS): Certificate of Origin, regional phytosanitary pass.
  - Kenya / EAC: KEPHIS phytosanitary clearance, import permit reference.
  - EU / UK: Strict trace-to-farm records, GlobalGAP, MRL lab test certificates.
- **Destination-Specific Verification**:
  - Products must never carry a universal "Export Certified" badge.
  - The catalog stores produce as **"Declared for Export Assessment"**.
  - Eligibility is evaluated on a per-corridor basis: when an approved buyer requests supply for a destination (e.g. Ghana -> Nigeria), Aduanefie cross-references the destination rules in `customs_duties` and phytosanitary requirements before approving feasibility.

---

## 4. Proposed Smallest Safe Experience (End-to-End Flow)

### 4.1 Recommended Navigation Architecture
Preserving existing module IDs (`Module 1 = Retail`, `Module 2 = Domestic Wholesale`) and brand assets:

```
[ Navigation Bar ]
  ├── Shop (Dropdown / Unified Hub)
  │     ├── Shop Retail (Consumer grocery, doorstep delivery) -> /home?module=1
  │     └── Shop Wholesale (Domestic bulk crates/bags, local market transport) -> /home?module=2
  │
  └── Trade across Africa (Contextual Top-Level Item) -> /trade
        ├── Public View: Trade corridor insights, commodity price benchmarks, access requirements
        └── Contextual Banner on Wholesale: "Looking to source across borders? Apply for Trade Access"
```

### 4.2 Four Role Experiences

```
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│   APPLICANT     │       │ ADUANEFIE ADMIN │       │ APPROVED BUYER  │       │ FARMER/SUPPLIER │
│ (Unverified)    │       │ (Reviewer Gate) │       │ (Trade Portal)  │       │ (Vendor Portal) │
└────────┬────────┘       └────────┬────────┘       └────────┬────────┘       └────────┬────────┘
         │                         │                         │                         │
         │ 1. Submits Trade App    │                         │                         │
         ├────────────────────────>│                         │                         │
         │                         │ 2. Reviews Identity,    │                         │
         │                         │    TIN, & Destination   │                         │
         │                         ├─────────────────┐       │                         │
         │                         │ Gate 1: Approved│       │                         │
         │<────────────────────────┼─────────────────┘       │                         │
         │    Notified of Approval │                         │                         │
         │                         │                         │                         │ 3. Declares Export
         │                         │                         │                         │    Supply on Plot
         │                         │<──────────────────────────────────────────────────┤
         │                         │ Gate 2: Product Verified│                         │
         │                         ├─────────────────────────┤                         │
         │                         │                         │                         │
         │                         │                         │ 4. Browses Supply &     │
         │                         │                         │    Submits Non-Binding  │
         │                         │                         │    Harvest Request      │
         │                         │<────────────────────────┤                         │
         │                         │                         │                         │
         │                         │ 5. Operational Check:   │                         │
         │                         │    Logistics, Customs,  │                         │
         │                         │    Allocation Match     │                         │
         │                         ├─────────────────────────┤                         │
         │                         │ Gate 3: Feasibility OK  │                         │
         │                         ├────────────────────────>│ (Proforma / Assisted    │
         │                         │                         │  Escrow Term Sheet)     │
```

1. **Applicant Experience (Public `/trade`)**:
   - Sees trade bloc corridors (ECOWAS, AfCFTA), average indicative transit times, and commodity price benchmarks.
   - Access Gate Callout: *"Cross-border agricultural trade is restricted to vetted commercial buyers."*
   - Clicks **"Apply for Sourcing Access"**: Submits business registration name, country of incorporation, Tax ID/TIN, primary commodities needed, estimated monthly volume, and destination ports/cities.
   - Status tracking: Application displays `Under Review by Aduanefie Trade Operations` with an assigned reference (`TR-APP-XXXXX`).

2. **Aduanefie Reviewer Experience (Admin Portal `/admin/trade`)**:
   - **Gate 1 (Buyer Verification)**: Admin reviews applicant credentials, confirms corporate standing, checks destination import capability, and approves or rejects access. Approval sends automated credentials/login link.
   - **Gate 2 (Product & Harvest Readiness)**: Reviewer inspects farmer supply declarations. Confirms plot location, planting schedule, and aggregation hub quality checks.
   - **Gate 3 (Shipment Coordination & Allocation)**: When an approved buyer submits interest, operations assesses corridor trucking rates (via `ShippingQuoteService`), phytosanitary certificate requirements, and matches the quantity to verified supply.

3. **Approved Buyer Experience (Authenticated `/trade/sourcing`)**:
   - Authenticated access unlocked.
   - Discovers **Private Export Supply**:
     - *In-Stock Produce*: Located at Aggregation Hubs with grade certifications.
     - *Upcoming Harvests*: Verified farm plots with planting date, expected harvest date, and crop cycle progress.
   - Direct supplier phone numbers and exact coordinates remain hidden.
   - Submits **Non-binding Harvest Sourcing Request**:
     - Desired crop, grade, quantity (metric tons/bags), target delivery window, destination country/city, and preferred Incoterm (FOB, CIF, DDP).
     - Submits with zero payment. Reassures buyer: *"Your request has been received. Aduanefie Operations will confirm feasibility, logistics options, and proforma pricing within 24 hours."*

4. **Farmer / Cooperative Supplier Experience (Vendor Portal `/farmer`)**:
   - Farmer or coop manager navigates to **Live Farming / Farm Plots**.
   - Checkbox on plot: *"Available for Export Allocation Consideration"*.
   - Uploads regular growth photos and confirms expected harvest date.
   - When Aduanefie operations matches a buyer request, supplier receives an allocation proposal with farm-gate pickup terms.
   - Supplier does not deal with complex foreign currency, cross-border freight risk, or overseas customs bureaucracy; Aduanefie facilitates aggregation and corridor logistics.

---

## 5. Security, Transaction & Operational Risk Analysis

1. **Transaction & Financial Risk (Mitigated)**:
   - *Risk*: Premature online payments for cross-border shipments create massive chargeback liabilities, foreign exchange volatility, and refund disputes if crops spoil or borders delay clearance.
   - *Control*: Zero self-service online payment rails for trade sourcing. All buyer requests are strictly non-binding expressions of interest until offline legal proformas and escrow terms are executed.
2. **PII Leakage & Scraping Risk (Mitigated)**:
   - *Risk*: Unauthenticated actors or bot crawlers scraping buyer sourcing lists and farm contacts.
   - *Control*: Reusable sanitization logic from `TradeController::requests` (`ef33f2ed`) combined with strict middleware access gating (`EnsureApprovedTradeBuyer`).
3. **Ghost Supply & Crop Speculation (Mitigated)**:
   - *Risk*: Marketers or unverified agents listing fictitious farms to capture commissions or buyer deposits.
   - *Control*: Strict permission boundaries: Marketers can only onboard farmers for admin review. Only verified farms linked to active `FarmPlot` records can have supply declared for export consideration.
4. **Regulatory & SPS Sanction Risk (Mitigated)**:
   - *Risk*: Produce exported without proper phytosanitary certificates, resulting in border seizure, destruction, and regulatory fines.
   - *Control*: Destination-specific checks; no generic "Export Certified" claims. Operational sign-off required against destination phytosanitary requirements before any shipment commitment.

---

## 6. Business Decisions Requiring Executive Approval

| Decision ID | Area | Required Business Decision | Recommendation |
|---|---|---|---|
| **BD-01** | Commercial | **Approved Cross-Border Pilot Corridors** | Restrict Phase 3 pilot strictly to **Ghana -> Nigeria** and **Ghana -> Côte d'Ivoire / Togo (ECOWAS ETLS)** road corridors before attempting East/Southern Africa or air freight. |
| **BD-02** | Operations | **Buyer Minimum Sourcing Threshold** | Establish minimum sourcing threshold for trade applications (e.g. minimum 5 Metric Tons or $5,000 USD equivalent) to filter retail shoppers into domestic wholesale. |
| **BD-03** | Legal / Compliance | **Non-Binding Request Legal Disclaimer** | Formally approve legal disclaimer on buyer request forms clarifying that submission does not constitute a guaranteed allocation, carrier contract, or spot purchase order until bilateral proforma execution. |
| **BD-04** | Commercial | **Assisted Trade Coordination Fee Structure** | Determine platform fee model for assisted cross-border trade (e.g. 3-5% facilitation commission on landed goods value vs fixed per-ton aggregation fee). |
| **BD-05** | Operations | **Dedicated Trade Feasibility Desk** | Assign operational owner responsible for reviewing buyer applications within 24 hours and verifying crop plot harvests. |

---

## 7. Ordered Implementation Plan

```
┌────────────────────────────────────────────────────────────────────────┐
│ BATCH 1: ACCESS APPLICATION & BUYER REVIEW GATE (First Useful Batch)  │
│ - Trade Buyer Application Form & API                                   │
│ - Admin Review Screen (Approve/Reject/Revoke)                          │
│ - EnsureApprovedTradeBuyer Middleware                                  │
│ - Shop Navigation Separation (Retail vs Wholesale vs Trade)            │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ BATCH 2: EXPORT SUPPLY DECLARATION & PRIVATE DISCOVERY (Non-Binding)   │
│ - FarmPlot Export Supply Declaration Flag                              │
│ - Private Sourcing Catalog Endpoint (Gated to Approved Buyers)        │
│ - Non-binding Harvest Request Submission & Tracking                    │
│ - Supplier Contact Masking & Privacy Enforcement                       │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ BATCH 3 (DEFERRED): FEASIBILITY COORDINATION & ESCROW COMMITMENTS      │
│ - Multi-party Allocation & Quota Tracking                              │
│ - Proforma Invoice Issuance & Assisted Escrow Terms                    │
│ - Destination SPS/Phytosanitary Document Vault                         │
│ - Partner Freight Forwarder Integration                                │
└────────────────────────────────────────────────────────────────────────┘
```

### Batch 1: Access Applications & Review Gate (Implemented Locally Behind Disabled Flag)
*Objective*: Build the gated access perimeter, review workflow, and authoritative enforcement without altering any transaction or financial code.
- **Implementation Status**: `Implemented Locally` (Awaiting review before push). Flag `trade_access_applications` is `false` (`allow_new_transactions: false`, `public_visible: false`, `route_enabled: false`). Public application forms and buyer portal remain completely hidden.
- **Backend Components**:
  - Feature Flag: `trade_access_applications` registered in `FeatureFlagService.php`.
  - Migration: `database/migrations/2026_11_25_000002_create_trade_buyer_profiles_table.php` creating:
    - `trade_buyer_profiles`: `user_id` (unique), `organization_name` (nullable for individual traders), `business_type`, `country_of_incorporation`, `tax_id` (optional), `destination_countries` (json), `sourcing_categories` (json), `annual_volume_estimate`, `intended_use`, `status` (`pending`, `under_review`, `approved`, `rejected`, `revoked`), `reviewer_notes`, `approved_by`, `approved_at`, `revoked_at`.
    - `trade_buyer_audit_logs`: immutable audit trail (`profile_id`, `from_status`, `to_status`, `action`, `actor_id`, `actor_type`, `justification`, `ip_address`).
  - Models:
    - `App\Models\TradeBuyerProfile`: status machine (`isValidTransition()`), status messaging (`getStatusMessage()`), relationships to `user`, `reviewer`, `auditLogs`.
    - `App\Models\TradeBuyerAuditLog`: immutable audit model.
  - Middleware:
    - `App\Http\Middleware\EnsureApprovedTradeBuyer`: authoritative database check on every request (`TradeBuyerProfile::where('user_id', $user->id)->first()`), rejecting unapproved, rejected, or revoked buyers immediately (`403 trade_access_required` / `403 trade_access_revoked`).
  - Controllers:
    - `App\Http\Controllers\Api/V1/TradeAccessController`: `apply()` (validates flexible fields, prevents duplicates via row locks, supports resubmission after rejection) and `status()` (ownership-isolated, returns applicant's own status and honest messaging).
    - `App\Http\Controllers\Admin/TradeBuyerReviewController`: `index()` (filters by status), `show()` (profile, sourcing details, full immutable audit trail), and `transition()` (atomic status transitions, permission checks, mandatory rejection/revocation justifications, row-level locks).
  - Admin Views:
    - `resources/views/admin-views/trade-buyers/index.blade.php`: review dashboard listing applicants.
    - `resources/views/admin-views/trade-buyers/show.blade.php`: applicant details, status transition form, and audit trail table.
- **Verification Evidence**:
  - `tests/Feature/TradeAccessControlTest.php`: 11 test cases passed (asserting disabled feature flag, auth requirements, optional organization name, duplicate prevention, unauthorized admin rejection, authorized transitions, atomic lock conflict handling, rejection/resubmission lifecycle, authoritative DB revocation check with immediate denial, ownership isolation, and domestic wholesale/RFQ regression protection).
  - Full suite run: **33 passed (222 assertions)**, zero failures.

---

## 4. Unresolved Catalog Privacy Architecture Proposal (For Supply Batch)

### 4.1 The Architectural Problem & Bypass Trace
In Aduanefie, agricultural items can be listed for domestic retail (`module_id = 1`) or domestic wholesale (`module_id = 2`). When a supplier has supply suitable for cross-border export (or upcoming harvest on a `farm_plot`), exposing that supply must not inadvertently leak private data across public domestic endpoints.

Simply adding an `is_trade_only` column or relying on `Item::scopeActive()` is **insufficient and unsafe** due to multiple bypass paths across the existing codebase:
1. **Direct Item Detail Queries (`ItemController::getItemDetails` / `ProductLogic::get_product`)**:
   - `GET /api/v1/items/details/{id}` retrieves items without checking buyer trade approval.
   - Serializes the entire `Item` model, `store` relation (including vendor phone and address), and related items.
   - If a product is sold both domestically and cross-border, sharing the same `Item` record would expose export quantities, pricing, or certificates to domestic retail buyers.
2. **Listings & Category Feeds (`ItemController::getItems`, `getPopularItems`, `forYouItems`, `getItemsByDiscount`)**:
   - Multiple specialized query builders execute custom queries against `items` table without uniformly inheriting global scopes.
   - Auto-suggest and search endpoints (`ItemController::getSearchedItems`) index item names and return items matching partial strings.
3. **Store / Vendor Feeds (`StoreLogic::get_store_details` / `ItemController::getStoreItems`)**:
   - `GET /api/v1/stores/{id}/items` queries items belonging to a store. If export-only or dual-purpose items are attached to the store, they would render in the public vendor showcase.
4. **Cache & Serialization Leakage**:
   - Public items are cached under zone/module cache keys (e.g. `items_{zone_id}`). Storing trade-specific metadata on `Item` would pollute public cache entries.

### 4.2 Proposed Dual-Offer Decoupled Architecture for Supply Batch (PROPOSED ONLY — NOT APPROVED)

> [!NOTE]
> **Status: Architectural Proposal Only**. The design below is recorded as an unapproved proposal for resolving catalog privacy prior to any supply batch scoping.
> - **No hard-coded units**: Metric Tonnes (MT) is NOT hard-coded; quantity units remain flexible (e.g., MT, kg, 50kg bags, crates, bulk units depending on commodity).
> - **No hard-coded commercial terms**: FOB terms are NOT hard-coded; trade delivery terms remain flexible (Ex-Works, FOB, CIF, or regional warehouse intake) per trade corridor.
> - **No universal export-readiness claim**: Products are NEVER labelled with a universal "export-ready" certification; destination eligibility is evaluated per target country corridor and quarantine/SPS compliance.
> - **Zero financial commitments**: No allocations, payments, RFQ mutations, escrow commitments, or automated carrier dispatches are approved.

To allow a single farm or product to support domestic sales and a private cross-border export offer cleanly and securely without data leakage:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        PUBLIC DOMESTIC COMMERCE                        │
│                                                                        │
│   [ Public User / Guest ]                                              │
│             │                                                          │
│             ▼                                                          │
│   GET /api/v1/items/* (Public Endpoints)                               │
│             │                                                          │
│             ▼                                                          │
│   [ Item Model: Domestic SKU ]                                         │
│   - stock: Domestic retail/wholesale inventory                         │
│   - price: Domestic GHS currency                                       │
│   - store: Public vendor profile                                       │
└────────────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────────────┐
│                     PRIVATE CROSS-BORDER TRADE                         │
│                                                                        │
│   [ Approved Trade Buyer ]                                             │
│             │                                                          │
│             ▼  (EnsureApprovedTradeBuyer Middleware)                   │
│   GET /api/v1/trade/supply/* (Private Gated Endpoints — Unimplemented) │
│             │                                                          │
│             ▼                                                          │
│   [ TradeSupplyOffer Entity (Proposed Only) ] ──> [ FarmPlot / Item ]  │
│   - trade_offer_id                                                     │
│   - item_id (nullable reference to domestic SKU)                       │
│   - farm_plot_id (upcoming harvest forecast reference)                 │
│   - export_available_qty / unit (flexible: MT, kg, 50kg bags, etc.)   │
│   - currency / indicative_unit_price / commercial_term (ExW/FOB/etc.)  │
│   - grade / moisture_content / packaging_specs                         │
│   - destination_eligibility (corridor-specific SPS/quarantine checks)  │
│   - compliance_documents (quarantine certificates, vault references)   │
│             │                                                          │
│             ▼                                                          │
│   [ Privacy-Safe Trade Supply DTO ]                                    │
│   - Masked Aggregator ID (e.g. "COOP-VOLTA-04")                        │
│   - Administrative Region only (e.g. "Volta Region, Ghana")            │
│   - Zero farmer phone numbers, GPS coordinates, or direct PII          │
└────────────────────────────────────────────────────────────────────────┘
```

#### Core Design Rules:
1. **Entity Decoupling (`trade_supply_offers` — Proposed Only)**:
   - Do NOT add `is_trade_only` or export stock columns directly to `items`.
   - Create a dedicated `trade_supply_offers` table representing export lots.
   - An offer can reference an existing `item_id` (for physical hub stock) or a `farm_plot_id` (for upcoming harvest forecast), with its own distinct lifecycle, flexible unit, and negotiated commercial terms.
2. **Endpoint & Scope Isolation**:
   - Existing `/api/v1/items/*` and `/api/v1/stores/*` endpoints remain 100% unaware of `trade_supply_offers`. They never join, eager-load, or serialize trade offers.
   - Private trade discovery is served exclusively through `GET /api/v1/trade/supply` behind `EnsureApprovedTradeBuyer`.
3. **Supplier Contact Masking**:
   - Trade supply DTOs returned to approved buyers include only: crop name, variety, grade, indicative volume, packing specification, administrative origin region, and verified aggregator reference code.
   - Direct farmer PII, telephone numbers, farm GPS coordinates, and private lease documents are stripped server-side.
4. **Cache Partitioning**:
   - Public item caches continue to use standard keys (`item_{id}`).
   - Trade supply offerings use dedicated, isolated cache keys (`trade:supply:{id}`) tagged under trade namespaces, inaccessible to unauthenticated requests.
5. **Direct Access Protection**:
   - Direct requests to `/api/v1/items/details/{id}` for a product that happens to have an active export offer return only domestic information. The private offer is never discoverable via the item endpoint.

---

### Batch 2: Export Supply Declaration & Private Discovery (Non-Binding)
*Objective*: Allow verified farms to declare export availability and approved buyers to request harvest allocations safely.
- **Backend Components**:
  - Migration: Add `is_export_eligible`, `export_min_qty_mt` to `farm_plots` and `items`.
  - Migration: Create `trade_sourcing_requests` (`buyer_id`, `crop_name`, `quantity_mt`, `destination_country`, `destination_city`, `delivery_window_start`, `delivery_window_end`, `status` [submitted, reviewing, matched, cancelled]).
  - Endpoint: `GET /api/v1/trade/supply` (gated by `EnsureApprovedTradeBuyer`, masks supplier names/contacts).
  - Endpoint: `POST /api/v1/trade/requests` (strictly non-binding, zero payment collected).
- **Frontend Components**:
  - Approved Buyer Portal `/trade/sourcing`: Private catalog of spot hub stock and upcoming harvests.
  - Sourcing Request Modal: Quantity, destination, date window.
  - Farmer Dashboard: Checkbox to declare upcoming harvest for export consideration.
- **Acceptance Tests**:
  - `PrivateTradeSupplyDiscoveryTest`:
    - Unapproved visitor requesting `GET /api/v1/trade/supply` receives `403 trade_access_required`.
    - Approved trade buyer receives list of available export supply with supplier name and phone masked.
    - Non-binding sourcing request creates record without charging payment or modifying `Item.stock`.
    - Reductions in farmer plot harvest yield trigger notifications without automated cancellations.

### Batch 3: Confirmed Allocations, Logistics Feasibility & Escrow (Intentionally Deferred)
*Objective*: Commercial and payment execution.
- Gated behind: Executive approval of BD-01 to BD-05, integration of a licensed escrow/banking partner, and operational trade desk onboarding.
- Automated tests will verify contract generation, proforma agreement, and escrow deposit lifecycle before enabling live.

---

## Repository Boundary Audit (2026-11-25)
- **Frontend repo** (`origin` -> `johnsonsemmanuel/aduanefie.git`): Clean Next.js repo; duplicate `backend/` tree removed in `43d89164` and `7e083222`.
- **Backend repo** (`backend` -> `johnsonsemmanuel/aduanefie-backend.git`): Dedicated Laravel repository; Batch A & B fixes previously committed only to `3a08f367` were cleanly ported to `aduanefie-backend` `main` without overwriting `bd55799e..8506c717`, verified with `php -l`, `php artisan test` (51 passed, 3,034 assertions), `php artisan route:cache`, and `php artisan view:cache`.
