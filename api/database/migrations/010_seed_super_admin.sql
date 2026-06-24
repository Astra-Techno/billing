-- Create super admin user if not already exists
INSERT INTO users (name, email, mobile, password, is_super_admin, active, created_at, updated_at)
SELECT 'Super Admin', 'admin@aibilling.com', '', '$2y$10$m9c/I0eFNKib7ASX2UzPu.K2j3rtksqrL9row7fP5eCZFH0uJV4By', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM users WHERE email = 'admin@aibilling.com'
);
