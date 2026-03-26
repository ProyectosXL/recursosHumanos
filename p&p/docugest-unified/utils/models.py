"""
Modelos Pydantic para validación de requests y responses.
Define la estructura de datos que fluye entre PHP y el servicio RAG.
"""

from typing import Optional, List, Dict, Any
from pydantic import BaseModel, Field, validator
from datetime import datetime


# ============================================================================
# MODELOS DE WEBHOOKS (Requests desde PHP)
# ============================================================================

class DocumentUploadedWebhook(BaseModel):
    """
    Payload del webhook cuando PHP sube un nuevo documento.
    PHP envía toda la información necesaria vía HTTP POST.
    """
    document_id: int = Field(..., description="ID del documento en SQL Server")
    titulo: str = Field(..., min_length=1, max_length=255, description="Título del documento")
    ruta_archivo: str = Field(..., description="Ruta completa del archivo PDF")
    sector_nombre: str = Field(..., description="Nombre del sector al que pertenece")
    tipo: str = Field(..., description="Tipo: 'politica' o 'procedimiento'")
    
    @validator('tipo')
    def validate_tipo(cls, v):
        if v not in ['politica', 'procedimiento']:
            raise ValueError("tipo debe ser 'politica' o 'procedimiento'")
        return v
    
    class Config:
        schema_extra = {
            "example": {
                "document_id": 123,
                "titulo": "Política de Seguridad de la Información",
                "ruta_archivo": "C:\\xampp\\htdocs\\administracion\\recursosHumanos\\p&p\\documentos\\pol-seguridad-123.pdf",
                "sector_nombre": "IT",
                "tipo": "politica"
            }
        }


class DocumentUpdatedWebhook(DocumentUploadedWebhook):
    """
    Payload del webhook cuando PHP actualiza un documento existente.
    Misma estructura que DocumentUploadedWebhook.
    """
    pass


class ReindexRequest(BaseModel):
    """
    Request manual para re-indexar un documento específico.
    """
    titulo: str = Field(..., min_length=1, max_length=255)
    ruta_archivo: str = Field(...)
    sector_nombre: str = Field(...)
    tipo: str = Field(...)
    
    @validator('tipo')
    def validate_tipo(cls, v):
        if v not in ['politica', 'procedimiento']:
            raise ValueError("tipo debe ser 'politica' o 'procedimiento'")
        return v


# ============================================================================
# MODELOS DE CONSULTAS RAG
# ============================================================================

class HistorialItem(BaseModel):
    """Un intercambio previo en la conversación."""
    pregunta: str
    respuesta: str


class QueryRequest(BaseModel):
    """
    Request para consultas RAG en lenguaje natural.
    """
    pregunta: str = Field(..., min_length=3, max_length=500, description="Pregunta en lenguaje natural")
    top_k: Optional[int] = Field(5, ge=1, le=20, description="Número de chunks a recuperar")
    historial: List[HistorialItem] = Field(default=[], description="Historial de la conversación (últimos N intercambios)")

    class Config:
        schema_extra = {
            "example": {
                "pregunta": "¿Y cuánto tiempo tarda ese proceso?",
                "top_k": 5,
                "historial": [
                    {"pregunta": "¿Cómo se hace una solicitud de compra?", "respuesta": "Según el instructivo..."}
                ]
            }
        }


class DocumentSource(BaseModel):
    """
    Información de un documento fuente en la respuesta RAG.
    """
    document_id: int
    titulo: str
    sector: str
    tipo: str
    chunk_text: str = Field(..., description="Preview del texto del chunk")
    similarity_score: float = Field(..., ge=0.0, le=1.0, description="Score de similitud coseno")


class QueryResponse(BaseModel):
    """
    Respuesta a una consulta RAG.
    """
    respuesta: str = Field(..., description="Respuesta generada por el modelo")
    fuentes: List[DocumentSource] = Field(..., description="Documentos fuente usados")
    total_chunks_encontrados: int
    tiempo_busqueda_ms: float
    tiempo_generacion_ms: float
    
    class Config:
        schema_extra = {
            "example": {
                "respuesta": "Según la Política de Recursos Humanos, los empleados tienen derecho a 15 días de vacaciones anuales...",
                "fuentes": [
                    {
                        "document_id": 45,
                        "titulo": "Política de Recursos Humanos",
                        "sector": "RRHH",
                        "tipo": "politica",
                        "chunk_text": "Los empleados tienen derecho a 15 días de vacaciones...",
                        "similarity_score": 0.89
                    }
                ],
                "total_chunks_encontrados": 5,
                "tiempo_busqueda_ms": 245.3,
                "tiempo_generacion_ms": 1823.7
            }
        }


# ============================================================================
# MODELOS DE RESPUESTAS GENÉRICAS
# ============================================================================

class SuccessResponse(BaseModel):
    """
    Respuesta genérica de éxito.
    """
    status: str = Field(default="success")
    message: str
    data: Optional[Dict[str, Any]] = None


class IndexingResponse(BaseModel):
    """
    Respuesta después de indexar un documento.
    """
    status: str = Field(default="success")
    document_id: int
    chunks_count: int
    tiempo_procesamiento_ms: float
    
    class Config:
        schema_extra = {
            "example": {
                "status": "success",
                "document_id": 123,
                "chunks_count": 42,
                "tiempo_procesamiento_ms": 8742.5
            }
        }


class DeleteResponse(BaseModel):
    """
    Respuesta después de eliminar chunks de un documento.
    """
    status: str = Field(default="success")
    document_id: int
    deleted_chunks: int


class ErrorResponse(BaseModel):
    """
    Respuesta de error estándar.
    """
    status: str = Field(default="error")
    error: str
    details: Optional[str] = None


# ============================================================================
# MODELOS DE HEALTH CHECK Y STATS
# ============================================================================

class HealthResponse(BaseModel):
    """
    Respuesta del health check endpoint.
    """
    status: str = Field(default="ok")
    version: str
    chroma_collections: int
    total_chunks: int
    total_documents: int = 0
    timestamp: datetime = Field(default_factory=datetime.now)


class StatsResponse(BaseModel):
    """
    Estadísticas del sistema RAG.
    """
    total_documentos_indexados: int
    total_chunks: int
    documentos_por_tipo: Dict[str, int]
    document_ids: List[int] = Field(default=[], description="Lista de IDs de documentos indexados")
    documents_by_title: Dict[str, int] = Field(default={}, description="Documentos agrupados por título con conteo de chunks")
    
    class Config:
        schema_extra = {
            "example": {
                "total_documentos_indexados": 87,
                "total_chunks": 3542,
                "documentos_por_tipo": {
                    "politica": 45,
                    "procedimiento": 42
                },
                "document_ids": [1, 2, 3, 45, 67, 89]
            }
        }


# ============================================================================
# MODELOS DE TESTING
# ============================================================================

class TestIndexRequest(BaseModel):
    """
    Request para el endpoint de testing.
    """
    pdf_path: str = Field(..., description="Ruta al PDF de prueba")
    titulo: Optional[str] = Field("Documento de Prueba")
    sector: Optional[str] = Field("Testing")
    tipo: Optional[str] = Field("politica")
    
    @validator('tipo')
    def validate_tipo(cls, v):
        if v not in ['politica', 'procedimiento']:
            raise ValueError("tipo debe ser 'politica' o 'procedimiento'")
        return v
