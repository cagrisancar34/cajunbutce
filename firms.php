<?php
// Firmalar sayfası
// Firma ekle, düzenle, sil ve listele
// ...app.py ve firms.html mantığına göre...
global $wpdb;
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $firm_id = sanitize_text_field($_POST['firm_id']);
        $firm_name = sanitize_text_field($_POST['firm_name']);
        $balance = floatval($_POST['balance']);
        $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}firms WHERE firm_id=%s", $firm_id));
        if ($exists) {
            echo '<div class="notice notice-error">Bu ID ile firma zaten var!</div>';
        } else {
            $wpdb->insert("{$wpdb->prefix}firms", [
                'firm_id' => $firm_id,
                'firm_name' => $firm_name,
                'balance' => $balance
            ]);
            echo '<div class="notice notice-success">Firma eklendi.</div>';
        }
    } elseif ($_POST['action'] == 'edit') {
        $original_firm_id = sanitize_text_field($_POST['original_firm_id']);
        $firm_id = sanitize_text_field($_POST['firm_id']);
        $firm_name = sanitize_text_field($_POST['firm_name']);
        $balance = floatval($_POST['balance']);
        $wpdb->update("{$wpdb->prefix}firms",
            [ 'firm_id' => $firm_id, 'firm_name' => $firm_name, 'balance' => $balance ],
            [ 'firm_id' => $original_firm_id ]
        );
        echo '<div class="notice notice-success">Firma güncellendi.</div>';
    } elseif ($_POST['action'] == 'delete') {
        $firm_id = sanitize_text_field($_POST['firm_id']);
        $wpdb->delete("{$wpdb->prefix}firms", [ 'firm_id' => $firm_id ]);
        echo '<div class="notice notice-success">Firma silindi.</div>';
    }
}
$edit_mode = false;
$edit_firm = null;
if (isset($_GET['edit'])) {
    $edit_id = sanitize_text_field($_GET['edit']);
    $edit_firm = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}firms WHERE firm_id=%s", $edit_id), ARRAY_A);
    if ($edit_firm) $edit_mode = true;
}
$firms = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}firms", ARRAY_A);
echo '<h2>Firmalar</h2>';
echo '<form method="post">';
if ($edit_mode) {
    echo '<input type="hidden" name="action" value="edit">';
    echo '<input type="hidden" name="original_firm_id" value="'.esc_attr($edit_firm['firm_id']).'">';
} else {
    echo '<input type="hidden" name="action" value="add">';
}
echo 'ID: <input name="firm_id" value="'.esc_attr($edit_firm['firm_id'] ?? '').'" required> ';
echo 'Ad: <input name="firm_name" value="'.esc_attr($edit_firm['firm_name'] ?? '').'" required> ';
echo 'Bakiye: <input name="balance" value="'.esc_attr($edit_firm['balance'] ?? '0').'" required> ';
echo '<button type="submit">'.($edit_mode ? 'Güncelle' : 'Ekle').'</button>';
if ($edit_mode) echo ' <a href="?page=budget-excel-firms">Vazgeç</a>';
echo '</form>';
echo '<table class="widefat"><thead><tr><th>ID</th><th>Ad</th><th>Bakiye</th><th>İşlemler</th></tr></thead><tbody>';
foreach ($firms as $firm) {
    echo '<tr>';
    echo '<td>'.esc_html($firm['firm_id']).'</td>';
    echo '<td>'.esc_html($firm['firm_name']).'</td>';
    echo '<td>'.esc_html($firm['balance']).'</td>';
    echo '<td>';
    echo '<a href="?page=budget-excel-firms&edit='.esc_attr($firm['firm_id']).'">Düzenle</a> ';
    echo '<form method="post" style="display:inline;"><input type="hidden" name="action" value="delete"><input type="hidden" name="firm_id" value="'.esc_attr($firm['firm_id']).'"><button type="submit" onclick="return confirm(\'Silinsin mi?\')">Sil</button></form>';
    echo '</td>';
    echo '</tr>';
}
echo '</tbody></table>';
