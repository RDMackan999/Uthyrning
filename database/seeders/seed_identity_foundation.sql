INSERT INTO roles (organization_id, role_key, name, description, status_key)
SELECT NULL, 'system_admin', 'System administrator', 'System role for full administrative access.', 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM roles WHERE organization_id IS NULL AND role_key = 'system_admin'
);

INSERT INTO roles (organization_id, role_key, name, description, status_key)
SELECT NULL, 'organization_owner', 'Organization owner', 'Role for future owners of a rental organization.', 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM roles WHERE organization_id IS NULL AND role_key = 'organization_owner'
);

INSERT INTO roles (organization_id, role_key, name, description, status_key)
SELECT NULL, 'organization_staff', 'Organization staff', 'Role for future staff in a rental organization.', 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM roles WHERE organization_id IS NULL AND role_key = 'organization_staff'
);

INSERT INTO roles (organization_id, role_key, name, description, status_key)
SELECT NULL, 'customer_user', 'Customer user', 'Role for future customer accounts without administrative access.', 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM roles WHERE organization_id IS NULL AND role_key = 'customer_user'
);

INSERT INTO permissions (permission_key, name, description, status_key)
SELECT 'organizations.view', 'View organizations', 'Base permission for reading organization data.', 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE permission_key = 'organizations.view'
);

INSERT INTO permissions (permission_key, name, description, status_key)
SELECT 'users.view', 'View users', 'Base permission for reading user data.', 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE permission_key = 'users.view'
);

INSERT INTO permissions (permission_key, name, description, status_key)
SELECT 'roles.manage', 'Manage roles', 'Base permission for future role administration.', 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE permission_key = 'roles.manage'
);

INSERT INTO permissions (permission_key, name, description, status_key)
SELECT 'permissions.view', 'View permissions', 'Base permission for reading permission data.', 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE permission_key = 'permissions.view'
);

INSERT INTO permissions (permission_key, name, description, status_key)
SELECT 'companies.view', 'View companies', 'Base permission for reading company data.', 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE permission_key = 'companies.view'
);

INSERT INTO permissions (permission_key, name, description, status_key)
SELECT 'companies.manage', 'Manage companies', 'Base permission for future company management.', 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE permission_key = 'companies.manage'
);

INSERT INTO permissions (permission_key, name, description, status_key)
SELECT 'customers.view', 'View customers', 'Base permission for reading customer data.', 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE permission_key = 'customers.view'
);

INSERT INTO permissions (permission_key, name, description, status_key)
SELECT 'customers.manage', 'Manage customers', 'Base permission for future customer management.', 'active'
WHERE NOT EXISTS (
    SELECT 1 FROM permissions WHERE permission_key = 'customers.manage'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
CROSS JOIN permissions
WHERE roles.organization_id IS NULL
    AND roles.role_key = 'system_admin';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions
    ON permissions.permission_key IN (
        'organizations.view',
        'users.view',
        'companies.view',
        'companies.manage',
        'customers.view',
        'customers.manage'
    )
WHERE roles.organization_id IS NULL
    AND roles.role_key = 'organization_owner';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT roles.id, permissions.id
FROM roles
INNER JOIN permissions
    ON permissions.permission_key IN (
        'companies.view',
        'customers.view'
    )
WHERE roles.organization_id IS NULL
    AND roles.role_key = 'organization_staff';
