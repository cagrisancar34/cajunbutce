**Bütçe Yönetimi Web Uygulaması - Yeniden Geliştirme Promptu**

### 1. Ekranlar ve Temel Fonksiyonlar

- **Genel Bakış (Dashboard)**
  - Toplam gelir, gider, maaş ve net bakiye kartları
  - Personel ve firma sayısı
  - Son 10 finansal hareket tablosu
  - Tarih aralığı seçimi ve özet rapor

- **Gelir Ekle**
  - Tarih, kategori, açıklama, tutar, firma (opsiyonel) ve ödeme tipi alanları
  - Kayıt ekleme formu
  - Ödeme tipi için örnek seçenekler: Nakit, Kart, Çek, Senet

- **Gider Ekle**
  - Tarih, kategori, açıklama, tutar, firma (opsiyonel) ve ödeme tipi alanları
  - Kayıt ekleme formu
  - Ödeme tipi için örnek seçenekler: Nakit, Kart, Çek, Senet

- **Tüm Hareketler**
  - Tarih, tür (gelir/gider), kategori, açıklama, tutar, firma, ödeme tipi sütunları
  - Listeleme tablosu
  - Firma adı gösterilir (firm_name)

- **Personel Yönetimi**
  - Personel ekleme formu (Personel No kullanıcıdan alınır, otomatik artan değildir; ad soyad, ünvan, maaş)
  - Kayıtlı personel tablosu

- **Maaş Ödemeleri**
  - Maaş ekleme formu (tarih, personel, brüt tutar, not)
  - Ödeme geçmişi tablosu
  - Personel adı gösterilir (employee_name)

- **Firma Yönetimi**
  - Firma ekleme/güncelleme formu (Firma ID kullanıcıdan alınır, otomatik artan değildir; ad, bakiye)
  - Firma listesi tablosu
  - Firma detay ekranı: hareketler, bakiye değişimi, tarih aralığı filtresi
  - Firma detayında hareketler için önceki ve yeni bakiye gösterilir

#### Navigasyon
- Navbar: Genel Bakış, Gelir, Gider, Hareketler, Personel, Maaş, Firmalar
- Responsive tasarım

---

### 2. Veri Modeli ve Tablo Yapıları

#### Tablolar ve Alanlar

- **transactions**
  - id (otomatik, int, PRIMARY KEY)
  - date (DATE, NOT NULL)
  - type (VARCHAR, NOT NULL, gelir/gider)
  - category (VARCHAR)
  - description (TEXT)
  - amount (DECIMAL(18,2), NOT NULL)
  - firm_id (VARCHAR, FOREIGN KEY, NULLABLE)
  - payment_type (VARCHAR)

- **employees**
  - employee_id (VARCHAR, PRIMARY KEY, kullanıcıdan alınır)
  - name (VARCHAR, NOT NULL)
  - title (VARCHAR)
  - base_salary (DECIMAL(18,2), NOT NULL)

- **salaries**
  - id (otomatik, int, PRIMARY KEY)
  - date (DATE, NOT NULL)
  - employee_id (VARCHAR, FOREIGN KEY, NOT NULL)
  - employee_name (VARCHAR)
  - gross_amount (DECIMAL(18,2), NOT NULL)
  - notes (TEXT)

- **firms**
  - firm_id (VARCHAR, PRIMARY KEY, kullanıcıdan alınır)
  - firm_name (VARCHAR, NOT NULL)
  - balance (DECIMAL(18,2), NOT NULL)

#### İlişkiler
- transactions.firm_id → firms.firm_id (opsiyonel, FOREIGN KEY, ON DELETE SET NULL)
- salaries.employee_id → employees.employee_id (FOREIGN KEY, ON DELETE CASCADE)

#### Notlar
- Tablolarda birincil anahtar (PRIMARY KEY) ve yabancı anahtar (FOREIGN KEY) ilişkileri açıkça tanımlanmalıdır.
- Otomatik artan alanlar için INT AUTO_INCREMENT (veya SERIAL/IDENTITY) kullanılmalıdır.
- ID alanları (firm_id, employee_id) metin ise VARCHAR, sayı ise INT olmalıdır. Uygulamada metin olarak tutulması önerilir.
- Tüm zorunlu alanlar NOT NULL olmalıdır.
- Tutarlar için DECIMAL(18,2) kullanılmalıdır.
- Açıklama/not gibi uzun metinler için TEXT kullanılmalıdır.
- Yabancı anahtar ilişkilerinde silme durumunda uygun davranış (ON DELETE SET NULL veya ON DELETE CASCADE) belirtilmelidir.
- Gösterim için kullanılan alanlar (firm_name, employee_name) veritabanında tutulmak zorunda değildir, JOIN ile çekilebilir.
- Ödeme tipi için ENUM veya VARCHAR kullanılabilir, örnek değerler: Nakit, Kart, Çek, Senet.
