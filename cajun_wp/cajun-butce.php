<?php
/*
Plugin Name: Cajun Bütçe Yönetimi
Description: Excel tabanlı bütçe yönetimi uygulamasının WordPress admin paneli için modül uyarlaması. Gelir, gider, personel, maaş ve firma yönetimi.
Version: 1.0
Author: GitHub Copilot
*/

if (!defined('ABSPATH')) exit;

class CajunButceYonetimi {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        register_activation_hook(__FILE__, [$this, 'install_tables']);
    }

    public function add_admin_menu() {
        add_menu_page('Bütçe Yönetimi', 'Bütçe Yönetimi', 'manage_options', 'cajun-butce', [$this, 'dashboard_page'], 'dashicons-chart-pie', 3);
        add_submenu_page('cajun-butce', 'Gelir/Gider', 'Gelir/Gider', 'manage_options', 'cajun-butce-transactions', [$this, 'transactions_page']);
        add_submenu_page('cajun-butce', 'Personel', 'Personel', 'manage_options', 'cajun-butce-employees', [$this, 'employees_page']);
        add_submenu_page('cajun-butce', 'Firmalar', 'Firmalar', 'manage_options', 'cajun-butce-firms', [$this, 'firms_page']);
        add_submenu_page('cajun-butce', 'Maaşlar', 'Maaşlar', 'manage_options', 'cajun-butce-salaries', [$this, 'salaries_page']);
    }

    public function install_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $charset_collate = $wpdb->get_charset_collate();
        $tables = [];
        $tables[] = "CREATE TABLE {$wpdb->prefix}cajun_transactions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            type VARCHAR(20) NOT NULL,
            category VARCHAR(100),
            description TEXT,
            amount DECIMAL(18,2) NOT NULL,
            firm_id BIGINT,
            payment_type VARCHAR(50)
        ) $charset_collate;";
        $tables[] = "CREATE TABLE {$wpdb->prefix}cajun_employees (
            employee_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            title VARCHAR(100),
            base_salary DECIMAL(18,2) NOT NULL
        ) $charset_collate;";
        $tables[] = "CREATE TABLE {$wpdb->prefix}cajun_salaries (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            employee_id BIGINT NOT NULL,
            employee_name VARCHAR(100),
            gross_amount DECIMAL(18,2) NOT NULL,
            notes TEXT
        ) $charset_collate;";
        $tables[] = "CREATE TABLE {$wpdb->prefix}cajun_firms (
            firm_id BIGINT AUTO_INCREMENT PRIMARY KEY,
            firm_name VARCHAR(100) NOT NULL,
            balance DECIMAL(18,2) NOT NULL
        ) $charset_collate;";
        foreach ($tables as $sql) {
            dbDelta($sql);
        }
    }

    public function dashboard_page() {
        global $wpdb;
        echo '<div class="wrap"><h1>Bütçe Yönetimi</h1>';
        // Toplamlar
        $total_income = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}cajun_transactions WHERE type='income'");
        $total_expense = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}cajun_transactions WHERE type='expense'");
        $total_salaries = $wpdb->get_var("SELECT SUM(gross_amount) FROM {$wpdb->prefix}cajun_salaries");
        $net = floatval($total_income) - (floatval($total_expense) + floatval($total_salaries));
        $employees_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cajun_employees");
        $firms_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cajun_firms");
        echo '<div class="card" style="max-width:600px;padding:20px;margin-bottom:20px;">';
        echo '<h2>Genel Bakış</h2>';
        echo '<ul><li><strong>Toplam Gelir:</strong> '.cajun_currency_fmt($total_income).' ₺</li>';
        echo '<li><strong>Toplam Gider:</strong> '.cajun_currency_fmt($total_expense).' ₺</li>';
        echo '<li><strong>Toplam Maaş:</strong> '.cajun_currency_fmt($total_salaries).' ₺</li>';
        echo '<li><strong>Net:</strong> '.cajun_currency_fmt($net).' ₺</li>';
        echo '<li><strong>Personel Sayısı:</strong> '.$employees_count.'</li>';
        echo '<li><strong>Firma Sayısı:</strong> '.$firms_count.'</li></ul>';
        echo '</div>';
        // Son 10 hareket
        $recent = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cajun_transactions ORDER BY date DESC LIMIT 10");
        echo '<h2>Son 10 Hareket</h2><table class="widefat"><thead><tr><th>Tarih</th><th>Tür</th><th>Kategori</th><th>Açıklama</th><th>Tutar</th></tr></thead><tbody>';
        foreach ($recent as $r) {
            echo '<tr><td>'.$r->date.'</td><td>'.$r->type.'</td><td>'.$r->category.'</td><td>'.$r->description.'</td><td>'.cajun_currency_fmt($r->amount).'</td></tr>';
        }
        echo '</tbody></table>';
        // Tarih aralığına göre özet
        echo '<h2>Tarih Aralığına Göre Rapor</h2>';
        echo '<form method="post"><label>Başlangıç Tarihi: <input type="date" name="start_date"></label> <label>Bitiş Tarihi: <input type="date" name="end_date"></label> <input type="submit" name="cajun_report" class="button" value="Filtrele"></form>';
        if (isset($_POST['cajun_report']) && $_POST['start_date'] && $_POST['end_date']) {
            $start = esc_sql($_POST['start_date']);
            $end = esc_sql($_POST['end_date']);
            $period_income = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}cajun_transactions WHERE type='income' AND date >= '$start' AND date <= '$end'");
            $period_expense = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}cajun_transactions WHERE type='expense' AND date >= '$start' AND date <= '$end'");
            $period_salary = $wpdb->get_var("SELECT SUM(gross_amount) FROM {$wpdb->prefix}cajun_salaries WHERE date >= '$start' AND date <= '$end'");
            $period_net = floatval($period_income) - (floatval($period_expense) + floatval($period_salary));
            echo '<div class="card" style="max-width:600px;padding:20px;margin-top:20px;">';
            echo '<h3>Seçilen Tarih Aralığı Özet</h3>';
            echo '<ul><li><strong>Gelir:</strong> '.cajun_currency_fmt($period_income).' ₺</li>';
            echo '<li><strong>Gider:</strong> '.cajun_currency_fmt($period_expense).' ₺</li>';
            echo '<li><strong>Maaş:</strong> '.cajun_currency_fmt($period_salary).' ₺</li>';
            echo '<li><strong>Net:</strong> '.cajun_currency_fmt($period_net).' ₺</li></ul>';
            echo '</div>';
        }
        echo '</div>';
    }

    public function transactions_page() {
        global $wpdb;
        echo '<div class="wrap"><h1>Gelir/Gider</h1>';
        // Ekleme Formu
        echo '<form method="post"><table class="form-table"><tr><th>Tarih</th><td><input type="date" name="date" required></td></tr><tr><th>Tür</th><td><select name="type"><option value="income">Gelir</option><option value="expense">Gider</option></select></td></tr><tr><th>Kategori</th><td><input type="text" name="category"></td></tr><tr><th>Açıklama</th><td><input type="text" name="description"></td></tr><tr><th>Tutar</th><td><input type="number" step="0.01" name="amount" required></td></tr><tr><th>Firma</th><td><input type="number" name="firm_id"></td></tr><tr><th>Ödeme Tipi</th><td><input type="text" name="payment_type"></td></tr></table><p><input type="submit" name="cajun_add_tx" class="button-primary" value="Kaydet"></p></form>';
        // Ekleme işlemi
        if (isset($_POST['cajun_add_tx'])) {
            $wpdb->insert($wpdb->prefix.'cajun_transactions', [
                'date' => $_POST['date'],
                'type' => $_POST['type'],
                'category' => $_POST['category'],
                'description' => $_POST['description'],
                'amount' => $_POST['amount'],
                'firm_id' => $_POST['firm_id'],
                'payment_type' => $_POST['payment_type'],
            ]);
            echo '<div class="updated"><p>Kayıt eklendi.</p></div>';
        }
        // Silme işlemi
        if (isset($_GET['delete_tx'])) {
            $wpdb->delete($wpdb->prefix.'cajun_transactions', ['id' => intval($_GET['delete_tx'])]);
            echo '<div class="updated"><p>Kayıt silindi.</p></div>';
        }
        // Listeleme
        $rows = $wpdb->get_results('SELECT * FROM '.$wpdb->prefix.'cajun_transactions ORDER BY date DESC');
        echo '<h2>Son Hareketler</h2><table class="widefat"><thead><tr><th>Tarih</th><th>Tür</th><th>Kategori</th><th>Açıklama</th><th>Tutar</th><th>Firma</th><th>Ödeme Tipi</th><th>İşlem</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td>'.$r->date.'</td><td>'.$r->type.'</td><td>'.$r->category.'</td><td>'.$r->description.'</td><td>'.cajun_currency_fmt($r->amount).'</td><td>'.$r->firm_id.'</td><td>'.$r->payment_type.'</td><td><a href="?page=cajun-butce-transactions&delete_tx='.$r->id.'" onclick="return confirm(\'Silmek istediğinize emin misiniz?\');">Sil</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function employees_page() {
        global $wpdb;
        echo '<div class="wrap"><h1>Personel</h1>';
        // Ekleme Formu
        echo '<form method="post"><table class="form-table"><tr><th>Ad Soyad</th><td><input type="text" name="name" required></td></tr><tr><th>Ünvan</th><td><input type="text" name="title"></td></tr><tr><th>Maaş</th><td><input type="number" step="0.01" name="base_salary" required></td></tr></table><p><input type="submit" name="cajun_add_emp" class="button-primary" value="Ekle"></p></form>';
        // Ekleme işlemi
        if (isset($_POST['cajun_add_emp'])) {
            $wpdb->insert($wpdb->prefix.'cajun_employees', [
                'name' => $_POST['name'],
                'title' => $_POST['title'],
                'base_salary' => $_POST['base_salary'],
            ]);
            echo '<div class="updated"><p>Personel eklendi.</p></div>';
        }
        // Silme işlemi
        if (isset($_GET['delete_emp'])) {
            $wpdb->delete($wpdb->prefix.'cajun_employees', ['employee_id' => intval($_GET['delete_emp'])]);
            echo '<div class="updated"><p>Personel silindi.</p></div>';
        }
        // Listeleme
        $rows = $wpdb->get_results('SELECT * FROM '.$wpdb->prefix.'cajun_employees ORDER BY employee_id DESC');
        echo '<h2>Kayıtlı Personel</h2><table class="widefat"><thead><tr><th>No</th><th>Ad</th><th>Ünvan</th><th>Maaş</th><th>İşlem</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td>'.$r->employee_id.'</td><td>'.$r->name.'</td><td>'.$r->title.'</td><td>'.cajun_currency_fmt($r->base_salary).'</td><td><a href="?page=cajun-butce-employees&delete_emp='.$r->employee_id.'" onclick="return confirm(\'Silmek istediğinize emin misiniz?\');">Sil</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function firms_page() {
        global $wpdb;
        echo '<div class="wrap"><h1>Firmalar</h1>';
        // Ekleme Formu
        echo '<form method="post"><table class="form-table"><tr><th>Firma Adı</th><td><input type="text" name="firm_name" required></td></tr><tr><th>Bakiye</th><td><input type="number" step="0.01" name="balance" required></td></tr></table><p><input type="submit" name="cajun_add_firm" class="button-primary" value="Ekle"></p></form>';
        // Ekleme işlemi
        if (isset($_POST['cajun_add_firm'])) {
            $wpdb->insert($wpdb->prefix.'cajun_firms', [
                'firm_name' => $_POST['firm_name'],
                'balance' => $_POST['balance'],
            ]);
            echo '<div class="updated"><p>Firma eklendi.</p></div>';
        }
        // Silme işlemi
        if (isset($_GET['delete_firm'])) {
            $wpdb->delete($wpdb->prefix.'cajun_firms', ['firm_id' => intval($_GET['delete_firm'])]);
            echo '<div class="updated"><p>Firma silindi.</p></div>';
        }
        // Listeleme
        $rows = $wpdb->get_results('SELECT * FROM '.$wpdb->prefix.'cajun_firms ORDER BY firm_id DESC');
        echo '<h2>Kayıtlı Firmalar</h2><table class="widefat"><thead><tr><th>ID</th><th>Ad</th><th>Bakiye</th><th>İşlem</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td>'.$r->firm_id.'</td><td>'.$r->firm_name.'</td><td>'.cajun_currency_fmt($r->balance).'</td><td><a href="?page=cajun-butce-firms&delete_firm='.$r->firm_id.'" onclick="return confirm(\'Silmek istediğinize emin misiniz?\');">Sil</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function salaries_page() {
        global $wpdb;
        // Personel listesi
        $employees = $wpdb->get_results('SELECT employee_id, name FROM '.$wpdb->prefix.'cajun_employees');
        echo '<div class="wrap"><h1>Maaşlar</h1>';
        // Ekleme Formu
        echo '<form method="post"><table class="form-table"><tr><th>Tarih</th><td><input type="date" name="date" required></td></tr><tr><th>Personel</th><td><select name="employee_id">';
        foreach ($employees as $e) {
            echo '<option value="'.$e->employee_id.'">'.$e->employee_id.' - '.$e->name.'</option>';
        }
        echo '</select></td></tr><tr><th>Brüt Tutar</th><td><input type="number" step="0.01" name="gross_amount" required></td></tr><tr><th>Not</th><td><input type="text" name="notes"></td></tr></table><p><input type="submit" name="cajun_add_salary" class="button-primary" value="Kaydet"></p></form>';
        // Ekleme işlemi
        if (isset($_POST['cajun_add_salary'])) {
            $emp_row = $wpdb->get_row($wpdb->prepare('SELECT name FROM '.$wpdb->prefix.'cajun_employees WHERE employee_id=%d', $_POST['employee_id']));
            $employee_name = $emp_row ? $emp_row->name : '';
            $wpdb->insert($wpdb->prefix.'cajun_salaries', [
                'date' => $_POST['date'],
                'employee_id' => $_POST['employee_id'],
                'employee_name' => $employee_name,
                'gross_amount' => $_POST['gross_amount'],
                'notes' => $_POST['notes'],
            ]);
            echo '<div class="updated"><p>Maaş kaydı eklendi.</p></div>';
        }
        // Silme işlemi
        if (isset($_GET['delete_salary'])) {
            $wpdb->delete($wpdb->prefix.'cajun_salaries', ['id' => intval($_GET['delete_salary'])]);
            echo '<div class="updated"><p>Maaş kaydı silindi.</p></div>';
        }
        // Listeleme
        $rows = $wpdb->get_results('SELECT * FROM '.$wpdb->prefix.'cajun_salaries ORDER BY date DESC');
        echo '<h2>Ödeme Geçmişi</h2><table class="widefat"><thead><tr><th>Tarih</th><th>Personel</th><th>Tutar</th><th>Not</th><th>İşlem</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr><td>'.$r->date.'</td><td>'.$r->employee_id.' - '.$r->employee_name.'</td><td>'.cajun_currency_fmt($r->gross_amount).'</td><td>'.$r->notes.'</td><td><a href="?page=cajun-butce-salaries&delete_salary='.$r->id.'" onclick="return confirm(\'Silmek istediğinize emin misiniz?\');">Sil</a></td></tr>';
        }
        echo '</tbody></table></div>';
    }
}

new CajunButceYonetimi();
