<?php
// cajun_wp/readme.txt
// Kurulum ve tablo yapısı açıklaması

/*
Bütçe Yönetimi (WordPress Modülü)

Tablo Yapıları:
- cajun_transactions: id, date, type, category, description, amount, firm_id, payment_type
- cajun_employees: employee_id, name, title, base_salary
- cajun_salaries: id, date, employee_id, employee_name, gross_amount, notes
- cajun_firms: firm_id, firm_name, balance

Kurulum:
1. cajun_wp klasörünü WordPress'in wp-content/plugins dizinine kopyalayın.
2. WordPress admin panelinden "Cajun Bütçe Yönetimi" eklentisini etkinleştirin.
3. Eklenti etkinleşince veritabanı tabloları otomatik oluşur.

Kullanım:
- Admin panelinde "Bütçe Yönetimi" menüsünden tüm işlemler yapılabilir.
*/
