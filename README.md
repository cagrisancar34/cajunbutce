# Bütçe Yönetimi (Excel Tabanlı) - Flask Uygulaması

Bu proje, Render üzerinde kolayca deploy edilebilen, sadece Excel dosyası ile çalışan bir bütçe yönetimi web uygulamasıdır.

## Özellikler
- Gelir/gider kaydı
- Personel ve maaş yönetimi
- Firma yönetimi
- Tüm veriler tek bir Excel dosyasında saklanır (`data/budget.xlsx`)
- Modern ve sade arayüz (Flask + Jinja2)

## Klasör ve Dosya Yapısı
```
app.py                # Ana Flask uygulaması
requirements.txt      # Gerekli Python paketleri
runtime.txt           # Render için Python sürümü
Procfile              # Render için uygulama başlatıcı
README.md             # Bu dosya
.gitignore            # Gereksiz dosyalar için
/data/budget.xlsx     # Excel veri dosyası (otomatik oluşur)
/templates/           # HTML şablonları
```

## Render Deploy Talimatları

1. **Bu repoyu kendi GitHub hesabınıza fork/clone edin.**
2. **Render.com'da yeni bir Web Service oluşturun.**
   - Environment: Python
   - Build Command: `pip install --upgrade pip && pip install -r requirements.txt`
   - Start Command: `gunicorn app:app`
3. **Aşağıdaki dosyaların repoda olduğundan emin olun:**
   - `app.py`
   - `requirements.txt`
   - `runtime.txt`
   - `Procfile`
   - `data/budget.xlsx` (boş da olabilir, repoda olmalı)
   - `templates/` klasörü ve içeriği
4. **Varsa .venv, __pycache__, .DS_Store gibi dosyaları repodan silin veya .gitignore ile hariç tutun.**
5. **Deploy sonrası uygulama otomatik olarak çalışacaktır.**

## Geliştirme (Lokal)

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt
python app.py
```

## Notlar
- Tüm veriler `data/budget.xlsx` dosyasında saklanır. Render'da bu dosya yazılabilir olmalı.
- Uygulama ilk çalıştırıldığında Excel dosyası yoksa otomatik oluşturur.
- Sadece Flask uygulaması desteklenir, WordPress veya başka bir sistem yoktur.

## Sorun Giderme
- Pandas veya openpyxl build hatası alırsanız, Python sürümünüzün ve requirements.txt dosyanızın Render ile uyumlu olduğundan emin olun.
- `runtime.txt` dosyasında `python-3.10.13` yazmalıdır.
- `Procfile` içeriği: `web: gunicorn app:app`

---
Her türlü soru için: github issues veya Render destek.
