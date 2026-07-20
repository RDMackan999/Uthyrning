INSERT INTO item_categories (organization_id, organization_scope_key, slug, name, description, sort_order, is_active, created_at, updated_at)
SELECT NULL, 'global', 'verktyg', 'Verktyg', NULL, 10, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM item_categories WHERE organization_id IS NULL AND slug = 'verktyg'
);

INSERT INTO item_categories (organization_id, organization_scope_key, slug, name, description, sort_order, is_active, created_at, updated_at)
SELECT NULL, 'global', 'maskiner', 'Maskiner', NULL, 20, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM item_categories WHERE organization_id IS NULL AND slug = 'maskiner'
);

INSERT INTO item_categories (organization_id, organization_scope_key, slug, name, description, sort_order, is_active, created_at, updated_at)
SELECT NULL, 'global', 'slap', 'Släp', NULL, 30, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM item_categories WHERE organization_id IS NULL AND slug = 'slap'
);

INSERT INTO item_categories (organization_id, organization_scope_key, slug, name, description, sort_order, is_active, created_at, updated_at)
SELECT NULL, 'global', 'tradgard', 'Trädgård', NULL, 40, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM item_categories WHERE organization_id IS NULL AND slug = 'tradgard'
);

INSERT INTO item_categories (organization_id, organization_scope_key, slug, name, description, sort_order, is_active, created_at, updated_at)
SELECT NULL, 'global', 'bygg', 'Bygg', NULL, 50, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM item_categories WHERE organization_id IS NULL AND slug = 'bygg'
);

INSERT INTO item_categories (organization_id, organization_scope_key, slug, name, description, sort_order, is_active, created_at, updated_at)
SELECT NULL, 'global', 'ovrigt', 'Övrigt', NULL, 60, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM item_categories WHERE organization_id IS NULL AND slug = 'ovrigt'
);
