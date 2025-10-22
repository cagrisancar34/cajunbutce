<?php
// cajun_wp/functions.php
// Yardımcı fonksiyonlar ve veri işlemleri burada olacak.

// Para formatı
function cajun_currency_fmt($x) {
    return number_format(floatval($x), 2, ',', '.');
}
