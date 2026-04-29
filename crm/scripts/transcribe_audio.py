import json
import os
import sys
import traceback


def log_debug(message):
    try:
        log_path = os.path.join(os.path.dirname(os.path.dirname(__file__)), "data", "python_whisper_debug.log")
        os.makedirs(os.path.dirname(log_path), exist_ok=True)
        with open(log_path, "a", encoding="utf-8") as fh:
            fh.write(message + "\n")
    except Exception:
        pass


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"ok": False, "error": "Arquivo de audio nao informado"}))
        return 1

    audio_path = sys.argv[1]
    model_name = sys.argv[2] if len(sys.argv) > 2 else "base"
    log_debug(f"start audio={audio_path} model={model_name} python={sys.executable}")

    try:
        from faster_whisper import WhisperModel

        log_debug("loading faster-whisper model")
        model = WhisperModel(model_name, device="cpu", compute_type="int8")
        log_debug("transcribing with faster-whisper")
        segments, _ = model.transcribe(audio_path, language="pt", vad_filter=True)
        text = " ".join(segment.text.strip() for segment in segments).strip()
        log_debug(f"success faster-whisper chars={len(text)}")
        print(json.dumps({"ok": True, "text": text}, ensure_ascii=False))
        return 0
    except ModuleNotFoundError:
        log_debug("faster-whisper not installed")
        pass
    except Exception as exc:
        log_debug("faster-whisper error: " + repr(exc))
        log_debug(traceback.format_exc())
        print(json.dumps({"ok": False, "error": f"faster-whisper: {exc}"}, ensure_ascii=False))
        return 1

    try:
        import whisper

        log_debug("loading openai-whisper model")
        model = whisper.load_model(model_name)
        log_debug("transcribing with openai-whisper")
        result = model.transcribe(audio_path, language="pt", fp16=False)
        log_debug(f"success openai-whisper chars={len((result.get('text') or '').strip())}")
        print(json.dumps({"ok": True, "text": (result.get("text") or "").strip()}, ensure_ascii=False))
        return 0
    except ModuleNotFoundError:
        log_debug("openai-whisper not installed")
        print(json.dumps({
            "ok": False,
            "error": "Instale faster-whisper ou openai-whisper no Python do servidor"
        }, ensure_ascii=False))
        return 1
    except Exception as exc:
        log_debug("openai-whisper error: " + repr(exc))
        log_debug(traceback.format_exc())
        print(json.dumps({"ok": False, "error": f"openai-whisper: {exc}"}, ensure_ascii=False))
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
