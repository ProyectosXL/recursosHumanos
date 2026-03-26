"""
Procesamiento de archivos PDF.
Extrae texto completo de documentos PDF usando PyMuPDF (fitz).
Soporta PDFs desde filesystem local o desde bytes (descargados vía HTTP).
"""

import logging
import os
import io
from pathlib import Path
from typing import Tuple
import fitz  # PyMuPDF

logger = logging.getLogger(__name__)


class PDFProcessingError(Exception):
    """Error durante el procesamiento de PDF."""
    pass


def extract_text_from_pdf_bytes(pdf_bytes: bytes) -> Tuple[str, int]:
    """
    Extrae texto de PDF en memoria (bytes descargados vía HTTP).
    
    Args:
        pdf_bytes: Contenido binario del PDF
        
    Returns:
        Tupla (texto_completo, numero_paginas)
        
    Raises:
        PDFProcessingError: Si hay error al procesar el PDF
        
    Example:
        >>> pdf_bytes = requests.get(url).content
        >>> text, pages = extract_text_from_pdf_bytes(pdf_bytes)
    """
    try:
        logger.info(f"Procesando PDF desde bytes ({len(pdf_bytes)} bytes)")
        
        # Crear stream en memoria
        pdf_stream = io.BytesIO(pdf_bytes)
        
        # Abrir PDF desde stream
        doc = fitz.open(stream=pdf_stream, filetype="pdf")
        
        # Verificar que el PDF tiene páginas
        if doc.page_count == 0:
            raise PDFProcessingError("El PDF no tiene páginas")
        
        # Extraer texto de todas las páginas
        text_parts = []
        for page_num in range(doc.page_count):
            try:
                page = doc[page_num]
                page_text = page.get_text()
                
                if page_text.strip():  # Solo agregar si hay contenido
                    text_parts.append(page_text)
                    logger.debug(f"Página {page_num + 1}/{doc.page_count}: {len(page_text)} caracteres")
                else:
                    logger.warning(f"Página {page_num + 1} está vacía")
                    
            except Exception as e:
                logger.error(f"Error al procesar página {page_num + 1}: {str(e)}")
                continue
        
        num_pages = doc.page_count
        doc.close()
        
        # Verificar que se extrajo texto
        if not text_parts:
            raise PDFProcessingError(
                "No se pudo extraer texto del PDF. Puede ser un PDF escaneado sin OCR"
            )
        
        full_text = "\n\n".join(text_parts)
        
        logger.info(
            f"Texto extraído exitosamente: {len(full_text)} caracteres, "
            f"{num_pages} páginas"
        )
        
        return full_text, num_pages
        
    except Exception as e:
        if "fitz" in str(type(e).__module__) or isinstance(e, RuntimeError):
            raise PDFProcessingError(f"Error de PyMuPDF: {str(e)}")
        else:
            raise PDFProcessingError(f"Error al procesar PDF desde bytes: {str(e)}")


def extract_text_from_pdf(pdf_path: str) -> Tuple[str, int]:
    """
    Extrae todo el texto de un archivo PDF.
    
    Args:
        pdf_path: Ruta completa al archivo PDF (puede ser absoluta o relativa)
        
    Returns:
        Tupla (texto_completo, numero_paginas)
        
    Raises:
        PDFProcessingError: Si hay error al procesar el PDF
        
    Example:
        >>> text, pages = extract_text_from_pdf("documentos/politica-123.pdf")
        >>> print(f"Extraído texto de {pages} páginas")
    """
    # Normalizar ruta (soporta Windows con backslashes)
    normalized_path = os.path.normpath(pdf_path)
    
    # Verificar que el archivo existe
    if not os.path.exists(normalized_path):
        raise PDFProcessingError(
            f"Archivo no encontrado: {normalized_path}"
        )
    
    # Verificar que es un archivo (no directorio)
    if not os.path.isfile(normalized_path):
        raise PDFProcessingError(
            f"La ruta no apunta a un archivo: {normalized_path}"
        )
    
    # Verificar extensión
    if not normalized_path.lower().endswith('.pdf'):
        raise PDFProcessingError(
            f"El archivo no es un PDF: {normalized_path}"
        )
    
    try:
        logger.info(f"Abriendo PDF: {normalized_path}")
        
        # Abrir el PDF
        doc = fitz.open(normalized_path)
        
        # Verificar que el PDF tiene páginas
        if doc.page_count == 0:
            raise PDFProcessingError(
                f"El PDF no tiene páginas: {normalized_path}"
            )
        
        # Extraer texto de todas las páginas
        text_parts = []
        for page_num in range(doc.page_count):
            try:
                page = doc[page_num]
                page_text = page.get_text()
                
                if page_text.strip():  # Solo agregar si hay contenido
                    text_parts.append(page_text)
                    logger.debug(f"Página {page_num + 1}/{doc.page_count}: {len(page_text)} caracteres")
                else:
                    logger.warning(f"Página {page_num + 1} está vacía o sin texto")
                    
            except Exception as e:
                logger.error(f"Error al procesar página {page_num + 1}: {str(e)}")
                # Continuar con las demás páginas
                continue
        
        # Guardar información antes de cerrar
        num_pages = doc.page_count
        
        # Cerrar el documento
        doc.close()
        
        # Verificar que se extrajo algo de texto
        if not text_parts:
            raise PDFProcessingError(
                f"No se pudo extraer texto del PDF. "
                f"Puede ser un PDF escaneado sin OCR: {normalized_path}"
            )
        
        # Unir todo el texto con saltos de línea
        full_text = "\n\n".join(text_parts)
        
        logger.info(
            f"Texto extraído exitosamente: {len(full_text)} caracteres, "
            f"{num_pages} páginas"
        )
        
        return full_text, num_pages
        
    except Exception as e:
        # PyMuPDF cambió su estructura de excepciones en versiones recientes
        # Capturamos todas las excepciones relacionadas con el procesamiento
        if "fitz" in str(type(e).__module__) or isinstance(e, RuntimeError):
            raise PDFProcessingError(
                f"Error de PyMuPDF al procesar {normalized_path}: {str(e)}"
            )
        elif isinstance(e, PermissionError):
            raise PDFProcessingError(
                f"Sin permisos para leer el archivo: {normalized_path}"
            )
        else:
            raise PDFProcessingError(
                f"Error inesperado al procesar PDF {normalized_path}: {str(e)}"
            )


def validate_pdf_file(pdf_path: str) -> bool:
    """
    Valida que un archivo PDF existe y es accesible.
    
    Args:
        pdf_path: Ruta al archivo PDF
        
    Returns:
        True si el archivo es válido, False en caso contrario
        
    Example:
        >>> if validate_pdf_file("documentos/doc.pdf"):
        ...     print("PDF válido")
    """
    try:
        normalized_path = os.path.normpath(pdf_path)
        
        if not os.path.exists(normalized_path):
            logger.error(f"Archivo no existe: {normalized_path}")
            return False
        
        if not os.path.isfile(normalized_path):
            logger.error(f"No es un archivo: {normalized_path}")
            return False
        
        if not normalized_path.lower().endswith('.pdf'):
            logger.error(f"No es un PDF: {normalized_path}")
            return False
        
        # Intentar abrir para verificar que es un PDF válido
        doc = fitz.open(normalized_path)
        doc.close()
        
        return True
        
    except Exception as e:
        logger.error(f"Error al validar PDF {pdf_path}: {str(e)}")
        return False


def get_pdf_info(pdf_path: str) -> dict:
    """
    Obtiene información metadata del PDF.
    
    Args:
        pdf_path: Ruta al archivo PDF
        
    Returns:
        Diccionario con metadata del PDF
        
    Example:
        >>> info = get_pdf_info("documentos/doc.pdf")
        >>> print(f"Páginas: {info['pages']}, Tamaño: {info['size_kb']} KB")
    """
    try:
        normalized_path = os.path.normpath(pdf_path)
        
        if not os.path.exists(normalized_path):
            raise PDFProcessingError(f"Archivo no encontrado: {normalized_path}")
        
        # Información del archivo
        file_stats = os.stat(normalized_path)
        
        # Abrir PDF para obtener metadata
        doc = fitz.open(normalized_path)
        
        info = {
            'path': normalized_path,
            'filename': os.path.basename(normalized_path),
            'size_bytes': file_stats.st_size,
            'size_kb': round(file_stats.st_size / 1024, 2),
            'pages': doc.page_count,
            'metadata': doc.metadata,
            'is_encrypted': doc.is_encrypted,
        }
        
        doc.close()
        
        return info
        
    except Exception as e:
        logger.error(f"Error al obtener info del PDF {pdf_path}: {str(e)}")
        raise PDFProcessingError(f"Error al obtener info: {str(e)}")
