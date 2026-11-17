# Changelog — Josra Shopping Feed

## 1.0.1 — 2026-11-17
- Categorías de Google: sale el árbol completo, con botones para desplegar cada rama y «Desplegar todo / Plegar todo»; las ramas con algo asignado se abren solas.
- El número de productos de cada categoría cuenta también los de sus subcategorías (antes las principales salían con 0).
- El buscador de categorías encuentra palabras con o sin acentos («vehiculos» = «vehículos»): ahora usa una lista propia en vez de la del navegador, que filtraba por acentos. Se maneja también con flechas y Enter.
- Si la búsqueda no encuentra nada, enlace a la lista completa de categorías de Google para buscarla allí y escribir su número; enlace también en la ayuda de la pantalla.

## 1.0.0 — 2026-11-17
- Primera versión, basada en gshoppingflux 1.8.3 de Dim00z (Apache 2.0).
- Asistente de primera configuración en 6 pasos: tienda, qué productos, datos del producto, categorías de Google, envío y listo (genera el feed y explica cómo darlo de alta en Merchant Center).
- Pantalla principal con Resumen (URLs del feed con botón de copiar, artículos enviados, no enviados y avisos), Diagnóstico (productos que Google rechazará o marcará, con enlace a su ficha), Configuración en pestañas y Categorías de Google.
- Categorías de Google con buscador de la taxonomía oficial en español; se envía el ID y las subcategorías heredan.
- El feed se sirve desde la tienda y se regenera solo cuando Google lo lee y está viejo; URL de tarea programada opcional.
- Revisado según la especificación de Google: GTIN validado, `identifier_exists` bien calculado, MPN del fabricante, `backorder` con fecha de disponibilidad, `sale_price_effective_date`, `product_detail` con las características, etiquetas `custom_label`, imágenes de 500 px o más, sin atributos que Google no admite.
- Botón «Reiniciar el módulo» y «Volver a lanzar el asistente».
- Conexión al servidor de actualizaciones.
