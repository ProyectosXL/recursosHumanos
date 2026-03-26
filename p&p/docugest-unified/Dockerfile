# DocuGest Unified API - Dockerfile para Hugging Face Spaces
# SDK: docker | Puerto: 7860 (requerido por HF)
FROM python:3.11-slim

# Metadatos
LABEL maintainer="DocuGest"
LABEL version="1.0.0"
LABEL description="DocuGest Unified API - Tags, Glosario y RAG"

WORKDIR /app

# Instalar dependencias del sistema
# build-essential: para compilar extensiones C (chromadb, etc.)
RUN apt-get update && apt-get install -y \
    build-essential \
    && rm -rf /var/lib/apt/lists/*

# Copiar requirements primero (mejor cache de Docker layers)
COPY requirements.txt .

# Instalar dependencias Python
RUN pip install --no-cache-dir -r requirements.txt

# Descargar modelo spaCy en tiempo de build (no en runtime)
# es_core_news_sm: modelo pequeño de español (~12MB)
RUN python -m spacy download es_core_news_sm

# Copiar código fuente
COPY . .

# Puerto 7860 (OBLIGATORIO para Hugging Face Spaces)
EXPOSE 7860

# Health check
HEALTHCHECK --interval=60s --timeout=15s --start-period=120s --retries=3 \
    CMD python -c "import urllib.request; urllib.request.urlopen('http://localhost:7860/health')" || exit 1

# Ejecutar la aplicación
CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "7860", "--workers", "1"]
