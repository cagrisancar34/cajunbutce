<?php
// Gelir ekleme sayfası
// ...app.py ve income.html mantığına göre...
global $wpdb;
$firms = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}firms", ARRAY_A);
$payment_types = ['Nakit', 'Kart', 'Çek', 'Senet'];
if (isset($_POST['action']) && $_POST['action'] == 'income') {
    $date = sanitize_text_field($_POST['date']);
    $category = sanitize_text_field($_POST['category']);
    $description = sanitize_text_field($_POST['description']);
    $amount = floatval($_POST['amount']);
    $firm_id = sanitize_text_field($_POST['firm_id']);
    $payment_type = sanitize_text_field($_POST['payment_type']);
    $wpdb->insert("{$wpdb->prefix}transactions", [
        'date' => $date,
        'type' => 'income',
        'category' => $category,
        'description' => $description,
        'amount' => $amount,
        'firm_id' => $firm_id,
        'payment_type' => $payment_type
    ]);
    if ($firm_id) {
        $firm = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}firms WHERE firm_id=%s", $firm_id), ARRAY_A);
        if ($firm) {
            $new_balance = $firm['balance'] + $amount;
            $wpdb->update("{$wpdb->prefix}firms", ['balance' => $new_balance], ['firm_id' => $firm_id]);
        }
    }
    echo '<div class="notice notice-success">Gelir kaydı eklendi.</div>';
}
echo '<h2>Gelir Ekle</h2>';
echo '<form method="post">';
echo '<input type="hidden" name="action" value="income">';
echo 'Tarih: <input type="date" name="date" value="'.date('Y-m-d').'" required> ';
echo 'Kategori: <input name="category" value="Genel"> ';
echo 'Açıklama: <input name="description"> ';
echo 'Tutar: <input name="amount" value="0" required> ';
echo 'Firma: <select name="firm_id"><option value="">Seçiniz</option>';
foreach ($firms as $firm) {
    echo '<option value="'.esc_attr($firm['firm_id']).'">'.esc_html($firm['firm_name']).'</option>';
}
echo '</select> ';
echo 'Ödeme Tipi: <select name="payment_type">';
foreach ($payment_types as $pt) {
    echo '<option value="'.esc_attr($pt).'">'.esc_html($pt).'</option>';
}
echo '</select> ';
echo '<button type="submit">Gelir Ekle</button>';
echo '</form>';
$transactions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}transactions WHERE type=%s ORDER BY date DESC LIMIT 10", 'income'), ARRAY_A);
echo '<h3>Son Gelirler</h3>';
echo '<table class="widefat"><thead><tr><th>Tarih</th><th>Kategori</th><th>Açıklama</th><th>Tutar</th><th>Firma</th><th>Ödeme Tipi</th></tr></thead><tbody>';
foreach ($transactions as $tx) {
    $firm_name = '';
    foreach ($firms as $firm) {
        if ($firm['firm_id'] == $tx['firm_id']) { $firm_name = $firm['firm_name']; break; }
    }
    echo '<tr>';
    echo '<td>'.esc_html($tx['date']).'</td>';
    echo '<td>'.esc_html($tx['category']).'</td>';
    echo '<td>'.esc_html($tx['description']).'</td>';
    echo '<td>'.esc_html($tx['amount']).'</td>';
    echo '<td>'.esc_html($firm_name).'</td>';
    echo '<td>'.esc_html($tx['payment_type']).'</td>';
    echo '</tr>';
}
echo '</tbody></table>';
