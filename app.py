import os
import pandas as pd
from datetime import datetime
from dateutil.relativedelta import relativedelta
from flask import Flask, render_template, request, redirect, url_for, flash

APP_TITLE = "Bütçe Yönetimi (Excel Tabanlı)"
EXCEL_PATH = os.path.join(os.path.dirname(__file__), "data", "budget.xlsx")

SHEETS_DEF = {
    "transactions": ["date", "type", "category", "description", "amount", "firm_id", "payment_type"],
    "employees": ["employee_id", "name", "title", "base_salary"],
    "salaries": ["date", "employee_id", "employee_name", "gross_amount", "notes"],
    "firms": ["firm_id", "firm_name", "balance"],
}

def read_sheet(sheet: str) -> pd.DataFrame:
    try:
        xl = pd.ExcelFile(EXCEL_PATH)
        if sheet in xl.sheet_names:
            df = xl.parse(sheet)
            df = df.reindex(columns=SHEETS_DEF[sheet])
            return df.fillna("")
        else:
            return pd.DataFrame(columns=SHEETS_DEF[sheet])
    except Exception:
        return pd.DataFrame(columns=SHEETS_DEF[sheet])

def write_sheet(sheet: str, df: pd.DataFrame) -> None:
    try:
        with pd.ExcelWriter(EXCEL_PATH, mode="a", if_sheet_exists="replace", engine="openpyxl") as writer:
            df = df.reindex(columns=SHEETS_DEF[sheet])
            df.to_excel(writer, sheet_name=sheet, index=False)
    except Exception:
        with pd.ExcelWriter(EXCEL_PATH, mode="w", engine="openpyxl") as writer:
            for s, cols in SHEETS_DEF.items():
                empty_df = pd.DataFrame(columns=cols)
                empty_df.to_excel(writer, sheet_name=s, index=False)
            df = df.reindex(columns=SHEETS_DEF[sheet])
            df.to_excel(writer, sheet_name=sheet, index=False)

def append_row(sheet: str, row_dict: dict) -> None:
    df = read_sheet(sheet)
    df = pd.concat([df, pd.DataFrame([row_dict])], ignore_index=True)
    write_sheet(sheet, df)

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
    tx = read_sheet("transactions")
    salaries = read_sheet("salaries")
    employees = read_sheet("employees")

    if request.method == "POST":
        start_date_str = request.form.get("start_date")
        end_date_str = request.form.get("end_date")
    else:
        start_date_str = request.args.get("start_date")
        end_date_str = request.args.get("end_date")

    today = datetime.today()
    start_date = datetime.strptime(start_date_str, "%Y-%m-%d") if start_date_str else today.replace(day=1)
    end_date = datetime.strptime(end_date_str, "%Y-%m-%d") if end_date_str else today.replace(day=1) + relativedelta(months=1)

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
    if request.method == "POST":
        start_date_str = request.form.get("start_date")
        end_date_str = request.form.get("end_date")
    else:
        start_date_str = request.args.get("start_date")
        end_date_str = request.args.get("end_date")
    today = datetime.today()
    start_date = datetime.strptime(start_date_str, "%Y-%m-%d") if start_date_str else None
    end_date = datetime.strptime(end_date_str, "%Y-%m-%d") if end_date_str else None
    if start_date and end_date:
        filtered_tx = tx[(tx["date"] >= start_date) & (tx["date"] <= end_date)] if not tx.empty else pd.DataFrame()
    else:
        filtered_tx = tx if not tx.empty else pd.DataFrame()
    filtered_tx = filtered_tx.sort_values("date", ascending=True)
    tx_rows = filtered_tx.to_dict(orient="records") if not filtered_tx.empty else []
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
    edit_id = request.args.get("edit")
    if edit_id:
        firm_row = df[df["firm_id"] == edit_id]
        if not firm_row.empty:
            edit_firm = firm_row.iloc[0].to_dict()
            edit_mode = True
            original_firm_id = edit_id
    if request.method == "POST":
        if request.form.get("edit_mode"):
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
        else:
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

if __name__ == "__main__":
    port = int(os.environ.get("PORT", 10000))
    app.run(host="0.0.0.0", port=port)
