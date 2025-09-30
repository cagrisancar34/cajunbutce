import os
import json
import time
from datetime import datetime
from dateutil.relativedelta import relativedelta
from flask import Flask, render_template, request, redirect, url_for, flash
import pandas as pd

# --- Google Sheets bağımlılıkları ---
import gspread
from google.oauth2.service_account import Credentials
from gspread.exceptions import APIError, WorksheetNotFound

# --------------------------------------------------------------------------------------
# UYGULAMA AYARLARI
# --------------------------------------------------------------------------------------
APP_TITLE = "Bütçe Yönetimi (Google Sheets Tabanlı)"

# ENV ile override edilebilir
SHEET_ID = os.environ.get("GOOGLE_SHEETS_ID", "1H7Nl-An6BKfXFUJm-pLQOxs2PMCQIMRC5Ak8R_kMWA8")

GS_SCOPES = [
    "https://www.googleapis.com/auth/spreadsheets",
    "https://www.googleapis.com/auth/drive",
]

# Uygulamanın beklediği çalışma sayfaları ve kolonları
SHEETS_DEF = {
    "transactions": ["date", "type", "category", "description", "amount", "firm_id", "payment_type"],
    "employees": ["employee_id", "name", "title", "base_salary"],
    "salaries": ["date", "employee_id", "employee_name", "gross_amount", "notes"],
    "firms": ["firm_id", "firm_name", "balance"],
}

# --------------------------------------------------------------------------------------
# GOOGLE SHEETS ALTYAPISI (kota dostu: tek client, tek spreadsheet, cache + retry)
# --------------------------------------------------------------------------------------
_GS_CLIENT = None
_SPREADSHEET = None

# Basit süreli cache (tüm sayfaları topluca tutar)
_GS_CACHE = {"ts": 0.0, "data": {}}  # data: {sheet_name: [[...],[...],...]}
_CACHE_TTL = 60.0  # saniye – navigasyonu belirgin hızlandırır

# ensure_workbook'u sadece bir defa çalıştır
_WORKBOOK_OK = False

def _retry(fn, *args, **kwargs):
    """429 / 5xx için exponential backoff ile 5 deneme."""
    delay = 0.8
    for _ in range(5):
        try:
            return fn(*args, **kwargs)
        except APIError as e:
            msg = str(e)
            if ("429" in msg or "rateLimitExceeded" in msg or
                "User Rate Limit Exceeded" in msg or "[500]" in msg or "[503]" in msg):
                time.sleep(delay)
                delay = min(delay * 2, 8.0)
                continue
            raise
    return fn(*args, **kwargs)

def _gs_credentials():
    """
    Servis hesabı kimliği:
    - GOOGLE_APPLICATION_CREDENTIALS = /path/to/key.json
      veya
    - GOOGLE_CREDENTIALS_JSON = { ...json... }
    veya proje kökünde service_account.json
    """
    cred_path = os.environ.get("GOOGLE_APPLICATION_CREDENTIALS")
    if cred_path and os.path.exists(cred_path):
        return Credentials.from_service_account_file(cred_path, scopes=GS_SCOPES)

    cred_json = os.environ.get("GOOGLE_CREDENTIALS_JSON")
    if cred_json:
        return Credentials.from_service_account_info(json.loads(cred_json), scopes=GS_SCOPES)

    for name in ("service_account.json", "credentials.json", "gcp-key.json"):
        candidate = os.path.join(os.path.dirname(__file__), name)
        if os.path.exists(candidate):
            return Credentials.from_service_account_file(candidate, scopes=GS_SCOPES)

    raise RuntimeError(
        "Google servis hesabı bilgisi bulunamadı. "
        "GOOGLE_APPLICATION_CREDENTIALS veya GOOGLE_CREDENTIALS_JSON verin "
        "ya da proje köküne service_account.json koyun."
    )

def _gs_client():
    global _GS_CLIENT
    if (_GS_CLIENT is None):
        _GS_CLIENT = gspread.authorize(_gs_credentials())
    return _GS_CLIENT

def _open_spreadsheet():
    global _SPREADSHEET
    if not SHEET_ID:
        raise RuntimeError("GOOGLE_SHEETS_ID tanımlı değil.")
    if _SPREADSHEET is None:
        _SPREADSHEET = _retry(_gs_client().open_by_key, SHEET_ID)
    return _SPREADSHEET

def _normalize_rows(rows, cols_len):
    """Satırları sağdan '' ile pad'leyip/truncate eder; pandas ve update için güvenli."""
    norm = []
    for r in rows:
        r = list(r)
        if len(r) < cols_len:
            r = r + [""] * (cols_len - len(r))
        elif len(r) > cols_len:
            r = r[:cols_len]
        norm.append(r)
    return norm

def ensure_workbook_once():
    """
    Gerekli çalışma sayfaları ve başlıkları **bir kez** garanti altına al.
    Her istek başına çalışmaz → ciddi hızlanma.
    """
    global _WORKBOOK_OK
    if _WORKBOOK_OK:
        return
    sh = _open_spreadsheet()
    existing = {ws.title for ws in _retry(sh.worksheets)}
    for sheet_name, cols in SHEETS_DEF.items():
        if sheet_name not in existing:
            _retry(sh.add_worksheet, title=sheet_name, rows=1000, cols=max(len(cols), 10))
            ws = _retry(sh.worksheet, sheet_name)
            _retry(ws.update, [cols])  # başlık
        else:
            ws = _retry(sh.worksheet, sheet_name)
            first_row = _retry(ws.row_values, 1)
            if first_row != cols:
                values = _retry(ws.get_all_values)
                data_rows = values[1:] if values else []
                data_rows = _normalize_rows(data_rows, len(cols))
                _retry(ws.clear)
                _retry(ws.update, [cols] + data_rows)
    _WORKBOOK_OK = True

def _invalidate_cache():
    _GS_CACHE["ts"] = 0.0

def _refresh_cache_if_needed(force=False):
    """Cache TTL dolduysa 3 sayfayı tek çağrıyla çek."""
    ensure_workbook_once()
    if not force:
        now = time.time()
        if now - _GS_CACHE["ts"] < _CACHE_TTL:
            return
    sh = _open_spreadsheet()
    ranges = list(SHEETS_DEF.keys())  # ['transactions','employees','salaries']
    resp = _retry(sh.values_batch_get, ranges=ranges)
    data = {}
    for vr in resp.get("valueRanges", []):
        # "SheetName!A1:Z" gibi dönebilir; isim kısmını al
        title = vr.get("range", "").split("!")[0].strip("'")
        values = vr.get("values", [])
        data[title] = values
    _GS_CACHE["data"] = data
    _GS_CACHE["ts"] = time.time()

def _values_to_df(sheet_name):
    cols = SHEETS_DEF[sheet_name]
    values = _GS_CACHE["data"].get(sheet_name, [])
    if not values:
        return pd.DataFrame(columns=cols)
    # İlk satır başlık; geri kalan satırları hizala
    data_rows = values[1:] if values else []
    data_rows = _normalize_rows(data_rows, len(cols))
    df = pd.DataFrame(data_rows, columns=cols)
    return df

def read_sheet(sheet: str) -> pd.DataFrame:
    """
    İstenen çalışma sayfasını pandas DataFrame olarak döndürür.
    Cache + batch ile tek çağrıda okur. Satırları pad'ler.
    """
    try:
        _refresh_cache_if_needed(force=bool(request.args.get("refresh")))
    except RuntimeError:
        # request context yoksa (ör. startup) normal devam
        _refresh_cache_if_needed()
    except WorksheetNotFound:
        ensure_workbook_once()
        _refresh_cache_if_needed(force=True)

    df = _values_to_df(sheet)

    # Tip düzenlemeleri
    if sheet == "transactions":
        df["amount"] = pd.to_numeric(df["amount"], errors="coerce")
    elif sheet == "employees":
        df["base_salary"] = pd.to_numeric(df["base_salary"], errors="coerce")
    elif sheet == "salaries":
        df["gross_amount"] = pd.to_numeric(df["gross_amount"], errors="coerce")

    return df.fillna("")

def write_sheet(sheet: str, df: pd.DataFrame) -> None:
    """
    Tüm sayfayı verilen DataFrame ile değiştirir.
    """
    ensure_workbook_once()
    sh = _open_spreadsheet()
    ws = _retry(sh.worksheet, sheet)
    df = df.reindex(columns=SHEETS_DEF[sheet])
    values = [list(df.columns)] + df.astype(object).where(pd.notna(df), "").values.tolist()
    values = _normalize_rows(values, len(SHEETS_DEF[sheet]))  # güvenlik
    _retry(ws.clear)
    _retry(ws.update, values)
    _invalidate_cache()

def append_row(sheet: str, row_dict: dict) -> None:
    """
    Tek satır ekleme – kota dostu (tek API çağrısı).
    Kolon sırasını SHEETS_DEF'e göre hizalar.
    """
    ensure_workbook_once()
    sh = _open_spreadsheet()
    ws = _retry(sh.worksheet, sheet)
    values = [row_dict.get(col, "") for col in SHEETS_DEF[sheet]]
    values = _normalize_rows([values], len(SHEETS_DEF[sheet]))[0]
    _retry(ws.append_row, values)
    _invalidate_cache()

# --------------------------------------------------------------------------------------
# UYGULAMA (ROUTER + YARDIMCI FONKSİYONLAR)
# --------------------------------------------------------------------------------------
def currency_fmt(x):
    try:
        return f"{float(x):,.2f}".replace(",", "X").replace(".", ",").replace("X", ".")
    except Exception:
        return x

app = Flask(__name__)
app.secret_key = os.environ.get("FLASK_SECRET_KEY", "dev-secret")

@app.template_filter("tl")
def tl_filter(val):
    return currency_fmt(val)

@app.route("/", methods=["GET", "POST"])
def index():
    # Google Sheets'ten verileri çek (cache + batch)
    tx = read_sheet("transactions")
    salaries = read_sheet("salaries")
    employees = read_sheet("employees")

    # Tarih aralığı
    if request.method == "POST":
        start_date_str = request.form.get("start_date")
        end_date_str = request.form.get("end_date")
    else:
        start_date_str = request.args.get("start_date")
        end_date_str = request.args.get("end_date")

    today = datetime.today()
    start_date = datetime.strptime(start_date_str, "%Y-%m-%d") if start_date_str else today.replace(day=1)
    end_date = datetime.strptime(end_date_str, "%Y-%m-%d") if end_date_str else today.replace(day=1) + relativedelta(months=1)

    # Tarih kolonlarını dönüştür
    tx["date"] = pd.to_datetime(tx.get("date", pd.Series(dtype="datetime64[ns]")), errors="coerce")
    salaries["date"] = pd.to_datetime(salaries.get("date", pd.Series(dtype="datetime64[ns]")), errors="coerce")

    cur_tx = tx[(tx["date"] >= start_date) & (tx["date"] <= end_date)] if not tx.empty else pd.DataFrame()
    cur_sal = salaries[(salaries["date"] >= start_date) & (salaries["date"] <= end_date)] if not salaries.empty else pd.DataFrame()

    total_income = float(tx[tx["type"] == "income"]["amount"].sum()) if not tx.empty else 0.0
    total_expense = float(tx[tx["type"] == "expense"]["amount"].sum()) if not tx.empty else 0.0
    total_salaries = float(salaries["gross_amount"].sum()) if not salaries.empty else 0.0
    net = total_income - (total_expense + total_salaries)

    period_income = float(cur_tx[cur_tx["type"] == "income"]["amount"].sum()) if not cur_tx.empty else 0.0
    period_expense = float(cur_tx[cur_tx["type"] == "expense"]["amount"].sum()) if not cur_tx.empty else 0.0
    period_salary = float(cur_sal["gross_amount"].sum()) if not cur_sal.empty else 0.0
    period_net = period_income - (period_expense + period_salary)

    recent = tx.sort_values("date", ascending=False).head(10).to_dict(orient="records") if not tx.empty else []

    return render_template(
        "index.html",
        app_title=APP_TITLE,
        total_income=total_income,
        total_expense=total_expense,
        total_salaries=total_salaries,
        net=net,
        period_income=period_income,
        period_expense=period_expense,
        period_salary=period_salary,
        period_net=period_net,
        recent=recent,
        employees_count=len(employees.index),
        start_date=start_date.strftime("%Y-%m-%d"),
        end_date=end_date.strftime("%Y-%m-%d"),
    )

@app.route("/income", methods=["GET", "POST"])
def income():
    firms_df = read_sheet("firms")
    firms = firms_df.to_dict(orient="records") if not firms_df.empty else []
    payment_types = ["Nakit", "Kart", "Çek", "Senet"]
    if request.method == "POST":
        date = request.form.get("date") or datetime.today().strftime("%Y-%m-%d")
        category = request.form.get("category") or "Genel"
        description = request.form.get("description") or ""
        amount = float((request.form.get("amount") or "0").replace(",", "."))
        firm_id = request.form.get("firm_id") or ""
        payment_type = request.form.get("payment_type") or "Nakit"
        append_row(
            "transactions",
            {
                "date": date,
                "type": "income",
                "category": category,
                "description": description,
                "amount": amount,
                "firm_id": firm_id,
                "payment_type": payment_type,
            },
        )
        # Firma bakiyesini güncelle (gelir eklenirse artar)
        if firm_id:
            idx = firms_df[firms_df["firm_id"] == firm_id].index
            if not idx.empty:
                cur_balance = float(firms_df.loc[idx[0], "balance"] or 0)
                new_balance = cur_balance + amount
                firms_df.loc[idx[0], "balance"] = new_balance
                write_sheet("firms", firms_df)
        flash("Gelir kaydı eklendi.", "success")
        return redirect(url_for("income"))
    return render_template("income.html", app_title=APP_TITLE, firms=firms, payment_types=payment_types)

@app.route("/expense", methods=["GET", "POST"])
def expense():
    firms_df = read_sheet("firms")
    firms = firms_df.to_dict(orient="records") if not firms_df.empty else []
    payment_types = ["Nakit", "Kart", "Çek", "Senet"]
    if request.method == "POST":
        date = request.form.get("date") or datetime.today().strftime("%Y-%m-%d")
        category = request.form.get("category") or "Genel"
        description = request.form.get("description") or ""
        amount = float((request.form.get("amount") or "0").replace(",", "."))
        firm_id = request.form.get("firm_id") or ""
        payment_type = request.form.get("payment_type") or "Nakit"
        append_row(
            "transactions",
            {
                "date": date,
                "type": "expense",
                "category": category,
                "description": description,
                "amount": amount,
                "firm_id": firm_id,
                "payment_type": payment_type,
            },
        )
        # Firma bakiyesini güncelle
        if firm_id:
            idx = firms_df[firms_df["firm_id"] == firm_id].index
            if not idx.empty:
                cur_balance = float(firms_df.loc[idx[0], "balance"] or 0)
                new_balance = cur_balance - amount
                firms_df.loc[idx[0], "balance"] = new_balance
                write_sheet("firms", firms_df)
        flash("Gider kaydı eklendi.", "success")
        return redirect(url_for("expense"))
    return render_template("expense.html", app_title=APP_TITLE, firms=firms, payment_types=payment_types)

@app.route("/employees", methods=["GET", "POST"])
def employees():
    if request.method == "POST":
        employee_id = request.form.get("employee_id")
        name = request.form.get("name")
        title = request.form.get("title") or ""
        base_salary = float((request.form.get("base_salary") or "0").replace(",", "."))
        append_row(
            "employees",
            {"employee_id": employee_id, "name": name, "title": title, "base_salary": base_salary},
        )
        flash("Personel eklendi.", "success")
        return redirect(url_for("employees"))

    df = read_sheet("employees")
    rows = df.to_dict(orient="records") if not df.empty else []
    return render_template("employees.html", app_title=APP_TITLE, rows=rows)

@app.route("/salaries", methods=["GET", "POST"])
def salaries():
    employees_df = read_sheet("employees")
    employees = employees_df.to_dict(orient="records") if not employees_df.empty else []

    if request.method == "POST":
        date = request.form.get("date") or datetime.today().strftime("%Y-%m-%d")
        employee_id = request.form.get("employee_id")
        emp_row = employees_df[employees_df["employee_id"] == employee_id] if not employees_df.empty else pd.DataFrame()
        if emp_row.empty:
            flash("Personel bulunamadı. Önce personeli ekleyin.", "danger")
            return redirect(url_for("salaries"))
        employee_name = emp_row.iloc[0]["name"]
        gross_amount = float((request.form.get("gross_amount") or "0").replace(",", "."))
        notes = request.form.get("notes") or ""
        append_row(
            "salaries",
            {"date": date, "employee_id": employee_id, "employee_name": employee_name, "gross_amount": gross_amount, "notes": notes},
        )
        flash("Maaş ödemesi kaydedildi.", "success")
        return redirect(url_for("salaries"))

    payments_df = read_sheet("salaries")
    payments = payments_df.sort_values("date", ascending=False).to_dict(orient="records") if not payments_df.empty else []
    return render_template("salaries.html", app_title=APP_TITLE, employees=employees, payments=payments)

@app.route("/transactions")
def transactions():
    tx = read_sheet("transactions")
    firms_df = read_sheet("firms")
    firms_map = {row["firm_id"]: row["firm_name"] for _, row in firms_df.iterrows()} if not firms_df.empty else {}
    tx = tx.sort_values("date", ascending=False) if not tx.empty else tx
    rows = tx.to_dict(orient="records") if not tx.empty else []
    # Firma adı ve ödeme tipi ekle
    for row in rows:
        row["firm_name"] = firms_map.get(row.get("firm_id", ""), "-")
    return render_template("transactions.html", app_title=APP_TITLE, rows=rows)

@app.route("/firm/<firm_id>", methods=["GET", "POST"])
def firm_detail(firm_id):
    firms_df = read_sheet("firms")
    firm_row = firms_df[firms_df["firm_id"] == firm_id] if not firms_df.empty else pd.DataFrame()
    firm = firm_row.iloc[0].to_dict() if not firm_row.empty else None
    tx = read_sheet("transactions")
    tx = tx[tx["firm_id"] == firm_id] if not tx.empty else pd.DataFrame()
    tx["date"] = pd.to_datetime(tx["date"], errors="coerce")
    # Tarih filtresi
    if request.method == "POST":
        start_date_str = request.form.get("start_date")
        end_date_str = request.form.get("end_date")
    else:
        start_date_str = request.args.get("start_date")
        end_date_str = request.args.get("end_date")
    today = datetime.today()
    start_date = datetime.strptime(start_date_str, "%Y-%m-%d") if start_date_str else None
    end_date = datetime.strptime(end_date_str, "%Y-%m-%d") if end_date_str else None
    # Tarih filtresi yoksa tüm işlemleri göster
    if start_date and end_date:
        filtered_tx = tx[(tx["date"] >= start_date) & (tx["date"] <= end_date)] if not tx.empty else pd.DataFrame()
    else:
        filtered_tx = tx if not tx.empty else pd.DataFrame()
    # İşlemleri en eskiye göre sırala
    filtered_tx = filtered_tx.sort_values("date", ascending=True)
    tx_rows = filtered_tx.to_dict(orient="records") if not filtered_tx.empty else []
    # Firma bakiyesinin başlangıcı: firmanın güncel bakiyesi - işlem toplamı
    toplam_gelir = sum(float(row["amount"] or 0) for row in tx_rows if row["type"] == "income")
    toplam_gider = sum(float(row["amount"] or 0) for row in tx_rows if row["type"] == "expense")
    bakiye = float(firm["balance"]) - (toplam_gelir - toplam_gider) if firm else 0.0
    bakiye_degisimi = []
    for row in tx_rows:
        tutar = float(row["amount"] or 0)
        onceki_bakiye = bakiye
        yeni_bakiye = bakiye + tutar if row["type"] == "income" else bakiye - tutar
        bakiye_degisimi.append({"onceki_bakiye": onceki_bakiye, "yeni_bakiye": yeni_bakiye})
        bakiye = yeni_bakiye
    # Sonuçları ekrana verirken en yeni en üstte olsun istiyorsanız ters çevirin
    tx_rows = tx_rows[::-1]
    bakiye_degisimi = bakiye_degisimi[::-1]
    tx_bakiye_list = list(zip(tx_rows, bakiye_degisimi))
    return render_template("firm_detail.html", app_title=APP_TITLE, firm=firm, tx_bakiye_list=tx_bakiye_list, start_date=start_date.strftime("%Y-%m-%d") if start_date else "", end_date=end_date.strftime("%Y-%m-%d") if end_date else "")

@app.route("/firms", methods=["GET", "POST"])
def firms():
    df = read_sheet("firms")
    rows = df.to_dict(orient="records") if not df.empty else []
    edit_mode = False
    edit_firm = None
    original_firm_id = None
    # Düzenleme için GET parametresi
    edit_id = request.args.get("edit")
    if edit_id:
        firm_row = df[df["firm_id"] == edit_id]
        if not firm_row.empty:
            edit_firm = firm_row.iloc[0].to_dict()
            edit_mode = True
            original_firm_id = edit_id
    if request.method == "POST":
        if request.form.get("edit_mode"):  # Güncelleme
            original_firm_id = request.form.get("original_firm_id")
            firm_id = request.form.get("firm_id")
            firm_name = request.form.get("firm_name")
            balance = float((request.form.get("balance") or "0").replace(",", "."))
            idx = df[df["firm_id"] == original_firm_id].index
            if not idx.empty:
                df.loc[idx[0], ["firm_id", "firm_name", "balance"]] = [firm_id, firm_name, balance]
                write_sheet("firms", df)
                flash("Firma güncellendi.", "success")
            return redirect(url_for("firms"))
        else:  # Ekleme
            firm_id = request.form.get("firm_id")
            firm_name = request.form.get("firm_name")
            balance = float((request.form.get("balance") or "0").replace(",", "."))
            if not df[df["firm_id"] == firm_id].empty:
                flash("Bu ID ile firma zaten var!", "danger")
            else:
                append_row("firms", {"firm_id": firm_id, "firm_name": firm_name, "balance": balance})
                flash("Firma eklendi.", "success")
            return redirect(url_for("firms"))
    return render_template("firms.html", app_title=APP_TITLE, rows=rows, edit_mode=edit_mode, edit_firm=edit_firm, original_firm_id=original_firm_id)

@app.route("/firms/delete/<firm_id>", methods=["POST"])
def delete_firm(firm_id):
    df = read_sheet("firms")
    idx = df[df["firm_id"] == firm_id].index
    if not idx.empty:
        df = df.drop(idx)
        write_sheet("firms", df)
        flash("Firma silindi.", "success")
    else:
        flash("Firma bulunamadı.", "danger")
    return redirect(url_for("firms"))

# --------------------------------------------------------------------------------------
# ÇALIŞTIRMA
# --------------------------------------------------------------------------------------
if __name__ == "__main__":
    # ilk açılışta yapıyı hazırla
    ensure_workbook_once()
    # Debug reloader gereksiz istek yapmasın; threaded=True navigasyonu akıcılaştırır
    app.run(host="0.0.0.0", port=5010, debug=False, use_reloader=False, threaded=True)
