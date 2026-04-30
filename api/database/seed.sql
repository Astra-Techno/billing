-- BillBook India — Demo Seed Data
-- business_id=1, user_id=2

SET FOREIGN_KEY_CHECKS = 0;

-- ── EXPENSE CATEGORIES ────────────────────────────────────────────────────────
INSERT INTO expense_categories (business_id, name, sort_order) VALUES
(1, 'Office Supplies',   1),
(1, 'Travel',            2),
(1, 'Software & Tools',  3),
(1, 'Marketing',         4),
(1, 'Utilities',         5),
(1, 'Miscellaneous',     6);

-- ── PRODUCTS / SERVICES ───────────────────────────────────────────────────────
INSERT INTO products (business_id, type, name, description, hsn_sac, unit, price, active, created_at) VALUES
(1, 'service', 'Web Development',           'Custom website design and development',             '998314', 'Project', 45000.00, 1, NOW()),
(1, 'service', 'Annual Maintenance Contract','Software maintenance and support for 12 months',   '998313', 'Year',    24000.00, 1, NOW()),
(1, 'service', 'SEO & Digital Marketing',   'SEO and social media marketing',                    '998361', 'Month',   12000.00, 1, NOW()),
(1, 'service', 'Mobile App Development',    'Android/iOS app development',                       '998314', 'Project', 80000.00, 1, NOW()),
(1, 'product', 'Laptop Stand',              'Adjustable aluminium laptop stand',                 '84733090','Nos',     1500.00, 1, NOW()),
(1, 'product', 'Mechanical Keyboard',       'Wireless mechanical keyboard',                      '84716060','Nos',     3500.00, 1, NOW()),
(1, 'service', 'GST Return Filing',         'Monthly GST return preparation and filing',         '998232', 'Month',   2500.00, 1, NOW()),
(1, 'service', 'Logo Design',               'Professional brand logo design',                    '998386', 'Project',  8000.00, 1, NOW());

-- ── CLIENTS ───────────────────────────────────────────────────────────────────
INSERT INTO clients (business_id, type, name, company, gstin, email, mobile, address_line1, city, state_id, pincode, credit_days, active, created_at) VALUES
(1, 'business',    'Sharma Electronics',   'Sharma Electronics Pvt Ltd',      '27AABCS1234A1Z5', 'sharma@sharmaelectronics.com', '9876543210', '45, Linking Road, Bandra West', 'Mumbai',    26, '400050', 30, 1, NOW() - INTERVAL 90 DAY),
(1, 'business',    'Mehta Traders',        'Mehta Traders & Co.',             '24AABCM5678B1Z3', 'info@mehtatraders.com',        '9765432109', '12, Ring Road, Navrangpura',    'Ahmedabad', 24, '380009', 45, 1, NOW() - INTERVAL 75 DAY),
(1, 'business',    'Patel Industries',     'Patel Industries Ltd',            '29AABCP9012C1Z1', 'accounts@patelindustries.com', '9654321098', '78, Industrial Area, Peenya',   'Bengaluru', 28, '560058', 30, 1, NOW() - INTERVAL 60 DAY),
(1, 'individual',  'Rajesh Kumar',         NULL,                              NULL,              'rajesh.kumar@gmail.com',       '9543210987', '23, Anna Nagar, 4th Street',   'Chennai',   32, '600040', 15, 1, NOW() - INTERVAL 45 DAY),
(1, 'business',    'Gupta & Sons',         'Gupta & Sons Trading Company',    '07AABCG3456D1Z7', 'guptasons@gmail.com',          '9432109876', '56, Karol Bagh Market',        'New Delhi',  7, '110005', 30, 1, NOW() - INTERVAL 30 DAY),
(1, 'business',    'TechStart Solutions',  'TechStart Solutions LLP',         '27AABCT7890E1Z4', 'billing@techstart.in',         '9321098765', '89, BKC, Bandra East',         'Mumbai',    26, '400051', 60, 1, NOW() - INTERVAL 20 DAY),
(1, 'individual',  'Priya Nair',           NULL,                              NULL,              'priya.nair@outlook.com',       '9210987654', '34, MG Road',                  'Kochi',     32, '682016', 15, 1, NOW() - INTERVAL 10 DAY),
(1, 'business',    'Jaipur Handicrafts',   'Jaipur Handicrafts Exports',      '08AABCJ1234F1Z9', 'orders@jaipurhandicrafts.com', '9109876543', '67, Johari Bazaar',            'Jaipur',     8, '302003', 30, 1, NOW() - INTERVAL 5  DAY);

SET @c1 = (SELECT id FROM clients WHERE name = 'Sharma Electronics'  AND business_id = 1 LIMIT 1);
SET @c2 = (SELECT id FROM clients WHERE name = 'Mehta Traders'       AND business_id = 1 LIMIT 1);
SET @c3 = (SELECT id FROM clients WHERE name = 'Patel Industries'    AND business_id = 1 LIMIT 1);
SET @c4 = (SELECT id FROM clients WHERE name = 'Rajesh Kumar'        AND business_id = 1 LIMIT 1);
SET @c5 = (SELECT id FROM clients WHERE name = 'Gupta & Sons'        AND business_id = 1 LIMIT 1);
SET @c6 = (SELECT id FROM clients WHERE name = 'TechStart Solutions' AND business_id = 1 LIMIT 1);
SET @c7 = (SELECT id FROM clients WHERE name = 'Priya Nair'          AND business_id = 1 LIMIT 1);
SET @c8 = (SELECT id FROM clients WHERE name = 'Jaipur Handicrafts'  AND business_id = 1 LIMIT 1);

-- ── INVOICES ──────────────────────────────────────────────────────────────────

-- 1. Sharma Electronics — Web Dev (PAID)
INSERT INTO invoices (business_id,created_by,client_id,number,invoice_type,status,issue_date,due_date,financial_year,supply_type,place_of_supply,subtotal,cgst_total,sgst_total,tax_total,total,amount_paid,amount_due,sent_at,paid_at,created_at) VALUES
(1,2,@c1,'INV-2024-001','tax_invoice','paid','2024-10-01','2024-10-31','2024-25','intra',26,45000.00,4050.00,4050.00,8100.00,53100.00,53100.00,0.00,'2024-10-01 10:00:00','2024-10-28 15:00:00','2024-10-01 09:00:00');
SET @i1=LAST_INSERT_ID();
INSERT INTO invoice_items (invoice_id,description,hsn_sac,unit,quantity,unit_price,taxable_amt,gst_rate,cgst_rate,sgst_rate,cgst_amt,sgst_amt,total,sort_order) VALUES
(@i1,'Web Development — Corporate Website','998314','Project',1.000,45000.00,45000.00,18.00,9.00,9.00,4050.00,4050.00,53100.00,1);

-- 2. Mehta Traders — AMC (SENT / Awaiting Payment)
INSERT INTO invoices (business_id,created_by,client_id,number,invoice_type,status,issue_date,due_date,financial_year,supply_type,place_of_supply,subtotal,igst_total,tax_total,total,amount_paid,amount_due,sent_at,created_at) VALUES
(1,2,@c2,'INV-2024-002','tax_invoice','sent','2024-11-01','2024-11-30','2024-25','inter',24,24000.00,4320.00,4320.00,28320.00,0.00,28320.00,'2024-11-01 10:00:00','2024-11-01 09:00:00');
SET @i2=LAST_INSERT_ID();
INSERT INTO invoice_items (invoice_id,description,hsn_sac,unit,quantity,unit_price,taxable_amt,gst_rate,igst_rate,igst_amt,total,sort_order) VALUES
(@i2,'Annual Maintenance Contract — Software Support','998313','Year',1.000,24000.00,24000.00,18.00,18.00,4320.00,28320.00,1);

-- 3. Patel Industries — Products (OVERDUE)
INSERT INTO invoices (business_id,created_by,client_id,number,invoice_type,status,issue_date,due_date,financial_year,supply_type,place_of_supply,subtotal,igst_total,tax_total,total,amount_paid,amount_due,sent_at,created_at) VALUES
(1,2,@c3,'INV-2024-003','tax_invoice','overdue','2024-10-15','2024-11-14','2024-25','inter',28,7500.00,1350.00,1350.00,8850.00,0.00,8850.00,'2024-10-15 10:00:00','2024-10-15 09:00:00');
SET @i3=LAST_INSERT_ID();
INSERT INTO invoice_items (invoice_id,description,unit,quantity,unit_price,taxable_amt,gst_rate,igst_rate,igst_amt,total,sort_order) VALUES
(@i3,'Laptop Stand — Adjustable Aluminium','Nos',5.000,1500.00,7500.00,18.00,18.00,1350.00,8850.00,1);

-- 4. Rajesh Kumar — SEO (PAID)
INSERT INTO invoices (business_id,created_by,client_id,number,invoice_type,status,issue_date,due_date,financial_year,supply_type,place_of_supply,subtotal,igst_total,tax_total,total,amount_paid,amount_due,sent_at,paid_at,created_at) VALUES
(1,2,@c4,'INV-2024-004','tax_invoice','paid','2024-11-15','2024-11-30','2024-25','inter',32,12000.00,2160.00,2160.00,14160.00,14160.00,0.00,'2024-11-15 10:00:00','2024-11-25 12:00:00','2024-11-15 09:00:00');
SET @i4=LAST_INSERT_ID();
INSERT INTO invoice_items (invoice_id,description,unit,quantity,unit_price,taxable_amt,gst_rate,igst_rate,igst_amt,total,sort_order) VALUES
(@i4,'SEO & Digital Marketing — November 2024','Month',1.000,12000.00,12000.00,18.00,18.00,2160.00,14160.00,1);

-- 5. Gupta & Sons — Multiple items (SENT)
INSERT INTO invoices (business_id,created_by,client_id,number,invoice_type,status,issue_date,due_date,financial_year,supply_type,place_of_supply,subtotal,igst_total,tax_total,total,amount_paid,amount_due,sent_at,created_at) VALUES
(1,2,@c5,'INV-2024-005','tax_invoice','sent','2024-12-01','2024-12-31','2024-25','inter',7,11500.00,2070.00,2070.00,13570.00,0.00,13570.00,'2024-12-01 10:00:00','2024-12-01 09:00:00');
SET @i5=LAST_INSERT_ID();
INSERT INTO invoice_items (invoice_id,description,unit,quantity,unit_price,taxable_amt,gst_rate,igst_rate,igst_amt,total,sort_order) VALUES
(@i5,'Mechanical Keyboard — Wireless','Nos',2.000,3500.00,7000.00,18.00,18.00,1260.00,8260.00,1),
(@i5,'Laptop Stand — Aluminium','Nos',3.000,1500.00,4500.00,18.00,18.00,810.00,5310.00,2);

-- 6. TechStart Solutions — App Dev (PARTIAL)
INSERT INTO invoices (business_id,created_by,client_id,number,invoice_type,status,issue_date,due_date,financial_year,supply_type,place_of_supply,subtotal,cgst_total,sgst_total,tax_total,total,amount_paid,amount_due,sent_at,created_at) VALUES
(1,2,@c6,'INV-2024-006','tax_invoice','partial','2024-12-05','2025-01-04','2024-25','intra',26,80000.00,7200.00,7200.00,14400.00,94400.00,40000.00,54400.00,'2024-12-05 10:00:00','2024-12-05 09:00:00');
SET @i6=LAST_INSERT_ID();
INSERT INTO invoice_items (invoice_id,description,unit,quantity,unit_price,taxable_amt,gst_rate,cgst_rate,sgst_rate,cgst_amt,sgst_amt,total,sort_order) VALUES
(@i6,'Mobile App Development — Phase 1 (Android & iOS)','Project',1.000,80000.00,80000.00,18.00,9.00,9.00,7200.00,7200.00,94400.00,1);

-- 7. Priya Nair — Logo Design (DRAFT)
INSERT INTO invoices (business_id,created_by,client_id,number,invoice_type,status,issue_date,due_date,financial_year,supply_type,place_of_supply,subtotal,igst_total,tax_total,total,amount_paid,amount_due,created_at) VALUES
(1,2,@c7,'INV-2024-007','tax_invoice','draft','2024-12-20','2025-01-19','2024-25','inter',32,8000.00,1440.00,1440.00,9440.00,0.00,9440.00,'2024-12-20 09:00:00');
SET @i7=LAST_INSERT_ID();
INSERT INTO invoice_items (invoice_id,description,unit,quantity,unit_price,taxable_amt,gst_rate,igst_rate,igst_amt,total,sort_order) VALUES
(@i7,'Professional Logo Design + Brand Guidelines','Project',1.000,8000.00,8000.00,18.00,18.00,1440.00,9440.00,1);

-- 8. Jaipur Handicrafts — GST Filing (PAID)
INSERT INTO invoices (business_id,created_by,client_id,number,invoice_type,status,issue_date,due_date,financial_year,supply_type,place_of_supply,subtotal,igst_total,tax_total,total,amount_paid,amount_due,sent_at,paid_at,created_at) VALUES
(1,2,@c8,'INV-2024-008','tax_invoice','paid','2024-12-01','2024-12-31','2024-25','inter',8,7500.00,1350.00,1350.00,8850.00,8850.00,0.00,'2024-12-01 10:00:00','2024-12-20 11:00:00','2024-12-01 09:00:00');
SET @i8=LAST_INSERT_ID();
INSERT INTO invoice_items (invoice_id,description,unit,quantity,unit_price,taxable_amt,gst_rate,igst_rate,igst_amt,total,sort_order) VALUES
(@i8,'GST Return Filing — GSTR-1 & GSTR-3B (Oct–Dec 2024)','Month',3.000,2500.00,7500.00,18.00,18.00,1350.00,8850.00,1);

-- 9. Sharma Electronics — SEO (OVERDUE)
INSERT INTO invoices (business_id,created_by,client_id,number,invoice_type,status,issue_date,due_date,financial_year,supply_type,place_of_supply,subtotal,cgst_total,sgst_total,tax_total,total,amount_paid,amount_due,sent_at,created_at) VALUES
(1,2,@c1,'INV-2024-009','tax_invoice','overdue','2024-10-01','2024-10-31','2024-25','intra',26,12000.00,1080.00,1080.00,2160.00,14160.00,0.00,14160.00,'2024-10-01 10:00:00','2024-10-01 09:00:00');
SET @i9=LAST_INSERT_ID();
INSERT INTO invoice_items (invoice_id,description,unit,quantity,unit_price,taxable_amt,gst_rate,cgst_rate,sgst_rate,cgst_amt,sgst_amt,total,sort_order) VALUES
(@i9,'SEO & Digital Marketing — October 2024','Month',1.000,12000.00,12000.00,18.00,9.00,9.00,1080.00,1080.00,14160.00,1);

-- 10. TechStart Solutions — AMC renewal (SENT)
INSERT INTO invoices (business_id,created_by,client_id,number,invoice_type,status,issue_date,due_date,financial_year,supply_type,place_of_supply,subtotal,cgst_total,sgst_total,tax_total,total,amount_paid,amount_due,sent_at,created_at) VALUES
(1,2,@c6,'INV-2024-010','tax_invoice','sent','2024-12-15','2025-01-14','2024-25','intra',26,24000.00,2160.00,2160.00,4320.00,28320.00,0.00,28320.00,'2024-12-15 10:00:00','2024-12-15 09:00:00');
SET @i10=LAST_INSERT_ID();
INSERT INTO invoice_items (invoice_id,description,unit,quantity,unit_price,taxable_amt,gst_rate,cgst_rate,sgst_rate,cgst_amt,sgst_amt,total,sort_order) VALUES
(@i10,'Annual Maintenance Contract 2025','Year',1.000,24000.00,24000.00,18.00,9.00,9.00,2160.00,2160.00,28320.00,1);

-- ── QUOTES ────────────────────────────────────────────────────────────────────
INSERT INTO quotes (business_id,created_by,client_id,number,type,status,issue_date,valid_until,financial_year,supply_type,place_of_supply,subtotal,igst_total,tax_total,total,notes,created_at) VALUES
(1,2,@c3,'QUO-2024-001','quote','sent',    '2024-12-01','2024-12-31','2024-25','inter',28,80000.00,14400.00,14400.00, 94400.00,'50% advance, 50% on delivery','2024-12-01 09:00:00'),
(1,2,@c5,'QUO-2024-002','quote','accepted','2024-11-15','2024-12-15','2024-25','inter', 7,45000.00, 8100.00, 8100.00, 53100.00,'Includes 3 months post-launch support','2024-11-15 09:00:00'),
(1,2,@c8,'QUO-2024-003','quote','draft',   '2024-12-18','2025-01-17','2024-25','inter', 8,36000.00, 6480.00, 6480.00, 42480.00,NULL,'2024-12-18 09:00:00'),
(1,2,@c7,'QUO-2024-004','proforma','sent', '2024-12-10','2025-01-09','2024-25','inter',32,20000.00, 3600.00, 3600.00, 23600.00,'Valid for 30 days','2024-12-10 09:00:00'),
(1,2,@c2,'QUO-2024-005','quote','expired', '2024-10-01','2024-10-31','2024-25','inter',24,60000.00,10800.00,10800.00, 70800.00,NULL,'2024-10-01 09:00:00');

SET @q1=(SELECT id FROM quotes WHERE number='QUO-2024-001' AND business_id=1 LIMIT 1);
SET @q2=(SELECT id FROM quotes WHERE number='QUO-2024-002' AND business_id=1 LIMIT 1);
SET @q3=(SELECT id FROM quotes WHERE number='QUO-2024-003' AND business_id=1 LIMIT 1);
SET @q4=(SELECT id FROM quotes WHERE number='QUO-2024-004' AND business_id=1 LIMIT 1);
SET @q5=(SELECT id FROM quotes WHERE number='QUO-2024-005' AND business_id=1 LIMIT 1);

INSERT INTO quote_items (quote_id,description,unit,quantity,unit_price,taxable_amt,gst_rate,igst_rate,igst_amt,total,sort_order) VALUES
(@q1,'Mobile App Development — Android & iOS',        'Project',1.000,80000.00,80000.00,18.00,18.00,14400.00,94400.00,1),
(@q2,'Web Development — E-Commerce Portal',           'Project',1.000,45000.00,45000.00,18.00,18.00, 8100.00,53100.00,1),
(@q3,'SEO & Digital Marketing — 3 Months',            'Month',  3.000,12000.00,36000.00,18.00,18.00, 6480.00,42480.00,1),
(@q4,'Logo Design + Brand Identity Package',          'Project',1.000,12000.00,12000.00,18.00,18.00, 2160.00,14160.00,1),
(@q4,'Social Media Kit Design',                       'Project',1.000, 8000.00, 8000.00,18.00,18.00, 1440.00, 9440.00,2),
(@q5,'Mobile App Development — iOS',                  'Project',1.000,60000.00,60000.00,18.00,18.00,10800.00,70800.00,1);

-- ── EXPENSES ──────────────────────────────────────────────────────────────────
SET @cat1=(SELECT id FROM expense_categories WHERE name='Office Supplies'  AND business_id=1 LIMIT 1);
SET @cat2=(SELECT id FROM expense_categories WHERE name='Travel'           AND business_id=1 LIMIT 1);
SET @cat3=(SELECT id FROM expense_categories WHERE name='Software & Tools' AND business_id=1 LIMIT 1);
SET @cat4=(SELECT id FROM expense_categories WHERE name='Marketing'        AND business_id=1 LIMIT 1);
SET @cat5=(SELECT id FROM expense_categories WHERE name='Utilities'        AND business_id=1 LIMIT 1);

INSERT INTO expenses (business_id,category_id,recorded_by,vendor_name,description,amount,gst_amount,total_amount,expense_date,method,financial_year,created_at) VALUES
(1,@cat3,2,'Adobe Inc.',       'Adobe Creative Cloud Annual Subscription', 4237.00, 763.00,5000.00,'2024-12-01','card','2024-25',NOW()-INTERVAL 25 DAY),
(1,@cat2,2,'Ola Cabs',         'Client meeting — travel expenses',          450.00,   0.00, 450.00,'2024-12-05','upi', '2024-25',NOW()-INTERVAL 20 DAY),
(1,@cat5,2,'BSNL Broadband',   'Internet bill — November 2024',            1271.00, 229.00,1500.00,'2024-12-08','upi', '2024-25',NOW()-INTERVAL 17 DAY),
(1,@cat4,2,'Google Ads',       'Google Ads campaign — Q4 2024',            8475.00,1525.00,10000.00,'2024-12-10','card','2024-25',NOW()-INTERVAL 15 DAY),
(1,@cat1,2,'Amazon Business',  'Office stationery and printer cartridges', 1695.00, 305.00,2000.00,'2024-12-12','card','2024-25',NOW()-INTERVAL 13 DAY),
(1,@cat3,2,'JetBrains',        'PhpStorm IDE — Annual License',            3390.00, 610.00,4000.00,'2024-12-15','card','2024-25',NOW()-INTERVAL 10 DAY),
(1,@cat2,2,'IndiGo Airlines',  'Business trip — Mumbai to Delhi return',   8500.00,   0.00,8500.00,'2024-12-18','card','2024-25',NOW()-INTERVAL 7  DAY),
(1,@cat5,2,'Tata Power',        'Electricity bill — November 2024',        3200.00,   0.00,3200.00,'2024-12-20','upi', '2024-25',NOW()-INTERVAL 5  DAY);

-- ── CREDIT NOTES (Returns / Adjustments) ──────────────────────────────────────

-- Re-fetch invoice IDs by number (safe for re-running seed independently)
SET @ri3 = (SELECT id FROM invoices WHERE number = 'INV-2024-003' AND business_id = 1 LIMIT 1);
SET @ri6 = (SELECT id FROM invoices WHERE number = 'INV-2024-006' AND business_id = 1 LIMIT 1);
SET @ri9 = (SELECT id FROM invoices WHERE number = 'INV-2024-009' AND business_id = 1 LIMIT 1);

-- CN-2024-001: Patel Industries returned 2 damaged Laptop Stands from INV-2024-003 (inter-state)
INSERT INTO credit_notes (business_id,created_by,invoice_id,client_id,number,reason,issue_date,supply_type,place_of_supply,subtotal,igst_total,tax_total,total,status,notes,created_at) VALUES
(1,2,@ri3,@c3,'CN-2024-001','return','2024-11-05','inter',28,3000.00,540.00,540.00,3540.00,'issued','2 units returned due to damage in transit. Replacement dispatched separately.','2024-11-05 10:00:00');
SET @cn1=LAST_INSERT_ID();
INSERT INTO credit_note_items (credit_note_id,description,hsn_sac,unit,quantity,unit_price,taxable_amt,gst_rate,igst_amt,total) VALUES
(@cn1,'Laptop Stand — Aluminium (Returned — Damaged)','84733090','Nos',2.000,1500.00,3000.00,18.00,540.00,3540.00);

-- CN-2024-002: TechStart Solutions — Discount correction on INV-2024-006 (intra-state)
INSERT INTO credit_notes (business_id,created_by,invoice_id,client_id,number,reason,issue_date,supply_type,place_of_supply,subtotal,cgst_total,sgst_total,tax_total,total,status,notes,created_at) VALUES
(1,2,@ri6,@c6,'CN-2024-002','discount','2024-12-10','intra',26,5000.00,450.00,450.00,900.00,5900.00,'issued','Loyalty discount of ₹5,000 agreed upon for early project milestone completion.','2024-12-10 11:00:00');
SET @cn2=LAST_INSERT_ID();
INSERT INTO credit_note_items (credit_note_id,description,unit,quantity,unit_price,taxable_amt,gst_rate,cgst_amt,sgst_amt,total) VALUES
(@cn2,'Discount Adjustment — Mobile App Development Phase 1','Project',1.000,5000.00,5000.00,18.00,450.00,450.00,5900.00);

-- CN-2024-003: Sharma Electronics — SEO service cancelled mid-month (intra-state)
INSERT INTO credit_notes (business_id,created_by,invoice_id,client_id,number,reason,issue_date,supply_type,place_of_supply,subtotal,cgst_total,sgst_total,tax_total,total,status,notes,created_at) VALUES
(1,2,@ri9,@c1,'CN-2024-003','return','2024-10-20','intra',26,6000.00,540.00,540.00,1080.00,7080.00,'adjusted','SEO campaign paused at client request after 15 days. Credit for unused 15 days.','2024-10-20 09:30:00');
SET @cn3=LAST_INSERT_ID();
INSERT INTO credit_note_items (credit_note_id,description,unit,quantity,unit_price,taxable_amt,gst_rate,cgst_amt,sgst_amt,total) VALUES
(@cn3,'SEO & Digital Marketing — Partial Month Credit (15 days)','Month',0.500,12000.00,6000.00,18.00,540.00,540.00,7080.00);

-- ── PURCHASE ORDERS ────────────────────────────────────────────────────────────

-- PO-2024-001: Buy Mechanical Keyboards from Sharma Electronics — RECEIVED
INSERT INTO purchase_orders (business_id,created_by,supplier_id,number,status,order_date,expected_date,subtotal,tax_total,total,notes,created_at) VALUES
(1,2,@c1,'PO-2024-001','received','2024-11-01','2024-11-10',17500.00,3150.00,20650.00,'Urgent requirement for office setup. Delivery to BKC office.','2024-11-01 09:00:00');
SET @po1=LAST_INSERT_ID();
INSERT INTO purchase_order_items (po_id,description,hsn_sac,unit,quantity,unit_price,gst_rate,total,sort_order) VALUES
(@po1,'Mechanical Keyboard — Wireless (Cherry MX Blue)','84716060','Nos',5.000,3500.00,18.00,20650.00,1);

-- PO-2024-002: Buy Laptop Stands from Patel Industries — SENT
INSERT INTO purchase_orders (business_id,created_by,supplier_id,number,status,order_date,expected_date,subtotal,tax_total,total,notes,created_at) VALUES
(1,2,@c3,'PO-2024-002','sent','2024-11-20','2024-11-30',15000.00,2700.00,17700.00,'10 units for employee workstation upgrade project.','2024-11-20 10:00:00');
SET @po2=LAST_INSERT_ID();
INSERT INTO purchase_order_items (po_id,description,hsn_sac,unit,quantity,unit_price,gst_rate,total,sort_order) VALUES
(@po2,'Laptop Stand — Adjustable Aluminium (Height & Angle)','84733090','Nos',10.000,1500.00,18.00,17700.00,1);

-- PO-2024-003: Office furniture from Gupta & Sons — RECEIVED
INSERT INTO purchase_orders (business_id,created_by,supplier_id,number,status,order_date,expected_date,subtotal,tax_total,total,notes,created_at) VALUES
(1,2,@c5,'PO-2024-003','received','2024-10-15','2024-10-25',20000.00,2400.00,22400.00,'Office renovation — new workstations. GST 12% on furniture.','2024-10-15 09:00:00');
SET @po3=LAST_INSERT_ID();
INSERT INTO purchase_order_items (po_id,description,hsn_sac,unit,quantity,unit_price,gst_rate,total,sort_order) VALUES
(@po3,'Office Workstation Desk — L-Shape','94033010','Nos',4.000,3500.00,12.00,15680.00,1),
(@po3,'Ergonomic Office Chair','94012000','Nos',4.000,1500.00,12.00,6720.00,2);

-- PO-2024-004: Cloud hosting from TechStart Solutions — DRAFT
INSERT INTO purchase_orders (business_id,created_by,supplier_id,number,status,order_date,expected_date,subtotal,tax_total,total,notes,created_at) VALUES
(1,2,@c6,'PO-2024-004','draft','2024-12-18','2025-01-18',36000.00,6480.00,42480.00,'Annual cloud infrastructure renewal — AWS equivalent. Pending approval from director.','2024-12-18 11:00:00');
SET @po4=LAST_INSERT_ID();
INSERT INTO purchase_order_items (po_id,description,hsn_sac,unit,quantity,unit_price,gst_rate,total,sort_order) VALUES
(@po4,'Cloud Hosting — Business Plan (Annual)','998316','Year',1.000,24000.00,18.00,28320.00,1),
(@po4,'SSL Certificate + CDN — Annual','998313','Year',1.000,12000.00,18.00,14160.00,2);

-- PO-2024-005: Handicraft samples from Jaipur Handicrafts — CANCELLED
INSERT INTO purchase_orders (business_id,created_by,supplier_id,number,status,order_date,expected_date,subtotal,tax_total,total,notes,created_at) VALUES
(1,2,@c8,'PO-2024-005','cancelled','2024-10-05','2024-10-20',25000.00,3000.00,28000.00,'Office decor samples. Cancelled as budget was reallocated.','2024-10-05 10:00:00');
SET @po5=LAST_INSERT_ID();
INSERT INTO purchase_order_items (po_id,description,hsn_sac,unit,quantity,unit_price,gst_rate,total,sort_order) VALUES
(@po5,'Rajasthani Handicraft Wall Art — Large (Assorted)','97011000','Set',5.000,4000.00,12.00,22400.00,1),
(@po5,'Decorative Brass Showpiece (Set of 3)','83062900','Set',5.000,1000.00,12.00,5600.00,2);

-- ── DELIVERY CHALLANS ──────────────────────────────────────────────────────────

-- DC-2024-001: Deliver Mobile App Phase 2 assets to TechStart — DELIVERED
INSERT INTO delivery_challans (business_id,created_by,client_id,number,status,challan_date,vehicle_no,driver_name,destination,notes,created_at) VALUES
(1,2,@c6,'DC-2024-001','delivered','2024-12-10','MH-04-AB-2345','Ramesh Patil','TechStart Solutions, BKC, Mumbai - 400051','Handle with care. USB drives with source code and design assets.','2024-12-10 09:00:00');
SET @dc1=LAST_INSERT_ID();
INSERT INTO delivery_challan_items (dc_id,description,hsn_sac,unit,quantity,sort_order) VALUES
(@dc1,'USB Drive — Project Source Code & Assets','84717090','Nos',2.000,1),
(@dc1,'Printed Documentation — User Manual & API Docs','49111090','Set',1.000,2),
(@dc1,'License Certificate (Printed)','49111090','Nos',1.000,3);

-- DC-2024-002: Deliver Mechanical Keyboards to Sharma Electronics — DELIVERED
INSERT INTO delivery_challans (business_id,created_by,client_id,number,status,challan_date,vehicle_no,driver_name,destination,notes,created_at) VALUES
(1,2,@c1,'DC-2024-002','delivered','2024-11-08','MH-01-CD-5678','Suresh Kumar','Sharma Electronics, Linking Road, Bandra West, Mumbai - 400050','5 keyboards as per PO-2024-001. Handle with care.','2024-11-08 10:00:00');
SET @dc2=LAST_INSERT_ID();
INSERT INTO delivery_challan_items (dc_id,description,hsn_sac,unit,quantity,sort_order) VALUES
(@dc2,'Mechanical Keyboard — Wireless (Cherry MX Blue)','84716060','Nos',5.000,1);

-- DC-2024-003: Deliver Laptop Stands to Patel Industries — ISSUED (in transit)
INSERT INTO delivery_challans (business_id,created_by,client_id,number,status,challan_date,vehicle_no,driver_name,destination,notes,created_at) VALUES
(1,2,@c3,'DC-2024-003','issued','2024-11-28','KA-03-EF-9012','Venkat Reddy','Patel Industries, Industrial Area, Peenya, Bengaluru - 560058','10 laptop stands as per PO-2024-002. Fragile — do not stack.','2024-11-28 08:30:00');
SET @dc3=LAST_INSERT_ID();
INSERT INTO delivery_challan_items (dc_id,description,hsn_sac,unit,quantity,sort_order) VALUES
(@dc3,'Laptop Stand — Adjustable Aluminium','84733090','Nos',10.000,1);

-- DC-2024-004: Deliver SEO Report & Materials to Rajesh Kumar — ISSUED
INSERT INTO delivery_challans (business_id,created_by,client_id,number,status,challan_date,vehicle_no,driver_name,destination,notes,created_at) VALUES
(1,2,@c4,'DC-2024-004','issued','2024-12-01','TN-09-GH-3456','Muthu Selvam','Rajesh Kumar, Anna Nagar 4th Street, Chennai - 600040','Monthly SEO reports and access credentials document.','2024-12-01 11:00:00');
SET @dc4=LAST_INSERT_ID();
INSERT INTO delivery_challan_items (dc_id,description,unit,quantity,sort_order) VALUES
(@dc4,'SEO Performance Report — November 2024 (Printed)','Set',1.000,1),
(@dc4,'Keyword Research Document (USB Copy)','Nos',1.000,2);

-- DC-2024-005: New DC for Gupta & Sons — DRAFT
INSERT INTO delivery_challans (business_id,created_by,client_id,number,status,challan_date,destination,notes,created_at) VALUES
(1,2,@c5,'DC-2024-005','draft','2024-12-22','Gupta & Sons, Karol Bagh Market, New Delhi - 110005','Keyboard and stand bundle as per invoice INV-2024-005. Awaiting dispatch clearance.','2024-12-22 14:00:00');
SET @dc5=LAST_INSERT_ID();
INSERT INTO delivery_challan_items (dc_id,description,hsn_sac,unit,quantity,sort_order) VALUES
(@dc5,'Mechanical Keyboard — Wireless','84716060','Nos',2.000,1),
(@dc5,'Laptop Stand — Aluminium','84733090','Nos',3.000,2);

-- ── SEQUENCES (so next invoice/quote picks up correct number) ─────────────────
INSERT INTO sequences (business_id, type, financial_year, prefix, next_number, padding)
VALUES
  (1, 'invoice',          '2024-25', 'INV', 11, 4),
  (1, 'quote',            '2024-25', 'QUO',  6, 4),
  (1, 'credit_note',      '2024-25', 'CN',   4, 4),
  (1, 'purchase_order',   '2024-25', 'PO',   6, 4),
  (1, 'delivery_challan', '2024-25', 'DC',   6, 4)
ON DUPLICATE KEY UPDATE next_number = VALUES(next_number);

SET FOREIGN_KEY_CHECKS = 1;
