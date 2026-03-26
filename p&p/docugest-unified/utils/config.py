"""
Configuración unificada del servicio DocuGest.
Soporta 4 entornos:
- HF Space: Lee GOOGLE_API_KEY y PHP_SERVER_URL de variables de entorno (SPACE_ID auto-set)
- Railway: Lee variables de entorno (RAILWAY_PROJECT_ID auto-set)
- Producción (app.xl.com.ar): API key hardcodeada
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
            "Configurar en Space Settings → Variables (ej: https://app.xl.com.ar/administracion/recursosHumanos/p&p)"
        )
    # Eliminar trailing slash
    PHP_SERVER_URL = PHP_SERVER_URL.rstrip("/")

elif IS_PRODUCTION:
    # Producción: API key hardcodeada (no necesita .env)
    GOOGLE_API_KEY = "AIzaSyDUAWRkNRV4s11G2w4M3AG1Bu5H1h482DE"
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

GEMINI_EMBEDDING_MODEL = "models/text-embedding-004"
EMBEDDING_DIMENSION = 384  # paraphrase-multilingual-MiniLM-L12-v2 produce 384 dims

EMBEDDING_TASK_DOCUMENT = "retrieval_document"
EMBEDDING_TASK_QUERY = "retrieval_query"

EMBEDDING_BATCH_SIZE = 10

# ============================================================================
# CONFIGURACIÓN DE GENERACIÓN DE RESPUESTAS
# ============================================================================

GENERATION_MODEL = "models/gemma-3-27b-it"
GENERATION_TEMPERATURE = 0.7
GENERATION_MAX_TOKENS = 2048

# ============================================================================
# CONFIGURACIÓN DE RAG
# ============================================================================

DEFAULT_TOP_K = 5

# ============================================================================
# CONFIGURACIÓN DE RATE LIMITING Y RETRIES
# ============================================================================

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
