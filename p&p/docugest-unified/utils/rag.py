"""
Lógica completa de RAG (Retrieval-Augmented Generation).
Combina búsqueda semántica con generación de respuestas usando Gemini.
"""

import logging
import re
import time
import unicodedata
from typing import List, Dict, Any
import google.generativeai as genai

from utils.config import (
    GOOGLE_API_KEY,
    GENERATION_MODEL_CANDIDATES,
    GENERATION_TEMPERATURE,
    GENERATION_MAX_TOKENS,
    RAG_PROMPT_TEMPLATE,
    DEFAULT_TOP_K
)
from utils.llm_models import generate_content
from utils.embeddings import generate_query_embedding, EmbeddingError
from utils.database import get_chroma_client, ChromaDBError
from utils.models import DocumentSource

logger = logging.getLogger(__name__)

# Configurar Gemini
genai.configure(api_key=GOOGLE_API_KEY)


class RAGError(Exception):
    """Error en el proceso RAG."""
    pass


def search_relevant_chunks(
    query: str,
    top_k: int = DEFAULT_TOP_K,
    filter_metadata: Dict[str, Any] = None
) -> Dict[str, Any]:
    """
    Busca chunks relevantes para una query.
    
    Args:
        query: Pregunta o consulta del usuario
        top_k: Número de chunks a recuperar
        filter_metadata: Filtros opcionales por metadata
        
    Returns:
        Diccionario con chunks relevantes y metadata
        
    Raises:
        RAGError: Si hay error en la búsqueda
    """
    try:
        start_time = time.time()
        
        logger.info(f"Iniciando búsqueda para query: '{query[:100]}...'")
        
        # Generar embedding de la query
        query_embedding = generate_query_embedding(query)
        
        # Buscar en ChromaDB
        chroma_client = get_chroma_client()
        results = chroma_client.query_similar_chunks(
            query_embedding=query_embedding,
            top_k=top_k,
            filter_metadata=filter_metadata
        )
        
        elapsed_ms = (time.time() - start_time) * 1000
        
        logger.info(
            f"✓ Búsqueda completada: {len(results['ids'][0])} chunks en {elapsed_ms:.2f}ms"
        )
        
        return {
            'results': results,
            'tiempo_busqueda_ms': elapsed_ms
        }
        
    except EmbeddingError as e:
        logger.error(f"Error al generar embedding: {str(e)}")
        raise RAGError(f"Error en embedding: {str(e)}")
    except ChromaDBError as e:
        logger.error(f"Error en ChromaDB: {str(e)}")
        raise RAGError(f"Error en búsqueda: {str(e)}")
    except Exception as e:
        logger.error(f"Error inesperado en búsqueda: {str(e)}")
        raise RAGError(f"Error en búsqueda: {str(e)}")


# ----------------------------------------------------------------------------
# Documentos nombrados en la pregunta
# ----------------------------------------------------------------------------
# La búsqueda semántica trae los top_k fragmentos más parecidos a la pregunta.
# Si el usuario nombra un documento ("el instructivo del sistema de etiquetas de
# precios") y ninguno de sus fragmentos entra en ese top_k, el modelo ve el título
# en la lista de documentos disponibles pero no su contenido, y responde que "no
# tiene fragmentos". Por eso, si la pregunta (o la anterior del historial) nombra
# un documento, se suman sus fragmentos más relevantes al contexto.

_PALABRAS_GENERICAS = {
    'instructivo', 'instructivos', 'procedimiento', 'procedimientos', 'manual',
    'protocolo', 'politica', 'politicas', 'documento', 'para', 'del', 'las',
    'los', 'con', 'por', 'una', 'uno', 'que', 'como', 'sobre', 'desde',
}
MAX_DOCUMENTOS_NOMBRADOS = 2
CHUNKS_POR_DOCUMENTO_NOMBRADO = 4


def _normalizar(texto: str) -> str:
    """Minúsculas, sin tildes y sin signos."""
    texto = unicodedata.normalize('NFKD', str(texto or '').lower())
    texto = ''.join(c for c in texto if not unicodedata.combining(c))
    return re.sub(r'[^a-z0-9]+', ' ', texto).strip()


def _palabras_significativas(texto: str) -> set:
    return {
        p for p in _normalizar(texto).split()
        if len(p) >= 3 and not p.isdigit() and p not in _PALABRAS_GENERICAS
    }


def _titulos_mencionados(textos: List[str], titulos: List[str]) -> List[str]:
    """
    Títulos de documentos que aparecen nombrados en los textos.
    Coincide si están todas las palabras significativas del título, o al menos
    el 75% con un mínimo de 2 palabras.
    """
    palabras_texto = set()
    for t in textos:
        palabras_texto |= _palabras_significativas(t)

    candidatos = []
    for titulo in titulos:
        palabras_titulo = _palabras_significativas(titulo)
        if not palabras_titulo:
            continue
        comunes = len(palabras_titulo & palabras_texto)
        proporcion = comunes / len(palabras_titulo)
        if proporcion == 1 or (comunes >= 2 and proporcion >= 0.75):
            candidatos.append((proporcion, comunes, titulo))

    candidatos.sort(reverse=True)
    return [titulo for _, _, titulo in candidatos[:MAX_DOCUMENTOS_NOMBRADOS]]


def _agregar_chunks(base: Dict[str, Any], extra: Dict[str, Any]) -> int:
    """
    Agrega al principio de `base` los chunks de `extra` que no estén ya.
    Ambos tienen el formato de ChromaDB: listas anidadas [[...]]. Devuelve cuántos agregó.
    """
    if not extra or not extra.get('ids') or not extra['ids'][0]:
        return 0

    for clave in ('ids', 'documents', 'metadatas', 'similarities'):
        if not base.get(clave):
            base[clave] = [[]]

    existentes = set(base['ids'][0])
    nuevos = {'ids': [], 'documents': [], 'metadatas': [], 'similarities': []}
    sims_extra = (extra.get('similarities') or [[]])[0]

    for i, chunk_id in enumerate(extra['ids'][0]):
        if chunk_id in existentes:
            continue
        nuevos['ids'].append(chunk_id)
        nuevos['documents'].append(extra['documents'][0][i])
        nuevos['metadatas'].append(extra['metadatas'][0][i])
        nuevos['similarities'].append(sims_extra[i] if i < len(sims_extra) else 0.0)

    for clave in nuevos:
        base[clave][0] = nuevos[clave] + list(base[clave][0])

    return len(nuevos['ids'])


def _texto_de_respuesta(response) -> str:
    """
    Texto de una respuesta de Gemini sin usar `response.text`, que falla cuando la
    respuesta tiene varias partes (por ejemplo, partes de razonamiento) o vino vacía.
    """
    partes_texto = []
    motivo = None
    for candidato in (getattr(response, 'candidates', None) or []):
        motivo = getattr(candidato, 'finish_reason', motivo)
        contenido = getattr(candidato, 'content', None)
        for parte in (getattr(contenido, 'parts', None) or []):
            if getattr(parte, 'thought', False):
                continue  # razonamiento interno del modelo: no se muestra
            texto = getattr(parte, 'text', None)
            if texto:
                partes_texto.append(texto)
        if partes_texto:
            break  # alcanza con el primer candidato que tenga texto

    if partes_texto:
        return ''.join(partes_texto).strip()

    logger.warning(f"Respuesta de Gemini sin texto (finish_reason={motivo})")
    return ("No pude generar una respuesta en este momento. "
            "Probá reformular la pregunta o volvé a intentar en unos segundos.")


def build_context_from_chunks(chunks_data: Dict[str, Any]) -> str:
    """
    Construye el contexto para el prompt a partir de los chunks recuperados.
    
    Args:
        chunks_data: Resultados de la búsqueda en ChromaDB
        
    Returns:
        String con el contexto formateado
    """
    if not chunks_data['ids'] or not chunks_data['ids'][0]:
        return "No se encontraron documentos relevantes."
    
    context_parts = []
    
    for i in range(len(chunks_data['ids'][0])):
        chunk_id = chunks_data['ids'][0][i]
        document = chunks_data['documents'][0][i]
        metadata = chunks_data['metadatas'][0][i]
        
        # Formatear cada chunk con su metadata
        chunk_context = f"""
---
Documento: {metadata.get('titulo', 'Sin título')}
Sector: {metadata.get('sector', 'N/A')}
Tipo: {metadata.get('tipo', 'N/A')}

Contenido:
{document}
---
"""
        context_parts.append(chunk_context)
    
    return "\n".join(context_parts)


def generate_rag_response(
    query: str,
    top_k: int = DEFAULT_TOP_K,
    filter_metadata: Dict[str, Any] = None,
    historial: List[Any] = None
) -> Dict[str, Any]:
    """
    Genera una respuesta completa usando RAG con detección inteligente.
    
    Proceso:
    1. Buscar chunks relevantes en documentos
    2. Si los chunks tienen buena relevancia → contexto completo
    3. Si los chunks tienen baja relevancia → contexto vacío (LLM responde solo)
    4. Enviar a Gemini con prompt inteligente que maneja ambos casos
    5. Retornar respuesta + fuentes
    
    Args:
        query: Pregunta del usuario
        top_k: Número de chunks a usar
        filter_metadata: Filtros opcionales
        
    Returns:
        Diccionario con respuesta, fuentes y tiempos
        
    Raises:
        RAGError: Si hay error en el proceso
    """
    try:
        logger.info(f"Generando respuesta RAG para: '{query[:100]}...'")
        
        # 1. Buscar chunks relevantes
        search_results = search_relevant_chunks(query, top_k, filter_metadata)
        
        if not search_results:
            raise RAGError("search_relevant_chunks devolvió None")
        
        chunks_data = search_results.get('results')
        if chunks_data is None:
            raise RAGError("search_results no contiene 'results'")
            
        tiempo_busqueda = search_results.get('tiempo_busqueda_ms', 0)

        # 1b. Lista de documentos indexados (para el prompt y para detectar documentos nombrados)
        try:
            stats = get_chroma_client().get_stats()
            all_titles = list(stats.get('documents_by_title', {}).keys())
        except Exception:
            all_titles = []

        # 1c. Si la pregunta (o la anterior del historial) nombra un documento, sumar sus fragmentos
        textos_a_revisar = [query]
        if historial:
            ultima = historial[-1]
            textos_a_revisar.append(getattr(ultima, 'pregunta', '') or '')
        titulos_nombrados = _titulos_mencionados(textos_a_revisar, all_titles)
        chunks_agregados = 0
        for titulo in titulos_nombrados:
            try:
                extra = search_relevant_chunks(query, CHUNKS_POR_DOCUMENTO_NOMBRADO, {'titulo': titulo})
                chunks_agregados += _agregar_chunks(chunks_data, extra.get('results'))
            except RAGError as e:
                logger.warning(f"No se pudieron sumar fragmentos de '{titulo}': {e}")
        if titulos_nombrados:
            logger.info(f"Documentos nombrados en la pregunta: {titulos_nombrados} (+{chunks_agregados} fragmentos)")

        # 2. Evaluar relevancia de los resultados
        contexto = ""
        fuentes = []
        chunks_usados = 0

        # Umbral de relevancia: si el mejor resultado tiene score < 0.15, probablemente no es relevante
        # (ChromaDB convierte distance a similarity con 1-dist, scores típicos: 0.6-0.9 = muy relevante, 0.3-0.6 = relevante, <0.3 = poco relevante)
        # Si la pregunta nombra un documento, su contenido se usa siempre.
        UMBRAL_RELEVANCIA = 0.15

        if chunks_data.get('ids') and chunks_data['ids'][0]:
            similarities = chunks_data.get('similarities', [[]])[0]

            if similarities and len(similarities) > 0:
                mejor_score = max(similarities)
                logger.info(f"Mejor score de similitud: {mejor_score:.3f}")

                if mejor_score > UMBRAL_RELEVANCIA or chunks_agregados > 0:
                    # Hay resultados relevantes, construir contexto completo
                    contexto = build_context_from_chunks(chunks_data)
                    chunks_usados = len(chunks_data['ids'][0])
                    
                    # Preparar fuentes
                    for i in range(len(chunks_data['ids'][0])):
                        metadata = chunks_data['metadatas'][0][i]
                        document = chunks_data['documents'][0][i]
                        similarity = similarities[i] if i < len(similarities) else 0.0
                        
                        # Preview del chunk (primeros 200 caracteres)
                        chunk_preview = document[:200] + "..." if len(document) > 200 else document
                        
                        fuentes.append(DocumentSource(
                            document_id=int(metadata.get('document_id', 0)),
                            titulo=metadata.get('titulo', 'Sin título'),
                            sector=metadata.get('sector', 'N/A'),
                            tipo=metadata.get('tipo', 'N/A'),
                            chunk_text=chunk_preview,
                            similarity_score=float(similarity)
                        ))
                else:
                    # Baja relevancia, dejar contexto vacío para que el LLM responda naturalmente
                    logger.info(f"Baja relevancia (score: {mejor_score:.3f} < {UMBRAL_RELEVANCIA}), LLM responderá sin documentos")
                    contexto = "No hay documentos relevantes para esta consulta."
            else:
                logger.info("No se encontraron similarities en los resultados")
                contexto = "No hay documentos relevantes para esta consulta."
        else:
            # Sin resultados, contexto vacío
            logger.info("No se encontraron chunks en la búsqueda")
            contexto = "No hay documentos relevantes para esta consulta."
        
        # 3. Lista completa de documentos indexados (ya obtenida en 1b)
        documentos_disponibles = "\n".join([f"- {t}" for t in sorted(all_titles)]) if all_titles else "Lista no disponible"

        # 4. Construir historial de conversación
        if historial:
            history_parts = [
                f"Usuario: {h.pregunta}\nAsistente: {h.respuesta}"
                for h in historial[-5:]  # Últimos 5 intercambios máximo
            ]
            historial_conversacion = "\n\n".join(history_parts)
        else:
            historial_conversacion = "(Sin historial previo - primera pregunta)"

        # 5. Construir prompt completo (el LLM decidirá cómo responder)
        prompt = RAG_PROMPT_TEMPLATE.format(
            historial_conversacion=historial_conversacion,
            documentos_disponibles=documentos_disponibles,
            contexto_chunks=contexto,
            pregunta=query
        )
        
        logger.debug(f"Prompt construido: {len(prompt)} caracteres")
        
        # 4. Generar respuesta con Gemini (siempre, incluso sin documentos relevantes)
        start_gen = time.time()
        
        try:
            response = generate_content(
                GENERATION_MODEL_CANDIDATES,
                "generation",
                prompt,
                generation_config=genai.GenerationConfig(
                    temperature=GENERATION_TEMPERATURE,
                    max_output_tokens=GENERATION_MAX_TOKENS,
                )
            )
            
            tiempo_generacion = (time.time() - start_gen) * 1000
            
            # Extraer texto de la respuesta con manejo robusto de errores
            if response is None:
                raise RAGError("La API de Gemini devolvió None")
            
            # No usar response.text: falla con respuestas de varias partes o sin texto
            respuesta_texto = _texto_de_respuesta(response)
            
            logger.info(f"✓ Respuesta generada en {tiempo_generacion:.2f}ms con {chunks_usados} chunks")
            
        except Exception as api_error:
            tiempo_generacion = (time.time() - start_gen) * 1000
            logger.error(f"Error al llamar API de Gemini: {type(api_error).__name__}: {str(api_error)}")
            raise RAGError(f"Error en API de generación ({type(api_error).__name__}): {str(api_error)}")
        
        # 5. Retornar resultado completo
        return {
            'respuesta': respuesta_texto,
            'fuentes': fuentes,
            'total_chunks_encontrados': chunks_usados,
            'tiempo_busqueda_ms': tiempo_busqueda,
            'tiempo_generacion_ms': tiempo_generacion
        }
        
    except Exception as e:
        logger.error(f"Error en generación RAG: {str(e)}")
        raise RAGError(f"Error al generar respuesta: {str(e)}")


def test_rag_pipeline(test_query: str = "¿Qué información tienes disponible?") -> Dict[str, Any]:
    """
    Prueba el pipeline completo de RAG.
    
    Args:
        test_query: Query de prueba
        
    Returns:
        Resultado de la prueba
    """
    try:
        logger.info("Iniciando prueba del pipeline RAG")
        
        result = generate_rag_response(test_query, top_k=3)
        
        logger.info("✓ Pipeline RAG funcionando correctamente")
        
        return {
            'status': 'success',
            'query': test_query,
            'result': result
        }
        
    except Exception as e:
        logger.error(f"Error en prueba RAG: {str(e)}")
        return {
            'status': 'error',
            'error': str(e)
        }
