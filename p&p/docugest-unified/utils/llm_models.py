"""
Selección de modelo de generación de Gemini con fallback.

Dos problemas reales que resuelve este módulo:

1. Google retira modelos sin previo aviso util: gemini-2.5-flash-lite y
   gemini-2.5-flash devuelven 404 "no longer available to new users" aunque
   ListModels los siga listando. O sea que consultar el catálogo NO alcanza:
   hay que descubrirlo en la llamada real.

2. El free tier da RPM por modelo, con buckets independientes (15 RPM en los
   flash-lite). Ante un 429 conviene desbordar al siguiente candidato en vez de
   fallarle al usuario.

Por eso la lista de candidatos de utils/config.py se recorre en orden y se
descarta de forma permanente sólo lo que está retirado; el 429 es transitorio.
"""

import logging
from functools import lru_cache
from typing import List, Optional, Sequence, Set

import google.generativeai as genai

from utils.config import GOOGLE_API_KEY

logger = logging.getLogger(__name__)

# Este módulo se importa antes que utils.rag / utils.tags_glossary_llm, así que
# configura la API key por su cuenta en vez de depender del orden de imports.
genai.configure(api_key=GOOGLE_API_KEY)

# Modelos que devolvieron "no disponible" en esta instancia: no se reintentan.
_DISABLED: Set[str] = set()

# Fragmentos de error de modelo retirado / inexistente (permanente).
_UNAVAILABLE_MARKERS = (
    "notfound",
    "404",
    "not found",
    "no longer available",
    "is not supported",
)

# Fragmentos de rate limit / cuota agotada (transitorio).
_RATE_LIMIT_MARKERS = (
    "resourceexhausted",
    "toomanyrequests",
    "429",
    "exceeded your current quota",
    "quota exceeded",
    "rate limit",
)


def _normalize(model_name: str) -> str:
    """Devuelve el nombre siempre con prefijo 'models/'."""
    name = (model_name or "").strip()
    if not name:
        return name
    return name if name.startswith("models/") else f"models/{name}"


def _normalized(candidates: Sequence[str]) -> List[str]:
    return [_normalize(c) for c in candidates if c and c.strip()]


def _ordered_candidates(candidates: Sequence[str]) -> List[str]:
    """Candidatos en orden de preferencia, salteando los ya descartados."""
    normalized = _normalized(candidates)
    usable = [c for c in normalized if c not in _DISABLED]
    # Si se descartaron todos, reintentar la lista completa antes de rendirse.
    return usable or normalized


def active_model(candidates: Sequence[str], label: str = "gemini") -> str:
    """Modelo que se usará en la próxima llamada."""
    ordered = _ordered_candidates(candidates)
    if not ordered:
        raise ValueError(f"Lista de modelos vacía para '{label}'")
    return ordered[0]


def _is_model_unavailable(exc: Exception) -> bool:
    message = f"{type(exc).__name__} {exc}".lower()
    return any(marker in message for marker in _UNAVAILABLE_MARKERS)


def _is_rate_limited(exc: Exception) -> bool:
    message = f"{type(exc).__name__} {exc}".lower()
    return any(marker in message for marker in _RATE_LIMIT_MARKERS)


def generate_content(
    candidates: Sequence[str],
    label: str,
    prompt: str,
    generation_config=None,
):
    """
    Genera contenido probando los candidatos en orden hasta que uno responda.

    - 404 / "no longer available": el modelo queda descartado para el resto de
      la vida del proceso (es permanente, no tiene sentido reintentarlo).
    - 429: se desborda al siguiente candidato, pero sin descartar nada — la
      próxima consulta vuelve a arrancar por el preferido.
    - Cualquier otro error (red, safety, API key inválida) se propaga tal cual.
    """
    ordered = _ordered_candidates(candidates)
    if not ordered:
        raise ValueError(f"Lista de modelos vacía para '{label}'")

    last_index = len(ordered) - 1
    for index, model_name in enumerate(ordered):
        try:
            model = genai.GenerativeModel(model_name)
            response = model.generate_content(prompt, generation_config=generation_config)
            if index > 0:
                logger.info(f"[{label}] Respondió '{model_name}' (fallback #{index})")
            return response
        except Exception as exc:
            if index >= last_index:
                logger.error(
                    f"[{label}] Ningún modelo disponible. Último intento "
                    f"'{model_name}': {type(exc).__name__}: {exc}"
                )
                raise

            siguiente = ordered[index + 1]

            if _is_model_unavailable(exc):
                _DISABLED.add(model_name)
                logger.warning(
                    f"[{label}] '{model_name}' retirado por Google "
                    f"({type(exc).__name__}). Descartado; se usa '{siguiente}'."
                )
                continue

            if _is_rate_limited(exc):
                logger.warning(
                    f"[{label}] '{model_name}' sin cuota RPM ahora. "
                    f"Desbordando a '{siguiente}'."
                )
                continue

            raise


@lru_cache(maxsize=1)
def _catalog() -> frozenset:
    """Modelos con generateContent según ListModels (sólo informativo)."""
    try:
        return frozenset(
            _normalize(model.name)
            for model in genai.list_models()
            if "generateContent" in getattr(model, "supported_generation_methods", [])
        )
    except Exception as exc:
        logger.warning(
            f"No se pudo listar modelos de Gemini ({type(exc).__name__}: {exc})"
        )
        return frozenset()


def reset_cache() -> None:
    """Limpia el estado (útil en tests o tras rotar la API key)."""
    _DISABLED.clear()
    _catalog.cache_clear()


def diagnostics(candidates: Optional[Sequence[str]] = None) -> dict:
    """
    Info de modelos para el endpoint /api/modelos.

    Ojo: 'catalogo' viene de ListModels, que sigue listando modelos retirados.
    El dato confiable es 'descartados', que surge de las llamadas reales.
    """
    result = {
        "catalogo": sorted(_catalog()),
        "descartados_en_runtime": sorted(_DISABLED),
    }
    if candidates:
        result["candidatos"] = _normalized(candidates)
        result["en_uso"] = active_model(candidates)
    return result
