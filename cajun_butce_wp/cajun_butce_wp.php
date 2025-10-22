<?php
/*
Plugin Name: Cajun Bütçe WP
Description: Excel tabanlı bütçe yönetimi sistemini WordPress üzerinde sekmeli ve tek sayfa olarak kullanmanızı sağlar.
Version: 1.0
Author: GitHub Copilot
*/

// Eklenti etkinleştirildiğinde veritabanı tablolarını oluştur
register_activation_hook(__FILE__, 'cajun_butce_wp_create_tables');
function cajun_butce_wp_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $tables = [
        'transactions' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cajun_transactions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            type VARCHAR(20) NOT NULL,
            category VARCHAR(100),
            description TEXT,
            amount DECIMAL(15,2) NOT NULL,
            firm_id VARCHAR(50),
            payment_type VARCHAR(50)
        ) $charset_collate;",
        'employees' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cajun_employees (
            employee_id VARCHAR(50) PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            title VARCHAR(100),
            base_salary DECIMAL(15,2)
        ) $charset_collate;",
        'salaries' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cajun_salaries (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL,
            employee_id VARCHAR(50) NOT NULL,
            employee_name VARCHAR(100),
            gross_amount DECIMAL(15,2),
            notes TEXT
        ) $charset_collate;",
        'firms' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cajun_firms (
            firm_id VARCHAR(50) PRIMARY KEY,
            firm_name VARCHAR(100),
            balance DECIMAL(15,2)
        ) $charset_collate;"
    ];

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
}

// Bootstrap CSS ekle
add_action('wp_head', function() {
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">';
});

// Bootstrap JS ekle (önce jQuery, sonra Popper, sonra Bootstrap JS)
add_action('wp_footer', function() {
    echo '<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>';
});

// Kısa kod ile tek sayfa sekmeli arayüzü göster
add_shortcode('cajun_butce_wp', 'cajun_butce_wp_render');
function cajun_butce_wp_render() {
    ob_start();
    ?>
    <div id="cajun-butce-wp">
        <ul class="cajun-tabs">
            <li><a href="#tab-dashboard">Dashboard</a></li>
            <li><a href="#tab-transactions">İşlemler</a></li>
            <li><a href="#tab-income">Gelir Ekle</a></li>
            <li><a href="#tab-expense">Gider Ekle</a></li>
            <li><a href="#tab-employees">Personeller</a></li>
            <li><a href="#tab-salaries">Maaşlar</a></li>
            <li><a href="#tab-firms">Firmalar</a></li>
        </ul>
        <div class="cajun-tab-content" id="tab-dashboard">
            <?php echo cajun_butce_wp_dashboard(); ?>
        </div>
        <div class="cajun-tab-content" id="tab-transactions" style="display:none;">
            <?php echo cajun_butce_wp_transactions(); ?>
        </div>
        <div class="cajun-tab-content" id="tab-income" style="display:none;">
            <?php echo cajun_butce_wp_income_form(); ?>
        </div>
        <div class="cajun-tab-content" id="tab-expense" style="display:none;">
            <?php echo cajun_butce_wp_expense_form(); ?>
        </div>
        <div class="cajun-tab-content" id="tab-employees" style="display:none;">
            <?php echo cajun_butce_wp_employees(); ?>
        </div>
        <div class="cajun-tab-content" id="tab-salaries" style="display:none;">
            <?php echo cajun_butce_wp_salaries(); ?>
        </div>
        <div class="cajun-tab-content" id="tab-firms" style="display:none;">
            <?php echo cajun_butce_wp_firms(); ?>
        </div>
    </div>
    <style>
        .cajun-tabs { list-style:none; padding:0; display:flex; gap:10px; }
        .cajun-tabs li { display:inline; }
        .cajun-tabs a { padding:8px 16px; background:#eee; border-radius:4px; text-decoration:none; }
        .cajun-tabs a.active { background:#0073aa; color:#fff; }
        .cajun-tab-content { margin-top:20px; }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.cajun-tabs a');
        const contents = document.querySelectorAll('.cajun-tab-content');
        function showTab(tabId) {
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.style.display = 'none');
            const activeTab = document.querySelector('.cajun-tabs a[href="' + tabId + '"]');
            if (activeTab) activeTab.classList.add('active');
            const activeContent = document.querySelector(tabId);
            if (activeContent) activeContent.style.display = 'block';
        }
        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                showTab(tab.getAttribute('href'));
            });
        });
        // Hash ile sekme açma desteği
        if (window.location.hash) {
            showTab(window.location.hash);
        } else {
            showTab('#tab-dashboard');
        }
        window.addEventListener('hashchange', function() {
            showTab(window.location.hash);
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

// Dashboard sekmesi
function cajun_butce_wp_dashboard() {
    ob_start();
    global $wpdb;
    // Toplamlar
    $total_income = floatval($wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}cajun_transactions WHERE type='income'"));
    $total_expense = floatval($wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}cajun_transactions WHERE type='expense'"));
    $total_salaries = floatval($wpdb->get_var("SELECT SUM(gross_amount) FROM {$wpdb->prefix}cajun_salaries"));
    $net = $total_income - ($total_expense + $total_salaries);
    $employees_count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cajun_employees"));

    // Tarih aralığı
    $today = date('Y-m-d');
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-01');
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : $today;

    // Dönem özetleri
    $period_income = floatval($wpdb->get_var($wpdb->prepare("SELECT SUM(amount) FROM {$wpdb->prefix}cajun_transactions WHERE type='income' AND date BETWEEN %s AND %s", $start_date, $end_date)));
    $period_expense = floatval($wpdb->get_var($wpdb->prepare("SELECT SUM(amount) FROM {$wpdb->prefix}cajun_transactions WHERE type='expense' AND date BETWEEN %s AND %s", $start_date, $end_date)));
    $period_salary = floatval($wpdb->get_var($wpdb->prepare("SELECT SUM(gross_amount) FROM {$wpdb->prefix}cajun_salaries WHERE date BETWEEN %s AND %s", $start_date, $end_date)));
    $period_net = $period_income - ($period_expense + $period_salary);

    // Son hareketler
    $recent = $wpdb->get_results("SELECT date, type, category, description, amount FROM {$wpdb->prefix}cajun_transactions ORDER BY date DESC LIMIT 10", ARRAY_A);

    // Dashboard HTML
    ?>
    <h1 class="h4 mb-3">Genel Bakış</h1>
    <div class="row g-3">
      <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Toplam Gelir</div><div class="fs-4"><?php echo number_format($total_income, 2, ',', '.'); ?> ₺</div></div></div></div>
      <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Toplam Gider</div><div class="fs-4"><?php echo number_format($total_expense + $total_salaries, 2, ',', '.'); ?> ₺</div><div class="small text-muted">Gider + Maaş</div></div></div></div>
      <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Net</div><div class="fs-4"><?php echo number_format($net, 2, ',', '.'); ?> ₺</div></div></div></div>
      <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Personel</div><div class="fs-4"><?php echo $employees_count; ?></div></div></div></div>
    </div>

    <h2 class="h5 mt-4">Tarih Aralığı Seç</h2>
    <form method="post" class="row g-3">
      <div class="col-md-3">
        <label for="start_date" class="form-label">Başlangıç Tarihi</label>
        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
      </div>
      <div class="col-md-3">
        <label for="end_date" class="form-label">Bitiş Tarihi</label>
        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary">Filtrele</button>
      </div>
    </form>

    <h2 class="h5 mt-4">Seçilen Tarih Aralığı Özet</h2>
    <div class="row g-3">
      <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Gelir</div><div class="fs-5"><?php echo number_format($period_income, 2, ',', '.'); ?> ₺</div></div></div></div>
      <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Gider</div><div class="fs-5"><?php echo number_format($period_expense, 2, ',', '.'); ?> ₺</div></div></div></div>
      <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Maaş</div><div class="fs-5"><?php echo number_format($period_salary, 2, ',', '.'); ?> ₺</div></div></div></div>
      <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted">Net</div><div class="fs-5"><?php echo number_format($period_net, 2, ',', '.'); ?> ₺</div></div></div></div>
    </div>

    <h2 class="h5 mt-4">Son Hareketler</h2>
    <div class="table-responsive">
    <table class="table table-sm table-striped align-middle">
      <thead><tr><th>Tarih</th><th>Tür</th><th>Kategori</th><th>Açıklama</th><th class="text-end">Tutar</th></tr></thead>
      <tbody>
        <?php if ($recent) foreach ($recent as $r) { ?>
        <tr>
          <td><?php echo esc_html($r['date']); ?></td>
          <td>
            <?php if ($r['type'] == 'income') { ?>
              <span class="badge text-bg-success">Gelir</span>
            <?php } else { ?>
              <span class="badge text-bg-danger">Gider</span>
            <?php } ?>
          </td>
          <td><?php echo esc_html($r['category']); ?></td>
          <td><?php echo esc_html($r['description']); ?></td>
          <td class="text-end"><?php echo number_format($r['amount'], 2, ',', '.'); ?> ₺</td>
        </tr>
        <?php } else { ?>
        <tr><td colspan="5" class="text-center text-muted">Kayıt yok</td></tr>
        <?php } ?>
      </tbody>
    </table>
    </div>
    <?php
    return ob_get_clean();
}

// İşlemler sekmesi
function cajun_butce_wp_transactions() {
    global $wpdb;
    $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cajun_transactions ORDER BY date DESC LIMIT 50");
    ob_start();
    echo '<h2>Gelir/Gider</h2><table class="widefat"><thead><tr><th>Tarih</th><th>Tür</th><th>Kategori</th><th>Açıklama</th><th>Tutar</th><th>Firma</th><th>Ödeme Tipi</th></tr></thead><tbody>';
    foreach($rows as $r) {
        $firm_name = $wpdb->get_var($wpdb->prepare("SELECT firm_name FROM {$wpdb->prefix}cajun_firms WHERE firm_id=%s", $r->firm_id));
        echo "<tr><td>{$r->date}</td><td>{$r->type}</td><td>{$r->category}</td><td>{$r->description}</td><td>{$r->amount}</td><td>" . esc_html($firm_name) . "</td><td>{$r->payment_type}</td></tr>";
    }
    echo '</tbody></table>';
    return ob_get_clean();
}

// Gelir ekleme formu
function cajun_butce_wp_income_form() {
    global $wpdb;
    $alert = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cajun_income_submit'])) {
        $date = sanitize_text_field($_POST['date']);
        $category = sanitize_text_field($_POST['category']);
        $description = sanitize_text_field($_POST['description']);
        $amount = str_replace(',', '.', $_POST['amount']);
        $firm_id = sanitize_text_field($_POST['firm_id']);
        $payment_type = sanitize_text_field($_POST['payment_type']);
        if (!$date || !$amount || !is_numeric($amount)) {
            $alert = '<div class="alert alert-danger">Tarih ve tutar zorunludur, tutar sayısal olmalıdır.</div>';
        } else {
            $wpdb->insert("{$wpdb->prefix}cajun_transactions", [
                'date' => $date,
                'type' => 'income',
                'category' => $category,
                'description' => $description,
                'amount' => floatval($amount),
                'firm_id' => $firm_id,
                'payment_type' => $payment_type
            ]);
            $alert = '<div class="alert alert-success">Gelir kaydı eklendi.</div>';
        }
    }
    $firms = $wpdb->get_results("SELECT firm_id, firm_name FROM {$wpdb->prefix}cajun_firms", ARRAY_A);
    $payment_types = ['Nakit', 'Kart', 'Çek', 'Senet'];
    ob_start();
    echo $alert;
    ?>
    <form method="post">
        <label>Tarih: <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required></label><br>
        <label>Kategori: <input type="text" name="category" value="Genel"></label><br>
        <label>Açıklama: <input type="text" name="description"></label><br>
        <label>Tutar: <input type="text" name="amount" required></label><br>
        <label>Firma:
            <select name="firm_id">
                <option value="">Seçiniz</option>
                <?php foreach ($firms as $firm) echo '<option value="' . esc_attr($firm['firm_id']) . '">' . esc_html($firm['firm_name']) . '</option>'; ?>
            </select>
        </label><br>
        <label>Ödeme Tipi:
            <select name="payment_type">
                <?php foreach ($payment_types as $pt) echo '<option value="' . esc_attr($pt) . '">' . esc_html($pt) . '</option>'; ?>
            </select>
        </label><br>
        <button type="submit" name="cajun_income_submit">Gelir Ekle</button>
    </form>
    <?php
    return ob_get_clean();
}

// Gider ekleme formu
function cajun_butce_wp_expense_form() {
    global $wpdb;
    $alert = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cajun_expense_submit'])) {
        $date = sanitize_text_field($_POST['date']);
        $category = sanitize_text_field($_POST['category']);
        $description = sanitize_text_field($_POST['description']);
        $amount = str_replace(',', '.', $_POST['amount']);
        $firm_id = sanitize_text_field($_POST['firm_id']);
        $payment_type = sanitize_text_field($_POST['payment_type']);
        if (!$date || !$amount || !is_numeric($amount)) {
            $alert = '<div class="alert alert-danger">Tarih ve tutar zorunludur, tutar sayısal olmalıdır.</div>';
        } else {
            $wpdb->insert("{$wpdb->prefix}cajun_transactions", [
                'date' => $date,
                'type' => 'expense',
                'category' => $category,
                'description' => $description,
                'amount' => floatval($amount),
                'firm_id' => $firm_id,
                'payment_type' => $payment_type
            ]);
            $alert = '<div class="alert alert-success">Gider kaydı eklendi.</div>';
        }
    }
    $firms = $wpdb->get_results("SELECT firm_id, firm_name FROM {$wpdb->prefix}cajun_firms", ARRAY_A);
    $payment_types = ['Nakit', 'Kart', 'Çek', 'Senet'];
    ob_start();
    echo $alert;
    ?>
    <form method="post">
        <label>Tarih: <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required></label><br>
        <label>Kategori: <input type="text" name="category" value="Genel"></label><br>
        <label>Açıklama: <input type="text" name="description"></label><br>
        <label>Tutar: <input type="text" name="amount" required></label><br>
        <label>Firma:
            <select name="firm_id">
                <option value="">Seçiniz</option>
                <?php foreach ($firms as $firm) echo '<option value="' . esc_attr($firm['firm_id']) . '">' . esc_html($firm['firm_name']) . '</option>'; ?>
            </select>
        </label><br>
        <label>Ödeme Tipi:
            <select name="payment_type">
                <?php foreach ($payment_types as $pt) echo '<option value="' . esc_attr($pt) . '">' . esc_html($pt) . '</option>'; ?>
            </select>
        </label><br>
        <button type="submit" name="cajun_expense_submit">Gider Ekle</button>
    </form>
    <?php
    return ob_get_clean();
}

// Personeller sekmesi
function cajun_butce_wp_employees() {
    global $wpdb;
    $alert = '';
    // Personel ekleme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cajun_employee_submit'])) {
        $employee_id = sanitize_text_field($_POST['employee_id']);
        $name = sanitize_text_field($_POST['name']);
        $title = sanitize_text_field($_POST['title']);
        $base_salary = floatval(str_replace(',', '.', $_POST['base_salary']));
        if (!$employee_id || !$name || !$base_salary) {
            $alert = '<div class="alert alert-danger">ID, Ad Soyad ve Taban Maaş zorunludur.</div>';
        } elseif (!is_numeric($_POST['base_salary'])) {
            $alert = '<div class="alert alert-danger">Taban maaş sayısal olmalıdır.</div>';
        } else {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cajun_employees WHERE employee_id=%s", $employee_id));
            if ($exists) {
                $alert = '<div class="alert alert-danger">Bu ID ile personel zaten var!</div>';
            } else {
                $wpdb->insert("{$wpdb->prefix}cajun_employees", [
                    'employee_id' => $employee_id,
                    'name' => $name,
                    'title' => $title,
                    'base_salary' => $base_salary
                ]);
                $alert = '<div class="alert alert-success">Personel eklendi.</div>';
            }
        }
    }
    // Personel güncelleme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cajun_employee_update'])) {
        $original_employee_id = sanitize_text_field($_POST['original_employee_id']);
        $employee_id = sanitize_text_field($_POST['employee_id']);
        $name = sanitize_text_field($_POST['name']);
        $title = sanitize_text_field($_POST['title']);
        $base_salary = floatval(str_replace(',', '.', $_POST['base_salary']));
        if (!$employee_id || !$name || !$base_salary) {
            $alert = '<div class="alert alert-danger">ID, Ad Soyad ve Taban Maaş zorunludur.</div>';
        } elseif (!is_numeric($_POST['base_salary'])) {
            $alert = '<div class="alert alert-danger">Taban maaş sayısal olmalıdır.</div>';
        } else {
            $wpdb->update("{$wpdb->prefix}cajun_employees",
                [ 'employee_id' => $employee_id, 'name' => $name, 'title' => $title, 'base_salary' => $base_salary ],
                [ 'employee_id' => $original_employee_id ]
            );
            $alert = '<div class="alert alert-success">Personel güncellendi.</div>';
        }
    }
    // Personel silme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cajun_employee_delete'])) {
        $employee_id = sanitize_text_field($_POST['employee_id']);
        $wpdb->delete("{$wpdb->prefix}cajun_employees", [ 'employee_id' => $employee_id ]);
        $alert = '<div class="alert alert-success">Personel silindi.</div>';
    }
    // Düzenleme modunda mı?
    $edit_mode = false;
    $edit_employee = null;
    if (isset($_GET['edit_employee'])) {
        $edit_employee = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cajun_employees WHERE employee_id=%s", $_GET['edit_employee']), ARRAY_A);
        if ($edit_employee) $edit_mode = true;
    }
    $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cajun_employees ORDER BY name ASC", ARRAY_A);
    ob_start();
    echo $alert;
    ?>
    <form method="post">
        <?php if ($edit_mode) { ?>
            <input type="hidden" name="original_employee_id" value="<?php echo esc_attr($edit_employee['employee_id']); ?>">
            <label>ID: <input type="text" name="employee_id" value="<?php echo esc_attr($edit_employee['employee_id']); ?>" required></label><br>
            <label>Ad Soyad: <input type="text" name="name" value="<?php echo esc_attr($edit_employee['name']); ?>" required></label><br>
            <label>Ünvan: <input type="text" name="title" value="<?php echo esc_attr($edit_employee['title']); ?>"></label><br>
            <label>Taban Maaş: <input type="text" name="base_salary" value="<?php echo esc_attr($edit_employee['base_salary']); ?>" required></label><br>
            <button type="submit" name="cajun_employee_update">Güncelle</button>
            <a href="?tab=employees" class="btn btn-secondary">Vazgeç</a>
        <?php } else { ?>
            <label>ID: <input type="text" name="employee_id" required></label><br>
            <label>Ad Soyad: <input type="text" name="name" required></label><br>
            <label>Ünvan: <input type="text" name="title"></label><br>
            <label>Taban Maaş: <input type="text" name="base_salary" required></label><br>
            <button type="submit" name="cajun_employee_submit">Personel Ekle</button>
        <?php } ?>
    </form>
    <h3>Personel Listesi</h3>
    <table class="widefat"><thead><tr><th>ID</th><th>Ad Soyad</th><th>Ünvan</th><th>Taban Maaş</th><th>Düzenle</th><th>Sil</th></tr></thead><tbody>
    <?php foreach ($rows as $row) {
        echo '<tr>';
        echo '<td>' . esc_html($row['employee_id']) . '</td>';
        echo '<td>' . esc_html($row['name']) . '</td>';
        echo '<td>' . esc_html($row['title']) . '</td>';
        echo '<td>' . number_format($row['base_salary'], 2, ',', '.') . ' TL</td>';
        echo '<td><a href="?tab=employees&edit_employee=' . esc_attr($row['employee_id']) . '" class="btn btn-warning btn-sm">Düzenle</a></td>';
        echo '<td><form method="post" style="display:inline;"><input type="hidden" name="employee_id" value="' . esc_attr($row['employee_id']) . '"><button type="submit" name="cajun_employee_delete" class="btn btn-danger btn-sm" onclick="return confirm(\'Silmek istediğinize emin misiniz?\');">Sil</button></form></td>';
        echo '</tr>';
    } ?>
    </tbody></table>
    <?php
    return ob_get_clean();
}

// Maaşlar sekmesi
function cajun_butce_wp_salaries() {
    global $wpdb;
    $alert = '';
    // Maaş ekleme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cajun_salary_submit'])) {
        $date = sanitize_text_field($_POST['date']);
        $employee_id = sanitize_text_field($_POST['employee_id']);
        $emp = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$wpdb->prefix}cajun_employees WHERE employee_id=%s", $employee_id));
        if (!$date || !$employee_id || !is_numeric($_POST['gross_amount'])) {
            $alert = '<div class="alert alert-danger">Tarih, personel ve brüt tutar zorunludur ve tutar sayısal olmalıdır.</div>';
        } elseif (!$emp) {
            $alert = '<div class="alert alert-danger">Personel bulunamadı. Önce personeli ekleyin.</div>';
        } else {
            $employee_name = $emp->name;
            $gross_amount = floatval(str_replace(',', '.', $_POST['gross_amount']));
            $notes = sanitize_text_field($_POST['notes']);
            $wpdb->insert("{$wpdb->prefix}cajun_salaries", [
                'date' => $date,
                'employee_id' => $employee_id,
                'employee_name' => $employee_name,
                'gross_amount' => $gross_amount,
                'notes' => $notes
            ]);
            $alert = '<div class="alert alert-success">Maaş ödemesi kaydedildi.</div>';
        }
    }
    // Maaş güncelleme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cajun_salary_update'])) {
        $id = intval($_POST['salary_id']);
        $date = sanitize_text_field($_POST['date']);
        $employee_id = sanitize_text_field($_POST['employee_id']);
        $emp = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$wpdb->prefix}cajun_employees WHERE employee_id=%s", $employee_id));
        $employee_name = $emp ? $emp->name : '';
        if (!$date || !$employee_id || !is_numeric($_POST['gross_amount'])) {
            $alert = '<div class="alert alert-danger">Tarih, personel ve brüt tutar zorunludur ve tutar sayısal olmalıdır.</div>';
        } else {
            $gross_amount = floatval(str_replace(',', '.', $_POST['gross_amount']));
            $notes = sanitize_text_field($_POST['notes']);
            $wpdb->update("{$wpdb->prefix}cajun_salaries",
                [ 'date' => $date, 'employee_id' => $employee_id, 'employee_name' => $employee_name, 'gross_amount' => $gross_amount, 'notes' => $notes ],
                [ 'id' => $id ]
            );
            $alert = '<div class="alert alert-success">Maaş güncellendi.</div>';
        }
    }
    // Maaş silme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cajun_salary_delete'])) {
        $id = intval($_POST['salary_id']);
        $wpdb->delete("{$wpdb->prefix}cajun_salaries", [ 'id' => $id ]);
        $alert = '<div class="alert alert-success">Maaş silindi.</div>';
    }
    // Düzenleme modunda mı?
    $edit_mode = false;
    $edit_salary = null;
    if (isset($_GET['edit_salary'])) {
        $edit_salary = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cajun_salaries WHERE id=%d", intval($_GET['edit_salary'])), ARRAY_A);
        if ($edit_salary) $edit_mode = true;
    }
    $employees = $wpdb->get_results("SELECT employee_id, name FROM {$wpdb->prefix}cajun_employees", ARRAY_A);
    $payments = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cajun_salaries ORDER BY date DESC", ARRAY_A);
    ob_start();
    echo $alert;
    ?>
    <form method="post">
        <?php if ($edit_mode) { ?>
            <input type="hidden" name="salary_id" value="<?php echo esc_attr($edit_salary['id']); ?>">
            <label>Tarih: <input type="date" name="date" value="<?php echo esc_attr($edit_salary['date']); ?>" required></label><br>
            <label>Personel:
                <select name="employee_id" required>
                    <option value="">Seçiniz</option>
                    <?php foreach ($employees as $emp) echo '<option value="' . esc_attr($emp['employee_id']) . '"' . ($emp['employee_id'] == $edit_salary['employee_id'] ? ' selected' : '') . '>' . esc_html($emp['name']) . '</option>'; ?>
                </select>
            </label><br>
            <label>Brüt Tutar: <input type="text" name="gross_amount" value="<?php echo esc_attr($edit_salary['gross_amount']); ?>" required></label><br>
            <label>Açıklama: <input type="text" name="notes" value="<?php echo esc_attr($edit_salary['notes']); ?>"></label><br>
            <button type="submit" name="cajun_salary_update">Güncelle</button>
            <a href="?tab=salaries" class="btn btn-secondary">Vazgeç</a>
        <?php } else { ?>
            <label>Tarih: <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required></label><br>
            <label>Personel:
                <select name="employee_id" required>
                    <option value="">Seçiniz</option>
                    <?php foreach ($employees as $emp) echo '<option value="' . esc_attr($emp['employee_id']) . '">' . esc_html($emp['name']) . '</option>'; ?>
                </select>
            </label><br>
            <label>Brüt Tutar: <input type="text" name="gross_amount" required></label><br>
            <label>Açıklama: <input type="text" name="notes"></label><br>
            <button type="submit" name="cajun_salary_submit">Maaş Ekle</button>
        <?php } ?>
    </form>
    <h3>Maaş Ödemeleri</h3>
    <table class="widefat"><thead><tr><th>Tarih</th><th>Personel</th><th>Brüt Tutar</th><th>Açıklama</th><th>Düzenle</th><th>Sil</th></tr></thead><tbody>
    <?php foreach ($payments as $pay) {
        echo '<tr>';
        echo '<td>' . esc_html($pay['date']) . '</td>';
        echo '<td>' . esc_html($pay['employee_name']) . '</td>';
        echo '<td>' . number_format($pay['gross_amount'], 2, ',', '.') . ' TL</td>';
        echo '<td>' . esc_html($pay['notes']) . '</td>';
        echo '<td><a href="?tab=salaries&edit_salary=' . esc_attr($pay['id']) . '" class="btn btn-warning btn-sm">Düzenle</a></td>';
        echo '<td><form method="post" style="display:inline;"><input type="hidden" name="salary_id" value="' . esc_attr($pay['id']) . '"><button type="submit" name="cajun_salary_delete" class="btn btn-danger btn-sm" onclick="return confirm(\'Silmek istediğinize emin misiniz?\');">Sil</button></form></td>';
        echo '</tr>';
    } ?>
    </tbody></table>
    <?php
    return ob_get_clean();
}

// Firmalar sekmesi
function cajun_butce_wp_firms() {
    global $wpdb;
    $alert = '';
    // Firma ekleme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cajun_firm_submit'])) {
        $firm_id = sanitize_text_field($_POST['firm_id']);
        $firm_name = sanitize_text_field($_POST['firm_name']);
        $balance = floatval(str_replace(',', '.', $_POST['balance']));
        if (!$firm_id || !$firm_name || $_POST['balance'] === '') {
            $alert = '<div class="alert alert-danger">ID, Firma Adı ve Bakiye zorunludur.</div>';
        } elseif (!is_numeric($_POST['balance'])) {
            $alert = '<div class="alert alert-danger">Bakiye sayısal olmalıdır.</div>';
        } else {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cajun_firms WHERE firm_id=%s", $firm_id));
            if ($exists) {
                $alert = '<div class="alert alert-danger">Bu ID ile firma zaten var!</div>';
            } else {
                $wpdb->insert("{$wpdb->prefix}cajun_firms", [
                    'firm_id' => $firm_id,
                    'firm_name' => $firm_name,
                    'balance' => $balance
                ]);
                $alert = '<div class="alert alert-success">Firma eklendi.</div>';
            }
        }
    }
    // Firma güncelleme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cajun_firm_update'])) {
        $original_firm_id = sanitize_text_field($_POST['original_firm_id']);
        $firm_id = sanitize_text_field($_POST['firm_id']);
        $firm_name = sanitize_text_field($_POST['firm_name']);
        $balance = floatval(str_replace(',', '.', $_POST['balance']));
        if (!$firm_id || !$firm_name || $_POST['balance'] === '') {
            $alert = '<div class="alert alert-danger">ID, Firma Adı ve Bakiye zorunludur.</div>';
        } elseif (!is_numeric($_POST['balance'])) {
            $alert = '<div class="alert alert-danger">Bakiye sayısal olmalıdır.</div>';
        } else {
            $wpdb->update("{$wpdb->prefix}cajun_firms",
                [ 'firm_id' => $firm_id, 'firm_name' => $firm_name, 'balance' => $balance ],
                [ 'firm_id' => $original_firm_id ]
            );
            $alert = '<div class="alert alert-success">Firma güncellendi.</div>';
        }
    }
    // Firma silme
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cajun_firm_delete'])) {
        $firm_id = sanitize_text_field($_POST['firm_id']);
        $wpdb->delete("{$wpdb->prefix}cajun_firms", [ 'firm_id' => $firm_id ]);
        $alert = '<div class="alert alert-success">Firma silindi.</div>';
    }
    // Düzenleme modunda mı?
    $edit_mode = false;
    $edit_firm = null;
    if (isset($_GET['edit_firm'])) {
        $edit_firm = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cajun_firms WHERE firm_id=%s", $_GET['edit_firm']), ARRAY_A);
        if ($edit_firm) $edit_mode = true;
    }
    $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cajun_firms ORDER BY firm_name ASC", ARRAY_A);
    ob_start();
    echo $alert;
    ?>
    <form method="post">
        <?php if ($edit_mode) { ?>
            <input type="hidden" name="original_firm_id" value="<?php echo esc_attr($edit_firm['firm_id']); ?>">
            <label>ID: <input type="text" name="firm_id" value="<?php echo esc_attr($edit_firm['firm_id']); ?>" required></label><br>
            <label>Firma Adı: <input type="text" name="firm_name" value="<?php echo esc_attr($edit_firm['firm_name']); ?>" required></label><br>
            <label>Bakiye: <input type="text" name="balance" value="<?php echo esc_attr($edit_firm['balance']); ?>" required></label><br>
            <button type="submit" name="cajun_firm_update">Güncelle</button>
            <a href="?tab=firms" class="btn btn-secondary">Vazgeç</a>
        <?php } else { ?>
            <label>ID: <input type="text" name="firm_id" required></label><br>
            <label>Firma Adı: <input type="text" name="firm_name" required></label><br>
            <label>Bakiye: <input type="text" name="balance" required></label><br>
            <button type="submit" name="cajun_firm_submit">Firma Ekle</button>
        <?php } ?>
    </form>
    <h3>Firma Listesi</h3>
    <table class="widefat"><thead><tr><th>ID</th><th>Firma Adı</th><th>Bakiye</th><th>Detay</th><th>Düzenle</th><th>Sil</th></tr></thead><tbody>
    <?php foreach ($rows as $row) {
        echo '<tr>';
        echo '<td>' . esc_html($row['firm_id']) . '</td>';
        echo '<td>' . esc_html($row['firm_name']) . '</td>';
        echo '<td>' . number_format($row['balance'], 2, ',', '.') . ' TL</td>';
        echo '<td><form method="get" style="display:inline;"><input type="hidden" name="tab" value="firms"><input type="hidden" name="firm_detail" value="' . esc_attr($row['firm_id']) . '"><button type="submit" class="btn btn-info btn-sm">Firma Detayı</button></form></td>';
        echo '<td><a href="?tab=firms&edit_firm=' . esc_attr($row['firm_id']) . '" class="btn btn-warning btn-sm">Düzenle</a></td>';
        echo '<td><form method="post" style="display:inline;"><input type="hidden" name="firm_id" value="' . esc_attr($row['firm_id']) . '"><button type="submit" name="cajun_firm_delete" class="btn btn-danger btn-sm" onclick="return confirm(\'Silmek istediğinize emin misiniz?\');">Sil</button></form></td>';
        echo '</tr>';
    } ?>
    </tbody></table>
    <?php
    // Firma Detayını göster
    if (isset($_GET['firm_detail'])) {
        echo cajun_butce_wp_firm_detail($_GET['firm_detail']);
    }
    return ob_get_clean();
}

function cajun_butce_wp_firm_detail($firm_id) {
    global $wpdb;
    $firm = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cajun_firms WHERE firm_id=%s", $firm_id), ARRAY_A);
    if (!$firm) return '<div class="error">Firma bulunamadı.</div>';
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-01');
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d');
    $tx = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cajun_transactions WHERE firm_id=%s AND date BETWEEN %s AND %s ORDER BY date ASC", $firm_id, $start_date, $end_date), ARRAY_A);
    $onceki_bakiye = floatval($firm['balance']);
    $tx_bakiye_list = array();
    foreach ($tx as $row) {
        $degisim = array();
        $degisim['onceki_bakiye'] = $onceki_bakiye;
        if ($row['type'] == 'income') {
            $yeni_bakiye = $onceki_bakiye + floatval($row['amount']);
        } else {
            $yeni_bakiye = $onceki_bakiye - floatval($row['amount']);
        }
        $degisim['yeni_bakiye'] = $yeni_bakiye;
        $tx_bakiye_list[] = array('row' => $row, 'degisim' => $degisim);
        $onceki_bakiye = $yeni_bakiye;
    }
    ob_start();
    ?>
    <h2>Firma Detayı: <?php echo esc_html($firm['firm_name']); ?></h2>
    <p><strong>Firma ID:</strong> <?php echo esc_html($firm['firm_id']); ?></p>
    <p><strong>Bakiye:</strong> <?php echo number_format($firm['balance'], 2, ',', '.'); ?> TL</p>
    <h3>Firma Hareketleri</h3>
    <form method="post" class="mb-4 d-flex flex-row gap-3 align-items-end">
        <div><label>Başlangıç Tarihi:</label> <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" class="form-control"></div>
        <div><label>Bitiş Tarihi:</label> <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" class="form-control"></div>
        <button type="submit" class="btn btn-primary">Filtrele</button>
    </form>
    <table class="table table-bordered">
        <thead><tr><th>Tarih</th><th>Tip</th><th>Kategori</th><th>Açıklama</th><th>Tutar</th><th>Ödeme Tipi</th><th>Önceki Bakiye</th><th>Yeni Bakiye</th></tr></thead>
        <tbody>
        <?php foreach ($tx_bakiye_list as $item) {
            $row = $item['row'];
            $degisim = $item['degisim'];
            echo '<tr>';
            echo '<td>' . esc_html($row['date']) . '</td>';
            echo '<td>' . esc_html($row['type']) . '</td>';
            echo '<td>' . esc_html($row['category']) . '</td>';
            echo '<td>' . esc_html($row['description']) . '</td>';
            echo '<td>' . number_format($row['amount'], 2, ',', '.') . ' TL</td>';
            echo '<td>' . esc_html($row['payment_type']) . '</td>';
            echo '<td>' . number_format($degisim['onceki_bakiye'], 2, ',', '.') . ' TL</td>';
            echo '<td>' . number_format($degisim['yeni_bakiye'], 2, ',', '.') . ' TL</td>';
            echo '</tr>';
        } ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}
?>
