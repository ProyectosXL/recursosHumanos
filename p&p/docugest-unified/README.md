---
title: DocuGest Unified API
emoji: 📚
colorFrom: blue
colorTo: green
sdk: docker
pinned: false
---

# DocuGest Unified API

API unificada para el sistema de gestión documental DocuGest.

## Endpoints

### Tags & Glosario
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/` | Info del servicio |
| GET | `/health` | Health check |
| POST | `/procesar` | Genera tags y glosario desde PDF en base64 |
| POST | `/procesar-upload` | Genera tags y glosario desde archivo PDF (testing) |

### RAG (Búsqueda semántica)
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/api/query` | Consulta en lenguaje natural sobre documentos |
| GET | `/api/stats` | Estadísticas del índice ChromaDB |
| POST | `/api/reindex/{document_id}` | Re-indexa un documento específico |
| DELETE | `/api/document/{document_id}` | Elimina documento del índice |

### Webhooks (llamados desde PHP)
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/webhook/document-uploaded` | Indexa nuevo documento al subir |
| POST | `/webhook/document-updated` | Re-indexa al actualizar documento |

### Admin
| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/admin/refresh` | Sincroniza todos los documentos desde PHP |
| POST | `/admin/reset-database` | Limpia ChromaDB para re-indexar todo |

## Variables de entorno requeridas

```
GOOGLE_API_KEY=<Gemini API key>
PHP_SERVER_URL=https://app.xl.com.ar/recursosHumanos/p&p
DOCUGEST_API_TOKEN=<secreto compartido con el .env del servidor PHP>
```

`DOCUGEST_API_TOKEN` se carga como **secreto** del Space y se envía en el header
`X-DocuGest-Token` al llamar a `api/listar_documentos.php` y `api/descargar_pdf.php`.

## Rolling update: Tags y Glosario con LLM

La nueva arquitectura de autotagging/glosario se integra en paralelo al pipeline legacy.

Configuracion actual en codigo (utils/config.py):

```
TAGS_GLOSSARY_PIPELINE_MODE = "shadow"
TAGS_GLOSSARY_LLM_MODEL_CANDIDATES = ["models/gemini-flash-lite-latest", "models/gemini-2.5-flash", ...]
TAGS_GLOSSARY_LLM_TEMPERATURE = 0.1
TAGS_GLOSSARY_LLM_MAX_OUTPUT_TOKENS = 1200
TAGS_GLOSSARY_LLM_MAX_CANDIDATES = 40
TAGS_GLOSSARY_LLM_MAX_TAGS = 20
TAGS_GLOSSARY_LLM_MAX_GLOSSARY = 10
TAGS_GLOSSARY_LLM_DOC_EXCERPT_CHARS = 3500
TAGS_GLOSSARY_LLM_MAX_DEFINITION_CHARS = 260
TAGS_GLOSSARY_LLM_ENABLE_FEWSHOT = True
```

### Seleccion de modelo Gemini

Google retira modelos sin aviso util: al 2026-07-28, `gemini-2.5-flash-lite` y
`gemini-2.5-flash` devuelven `404 ... no longer available to new users`, y la
serie 2.0 ya no tiene cuota free (429). Peor: `ListModels` los sigue listando,
asi que el catalogo no sirve para decidir; se descubre recien en la llamada real.

Por eso el modelo no esta hardcodeado. `utils/config.py` define listas en orden
de preferencia y `utils/llm_models.py` recorre esa lista:

- `404 / no longer available` -> descarta ese modelo para el resto del proceso.
- `429` -> desborda al siguiente candidato (es transitorio, no descarta nada).

Limites free tier medidos contra la API (por modelo, buckets independientes):

| Modelo                  | RPM free | Estado |
|-------------------------|----------|--------|
| `gemini-3.5-flash-lite` | 15       | OK - preferido para el chat/RAG |
| `gemini-3.1-flash-lite` | 15       | OK - cuota aparte, preferido para tags/glosario |
| `gemini-3.6-flash`      | 5        | OK - mas capaz, un tercio de throughput |
| `gemini-3.5-flash`      | 5        | OK (respondio 503 por alta demanda) |
| `gemini-2.5-flash*`     | -        | Retirado (404) |
| `gemini-2.0-flash*`     | -        | Sin cuota free (429) |

Como cada modelo tiene su propio bucket de RPM, separar el modelo del chat del
de indexado evita que el procesamiento de documentos le coma la cuota al usuario.

- Ver que modelos hay y cual esta en uso: `GET /api/modelos`
- Fijar un modelo puntual sin tocar codigo: variables de entorno
  `GENERATION_MODEL` y `TAGS_GLOSSARY_LLM_MODEL` (aceptan lista separada por comas).
  En HF Space se configuran en Settings -> Variables.

Nota: el alias `gemini-flash-lite-latest` solo existe en la API `v1beta` (en `v1`
da 404); esta tercero en la lista como seguro ante retiros, y si no resuelve el
fallback sigue al siguiente candidato.

Modos disponibles:

- `legacy`: solo pipeline clasico (comportamiento actual)
- `shadow`: corre LLM en paralelo pero responde legacy (ideal para medir sin riesgo)
- `hybrid`: responde LLM con fallback automatico a legacy

Secuencia sugerida en HF Space:

1. Deploy con `shadow` para validar sin afectar la salida legacy.
2. Revisar metadata en `/procesar` (llm_ok, llm_shadow_tags, llm_shadow_glosario).
3. Cambiar a `hybrid` en `utils/config.py` cuando el resultado sea estable.

## Arquitectura

- **Tags/Glosario**: KeyBERT + YAKE + spaCy + Wikipedia API → modelo ML v3.2 (0 falsos positivos)
- **RAG**: Gemini text-embedding-004 + ChromaDB + Gemma 3-27B → búsqueda semántica
- **Scheduler**: APScheduler con refresh automático cada 24h desde PHP server
- **ChromaDB**: Se reconstruye automáticamente al iniciar desde el servidor PHP
