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
PHP_SERVER_URL=https://app.xl.com.ar/administracion/recursosHumanos/p&p
```

## Arquitectura

- **Tags/Glosario**: KeyBERT + YAKE + spaCy + Wikipedia API → modelo ML v3.2 (0 falsos positivos)
- **RAG**: Gemini text-embedding-004 + ChromaDB + Gemma 3-27B → búsqueda semántica
- **Scheduler**: APScheduler con refresh automático cada 24h desde PHP server
- **ChromaDB**: Se reconstruye automáticamente al iniciar desde el servidor PHP
