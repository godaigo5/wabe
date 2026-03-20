INSERT INTO licenses (license_key, plan, status, domain_limit, customer_email, expires_at)
VALUES
('FREE-LOCAL-001', 'free', 'active', 1, 'test-free@example.com', NULL),
('ADVANCED-LOCAL-001', 'advanced', 'active', 1, 'test-advanced@example.com', DATE_ADD(NOW(), INTERVAL 1 YEAR)),
('PRO-LOCAL-001', 'pro', 'active', 3, 'test-pro@example.com', DATE_ADD(NOW(), INTERVAL 1 YEAR));
