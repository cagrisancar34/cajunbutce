<?php
/*
Plugin Name: Cajun Bütçe Yönetimi
Description: Excel tabanlı bütçe yönetimi uygulamasının WordPress admin paneli için modülüdür.
Version: 1.0
Author: GitHub Copilot
*/

// Tablo kurulum fonksiyonu
function cajunbutce_install() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // transactions
    $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cajun_transactions (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        type VARCHAR(16) NOT NULL,
        category VARCHAR(64),
        description TEXT,
        amount DECIMAL(15,2) NOT NULL,
        firm_id BIGINT,
        payment_type VARCHAR(32)
    ) $charset_collate;";

    // employees
    $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cajun_employees (
        employee_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(128) NOT NULL,
        title VARCHAR(64),
        base_salary DECIMAL(15,2) NOT NULL
    ) $charset_collate;";

    // salaries
    $sql3 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cajun_salaries (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        employee_id BIGINT NOT NULL,
        employee_name VARCHAR(128),
        gross_amount DECIMAL(15,2) NOT NULL,
        notes TEXT
    ) $charset_collate;";

    // firms
    $sql4 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cajun_firms (
        firm_id BIGINT AUTO_INCREMENT PRIMARY KEY,
        firm_name VARCHAR(128) NOT NULL,
        balance DECIMAL(15,2) NOT NULL
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
    dbDelta($sql4);
}
register_activation_hook(__FILE__, 'cajunbutce_install');

// Admin menüsü
add_action('admin_menu', function() {
    add_menu_page('Bütçe Yönetimi', 'Bütçe Yönetimi', 'manage_options', 'cajunbutce', 'cajunbutce_dashboard', 'dashicons-chart-area', 3);
    add_submenu_page('cajunbutce', 'Gelir/Gider', 'Gelir/Gider', 'manage_options', 'cajunbutce_transactions', 'cajunbutce_transactions');
    add_submenu_page('cajunbutce', 'Personel', 'Personel', 'manage_options', 'cajunbutce_employees', 'cajunbutce_employees');
    add_submenu_page('cajunbutce', 'Maaş', 'Maaş', 'manage_options', 'cajunbutce_salaries', 'cajunbutce_salaries');
    add_submenu_page('cajunbutce', 'Firmalar', 'Firmalar', 'manage_options', 'cajunbutce_firms', 'cajunbutce_firms');
});

function cajunbutce_dashboard() {
    echo '<div class="wrap"><h1>Bütçe Yönetimi</h1><p>Sol menüden modülleri seçebilirsiniz.</p></div>';
}

// Her modül için basit listeleme (CRUD formları eklenebilir)
function cajunbutce_transactions() {
    global $wpdb;
    $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cajun_transactions ORDER BY date DESC LIMIT 50");
    echo '<div class="wrap"><h2>Gelir/Gider</h2><table class="widefat"><thead><tr><th>Tarih</th><th>Tür</th><th>Kategori</th><th>Açıklama</th><th>Tutar</th><th>Firma</th><th>Ödeme Tipi</th></tr></thead><tbody>';
    foreach($rows as $r) {
        echo "<tr><td>{$r->date}</td><td>{$r->type}</td><td>{$r->category}</td><td>{$r->description}</td><td>{$r->amount}</td><td>{$r->firm_id}</td><td>{$r->payment_type}</td></tr>";
    }
    echo '</tbody></table></div>';
}
function cajunbutce_employees() {
    global $wpdb;
    $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cajun_employees ORDER BY employee_id DESC LIMIT 50");
    echo '<div class="wrap"><h2>Personel</h2><table class="widefat"><thead><tr><th>ID</th><th>Ad</th><th>Ünvan</th><th>Maaş</th></tr></thead><tbody>';
    foreach($rows as $r) {
        echo "<tr><td>{$r->employee_id}</td><td>{$r->name}</td><td>{$r->title}</td><td>{$r->base_salary}</td></tr>";
    }
    echo '</tbody></table></div>';
}
function cajunbutce_salaries() {
    global $wpdb;
    $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cajun_salaries ORDER BY date DESC LIMIT 50");
    echo '<div class="wrap"><h2>Maaş</h2><table class="widefat"><thead><tr><th>Tarih</th><th>Personel</th><th>Tutar</th><th>Not</th></tr></thead><tbody>';
    foreach($rows as $r) {
        echo "<tr><td>{$r->date}</td><td>{$r->employee_name}</td><td>{$r->gross_amount}</td><td>{$r->notes}</td></tr>";
    }
    echo '</tbody></table></div>';
}
function cajunbutce_firms() {
    global $wpdb;
    $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cajun_firms ORDER BY firm_id DESC LIMIT 50");
    echo '<div class="wrap"><h2>Firmalar</h2><table class="widefat"><thead><tr><th>ID</th><th>Ad</th><th>Bakiye</th></tr></thead><tbody>';
    foreach($rows as $r) {
        echo "<tr><td>{$r->firm_id}</td><td>{$r->firm_name}</td><td>{$r->balance}</td></tr>";
    }
    echo '</tbody></table></div>';
}

// Gelişmiş formlar ve CRUD işlemleri eklenebilir.
