"""
Lógica completa de RAG (Retrieval-Augmented Generation).
Combina búsqueda semántica con generación de respuestas usando Gemini.
"""

import logging
import time
from typing import List, Dict, Any
import google.generativeai as genai

from utils.config import (
    GOOGLE_API_KEY,
    GENERATION_MODEL,
    GENERATION_TEMPERATURE,
    GENERATION_MAX_TOKENS,
    RAG_PROMPT_TEMPLATE,
    DEFAULT_TOP_K
)
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
        
        # 2. Evaluar relevancia de los resultados
        contexto = ""
        fuentes = []
        chunks_usados = 0
        
        # Umbral de relevancia: si el mejor resultado tiene score < 0.15, probablemente no es relevante
        # (ChromaDB convierte distance a similarity con 1-dist, scores típicos: 0.6-0.9 = muy relevante, 0.3-0.6 = relevante, <0.3 = poco relevante)
        UMBRAL_RELEVANCIA = 0.15
        
        if chunks_data.get('ids') and chunks_data['ids'][0]:
            similarities = chunks_data.get('similarities', [[]])[0]
            
            if similarities and len(similarities) > 0:
                mejor_score = similarities[0]
                logger.info(f"Mejor score de similitud: {mejor_score:.3f}")
                
                if mejor_score > UMBRAL_RELEVANCIA:
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
        
        # 3. Obtener lista completa de documentos indexados
        try:
            stats = get_chroma_client().get_stats()
            all_titles = list(stats.get('documents_by_title', {}).keys())
            documentos_disponibles = "\n".join([f"- {t}" for t in sorted(all_titles)]) if all_titles else "Sin documentos indexados"
        except Exception:
            documentos_disponibles = "Lista no disponible"

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
            model = genai.GenerativeModel(GENERATION_MODEL)
            response = model.generate_content(
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
            
            if not hasattr(response, 'text'):
                logger.error(f"Response sin atributo 'text': {type(response)}, dir: {dir(response)}")
                raise RAGError(f"Respuesta de API sin formato esperado: {type(response)}")
            
            respuesta_texto = response.text if response.text else "No se pudo generar una respuesta."
            
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
