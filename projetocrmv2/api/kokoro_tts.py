"""Gera um WAV pt-BR com o Kokoro-82M.

Uso: python kokoro_tts.py <texto.txt> <saida.wav> [voz] [velocidade] [pausa_ms]

A pausa entre frases e o que da "respiro" a voz (prosodia), em vez de leitura corrida.
"""
import re
import sys

import numpy as np
import soundfile as sf
from kokoro import KPipeline

SAMPLE_RATE = 24000


def dividir_frases(texto: str) -> list[str]:
    partes = re.split(r"(?<=[.!?…])\s+", texto.strip())
    return [p.strip() for p in partes if p.strip()]


def main() -> int:
    if len(sys.argv) < 3:
        return 2

    text_file, out_file = sys.argv[1], sys.argv[2]
    voice = sys.argv[3] if len(sys.argv) > 3 else "pf_dora"
    speed = float(sys.argv[4]) if len(sys.argv) > 4 else 1.0
    pausa_ms = int(sys.argv[5]) if len(sys.argv) > 5 else 0

    with open(text_file, "r", encoding="utf-8") as fh:
        texto = fh.read().strip()
    if not texto:
        return 2

    pipeline = KPipeline(lang_code="p")
    silencio = np.zeros(int(SAMPLE_RATE * pausa_ms / 1000), dtype=np.float32)
    pedacos: list[np.ndarray] = []

    for frase in dividir_frases(texto):
        audio_frase = None
        for _, _, audio in pipeline(frase, voice=voice, speed=speed, split_pattern=None):
            trecho = np.asarray(audio, dtype=np.float32)
            audio_frase = trecho if audio_frase is None else np.concatenate([audio_frase, trecho])
        if audio_frase is None or audio_frase.size == 0:
            continue
        if pedacos and silencio.size:
            pedacos.append(silencio)
        pedacos.append(audio_frase)

    if not pedacos:
        return 1

    sf.write(out_file, np.concatenate(pedacos), SAMPLE_RATE)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
