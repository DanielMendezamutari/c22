# Regla Estricta: Desarrollo Frontend Web con Plantilla Materialize VueJS

Esta regla es obligatoria y de máxima prioridad para cualquier desarrollo en la plataforma web de C22 / Punto Frío.

## 1. Estructura de Proyectos
- **Código de Producción:** Se desarrolla exclusivamente sobre el proyecto derivado de `admin-starter-kit` (ubicado en `frontend_web/`).
- **Catálogo de Referencia Obligatorio:** La carpeta `admin-full-version` (o `template_reference/admin-full-version/`) es la ÚNICA fuente de la verdad para el diseño de interfaces, componentes y patrones visuales.

## 2. Regla de Oro antes de Crear o Pintar Cualquier Pantalla
1. **Inspección Previa Mandatoria:** Antes de escribir una sola línea de código para una pantalla, botón, KPI card, tabla, filtro, modal o gráfica, el agente DEBE inspeccionar los archivos de la versión completa (`admin-full-version/src/...`) para identificar el componente oficial correspondiente.
2. **Prohibido Inventar Componentes o Estilos Raros:** 
   - No crear componentes desde cero con CSS personalizado si la plantilla ya provee un componente Vuetify / Materialize oficial (ej: `VCard`, `VDataTableServer`, `VBtn`, `VChip`, `VDialog`, componentes de ApexCharts oficiales de la plantilla).
   - Reutilizar exactamente la estructura de props, clases SCSS y layouts que maneja la versión completa.
3. **Fidelidad al Ecosistema de la Plantilla:**
   - Usar el sistema de iconos oficial de la plantilla (`@iconify/vue` o iconos integrados).
   - Usar la tipografía, variables de color del tema (Dark/Light) y tokens de Vuetify configurados en la plantilla.
   - Usar Pinia para el estado y composables oficiales (`useApi`, etc.) adaptados a la API de Laravel de `c22.ribersoft.com`.
