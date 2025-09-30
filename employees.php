<?php
// Personeller sayfası
// Personel ekle ve listele
// ...app.py ve employees.html mantığına göre...
global $wpdb;
if (isset($_POST['action']) && $_POST['action'] == 'add') {
    $employee_id = sanitize_text_field($_POST['employee_id']);
    $name = sanitize_text_field($_POST['name']);
    $title = sanitize_text_field($_POST['title']);
    $base_salary = floatval($_POST['base_salary']);
    $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}employees WHERE employee_id=%s", $employee_id));
    if ($exists) {
        echo '<div class="notice notice-error">Bu ID ile personel zaten var!</div>';
    } else {
        $wpdb->insert("{$wpdb->prefix}employees", [
            'employee_id' => $employee_id,
            'name' => $name,
            'title' => $title,
            'base_salary' => $base_salary
        ]);
        echo '<div class="notice notice-success">Personel eklendi.</div>';
    }
}
$employees = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}employees", ARRAY_A);
echo '<h2>Personeller</h2>';
echo '<form method="post">';
echo '<input type="hidden" name="action" value="add">';
echo 'ID: <input name="employee_id" required> ';
echo 'Ad: <input name="name" required> ';
echo 'Ünvan: <input name="title"> ';
echo 'Maaş: <input name="base_salary" value="0" required> ';
echo '<button type="submit">Ekle</button>';
echo '</form>';
echo '<table class="widefat"><thead><tr><th>ID</th><th>Ad</th><th>Ünvan</th><th>Maaş</th></tr></thead><tbody>';
foreach ($employees as $emp) {
    echo '<tr>';
    echo '<td>'.esc_html($emp['employee_id']).'</td>';
    echo '<td>'.esc_html($emp['name']).'</td>';
    echo '<td>'.esc_html($emp['title']).'</td>';
    echo '<td>'.esc_html($emp['base_salary']).'</td>';
    echo '</tr>';
}
echo '</tbody></table>';
budget_excel_employees_page();
