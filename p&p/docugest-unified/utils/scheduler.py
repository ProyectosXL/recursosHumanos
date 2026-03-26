"""
Scheduler para auto-refresh de documentos cada 24 horas.
Se activa en cualquier entorno cloud (HF Space o Railway).
"""

import logging
import time
import threading
import requests
from apscheduler.schedulers.background import BackgroundScheduler
from utils.config import IS_CLOUD, PORT

logger = logging.getLogger(__name__)


def refresh_documents():
    """
    Ejecuta refresh automático de documentos desde PHP server.
    Se conecta al endpoint /admin/refresh del mismo servicio.
    """
    try:
        logger.info("Iniciando refresh automático de documentos...")

        refresh_url = f"http://localhost:{PORT}/admin/refresh"
        logger.info(f"Conectando a: {refresh_url}")

        max_retries = 10
        retry_delay = 3
        response = None

        for attempt in range(1, max_retries + 1):
            try:
                logger.info(f"Intento {attempt}/{max_retries}...")
                response = requests.post(refresh_url, timeout=300)
                break

            except requests.exceptions.ConnectionError:
                if attempt < max_retries:
                    logger.warning(f"Servidor aún no está listo, reintentando en {retry_delay}s...")
                    time.sleep(retry_delay)
                else:
                    raise

        if response and response.status_code == 200:
            data = response.json()
            logger.info(
                f"Refresh exitoso: {data.get('nuevos_indexados', 0)} nuevos documentos indexados, "
                f"Total chunks: {data.get('total_chunks', 0)}"
            )
        elif response:
            logger.error(f"Refresh falló con código {response.status_code}: {response.text}")

    except requests.exceptions.Timeout:
        logger.error("Timeout en refresh (>5 minutos). Verificar cantidad de documentos.")
    except Exception as e:
        logger.error(f"Error inesperado en refresh automático: {str(e)}")


def start_scheduler():
    """
    Inicia el scheduler si estamos en entorno cloud (HF Space o Railway).
    El primer refresh se ejecuta en un hilo separado con 15s de delay,
    para que el servidor termine de iniciar antes de que se conecte a sí mismo.

    Returns:
        Scheduler instance o None si no está en cloud
    """
    if not IS_CLOUD:
        logger.info("Scheduler deshabilitado (entorno local/producción)")
        return None

    try:
        logger.info("Iniciando scheduler de auto-refresh...")

        scheduler = BackgroundScheduler(
            job_defaults={
                'coalesce': True,
                'max_instances': 1
            }
        )

        scheduler.add_job(
            refresh_documents,
            'interval',
            hours=24,
            id='refresh_documents_24h',
            name='Auto-refresh documentos desde PHP',
            replace_existing=True
        )

        scheduler.start()
        logger.info("Scheduler iniciado - refresh cada 24 horas")

        # Ejecutar el primer refresh en background con delay de 15s
        # para que el servidor termine de iniciar antes de conectarse a sí mismo
        def delayed_initial_refresh():
            logger.info("Primer refresh iniciará en 15 segundos (esperando startup del servidor)...")
            time.sleep(15)
            refresh_documents()

        thread = threading.Thread(target=delayed_initial_refresh, daemon=True)
        thread.start()
        logger.info("Primer refresh programado en background (+15s)")

        return scheduler

    except Exception as e:
        logger.error(f"Error al iniciar scheduler: {str(e)}")
        return None
