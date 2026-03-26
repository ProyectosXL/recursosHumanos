"""
DocuGest Unified API v1.0
Combina el sistema de Tags/Glosario (HF Space) con el servicio RAG (antes en Railway).

Endpoints Tags/Glosario (compatibles con TagsGlosarioAPI.php):
  POST /procesar           → genera tags y glosario desde PDF base64
  POST /procesar-upload    → genera tags y glosario desde archivo PDF (testing)

Endpoints RAG (compatibles con RagWebhook.php):
  POST /webhook/document-uploaded
  POST /webhook/document-updated
  POST /api/query
  POST /api/reindex/{document_id}
  DELETE /api/document/{document_id}
  GET  /api/stats
  POST /admin/refresh
  POST /admin/reset-database

Endpoints compartidos:
  GET /         → info del servicio
  GET /health   → health check combinado
  GET /docs     → Swagger UI

Scheduler interno: APScheduler con auto-refresh cada 24h desde PHP server.
ChromaDB se reconstruye automáticamente al iniciar (es efímero en HF Spaces).
"""

import logging
import time
import base64
import re
import unicodedata
import os
from io import BytesIO
from datetime import datetime
from typing import Optional, List, Dict
from urllib.parse import quote

import fitz  # PyMuPDF
import requests
from fastapi import FastAPI, HTTPException, File, UploadFile, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from pydantic import BaseModel

# Módulos del sistema Tags/Glosario (archivos planos en la raíz del Space)
from candidate_extraction import CandidateExtractor
from term_scoring import TermScorer

# Módulos RAG
from utils.config import (
    IS_CLOUD, IS_HF_SPACE, IS_RAILWAY,
    PORT, PHP_SERVER_URL, GOOGLE_API_KEY,
    CHROMA_DB_PATH, LOG_LEVEL, LOG_FILE
)
from utils.models import (
    DocumentUploadedWebhook, DocumentUpdatedWebhook,
    QueryRequest, QueryResponse,
    IndexingResponse, DeleteResponse,
    StatsResponse,
    ReindexRequest
)
from utils.pdf_utils import extract_text_from_pdf, extract_text_from_pdf_bytes, PDFProcessingError
from utils.chunking import create_chunks, generate_chunk_id, ChunkingError
from utils.embeddings import generate_embeddings_batch, test_gemini_connection, EmbeddingError
from utils.database import get_chroma_client, ChromaDBError
from utils.rag import generate_rag_response, RAGError

# ============================================================================
# CONFIGURACIÓN DE LOGGING
# ============================================================================

handlers = [logging.StreamHandler()]
if LOG_FILE and not IS_CLOUD:
    handlers.append(logging.FileHandler(LOG_FILE))

logging.basicConfig(
    level=LOG_LEVEL,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=handlers
)
logger = logging.getLogger(__name__)

# DEBUG mode (env var: DOCUGEST_DEBUG=1)
DEBUG = os.getenv('DOCUGEST_DEBUG', '0') == '1'

# ============================================================================
# FASTAPI APP
# ============================================================================

app = FastAPI(
    title="DocuGest Unified API",
    description="Tags, Glosario y RAG para el sistema de gestión documental DocuGest",
    version="1.0.0",
    docs_url="/docs",
    redoc_url="/redoc"
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ============================================================================
# ESTADO GLOBAL - MODELOS DE TAGS/GLOSARIO
# ============================================================================

extractor: Optional[CandidateExtractor] = None
scorer: Optional[TermScorer] = None


# ============================================================================
# EVENT HANDLERS
# ============================================================================

@app.on_event("startup")
async def startup_event():
    """
    Al iniciar el servicio:
    1. Carga modelos de Tags/Glosario (KeyBERT + spaCy)
    2. Inicializa ChromaDB para RAG
    3. Arranca scheduler de auto-refresh (solo en cloud)
    """
    global extractor, scorer

    logger.info("=" * 80)
    logger.info("Iniciando DocuGest Unified API v1.0")
    logger.info(f"  Entorno: {'HF Space' if IS_HF_SPACE else 'Railway' if IS_RAILWAY else 'local'}")
    logger.info(f"  Puerto: {PORT}")
    logger.info(f"  ChromaDB: {CHROMA_DB_PATH}")
    logger.info("=" * 80)

    # --- 1. Cargar modelos Tags/Glosario ---
    try:
        logger.info("Cargando modelos de Tags/Glosario...")
        extractor = CandidateExtractor()
        logger.info("  CandidateExtractor OK")
        scorer = TermScorer(mode="in_doc_candidate")
        logger.info("  TermScorer OK")
    except Exception as e:
        logger.error(f"Error al cargar modelos Tags/Glosario: {str(e)}")
        raise

    # --- 2. Inicializar RAG (Gemini + ChromaDB) ---
    try:
        logger.info("Verificando sistema de embeddings (Gemini)...")
        if not test_gemini_connection():
            logger.warning("No se pudo verificar la conexión con Gemini")

        logger.info("Inicializando ChromaDB...")
        chroma_client = get_chroma_client()
        stats = chroma_client.get_stats()
        logger.info(
            f"  ChromaDB OK - {stats['total_chunks']} chunks, "
            f"{stats['total_documentos_indexados']} documentos indexados"
        )
    except Exception as e:
        logger.error(f"Error al inicializar RAG: {str(e)}")
        raise

    # --- 3. Iniciar scheduler de auto-refresh (solo en cloud) ---
    if IS_CLOUD:
        try:
            from utils.scheduler import start_scheduler
            start_scheduler()
        except Exception as e:
            logger.error(f"Error al iniciar scheduler: {str(e)}")

    logger.info("=" * 80)
    logger.info("DocuGest Unified API lista")
    logger.info("=" * 80)


@app.on_event("shutdown")
async def shutdown_event():
    logger.info("Cerrando DocuGest Unified API...")


# ============================================================================
# PYDANTIC MODELS - TAGS/GLOSARIO
# ============================================================================

class DocumentRequest(BaseModel):
    """Request para procesar documento (desde PHP vía TagsGlosarioAPI.php)"""
    contenido_base64: str
    nombre_archivo: Optional[str] = "documento.pdf"
    sector: Optional[str] = None


class TerminoTag(BaseModel):
    termino: str
    score: float
    occurrences: int
    threshold: float
    threshold_type: str


class TerminoGlosario(BaseModel):
    termino: str
    score: float
    occurrences: int
    threshold: float
    threshold_type: str
    contexto: str
    definicion: Optional[str] = None


class DocumentResponse(BaseModel):
    tags: List[TerminoTag]
    glosario: List[TerminoGlosario]
    metadata: Dict
    timestamp: str


# ============================================================================
# FUNCIONES AUXILIARES - WIKIPEDIA
# ============================================================================

def _translate_to_spanish(text: str) -> str:
    """Traduce texto de inglés a español usando MyMemory API (gratuita, sin auth)."""
    if not text or len(text) < 10:
        return text
    try:
        url = "https://api.mymemory.translated.net/get"
        params = {"q": text[:500], "langpair": "en|es"}
        response = requests.get(url, params=params, timeout=3)
        if response.status_code == 200:
            data = response.json()
            translated = data.get("responseData", {}).get("translatedText", "")
            if translated and translated.lower() != text.lower():
                return translated
        return text
    except Exception:
        return text


def _fetch_from_wikipedia(term: str, lang: str = "es", context: str = "") -> str:
    """Obtiene extract de Wikipedia para un término."""
    try:
        variants = []
        context_lower = context.lower() if context else ""
        domain_keywords = {
            "version_control": ["git", "github", "commit", "merge", "repository", "repositorio"],
            "software": ["código", "code", "program", "software", "aplicación", "app"],
            "programming": ["python", "javascript", "java", "función", "function"],
            "computing": ["computer", "computadora", "sistema", "system"],
            "command": ["comando", "command", "terminal", "shell", "bash"]
        }
        detected_domains = [d for d, kws in domain_keywords.items() if any(kw in context_lower for kw in kws)]

        if lang == "en" and " " not in term:
            if detected_domains:
                for domain in detected_domains:
                    variants.append(f"{term}_({domain})")
                    if term.endswith("ch"):
                        variants.append(f"{term}ing_({domain})")
                variants.append(term.strip())
            else:
                variants.append(term.strip())
            for suffix in ["(software)", "(programming)", "(command)", "(computing)", "(technology)"]:
                if f"{term}{suffix}" not in variants:
                    variants.append(f"{term}{suffix}")
        else:
            variants.append(term.strip())

        for variant in variants:
            url = f"https://{lang}.wikipedia.org/api/rest_v1/page/summary/{quote(variant)}"
            response = requests.get(url, headers={"User-Agent": "DocuGest/1.0"}, timeout=3)
            if response.status_code == 200:
                data = response.json()
                if data.get("type") == "disambiguation":
                    continue
                extract = data.get("extract", "").strip()
                if extract:
                    return extract
        return ""
    except Exception:
        return ""


def _fetch_from_dictionary_api(term: str) -> str:
    """Obtiene definición desde dictionaryapi.dev (términos en inglés)."""
    try:
        url = f"https://api.dictionaryapi.dev/api/v2/entries/en/{quote(term.strip())}"
        response = requests.get(url, headers={"User-Agent": "DocuGest/1.0"}, timeout=3)
        if response.status_code == 200:
            data = response.json()
            if data:
                meanings = data[0].get("meanings", [])
                if meanings:
                    definitions = meanings[0].get("definitions", [])
                    if definitions:
                        definition = definitions[0].get("definition", "").strip()
                        pos = meanings[0].get("partOfSpeech", "")
                        pos_es = {"noun": "sustantivo", "verb": "verbo", "adjective": "adjetivo"}.get(pos, pos)
                        return f"({pos_es}) {definition}" if pos_es else definition
        return ""
    except Exception:
        return ""


def fetch_wikipedia_definition(term: str, lang: str = "es", context: str = "") -> str:
    """Sistema de fallback multi-API para definiciones. Siempre retorna en español."""
    result = _fetch_from_wikipedia(term, "es", context=context)
    if result:
        return result

    words = term.split()
    if len(words) >= 2:
        stopwords_es = {"de", "del", "la", "el", "los", "las", "en", "con", "por", "para", "y", "o"}
        important_words = sorted(
            [w for w in words if len(w) >= 3 and w.lower() not in stopwords_es],
            key=lambda w: (not w[0].isupper(), -len(w))
        )[:2]
        definitions = []
        for word in important_words:
            word_def = _fetch_from_wikipedia(word, "es", context=context)
            if not word_def:
                word_def = _fetch_from_wikipedia(word, "en", context=context)
                if word_def:
                    word_def = _translate_to_spanish(word_def)
            if not word_def:
                word_def = _fetch_from_dictionary_api(word)
                if word_def:
                    word_def = _translate_to_spanish(word_def)
            if word_def:
                definitions.append(f"{word}: {word_def.split('.')[0]}.")
        if definitions:
            return " ".join(definitions)

    result = _fetch_from_wikipedia(term, "en", context=context)
    if result:
        return _translate_to_spanish(result)

    result = _fetch_from_dictionary_api(term)
    if result:
        return _translate_to_spanish(result)

    return ""


def shorten_definition(text: str, max_chars: int = 250) -> str:
    """Normaliza y acorta definición de Wikipedia a 1-2 oraciones."""
    if not text:
        return ""
    text = unicodedata.normalize("NFKC", text)
    text = re.sub(r'\?\?+', '', text)
    text = re.sub(r'[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]', '', text)
    text = re.sub(r'\s+', ' ', text).strip()
    sentences = re.split(r'(?<=[.!?])\s+(?=[A-ZÁÉÍÓÚÑ])', text)
    result = ""
    for sentence in sentences[:3]:
        if len(result + sentence) <= max_chars:
            result += sentence + " "
        else:
            if not result:
                result = sentence[:max_chars]
                last_period = result.rfind('.')
                if last_period > 50:
                    result = result[:last_period + 1]
            break
    result = result.strip()
    if result and not result.endswith(('.', '!', '?')):
        result += "."
    return result if len(result) >= 20 else ""


def limpiar_definicion(texto: str, max_chars: int = 200) -> str:
    """Limpia y acorta texto de contexto del documento."""
    if not texto:
        return ""
    texto = unicodedata.normalize('NFKC', texto)
    texto = re.sub(r'\?+', '', texto)
    texto = re.sub(r'\s+', ' ', texto).strip()
    puntos = list(re.finditer(r'[.!]\s+[A-ZÁÉÍÓÚ]', texto))
    if len(puntos) >= 2:
        texto = texto[:puntos[1].start() + 1].strip()
    elif len(puntos) == 1:
        texto = texto[:puntos[0].start() + 1].strip()
    elif len(texto) > max_chars:
        texto = texto[:max_chars]
        ultimo_espacio = texto.rfind(' ')
        if ultimo_espacio > 0:
            texto = texto[:ultimo_espacio].strip()
    if texto and not texto.endswith('.'):
        texto += '.'
    return texto


def normalize_candidate(term: str) -> str:
    """Normalización para dedupe de candidatos."""
    normalized = unicodedata.normalize('NFKD', term)
    normalized = normalized.encode('ASCII', 'ignore').decode('ASCII')
    return re.sub(r'\s+', ' ', normalized.lower().strip())


def has_technical_shape(term: str) -> bool:
    """Detecta si término tiene forma técnica."""
    if term.isupper() and 2 <= len(term) <= 6:
        return True
    if re.search(r'[a-z][A-Z]', term):
        return True
    if '-' in term or '_' in term:
        return True
    return False


def _extract_short_technical_tokens(texto: str, max_candidates: int = 50) -> List[str]:
    """Extrae tokens cortos (2-6 chars) en contexto técnico sin listas hardcodeadas."""
    candidates = set()
    technical_symbols = r'[<>{};/\\]'
    lines = texto.split('\n')

    for match in re.finditer(r'\.([a-z]{2,6})\b', texto, re.IGNORECASE):
        candidates.add(match.group(1).lower())

    for line in lines:
        if re.search(technical_symbols, line):
            tokens = re.findall(r'\b([a-z]{2,6})\b', line, re.IGNORECASE)
            for token in tokens:
                tl = token.lower()
                token_positions = [m.start() for m in re.finditer(rf'\b{re.escape(tl)}\b', line.lower())]
                symbol_positions = [m.start() for m in re.finditer(technical_symbols, line)]
                if any(any(abs(tp - sp) <= 30 for sp in symbol_positions) for tp in token_positions):
                    candidates.add(tl)

    for line in lines:
        symbol_count = len(re.findall(technical_symbols, line))
        if len(line.strip()) > 0 and symbol_count / len(line.strip()) > 0.10:
            for token in re.findall(r'\b([a-z]{2,6})\b', line, re.IGNORECASE):
                candidates.add(token.lower())

    candidates_list = list(candidates)
    candidate_scores = {}
    for c in candidates_list:
        candidate_scores[c] = sum(1 for line in lines if re.search(technical_symbols, line) and c in line.lower())
    return [c for c, _ in sorted(candidate_scores.items(), key=lambda x: x[1], reverse=True)[:max_candidates]]


def procesar_documento_texto(texto: str, sector: Optional[str] = None) -> tuple:
    """
    Extrae tags y glosario de un texto.

    Returns:
        Tupla (tags, glosario, metadata)
    """
    if extractor is None or scorer is None:
        raise HTTPException(status_code=503, detail="Modelos de Tags/Glosario no inicializados")

    keybert_candidates = extractor.extract_with_keybert(texto, top_n=150)
    yake_candidates = extractor.extract_with_yake(texto, top_n=150)
    spacy_candidates = extractor.extract_with_spacy_chunks(texto, top_n=150)

    candidatos_unicos = {}
    for term, score_kb in keybert_candidates:
        norm_key = normalize_candidate(term)
        if norm_key not in candidatos_unicos:
            candidatos_unicos[norm_key] = {'term': term, 'keybert_score': score_kb, 'yake_score': 0.0, 'spacy_score': 0.0, 'tech_shape': has_technical_shape(term)}
        else:
            existing = candidatos_unicos[norm_key]
            if score_kb > existing['keybert_score'] or (score_kb == existing['keybert_score'] and len(term) > len(existing['term'])):
                candidatos_unicos[norm_key].update({'term': term, 'keybert_score': score_kb, 'tech_shape': has_technical_shape(term)})

    for term, score_yake in yake_candidates:
        norm_key = normalize_candidate(term)
        if norm_key in candidatos_unicos:
            candidatos_unicos[norm_key]['yake_score'] = score_yake
        else:
            candidatos_unicos[norm_key] = {'term': term, 'keybert_score': 0.0, 'yake_score': score_yake, 'spacy_score': 0.0, 'tech_shape': has_technical_shape(term)}

    # Tercera fuente: spaCy noun chunks (ATE state-of-the-art — captura sintagmas nominales)
    for term, score_sp in spacy_candidates:
        norm_key = normalize_candidate(term)
        if norm_key in candidatos_unicos:
            candidatos_unicos[norm_key]['spacy_score'] = score_sp
        else:
            candidatos_unicos[norm_key] = {'term': term, 'keybert_score': 0.0, 'yake_score': 0.0, 'spacy_score': score_sp, 'tech_shape': has_technical_shape(term)}

    candidatos_sorted = sorted(
        candidatos_unicos.values(),
        key=lambda x: (x['keybert_score'] + x.get('spacy_score', 0.0), x['yake_score']),
        reverse=True
    )[:350]

    # Filtrar verbos/adverbios de 1 palabra
    candidatos_list = []
    for c in candidatos_sorted:
        term = c['term']
        if len(term.split()) == 1 and not term.isupper():
            doc = scorer.nlp(term)
            if len(doc) > 0 and doc[0].pos_ in ("VERB", "AUX", "ADV"):
                continue
        candidatos_list.append(term)

    # Agregar tokens técnicos cortos
    for tech_term in _extract_short_technical_tokens(texto):
        if normalize_candidate(tech_term) not in {normalize_candidate(c) for c in candidatos_list}:
            candidatos_list.append(tech_term)

    # Agregar acrónimos entre paréntesis
    for match in re.finditer(r'\(([A-Za-z0-9][A-Za-z0-9/\-]{1,9})\)', texto):
        acr = match.group(1)
        if 2 <= len(acr) <= 10 and normalize_candidate(acr) not in {normalize_candidate(c) for c in candidatos_list}:
            candidatos_list.append(acr)

    # Scoring ML
    scored_terms = scorer.score_and_rank_terms(candidates=candidatos_list, full_text=texto, sector=sector)

    tags = []
    glosario = []
    valid_terms_count = 0
    typology_counts = {}

    for term_score in scored_terms:
        if not term_score.is_valid:
            continue
        valid_terms_count += 1
        typology = term_score.term_typology
        typology_counts[typology] = typology_counts.get(typology, 0) + 1

        tags.append(TerminoTag(
            termino=term_score.term,
            score=round(term_score.total_score, 3),
            occurrences=term_score.occurrences,
            threshold=round(term_score.threshold_used, 2),
            threshold_type=term_score.term_typology
        ))

        context_patterns_score = term_score.features.get('context_patterns', 0.0)
        is_multiword = len(term_score.term.split()) >= 2
        is_technical = term_score.term_typology in ("acronym", "techshape", "rare_term")
        is_short_tech_glossary = (
            term_score.term_typology == "short_tech"
            and term_score.total_score >= 0.60
            and term_score.features.get('short_tech_evidence', 0.0) >= 1.0
        )
        # Siglas técnicas reales: ALL CAPS, 2-4 chars, sin contexto definitorio necesario.
        # Restricciones estrictas para 0 FP:
        #   - term.isupper(): excluye "Accedé", "Observada", "Repetí" (verbos argentinos
        #     con mayúscula inicial que el sistema confunde con acronym)
        #   - len <= 4: excluye "RETIRA"(6), "FALLA"(5), "CENTRAL"(7) — palabras
        #     castellanas en ALL CAPS que son section labels, no siglas técnicas
        #   - len >= 2: excluye tokens de 1 char
        # Captura: HTML(4), PHP(3), SQL(3), API(3), CSS(3), SSMS(4)
        is_high_confidence_acronym = (
            term_score.term_typology == "acronym"
            and term_score.term.isupper()               # estrictamente all-caps
            and 2 <= len(term_score.term) <= 4          # sigla corta, no palabra completa
            and term_score.total_score >= 0.62
        )
        # CamelCase/techshape de 1 sola palabra (JavaScript, PostgreSQL, OAuth, etc.)
        is_high_confidence_techshape = (
            term_score.term_typology == "techshape"
            and len(term_score.term.split()) == 1       # solo palabras simples, no compuestos
            and term_score.total_score >= 0.65
        )
        is_glossary = (
            is_short_tech_glossary
            or is_high_confidence_acronym
            or is_high_confidence_techshape
            or (
                context_patterns_score >= 0.25      # era 0.30
                and term_score.total_score >= 0.65  # era 0.72
                and (is_multiword or is_technical)
            )
        )

        if is_glossary:
            contexto_documento = term_score.context_snippet if term_score.context_snippet else ""
            wiki_extract = fetch_wikipedia_definition(term_score.term, lang="es", context=contexto_documento)
            definicion_final = shorten_definition(wiki_extract, max_chars=250) if wiki_extract else None
            contexto_limpio = limpiar_definicion(contexto_documento, max_chars=200)

            glosario.append(TerminoGlosario(
                termino=term_score.term,
                score=round(term_score.total_score, 3),
                occurrences=term_score.occurrences,
                threshold=round(term_score.threshold_used, 2),
                threshold_type=term_score.term_typology,
                contexto=contexto_limpio,
                definicion=definicion_final
            ))

    tags.sort(key=lambda x: x.score, reverse=True)
    glosario.sort(key=lambda x: x.score, reverse=True)
    tags = tags[:20]
    glosario = glosario[:10]

    metadata = {
        "candidatos_extraidos": len(candidatos_list),
        "candidatos_scored": len(scored_terms),
        "valid_terms_count": valid_terms_count,
        "tags_generados": len(tags),
        "glosario_generados": len(glosario),
        "caracteres_procesados": len(texto),
        "modelo_version": "3.2.0",
        "mode": "in_doc_candidate",
    }
    if DEBUG:
        metadata["debug_typology_distribution"] = typology_counts

    return tags, glosario, metadata


def extraer_texto_pdf_base64(contenido_base64: str) -> str:
    """Extrae texto de PDF codificado en base64."""
    try:
        pdf_bytes = base64.b64decode(contenido_base64)
        doc = fitz.open(stream=BytesIO(pdf_bytes), filetype="pdf")
        texto = ""
        for page in doc:
            texto += page.get_text()
        doc.close()
        if not texto.strip():
            raise ValueError("El PDF no contiene texto extraíble")
        return texto
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Error al procesar PDF: {str(e)}")


# ============================================================================
# ENDPOINTS - RAÍZ Y HEALTH (unificado)
# ============================================================================

@app.get("/", tags=["Sistema"])
async def root():
    """Información del servicio unificado."""
    return {
        "api": "DocuGest Unified API",
        "version": "1.0.0",
        "status": "running",
        "tags_modelo": "ML Engineering v3.2 (KeyBERT + YAKE + spaCy, 0 FP)",
        "rag_modelo": "Gemini text-embedding-004 + ChromaDB + Gemma 3-27B",
        "documentation": "/docs",
        "entorno": "hf_space" if IS_HF_SPACE else "railway" if IS_RAILWAY else "local"
    }


@app.get("/health", tags=["Sistema"])
async def health_check():
    """Health check combinado: Tags/Glosario + RAG."""
    try:
        chroma_client = get_chroma_client()
        chroma_stats = chroma_client.get_stats()

        return {
            "status": "healthy",
            "tags_modelos_cargados": extractor is not None and scorer is not None,
            "chromadb_ok": True,
            "total_chunks": chroma_stats['total_chunks'],
            "total_documentos": chroma_stats['total_documentos_indexados'],
            "timestamp": datetime.now().isoformat()
        }
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail=f"Servicio no disponible: {str(e)}"
        )


# ============================================================================
# ENDPOINTS - TAGS & GLOSARIO
# ============================================================================

@app.post("/procesar", response_model=DocumentResponse, tags=["Tags & Glosario"])
async def procesar_documento_endpoint(request: DocumentRequest):
    """
    Endpoint principal para generar tags y glosario desde un PDF.
    Llamado por TagsGlosarioAPI.php (app.xl.com.ar).

    Recibe PDF en base64, extrae texto y aplica modelo ML v3.2.
    """
    logger.info(f"POST /procesar - archivo: {request.nombre_archivo}")

    texto = extraer_texto_pdf_base64(request.contenido_base64)
    tags, glosario, metadata = procesar_documento_texto(texto, request.sector)

    return DocumentResponse(
        tags=tags,
        glosario=glosario,
        metadata=metadata,
        timestamp=datetime.now().isoformat()
    )


@app.post("/procesar-upload", tags=["Tags & Glosario"])
async def procesar_documento_upload(file: UploadFile = File(...)):
    """
    Endpoint alternativo para subir PDF directamente (testing / Swagger UI).
    """
    if not file.filename.lower().endswith('.pdf'):
        raise HTTPException(status_code=400, detail="Solo se aceptan archivos PDF")

    contenido = await file.read()
    contenido_base64 = base64.b64encode(contenido).decode('utf-8')

    return await procesar_documento_endpoint(
        DocumentRequest(contenido_base64=contenido_base64, nombre_archivo=file.filename)
    )


# ============================================================================
# ENDPOINTS - WEBHOOKS RAG (llamados desde RagWebhook.php)
# ============================================================================

@app.post(
    "/webhook/document-uploaded",
    response_model=IndexingResponse,
    status_code=status.HTTP_200_OK,
    tags=["Webhooks"]
)
async def webhook_document_uploaded(payload: DocumentUploadedWebhook):
    """
    Webhook llamado por PHP cuando se sube un nuevo documento.
    Extrae texto → chunks → embeddings → indexa en ChromaDB.
    """
    start_time = time.time()
    try:
        logger.info(f"Webhook: nuevo documento {payload.document_id} - {payload.titulo}")

        text, num_pages = extract_text_from_pdf(payload.ruta_archivo)

        metadata = {
            'document_id': payload.document_id,
            'titulo': payload.titulo,
            'sector': payload.sector_nombre,
            'tipo': payload.tipo
        }
        chunks = create_chunks(text, metadata)
        chunk_texts = [chunk['text'] for chunk in chunks]
        embeddings = generate_embeddings_batch(chunk_texts, show_progress=False)

        chunk_ids = [generate_chunk_id(payload.document_id, chunk['metadata']['chunk_index']) for chunk in chunks]
        chroma_client = get_chroma_client()
        chroma_client.add_chunks(chunk_ids, embeddings, chunk_texts, [chunk['metadata'] for chunk in chunks])

        elapsed_ms = (time.time() - start_time) * 1000
        logger.info(f"Documento {payload.document_id} indexado en {elapsed_ms:.2f}ms")

        return IndexingResponse(
            status="success",
            document_id=payload.document_id,
            chunks_count=len(chunks),
            tiempo_procesamiento_ms=elapsed_ms
        )
    except PDFProcessingError as e:
        raise HTTPException(status_code=status.HTTP_400_BAD_REQUEST, detail=f"Error al procesar PDF: {str(e)}")
    except (ChunkingError, EmbeddingError, ChromaDBError) as e:
        raise HTTPException(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=f"Error en indexación: {str(e)}")
    except Exception as e:
        raise HTTPException(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=f"Error inesperado: {str(e)}")


@app.post(
    "/webhook/document-updated",
    response_model=IndexingResponse,
    status_code=status.HTTP_200_OK,
    tags=["Webhooks"]
)
async def webhook_document_updated(payload: DocumentUpdatedWebhook):
    """
    Webhook llamado por PHP cuando se actualiza un documento existente.
    Elimina chunks antiguos → re-indexa documento completo.
    """
    start_time = time.time()
    try:
        logger.info(f"Webhook: actualización documento {payload.document_id} - {payload.titulo}")

        chroma_client = get_chroma_client()
        chroma_client.delete_document_chunks(payload.document_id)

        text, _ = extract_text_from_pdf(payload.ruta_archivo)
        metadata = {
            'document_id': payload.document_id,
            'titulo': payload.titulo,
            'sector': payload.sector_nombre,
            'tipo': payload.tipo
        }
        chunks = create_chunks(text, metadata)
        chunk_texts = [chunk['text'] for chunk in chunks]
        embeddings = generate_embeddings_batch(chunk_texts, show_progress=False)
        chunk_ids = [generate_chunk_id(payload.document_id, chunk['metadata']['chunk_index']) for chunk in chunks]
        chroma_client.add_chunks(chunk_ids, embeddings, chunk_texts, [chunk['metadata'] for chunk in chunks])

        elapsed_ms = (time.time() - start_time) * 1000
        return IndexingResponse(
            status="success",
            document_id=payload.document_id,
            chunks_count=len(chunks),
            tiempo_procesamiento_ms=elapsed_ms
        )
    except Exception as e:
        raise HTTPException(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=f"Error en actualización: {str(e)}")


# ============================================================================
# ENDPOINTS - RAG QUERIES
# ============================================================================

@app.post("/api/query", response_model=QueryResponse, status_code=status.HTTP_200_OK, tags=["RAG"])
async def query_documents(request: QueryRequest):
    """Consulta RAG en lenguaje natural sobre documentos corporativos."""
    try:
        logger.info(f"Consulta RAG: '{request.pregunta[:80]}...'")
        result = generate_rag_response(query=request.pregunta, top_k=request.top_k, historial=request.historial or None)
        return QueryResponse(**result)
    except RAGError as e:
        error_msg = str(e)
        if "429" in error_msg or "quota" in error_msg.lower():
            raise HTTPException(status_code=status.HTTP_429_TOO_MANY_REQUESTS, detail="Cuota de API excedida. Intenta más tarde.")
        raise HTTPException(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=f"Error en consulta: {error_msg}")
    except Exception as e:
        raise HTTPException(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=f"Error inesperado: {str(e)}")


# ============================================================================
# ENDPOINTS - GESTIÓN DE DOCUMENTOS RAG
# ============================================================================

@app.post("/api/reindex/{document_id}", response_model=IndexingResponse, status_code=status.HTTP_200_OK, tags=["Documentos"])
async def reindex_document(document_id: int, payload: ReindexRequest):
    """Re-indexa un documento específico."""
    start_time = time.time()
    try:
        chroma_client = get_chroma_client()
        chroma_client.delete_document_chunks(document_id)

        text, _ = extract_text_from_pdf(payload.ruta_archivo)
        metadata = {'document_id': document_id, 'titulo': payload.titulo, 'sector': payload.sector_nombre, 'tipo': payload.tipo}
        chunks = create_chunks(text, metadata)
        chunk_texts = [chunk['text'] for chunk in chunks]
        embeddings = generate_embeddings_batch(chunk_texts, show_progress=False)
        chunk_ids = [generate_chunk_id(document_id, chunk['metadata']['chunk_index']) for chunk in chunks]
        chroma_client.add_chunks(chunk_ids, embeddings, chunk_texts, [chunk['metadata'] for chunk in chunks])

        elapsed_ms = (time.time() - start_time) * 1000
        return IndexingResponse(status="success", document_id=document_id, chunks_count=len(chunks), tiempo_procesamiento_ms=elapsed_ms)
    except Exception as e:
        raise HTTPException(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=f"Error en re-indexación: {str(e)}")


@app.delete("/api/document/{document_id}", response_model=DeleteResponse, status_code=status.HTTP_200_OK, tags=["Documentos"])
async def delete_document(document_id: int):
    """Elimina todos los chunks de un documento. Llamar cuando PHP elimina un documento."""
    try:
        chroma_client = get_chroma_client()
        deleted_count = chroma_client.delete_document_chunks(document_id)
        return DeleteResponse(status="success", document_id=document_id, deleted_chunks=deleted_count)
    except ChromaDBError as e:
        raise HTTPException(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=f"Error al eliminar: {str(e)}")


@app.get("/api/stats", response_model=StatsResponse, status_code=status.HTTP_200_OK, tags=["Sistema"])
async def get_statistics():
    """Estadísticas del índice ChromaDB."""
    try:
        chroma_client = get_chroma_client()
        stats = chroma_client.get_stats()
        return StatsResponse(
            total_documentos_indexados=stats['total_documentos_indexados'],
            total_chunks=stats['total_chunks'],
            documentos_por_tipo=stats['documentos_por_tipo'],
            document_ids=stats['document_ids'],
            documents_by_title=stats.get('documents_by_title', {})
        )
    except Exception as e:
        raise HTTPException(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=f"Error al obtener stats: {str(e)}")


# ============================================================================
# ENDPOINTS - ADMIN
# ============================================================================

@app.post("/admin/refresh", status_code=status.HTTP_200_OK, tags=["Admin"])
async def refresh_from_php():
    """
    Sincroniza documentos desde PHP server.
    1. Obtiene lista desde {PHP_SERVER_URL}/api/listar_documentos.php
    2. Compara con ChromaDB
    3. Descarga e indexa documentos faltantes desde {PHP_SERVER_URL}/api/descargar_pdf.php?id=X
    """
    if not PHP_SERVER_URL:
        raise HTTPException(status_code=400, detail="PHP_SERVER_URL no configurada")

    try:
        logger.info(f"Iniciando sincronización desde PHP: {PHP_SERVER_URL}")

        response = requests.get(f"{PHP_SERVER_URL}/api/listar_documentos.php", timeout=30)
        response.raise_for_status()
        php_data = response.json()

        if not php_data.get('success'):
            raise HTTPException(400, "Error al obtener documentos de PHP: " + php_data.get('mensaje', 'Error desconocido'))

        php_docs = php_data['documentos']
        logger.info(f"Obtenidos {len(php_docs)} documentos desde PHP")

        chroma_client = get_chroma_client()
        all_metadata = chroma_client.collection.get(include=["metadatas"])
        indexed_titles = {m['titulo'] for m in all_metadata['metadatas'] if 'titulo' in m}

        nuevos_docs = [doc for doc in php_docs if doc['titulo'] not in indexed_titles]
        logger.info(f"Documentos faltantes: {len(nuevos_docs)}")

        if not nuevos_docs:
            stats = chroma_client.get_stats()
            return {
                "status": "success",
                "mensaje": "Todos los documentos ya están indexados",
                "nuevos_indexados": 0,
                "ya_indexados": len(indexed_titles),
                "total_chunks": stats['total_chunks'],
                "total_documentos": stats['total_documentos_indexados']
            }

        indexados = 0
        errores = 0
        chunks_agregados = 0

        for doc in nuevos_docs:
            try:
                doc_id = doc['id']
                titulo = doc['titulo']
                logger.info(f"Descargando: {titulo} (ID: {doc_id})")

                pdf_response = requests.get(f"{PHP_SERVER_URL}/api/descargar_pdf.php?id={doc_id}", timeout=60)
                pdf_response.raise_for_status()

                text, _ = extract_text_from_pdf_bytes(pdf_response.content)

                metadata = {
                    'document_id': doc_id,
                    'titulo': titulo,
                    'documento_nombre': doc.get('archivo_nombre', f"{titulo}.pdf"),
                    'sector': doc.get('sector', 'General'),
                    'tipo': doc.get('tipo', 'politica'),
                    'ruta_archivo': doc.get('ruta_archivo', '')
                }
                chunks = create_chunks(text, metadata)
                chunk_texts = [chunk['text'] for chunk in chunks]
                embeddings = generate_embeddings_batch(chunk_texts, show_progress=False)
                chunk_ids = [f"doc_{doc_id}_{i}" for i in range(len(chunks))]
                chunk_metadatas = [chunk['metadata'] for chunk in chunks]

                chroma_client.add_chunks(
                    chunk_ids=chunk_ids,
                    embeddings=embeddings,
                    documents=chunk_texts,
                    metadatas=chunk_metadatas
                )
                indexados += 1
                chunks_agregados += len(chunks)
                logger.info(f"Indexado: {titulo}")

            except Exception as e:
                errores += 1
                logger.error(f"Error indexando {doc.get('titulo', '?')}: {str(e)}")

        stats_final = chroma_client.get_stats()
        logger.info(f"Sincronización completada: {indexados} nuevos, {errores} errores")

        return {
            "status": "success",
            "mensaje": f"Sincronización completada: {indexados} nuevos documentos indexados",
            "nuevos_indexados": indexados,
            "errores": errores,
            "chunks_agregados": chunks_agregados,
            "total_chunks": stats_final['total_chunks'],
            "total_documentos": stats_final['total_documentos_indexados']
        }

    except requests.exceptions.RequestException as e:
        raise HTTPException(status_code=503, detail=f"Error de conexión con PHP server: {str(e)}")
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Error interno: {str(e)}")


@app.post("/admin/reset-database", status_code=status.HTTP_200_OK, tags=["Admin"])
async def reset_database():
    """
    ELIMINA todos los chunks de ChromaDB para forzar re-indexación completa.
    Usar solo cuando se necesita re-indexar todo desde cero.
    """
    try:
        chroma_client = get_chroma_client()
        collection = chroma_client.collection
        all_data = collection.get(include=[])
        all_ids = all_data['ids']

        if all_ids:
            collection.delete(ids=all_ids)
            return {"status": "success", "mensaje": "Base de datos reseteada", "chunks_eliminados": len(all_ids)}
        return {"status": "success", "mensaje": "Base de datos ya estaba vacía", "chunks_eliminados": 0}

    except Exception as e:
        raise HTTPException(status_code=status.HTTP_500_INTERNAL_SERVER_ERROR, detail=f"Error al resetear: {str(e)}")


# ============================================================================
# EXCEPTION HANDLER GLOBAL
# ============================================================================

@app.exception_handler(Exception)
async def global_exception_handler(request, exc):
    logger.error(f"Error no manejado: {str(exc)}", exc_info=True)
    return JSONResponse(
        status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
        content={"status": "error", "error": "Error interno del servidor", "details": str(exc)}
    )


# ============================================================================
# MAIN
# ============================================================================

if __name__ == "__main__":
    import uvicorn
    uvicorn.run("app:app", host="0.0.0.0", port=PORT, reload=False, log_level="info")
