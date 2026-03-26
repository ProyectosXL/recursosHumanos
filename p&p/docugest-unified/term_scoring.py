"""
Sistema de scoring multi-feature para términos
NO usa vetos tempranos - todo se puntúa y rankea
"""

from typing import Dict, List, Optional, Tuple
from dataclasses import dataclass
from collections import Counter
import re
import unicodedata
import numpy as np
import spacy
from sentence_transformers import SentenceTransformer, util
from wordfreq import word_frequency
import logging

from config import AppConfig, get_config

logger = logging.getLogger(__name__)


@dataclass
class TermScore:
    """Resultado del scoring de un término"""
    term: str
    total_score: float
    features: Dict[str, float]
    occurrences: int
    context_snippet: str
    pos_tag: str
    ner_label: str
    is_valid: bool  # Pasa threshold o no
    threshold_used: float  # Threshold dinámico aplicado
    term_typology: str  # acronym, techshape, rare_term, default
    
    def to_dict(self) -> dict:
        """Convierte a diccionario para JSON"""
        return {
            "term": self.term,
            "total_score": round(self.total_score, 4),
            "features": {k: round(v, 4) for k, v in self.features.items()},
            "occurrences": self.occurrences,
            "context_snippet": self.context_snippet,
            "pos_tag": self.pos_tag,
            "ner_label": self.ner_label,
            "is_valid": self.is_valid,
            "threshold_used": round(self.threshold_used, 4),
            "term_typology": self.term_typology
        }


class TermScorer:
    """
    Sistema de scoring multi-feature para términos
    Combina señales lingüísticas, contextuales y semánticas
    """
    
    def __init__(self, config: Optional[AppConfig] = None, mode: str = "in_doc_candidate"):
        """
        Inicializa el scorer con modelos y configuración
        
        Args:
            config: Configuración de la app (usa global si no se provee)
            mode: "in_doc_candidate" (veto si occurrences==0) o "domain_termness" (no veto por occurrences)
        """
        self.config = config or get_config()
        self.mode = mode
        
        # Cargar modelos
        logger.info(f"🔄 Cargando modelos para scoring (mode={mode})...")
        self.nlp = spacy.load("es_core_news_sm")
        self.embedding_model = SentenceTransformer(
            self.config.scoring.embedding_model
        )
        logger.info("✅ Modelos de scoring cargados")
        
        # Cache para embeddings
        self._embedding_cache: Dict[str, np.ndarray] = {}
        self._seed_embeddings: Optional[np.ndarray] = None  # Shape: [n_seeds, embedding_dim]
        self._seeds_list: List[str] = []  # Track seeds used
        self._seed_sector: Optional[str] = None  # v3.1: Track sector for cache invalidation
        
        # Stopwords universales
        self.stopwords = self.config.stopwords.get_all_stopwords()
        
        # Lemma counter para genericity_in_doc
        self._doc_lemma_counter: Optional[Counter] = None
        self._doc_top_lemmas: set = set()
        self._doc_fingerprint: Optional[Tuple[int, int]] = None  # (len, hash) para invalidar cache
        
        # v3.2.1 EXPANDIDO: Nombres comunes españoles (exhaustivo: 200+ nombres)
        self.nombres_comunes = {
            # Nombres masculinos comunes
            'juan', 'josé', 'antonio', 'manuel', 'francisco', 'david', 'daniel', 'carlos', 
            'miguel', 'alejandro', 'pedro', 'javier', 'jesús', 'fernando', 'luis', 'sergio',
            'rafael', 'alberto', 'jorge', 'roberto', 'ramón', 'ángel', 'andrés', 'pablo',
            'diego', 'mario', 'raúl', 'marcos', 'vicente', 'agustín', 'eduardo', 'ramiro',
            'oscar', 'enrique', 'guillermo', 'ignacio', 'ricardo', 'salvador', 'jaime', 'gustavo',
            'rubén', 'iván', 'lorenzo', 'hugo', 'víctor', 'gabriel', 'nicolás', 'santiago',
            'martín', 'lucas', 'mateo', 'federico', 'sebastián', 'tomás', 'benjamín', 'joaquín',
            'emilio', 'rodrigo', 'adrián', 'bruno', 'cristian', 'fabián', 'germán', 'hernán',
            'marcelo', 'mauricio', 'maximiliano', 'néstor', 'patricio', 'renato', 'facundo',
            'leonardo', 'gonzalo', 'esteban', 'matías', 'damián', 'claudio', 'omar', 'julio',
            'cesar', 'arturo', 'armando', 'lorenzo', 'rodrigo', 'ezequiel', 'ismael', 'gerardo',
            # Nombres femeninos comunes
            'maría', 'ana', 'carmen', 'isabel', 'dolores', 'pilar', 'teresa', 'rosa',
            'francisca', 'laura', 'cristina', 'marta', 'beatriz', 'elena', 'lucía', 'paula',
            'patricia', 'silvia', 'mónica', 'raquel', 'sandra', 'angela', 'verónica', 'diana',
            'natalia', 'susana', 'julia', 'gloria', 'victoria', 'margarita', 'rocío', 'amparo',
            'mercedes', 'andrea', 'eva', 'alicia', 'irene', 'carolina', 'claudia', 'gabriela',
            'valeria', 'daniela', 'alejandra', 'fernanda', 'mariana', 'sofía', 'camila', 'valentina',
            'martina', 'catalina', 'florencia', 'victoria', 'agustina', 'julieta', 'micaela', 'romina',
            'celeste', 'soledad', 'yamila', 'melina', 'vanesa', 'jessica', 'brenda', 'antonella',
            'milagros', 'guadalupe', 'constanza', 'azul', 'belen', 'candela', 'jimena', 'tamara',
            'noelia', 'lorena', 'paola', 'mariela', 'liliana', 'marcela', 'graciela', 'nora',
            'estela', 'gladys', 'norma', 'miriam', 'blanca', 'adriana', 'cecilia', 'monica',
            'olga', 'lidia', 'aurora', 'emilia', 'delfina', 'luna', 'emma', 'olivia', 'mia',
            # Apellidos comunes hispanos
            'garcía', 'rodríguez', 'martínez', 'hernández', 'lópez', 'gonzález', 'pérez', 
            'sánchez', 'ramírez', 'torres', 'flores', 'rivera', 'gómez', 'díaz', 'cruz',
            'morales', 'reyes', 'ortiz', 'gutiérrez', 'chávez', 'ruiz', 'álvarez', 'castillo',
            'jiménez', 'moreno', 'romero', 'méndez', 'medina', 'castro', 'vargas', 'ramos',
            'silva', 'rojas', 'guerrero', 'muñoz', 'vega', 'soto', 'mendoza', 'aguilar',
            'santos', 'fernández', 'espinoza', 'campos', 'navarro', 'peña', 'herrera', 'cortez',
            'molina', 'núñez', 'valdez', 'montoya', 'orozco', 'peralta', 'delgado', 'salazar'
        }
        
        # v3.2.1 CRÍTICO EXPANDIDO: Stopwords globales exhaustivas (400+ palabras)
        # Lista completa del español para evitar FP gravísimos
        self.global_stopwords = {
            # Preposiciones y conjunciones
            'de', 'del', 'la', 'el', 'los', 'las', 'un', 'una', 'unos', 'unas',
            'en', 'con', 'por', 'para', 'sin', 'sobre', 'bajo', 'entre', 'desde', 'hasta', 'hacia',
            'contra', 'ante', 'tras', 'según', 'durante', 'mediante',
            # Artículos y contracciones
            'el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'del', 'al',
            # Preposiciones
            'de', 'a', 'en', 'con', 'por', 'para', 'sin', 'sobre', 'bajo', 'entre', 
            'desde', 'hasta', 'hacia', 'contra', 'ante', 'tras', 'según', 'durante', 'mediante',
            # Conjunciones
            'y', 'o', 'u', 'e', 'ni', 'que', 'porque', 'cuando', 'donde', 'como', 'si', 
            'aunque', 'pero', 'sino', 'mas', 'pues', 'luego', 'así', 'también', 'tampoco',
            'sea', 'mientras', 'apenas', 'tan pronto', 'salvo', 'excepto',
            # Pronombres personales
            'yo', 'tú', 'tu', 'él', 'ella', 'usted', 'nosotros', 'nosotras', 'vosotros', 
            'vosotras', 'ellos', 'ellas', 'ustedes', 'me', 'te', 'se', 'le', 'lo', 'la',
            'nos', 'os', 'les', 'los', 'las', 'mí', 'ti', 'sí', 'conmigo', 'contigo', 'consigo',
            # Pronombres demostrativos
            'este', 'ese', 'aquel', 'esta', 'esa', 'aquella', 'esto', 'eso', 'aquello',
            'estos', 'esos', 'aquellos', 'estas', 'esas', 'aquellas',
            # Pronombres posesivos
            'mi', 'mis', 'tu', 'tus', 'su', 'sus', 'nuestro', 'nuestra', 'nuestros', 'nuestras',
            'vuestro', 'vuestra', 'vuestros', 'vuestras', 'mío', 'mía', 'míos', 'mías',
            'tuyo', 'tuya', 'tuyos', 'tuyas', 'suyo', 'suya', 'suyos', 'suyas',
            # Pronombres indefinidos
            'todo', 'toda', 'todos', 'todas', 'algo', 'alguien', 'alguno', 'alguna', 'algunos', 'algunas',
            'nada', 'nadie', 'ninguno', 'ninguna', 'ningunos', 'ningunas', 'poco', 'poca', 'pocos', 'pocas',
            'mucho', 'mucha', 'muchos', 'muchas', 'bastante', 'bastantes', 'demasiado', 'demasiada',
            'cualquier', 'cualquiera', 'cualesquiera', 'otro', 'otra', 'otros', 'otras',
            'mismo', 'misma', 'mismos', 'mismas', 'tal', 'tales', 'varios', 'varias',
            # Pronombres interrogativos y relativos
            'que', 'qué', 'cual', 'cuál', 'cuáles', 'quien', 'quién', 'quienes', 'quiénes',
            'cuanto', 'cuánto', 'cuánta', 'cuántos', 'cuántas', 'cuyo', 'cuya', 'cuyos', 'cuyas',
            # Adverbios de lugar
            'aquí', 'ahí', 'allí', 'allá', 'acá', 'donde', 'dónde', 'adonde', 'arriba', 'abajo',
            'delante', 'detrás', 'adelante', 'atrás', 'dentro', 'fuera', 'afuera', 'adentro',
            'encima', 'debajo', 'cerca', 'lejos', 'enfrente', 'alrededor', 'junto', 'alrededor',
            # Adverbios de tiempo
            'ahora', 'hoy', 'ayer', 'mañana', 'anteayer', 'pasado', 'antes', 'después', 'luego',
            'entonces', 'pronto', 'tarde', 'temprano', 'todavía', 'aún', 'ya', 'siempre', 'nunca',
            'jamás', 'apenas', 'recién', 'mientras', 'cuando', 'cuándo', 'enseguida', 'inmediatamente',
            # Adverbios de modo
            'bien', 'mal', 'mejor', 'peor', 'así', 'como', 'cómo', 'apenas', 'solo', 'sólo',
            'solamente', 'simplemente', 'únicamente', 'incluso', 'inclusive', 'además', 'también',
            'tampoco', 'siquiera', 'despacio', 'deprisa', 'rápido', 'lento',
            # Adverbios de cantidad
            'muy', 'mucho', 'poco', 'más', 'menos', 'bastante', 'demasiado', 'tanto', 'tan',
            'nada', 'algo', 'casi', 'apenas', 'suficiente',
            # Adverbios de afirmación/negación/duda
            'sí', 'no', 'tal vez', 'talvez', 'quizá', 'quizás', 'acaso', 'probablemente',
            'posiblemente', 'seguramente', 'ciertamente', 'efectivamente', 'claro', 'obvio',
            # Verbos auxiliares y copulativos más comunes
            'ser', 'estar', 'haber', 'sido', 'estado', 'habido', 'siendo', 'estando', 'habiendo',
            'soy', 'eres', 'es', 'somos', 'sois', 'son', 'era', 'eras', 'éramos', 'erais', 'eran',
            'fui', 'fuiste', 'fue', 'fuimos', 'fueron', 'seré', 'serás', 'será', 'seremos', 'serán',
            'estoy', 'estás', 'está', 'estamos', 'están', 'estaba', 'estabas', 'estábamos', 'estaban',
            'estuve', 'estuviste', 'estuvo', 'estuvimos', 'estuvieron', 'estaré', 'estarás', 'estará',
            'he', 'has', 'ha', 'hemos', 'han', 'había', 'habías', 'habíamos', 'habían',
            'hube', 'hubo', 'hubimos', 'habré', 'habrás', 'habrá', 'habremos', 'habrán',
            # Verbos modales y frecuentes
            'tener', 'hacer', 'poder', 'deber', 'querer', 'saber', 'ir', 'venir', 'dar', 'ver',
            'poner', 'decir', 'salir', 'llegar', 'pasar', 'quedar', 'seguir', 'llevar', 'dejar',
            'volver', 'tomar', 'encontrar', 'sentir', 'parecer', 'pensar', 'creer', 'conocer',
            'tengo', 'tienes', 'tiene', 'tenemos', 'tienen', 'tenía', 'tendrá', 'tuvo', 'tuve',
            'hago', 'haces', 'hace', 'hacemos', 'hacen', 'hacía', 'hará', 'hizo', 'hice',
            'puedo', 'puedes', 'puede', 'podemos', 'pueden', 'podía', 'podrá', 'pudo', 'pude',
            'debo', 'debes', 'debe', 'debemos', 'deben', 'debía', 'deberá', 'debió',
            'quiero', 'quieres', 'quiere', 'queremos', 'quieren', 'quería', 'querrá', 'quiso',
            'voy', 'vas', 'va', 'vamos', 'van', 'iba', 'irá', 'fue', 'fui',
            # Verbos infinitivos comunes en documentos/SOPs
            'abrir', 'cerrar', 'crear', 'editar', 'modificar', 'cambiar', 'agregar', 'añadir',
            'eliminar', 'borrar', 'guardar', 'actualizar', 'revisar', 'verificar', 'validar',
            'enviar', 'recibir', 'buscar', 'encontrar', 'filtrar', 'ordenar', 'seleccionar',
            'copiar', 'pegar', 'cortar', 'mover', 'cargar', 'descargar', 'subir', 'bajar',
            'instalar', 'configurar', 'ajustar', 'definir', 'establecer', 'asignar', 'completar',
            'iniciar', 'comenzar', 'empezar', 'terminar', 'finalizar', 'cancelar', 'confirmar',
            'aceptar', 'rechazar', 'aprobar', 'denegar', 'autorizar', 'permitir', 'habilitar',
            'deshabilitar', 'activar', 'desactivar', 'mostrar', 'ocultar', 'visualizar', 'imprimir',
            # Adjetivos y participios muy comunes
            'nuevo', 'nueva', 'nuevos', 'nuevas', 'viejo', 'vieja', 'viejos', 'viejas',
            'bueno', 'buena', 'buenos', 'buenas', 'malo', 'mala', 'malos', 'malas',
            'grande', 'grandes', 'pequeño', 'pequeña', 'pequeños', 'pequeñas', 'chico', 'chica',
            'largo', 'larga', 'largos', 'largas', 'corto', 'corta', 'cortos', 'cortas',
            'alto', 'alta', 'altos', 'altas', 'bajo', 'baja', 'bajos', 'bajas',
            'primero', 'primera', 'primeros', 'primeras', 'último', 'última', 'últimos', 'últimas',
            'anterior', 'anteriores', 'posterior', 'posteriores', 'siguiente', 'siguientes',
            'propio', 'propia', 'propios', 'propias', 'ajeno', 'ajena', 'ajenos', 'ajenas',
            'mismo', 'misma', 'mismos', 'mismas', 'distinto', 'distinta', 'distintos', 'distintas',
            'igual', 'iguales', 'diferente', 'diferentes', 'parecido', 'parecida', 'similar', 'similares',
            'cierto', 'cierta', 'ciertos', 'ciertas', 'verdadero', 'falso', 'posible', 'imposible',
            'necesario', 'necesaria', 'importante', 'principal', 'general', 'particular',
            'total', 'parcial', 'completo', 'completa', 'incompleto', 'entero', 'entera',
            'único', 'única', 'únicos', 'únicas', 'solo', 'sola', 'solos', 'solas',
            'ambos', 'ambas', 'cada', 'sendos', 'sendas', 'demás',
            # Números cardinales y ordinales
            'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez',
            'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'veinte', 'treinta',
            'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa', 'cien', 'ciento',
            'doscientos', 'trescientos', 'mil', 'millón', 'millones', 'billón',
            'primer', 'primero', 'segunda', 'segundo', 'tercer', 'tercero', 'cuarto', 'quinto',
            'sexto', 'séptimo', 'octavo', 'noveno', 'décimo', 'undécimo', 'duodécimo',
            # Sustantivos genéricos muy comunes (FP graves)
            'cosa', 'cosas', 'parte', 'partes', 'lugar', 'lugares', 'punto', 'puntos',
            'caso', 'casos', 'ejemplo', 'ejemplos', 'tipo', 'tipos', 'clase', 'clases',
            'forma', 'formas', 'modo', 'modos', 'manera', 'maneras', 'medio', 'medios',
            'vez', 'veces', 'tiempo', 'tiempos', 'momento', 'momentos', 'día', 'días',
            'hora', 'horas', 'mes', 'meses', 'año', 'años', 'semana', 'semanas',
            'vida', 'mundo', 'persona', 'personas', 'gente', 'grupo', 'grupos',
            'nombre', 'nombres', 'número', 'números', 'lista', 'listas', 'orden',
            'nivel', 'niveles', 'estado', 'estados', 'situación', 'condición',
            'problema', 'problemas', 'solución', 'soluciones', 'resultado', 'resultados',
            'fin', 'fines', 'inicio', 'comienzo', 'final', 'principio', 'mitad',
            'lado', 'lados', 'extremo', 'límite', 'borde', 'centro', 'medio',
            # Palabras organizacionales comunes (no específicas)
            'dato', 'datos', 'información', 'valor', 'valores', 'contenido', 'contenidos',
            'campo', 'campos', 'opción', 'opciones', 'elemento', 'elementos', 'item', 'items',
            'registro', 'registros', 'entrada', 'entradas', 'salida', 'salidas',
            'proceso', 'procesos', 'operación', 'operaciones', 'acción', 'acciones',
            'función', 'funciones', 'tarea', 'tareas', 'trabajo', 'trabajos',
            'documento', 'documentos', 'archivo', 'archivos', 'carpeta', 'carpetas',
            'pantalla', 'pantallas', 'ventana', 'ventanas', 'página', 'páginas',
            'botón', 'botones', 'enlace', 'enlaces', 'menú', 'menús', 'texto', 'textos',
            # Verbos corporativos genéricos (infinitivos)
            'realizar', 'ejecutar', 'efectuar', 'llevar', 'mantener', 'obtener', 'lograr',
            'permitir', 'evitar', 'mejorar', 'aumentar', 'reducir', 'controlar', 'gestionar',
            'administrar', 'organizar', 'planificar', 'desarrollar', 'implementar', 'aplicar',
            'utilizar', 'usar', 'emplear', 'servir', 'funcionar', 'operar', 'trabajar',
            'continuar', 'seguir', 'proceder', 'avanzar', 'retroceder', 'volver', 'regresar',
            'indicar', 'señalar', 'mostrar', 'presentar', 'representar', 'expresar', 'comunicar',
            'explicar', 'describir', 'especificar', 'detallar', 'informar', 'notificar', 'avisar',
            'solicitar', 'pedir', 'requerir', 'necesitar', 'depender', 'contar', 'considerar',
            'incluir', 'excluir', 'contener', 'comprender', 'abarcar', 'cubrir',
            'corresponder', 'pertenecer', 'referir', 'relacionar', 'vincular', 'asociar', 'conectar',
            # Palabras de UI/interfaz comunes
            'clic', 'click', 'hacer', 'presionar', 'pulsar', 'tocar', 'seleccionar', 'elegir',
            'marcar', 'desmarcar', 'tildar', 'check', 'activar', 'desactivar',
            # Interjecciones y conectores
            'pues', 'bueno', 'claro', 'entonces', 'además', 'asimismo', 'igualmente',
            'sin embargo', 'no obstante', 'por tanto', 'por consiguiente', 'por ende',
            'es decir', 'o sea', 'a saber', 'esto es',
            # Otros FP detectados en producción
            'disponible', 'disponibles', 'posible', 'posibles', 'actual', 'actuales',
            'anterior', 'posteriores', 'general', 'generales', 'especial', 'especiales',
            'completo', 'completos', 'completa', 'completas', 'total', 'totales',
            'principal', 'principales', 'básico', 'básica', 'básicos', 'básicas'
        }
        
        # Patrones de contexto definitorios (SOLO realmente definitorios)
        self.definition_patterns = [
            r'\b(?:se define|significa|define como|entiende por|denomina)\b.*?{term}',
            r'{term}.*?\b(?:es|son|significa|se define)\b',
            r'(?:glosario|definiciones?).*?{term}',
            # Patrones adicionales en español formal
            r'\b(?:consiste\s+en|hace\s+referencia\s+a|se\s+denomina|se\s+refiere\s+a)\b.*?{term}',
            r'{term}.*?\b(?:consiste\s+en|hace\s+referencia\s+a|comprende|tiene\s+por\s+objeto)\b',
            r'por\s+{term}\s+se\s+entiende',
        ]
        
        # Patrones para detectar personas en contexto
        self.person_context_patterns = [
            r'\b(?:Sr|Sra|Srta|Don|Doña|Dr|Dra)\.?\s+{term}',
            r'{term}.*?\b(?:DNI|CUIL|CUIT|Legajo|Empleado|empleado)\b',
            r'\b(?:empleado|empleada|trabajador|trabajadora)\s+{term}'
        ]
    
    def _normalize_text(self, text: str) -> str:
        """Normaliza texto para comparaciones"""
        if not text:
            return ""
        text = text.lower()
        # Eliminar acentos
        text = ''.join(
            c for c in unicodedata.normalize('NFD', text)
            if unicodedata.category(c) != 'Mn'
        )
        return text.strip()
    
    def _get_embedding(self, text: str) -> np.ndarray:
        """Obtiene embedding con cache"""
        text_norm = self._normalize_text(text)
        
        if text_norm not in self._embedding_cache:
            if len(self._embedding_cache) >= self.config.scoring.embedding_cache_size:
                # Limpiar cache si está lleno (FIFO simple)
                first_key = next(iter(self._embedding_cache))
                del self._embedding_cache[first_key]
            
            self._embedding_cache[text_norm] = self.embedding_model.encode(
                text, 
                convert_to_tensor=False
            )
        
        return self._embedding_cache[text_norm]
    
    def _prepare_seed_embeddings(self, sector: Optional[str] = None):
        """Prepara embeddings individuales de seeds [n_seeds, dim] - v3.1: Cache por sector"""
        # Si ya están cargados para este sector, no hacer nada
        if self._seed_embeddings is not None and self._seed_sector == sector:
            return
        
        seeds = self.config.seeds.get_seeds_for_sector(sector)
        
        if seeds:
            self._seeds_list = seeds
            self._seed_sector = sector  # Guardar sector para cache
            # Generar embeddings individuales para cada seed
            seed_embeddings_list = []
            for seed in seeds:
                emb = self._get_embedding(seed)
                seed_embeddings_list.append(emb)
            
            # Stack en array 2D [n_seeds, embedding_dim]
            self._seed_embeddings = np.stack(seed_embeddings_list, axis=0)
            logger.info(f"Seeds preparados para sector {sector or 'global'}: {len(seeds)} embeddings individuales")
    
    def _is_acronym(self, term: str) -> bool:
        """Detecta si el término es un acrónimo (all-caps, mixed OAuth/JWT, permite "/" "-")"""
        # Acrónimos stopwords universales
        term_norm = self._normalize_text(term)
        if term_norm in self.config.stopwords.universal_acronyms_reject:
            return False
        
        # Regla 1: All-caps 2-10 caracteres alfanuméricos (permite "/" "-")
        if term.isupper():
            alphanumeric = re.sub(r'[^A-Z0-9]', '', term)
            if 2 <= len(alphanumeric) <= 10:
                return True
        
        # Regla 2: Mixed case estilo OAuth, JWT (no CamelCase extenso)
        if 2 <= len(term) <= 10:
            has_upper = any(c.isupper() for c in term)
            has_lower = any(c.islower() for c in term)
            if has_upper and has_lower:
                # No es CamelCase extenso (ej: "PostgreSQL" tiene >6 chars)
                # Pero "OAuth", "JWT", "eBay" sí
                if not re.search(r'[a-z]{4,}[A-Z]', term):  # No tiene 4+ minúsculas seguidas
                    return True
        
        return False
    
    def count_occurrences(self, term: str, full_text: str) -> Tuple[int, bool]:
        """
        Cuenta ocurrencias del término con matching robusto:
        - Para mono-token: cuenta por lemma (cubre singular/plural) + texto normalizado
        - Para multi-token: busca secuencia de lemas o fallback a normalización
        - TODAS las comparaciones usan _normalize_text() para consistencia
        - Fallback a substring normalizado sobre TODO el texto si lemma parsing falla
        
        Returns:
            (occurrences, morph_variant_match)
            - occurrences: número de matches encontrados
            - morph_variant_match: True si el match fue por lemma (ej: webhook->webhooks)
        """
        term_norm = self._normalize_text(term)
        text_norm = self._normalize_text(full_text)
        
        # Contar matches exactos normalizados (baseline)
        exact_matches = text_norm.count(term_norm)
        
        # v3.1 FIX: Si hay matches exactos, NUNCA retornar 0 (evita veto por spaCy limit)
        if exact_matches > 0:
            # Intentar spaCy parsing solo si es factible (< 100k chars)
            if len(full_text) <= 100000:
                try:
                    term_doc = self.nlp(term)
                    # Limitar full_text también para blindar PDFs grandes
                    full_doc = self.nlp(full_text[:100000])
                    
                    # Obtener tokens alfabéticos del término
                    term_tokens = [tok for tok in term_doc if tok.is_alpha]
                    if term_tokens:
                        # Contar por lemma (más robusto) - devuelve tuple (count, morph)
                        count, morph = self._count_lemma_matches_from_doc(
                            term, term_tokens, full_doc, term_norm, text_norm, exact_matches
                        )
                        if count > 0:
                            return count, morph
                except Exception as e:
                    logger.warning(f"spaCy parsing falló para '{term}': {e}. Usando exact_matches.")
            
            # Fallback seguro: retornar exact_matches (nunca 0)
            return exact_matches, False
        
        # Si NO hay exact_matches, intentar spaCy parsing completo
        doc_text_limit = full_text[:100000] if len(full_text) > 100000 else full_text
        
        try:
            term_doc = self.nlp(term)
            full_doc = self.nlp(doc_text_limit)
        except Exception as e:
            logger.warning(f"spaCy parsing falló para '{term}': {e}. Usando fallback.")
            return 0, False
        
        # Obtener tokens alfabéticos del término
        term_tokens = [tok for tok in term_doc if tok.is_alpha]
        
        if not term_tokens:
            # Si no hay tokens alfabéticos, usar fallback
            return exact_matches, False
        
        # Procesar conteo por lemma
        return self._count_lemma_matches_from_doc(term, term_tokens, full_doc, term_norm, text_norm, exact_matches)
    
    def _count_lemma_matches_from_doc(
        self, 
        term: str, 
        term_tokens: List, 
        full_doc, 
        term_norm: str, 
        text_norm: str, 
        exact_matches: int
    ) -> Tuple[int, bool]:
        """Helper para contar matches por lemma (extraído para reusabilidad en v3.1)"""
        
        # CASO 1: Término mono-token
        if len(term_tokens) == 1:
            # NORMALIZAR lemma del término
            term_lemma_norm = self._normalize_text(term_tokens[0].lemma_)
            
            lemma_matches = 0
            for token in full_doc:
                if not token.is_alpha:
                    continue
                
                # NORMALIZAR lemma y texto del token (CONSISTENCIA)
                token_lemma_norm = self._normalize_text(token.lemma_)
                token_text_norm = self._normalize_text(token.text)
                
                # Match por lemma normalizado O por texto normalizado
                if token_lemma_norm == term_lemma_norm or token_text_norm == term_norm:
                    lemma_matches += 1
            
            # Fallback: si lemma_matches==0 pero el término está en el texto completo
            if lemma_matches == 0 and exact_matches == 0:
                # Buscar en TODO el texto normalizado (no limitado a 100k)
                fallback_count = text_norm.count(term_norm)
                if fallback_count > 0:
                    logger.debug(f"Fallback activado para '{term}': {fallback_count} matches")
                    return fallback_count, False
                else:
                    # INSTRUMENTACIÓN: loggear snippet para debug de occurrences=0
                    snippet = text_norm[:500] + "..." if len(text_norm) > 500 else text_norm
                    logger.debug(f"occurrences=0 para '{term}' (term_norm='{term_norm}'). Doc snippet: {snippet}")
            
            # Detectar si hubo matches por lemma que no estaban en exact_matches
            morph_variant = lemma_matches > exact_matches
            return max(lemma_matches, exact_matches), morph_variant
        
        # CASO 2: Término multi-token
        # Buscar secuencia de lemas normalizados
        term_lemmas_norm = [self._normalize_text(tok.lemma_) for tok in term_tokens]
        
        lemma_sequence_matches = 0
        doc_tokens = [tok for tok in full_doc if tok.is_alpha]
        
        # Sliding window para encontrar secuencias
        for i in range(len(doc_tokens) - len(term_tokens) + 1):
            window = doc_tokens[i:i+len(term_tokens)]
            window_lemmas_norm = [self._normalize_text(tok.lemma_) for tok in window]
            
            if window_lemmas_norm == term_lemmas_norm:
                lemma_sequence_matches += 1
        
        # Fallback para multi-token: si no se encontraron secuencias
        if lemma_sequence_matches == 0 and exact_matches == 0:
            # Usar substring normalizado sobre TODO el texto
            fallback_count = text_norm.count(term_norm)
            if fallback_count > 0:
                logger.debug(f"Fallback multi-token para '{term}': {fallback_count} matches")
                return fallback_count, False
            else:
                # INSTRUMENTACIÓN: loggear snippet para debug de occurrences=0
                snippet = text_norm[:500] + "..." if len(text_norm) > 500 else text_norm
                logger.debug(f"occurrences=0 multi-token para '{term}' (term_norm='{term_norm}'). Doc snippet: {snippet}")
        
        # Si encontramos matches por lemma sequence, usar esos
        if lemma_sequence_matches > 0:
            morph_variant = lemma_sequence_matches > exact_matches
            return max(lemma_sequence_matches, exact_matches), morph_variant
        
        # Fallback final: usar exact_matches normalizados
        return exact_matches, False
    
    def _build_doc_lemma_counter(self, full_text: str, top_k: int = 40):
        """Construye counter de lemas del documento para specificity (usa normalización consistente)"""
        # Calcular fingerprint del documento (len + hash de primeros 2000 chars)
        text_norm = self._normalize_text(full_text)
        fingerprint = (len(text_norm), hash(text_norm[:2000]) if len(text_norm) > 0 else 0)
        
        # Si el fingerprint cambió, resetear caches
        if self._doc_fingerprint != fingerprint:
            self._doc_lemma_counter = None
            self._doc_top_lemmas = set()
            self._doc_fingerprint = fingerprint
            logger.debug(f"Cache de lemma counter reseteado (nuevo documento fingerprint={fingerprint})")
        
        # Si ya está construido para este documento, no hacer nada
        if self._doc_lemma_counter is not None:
            return
        
        doc = self.nlp(full_text[:100000])  # Limitar para performance
        
        lemmas = []
        for token in doc:
            if token.is_alpha and not token.is_stop and len(token.text) >= 3:
                # Aplicar _normalize_text para consistencia (quita tildes)
                lemma_norm = self._normalize_text(token.lemma_)
                lemmas.append(lemma_norm)
        
        self._doc_lemma_counter = Counter(lemmas)
        
        # Top-K lemas más frecuentes
        top_lemmas = [lemma for lemma, _ in self._doc_lemma_counter.most_common(top_k)]
        self._doc_top_lemmas = set(top_lemmas)
        
        logger.info(f"Lemma counter construido: {len(self._doc_lemma_counter)} lemas únicos, top-{top_k} identificados")
    
    def _hard_veto_check(self, term: str, full_text: str = "") -> Tuple[bool, str]:
        """
        Vetos DUROS (solo casos obvios que nunca deben pasar)
        
        Returns:
            (es_veto, razon)
        """
        term_norm = self._normalize_text(term)
        
        # 0. v3.2.1 CRÍTICO: Stopwords globales hardcodeadas (FP gravísimos)
        # Incluye: nuevo, editar, del, dos, dentro, fuera, etc.
        if term_norm in self.global_stopwords:
            return True, "global_stopword"
        
        # 1. Vacío o muy corto
        if len(term) < self.config.scoring.min_term_length:
            return True, "too_short"
        
        # 2. Solo dígitos
        if term.isdigit():
            return True, "only_digits"
        
        # 3. Stopwords universales (artículos, preposiciones, meses, días)
        if term_norm in self.stopwords:
            return True, "stopword"
        
        # 4. Nombres propios de PERSONAS
        doc = self.nlp(term)
        
        # 4a. NER detecta PER (excluir acrónimos)
        for ent in doc.ents:
            if ent.label_ == "PER":
                if not term.isupper():
                    return True, "person_name_ner"
        
        # 4b. PROPN + lista corta de nombres comunes
        if len(doc) > 0 and doc[0].pos_ == "PROPN":
            if term_norm in self.nombres_comunes:
                return True, "person_name_common"
        
        # 4c. Patrones contextuales de personas
        if full_text:
            for pattern_template in self.person_context_patterns:
                pattern = pattern_template.replace('{term}', re.escape(term))
                if re.search(pattern, full_text, re.IGNORECASE):
                    return True, "person_context_pattern"
        
        # Pasar todos los demás casos
        return False, ""
    
    def _score_embedding_similarity(
        self, 
        term: str,
        context_snippets: List[str],
        sector: Optional[str] = None
    ) -> float:
        """
        Feature A: Similitud de embeddings
        - Similitud con seed concepts del sector (top-3 mean)
        - Similitud con contextos donde aparece el término (top-2 mean)
        - Combina: 0.85*sim_context + 0.15*sim_seeds
        
        Returns:
            Score 0.0 - 1.0
        """
        # Preparar seeds (guard interno por sector)
        self._prepare_seed_embeddings(sector)
        
        # Embedding del término
        term_emb = self._get_embedding(term)
        
        sim_seeds = None
        sim_context = None
        
        # 1. Similitud con seeds (si disponibles) - top-3 mean
        if self._seed_embeddings is not None:
            # Calcular cosine similarity con todos los seeds [n_seeds]
            seed_sims = util.cos_sim(term_emb, self._seed_embeddings)[0]  # Shape: [n_seeds]
            seed_sims_np = seed_sims.cpu().numpy() if hasattr(seed_sims, 'cpu') else seed_sims
            
            # Top-3 mean (o menos si hay pocos seeds)
            top_k = min(3, len(seed_sims_np))
            top_k_sims = np.partition(seed_sims_np, -top_k)[-top_k:]  # Top-k valores
            sim_seeds = float(np.mean(np.maximum(0, top_k_sims)))  # Clip negativos
            
            # Reducir influencia si sector=None (evitar premiar genéricos corporativos)
            if sector is None:
                sim_seeds *= 0.5
        
        # 2. Similitud con contextos - top-2 mean
        if context_snippets:
            context_sims = []
            for snippet in context_snippets[:4]:  # Considerar hasta 4 contextos
                if snippet:
                    ctx_emb = self._get_embedding(snippet)
                    ctx_sim = util.cos_sim(term_emb, ctx_emb).item()
                    context_sims.append(max(0, ctx_sim))
            
            if context_sims:
                # Top-2 mean
                top_k = min(2, len(context_sims))
                top_k_ctx = sorted(context_sims, reverse=True)[:top_k]
                sim_context = float(np.mean(top_k_ctx))
        
        # Combinar: 0.85*context + 0.15*seeds
        if sim_context is not None and sim_seeds is not None:
            return 0.85 * sim_context + 0.15 * sim_seeds
        elif sim_context is not None:
            return sim_context  # Solo contextos disponibles
        elif sim_seeds is not None:
            return sim_seeds  # Solo seeds disponibles
        
        return 0.5  # Neutral si no hay información
    
    def _score_context_patterns(
        self, 
        term: str, 
        full_text: str,
        context_snippets: List[str]
    ) -> float:
        """
        Feature B: Patrones de contexto
        - Aparece en patrones definitorios
        - Aparece en bullets/headers (heurística simple)
        
        Returns:
            Score 0.0 - 1.0
        """
        score = 0.0
        
        # 1. Patrones definitorios
        for pattern_template in self.definition_patterns:
            pattern = pattern_template.replace('{term}', re.escape(term))
            if re.search(pattern, full_text, re.IGNORECASE):
                score += 0.3
                break  # Un match es suficiente
        
        # 2. Aparece con capitalización (indica título/header)
        if term[0].isupper() and not term.isupper():
            # Buscar líneas donde aparece con capitalización
            lines = full_text.split('\n')
            for line in lines:
                if term in line and len(line.strip()) < 100:  # Línea corta = posible header
                    score += 0.2
                    break
        
        # 3. Aparece en bullets (detectar por símbolos comunes)
        bullet_patterns = [r'^\s*[-•*]\s+.*' + re.escape(term), 
                          r'^\s*\d+[.)]\s+.*' + re.escape(term)]
        for pattern in bullet_patterns:
            if re.search(pattern, full_text, re.MULTILINE | re.IGNORECASE):
                score += 0.2
                break
        
        # 4. Contextos ricos (snippets largos indican uso descriptivo)
        # Reducido a +0.1 máximo para no inflar genéricos
        if context_snippets:
            avg_snippet_len = np.mean([len(s) for s in context_snippets])
            if avg_snippet_len > 150:
                score += 0.1
            elif avg_snippet_len > 100:
                score += 0.05
        
        return min(1.0, score)  # Cap a 1.0
    
    def _score_pos_ner(self, term: str) -> Tuple[float, str, str]:
        """
        Feature C: POS tagging y NER
        - Soporta multiword (analiza tokens alfa)
        - Acronyms override: score >= 0.8, no penalizar por POS/NER
        - NOUN/PROPN: bonus
        - VERB/ADV/DET: penalización
        - PER (persona): penalización fuerte (excepto acrónimos)
        
        Returns:
            (score, pos_tag, ner_label)
        """
        doc = self.nlp(term)
        
        if len(doc) == 0:
            return 0.5, "UNKNOWN", ""
        
        # Detectar acrónimos - override
        is_acronym = self._is_acronym(term)
        if is_acronym:
            # v3.2: Acrónimos: score alto (0.85 -> 0.88), no penalizar por NER/POS
            return 0.88, "ACRONYM", ""
        
        # Multiword: analizar todos los tokens alfabéticos
        if ' ' in term:
            alpha_tokens = [tok for tok in doc if tok.is_alpha]
            
            if not alpha_tokens:
                return 0.5, "UNKNOWN", ""
            
            # Contar POS tags
            pos_counts = Counter([tok.pos_ for tok in alpha_tokens])
            total_tokens = len(alpha_tokens)
            
            # Calcular score por mayoría
            noun_count = pos_counts.get("NOUN", 0) + pos_counts.get("PROPN", 0)
            adj_count = pos_counts.get("ADJ", 0)
            verb_count = pos_counts.get("VERB", 0) + pos_counts.get("AUX", 0) + pos_counts.get("ADV", 0)
            
            if noun_count / total_tokens >= 0.5:
                score = 0.8
            elif adj_count / total_tokens >= 0.3:
                score = 0.7
            elif verb_count / total_tokens >= 0.5:
                score = 0.3
            else:
                score = 0.6  # Mix
            
            # NER: detectar PER en cualquier entidad
            ner_label = ""
            for ent in doc.ents:
                if ent.label_ == "PER":
                    score *= 0.2  # Penalizar personas
                    ner_label = "PER"
                    break
            
            pos_tag = "+".join([pos for pos, _ in pos_counts.most_common(2)])
            return score, pos_tag, ner_label
        
        # Single word: análisis normal
        token = doc[0]
        pos = token.pos_
        
        # NER
        ner_label = ""
        for ent in doc.ents:
            if ent.text.lower() == term.lower():
                ner_label = ent.label_
                break
        
        # Scoring por POS
        score = 0.5  # Neutral por defecto
        
        if pos in ["NOUN", "PROPN"]:
            score = 0.8
        elif pos == "ADJ":
            score = 0.7
        elif pos in ["VERB", "ADV", "AUX"]:
            score = 0.3
        elif pos in ["DET", "ADP", "PRON"]:
            score = 0.1
        
        # Ajuste por NER
        if ner_label == "PER":
            score *= 0.2  # Penalización fuerte para personas
        elif ner_label in ["ORG", "LOC"]:
            score *= 0.9  # Penalización leve
        elif ner_label in ["MISC", "PRODUCT"]:
            score = max(score, 0.7)  # Bonus para productos/miscelánea técnica
        
        return score, pos, ner_label
    
    def _score_doc_frequency(
        self, 
        term: str, 
        full_text: str,
        term_typology: Optional[str] = None
    ) -> Tuple[float, int, bool]:
        """
        Feature D: Frecuencia en el documento (con matching robusto por lemma)
        - Sweet spot: 2-10 apariciones (score alto)
        - 1 aparición: score depende de tipología (acrónimo=0.85, rare_term=0.75, default=0.6)
        - 0 apariciones: score bajo (modo domain_termness) o veto (modo in_doc_candidate)
        - >20 apariciones: penalización (posible genérico)
        
        Returns:
            (score, occurrences, morph_variant_match)
        """
        # Usar count_occurrences() para matching robusto
        occurrences, morph_variant_match = self.count_occurrences(term, full_text)
        
        cfg = self.config.scoring
        is_acronym = self._is_acronym(term)
        
        if occurrences == 0:
            # Modo domain_termness: no vetar, solo penalizar
            score = 0.2  # Score bajo pero no cero
        elif cfg.doc_freq_sweet_spot_min <= occurrences <= cfg.doc_freq_sweet_spot_max:
            score = 1.0  # Óptimo
        elif occurrences == 1:
            # Scoring diferenciado por tipología
            if is_acronym:
                score = 0.85  # Acrónimos: alta validez con 1 mención
            elif term_typology == "rare_term":
                score = 0.75  # Términos raros: validez media-alta
            else:
                score = 0.6  # Default: validez media
        elif occurrences > cfg.doc_freq_too_common_threshold:
            # Penalización proporcional
            penalty = min(1.0, (occurrences - cfg.doc_freq_too_common_threshold) / 50)
            score = max(0.2, 0.6 - penalty)
        else:
            # Entre 11-19: score decreciente
            score = 0.8 - (occurrences - cfg.doc_freq_sweet_spot_max) * 0.05
            score = max(0.5, score)
        
        return score, occurrences, morph_variant_match
    
    def _score_shape_surface(self, term: str, full_text: str = "") -> float:
        """
        Feature E: Forma superficial del término
        - Acrónimo (2-6 uppercase): bonus
        - CamelCase/PascalCase: bonus
        - Guiones/underscores: bonus
        - Dígitos embebidos: bonus moderado
        - Todo minúsculas y corto (3-4): penalización EXCEPTO si contexto técnico
        
        v3.1.2 FIX: Detecta contexto técnico para NO penalizar lowercase corto
        como html, css, php que aparecen cerca de símbolos o extensiones.
        
        Returns:
            Score 0.0 - 1.0
        """
        score = 0.5  # Neutral
        
        cfg = self.config.scoring
        
        # 1. Acrónimo
        if term.isupper() and cfg.acronym_min_length <= len(term) <= cfg.acronym_max_length:
            # Verificar que no esté en lista de acrónimos a rechazar
            term_norm = self._normalize_text(term)
            if term_norm not in self.config.stopwords.universal_acronyms_reject:
                score += 0.4
        
        # 2. CamelCase o PascalCase
        camel_case_pattern = r'[a-z][A-Z]|[A-Z][a-z][A-Z]'
        if re.search(camel_case_pattern, term):
            score += 0.3
        
        # 3. Guiones o underscores (términos compuestos técnicos)
        if '-' in term or '_' in term:
            parts = re.split(r'[-_]', term)
            if len(parts) >= 2 and all(len(p) >= 2 for p in parts):
                score += 0.3
        
        # 4. Dígitos embebidos (versiones, modelos, etc.)
        if any(c.isdigit() for c in term) and any(c.isalpha() for c in term):
            score += 0.2
        
        # 5. v3.2: Penalización lowercase corto SOLO si NO está en contexto técnico
        if term.islower() and 2 <= len(term) <= 6:
            # Detectar contexto técnico con nueva función granular
            is_tech, _ = self._detect_short_tech_context(term, full_text)
            
            if not is_tech:
                # Penalizar palabras comunes ("base", "caja", "area")
                penalty = 0.3 if len(term) <= 4 else 0.2  # Menos penalización para 5-6 chars
                score -= penalty
            # Si está en contexto técnico (html, css, php, js, api), NO penalizar
        
        return min(1.0, max(0.0, score))
    
    def _has_technical_signals(self, term: str) -> bool:
        """Detecta si el término tiene señales técnicas (acrónimo, camelCase, guión)"""
        # Acrónimo
        if term.isupper() and 2 <= len(term) <= 6:
            return True
        
        # CamelCase
        if re.search(r'[a-z][A-Z]', term):
            return True
        
        # Guión o underscore
        if '-' in term or '_' in term:
            return True
        
        return False
    
    def _detect_short_tech_context(self, term: str, full_text: str) -> Tuple[bool, Dict[str, float]]:
        """
        v3.2: Detecta si término corto aparece en contexto técnico FUERTE.
        
        Retorna evidencia cuantificada sin hardcodear listas.
        
        Evidencias detectadas (cualquiera alcanza):
        a) Extensión de archivo (.html, .php, .css)
        b) Cerca de símbolos técnicos (< > { } ; / \)
        c) Alta densidad de símbolos en línea
        d) Listas técnicas (patrón tipo "HTML, CSS, PHP")
        
        Returns:
            (is_short_tech: bool, features: Dict[str, float])
        """
        features = {
            'file_extension': 0.0,
            'near_symbols': 0.0,
            'high_symbol_density': 0.0,
            'technical_list_pattern': 0.0,
            'parenthesis_acronym': 0.0  # NUEVO: (JS), (API)
        }
        
        if not full_text:
            return False, features
        
        term_lower = term.lower()
        term_upper = term.upper()
        
        # v3.2.1 CRITICAL FIX: Rechazar palabras comunes del español (stopwords/preposiciones)
        # ANTES de cualquier detección técnica para evitar FP gravísimos
        spanish_common_words = {
            'dentro', 'fuera', 'sobre', 'bajo', 'entre', 'desde', 'hasta', 'hacia',
            'para', 'por', 'sin', 'con', 'contra', 'ante', 'tras', 'según',
            'cuando', 'donde', 'como', 'porque', 'aunque', 'pero', 'sino',
            'este', 'ese', 'aquel', 'esta', 'esa', 'aquella', 'esto', 'eso', 'aquello',
            'todo', 'algo', 'nada', 'nadie', 'alguien', 'cual', 'quien',
            'muy', 'más', 'menos', 'tan', 'tanto', 'mucho', 'poco', 'bien', 'mal',
            'aquí', 'ahí', 'allí', 'ahora', 'antes', 'después', 'luego', 'siempre', 'nunca',
            'solo', 'sólo', 'tal', 'vez', 'quizá', 'quizás', 'acaso'
        }
        
        if term_lower in spanish_common_words:
            # Rechazar inmediatamente - son palabras funcionales del español
            return False, features
        
        # 0. Acrónimo entre paréntesis (ej: "JavaScript (JS)")
        # Muy específico y técnico
        if re.search(rf'\({re.escape(term_upper)}\)', full_text):
            features['parenthesis_acronym'] = 1.0
        
        # 1. Extensión de archivo (ej: ".php", ".html", ".css")
        if re.search(rf'\.{re.escape(term_lower)}\b', full_text, re.IGNORECASE):
            features['file_extension'] = 1.0
        
        # 2. Aparece cerca de símbolos técnicos (±50 chars, aumentado desde 30)
        technical_symbols = r'[<>{};/\\]'
        text_lower = full_text.lower()
        for match in re.finditer(rf'\b{re.escape(term_lower)}\b', text_lower):
            start = max(0, match.start() - 50)  # Ventana ampliada
            end = min(len(full_text), match.end() + 50)
            context_window = full_text[start:end]
            
            if re.search(technical_symbols, context_window):
                features['near_symbols'] = 1.0
                break
        
        # 3. Alta densidad de símbolos en líneas con el término
        lines = full_text.split('\n')
        for line in lines:
            if term_lower in line.lower():
                symbol_count = len(re.findall(technical_symbols, line))
                line_length = len(line.strip())
                
                if line_length > 0:
                    symbol_density = symbol_count / line_length
                    if symbol_density > 0.10:  # > 10% símbolos
                        features['high_symbol_density'] = 1.0
                        break
        
        # 4. Patrón de lista técnica (ej: "HTML5, CSS3, JavaScript" o "html/css/php" o "(JS)")
        # Detecta si término aparece en línea con lista estructural de tokens cortos
        # 4. Patrón de lista técnica (ej: "HTML5, CSS3, JavaScript" o "html/css/php" o "(JS)")
        # Detecta si término aparece en línea con lista estructural de tokens cortos
        # Soporta: minúsculas, versiones (HTML5), paréntesis (JS), separadores (, / - ; | " y ")
        # CRITERIO CLAVE: La línea debe tener señales técnicas para evitar FP
        lines = full_text.split('\n')
        for line in lines:
            # Matching flexible: term puede estar como "html" en "HTML5" o "js" en "(JS)"
            line_lower = line.lower()
            if term_lower in line_lower or f'({term_lower})' in line_lower or f'{term_lower}\d' in line_lower:
                # Extraer tokens cortos: 2-6 letras, permitir dígitos pegados y entre paréntesis
                # Ej: HTML5, CSS3, JavaScript, (JS), PHP
                tokens_in_line = re.findall(r'\b[A-Za-z]{2,6}(?:\d+)?\b|\([A-Za-z]{2,10}\)', line, re.IGNORECASE)
                tokens_base = [re.sub(r'\d+', '', t.strip('()')).lower() for t in tokens_in_line]
                
                # Buscar lista: >=2 tokens separados por delimitadores
                token_pattern = r'(?:\b[A-Za-z]{2,6}(?:\d+)?\b|\([A-Za-z]{2,10}\))'
                separator_pattern = r'(?:\s*[,/;|\-]\s*|\s+y\s+)'
                list_pattern = token_pattern + separator_pattern + token_pattern
                
                # Verificar: término en tokens (o como substring) + lista estructural
                term_in_tokens = any(term_lower in t or term_lower == t for t in tokens_base)
                
                if re.search(list_pattern, line, re.IGNORECASE) and term_in_tokens and len(tokens_in_line) >= 2:
                    # ANTI-FP: La línea debe tener señales técnicas FUERTES
                    # Además: debe haber al menos 1 token ALL CAPS puro (HTML, REST, API) de 3+ letras
                    # O acrónimo entre paréntesis (JS), (API)
                    # O dígitos (versiones) O símbolos técnicos
                    # Esto elimina listas tipo "stock, inventario, gastos, costos"
                    
                    has_all_caps_token = any(
                        t.isupper() and len(t) >= 3 and t.isalpha() 
                        for t in tokens_in_line
                    )
                    
                    has_parenthesis_acronym = bool(re.search(r'\([A-Z]{2,6}\)', line))
                    
                    technical_indicators = [
                        r'[<>{};/\\]',  # Símbolos técnicos
                        r'\.\w{2,6}\b',  # Extensiones (.html, .css)
                        r'\b(?:tecnología|tecnologías|stack|lenguaje|lenguajes|framework|api|código|script|frontend|backend|web|skills|herramientas|programming|utiliza|sistema)\w*\b',
                        r'\d+',  # Versiones (HTML5, PHP 8)
                    ]
                    
                    has_tech_signal = any(re.search(pattern, line, re.IGNORECASE) for pattern in technical_indicators)
                    
                    # Requiere: (ALL CAPS 3+ letras) O (paréntesis acronym) O (señales técnicas)
                    if has_all_caps_token or has_parenthesis_acronym or has_tech_signal:
                        features['technical_list_pattern'] = 1.0
                        break
        
        # Es short_tech si CUALQUIER evidencia está presente
        is_short_tech = any(v > 0 for v in features.values())
        
        return is_short_tech, features
    
    def _is_in_technical_context(self, term: str, full_text: str) -> bool:
        """
        v3.1.2: Detecta si término corto aparece en contexto técnico (sin hardcodear listas).
        
        Heurísticas:
        - Aparece como extensión de archivo (.html, .css, .php)
        - Aparece cerca de símbolos técnicos (< > { } ; /)
        - Aparece en líneas con alta densidad de símbolos
        
        Returns:
            True si contexto técnico, False si contexto operativo
        """
        if not full_text:
            return False
        
        term_lower = term.lower()
        
        # 1. Extensión de archivo (ej: ".php", ".html", ".css")
        if re.search(rf'\.{re.escape(term_lower)}\b', full_text, re.IGNORECASE):
            return True
        
        # 2. Aparece cerca de símbolos técnicos (±30 chars)
        technical_symbols = r'[<>{};/\\]'
        # Buscar ocurrencias del término
        text_lower = full_text.lower()
        for match in re.finditer(rf'\b{re.escape(term_lower)}\b', text_lower):
            start = max(0, match.start() - 30)
            end = min(len(full_text), match.end() + 30)
            context_window = full_text[start:end]
            
            # Si hay símbolos técnicos en ventana ±30
            if re.search(technical_symbols, context_window):
                return True
        
        # 3. Aparece en líneas con alta densidad de símbolos
        lines = full_text.split('\n')
        for line in lines:
            if term_lower in line.lower():
                # Contar símbolos técnicos en la línea
                symbol_count = len(re.findall(technical_symbols, line))
                line_length = len(line.strip())
                
                if line_length > 0:
                    symbol_density = symbol_count / line_length
                    # Densidad > 10% indica línea técnica (código, comandos)
                    if symbol_density > 0.10:
                        return True
        
        return False
    
    def _score_specificity(
        self, 
        term: str, 
        full_text: str
    ) -> float:
        """
        Feature F: Especificidad del término (combinación de doc_specificity + corpus_rarity)
        
        Estrategia:
        - doc_specificity (40%): penaliza si el lema está en top-K del documento
        - corpus_rarity (60%): penaliza por frecuencia en corpus español (word_frequency)
        
        La combinación refleja "rareza general" no solo "rareza en este documento".
        Esto separa palabras corporativas comunes (reporte, finanzas, logística)
        de términos técnicos realmente raros (devengamiento, amortización).
        
        Returns:
            Score 0.0 - 1.0 (1.0 = muy específico/raro, 0.0 = muy genérico/común)
        """
        # Construir lemma counter si no existe
        if self._doc_lemma_counter is None:
            self._build_doc_lemma_counter(full_text)
        
        # 1. DOC_SPECIFICITY: Penalizar si está en top-K del documento
        doc_specificity = 1.0  # Asumir específico por defecto
        
        # Lematizar término (si es multiword, usar primer token alfa)
        doc = self.nlp(term)
        alpha_tokens = [tok for tok in doc if tok.is_alpha]
        if not alpha_tokens:
            return 0.5  # Sin tokens alfabéticos, neutral
        
        lemma_norm = self._normalize_text(alpha_tokens[0].lemma_)
        
        # Penalizar si está en top-K del documento Y NO tiene señales técnicas
        if lemma_norm in self._doc_top_lemmas:
            has_tech = self._has_technical_signals(term)
            if not has_tech:
                doc_specificity -= 0.5
        
        # 2. CORPUS_RARITY: Penalizar por frecuencia en corpus español
        # v3.1.2: Usar _score_corpus_rarity() que maneja multiword con MIN por token
        corpus_rarity = self._score_corpus_rarity(term, full_text)
        
        # 3. COMBINAR: 40% doc_specificity + 60% corpus_rarity
        # corpus_rarity tiene más peso porque es lo que realmente distingue
        # "reporte" (común) de "devengamiento" (raro)
        specificity = 0.4 * doc_specificity + 0.6 * corpus_rarity
        
        return max(0.0, min(1.0, specificity))
    
    def _score_corpus_rarity(self, term: str, full_text: str = "") -> float:
        """
        Calcula rareza basada en word_frequency del corpus español.
        
        v3.1.2 FIX: Para multiword, usa MIN de rarezas por token para evitar FP
        como "Cards aprobadas" donde 1 token inglés raro contamina el score.
        
        Returns:
            Score 0.0 - 1.0 (1.0 = muy raro, 0.0 = muy común)
        """
        doc = self.nlp(term)
        alpha_tokens = [tok for tok in doc if tok.is_alpha]
        if not alpha_tokens:
            return 0.5
        
        # v3.1.2: Para multiword, calcular rareza por token y usar MIN
        if len(alpha_tokens) > 1:
            token_rarities = []
            for token in alpha_tokens:
                # Usar lemma CON acentos
                lemma_con_acentos = token.lemma_.lower()
                freq = word_frequency(lemma_con_acentos, 'es', wordlist='large')
                
                # Fallback a surface form si lemma muy raro
                if freq < 1e-6:
                    surface_lower = token.text.lower()
                    freq_surface = word_frequency(surface_lower, 'es', wordlist='large')
                    if freq_surface > freq:
                        freq = freq_surface
                
                FREQ_MAX = 1e-4
                token_rarity = 1.0 - min(freq / FREQ_MAX, 1.0)
                token_rarities.append(token_rarity)
            
            # MIN: el token MÁS COMÚN domina (evita que "Cards" raro haga pasar "aprobadas" común)
            corpus_rarity = min(token_rarities)
            return max(0.0, min(1.0, corpus_rarity))
        
        # Single word: lógica original
        lemma_con_acentos = alpha_tokens[0].lemma_.lower()
        freq = word_frequency(lemma_con_acentos, 'es', wordlist='large')
        
        # Fallback: Si lemma tiene frecuencia muy baja (< 1e-6), usar término original
        if freq < 1e-6:
            term_lower = term.lower()
            freq_term = word_frequency(term_lower, 'es', wordlist='large')
            if freq_term > freq:
                freq = freq_term
        
        FREQ_MAX = 1e-4
        corpus_rarity = 1.0 - min(freq / FREQ_MAX, 1.0)
        
        return max(0.0, min(1.0, corpus_rarity))
    
    def _is_rare_term(self, term: str, features: Dict[str, float], occurrences: int = 1) -> bool:
        """
        Detecta si el término es "raro/específico" técnico para usar threshold=0.52.
        
        PRIORIDAD: MINIMIZAR FALSOS POSITIVOS (palabras corporativas comunes).
        
        Estrategia MUY CONSERVADORA (v3.1):
        - Requiere pos_ner >= 0.7 (sustantivo/adj)
        - Requiere embedding_similarity >= 0.30 (cercano a seeds técnicos)
        - Requiere specificity >= 0.80 (no común en doc ni corpus)
        - Requiere corpus_rarity >= 0.55 (no muy frecuente en español)
        - Y además requiere ALGUNA de:
          * Señales técnicas en la forma (acrónimo, CamelCase, guión)
          * Patrones definitorios en contexto (context_patterns >= 0.30)
        
        Estos gates expulsan: costo, costos, gastos, presupuesto, finanzas.
        """
        emb = features.get("embedding_similarity", 0)
        pos = features.get("pos_ner", 0)
        ctx = features.get("context_patterns", 0)
        spec = features.get("specificity", 0)
        corpus_rarity = features.get("corpus_rarity", 0)
        
        # Gate 1: POS debe ser sustantivo/adj
        if pos < 0.7:
            return False
        
        # Gate 2: Embedding debe ser razonable
        if emb < 0.30:
            return False
        
        # Gate 3: Specificity debe ser alta (no común en doc ni corpus)
        if spec < 0.80:
            return False
        
        # Gate 4: Corpus rarity debe ser razonable
        if corpus_rarity < 0.55:
            return False
        
        # Gate 5: Debe tener señales técnicas reales O patrones definitorios
        has_tech_signals = self._has_technical_signals(term)
        has_definitional_pattern = ctx >= 0.30
        
        if not has_tech_signals and not has_definitional_pattern:
            return False
        
        return True
    
    def _determine_threshold(self, term: str, features: Optional[Dict[str, float]] = None, occurrences: int = 1, full_text: str = "") -> Tuple[float, str]:
        """
        Determina threshold dinámico según tipología del término
        
        Orden de evaluación (v3.2):
        1. Acronym (por forma): 0.48
        2. Short_tech (evidencia técnica + occurrences>=2): 0.55
        3. Techshape (por forma): 0.52
        4. Rare_term (por features post-scoring): 0.52
        5. Default: 0.58
        
        Returns:
            (threshold, typology)
        """
        # 1. Acrónimo/sigla (2-10 mayúsculas o mix OAuth/JWT/API)
        # v3.2: Incluir siglas de 2-6 chars ALL CAPS
        if term.isupper() and 2 <= len(term) <= 10:
            return 0.48, "acronym"
        
        # Mix mayúscula/minúscula estilo OAuth, JWT (no puro CamelCase)
        # Permite "/" y "-" para casos como "CI/CD"
        if len(term) <= 10:
            has_upper = any(c.isupper() for c in term if c.isalpha())
            has_lower = any(c.islower() for c in term if c.isalpha())
            if has_upper and has_lower:
                # Verificar que no sea CamelCase largo (ej: "OAuth" ✓ vs "PostgreSQL" ✗)
                if not re.search(r'[a-z]{3,}[A-Z]', term):  # No es "palabra[A-Z]"
                    return 0.48, "acronym"
        
        # 2. v3.2: Short technical token (2-6 chars, evidencia técnica)
        # Permitir occ=1 SOLO con evidencia FUERTE (file_extension, technical_list o parenthesis_acronym)
        if 2 <= len(term) <= 6 and full_text:
            is_short_tech, tech_features = self._detect_short_tech_context(term, full_text)
            strong_evidence = (
                tech_features.get('file_extension', 0.0) == 1.0 or 
                tech_features.get('technical_list_pattern', 0.0) == 1.0 or
                tech_features.get('parenthesis_acronym', 0.0) == 1.0
            )
            
            if is_short_tech and (occurrences >= 2 or strong_evidence):
                # Más estricto con 1 ocurrencia (solo si evidencia fuerte)
                if occurrences == 1 and strong_evidence:
                    return 0.60, "short_tech"  # Threshold más alto
                return 0.55, "short_tech"
        
        # 3. CamelCase, guión, underscore
        if re.search(r'[a-z][A-Z]', term) or '-' in term or '_' in term:
            return 0.52, "techshape"
        
        # 4. Rare term (requiere features calculados)
        if features and self._is_rare_term(term, features, occurrences=occurrences):
            return 0.52, "rare_term"
        
        # 5. Default
        return 0.54, "default"  # era 0.58 — reducido para capturar más términos en español
    
    def _extract_context_snippets(
        self, 
        term: str, 
        full_text: str,
        max_snippets: int = 3
    ) -> List[str]:
        """
        Extrae snippets de contexto donde aparece el término
        Usa normalización para encontrar matches (tolera tildes y mayúsculas)
        MEJORA v3.1: Filtra encabezados PDF y busca oraciones completas
        
        Args:
            term: Término a buscar
            full_text: Texto completo del documento
            max_snippets: Máximo de snippets a extraer
        
        Returns:
            Lista de snippets (strings) con oraciones completas
        """
        snippets = []
        term_norm = self._normalize_text(term)
        
        # Dividir texto en oraciones (más robusto que buscar por ventanas)
        # Regex para detectar fin de oración: punto seguido de espacio y mayúscula
        sentences = re.split(r'(?<=[.!?])\s+(?=[A-ZÁÉÍÓÚÑ])', full_text)
        
        # Patrones para detectar encabezados/pies de página PDF (DESCARTAR)
        pdf_header_patterns = [
            r'P\s*á\s*g\s*i\s*n\s*a',  # "Página" con espacios entre letras
            r'Creado:?\s*\w+\s*\d{4}',  # "Creado: octubre 2025"
            r'Última actualización:?',
            r'Documento preparado por:?',
            r'^\d+\s*$',  # Solo número de página
            r'^\s*\|\s*\d+\s*$',  # "| 1"
            r'©\s*\d{4}',  # Copyright
            r'Página\s+\d+\s+de\s+\d+',
        ]
        
        for sentence in sentences:
            sentence_norm = self._normalize_text(sentence)
            
            # Verificar si el término aparece en esta oración
            if term_norm not in sentence_norm:
                continue
            
            # FILTRAR encabezados/pies de página PDF
            is_pdf_header = False
            for pattern in pdf_header_patterns:
                if re.search(pattern, sentence, re.IGNORECASE):
                    is_pdf_header = True
                    break
            
            if is_pdf_header:
                continue  # Saltar encabezados PDF
            
            # FILTRAR oraciones muy cortas (menos de 30 caracteres = no descriptivas)
            if len(sentence.strip()) < 30:
                continue
            
            # PRIORIZAR oraciones con verbos (más descriptivas)
            # Verbos comunes en definiciones: "es", "permite", "consiste", "se utiliza", etc.
            has_verb = any(verb in sentence_norm for verb in [
                ' es ', ' son ', ' permite', ' consiste', ' utiliza', ' usa',
                ' sirve', ' refiere', ' define', ' describe', ' significa'
            ])
            
            # Agregar snippet (limpiar espacios extra)
            clean_snippet = re.sub(r'\s+', ' ', sentence).strip()
            
            if has_verb:
                # Insertar al principio (prioridad alta)
                snippets.insert(0, clean_snippet)
            else:
                # Agregar al final
                snippets.append(clean_snippet)
            
            if len(snippets) >= max_snippets:
                break
        
        # FALLBACK v3.1: Si no se encontraron snippets con oraciones, usar ventana de contexto
        if not snippets:
            logger.debug(f"[{term}] Fallback to window-based context extraction")
            term_norm = self._normalize_text(term)
            text_norm = self._normalize_text(full_text)
            
            idx = text_norm.find(term_norm)
            if idx != -1:
                window = 80  # ±80 chars
                ctx_start = max(0, idx - window)
                ctx_end = min(len(full_text), idx + len(term_norm) + window)
                snippet = full_text[ctx_start:ctx_end].strip()
                snippet = re.sub(r'\s+', ' ', snippet)
                snippets.append(snippet)
        
        return snippets[:max_snippets]
    
    def score_term(
        self, 
        term: str, 
        full_text: str,
        sector: Optional[str] = None
    ) -> TermScore:
        """
        Calcula el score completo de un término usando todas las features
        
        Args:
            term: Término a evaluar
            full_text: Texto completo del documento
            sector: Sector opcional para seeds contextuales
        
        Returns:
            TermScore con score total y breakdown de features
        """
        # 1. Veto duro (solo casos obvios)
        is_veto, veto_reason = self._hard_veto_check(term, full_text)
        if is_veto:
            threshold, typology = self._determine_threshold(term)
            return TermScore(
                term=term,
                total_score=0.0,
                features={"veto": 1.0, "reason": veto_reason},
                occurrences=0,
                context_snippet="",
                pos_tag="",
                ner_label="",
                is_valid=False,
                threshold_used=threshold,
                term_typology=typology
            )
        
        # 2. Extraer contextos primero
        context_snippets = self._extract_context_snippets(term, full_text)
        
        # 2b. Determinar tipología preliminar (para _score_doc_frequency)
        threshold_prelim, typology_prelim = self._determine_threshold(term, features=None, occurrences=1, full_text=full_text)
        
        # 2c. Score doc frequency con tipología preliminar (retorna occurrences + morph_variant_match)
        freq_score, occurrences, morph_variant_match = self._score_doc_frequency(
            term, full_text, term_typology=typology_prelim
        )
        
        # VETO: Si occurrences == 0 y mode == in_doc_candidate Y NO morph_variant_match, retornar early
        # Si morph_variant_match=True (ej: webhook encontrado como webhooks), NO aplicar veto
        if self.mode == "in_doc_candidate" and occurrences == 0 and not morph_variant_match:
            threshold, typology = self._determine_threshold(term)
            return TermScore(
                term=term,
                total_score=0.0,
                features={"veto": 1.0, "reason": "not_in_document"},
                occurrences=0,
                context_snippet="",
                pos_tag="",
                ner_label="",
                is_valid=False,
                threshold_used=threshold,
                term_typology=typology
            )
        
        # 3. Calcular cada feature
        weights = self.config.scoring.weights
        
        # A. Embedding similarity
        emb_score = self._score_embedding_similarity(term, context_snippets, sector)
        
        # B. Context patterns
        ctx_score = self._score_context_patterns(term, full_text, context_snippets)
        
        # C. POS/NER
        pos_score, pos_tag, ner_label = self._score_pos_ner(term)
        
        # v3.2: Detectar si es short_tech o acronym para ajustes condicionales
        is_acronym = self._is_acronym(term)
        is_short_tech, short_tech_features = self._detect_short_tech_context(term, full_text)
        
        # v3.2: Ajuste CONDICIONAL para acrónimos - clamp pos_ner mínimo
        if is_acronym and pos_score < 0.80:
            pos_score = 0.80  # Acrónimos: confiar en la forma, no en POS/NER
        
        # v3.2: Ajuste CONDICIONAL para short_tech - clamp pos_ner mínimo
        if is_short_tech and len(term) <= 6 and pos_score < 0.70:
            pos_score = 0.70  # Short tech: spaCy puede fallar en términos técnicos cortos
        
        # E. Shape/surface (v3.2: detecta contexto técnico, no penaliza short_tech)
        shape_score = self._score_shape_surface(term, full_text)
        
        # F. Specificity (antes genericity_in_doc)
        specificity_score = self._score_specificity(term, full_text)
        
        # G. Corpus rarity (v3.1.2: multiword usa MIN por token)
        corpus_rarity_score = self._score_corpus_rarity(term, full_text)
        
        # v3.2: Ajuste CONDICIONAL para short_tech - clamp corpus_rarity mínimo
        # wordfreq en español no es confiable para términos técnicos cortos
        if is_short_tech and len(term) <= 6:
            corpus_rarity_score = max(corpus_rarity_score, 0.60)  # Evitar penalización extrema, no bonus
        
        # 4. Combinar scores con pesos
        features = {
            "embedding_similarity": emb_score,
            "context_patterns": ctx_score,
            "pos_ner": pos_score,
            "doc_frequency": freq_score,
            "shape_surface": shape_score,
            "specificity": specificity_score,
            "corpus_rarity": corpus_rarity_score,
            "morph_variant_match": 1.0 if morph_variant_match else 0.0,
            "is_short_tech": 1.0 if is_short_tech else 0.0,  # v3.2: Flag para debug
            "short_tech_evidence": sum(short_tech_features.values()) if is_short_tech else 0.0  # v3.2: Evidencia acumulada
        }
        
        total_score = (
            emb_score * weights.embedding_similarity +
            ctx_score * weights.context_patterns +
            pos_score * weights.pos_ner +
            freq_score * weights.doc_frequency +
            shape_score * weights.shape_surface +
            specificity_score * weights.specificity
        )
        
        # v3.2 BOOST: Términos short_tech con evidencia FUERTE
        # Esto compensa scores bajos de embedding/context en términos técnicos muy específicos
        strong_evidence = False
        if is_short_tech:
            strong_evidence = (
                short_tech_features.get('file_extension', 0.0) == 1.0 or 
                short_tech_features.get('technical_list_pattern', 0.0) == 1.0 or
                short_tech_features.get('parenthesis_acronym', 0.0) == 1.0
            )
            if strong_evidence:
                # Boost multiplicativo: eleva el score base pero no rompe el techo
                short_tech_boost = 1.30  # +30%
                total_score *= short_tech_boost
                logger.debug(f"[{term}] Short tech boost: {short_tech_boost:.2f}x (evidencia fuerte)")
        
        # 4b. PENALIZACIÓN MULTIPLICATIVA POR SPECIFICITY (v3.1 - anti falsos positivos)
        # Términos corporativos genéricos (gestión, finanzas, proveedores, etc) tienen specificity baja
        # Esta penalización baja su score total incluso si tienen buena frecuencia/POS
        # v3.2: NO aplicar a short_tech con evidencia fuerte
        if not (is_short_tech and strong_evidence):
            specificity_penalty = 0.55 + 0.45 * specificity_score
            total_score *= specificity_penalty
            logger.debug(f"[{term}] Specificity penalty: {specificity_penalty:.3f} (spec={specificity_score:.3f})")
        
        # 4c. PENALIZACIÓN ADICIONAL: Top-K doc lemmas SIN señales técnicas (v3.1)
        # Si el lema está en top-K del documento Y NO tiene señales técnicas => genérico corporativo
        # v3.2: NO aplicar a short_tech con evidencia fuerte
        if not (is_short_tech and strong_evidence):
            doc_for_check = self.nlp(term)
            alpha_tokens_check = [tok for tok in doc_for_check if tok.is_alpha]
            if alpha_tokens_check:
                lemma_norm_check = self._normalize_text(alpha_tokens_check[0].lemma_)
                if lemma_norm_check in self._doc_top_lemmas:
                    has_tech_check = self._has_technical_signals(term)
                    if not has_tech_check:
                        top_lemma_penalty = 0.65
                        total_score *= top_lemma_penalty
                        logger.debug(f"[{term}] Top-doc-lemma penalty: {top_lemma_penalty:.3f} (lemma '{lemma_norm_check}' in top-K)")
        
        # 4d. PENALIZACIÓN CORPORATIVO-COMÚN: v3.2 - NO aplicar a acrónimos ni short_tech
        # Detecta palabras corporativas comunes (proveedor, factura, finanzas) que tienen:
        # - corpus_rarity < 0.90 (no son extremadamente raros)
        # - occurrences >= 2 (frecuentes en documento)
        # - NO es acrónimo
        # - NO es short_tech
        # - NO tiene señales técnicas
        
        # v3.2: Skip penalización para acrónimos y short_tech
        if not is_acronym and not is_short_tech:
            has_tech_signals_check = self._has_technical_signals(term)
            
            if not has_tech_signals_check:
                if corpus_rarity_score < 0.90 and occurrences >= 2:
                    corporate_common_penalty = 0.72
                    total_score *= corporate_common_penalty
                    logger.debug(f"[{term}] Corporate-common penalty: {corporate_common_penalty:.3f} (corpus_rarity={corpus_rarity_score:.3f}, occurrences={occurrences})")
        
        # 5. Determinar threshold dinámico FINAL (con features para detectar rare_term)
        threshold, typology = self._determine_threshold(term, features, occurrences=occurrences, full_text=full_text)
        
        # 6. Context snippet para output (NO truncar - preservar para glosario en app.py)
        context_snippet = context_snippets[0] if context_snippets else ""
        
        return TermScore(
            term=term,
            total_score=total_score,
            features=features,
            occurrences=occurrences,
            context_snippet=context_snippet,
            pos_tag=pos_tag,
            ner_label=ner_label,
            is_valid=False,  # Se establece después según threshold
            threshold_used=threshold,
            term_typology=typology
        )
    
    def score_and_rank_terms(
        self,
        candidates: List[str],
        full_text: str,
        threshold: Optional[float] = None,  # Ahora opcional (usa dinámico si None)
        sector: Optional[str] = None,
        top_n: Optional[int] = None,
        return_all_scored: bool = False  # v3.1: Para debug/verbose
    ) -> List[TermScore]:
        """
        Puntúa todos los candidatos, filtra por threshold y retorna rankeados
        
        Args:
            candidates: Lista de términos candidatos
            full_text: Texto completo del documento
            threshold: Threshold global opcional (si None, usa threshold dinámico por término)
            sector: Sector opcional para seeds
            top_n: Limitar a top N términos (None = todos)
            return_all_scored: Si True, retorna TODOS los scored terms (incluso inválidos), para debug
        
        Returns:
            Lista de TermScore ordenados por score (descendente)
        """
        logger.info(f"Scoring {len(candidates)} candidatos (mode={self.mode})...")
        
        # Score cada término
        scored_terms = []
        for term in candidates:
            score_result = self.score_term(term, full_text, sector)
            
            # Marcar como válido usando threshold dinámico o global
            if threshold is not None:
                # Threshold global fijo
                score_result.is_valid = score_result.total_score >= threshold
            else:
                # Threshold dinámico por término (usa >= no >)
                score_result.is_valid = score_result.total_score >= score_result.threshold_used
            
            scored_terms.append(score_result)
        
        # Ordenar por score
        scored_terms.sort(key=lambda x: x.total_score, reverse=True)
        
        # v3.1: Si return_all_scored=True, retornar todos (para debug)
        if return_all_scored:
            return scored_terms
        
        # Filtrar por validez
        valid_terms = [t for t in scored_terms if t.is_valid]
        
        if threshold is not None:
            logger.info(
                f"Términos válidos (score >= {threshold}): {len(valid_terms)}/{len(candidates)}"
            )
        else:
            logger.info(
                f"Términos válidos (threshold dinámico): {len(valid_terms)}/{len(candidates)}"
            )
        
        # Limitar a top_n si se especifica
        if top_n:
            valid_terms = valid_terms[:top_n]
        
        return valid_terms
    
    def score_terms_verbose(
        self,
        candidates: List[str],
        full_text: str,
        sector: Optional[str] = None,
        top_n: int = 30
    ) -> Dict:
        """
        Versión verbose de scoring para debugging (v3.1)
        Retorna dict con scoring completo + estadísticas de vetos/typologies
        
        Usage: Habilitado con env var DOCUGEST_DEBUG=1
        
        Returns:
            {
                'scored_terms_sorted': List[TermScore] (top N),
                'total_candidates': int,
                'veto_counts': {'not_in_doc': X, 'below_threshold': Y},
                'typology_distribution': {'acronym': X, 'techshape': Y, ...},
                'valid_count': int
            }
        """
        # Scoring completo con return_all_scored=True
        all_scored = self.score_and_rank_terms(
            candidates=candidates,
            full_text=full_text,
            sector=sector,
            return_all_scored=True
        )
        
        # Contar vetos y typologies
        veto_counts = {'not_in_doc': 0, 'below_threshold': 0}
        typology_distribution = {}
        valid_count = 0
        
        for ts in all_scored:
            if ts.is_valid:
                valid_count += 1
            else:
                if ts.occurrences == 0:
                    veto_counts['not_in_doc'] += 1
                else:
                    veto_counts['below_threshold'] += 1
            
            typology = ts.term_typology
            typology_distribution[typology] = typology_distribution.get(typology, 0) + 1
        
        return {
            'scored_terms_sorted': all_scored[:top_n],
            'total_candidates': len(all_scored),
            'veto_counts': veto_counts,
            'typology_distribution': typology_distribution,
            'valid_count': valid_count
        }

