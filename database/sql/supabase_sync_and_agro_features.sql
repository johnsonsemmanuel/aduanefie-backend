-- ============================================================================
-- Aduanefie Live - Supabase (Postgres `legacy` schema) Sync & Agro Features
-- ============================================================================
-- Run this ONCE in the Supabase SQL Editor (which runs as `postgres`).
-- Notice: NO `SET ROLE` and NO `ALTER TABLE ... OWNER TO` statements are used,
-- because Supabase managed `postgres` cannot SET ROLE to custom roles.
-- Instead, everything is created cleanly in `legacy` and privileges are granted
-- to `aduanefie_app`.
-- ============================================================================

SET search_path TO legacy, public;

-- ----------------------------------------------------------------------------
-- 1. Stores: B2B Verification, Gold Supplier, Remote Marketer & Farm Profile
-- ----------------------------------------------------------------------------
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS verification_tier VARCHAR(30) NULL;
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS verification_status VARCHAR(30) NOT NULL DEFAULT 'pending';
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS verification_documents TEXT NULL;
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS verified_at TIMESTAMP NULL;
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS gold_supplier BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS gold_supplier_expires_at DATE NULL;
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS marketer_id BIGINT NULL;
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS marketer_assigned_at TIMESTAMP NULL;

-- Farmer / Backyard Farming / Marketer Verification / Country columns
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS farm_type VARCHAR(40) NOT NULL DEFAULT 'commercial';
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS is_backyard BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS country VARCHAR(100) NULL DEFAULT 'Ghana';
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS region VARCHAR(120) NULL;
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS farm_size_acres NUMERIC(10, 2) NULL;
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS primary_crops TEXT NULL;
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS cultivation_status VARCHAR(30) NOT NULL DEFAULT 'active';
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS next_harvest_date DATE NULL;
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS marketer_verified BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS marketer_verified_at TIMESTAMP NULL;
ALTER TABLE legacy.stores ADD COLUMN IF NOT EXISTS marketer_verification_notes TEXT NULL;

CREATE INDEX IF NOT EXISTS stores_gold_supplier_idx ON legacy.stores (gold_supplier);
CREATE INDEX IF NOT EXISTS stores_marketer_id_idx ON legacy.stores (marketer_id);
CREATE INDEX IF NOT EXISTS stores_farm_type_idx ON legacy.stores (farm_type);
CREATE INDEX IF NOT EXISTS stores_is_backyard_idx ON legacy.stores (is_backyard);

-- ----------------------------------------------------------------------------
-- 2. Items: Agro Attributes, HS Codes & Produce Cultivation Lifecycle / Pre-Order
-- ----------------------------------------------------------------------------
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS origin_farm VARCHAR(255) NULL;
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS origin_country VARCHAR(100) NULL DEFAULT 'Ghana';
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS seasonality VARCHAR(100) NULL;
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS certification VARCHAR(255) NULL;
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS cold_chain_required BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS harvest_date DATE NULL;
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS expiry_date DATE NULL;
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS perishability_days INTEGER NULL;
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS minimum_order_qty INTEGER NOT NULL DEFAULT 1;
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS hs_code VARCHAR(50) NULL;
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS hs_description VARCHAR(255) NULL;

-- Produce Lifecycle ("farming" -> "harvest_ready" -> "in_stock") & Pre-Order
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS farming_status VARCHAR(30) NOT NULL DEFAULT 'in_stock';
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS planting_date DATE NULL;
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS expected_harvest_date DATE NULL;
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS crop_cycle_days INTEGER NULL;
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS pre_order_enabled BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS pre_order_limit INTEGER NULL;
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS is_backyard BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS marketer_verified BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE legacy.items ADD COLUMN IF NOT EXISTS marketer_verified_at TIMESTAMP NULL;

CREATE INDEX IF NOT EXISTS items_farming_status_idx ON legacy.items (farming_status);
CREATE INDEX IF NOT EXISTS items_expected_harvest_date_idx ON legacy.items (expected_harvest_date);

-- ----------------------------------------------------------------------------
-- 3. Admins: Two-Factor Auth & Remote Marketer Commission / Code
-- ----------------------------------------------------------------------------
ALTER TABLE legacy.admins ADD COLUMN IF NOT EXISTS two_factor_secret TEXT NULL;
ALTER TABLE legacy.admins ADD COLUMN IF NOT EXISTS two_factor_recovery_codes TEXT NULL;
ALTER TABLE legacy.admins ADD COLUMN IF NOT EXISTS two_factor_confirmed_at TIMESTAMP NULL;
ALTER TABLE legacy.admins ADD COLUMN IF NOT EXISTS two_factor_last_step BIGINT NULL;
ALTER TABLE legacy.admins ADD COLUMN IF NOT EXISTS marketer_commission_rate NUMERIC(6, 3) NULL;
ALTER TABLE legacy.admins ADD COLUMN IF NOT EXISTS marketer_code VARCHAR(32) NULL;

-- ----------------------------------------------------------------------------
-- 4. Orders: Pre-Order Booking Ahead Support
-- ----------------------------------------------------------------------------
ALTER TABLE legacy.orders ADD COLUMN IF NOT EXISTS is_pre_order BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE legacy.orders ADD COLUMN IF NOT EXISTS expected_harvest_date DATE NULL;

-- ----------------------------------------------------------------------------
-- 5. B2B Cross-Border Trade Tables
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS legacy.trade_requests (
    id BIGSERIAL PRIMARY KEY,
    buyer_id BIGINT NOT NULL,
    buyer_type VARCHAR(50) NOT NULL,
    origin_country VARCHAR(100) NULL,
    product_id BIGINT NOT NULL,
    quantity BIGINT NOT NULL,
    unit VARCHAR(50) NOT NULL DEFAULT 'kg',
    destination_country VARCHAR(100) NOT NULL,
    incoterm VARCHAR(50) NOT NULL DEFAULT 'FOB',
    needed_by DATE NULL,
    notes TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS trade_requests_buyer_idx ON legacy.trade_requests (buyer_id, buyer_type);
CREATE INDEX IF NOT EXISTS trade_requests_status_idx ON legacy.trade_requests (status);
CREATE INDEX IF NOT EXISTS trade_requests_origin_country_idx ON legacy.trade_requests (origin_country);

CREATE TABLE IF NOT EXISTS legacy.trade_request_quotes (
    id BIGSERIAL PRIMARY KEY,
    trade_request_id BIGINT NOT NULL REFERENCES legacy.trade_requests(id) ON DELETE CASCADE,
    seller_id BIGINT NOT NULL,
    seller_type VARCHAR(50) NOT NULL DEFAULT 'vendor',
    quoted_price DOUBLE PRECISION NOT NULL,
    currency_code VARCHAR(10) NOT NULL DEFAULT 'GHS',
    estimated_delivery DATE NULL,
    terms TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS trade_request_quotes_req_idx ON legacy.trade_request_quotes (trade_request_id);
CREATE INDEX IF NOT EXISTS trade_request_quotes_status_idx ON legacy.trade_request_quotes (status);

CREATE TABLE IF NOT EXISTS legacy.trade_corridors (
    id BIGSERIAL PRIMARY KEY,
    origin_country VARCHAR(100) NOT NULL,
    destination_country VARCHAR(100) NOT NULL,
    corridor_name VARCHAR(255) NOT NULL,
    distance_km DOUBLE PRECISION NULL,
    transit_days_estimate INTEGER NULL,
    trucking_rate_per_ton_usd DOUBLE PRECISION NOT NULL DEFAULT 0,
    minimum_charge_usd DOUBLE PRECISION NOT NULL DEFAULT 0,
    cold_chain_available BOOLEAN NOT NULL DEFAULT FALSE,
    warehousing_available BOOLEAN NOT NULL DEFAULT FALSE,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT trade_corridors_origin_dest_unique UNIQUE (origin_country, destination_country)
);
CREATE INDEX IF NOT EXISTS trade_corridors_name_idx ON legacy.trade_corridors (corridor_name);
CREATE INDEX IF NOT EXISTS trade_corridors_status_idx ON legacy.trade_corridors (status);

CREATE TABLE IF NOT EXISTS legacy.customs_duties (
    id BIGSERIAL PRIMARY KEY,
    destination_country VARCHAR(100) NOT NULL,
    hs_code VARCHAR(50) NOT NULL,
    hs_description VARCHAR(255) NOT NULL,
    duty_rate_percent DOUBLE PRECISION NOT NULL DEFAULT 0,
    vat_rate_percent DOUBLE PRECISION NOT NULL DEFAULT 0,
    excise_rate_percent DOUBLE PRECISION NOT NULL DEFAULT 0,
    requires_sps_certificate BOOLEAN NOT NULL DEFAULT FALSE,
    requires_phytosanitary BOOLEAN NOT NULL DEFAULT FALSE,
    requires_origin_certificate BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS customs_duties_dest_hs_idx ON legacy.customs_duties (destination_country, hs_code);

CREATE TABLE IF NOT EXISTS legacy.trade_documents (
    id BIGSERIAL PRIMARY KEY,
    trade_request_id BIGINT NULL REFERENCES legacy.trade_requests(id) ON DELETE CASCADE,
    trade_quote_id BIGINT NULL REFERENCES legacy.trade_request_quotes(id) ON DELETE CASCADE,
    document_type VARCHAR(100) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    storage VARCHAR(50) NOT NULL DEFAULT 'public',
    issued_by VARCHAR(255) NULL,
    issue_date DATE NULL,
    expiry_date DATE NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS trade_documents_req_idx ON legacy.trade_documents (trade_request_id);
CREATE INDEX IF NOT EXISTS trade_documents_quote_idx ON legacy.trade_documents (trade_quote_id);
CREATE INDEX IF NOT EXISTS trade_documents_status_idx ON legacy.trade_documents (status);

CREATE TABLE IF NOT EXISTS legacy.trade_contracts (
    id BIGSERIAL PRIMARY KEY,
    trade_request_id BIGINT NOT NULL UNIQUE REFERENCES legacy.trade_requests(id) ON DELETE CASCADE,
    trade_quote_id BIGINT NULL REFERENCES legacy.trade_request_quotes(id) ON DELETE CASCADE,
    buyer_id BIGINT NOT NULL,
    buyer_type VARCHAR(50) NOT NULL,
    seller_id BIGINT NOT NULL,
    seller_type VARCHAR(50) NOT NULL DEFAULT 'vendor',
    contract_amount DOUBLE PRECISION NOT NULL,
    currency_code VARCHAR(10) NOT NULL DEFAULT 'GHS',
    escrow_amount DOUBLE PRECISION NOT NULL DEFAULT 0,
    payment_status VARCHAR(30) NOT NULL DEFAULT 'pending',
    delivery_status VARCHAR(30) NOT NULL DEFAULT 'pending',
    delivery_confirmed_at DATE NULL,
    docs_verified_at DATE NULL,
    funds_released_at DATE NULL,
    release_notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS trade_contracts_payment_idx ON legacy.trade_contracts (payment_status);
CREATE INDEX IF NOT EXISTS trade_contracts_delivery_idx ON legacy.trade_contracts (delivery_status);
CREATE INDEX IF NOT EXISTS trade_contracts_buyer_idx ON legacy.trade_contracts (buyer_id);
CREATE INDEX IF NOT EXISTS trade_contracts_seller_idx ON legacy.trade_contracts (seller_id);

CREATE TABLE IF NOT EXISTS legacy.trade_blocs (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    member_countries TEXT NOT NULL,
    primary_currencies VARCHAR(255) NOT NULL,
    common_languages VARCHAR(255) NOT NULL,
    website VARCHAR(255) NULL,
    logo VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS trade_blocs_status_idx ON legacy.trade_blocs (status);

CREATE TABLE IF NOT EXISTS legacy.bulk_pricing_tiers (
    id BIGSERIAL PRIMARY KEY,
    item_id BIGINT NOT NULL REFERENCES legacy.items(id) ON DELETE CASCADE,
    min_quantity INTEGER NOT NULL,
    max_quantity INTEGER NULL,
    discount_percent DOUBLE PRECISION NOT NULL DEFAULT 0,
    price_override DOUBLE PRECISION NULL,
    currency_code VARCHAR(10) NOT NULL DEFAULT 'GHS',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS bulk_pricing_tiers_item_idx ON legacy.bulk_pricing_tiers (item_id, min_quantity);

CREATE TABLE IF NOT EXISTS legacy.standing_orders (
    id BIGSERIAL PRIMARY KEY,
    buyer_id BIGINT NOT NULL,
    buyer_type VARCHAR(50) NOT NULL DEFAULT 'customer',
    item_id BIGINT NOT NULL REFERENCES legacy.items(id) ON DELETE CASCADE,
    store_id BIGINT NOT NULL REFERENCES legacy.stores(id) ON DELETE CASCADE,
    quantity INTEGER NOT NULL,
    unit VARCHAR(50) NOT NULL DEFAULT 'kg',
    frequency VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    destination_country VARCHAR(100) NOT NULL,
    incoterm VARCHAR(50) NOT NULL DEFAULT 'FOB',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    last_order_date DATE NULL,
    next_order_date DATE NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS standing_orders_buyer_idx ON legacy.standing_orders (buyer_id, buyer_type);
CREATE INDEX IF NOT EXISTS standing_orders_status_idx ON legacy.standing_orders (status);

CREATE TABLE IF NOT EXISTS legacy.trade_assurances (
    id BIGSERIAL PRIMARY KEY,
    trade_contract_id BIGINT NOT NULL UNIQUE REFERENCES legacy.trade_contracts(id) ON DELETE CASCADE,
    assurance_type VARCHAR(50) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    inspector_name VARCHAR(255) NULL,
    inspector_organization VARCHAR(255) NULL,
    inspection_date DATE NULL,
    inspection_report TEXT NULL,
    findings TEXT NULL,
    inspection_fee DOUBLE PRECISION NOT NULL DEFAULT 0,
    currency_code VARCHAR(10) NOT NULL DEFAULT 'USD',
    certificate_number VARCHAR(255) NULL,
    certificate_expiry DATE NULL,
    certificate_file VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS trade_assurances_status_idx ON legacy.trade_assurances (status);

CREATE TABLE IF NOT EXISTS legacy.buyer_sourcing_requests (
    id BIGSERIAL PRIMARY KEY,
    buyer_id BIGINT NOT NULL,
    buyer_type VARCHAR(50) NOT NULL DEFAULT 'customer',
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    commodity VARCHAR(255) NOT NULL,
    min_quantity INTEGER NOT NULL,
    unit VARCHAR(50) NOT NULL DEFAULT 'kg',
    destination_country VARCHAR(100) NOT NULL,
    incoterm VARCHAR(50) NOT NULL DEFAULT 'FOB',
    budget_min DOUBLE PRECISION NULL,
    budget_max DOUBLE PRECISION NULL,
    currency_code VARCHAR(10) NOT NULL DEFAULT 'USD',
    needed_by DATE NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS buyer_sourcing_buyer_idx ON legacy.buyer_sourcing_requests (buyer_id, buyer_type);
CREATE INDEX IF NOT EXISTS buyer_sourcing_status_idx ON legacy.buyer_sourcing_requests (status);

-- ----------------------------------------------------------------------------
-- 6. Remote Marketers Ledger & Payouts
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS legacy.marketer_commissions (
    id BIGSERIAL PRIMARY KEY,
    marketer_id BIGINT NOT NULL,
    store_id BIGINT NOT NULL,
    order_id BIGINT NOT NULL UNIQUE,
    order_transaction_id BIGINT NULL,
    base_amount NUMERIC(24, 2) NOT NULL,
    rate NUMERIC(6, 3) NOT NULL,
    amount NUMERIC(24, 2) NOT NULL,
    currency VARCHAR(10) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    eligible_at TIMESTAMP NULL,
    payout_id BIGINT NULL,
    void_reason VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS marketer_comm_marketer_idx ON legacy.marketer_commissions (marketer_id);
CREATE INDEX IF NOT EXISTS marketer_comm_store_idx ON legacy.marketer_commissions (store_id);
CREATE INDEX IF NOT EXISTS marketer_comm_status_idx ON legacy.marketer_commissions (status);

CREATE TABLE IF NOT EXISTS legacy.marketer_payouts (
    id BIGSERIAL PRIMARY KEY,
    marketer_id BIGINT NOT NULL,
    amount NUMERIC(24, 2) NOT NULL,
    currency VARCHAR(10) NOT NULL,
    method VARCHAR(30) NOT NULL DEFAULT 'mobile_money',
    network VARCHAR(50) NULL,
    account_name VARCHAR(100) NULL,
    account_number VARCHAR(50) NULL,
    country VARCHAR(10) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'requested',
    approved_by BIGINT NULL,
    requested_at TIMESTAMP NULL,
    approved_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    reference VARCHAR(100) NULL,
    note TEXT NULL,
    rejection_reason VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS marketer_payouts_marketer_idx ON legacy.marketer_payouts (marketer_id);
CREATE INDEX IF NOT EXISTS marketer_payouts_status_idx ON legacy.marketer_payouts (status);

-- ----------------------------------------------------------------------------
-- 7. Customer AI Chat Conversations & Messages (React v4.2 ChatBot)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS legacy.ai_conversations (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NULL,
    guest_id VARCHAR(100) NULL,
    module_id BIGINT NULL,
    zone_id BIGINT NULL,
    title VARCHAR(255) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS ai_conv_user_resume_idx ON legacy.ai_conversations (user_id, status, module_id, zone_id, updated_at);
CREATE INDEX IF NOT EXISTS ai_conv_guest_resume_idx ON legacy.ai_conversations (guest_id, status, module_id, zone_id, updated_at);

CREATE TABLE IF NOT EXISTS legacy.ai_messages (
    id BIGSERIAL PRIMARY KEY,
    conversation_id BIGINT NOT NULL REFERENCES legacy.ai_conversations(id) ON DELETE CASCADE,
    role VARCHAR(20) NOT NULL,
    content TEXT NULL,
    tool_name VARCHAR(100) NULL,
    metadata JSONB NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
CREATE INDEX IF NOT EXISTS ai_messages_conv_idx ON legacy.ai_messages (conversation_id);

-- ----------------------------------------------------------------------------
-- 8. Record Migrations & Grant Permissions to Application Role
-- ----------------------------------------------------------------------------
DO $$
DECLARE
    next_batch INTEGER;
    m TEXT;
    mig_list TEXT[] := ARRAY[
        '2026_01_01_000000_create_trade_requests_table',
        '2026_01_01_000001_create_trade_request_quotes_table',
        '2026_01_01_000002_add_verification_tier_to_stores_table',
        '2026_01_01_000003_create_trade_corridors_table',
        '2026_01_01_000004_create_customs_duties_table',
        '2026_01_01_000005_add_hs_code_to_items_table',
        '2026_01_01_000006_create_trade_documents_table',
        '2026_01_01_000007_add_origin_country_to_trade_requests',
        '2026_01_01_000008_create_trade_contracts_table',
        '2026_01_01_000009_create_trade_blocs_table',
        '2026_01_01_000010_create_bulk_pricing_tiers_table',
        '2026_01_01_000011_create_standing_orders_table',
        '2026_01_01_000012_create_trade_assurances_table',
        '2026_01_01_000013_add_gold_supplier_to_stores_table',
        '2026_01_01_000014_create_buyer_sourcing_requests_table',
        '2026_11_11_000001_add_two_factor_to_admins_table',
        '2026_11_11_000002_add_marketer_attribution',
        '2026_11_11_000003_create_marketer_tables',
        '2026_11_11_000004_add_marketer_assigned_at',
        '2026_11_11_000005_add_agro_lifecycle_and_ai_chat_tables'
    ];
BEGIN
    SELECT COALESCE(MAX(batch), 0) + 1 INTO next_batch FROM legacy.migrations;
    FOREACH m IN ARRAY mig_list LOOP
        IF NOT EXISTS (SELECT 1 FROM legacy.migrations WHERE migration = m) THEN
            INSERT INTO legacy.migrations (migration, batch) VALUES (m, next_batch);
        END IF;
    END LOOP;
END $$;

GRANT USAGE ON SCHEMA legacy TO aduanefie_app;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA legacy TO aduanefie_app;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA legacy TO aduanefie_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA legacy GRANT ALL ON TABLES TO aduanefie_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA legacy GRANT ALL ON SEQUENCES TO aduanefie_app;

-- Supabase enables Row-Level Security (RLS) by default on newly created tables;
-- disable RLS on these Laravel-managed backend tables so aduanefie_app can read/write them.
DO $$
DECLARE
    tbl TEXT;
    rls_tables TEXT[] := ARRAY[
        'trade_requests',
        'trade_request_quotes',
        'trade_corridors',
        'customs_duties',
        'trade_documents',
        'trade_contracts',
        'trade_blocs',
        'bulk_pricing_tiers',
        'standing_orders',
        'trade_assurances',
        'buyer_sourcing_requests',
        'marketer_commissions',
        'marketer_payouts',
        'ai_conversations',
        'ai_messages'
    ];
BEGIN
    FOREACH tbl IN ARRAY rls_tables LOOP
        EXECUTE format('ALTER TABLE legacy.%I DISABLE ROW LEVEL SECURITY;', tbl);
    END LOOP;
END $$;
