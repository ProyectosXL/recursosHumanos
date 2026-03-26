"""
Configuración centralizada para DocuGest Tags & Glossary API
Usa Pydantic para validación y type safety
"""

from pydantic import BaseModel, Field
from typing import Dict, List, Optional
from enum import Enum


class Sector(str, Enum):
    """Sectores soportados (opcional para hints)"""
    IT = "IT"
    RRHH = "RRHH"
    FINANZAS = "Finanzas"
    LEGAL = "Legal"
    MARKETING = "Marketing"
    OPERACIONES = "Operaciones"
    VENTAS = "Ventas"
    ADMINISTRACION = "Administración"
    CALIDAD = "Calidad"
    COMPRAS = "Compras"
    ECOMMERCE = "Ecommerce"
    LOGISTICA = "Logística"
    PRODUCCION = "Producción"
    PROYECTOS = "Proyectos"
    CONTROL_STOCK = "Control de Stock"
    POST_VENTA = "Post Venta"
    COMERCIO_EXTERIOR = "Comercio Exterior"


class FeatureWeights(BaseModel):
    """Pesos para cada feature del scoring system"""
    embedding_similarity: float = Field(default=0.25, ge=0.0, le=1.0)
    context_patterns: float = Field(default=0.18, ge=0.0, le=1.0)
    pos_ner: float = Field(default=0.15, ge=0.0, le=1.0)
    doc_frequency: float = Field(default=0.18, ge=0.0, le=1.0)
    shape_surface: float = Field(default=0.15, ge=0.0, le=1.0)
    specificity: float = Field(default=0.09, ge=0.0, le=1.0)  # era 0.12 — corregido para que pesos sumen 1.00


class ThresholdConfig(BaseModel):
    """Thresholds para tags vs glosario"""
    tags_threshold: float = Field(default=0.45, ge=0.0, le=1.0)
    glossary_threshold: float = Field(default=0.55, ge=0.0, le=1.0)


class ScoringConfig(BaseModel):
    """Configuración del sistema de scoring"""
    weights: FeatureWeights = Field(default_factory=FeatureWeights)
    thresholds: ThresholdConfig = Field(default_factory=ThresholdConfig)
    
    # Configuración de embeddings
    embedding_model: str = "paraphrase-multilingual-MiniLM-L12-v2"
    embedding_cache_size: int = 1000
    
    # Configuración de contexto
    context_window_tokens: int = 50  # Ventana ±N tokens para contexto
    max_document_chars: int = 200000  # Limitar tamaño de documento procesado
    
    # Configuración de frecuencia
    doc_freq_sweet_spot_min: int = 2
    doc_freq_sweet_spot_max: int = 10
    doc_freq_too_common_threshold: int = 20
    
    # Configuración de shape
    acronym_min_length: int = 2
    acronym_max_length: int = 6
    min_term_length: int = 3


class StopwordsConfig(BaseModel):
    """Stopwords universales mínimas (artículos, preposiciones, meses, días)"""
    
    # Artículos y determinantes esenciales
    articles: List[str] = Field(default=[
        'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas',
        'este', 'esta', 'estos', 'estas', 'ese', 'esa', 'esos', 'esas'
    ])
    
    # Preposiciones básicas
    prepositions: List[str] = Field(default=[
        'a', 'ante', 'bajo', 'con', 'contra', 'de', 'desde', 'en', 'entre',
        'hacia', 'hasta', 'para', 'por', 'según', 'sin', 'sobre', 'tras'
    ])
    
    # Conjunciones básicas
    conjunctions: List[str] = Field(default=[
        'y', 'e', 'o', 'u', 'pero', 'mas', 'sino', 'que', 'si', 'porque'
    ])
    
    # Meses del año
    months: List[str] = Field(default=[
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
    ])
    
    # Días de la semana
    weekdays: List[str] = Field(default=[
        'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'
    ])
    
    # Acrónimos universales a rechazar (países, palabras cortas comunes)
    universal_acronyms_reject: List[str] = Field(default=[
        'usa', 'uk', 'eu', 'pro', 'max', 'min', 'app', 'web'
    ])
    
    def get_all_stopwords(self) -> set:
        """Retorna set con todas las stopwords combinadas"""
        return set(
            self.articles + 
            self.prepositions + 
            self.conjunctions + 
            self.months + 
            self.weekdays +
            self.universal_acronyms_reject
        )


class SectorSeeds(BaseModel):
    """Seeds configurables por sector (opcional)"""
    
    # Seeds globales para todos los sectores
    global_seeds: List[str] = Field(default=[
        "factura", "inventario", "contrato", "reporte", "proceso",
        "sistema", "gestión", "análisis", "control", "documento"
    ])
    
    # Seeds específicos por sector (vacíos por defecto, configurables)
    sector_seeds: Dict[str, List[str]] = Field(default={
        "IT": [
            "API", "commit", "push", "merge", "fork", "repository", "deployment",
            "microservicio", "endpoint", "backend", "frontend", "framework",
            "PostgreSQL", "MongoDB", "Docker", "Kubernetes", "CI/CD"
        ],
        "RRHH": [
            "nómina", "liquidación", "convenio", "desempeño", "capacitación",
            "reclutamiento", "onboarding", "evaluación", "bienestar", "clima laboral"
        ],
        "Finanzas": [
            "activo", "pasivo", "patrimonio", "balance", "flujo de caja",
            "rentabilidad", "liquidez", "solvencia", "amortización", "depreciación"
        ],
        "Legal": [
            "demanda", "sentencia", "apelación", "contrato", "cláusula",
            "jurisdicción", "notificación", "arbitraje", "litigio", "normativa"
        ],
        "Marketing": [
            "conversión", "funnel", "segmentación", "posicionamiento", "branding",
            "ROI", "CPC", "CTR", "impresiones", "engagement", "lead"
        ],
        "Ecommerce": [
            "carrito abandonado", "checkout", "pasarela de pago", "fulfillment",
            "marketplace", "SKU", "inventario", "dropshipping", "tasa de rebote"
        ],
        "Logística": [
            "cross-docking", "picking", "packing", "last mile", "almacenamiento",
            "despacho", "trazabilidad", "consolidación", "distribución"
        ],
        "Comercio Exterior": [
            "FOB", "CIF", "incoterms", "despacho aduanero", "aranceles",
            "certificado de origen", "carta de crédito", "bill of lading"
        ]
    })
    
    def get_seeds_for_sector(self, sector: Optional[str] = None) -> List[str]:
        """Retorna seeds globales + específicos del sector si está disponible"""
        seeds = self.global_seeds.copy()
        
        if sector and sector in self.sector_seeds:
            seeds.extend(self.sector_seeds[sector])
        
        return list(set(seeds))  # Eliminar duplicados


class AppConfig(BaseModel):
    """Configuración completa de la aplicación"""
    scoring: ScoringConfig = Field(default_factory=ScoringConfig)
    stopwords: StopwordsConfig = Field(default_factory=StopwordsConfig)
    seeds: SectorSeeds = Field(default_factory=SectorSeeds)
    
    # Wikipedia (opcional, no bloqueante)
    use_wikipedia: bool = Field(default=False)
    wikipedia_timeout: float = Field(default=2.0)
    
    # Logging
    log_level: str = Field(default="INFO")
    debug_mode: bool = Field(default=False)


# Instancia global de configuración (singleton)
config = AppConfig()


def get_config() -> AppConfig:
    """Retorna la configuración global"""
    return config


def update_config(new_config: Dict) -> AppConfig:
    """Actualiza la configuración global con nuevos valores"""
    global config
    config = AppConfig(**{**config.dict(), **new_config})
    return config