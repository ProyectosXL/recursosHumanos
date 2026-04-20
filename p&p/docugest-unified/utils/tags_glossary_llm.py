"""
Pipeline LLM para tags y glosario (modo rolling update).

Objetivo:
- Integrar clasificacion por LLM sin romper el pipeline legacy.
- Mantener costo bajo con prompts compactos y limites estrictos.
- Devolver JSON estructurado para consumo seguro.
"""

import hashlib
import json
import logging
import re
from typing import Any, Dict, List, Optional

import google.generativeai as genai

from utils.config import (
    GOOGLE_API_KEY,
    TAGS_GLOSSARY_LLM_DOC_EXCERPT_CHARS,
    TAGS_GLOSSARY_LLM_ENABLE_FEWSHOT,
    TAGS_GLOSSARY_LLM_MAX_CANDIDATES,
    TAGS_GLOSSARY_LLM_MAX_DEFINITION_CHARS,
    TAGS_GLOSSARY_LLM_MAX_GLOSSARY,
    TAGS_GLOSSARY_LLM_MAX_OUTPUT_TOKENS,
    TAGS_GLOSSARY_LLM_MAX_TAGS,
    TAGS_GLOSSARY_LLM_MODEL,
    TAGS_GLOSSARY_LLM_TEMPERATURE,
)

logger = logging.getLogger(__name__)

# Configuracion global Gemini para este modulo.
genai.configure(api_key=GOOGLE_API_KEY)


# Ejemplos de estilo de glosario entregados por el usuario (subset corto para no inflar tokens).
GLOSSARY_STYLE_EXAMPLES = [
    {
        "termino": "SLA",
        "definicion": "Acuerdo de Nivel de Servicio. Compromiso pactado con clientes respecto al servicio prestado.",
    },
    {
        "termino": "KPI",
        "definicion": "Indicador Clave de Rendimiento. Metrica usada para cuantificar rendimiento frente a objetivos.",
    },
    {
        "termino": "ROI",
        "definicion": "Retorno de Inversion. Mide la eficiencia de una inversion.",
    },
    {
        "termino": "Compliance",
        "definicion": "Funcion que identifica, monitorea y reporta riesgo de cumplimiento normativo.",
    },
    {
        "termino": "GDPR",
        "definicion": "Reglamento General de Proteccion de Datos de la Union Europea.",
    },
    {
        "termino": "Cloud Computing",
        "definicion": "Uso de servidores remotos en internet para almacenar y procesar datos.",
    },
    {
        "termino": "Onboarding",
        "definicion": "Proceso de integracion de una persona a la organizacion.",
    },
    {
        "termino": "SEO",
        "definicion": "Tecnicas para mejorar visibilidad en motores de busqueda.",
    },
]


# Ejemplos de estilo de tags entregados por el usuario (subset corto).
TAG_STYLE_EXAMPLES = [
    {
        "documento": "Prompt para creacion de desarrollos",
        "tags": ["HTML", "SSMS", "PHP", "css", "class", "JavaScript", "SQL", "Server", "Estandares"],
    },
    {
        "documento": "Carga y gestion de proyectos de desarrollo en Trello",
        "tags": ["Proyectos", "Cards", "Revision", "Pendientes", "Etiqueta", "desarrollo", "funcionalidades"],
    },
    {
        "documento": "Instructivo para solicitud de compra",
        "tags": ["SOLICITUD", "COMPRA", "PROVEEDORES", "Articulos", "Requisitos", "gerentes"],
    },
]


# Cache en memoria para bajar costo en documentos repetidos durante la misma sesion del worker.
_CLASSIFICATION_CACHE: Dict[str, Dict[str, Any]] = {}
_DEFINITION_CACHE: Dict[str, Dict[str, Any]] = {}

# Estrategia de selección de glosario:
# - llm_first: más libertad al modelo, gate mínimo anti-ruido
# - strict: mantiene umbrales más conservadores
GLOSSARY_SELECTION_STRATEGY = "llm_first"


class TagsGlossaryLLMError(Exception):
    """Error controlado de pipeline LLM de tags/glosario."""


def _normalize_space(text: str) -> str:
    if not text:
        return ""
    return re.sub(r"\s+", " ", text).strip()


def _normalize_term_key(term: str) -> str:
    return _normalize_space(term).lower()


def _safe_excerpt(text: str, max_chars: int) -> str:
    cleaned = _normalize_space(text)
    if len(cleaned) <= max_chars:
        return cleaned
    return cleaned[:max_chars]


def _safe_json_load(raw: str) -> Dict[str, Any]:
    """Intenta parsear JSON incluso si el modelo agrega texto extra o ligeros errores."""
    if not raw:
        raise TagsGlossaryLLMError("Respuesta vacia del modelo")

    raw = raw.strip()

    # Limpiar wrappers comunes.
    if raw.startswith("```"):
        raw = re.sub(r"^```(?:json)?", "", raw).strip()
        raw = re.sub(r"```$", "", raw).strip()

    # Normalizar comillas tipograficas y espacios no estandar.
    raw = raw.replace("“", '"').replace("”", '"').replace("’", "'")
    raw = raw.replace("\u00A0", " ")

    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        pass

    # Intentar remover comas colgantes.
    repaired = re.sub(r",\s*([}\]])", r"\1", raw)
    try:
        return json.loads(repaired)
    except json.JSONDecodeError:
        pass

    # Buscar cualquier objeto JSON decodificable dentro del texto.
    decoder = json.JSONDecoder()
    for idx, ch in enumerate(raw):
        if ch != "{":
            continue
        try:
            obj, _ = decoder.raw_decode(raw[idx:])
            if isinstance(obj, dict):
                return obj
        except json.JSONDecodeError:
            continue

    # Fallback regex (ultimo intento).
    match = re.search(r"\{[\s\S]*\}", raw)
    if not match:
        raise TagsGlossaryLLMError("No se encontro bloque JSON en respuesta")

    try:
        return json.loads(re.sub(r",\s*([}\]])", r"\1", match.group(0)))
    except json.JSONDecodeError as exc:
        raise TagsGlossaryLLMError(f"JSON invalido en respuesta del modelo: {exc}") from exc


def _llm_generate_json(prompt: str) -> Dict[str, Any]:
    """Llamada unificada a Gemini con salida JSON."""
    model = genai.GenerativeModel(TAGS_GLOSSARY_LLM_MODEL)
    response = model.generate_content(
        prompt,
        generation_config=genai.GenerationConfig(
            temperature=TAGS_GLOSSARY_LLM_TEMPERATURE,
            max_output_tokens=TAGS_GLOSSARY_LLM_MAX_OUTPUT_TOKENS,
            response_mime_type="application/json",
        ),
    )

    text = getattr(response, "text", "") or ""
    return _safe_json_load(text)


def _compact_candidates(candidates: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
    compact = []
    for candidate in candidates[:TAGS_GLOSSARY_LLM_MAX_CANDIDATES]:
        term = _normalize_space(str(candidate.get("termino", "")))
        # Evitar términos con caracteres que suelen romper JSON del modelo.
        if not term:
            continue
        if any(c in term for c in ['"', "\\", "\n", "\r", "\t", "{", "}", "[", "]"]):
            continue
        if len(term) > 80:
            continue

        compact.append(
            {
                "termino": term,
                "score": round(float(candidate.get("score", 0.0)), 4),
                "occurrences": int(candidate.get("occurrences", 0) or 0),
                "tipologia": candidate.get("tipologia", "default"),
                "contexto": _safe_excerpt(candidate.get("contexto", ""), 180),
            }
        )
    return compact


def _classification_cache_key(
    excerpt: str,
    sector: Optional[str],
    candidates: List[Dict[str, Any]],
    legacy_tags: List[str],
    legacy_glossary: List[str],
) -> str:
    data = {
        "excerpt": excerpt,
        "sector": sector,
        "candidates": candidates,
        "legacy_tags": legacy_tags[:10],
        "legacy_glossary": legacy_glossary[:10],
        "model": TAGS_GLOSSARY_LLM_MODEL,
    }
    payload = json.dumps(data, ensure_ascii=True, sort_keys=True)
    return hashlib.sha1(payload.encode("utf-8")).hexdigest()


def _passes_glossary_quality_gate(candidate_info: Dict[str, Any]) -> bool:
    """
    Gate de calidad para términos de glosario seleccionados por LLM.

    No usa listas hardcodeadas de palabras: aplica umbrales por score/tipologia
    ya calculados por el pipeline para asegurar especificidad mínima.
    """
    score = float(candidate_info.get("score", 0.0) or 0.0)
    typology = str(candidate_info.get("tipologia", "default") or "default")
    term = _normalize_space(str(candidate_info.get("termino", "")))

    if not term:
        return False

    # Gate mínimo anti-ruido para modo con mayor libertad del LLM.
    if GLOSSARY_SELECTION_STRATEGY == "llm_first":
        if term.isdigit():
            return False
        if len(term) <= 2 and typology not in {"acronym", "short_tech"}:
            return False
        return score >= 0.45

    # Umbrales por tipología para elevar precisión en glosario raro.
    min_score_by_typology = {
        "acronym": 0.52,
        "techshape": 0.55,
        "short_tech": 0.58,
        "rare_term": 0.63,
        "default": 0.68,
    }
    min_score = min_score_by_typology.get(typology, 0.68)

    # Tokens ultra-cortos no técnicos no deben entrar al glosario.
    if len(term) <= 3 and typology not in {"acronym", "short_tech"}:
        return False

    return score >= min_score


def classify_candidates_with_llm(
    document_text: str,
    sector: Optional[str],
    candidates: List[Dict[str, Any]],
    legacy_tags: List[str],
    legacy_glossary: List[str],
) -> Dict[str, Any]:
    """
    Clasifica candidatos en tags y terminos raros de glosario.

    Returns:
        {
          "tags": ["..."],
          "glossary_terms": ["..."],
          "raw": {...}
        }
    """
    excerpt = _safe_excerpt(document_text, TAGS_GLOSSARY_LLM_DOC_EXCERPT_CHARS)
    compact_candidates = _compact_candidates(candidates)

    cache_key = _classification_cache_key(excerpt, sector, compact_candidates, legacy_tags, legacy_glossary)
    if cache_key in _CLASSIFICATION_CACHE:
        return _CLASSIFICATION_CACHE[cache_key]

    fewshot_block = ""
    if TAGS_GLOSSARY_LLM_ENABLE_FEWSHOT:
        fewshot_block = (
            "EJEMPLOS DE ESTILO DE TAGS (resumido):\n"
            + json.dumps(TAG_STYLE_EXAMPLES, ensure_ascii=True)
            + "\n\n"
            + "EJEMPLOS DE ESTILO DE GLOSARIO (resumido):\n"
            + json.dumps(GLOSSARY_STYLE_EXAMPLES, ensure_ascii=True)
            + "\n\n"
        )

    prompt = f"""
Sos un clasificador de terminos para DocuGest.
Responde SOLO JSON valido.

Objetivo:
1) Seleccionar tags de negocio/tecnicos para el documento.
2) Seleccionar SOLO terminos raros o tecnicos especificos para glosario.

Lineamientos:
- Usa exclusivamente terminos presentes en el arreglo 'candidates'.
- Exclui personas, nombres propios de empleados y palabras genericas.
- No inventes terminos nuevos.
- Priorizá utilidad y coherencia semántica en contexto de negocio.
- Para glosario, decidí con criterio semántico del documento:
    - Incluir conceptos que aporten valor reutilizable al glosario.
    - Admitir términos contextuales si son relevantes para el dominio.
    - Evitar solo ruido evidente (tokens sueltos, basura de formato o URL).
- Limite tags: {TAGS_GLOSSARY_LLM_MAX_TAGS}
- Limite glosario: {TAGS_GLOSSARY_LLM_MAX_GLOSSARY}

Formato de salida obligatorio:
{{
    "tags": ["termino1", "termino2"],
    "glossary_terms": ["termino1", "termino2"]
}}

{fewshot_block}SECTOR: {sector or "N/A"}

TAGS LEGACY ACTUALES (referencia):
{json.dumps(legacy_tags[:20], ensure_ascii=True)}

GLOSARIO LEGACY ACTUAL (referencia):
{json.dumps(legacy_glossary[:10], ensure_ascii=True)}

EXTRACTO DEL DOCUMENTO:
{excerpt}

CANDIDATOS:
{json.dumps(compact_candidates, ensure_ascii=True)}
"""

    result = _llm_generate_json(prompt)
    tags = []
    glossary_terms = []

    candidate_set = {c["termino"] for c in compact_candidates if c.get("termino")}
    candidate_info_by_term = {
        c["termino"]: c
        for c in compact_candidates
        if c.get("termino")
    }

    for item in result.get("tags", [])[:TAGS_GLOSSARY_LLM_MAX_TAGS]:
        if isinstance(item, dict):
            term = _normalize_space(str(item.get("termino", "")))
        else:
            term = _normalize_space(str(item))
        if term and term in candidate_set and term not in tags:
            tags.append(term)

    for item in result.get("glossary_terms", [])[:TAGS_GLOSSARY_LLM_MAX_GLOSSARY]:
        if isinstance(item, dict):
            term = _normalize_space(str(item.get("termino", "")))
        else:
            term = _normalize_space(str(item))
        if term and term in candidate_set and term not in glossary_terms:
            if not _passes_glossary_quality_gate(candidate_info_by_term.get(term, {})):
                continue
            glossary_terms.append(term)

    parsed = {
        "tags": tags,
        "glossary_terms": glossary_terms,
        "raw": result,
    }
    _CLASSIFICATION_CACHE[cache_key] = parsed
    return parsed


def _definition_cache_key(
    sector: Optional[str],
    terms_payload: List[Dict[str, str]],
) -> str:
    data = {
        "sector": sector,
        "terms": terms_payload,
        "model": TAGS_GLOSSARY_LLM_MODEL,
    }
    payload = json.dumps(data, ensure_ascii=True, sort_keys=True)
    return hashlib.sha1(payload.encode("utf-8")).hexdigest()


def _infer_domain_from_context(context: str, sector: Optional[str]) -> str:
    """Infiere dominio semántico desde contexto para definiciones ambiguas."""
    text = _normalize_term_key(context)
    if sector:
        sector_norm = _normalize_term_key(sector)
        if sector_norm:
            return sector_norm

    domain_keywords = {
        "economia y finanzas": ["banco", "deposito", "credito", "cuenta", "interes", "caja", "pago", "cobro"],
        "tecnologia y desarrollo": ["git", "github", "api", "ssh", "repositorio", "commit", "branch", "codigo"],
        "rrhh": ["empleado", "onboarding", "reclutamiento", "legajo", "nomina", "desempeno"],
        "comercial y ventas": ["precio", "vidriera", "cartel", "venta", "cliente", "promocion", "etiqueta"],
        "operaciones": ["proceso", "flujo", "sucursal", "operacion", "control", "procedimiento"],
    }

    for domain, keywords in domain_keywords.items():
        if any(k in text for k in keywords):
            return domain

    return "negocio"


def _build_non_empty_fallback_definition(term: str, context: str, sector: Optional[str]) -> str:
    """Genera definición de respaldo no vacía y contextualmente orientada."""
    domain = _infer_domain_from_context(context, sector)
    term_clean = _normalize_space(term)
    return (
        f"En el contexto de {domain}, {term_clean} es un concepto operativo o tecnico "
        "utilizado en el proceso documentado."
    )


def _generate_missing_definitions_with_llm(
    sector: Optional[str],
    terms_context: List[Dict[str, str]],
) -> Dict[str, str]:
    """Segunda pasada de LLM para completar definiciones faltantes (siempre no vacías)."""
    if not terms_context:
        return {}

    prompt = f"""
Sos redactor de glosario corporativo.
Responde SOLO JSON valido.

Objetivo:
- Completar definiciones FALTANTES para terminos tecnicos.
- Cada termino debe tener definicion NO VACIA.
- La definicion debe ser general pero anclada al contexto.
- Si el termino es ambiguo, iniciar con: "En el contexto de <dominio>, ..."

Formato de salida:
{{
  "definitions": [
    {{"termino": "...", "definicion": "..."}}
  ]
}}

SECTOR: {sector or "N/A"}
TERMINOS:
{json.dumps(terms_context, ensure_ascii=True)}
"""

    result = _llm_generate_json(prompt)
    output = {}
    for item in result.get("definitions", []):
        term = _normalize_space(str(item.get("termino", "")))
        definition = _normalize_space(str(item.get("definicion", "")))
        if term and definition:
            output[term] = definition
    return output


def generate_contextual_definitions_with_llm(
    document_text: str,
    sector: Optional[str],
    terms_context: List[Dict[str, str]],
) -> Dict[str, Any]:
    """
    Genera definiciones SOLO desde contexto del documento.

    Input terms_context example:
      [{"termino": "SLA", "contexto": "..."}]

    Returns:
      {
        "definitions": [{"termino": "...", "definicion": "...", "needs_review": false}],
        "raw": {...}
      }
    """
    compact_terms = []
    for item in terms_context[:TAGS_GLOSSARY_LLM_MAX_GLOSSARY]:
        term = _normalize_space(str(item.get("termino", "")))
        ctx = _safe_excerpt(str(item.get("contexto", "")), 260)
        if term:
            compact_terms.append({"termino": term, "contexto": ctx})

    if not compact_terms:
        return {"definitions": [], "raw": {}}

    cache_key = _definition_cache_key(sector, compact_terms)
    if cache_key in _DEFINITION_CACHE:
        return _DEFINITION_CACHE[cache_key]

    fewshot_block = ""
    if TAGS_GLOSSARY_LLM_ENABLE_FEWSHOT:
        fewshot_block = (
            "EJEMPLOS DE ESTILO DE DEFINICION (breves):\n"
            + json.dumps(GLOSSARY_STYLE_EXAMPLES, ensure_ascii=True)
            + "\n\n"
        )

    excerpt = _safe_excerpt(document_text, TAGS_GLOSSARY_LLM_DOC_EXCERPT_CHARS)

    prompt = f"""
Sos redactor de glosario tecnico para DocuGest.
Responde SOLO JSON valido.

Objetivo:
- Definir terminos usando EXCLUSIVAMENTE el contexto del documento.
- La definicion NUNCA debe quedar vacia para terminos seleccionados.
- La definicion debe ser general, reutilizable y basada en el contexto detectado.
- Si el termino es ambiguo, comenzar con: "En el contexto de <dominio>, ..."

Reglas:
- No usar Wikipedia ni conocimiento externo.
- Definiciones en espanol, claras, maximo {TAGS_GLOSSARY_LLM_MAX_DEFINITION_CHARS} caracteres.
- Mantener tono corporativo y concreto.
- La definicion debe ser GENERALIZABLE y reusable en otros documentos.
- No describir pasos de la pantalla ni rutas de menu.
- Evitar frases hiper-contextuales como: "en este sistema", "en esta interfaz",
    "accesible desde", "se refiere a un formato".
- Estructura sugerida: "<termino> es ..." + funcion general del concepto.
- Si la evidencia es limitada, inferir una definicion breve pero util segun el contexto.

Formato obligatorio:
{{
  "definitions": [
    {{
      "termino": "...",
      "definicion": "...",
      "needs_review": false
    }}
  ]
}}

{fewshot_block}SECTOR: {sector or "N/A"}

EXTRACTO DOCUMENTO:
{excerpt}

TERMINOS A DEFINIR:
{json.dumps(compact_terms, ensure_ascii=True)}
"""

    result = _llm_generate_json(prompt)

    definitions = []
    term_set = {item["termino"] for item in compact_terms}
    context_by_term = {item["termino"]: item.get("contexto", "") for item in compact_terms}
    missing_terms = []

    for item in result.get("definitions", [])[:TAGS_GLOSSARY_LLM_MAX_GLOSSARY]:
        term = _normalize_space(str(item.get("termino", "")))
        if not term or term not in term_set:
            continue

        definition = _normalize_space(str(item.get("definicion", "")))
        needs_review = bool(item.get("needs_review", False))

        if not definition:
            missing_terms.append(term)
            continue

        if len(definition) > TAGS_GLOSSARY_LLM_MAX_DEFINITION_CHARS:
            definition = definition[:TAGS_GLOSSARY_LLM_MAX_DEFINITION_CHARS].rstrip()

        if definition and not definition.endswith("."):
            definition += "."

        definitions.append(
            {
                "termino": term,
                "definicion": definition,
                "confidence": float(item.get("confidence", 0.0) or 0.0),
                "needs_review": needs_review,
            }
        )

    # Completar definiciones faltantes con una segunda pasada LLM.
    if missing_terms:
        missing_payload = [
            {"termino": term, "contexto": context_by_term.get(term, "")}
            for term in missing_terms
        ]
        recovered_map = _generate_missing_definitions_with_llm(sector=sector, terms_context=missing_payload)

        for term in missing_terms:
            recovered = _normalize_space(recovered_map.get(term, ""))
            if not recovered:
                recovered = _build_non_empty_fallback_definition(term, context_by_term.get(term, ""), sector)

            if len(recovered) > TAGS_GLOSSARY_LLM_MAX_DEFINITION_CHARS:
                recovered = recovered[:TAGS_GLOSSARY_LLM_MAX_DEFINITION_CHARS].rstrip()
            if recovered and not recovered.endswith("."):
                recovered += "."

            definitions.append(
                {
                    "termino": term,
                    "definicion": recovered,
                    "confidence": 0.5,
                    "needs_review": False,
                }
            )

    parsed = {
        "definitions": definitions,
        "raw": result,
    }
    _DEFINITION_CACHE[cache_key] = parsed
    return parsed


def run_llm_tags_glossary_pipeline(
    document_text: str,
    sector: Optional[str],
    candidates: List[Dict[str, Any]],
    legacy_tags: List[str],
    legacy_glossary: List[str],
    term_context_map: Dict[str, str],
) -> Dict[str, Any]:
    """Orquesta clasificacion + definiciones en dos llamadas LLM."""
    try:
        normalized_context_map = {
            _normalize_term_key(k): v
            for k, v in term_context_map.items()
            if k
        }

        classification = classify_candidates_with_llm(
            document_text=document_text,
            sector=sector,
            candidates=candidates,
            legacy_tags=legacy_tags,
            legacy_glossary=legacy_glossary,
        )

        selected_glossary = classification.get("glossary_terms", [])
        terms_context = [
            {
                "termino": term,
                "contexto": normalized_context_map.get(_normalize_term_key(term), ""),
            }
            for term in selected_glossary
        ]

        definitions_result = generate_contextual_definitions_with_llm(
            document_text=document_text,
            sector=sector,
            terms_context=terms_context,
        )
        definitions = {
            item.get("termino", ""): item
            for item in definitions_result.get("definitions", [])
            if item.get("termino")
        }

        glossary_items = []
        for term in selected_glossary[:TAGS_GLOSSARY_LLM_MAX_GLOSSARY]:
            item = definitions.get(term, {})
            glossary_items.append(
                {
                    "termino": term,
                    "definicion": item.get("definicion", ""),
                    "confidence": float(item.get("confidence", 0.0) or 0.0),
                    "needs_review": bool(item.get("needs_review", False)),
                }
            )

        return {
            "ok": True,
            "model": TAGS_GLOSSARY_LLM_MODEL,
            "tags": classification.get("tags", [])[:TAGS_GLOSSARY_LLM_MAX_TAGS],
            "glossary": glossary_items,
            "classification_raw": classification.get("raw", {}),
            "definitions_raw": definitions_result.get("raw", {}),
        }

    except Exception as exc:
        logger.error(f"Error en pipeline LLM tags/glosario: {exc}")
        return {
            "ok": False,
            "model": TAGS_GLOSSARY_LLM_MODEL,
            "tags": [],
            "glossary": [],
            "error": str(exc),
        }
