import json
import sys


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"ok": False, "error": "Arquivo de audio nao informado"}))
        return 1

    audio_path = sys.argv[1]
    model_name = sys.argv[2] if len(sys.argv) > 2 else "base"

    try:
        from faster_whisper import WhisperModel

        model = WhisperModel(model_name, device="cpu", compute_type="int8")
        segments, _ = model.transcribe(audio_path, language="pt", vad_filter=True)
        text = " ".join(segment.text.strip() for segment in segments).strip()
        print(json.dumps({"ok": True, "text": text}, ensure_ascii=False))
        return 0
    except ModuleNotFoundError:
        pass
    except Exception as exc:
        print(json.dumps({"ok": False, "error": f"faster-whisper: {exc}"}, ensure_ascii=False))
        return 1

    try:
        import whisper

        model = whisper.load_model(model_name)
        result = model.transcribe(audio_path, language="pt", fp16=False)
        print(json.dumps({"ok": True, "text": (result.get("text") or "").strip()}, ensure_ascii=False))
        return 0
    except ModuleNotFoundError:
        print(json.dumps({
            "ok": False,
            "error": "Instale faster-whisper ou openai-whisper no Python do servidor"
        }, ensure_ascii=False))
        return 1
    except Exception as exc:
        print(json.dumps({"ok": False, "error": f"openai-whisper: {exc}"}, ensure_ascii=False))
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
