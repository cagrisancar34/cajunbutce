<?php
/*
Plugin Name: Budget Excel Webapp
Description: Budget management system migrated from Python/Flask to WordPress plugin. Handles firms, employees, transactions, and salaries.
Version: 1.0
Author: Your Name
*/

// Activation hook: create tables if not exist
register_activation_hook(__FILE__, 'budget_excel_create_tables');
function budget_excel_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Firms table
    $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}firms (
        firm_id VARCHAR(64) NOT NULL PRIMARY KEY,
        firm_name VARCHAR(255) NOT NULL,
        balance FLOAT DEFAULT 0
    ) $charset_collate;";

    // Employees table
    $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}employees (
        employee_id VARCHAR(64) NOT NULL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        title VARCHAR(255),
        base_salary FLOAT DEFAULT 0
    ) $charset_collate;";

    // Transactions table
    $sql3 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}transactions (
        id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        type VARCHAR(32) NOT NULL,
        category VARCHAR(255),
        description TEXT,
        amount FLOAT DEFAULT 0,
        firm_id VARCHAR(64),
        payment_type VARCHAR(64)
    ) $charset_collate;";

    // Salaries table
    $sql4 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}salaries (
        id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        employee_id VARCHAR(64) NOT NULL,
        employee_name VARCHAR(255),
        gross_amount FLOAT DEFAULT 0,
        notes TEXT
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
    dbDelta($sql4);
}

// Tüm veritabanı tablolarını temizlemek için fonksiyon
function budget_excel_clear_all_data() {
    global $wpdb;
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}firms");
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}employees");
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}transactions");
    $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}salaries");
    echo '<div class="notice notice-success">Tüm veriler başarıyla temizlendi!</div>';
}

// Yönetici için bir buton ekle
add_action('admin_notices', function() {
    if (isset($_GET['clear_budget_data']) && current_user_can('manage_options')) {
        budget_excel_clear_all_data();
    }
    if (current_user_can('manage_options')) {
        echo '<div class="notice notice-warning"><form method="get" style="display:inline;"><input type="hidden" name="clear_budget_data" value="1"><button type="submit" class="button button-danger" onclick="return confirm(\'Tüm veriler silinecek. Emin misiniz?\')">Bütçe Verilerini Temizle</button></form></div>';
    }
});

// Main page
function budget_excel_main_page() {
    echo '<h1>Budget Excel Webapp</h1>';
    echo '<p>Welcome to the budget management system plugin.</p>';
}

function budget_excel_firms_page() {
    include_once __DIR__ . '/firms.php';
}
function budget_excel_employees_page() {
    include_once __DIR__ . '/employees.php';
}
function budget_excel_income_page() {
    include_once __DIR__ . '/income.php';
}
function budget_excel_expense_page() {
    include_once __DIR__ . '/expense.php';
}
function budget_excel_transactions_page() {
    include_once __DIR__ . '/transactions.php';
}
function budget_excel_salaries_page() {
    include_once __DIR__ . '/salaries.php';
}

add_action('admin_menu', function() {
    add_menu_page('Bütçe Yönetimi', 'Bütçe Yönetimi', 'manage_options', 'budget-excel-main', 'budget_excel_main_page');
    add_submenu_page('budget-excel-main', 'Firmalar', 'Firmalar', 'manage_options', 'budget-excel-firms', 'budget_excel_firms_page');
    add_submenu_page('budget-excel-main', 'Personeller', 'Personeller', 'manage_options', 'budget-excel-employees', 'budget_excel_employees_page');
    add_submenu_page('budget-excel-main', 'Gelir Ekle', 'Gelir Ekle', 'manage_options', 'budget-excel-income', 'budget_excel_income_page');
    add_submenu_page('budget-excel-main', 'Gider Ekle', 'Gider Ekle', 'manage_options', 'budget-excel-expense', 'budget_excel_expense_page');
    add_submenu_page('budget-excel-main', 'İşlemler', 'İşlemler', 'manage_options', 'budget-excel-transactions', 'budget_excel_transactions_page');
    add_submenu_page('budget-excel-main', 'Maaşlar', 'Maaşlar', 'manage_options', 'budget-excel-salaries', 'budget_excel_salaries_page');
});

// Her sayfa için shortcode tanımları
add_shortcode('budget_excel_firms', function() {
    ob_start();
    include_once __DIR__ . '/firms.php';
    return ob_get_clean();
});
add_shortcode('budget_excel_employees', function() {
    ob_start();
    include_once __DIR__ . '/employees.php';
    return ob_get_clean();
});
add_shortcode('budget_excel_income', function() {
    ob_start();
    include_once __DIR__ . '/income.php';
    return ob_get_clean();
});
add_shortcode('budget_excel_expense', function() {
    ob_start();
    include_once __DIR__ . '/expense.php';
    return ob_get_clean();
});
add_shortcode('budget_excel_transactions', function() {
    ob_start();
    include_once __DIR__ . '/transactions.php';
    return ob_get_clean();
});
add_shortcode('budget_excel_salaries', function() {
    ob_start();
    include_once __DIR__ . '/salaries.php';
    return ob_get_clean();
});
?>
