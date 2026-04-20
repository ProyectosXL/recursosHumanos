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
```

## Rolling update: Tags y Glosario con LLM

La nueva arquitectura de autotagging/glosario se integra en paralelo al pipeline legacy.

Configuracion actual en codigo (utils/config.py):

```
TAGS_GLOSSARY_PIPELINE_MODE = "shadow"
TAGS_GLOSSARY_LLM_MODEL = "models/gemini-2.5-flash-lite"
TAGS_GLOSSARY_LLM_TEMPERATURE = 0.1
TAGS_GLOSSARY_LLM_MAX_OUTPUT_TOKENS = 1200
TAGS_GLOSSARY_LLM_MAX_CANDIDATES = 40
TAGS_GLOSSARY_LLM_MAX_TAGS = 20
TAGS_GLOSSARY_LLM_MAX_GLOSSARY = 10
TAGS_GLOSSARY_LLM_DOC_EXCERPT_CHARS = 3500
TAGS_GLOSSARY_LLM_MAX_DEFINITION_CHARS = 260
TAGS_GLOSSARY_LLM_ENABLE_FEWSHOT = True
```

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
