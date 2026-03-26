"""
Cliente ChromaDB para almacenamiento y búsqueda de vectores.
Maneja persistencia local sin necesidad de servidor.
"""

import logging
from typing import List, Dict, Any, Optional
import chromadb
from chromadb.config import Settings
from chromadb.utils import embedding_functions

from utils.config import CHROMA_DB_PATH, CHROMA_COLLECTION_NAME

logger = logging.getLogger(__name__)


class ChromaDBError(Exception):
    """Error en operaciones de ChromaDB."""
    pass


class ChromaDBClient:
    """
    Cliente para interactuar con ChromaDB.
    Maneja operaciones de indexación y búsqueda de vectores.
    """
    
    def __init__(self):
        """
        Inicializa el cliente ChromaDB con persistencia local.
        """
        try:
            logger.info(f"Inicializando ChromaDB en: {CHROMA_DB_PATH}")
            
            # Crear directorio si no existe
            CHROMA_DB_PATH.mkdir(parents=True, exist_ok=True)
            
            # Crear cliente persistente
            self.client = chromadb.PersistentClient(
                path=str(CHROMA_DB_PATH),
                settings=Settings(
                    anonymized_telemetry=False,  # Deshabilitar telemetría
                    allow_reset=True
                )
            )
            
            # Obtener o crear colección
            # Usamos distancia coseno para búsqueda por similitud
            self.collection = self.client.get_or_create_collection(
                name=CHROMA_COLLECTION_NAME,
                metadata={"hnsw:space": "cosine"}  # Distancia coseno
            )
            
            logger.info(
                f"ChromaDB inicializado. Colección '{CHROMA_COLLECTION_NAME}' "
                f"con {self.collection.count()} chunks"
            )
            
        except Exception as e:
            logger.error(f"Error al inicializar ChromaDB: {str(e)}")
            raise ChromaDBError(f"Error de inicialización: {str(e)}")
    
    
    def add_chunks(
        self,
        chunk_ids: List[str],
        embeddings: List[List[float]],
        documents: List[str],
        metadatas: List[Dict[str, Any]]
    ) -> None:
        """
        Agrega chunks a la colección.
        
        Args:
            chunk_ids: Lista de IDs únicos (ej: "doc_123_chunk_5")
            embeddings: Lista de vectores embedding
            documents: Lista de textos de los chunks
            metadatas: Lista de diccionarios con metadata
            
        Raises:
            ChromaDBError: Si hay error al agregar
            
        Example:
            >>> client.add_chunks(
            ...     chunk_ids=["doc_1_chunk_0", "doc_1_chunk_1"],
            ...     embeddings=[emb1, emb2],
            ...     documents=["texto1", "texto2"],
            ...     metadatas=[meta1, meta2]
            ... )
        """
        try:
            if not chunk_ids:
                raise ChromaDBError("Lista de chunk_ids vacía")
            
            # Validar que todas las listas tienen la misma longitud
            if not (len(chunk_ids) == len(embeddings) == len(documents) == len(metadatas)):
                raise ChromaDBError("Las listas deben tener la misma longitud")
            
            logger.info(f"Agregando {len(chunk_ids)} chunks a ChromaDB")
            
            # Convertir metadatas: ChromaDB solo acepta str, int, float, bool
            cleaned_metadatas = []
            for meta in metadatas:
                cleaned_meta = {}
                for key, value in meta.items():
                    if isinstance(value, (str, int, float, bool)):
                        cleaned_meta[key] = value
                    else:
                        cleaned_meta[key] = str(value)
                cleaned_metadatas.append(cleaned_meta)
            
            # Agregar a la colección
            self.collection.add(
                ids=chunk_ids,
                embeddings=embeddings,
                documents=documents,
                metadatas=cleaned_metadatas
            )
            
            logger.info(f"✓ {len(chunk_ids)} chunks agregados exitosamente")
            
        except Exception as e:
            logger.error(f"Error al agregar chunks: {str(e)}")
            raise ChromaDBError(f"Error al agregar: {str(e)}")
    
    
    def query_similar_chunks(
        self,
        query_embedding: List[float],
        top_k: int = 5,
        filter_metadata: Optional[Dict[str, Any]] = None
    ) -> Dict[str, Any]:
        """
        Busca chunks similares a un query embedding.
        
        Args:
            query_embedding: Vector embedding de la query
            top_k: Número de resultados a retornar
            filter_metadata: Filtros opcionales (ej: {"tipo": "politica"})
            
        Returns:
            Diccionario con ids, distances, documents, metadatas
            
        Example:
            >>> results = client.query_similar_chunks(query_emb, top_k=5)
            >>> for doc, meta, dist in zip(results['documents'][0], results['metadatas'][0], results['distances'][0]):
            ...     print(f"Documento: {meta['titulo']}, Similitud: {1-dist:.3f}")
        """
        try:
            logger.info(f"Buscando top {top_k} chunks similares")
            
            # Preparar where clause si hay filtros
            where = filter_metadata if filter_metadata else None
            
            # Realizar búsqueda
            results = self.collection.query(
                query_embeddings=[query_embedding],
                n_results=top_k,
                where=where
            )
            
            # Convertir distancias a scores de similitud (1 - distancia)
            if results['distances']:
                similarities = [1 - dist for dist in results['distances'][0]]
                results['similarities'] = [similarities]
            
            num_results = len(results['ids'][0]) if results['ids'] else 0
            logger.info(f"✓ Encontrados {num_results} chunks similares")
            
            return results
            
        except Exception as e:
            logger.error(f"Error en búsqueda: {str(e)}")
            raise ChromaDBError(f"Error en query: {str(e)}")
    
    
    def delete_document_chunks(self, document_id: int) -> int:
        """
        Elimina todos los chunks de un documento específico.
        
        Args:
            document_id: ID del documento
            
        Returns:
            Número de chunks eliminados
            
        Example:
            >>> deleted = client.delete_document_chunks(123)
            >>> print(f"Eliminados {deleted} chunks")
        """
        try:
            logger.info(f"Eliminando chunks del documento {document_id}")
            
            # Obtener todos los chunks del documento
            results = self.collection.get(
                where={"document_id": document_id}
            )
            
            num_chunks = len(results['ids']) if results['ids'] else 0
            
            if num_chunks == 0:
                logger.warning(f"No se encontraron chunks para documento {document_id}")
                return 0
            
            # Eliminar por IDs
            self.collection.delete(
                ids=results['ids']
            )
            
            logger.info(f"✓ Eliminados {num_chunks} chunks del documento {document_id}")
            
            return num_chunks
            
        except Exception as e:
            logger.error(f"Error al eliminar chunks: {str(e)}")
            raise ChromaDBError(f"Error al eliminar: {str(e)}")
    
    
    def delete_all_chunks(self) -> int:
        """
        Elimina TODOS los chunks de ChromaDB (útil para re-indexación completa).
        
        Returns:
            Número de chunks eliminados
            
        Example:
            >>> deleted = client.delete_all_chunks()
            >>> print(f"Eliminados {deleted} chunks")
        """
        try:
            logger.info("Eliminando TODOS los chunks de ChromaDB...")
            
            # Obtener todos los IDs
            results = self.collection.get()
            num_chunks = len(results['ids']) if results['ids'] else 0
            
            if num_chunks == 0:
                logger.info("No hay chunks para eliminar")
                return 0
            
            # Eliminar todos
            self.collection.delete(ids=results['ids'])
            
            logger.info(f"✓ Eliminados {num_chunks} chunks")
            return num_chunks
            
        except Exception as e:
            logger.error(f"Error al eliminar todos los chunks: {str(e)}")
            raise ChromaDBError(f"Error al eliminar todos los chunks: {str(e)}")
    
    
    def reset_collection(self) -> None:
        """
        Elimina y recrea la colección de ChromaDB.
        Útil cuando hay cambios en la dimensión de embeddings.
        
        Example:
            >>> client.reset_collection()
            >>> print("Colección recreada")
        """
        try:
            logger.info(f"Recreando colección '{CHROMA_COLLECTION_NAME}'...")
            
            # Eliminar la colección existente
            try:
                self.client.delete_collection(name=CHROMA_COLLECTION_NAME)
                logger.info("Colección anterior eliminada")
            except Exception:
                logger.info("No había colección anterior")
            
            # Crear nueva colección
            self.collection = self.client.create_collection(
                name=CHROMA_COLLECTION_NAME,
                metadata={"hnsw:space": "cosine"}
            )
            
            logger.info("Colección recreada exitosamente")
            
        except Exception as e:
            logger.error(f"Error al recrear colección: {str(e)}")
            raise ChromaDBError(f"Error al recrear colección: {str(e)}")
    
    
    def get_document_chunks(self, document_id: int) -> List[Dict[str, Any]]:
        """
        Obtiene todos los chunks de un documento.
        
        Args:
            document_id: ID del documento
            
        Returns:
            Lista de diccionarios con información de los chunks
        """
        try:
            results = self.collection.get(
                where={"document_id": document_id}
            )
            
            chunks = []
            if results['ids']:
                for i in range(len(results['ids'])):
                    chunks.append({
                        'id': results['ids'][i],
                        'document': results['documents'][i] if results['documents'] else None,
                        'metadata': results['metadatas'][i] if results['metadatas'] else None
                    })
            
            return chunks
            
        except Exception as e:
            logger.error(f"Error al obtener chunks: {str(e)}")
            raise ChromaDBError(f"Error al obtener: {str(e)}")
    
    
    def count_total_chunks(self) -> int:
        """
        Retorna el número total de chunks en la colección.
        
        Returns:
            Número de chunks
        """
        try:
            return self.collection.count()
        except Exception as e:
            logger.error(f"Error al contar chunks: {str(e)}")
            return 0
    
    
    def get_unique_document_ids(self) -> List[int]:
        """
        Obtiene lista de IDs únicos de documentos indexados.
        
        Returns:
            Lista de document_ids
        """
        try:
            # Obtener todos los metadatas
            results = self.collection.get()
            
            if not results['metadatas']:
                return []
            
            # Extraer document_ids únicos (puede ser 0 para documentos auto-indexados)
            # Si todos son 0, contar por nombre de documento único
            document_ids = set()
            document_names = set()
            
            for meta in results['metadatas']:
                if 'document_id' in meta:
                    doc_id = int(meta['document_id'])
                    document_ids.add(doc_id)
                
                # También recopilar nombres de documentos únicos
                if 'documento_nombre' in meta:
                    document_names.add(meta['documento_nombre'])
            
            # Si solo hay document_id=0 pero múltiples documentos por nombre,
            # retornar conteo basado en nombres únicos
            if document_ids == {0} and len(document_names) > 1:
                # Generar IDs falsos basados en la cantidad de documentos únicos
                return list(range(len(document_names)))
            
            return sorted(list(document_ids))
            
        except Exception as e:
            logger.error(f"Error al obtener document_ids: {str(e)}")
            return []
    
    
    def get_stats(self) -> Dict[str, Any]:
        """
        Obtiene estadísticas de la base de datos.
        
        Returns:
            Diccionario con estadísticas
        """
        try:
            total_chunks = self.count_total_chunks()
            unique_docs = self.get_unique_document_ids()
            
            # Contar por tipo y obtener títulos
            results = self.collection.get()
            tipos = {}
            documents_by_title = {}
            
            if results['metadatas']:
                for meta in results['metadatas']:
                    tipo = meta.get('tipo', 'unknown')
                    tipos[tipo] = tipos.get(tipo, 0) + 1
                    
                    # Agrupar chunks por título
                    titulo = meta.get('titulo', 'Sin título')
                    documents_by_title[titulo] = documents_by_title.get(titulo, 0) + 1
            
            return {
                'total_chunks': total_chunks,
                'total_documentos_indexados': len(unique_docs),
                'documentos_por_tipo': tipos,
                'document_ids': unique_docs,
                'documents_by_title': documents_by_title
            }
            
        except Exception as e:
            logger.error(f"Error al obtener stats: {str(e)}")
            return {}
    
    
    def reset_collection(self) -> None:
        """
        Elimina toda la colección (USAR CON CUIDADO).
        Útil para testing.
        """
        try:
            logger.warning("⚠️  RESETEANDO colección completa")
            self.client.delete_collection(CHROMA_COLLECTION_NAME)
            
            # Recrear colección vacía
            self.collection = self.client.get_or_create_collection(
                name=CHROMA_COLLECTION_NAME,
                metadata={"hnsw:space": "cosine"}
            )
            
            logger.info("✓ Colección reseteada")
            
        except Exception as e:
            logger.error(f"Error al resetear colección: {str(e)}")
            raise ChromaDBError(f"Error al resetear: {str(e)}")


# Singleton global del cliente
_chroma_client: Optional[ChromaDBClient] = None


def get_chroma_client() -> ChromaDBClient:
    """
    Obtiene la instancia singleton del cliente ChromaDB.
    
    Returns:
        ChromaDBClient instance
    """
    global _chroma_client
    
    if _chroma_client is None:
        _chroma_client = ChromaDBClient()
    
    return _chroma_client
