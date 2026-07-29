"""
Configuración unificada del servicio DocuGest.
Soporta 4 entornos:
- HF Space: Lee GOOGLE_API_KEY y PHP_SERVER_URL de variables de entorno (SPACE_ID auto-set)
- Railway: Lee variables de entorno (RAILWAY_PROJECT_ID auto-set)
- Producción (app.xl.com.ar): Carga GOOGLE_API_KEY del .env compartido
- Local: Carga GOOGLE_API_KEY del .env compartido
"""

import os
import socket
from pathlib import Path
from dotenv import load_dotenv

# ============================================================================
# DETECCIÓN DE ENTORNO
# ============================================================================

def is_hf_space():
    """Detecta si estamos en Hugging Face Spaces."""
    return os.getenv("SPACE_ID") is not None

def is_railway():
    """Detecta si estamos en Railway.app"""
    return os.getenv("RAILWAY_ENVIRONMENT") is not None or os.getenv("RAILWAY_PROJECT_ID") is not None

def is_production():
    """Detecta si estamos en el servidor de producción."""
    try:
        hostname = socket.gethostname().lower()
        return 'app.xl.com.ar' in hostname or 'xl.com.ar' in hostname
    except Exception:
        return False

IS_HF_SPACE = is_hf_space()
IS_RAILWAY = is_railway()
IS_CLOUD = IS_HF_SPACE or IS_RAILWAY   # Cualquier entorno cloud (HF o Railway)
IS_PRODUCTION = is_production() and not IS_CLOUD
IS_LOCAL = not IS_CLOUD and not IS_PRODUCTION

# ============================================================================
# CONFIGURACIÓN DE PUERTO
# ============================================================================

# HF Spaces requiere puerto 7860 obligatoriamente
if IS_HF_SPACE:
    PORT = 7860
elif IS_RAILWAY:
    PORT = int(os.getenv("PORT", 8000))
else:
    PORT = 8000

# ============================================================================
# CONFIGURACIÓN DE GOOGLE GEMINI API
# ============================================================================

if IS_CLOUD:
    # HF Space y Railway: Leer de variables de entorno
    GOOGLE_API_KEY = os.getenv("GOOGLE_API_KEY")
    if not GOOGLE_API_KEY:
        raise ValueError(
            "GOOGLE_API_KEY no configurada. "
            "Configurar en Space Settings → Variables en HF, o en Variables en Railway."
        )

    PHP_SERVER_URL = os.getenv("PHP_SERVER_URL")
    if not PHP_SERVER_URL:
        raise ValueError(
            "PHP_SERVER_URL no configurada. "
            "Configurar en Space Settings → Variables (ej: https://app.xl.com.ar/recursosHumanos/p&p)"
        )
    # Eliminar trailing slash
    PHP_SERVER_URL = PHP_SERVER_URL.rstrip("/")

elif IS_PRODUCTION:
    # Producción: leer de entorno (.env fuera del repo). NUNCA hardcodear secretos.
    env_path = Path(__file__).resolve().parent.parent.parent.parent.parent / ".env"
    load_dotenv(env_path)
    GOOGLE_API_KEY = os.getenv("GOOGLE_API_KEY")
    if not GOOGLE_API_KEY:
        raise ValueError(
            "GOOGLE_API_KEY no configurada (variable de entorno / .env)."
        )
    PHP_SERVER_URL = None
    PORT = 8000

else:
    # Local: Cargar desde .env
    env_path = Path(__file__).resolve().parent.parent.parent.parent.parent / ".env"
    load_dotenv(env_path)
    GOOGLE_API_KEY = os.getenv("GOOGLE_API_KEY")
    if not GOOGLE_API_KEY:
        raise ValueError(
            "GOOGLE_API_KEY no encontrada en el archivo .env. "
            "Agregá la API key al archivo .env en la raíz del proyecto."
        )
    PHP_SERVER_URL = os.getenv("PHP_SERVER_URL")

# ============================================================================
# CONFIGURACIÓN DE CHROMADB
# ============================================================================

# En HF Spaces el filesystem es efímero → usar /tmp (se reconstruye al startup)
# En Railway también es efímero → usar /tmp
# En local/producción → persistir en el directorio del proyecto
if IS_CLOUD:
    CHROMA_DB_PATH = Path("/tmp/chroma_db")
else:
    CHROMA_DB_PATH = Path(__file__).parent.parent / "chroma_db"

CHROMA_COLLECTION_NAME = "document_chunks"

# ============================================================================
# CONFIGURACIÓN DE PROCESAMIENTO DE PDF
# ============================================================================

CHUNK_SIZE = 1000
CHUNK_OVERLAP = 200
CHUNK_SEPARATORS = ["\n\n", "\n", ". ", " ", ""]

# ============================================================================
# CONFIGURACIÓN DE EMBEDDINGS
# ============================================================================

USE_LOCAL_EMBEDDINGS = True

LOCAL_EMBEDDING_MODEL = "paraphrase-multilingual-MiniLM-L12-v2"

# Solo se usa si falla la carga del modelo local (ver utils/embeddings.py).
# OJO: text-embedding-004 fue retirado (404). El reemplazo es gemini-embedding-001,
# pero devuelve 3072 dims contra las 384 de EMBEDDING_DIMENSION: si alguna vez se
# activa este fallback hay que ajustar la dimensión y reindexar ChromaDB.
GEMINI_EMBEDDING_MODEL = "models/gemini-embedding-001"
EMBEDDING_DIMENSION = 384  # paraphrase-multilingual-MiniLM-L12-v2 produce 384 dims

EMBEDDING_TASK_DOCUMENT = "retrieval_document"
EMBEDDING_TASK_QUERY = "retrieval_query"

EMBEDDING_BATCH_SIZE = 10

# ============================================================================
# CONFIGURACIÓN DE GENERACIÓN DE RESPUESTAS
# ============================================================================

# Google retira modelos periódicamente: gemini-2.5-flash-lite Y gemini-2.5-flash
# devuelven 404 "no longer available to new users" (verificado 2026-07-28 contra
# la API con la key del proyecto), y la serie 2.0 ya no tiene cuota free.
#
# Límites free tier medidos con esta key (por modelo, buckets independientes):
#   gemini-3.5-flash-lite  → 15 RPM   (mejor calidad dentro de los de 15 RPM)
#   gemini-3.1-flash-lite  → 15 RPM   (cuota aparte: sirve de desborde)
#   gemini-3.6-flash       →  5 RPM   (más capaz, pero un tercio de throughput)
#
# El orden importa: utils/llm_models.py usa el primero disponible y, si devuelve
# 404 (retirado) o 429 (RPM agotado), pasa al siguiente. Como cada modelo tiene
# su propia cuota, la lista funciona además como desborde de rate limit.
# Para fijar uno puntual: variable de entorno GENERATION_MODEL (acepta lista).
GENERATION_MODEL_CANDIDATES = [
    m.strip()
    for m in os.getenv(
        "GENERATION_MODEL",
        "models/gemini-3.5-flash-lite,"
        "models/gemini-3.1-flash-lite,"
        "models/gemini-flash-lite-latest,"
        "models/gemini-3.6-flash",
    ).split(",")
    if m.strip()
]

# Preferido (el que se usa si no se puede consultar la lista de modelos).
GENERATION_MODEL = GENERATION_MODEL_CANDIDATES[0]
GENERATION_TEMPERATURE = 0.7
GENERATION_MAX_TOKENS = 2048

# ============================================================================
# CONFIGURACIÓN DE RAG
# ============================================================================

DEFAULT_TOP_K = 5

# ============================================================================
# CONFIGURACIÓN DE TAGS/GLOSARIO LLM (ROLLING UPDATE)
# ============================================================================

# Modos:
# - legacy: usa solo pipeline clásico (actual)
# - shadow: ejecuta pipeline LLM en paralelo pero responde con legacy
# - hybrid: usa pipeline LLM con fallback automático a legacy
TAGS_GLOSSARY_PIPELINE_MODE = "hybrid"

# Mismo criterio que GENERATION_MODEL_CANDIDATES, pero arrancando por el modelo
# de cuota "secundaria": así el indexado de documentos no le come los 15 RPM al
# chat, que es lo que ve el usuario. Override: TAGS_GLOSSARY_LLM_MODEL.
TAGS_GLOSSARY_LLM_MODEL_CANDIDATES = [
    m.strip()
    for m in os.getenv(
        "TAGS_GLOSSARY_LLM_MODEL",
        "models/gemini-3.1-flash-lite,"
        "models/gemini-3.5-flash-lite,"
        "models/gemini-flash-lite-latest,"
        "models/gemini-3.6-flash",
    ).split(",")
    if m.strip()
]

TAGS_GLOSSARY_LLM_MODEL = TAGS_GLOSSARY_LLM_MODEL_CANDIDATES[0]
TAGS_GLOSSARY_LLM_TEMPERATURE = 0.1
TAGS_GLOSSARY_LLM_MAX_OUTPUT_TOKENS = 1200

# Límites para controlar costo/tokens.
TAGS_GLOSSARY_LLM_MAX_CANDIDATES = 40
TAGS_GLOSSARY_LLM_MAX_TAGS = 20
TAGS_GLOSSARY_LLM_MAX_GLOSSARY = 10
TAGS_GLOSSARY_LLM_DOC_EXCERPT_CHARS = 3500
TAGS_GLOSSARY_LLM_MAX_DEFINITION_CHARS = 260
TAGS_GLOSSARY_LLM_ENABLE_FEWSHOT = True

# ============================================================================
# CONFIGURACIÓN DE RATE LIMITING Y RETRIES
# ============================================================================

# Free tier: 15 RPM por modelo flash-lite (quotaId
# GenerateRequestsPerMinutePerProjectPerModel-FreeTier).
GEMINI_RPM_LIMIT = 15
MAX_RETRIES = 3
RETRY_MIN_WAIT = 1
RETRY_MAX_WAIT = 60

# ============================================================================
# CONFIGURACIÓN DE LOGGING
# ============================================================================

LOG_LEVEL = "INFO"

if IS_CLOUD:
    # En cloud solo logs a STDOUT
    LOG_FILE = None
else:
    LOG_DIR = Path(__file__).parent.parent / "logs"
    LOG_DIR.mkdir(exist_ok=True)
    LOG_FILE = LOG_DIR / "docugest-unified.log"

# ============================================================================
# CONFIGURACIÓN DE RUTAS
# ============================================================================

BASE_DIR = Path(__file__).parent.parent.parent
DOCUMENTOS_PATH = BASE_DIR / "documentos"

# ============================================================================
# PROMPT TEMPLATE RAG
# ============================================================================

RAG_PROMPT_TEMPLATE = """Sos un asistente virtual inteligente de DocuGest, especializado en políticas y procedimientos corporativos.

HISTORIAL DE CONVERSACIÓN:
{historial_conversacion}

DOCUMENTOS INDEXADOS EN EL SISTEMA (lista completa):
{documentos_disponibles}

FRAGMENTOS RELEVANTES PARA ESTA CONSULTA:
{contexto_chunks}

PREGUNTA ACTUAL DEL USUARIO:
{pregunta}

INSTRUCCIONES:
1. Usá el HISTORIAL DE CONVERSACIÓN para entender el contexto. Si el usuario dice "sé más detallado", "¿y qué más?", "explicame eso", etc., sabés de qué tema viene hablando y podés responder sin que tenga que repetir la pregunta.

2. Si la pregunta es un saludo, presentación o consulta general sobre vos:
   - Respondé de forma amigable y natural
   - Presentate como el asistente virtual de DocuGest
   - NO menciones que no encontraste información en documentos

3. Si el usuario pregunta qué documentos tenés disponibles:
   - Listá TODOS los documentos de "DOCUMENTOS INDEXADOS EN EL SISTEMA"
   - No omitas ninguno

4. Si la pregunta requiere información específica de documentos:
   - Usá la información de los FRAGMENTOS RELEVANTES proporcionados arriba
   - Cuando cités un documento, usá EXACTAMENTE el título que aparece en "Documento:"
   - NO uses nombres de archivo, rutas o identificadores técnicos
   - Cita los documentos entre comillas dobles
   - Si la información específica no está en los fragmentos pero el documento existe en la lista, mencioná que el documento existe pero no tenés el detalle en contexto
   - Si el documento no existe en ninguna de las dos listas, decí: "No encontré ese documento en el sistema"

5. Siempre respondé en español con tono profesional pero amigable
6. Si hay información contradictoria entre documentos, mencionalo

RESPUESTA:
"""
