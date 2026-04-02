#!/usr/bin/env python3
"""
E-Nabız / Hastane PDF Tahlil Parser
Yüklenen PDF dosyasından kritik lab değerlerini regex ile çıkarır.
Kullanım: python3 parse_pdf.py /path/to/file.pdf
Çıktı: JSON formatında çıkarılan değerler
"""
import sys
import json
import re

try:
    import pdfplumber
except ImportError:
    print(json.dumps({"error": "pdfplumber kurulu degil"}))
    sys.exit(1)

def extract_lab_values(pdf_path):
    """PDF'den tüm metni çıkar ve regex ile lab değerlerini parse et."""
    text = ""
    try:
        with pdfplumber.open(pdf_path) as pdf:
            for page in pdf.pages:
                page_text = page.extract_text()
                if page_text:
                    text += page_text + "\n"
    except Exception as e:
        return {"error": str(e)}

    if not text.strip():
        return {"error": "PDF'den metin cikarilmadi"}

    result = {}

    # Patterns: "Test Adı    DEĞER  birim  referans"
    patterns = {
        "glucose":           r"Glukoz.*?(\d+\.?\d*)\s*mg",
        "cholesterol_total": r"(?<![HL])\bKolesterol\s+(\d+\.?\d*)\s*mg",
        "ldl":               r"LDL\s+[Kk]olesterol.*?(\d+\.?\d*)\s*mg",
        "hdl":               r"HDL\s+[Kk]olesterol.*?(\d+\.?\d*)\s*mg",
        "triglyceride":      r"Trigliserid\s+(\d+\.?\d*)\s*mg",
        "hba1c":             r"[Hh]b\s*A1c\)?\s+(\d+\.?\d*)\s*%",
        "iron":              r"Demir\s+\(serum\)\s+(\d+\.?\d*)",
        "ferritin":          r"Ferritin\s+(\d+\.?\d*)",
        "b12":               r"[Vv]itamin\s*B12\s+(\d+\.?\d*)",
        "vitamin_d":         r"[Vv]itamin\s*D.*?(\d+\.?\d*)\s*(ng|µg)",
        "tsh":               r"TSH\s+(\d+\.?\d*)",
        "hemoglobin":        r"HGB\s+(\d+\.?\d*)",
        "creatinine":        r"Kreatinin\s+(\d+\.?\d*)",
        "alt_sgpt":          r"(?:ALT|Alanin\s+aminotransferaz).*?(\d+\.?\d*)\s*U/L",
        "ast_sgot":          r"(?:AST|Aspartat\s+transaminaz).*?(\d+\.?\d*)\s*U/L",
    }

    for key, pattern in patterns.items():
        match = re.search(pattern, text, re.IGNORECASE)
        if match:
            try:
                result[key] = float(match.group(1))
            except (ValueError, IndexError):
                pass

    # Tarih çıkarma
    date_match = re.search(r"Tarih:\s*(\d{2})\.(\d{2})\.(\d{4})", text)
    if date_match:
        result["report_date"] = f"{date_match.group(3)}-{date_match.group(2)}-{date_match.group(1)}"

    # Ad/Soyad çıkarma
    name_match = re.search(r"Adı/Soyadı:\s*([A-ZÇĞİÖŞÜa-zçğıöşü\s]+?)(?:Cinsiyet|$)", text)
    if name_match:
        result["patient_name"] = name_match.group(1).strip()

    return result

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "PDF dosya yolu belirtilmedi"}))
        sys.exit(1)

    values = extract_lab_values(sys.argv[1])
    print(json.dumps(values, ensure_ascii=False))
