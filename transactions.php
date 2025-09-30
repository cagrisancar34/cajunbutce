<?php
// İşlemler sayfası
function budget_excel_transactions_page() {
    global $wpdb;
    $firms = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}firms", ARRAY_A);
    $firms_map = array();
    foreach ($firms as $firm) {
        $firms_map[$firm['firm_id']] = $firm['firm_name'];
    }
    $transactions = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}transactions ORDER BY date DESC", ARRAY_A);
    echo '<h2>İşlemler</h2>';
    echo '<table class="widefat"><thead><tr><th>Tarih</th><th>Tür</th><th>Kategori</th><th>Açıklama</th><th>Tutar</th><th>Firma</th><th>Ödeme Tipi</th></tr></thead><tbody>';
    foreach ($transactions as $tx) {
        echo '<tr>';
        echo '<td>'.esc_html($tx['date']).'</td>';
        echo '<td>'.esc_html($tx['type']).'</td>';
        echo '<td>'.esc_html($tx['category']).'</td>';
        echo '<td>'.esc_html($tx['description']).'</td>';
        echo '<td>'.esc_html($tx['amount']).'</td>';
        echo '<td>'.esc_html($firms_map[$tx['firm_id']] ?? '-').'</td>';
        echo '<td>'.esc_html($tx['payment_type']).'</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}
budget_excel_transactions_page();
