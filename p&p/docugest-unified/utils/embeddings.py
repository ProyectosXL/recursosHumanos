"""
Integración para generación de embeddings.
ARQUITECTURA HÍBRIDA:
- Embeddings LOCALES con sentence-transformers (ilimitado, rápido)
- Fallback a Google Gemini API si se configura USE_LOCAL_EMBEDDINGS = False
"""

import logging
import time
from typing import List, Dict, Any, Optional
from pathlib import Path
from tqdm import tqdm

# Importar configuración
from utils.config import (
    GOOGLE_API_KEY,
    USE_LOCAL_EMBEDDINGS,
    LOCAL_EMBEDDING_MODEL,
    GEMINI_EMBEDDING_MODEL,
    EMBEDDING_DIMENSION,
    EMBEDDING_TASK_DOCUMENT,
    EMBEDDING_TASK_QUERY,
    EMBEDDING_BATCH_SIZE,
    MAX_RETRIES,
    RETRY_MIN_WAIT,
    RETRY_MAX_WAIT,
)

logger = logging.getLogger(__name__)

# ============================================================================
# INICIALIZACIÓN DE MODELOS
# ============================================================================

_local_model = None

if USE_LOCAL_EMBEDDINGS:
    # Usar sentence-transformers (LOCAL, ilimitado)
    try:
        import os
        from sentence_transformers import SentenceTransformer
        
        # Configurar cache en una ruta válida de Windows
        cache_folder = Path(__file__).parent.parent / "cache" / "transformers"
        cache_folder.mkdir(parents=True, exist_ok=True)
        os.environ['SENTENCE_TRANSFORMERS_HOME'] = str(cache_folder)
        
        logger.info(f"Cargando modelo local de embeddings: {LOCAL_EMBEDDING_MODEL}")
        logger.info(f"Cache folder: {cache_folder}")
        
        _local_model = SentenceTransformer(LOCAL_EMBEDDING_MODEL, cache_folder=str(cache_folder))
        logger.info(f"✓ Modelo local cargado exitosamente (dimensión: {EMBEDDING_DIMENSION})")
    except ImportError as e:
        logger.warning(f"sentence-transformers no disponible: {e}")
        logger.warning("Fallback automático a Google Gemini API")
        USE_LOCAL_EMBEDDINGS = False
    except Exception as e:
        logger.error(f"Error al cargar modelo local: {e}")
        logger.warning("Fallback automático a Google Gemini API")
        USE_LOCAL_EMBEDDINGS = False

if not USE_LOCAL_EMBEDDINGS:
    # Usar Google Gemini API (requiere API key)
    try:
        import google.generativeai as genai
        from tenacity import retry, stop_after_attempt, wait_exponential, retry_if_exception_type
        genai.configure(api_key=GOOGLE_API_KEY)
        logger.info(f"✓ Sistema de embeddings OK (GEMINI {GEMINI_EMBEDDING_MODEL}, dimensión: {EMBEDDING_DIMENSION})")
    except Exception as e:
        logger.error(f"Error al configurar Gemini API: {e}")
        raise RuntimeError(f"No se pudo configurar Gemini API: {e}")


class EmbeddingError(Exception):
    """Error durante la generación de embeddings."""
    pass


# ============================================================================
# FUNCIONES DE EMBEDDINGS (ARQUITECTURA HÍBRIDA)
# ============================================================================

def generate_embedding(text: str, task_type: str = EMBEDDING_TASK_DOCUMENT) -> List[float]:
    """
    Genera un embedding para un texto.
    Usa modelo LOCAL si USE_LOCAL_EMBEDDINGS=True, sino Gemini API.
    
    Args:
        text: Texto a embedder
        task_type: Tipo de tarea (solo para Gemini: "retrieval_document" o "retrieval_query")
        
    Returns:
        Lista de floats (vector de embedding)
        
    Raises:
        EmbeddingError: Si hay error al generar el embedding
        
    Example:
        >>> embedding = generate_embedding("Este es un texto de ejemplo")
        >>> print(len(embedding))  # 384 (modelo local) o 768 (Gemini)
    """
    try:
        if not text or not text.strip():
            raise EmbeddingError("El texto está vacío")
        
        # Limitar longitud del texto
        max_chars = 10000
        if len(text) > max_chars:
            logger.warning(f"Texto truncado de {len(text)} a {max_chars} caracteres")
            text = text[:max_chars]
        
        if USE_LOCAL_EMBEDDINGS:
            # Usar modelo LOCAL (sentence-transformers)
            try:
                embedding = _local_model.encode(
                    text, 
                    convert_to_numpy=True,
                    show_progress_bar=False
                ).tolist()
            except Exception as e:
                logger.error(f"Error en modelo local: {e}")
                raise EmbeddingError(f"Error en modelo local de embeddings: {e}")
        else:
            # Usar Gemini API (con retry automático)
            @retry(
                stop=stop_after_attempt(MAX_RETRIES),
                wait=wait_exponential(multiplier=1, min=RETRY_MIN_WAIT, max=RETRY_MAX_WAIT),
                retry=retry_if_exception_type((Exception,)),
                reraise=True
            )
            def _generate_with_retry():
                result = genai.embed_content(
                    model=GEMINI_EMBEDDING_MODEL,
                    content=text,
                    task_type=task_type
                )
                return result['embedding']
            
            embedding = _generate_with_retry()
        
        if not embedding:
            raise EmbeddingError("Embedding vacío")
        
        return embedding
        
    except Exception as e:
        logger.error(f"Error al generar embedding: {str(e)}")
        raise EmbeddingError(f"Error generando embedding: {str(e)}")


def generate_embeddings_batch(
    texts: List[str],
    task_type: str = EMBEDDING_TASK_DOCUMENT,
    show_progress: bool = True
) -> List[List[float]]:
    """
    Genera embeddings para múltiples textos en batches.
    Modelo LOCAL: Procesa en paralelo (muy rápido, sin límites)
    Gemini API: Implementa rate limiting para no saturar
    
    Args:
        texts: Lista de textos
        task_type: Tipo de tarea (solo para Gemini)
        show_progress: Mostrar barra de progreso
        
    Returns:
        Lista de embeddings (cada uno es un vector)
        
    Example:
        >>> texts = ["texto 1", "texto 2", "texto 3"]
        >>> embeddings = generate_embeddings_batch(texts)
        >>> print(len(embeddings))  # 3
    """
    if not texts:
        return []
    
    embeddings = []
    total = len(texts)
    
    logger.info(f"Generando {total} embeddings {'(LOCAL)' if USE_LOCAL_EMBEDDINGS else '(Gemini API)'}...")
    
    if USE_LOCAL_EMBEDDINGS:
        # MODELO LOCAL: Procesar todo de golpe (rápido, sin límites)
        try:
            with tqdm(total=total, disable=not show_progress, desc="Embeddings") as pbar:
                # sentence-transformers puede procesar lotes grandes eficientemente
                embeddings = _local_model.encode(
                    texts,
                    show_progress_bar=False,
                    convert_to_numpy=True,
                    batch_size=EMBEDDING_BATCH_SIZE
                ).tolist()
                pbar.update(total)
                
            logger.info(f"✓ {total} embeddings generados (LOCAL)")
            
        except Exception as e:
            logger.error(f"Error en batch de embeddings locales: {e}")
            raise EmbeddingError(f"Error generando embeddings locales: {e}")
    
    else:
        # GEMINI API: Procesar en batches pequeños con rate limiting
        with tqdm(total=total, disable=not show_progress, desc="Embeddings (Gemini)") as pbar:
            for i in range(0, total, EMBEDDING_BATCH_SIZE):
                batch = texts[i:i + EMBEDDING_BATCH_SIZE]
                batch_start = time.time()
                
                # Procesar batch
                for text in batch:
                    try:
                        embedding = generate_embedding(text, task_type)
                        embeddings.append(embedding)
                        pbar.update(1)
                    except EmbeddingError as e:
                        logger.error(f"Error en embedding {i}: {e}")
                        raise
                
                # Rate limiting: asegurar al menos 1 segundo entre batches
                batch_time = time.time() - batch_start
                if batch_time < 1.0 and i + EMBEDDING_BATCH_SIZE < total:
                    time.sleep(1.0 - batch_time)
        
        logger.info(f"✓ {total} embeddings generados (Gemini API)")
    
    return embeddings


def generate_query_embedding(query: str) -> List[float]:
    """
    Genera embedding específico para una query/pregunta.
    Usa task_type "retrieval_query" (solo para Gemini).
    
    Args:
        query: Pregunta o consulta del usuario
        
    Returns:
        Embedding de la query
        
    Example:
        >>> query_emb = generate_query_embedding("¿Cuál es la política de vacaciones?")
    """
    return generate_embedding(query, task_type=EMBEDDING_TASK_QUERY)


def test_embedding_system() -> bool:
    """
    Prueba el sistema de embeddings (LOCAL o Gemini API).
    
    Returns:
        True si funciona correctamente
        
    Example:
        >>> if test_embedding_system():
        ...     print("Sistema de embeddings OK")
    """
    try:
        mode = "LOCAL" if USE_LOCAL_EMBEDDINGS else "Gemini API"
        logger.info(f"Probando sistema de embeddings ({mode})...")
        
        embedding = generate_embedding("test de conexión", EMBEDDING_TASK_DOCUMENT)
        
        if embedding and len(embedding) == EMBEDDING_DIMENSION:
            logger.info(f"✓ Sistema de embeddings OK ({mode}, dimensión: {EMBEDDING_DIMENSION})")
            return True
        else:
            logger.error(f"Embedding generado tiene dimensión incorrecta: {len(embedding)} vs esperado {EMBEDDING_DIMENSION}")
            return False
            
    except Exception as e:
        logger.error(f"Error al probar embeddings: {str(e)}")
        return False


# Mantener compatibilidad con código antiguo
test_gemini_connection = test_embedding_system
