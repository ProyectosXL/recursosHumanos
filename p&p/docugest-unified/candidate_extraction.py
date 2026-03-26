"""
Extracción de candidatos a términos usando KeyBERT + YAKE + spaCy Noun Chunks
Sin filtrado hard-coded - solo extracción pura
"""

from typing import List, Tuple
from keybert import KeyBERT
import yake
import spacy
import logging

logger = logging.getLogger(__name__)


class CandidateExtractor:
    """Extrae candidatos a términos técnicos usando múltiples métodos"""
    
    def __init__(self):
        """Inicializa los extractores"""
        self.kw_model = KeyBERT()
        logger.info("✅ KeyBERT cargado")
        self.nlp = spacy.load("es_core_news_sm")
        logger.info("✅ spaCy cargado para noun chunks")
    
    def extract_with_keybert(
        self, 
        texto: str, 
        top_n: int = 50,
        ngram_range: Tuple[int, int] = (1, 3),
        diversity: float = 0.7
    ) -> List[Tuple[str, float]]:
        """
        Extrae keywords usando KeyBERT (embeddings)
        
        Args:
            texto: Texto del documento
            top_n: Número de candidatos a extraer
            ngram_range: Rango de n-gramas (1,3) = palabras simples hasta 3-gramas
            diversity: Diversidad en la selección (0-1)
        
        Returns:
            Lista de tuplas (término, score_keybert)
        """
        try:
            keywords = self.kw_model.extract_keywords(
                texto,
                keyphrase_ngram_range=ngram_range,
                stop_words='spanish',
                top_n=top_n,
                diversity=diversity,
                use_mmr=True  # Maximal Marginal Relevance para diversidad
            )
            
            logger.info(f"KeyBERT extrajo {len(keywords)} candidatos")
            return keywords
            
        except Exception as e:
            logger.error(f"Error en KeyBERT: {e}")
            return []
    
    def extract_with_yake(
        self, 
        texto: str, 
        top_n: int = 50,
        ngram_range: int = 3,
        deduplication_threshold: float = 0.9
    ) -> List[Tuple[str, float]]:
        """
        Extrae keywords usando YAKE (estadístico)
        
        Args:
            texto: Texto del documento
            top_n: Número de candidatos a extraer
            ngram_range: Máximo n-grama a considerar
            deduplication_threshold: Umbral de deduplicación
        
        Returns:
            Lista de tuplas (término, score_yake) - NOTA: menor score = mejor
        """
        try:
            kw_extractor = yake.KeywordExtractor(
                lan="es",
                n=ngram_range,
                dedupLim=deduplication_threshold,
                top=top_n,
                features=None
            )
            
            keywords = kw_extractor.extract_keywords(texto)
            
            logger.info(f"YAKE extrajo {len(keywords)} candidatos")
            return keywords
            
        except Exception as e:
            logger.error(f"Error en YAKE: {e}")
            return []
    
    def extract_with_spacy_chunks(
        self,
        texto: str,
        top_n: int = 150
    ) -> List[Tuple[str, float]]:
        """
        Extrae noun phrases via spaCy dependency parsing.

        State-of-the-art en ATE (Automatic Term Extraction): los noun chunks
        son sintagmas nominales completos, siempre encabezados por un sustantivo.
        Captura términos compuestos en español como 'liquidación de haberes',
        'período de prueba', 'gestión de accesos' que YAKE y KeyBERT pueden omitir.

        Returns:
            Lista de tuplas (término, score_frecuencia_normalizada)
        """
        try:
            doc = self.nlp(texto[:100000])
            freq: dict = {}
            for chunk in doc.noun_chunks:
                text = chunk.text.strip()
                words = text.split()
                # Solo chunks de 1 a 4 palabras (evitar frases nominales muy largas)
                if 1 <= len(words) <= 4 and len(text) >= 3:
                    normalized = text.lower()
                    freq[normalized] = freq.get(normalized, 0) + 1

            max_freq = max(freq.values(), default=1)
            scored = [
                (term, count / max_freq)
                for term, count in freq.items()
            ]
            scored.sort(key=lambda x: x[1], reverse=True)
            result = scored[:top_n]
            logger.info(f"spaCy noun chunks: {len(result)} candidatos")
            return result
        except Exception as e:
            logger.error(f"Error en spaCy noun chunks: {e}")
            return []

    def extract_candidates(
        self,
        texto: str,
        top_n: int = 50,
        use_keybert: bool = True,
        use_yake: bool = True
    ) -> List[str]:
        """
        Combina KeyBERT + YAKE para extraer candidatos diversos
        
        Args:
            texto: Texto del documento
            top_n: Número de candidatos por método
            use_keybert: Usar KeyBERT
            use_yake: Usar YAKE
        
        Returns:
            Lista de candidatos únicos (strings)
        """
        candidatos = set()
        
        # KeyBERT: buenos para términos relevantes semánticamente
        if use_keybert:
            keywords_keybert = self.extract_with_keybert(texto, top_n=top_n)
            for term, score in keywords_keybert:
                candidatos.add(term.strip())
        
        # YAKE: buenos para términos frecuentes y distintivos
        if use_yake:
            keywords_yake = self.extract_with_yake(texto, top_n=top_n)
            for term, score in keywords_yake:
                # YAKE score bajo = relevante
                if score < 0.25:  # Filtro mínimo de calidad (0.25 captura más candidatos válidos)
                    candidatos.add(term.strip())
        
        candidatos_list = list(candidatos)
        logger.info(f"Total de candidatos únicos extraídos: {len(candidatos_list)}")
        
        return candidatos_list
    
    def extract_candidates_for_glossary(
        self, 
        texto: str,
        top_n: int = 30
    ) -> List[str]:
        """
        Extracción optimizada para glosario (términos más técnicos)
        Prioriza YAKE porque captura mejor términos técnicos recurrentes
        """
        candidatos = set()
        
        # YAKE con parámetros más estrictos para glosario
        keywords_yake = self.extract_with_yake(
            texto, 
            top_n=top_n * 2,  # Extraer más para filtrar después
            deduplication_threshold=0.85
        )
        
        for term, score in keywords_yake:
            # Para glosario: solo términos con score muy bajo (muy distintivos)
            if score < 0.08:
                candidatos.add(term.strip())
        
        # Complementar con KeyBERT (menos candidatos)
        keywords_keybert = self.extract_with_keybert(
            texto, 
            top_n=top_n,
            diversity=0.8  # Mayor diversidad para glosario
        )
        
        for term, score in keywords_keybert:
            candidatos.add(term.strip())
        
        candidatos_list = list(candidatos)
        logger.info(f"Candidatos para glosario: {len(candidatos_list)}")
        
        return candidatos_list