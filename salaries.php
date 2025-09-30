<?php
// Maaşlar sayfası
function budget_excel_salaries_page() {
    global $wpdb;
    $employees = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}employees", ARRAY_A);
    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $date = sanitize_text_field($_POST['date']);
        $employee_id = sanitize_text_field($_POST['employee_id']);
        $gross_amount = floatval($_POST['gross_amount']);
        $notes = sanitize_text_field($_POST['notes']);
        $emp_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}employees WHERE employee_id=%s", $employee_id), ARRAY_A);
        if (!$emp_row) {
            echo '<div class="notice notice-error">Personel bulunamadı. Önce personeli ekleyin.</div>';
        } else {
            $employee_name = $emp_row['name'];
            $wpdb->insert("{$wpdb->prefix}salaries", [
                'date' => $date,
                'employee_id' => $employee_id,
                'employee_name' => $employee_name,
                'gross_amount' => $gross_amount,
                'notes' => $notes
            ]);
            echo '<div class="notice notice-success">Maaş ödemesi kaydedildi.</div>';
        }
    }
    $salaries = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}salaries ORDER BY date DESC", ARRAY_A);
    echo '<h2>Maaşlar</h2>';
    echo '<form method="post">';
    echo '<input type="hidden" name="action" value="add">';
    echo 'Tarih: <input type="date" name="date" value="'.date('Y-m-d').'" required> ';
    echo 'Personel: <select name="employee_id">';
    foreach ($employees as $emp) {
        echo '<option value="'.esc_attr($emp['employee_id']).'">'.esc_html($emp['name']).'</option>';
    }
    echo '</select> ';
    echo 'Tutar: <input name="gross_amount" value="0" required> ';
    echo 'Not: <input name="notes"> ';
    echo '<button type="submit">Maaş Ekle</button>';
    echo '</form>';
    echo '<table class="widefat"><thead><tr><th>Tarih</th><th>Personel ID</th><th>Personel Adı</th><th>Tutar</th><th>Not</th></tr></thead><tbody>';
    foreach ($salaries as $sal) {
        echo '<tr>';
        echo '<td>'.esc_html($sal['date']).'</td>';
        echo '<td>'.esc_html($sal['employee_id']).'</td>';
        echo '<td>'.esc_html($sal['employee_name']).'</td>';
        echo '<td>'.esc_html($sal['gross_amount']).'</td>';
        echo '<td>'.esc_html($sal['notes']).'</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
budget_excel_salaries_page();
