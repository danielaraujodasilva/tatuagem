import sys, re, json

def abrir():
    # pymupdf e o nome novo da biblioteca; fitz ainda funciona mas emite aviso.
    for nome in ("pymupdf", "fitz"):
        try:
            return __import__(nome)
        except Exception:
            continue
    return None

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"ok": False, "message": "informe o arquivo pdf"}))
        return
    fitz = abrir()
    if fitz is None:
        print(json.dumps({"ok": False, "message": "sem leitor de PDF"}))
        return
    try:
        doc = fitz.open(sys.argv[1])
        texto = " ".join(pagina.get_text() for pagina in doc)
        doc.close()
    except Exception as erro:
        print(json.dumps({"ok": False, "message": str(erro)}))
        return
    print(json.dumps({"ok": True, "texto": re.sub(r"\s+", " ", texto)}))

main()