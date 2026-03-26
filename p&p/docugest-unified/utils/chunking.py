"""
División de texto en chunks (fragmentos) para procesamiento RAG.
Usa LangChain RecursiveCharacterTextSplitter para crear chunks semánticamente coherentes.
"""

import logging
from typing import List, Dict, Any
from langchain_text_splitters import RecursiveCharacterTextSplitter
from utils.config import CHUNK_SIZE, CHUNK_OVERLAP, CHUNK_SEPARATORS

logger = logging.getLogger(__name__)


class ChunkingError(Exception):
    """Error durante el proceso de chunking."""
    pass


def create_chunks(
    text: str,
    metadata: Dict[str, Any],
    chunk_size: int = CHUNK_SIZE,
    chunk_overlap: int = CHUNK_OVERLAP
) -> List[Dict[str, Any]]:
    """
    Divide un texto largo en chunks más pequeños con overlap.
    
    Args:
        text: Texto completo a dividir
        metadata: Metadata del documento (document_id, titulo, sector, tipo)
        chunk_size: Tamaño máximo de cada chunk en caracteres
        chunk_overlap: Overlap entre chunks para mantener contexto
        
    Returns:
        Lista de diccionarios con estructura:
        {
            'text': str,  # Texto del chunk
            'metadata': dict  # Metadata incluyendo chunk_index y total_chunks
        }
        
    Raises:
        ChunkingError: Si hay error al procesar los chunks
        
    Example:
        >>> metadata = {'document_id': 123, 'titulo': 'Política X', 'sector': 'IT', 'tipo': 'politica'}
        >>> chunks = create_chunks(long_text, metadata)
        >>> print(f"Generados {len(chunks)} chunks")
    """
    try:
        # Validar input
        if not text or not text.strip():
            raise ChunkingError("El texto está vacío")
        
        if not metadata or 'document_id' not in metadata:
            raise ChunkingError("Metadata debe incluir 'document_id'")
        
        logger.info(
            f"Iniciando chunking para documento {metadata.get('document_id')}: "
            f"{len(text)} caracteres"
        )
        
        # Crear el text splitter
        text_splitter = RecursiveCharacterTextSplitter(
            chunk_size=chunk_size,
            chunk_overlap=chunk_overlap,
            length_function=len,
            separators=CHUNK_SEPARATORS,
            is_separator_regex=False
        )
        
        # Dividir el texto
        text_chunks = text_splitter.split_text(text)
        
        if not text_chunks:
            raise ChunkingError("No se generaron chunks")
        
        # Crear lista de chunks con metadata enriquecida
        chunks = []
        total_chunks = len(text_chunks)
        
        for idx, chunk_text in enumerate(text_chunks):
            # Crear metadata específica del chunk
            chunk_metadata = {
                **metadata,  # Copiar metadata del documento
                'chunk_index': idx,
                'total_chunks': total_chunks,
            }
            
            chunks.append({
                'text': chunk_text,
                'metadata': chunk_metadata
            })
            
            logger.debug(
                f"Chunk {idx + 1}/{total_chunks}: {len(chunk_text)} caracteres"
            )
        
        logger.info(
            f"Chunking completado: {total_chunks} chunks generados "
            f"(promedio {sum(len(c['text']) for c in chunks) / total_chunks:.0f} caracteres/chunk)"
        )
        
        return chunks
        
    except Exception as e:
        logger.error(f"Error en chunking: {str(e)}")
        raise ChunkingError(f"Error al crear chunks: {str(e)}")


def generate_chunk_id(document_id: int, chunk_index: int) -> str:
    """
    Genera un ID único para un chunk.
    
    Args:
        document_id: ID del documento en SQL Server
        chunk_index: Índice del chunk (0-based)
        
    Returns:
        String con formato "doc_{document_id}_chunk_{chunk_index}"
        
    Example:
        >>> chunk_id = generate_chunk_id(123, 5)
        >>> print(chunk_id)  # "doc_123_chunk_5"
    """
    return f"doc_{document_id}_chunk_{chunk_index}"


def validate_chunk_size(text: str, max_size: int = CHUNK_SIZE) -> bool:
    """
    Valida que un chunk no exceda el tamaño máximo.
    
    Args:
        text: Texto del chunk
        max_size: Tamaño máximo permitido
        
    Returns:
        True si el chunk es válido
    """
    return len(text) <= max_size


def get_chunk_preview(chunk_text: str, max_length: int = 200) -> str:
    """
    Genera un preview corto de un chunk para mostrar en resultados.
    
    Args:
        chunk_text: Texto completo del chunk
        max_length: Longitud máxima del preview
        
    Returns:
        Preview del chunk con "..." al final si se truncó
        
    Example:
        >>> preview = get_chunk_preview("Este es un texto muy largo...", 20)
        >>> print(preview)  # "Este es un texto..."
    """
    if len(chunk_text) <= max_length:
        return chunk_text
    
    # Truncar y agregar ellipsis
    return chunk_text[:max_length].strip() + "..."


def merge_chunks(chunks: List[str], separator: str = "\n\n") -> str:
    """
    Combina múltiples chunks en un solo texto.
    Útil para construir contexto en prompts RAG.
    
    Args:
        chunks: Lista de textos de chunks
        separator: Separador entre chunks
        
    Returns:
        Texto combinado
        
    Example:
        >>> merged = merge_chunks(["chunk1", "chunk2", "chunk3"])
        >>> print(merged)  # "chunk1\n\nchunk2\n\nchunk3"
    """
    return separator.join(chunks)


def calculate_chunk_statistics(chunks: List[Dict[str, Any]]) -> Dict[str, Any]:
    """
    Calcula estadísticas sobre un conjunto de chunks.
    
    Args:
        chunks: Lista de chunks con estructura {'text': str, 'metadata': dict}
        
    Returns:
        Diccionario con estadísticas
        
    Example:
        >>> stats = calculate_chunk_statistics(chunks)
        >>> print(f"Promedio: {stats['avg_length']} caracteres")
    """
    if not chunks:
        return {
            'total_chunks': 0,
            'total_characters': 0,
            'avg_length': 0,
            'min_length': 0,
            'max_length': 0
        }
    
    lengths = [len(chunk['text']) for chunk in chunks]
    
    return {
        'total_chunks': len(chunks),
        'total_characters': sum(lengths),
        'avg_length': sum(lengths) / len(lengths),
        'min_length': min(lengths),
        'max_length': max(lengths)
    }
