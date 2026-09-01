# Informe de finalización+ (`local_completionreport`)

Plugin **local** para Moodle **4.5+ / 5.x** que corrige el orden del reporte de finalización en cursos con **subsecciones**, con gráficos, exportación Excel y datos enriquecidos.

- **Autor:** John Rivera
- **Repositorio:** https://github.com/Johnrivera7/moodle_local_completionreport
- **Componente:** `local_completionreport`
- **Instalación:** `moodle/local/completionreport/`

---

## Problema que resuelve

El reporte estándar `report/progress` ordena actividades por **número interno de sección** en la base de datos. En cursos con subsecciones (Moodle 5), las subsecciones del módulo inicial pueden tener números altos (p. ej. 27–30) mientras que secciones de cierre tienen números bajos (p. ej. 7–8). Resultado: el informe muestra primero el **cierre** del curso y al final el **inicio**.

Este plugin recorre el curso en **orden visual** (`get_listed_section_info_all` + subsecciones delegadas).

---

## Instalación

1. Copiar esta carpeta a:

```text
moodle/local/completionreport/
```

2. **Administración del sitio → Notificaciones**
3. En el curso: menú del curso → **Informe de finalización+**
4. Capacidad: `local/completionreport:view`

---

## Funcionalidades

| Función | Detalle |
|--------|---------|
| Orden visual | De inicio a cierre, respetando subsecciones |
| Gráficos | Donut de progreso, barras por sección y por actividad |
| Resumen | Tarjetas: participantes, actividades, sin iniciar, en progreso, completados |
| Tabla | Columnas agrupadas por sección, % progreso, conteo, curso finalizado |
| Filtros | Por grupo (Moodle) y por sección/subsección |
| Búsqueda | Filtro local por nombre, correo o ID |
| Excel | CSV UTF-8 con BOM + filas de metadatos (ruta sección, tipo, orden) |
| Impresión | Vista exportable sin gráficos |

---

## Comparación con report/progress

| | report/progress | local_completionreport |
|--|-----------------|------------------------|
| Orden en curso con subsecciones | Incorrecto | Correcto |
| Gráficos | No | Sí |
| % progreso por usuario | No | Sí |
| Agrupación visual por sección | No | Sí |
| Metadatos en Excel | No | Sí |

---

## Desarrollo

```bash
cd moodle
npx grunt amd --root=local/completionreport
```

Purgar cachés tras cambios de plantillas o AMD.

---

## Licencia

GNU GPL v3 o posterior.
