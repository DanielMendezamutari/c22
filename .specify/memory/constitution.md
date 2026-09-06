<!--
Sync Impact Report:
- Version change: 0.0.0 (Unratified Template) → 1.0.0 (Ratified)
- List of modified principles:
  - [PRINCIPLE_1_NAME] → I. Descubrimiento de Rendimiento Real Empírico (Anti-Equivalencia Teórica)
  - [PRINCIPLE_2_NAME] → II. Justicia Financiera en Comisiones (Solo Valor Transformado)
  - [PRINCIPLE_3_NAME] → III. Responsabilidad Individual Estricta por Turno (Cortes de 12 Horas)
  - [PRINCIPLE_4_NAME] → IV. Desacoplamiento e Independencia del POS (Libro de Auditoría Inteligente)
  - [PRINCIPLE_5_NAME] → V. Auditoría Centralizada y Trazabilidad Multi-Sucursal
- Added sections:
  - [SECTION_2_NAME] → Reglas Operativas y Estándares de Auditoría
  - [SECTION_3_NAME] → Ciclo Operativo de Barra y Control de Calidad de Datos
- Removed sections: None
- Follow-up TODOs: None
-->

# Sistema de Inteligencia y Control de Inventario (Grupo Punto Frío) Constitution

## Core Principles

### I. Descubrimiento de Rendimiento Real Empírico (Anti-Equivalencia Teórica)
El sistema DEBE basar el análisis de merma y consumo en mediciones empíricas continuas y NUNCA imponer una equivalencia teórica estática no demostrada. En cada acción de "Transformación" (ej. latas consumidas para producir botellas listas de cerveza Corona), el operador DEBE registrar tanto la materia prima efectivamente consumida como las unidades terminadas resultantes. El motor analítico DEBE calcular históricamente los coeficientes reales de transformación, detectando mermas por espuma, derrames o fugas sospechosas mediante modelos de desviación estadística por barman y por sucursal.

### II. Justicia Financiera en Comisiones (Solo Valor Transformado)
Las comisiones e incentivos por "rellenado" o preparación DEBEN liquidarse única y exclusivamente sobre unidades reales transformadas y registradas activamente en el sistema por cada barman. Queda terminantemente PROHIBIDO calcular o dispersar incentivos basándose en ventas brutas de caja o sobre productos que ingresan terminados de fábrica sin requerir transformación en barra. Todo pago de comisión debe estar respaldado por un evento de transformación auditable.

### III. Responsabilidad Individual Estricta por Turno (Cortes de 12 Horas)
Todo turno de 12 horas DEBE iniciar y finalizar con un corte físico de inventario obligatorio, verificable e inmutable por parte del personal entrante y saliente. Este corte DEBE incluir la cuantificación auditable de licores abiertos y fracciones de botella. El sistema DEBE asociar inmediatamente cualquier faltante o merma no justificada al empleado y turno específicos en que ocurrió, impidiendo la dilución de responsabilidades entre jornadas.

### IV. Desacoplamiento e Independencia del POS (Libro de Auditoría Inteligente)
La plataforma DEBE operar como un libro contable y operativo de auditoría completamente independiente del Punto de Venta (POS). Su integridad no debe subordinarse a la lógica comercial o configuración del POS. La conciliación de ventas se realiza mediante el cruce de balance físico contra el ticket Z de cierre emitido por caja.

### V. Auditoría Centralizada y Trazabilidad Multi-Sucursal
Todas las operaciones de las casas actuales (Casa22, Casa Coron, Madan) y futuras sedes DEBEN consolidarse en un único panel centralizado de control y auditoría. Cada movimiento de inventario (recepciones de pedidos, transformaciones, traspasos entre sucursales, bajas por rotura o vencimiento) DEBE ser registrado con actor, marca de tiempo, origen, destino y justificación documental, garantizando trazabilidad de extremo a extremo.

## Reglas Operativas y Estándares de Auditoría

El sistema DEBE validar de forma obligatoria el cuadre diario de inventario aplicando la ecuación de balance de masa:
`Balance = (Stock Inicial + Ingresos + Traspasos - Materia Prima Usada + Producto Terminado) - (Bajas) - (Stock Final)`
Dicho balance DEBE conciliarse diariamente contra: `Ventas Reales (Ticket Z) + Faltantes/Mermas Justificadas`.

- Tolerancia Cero en Comisiones: El margen de error permitido en el pago de incentivos sobre productos no transformados es del 0.0%.
- Alertas de Rendimiento: Si la merma o ratio de consumo de un barman excede el umbral histórico tolerado para su sede (ej. 1.5 latas por botella producida frente a un estándar de 1.1), el sistema DEBE disparar una alerta automática de auditoría para inspección inmediata.
- Fracciones y Licores Abiertos: Los conteos de corte deben disponer de mecanismos estandarizados (conteo visual parametrizado o pesaje) para no omitir producto en uso.

## Ciclo Operativo de Barra y Control de Calidad de Datos

- Agilidad en Barra: Las interfaces de registro (recepción, transformación, corte) DEBEN diseñarse para captura rápida con mínima fricción operativa, permitiendo el registro de datos en segundos sin interrumpir el servicio.
- Inmutabilidad de Registros: Todo corte o transformación confirmada no podrá ser editado retroactivamente sin un evento de contra-asiento o autorización explícita de auditoría central.
- Trazabilidad de Traspasos: Ningún traspaso entre sucursales se considerará efectivo en el inventario receptor hasta que la sede receptora ejecute la confirmación de recepción física en el sistema.

## Governance

Esta Constitución representa la norma suprema de gobernanza técnica, operativa y funcional para el Sistema de Inteligencia y Control de Inventario de Grupo Punto Frío. Todo requerimiento, especificación de software (spec), plan de arquitectura (plan), lista de tareas (tasks) y revisión de código DEBE someterse a estricto cumplimiento con estos principios.

1. Procedimiento de Enmienda: Cualquier propuesta de modificación de principios o reglas requiere documentación formal del impacto operativo/financiero, aprobación de la dirección y auditoría del Grupo Punto Frío, y un plan de migración si afecta modelos de datos o métricas históricas.
2. Política de Versionado: Se aplica versionado semántico estricto:
   - MAJOR: Eliminación, redefinición o ruptura de principios esenciales de auditoría, balance o cálculo de comisiones.
   - MINOR: Incorporación de nuevos principios, integración de nuevas sucursales con reglas particulares o ampliación de módulos funcionales.
   - PATCH: Ajustes de redacción, clarificaciones operativas o correcciones tipográficas sin alteración sustantiva de reglas.
3. Auditoría Continua: Todo PR o cambio de código que introduzca funcionalidades de transformación, corte o comisiones debe incorporar pruebas automatizadas que garanticen el cumplimiento de la ecuación de balance y la política de cero comisiones en productos no transformados.

**Version**: 1.0.0 | **Ratified**: 2026-09-05 | **Last Amended**: 2026-09-05
