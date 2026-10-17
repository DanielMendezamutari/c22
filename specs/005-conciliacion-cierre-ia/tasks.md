# Tasks: Conciliación Completa de Cierres de Encargadas con IA

**Input**: spec.md, research.md · **Tests**: SÍ (SC-001/SC-002; casos dorados).

## Phase 1: Motor de conciliación determinista (US2, US3) 🎯 base de todo
- [X] T001 [P] [US2] Value objects `Dinero`, `ResultadoRegla`, `ResultadoConciliacion`, `ReglasPago` en `backend/app/Domain/Cierre/`
- [X] T002 [P] [US2] `NombrePersona` (J. Pablo = Juan Pablo, Angy = Angie, filas repetidas) en `backend/app/Domain/Cierre/NombrePersona.php`
- [X] T003 [US2] `ConciliadorCierre` con reglas ING, EGR, SAL, SOB, GAR, PER, POS, COM, CHI, LIM, CHP, SHP, SHC, QRC, QRD, TXT, GNO/GDI y alertas en `backend/app/Domain/Cierre/ConciliadorCierre.php`
- [X] T004 [US3] `LiquidacionGlobalTurno` (garzones de día 5% de las 4 casas; encargadas base + 3%) en `backend/app/Domain/Cierre/LiquidacionGlobalTurno.php`
- [X] T005 [P] [US2] `ParserTextoCierre` (FR-003) en `backend/app/Domain/Cierre/ParserTextoCierre.php` + 38 tests (delegado a Codex)
- [X] T006 [US2] Casos dorados Fanny y Sisiela + 12 alteraciones + reglas entre casas en `backend/tests/Unit/Cierre/`
- [X] T007 [US1] Prioridad de "VIP" sobre "CASA22" en `ProcesarMensajeWhatsAppJob::inferirSucursalId` + test

## Phase 2: Extracción estructurada con IA (US1, US5)
- [ ] T008 [US1] Esquemas JSON por documento (planilla VENTAS, PAGO DE CHICAS, CONTROL DE SHAMPAGNE, Reporte POS, ticket por chica, sobre, comprobante, multi-documento por foto) con `responseSchema` en `backend/app/Infrastructure/AI/Cierre/`
- [ ] T009 [US1] Prompt con el formulario impreso exacto, notación "M"/"H", "caja chica no suma", personal de la BD, ejemplos verificados
- [ ] T010 [US5] Comando `cierres:evaluar-ia` que corre las fotos de `specs/005-.../fixtures/` y reporta % de campos correctos contra los casos dorados

## Phase 3: Lote de cierre (US1)
- [ ] T011 [US1] Migración y modelo `lotes_cierre` (casa, fecha operativa, turno, encargada, estado, resultado JSON)
- [ ] T012 [US1] Agrupar mensajes por encargada + casa + ventana de 10 min entre grupos; deduplicar por hash; fecha operativa desde planilla/POS
- [ ] T013 [US1] Job que concilia el lote al llegar la planilla + 10 min sin mensajes; reemplaza el despacho por foto individual del spec 004

## Phase 4: Informe para administración (US4)
- [ ] T014 [US4] Formateador de informe WhatsApp (estado, ingresos vs POS, egresos verificados, entrega, documentos, alertas)
- [ ] T015 [US4] Resumen diario de las 4 casas con `LiquidacionGlobalTurno`
- [ ] T016 [US5] Pantalla web de corrección de datos leídos (guarda valor IA vs correcto)

## Phase 5: Polish
- [X] T017 [P] Revisión cruzada con Codex (fase 1: 8 bugs de falso "conforme" corregidos con regresiones)
- [ ] T019 [US3] Calendario de casas por turno y día de semana (Madan solo jue–sáb noche) en `ReglasPago`/`LiquidacionGlobalTurno` en lugar de `casasPorTurno = 4`
- [ ] T020 Desactivar sucursales duplicadas (Madan 7 = Madam 3, Casa Corona 6 = corona 2, prueba 4) con confirmación del dueño
## Phase 6: Evidencia de otros grupos (US6)
- [ ] T021 [US6] Extraer de cada comprobante: beneficiario, monto, hora, concepto, cuenta destino; guardar como evidencia sin efectos contables hasta vincularse
- [ ] T022 [US6] `CruceEvidencia` en `backend/app/Domain/Cierre/`: vincular comprobantes a renglones de planilla (persona + monto exacto + noche; coincidencia única) y reportar pagos sin comprobante / comprobantes sin planilla
- [ ] T022a [US6] Casa por remitente: la encargada que envió el cierre de una casa esa noche identifica la casa de sus comprobantes, gastos y pedidos de taxi en los grupos generales (dueño 17-10: las encargadas mandan todo; en TAXI OFICIAL y en ADM casas 22 escriben la casa)
- [ ] T023 [US6] Hielo y gastos de "ADM casas 22" ↔ egresos de la planilla de la casa
- [ ] T024 [US6] Pedidos de móviles del grupo de taxis ↔ egreso de taxis por casa
- [ ] T025 [US6] Caso dorado: noche 16-10 de Hamacas (planilla + hoja + POS + "NATI 40" + "26")

- [ ] T018 Configuración `config/conciliacion.php` desde `.env` (cuenta QR oficial, bases, bonos)
