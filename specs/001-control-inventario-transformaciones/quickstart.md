# Phase 1: Quickstart & End-to-End Validation Guide

**Feature**: Sistema de Inteligencia y Control de Inventario (Grupo Punto Frío) v1.2  
**Feature Branch**: `001-control-inventario-transformaciones`  
**Date**: 2026-09-05  

---

## 1. Prerrequisitos de Entorno

- **Backend**:
  - PHP 8.2+ con extensiones: `pdo_mysql`, `gd`/`imagick`, `mbstring`, `openssl`.
  - Composer 2+.
  - Base de Datos MySQL 8.0+ o MariaDB 10.5+ (con soporte `DECIMAL(8,2)` y `utf8mb4`).
  - Apache / Nginx (o entorno local XAMPP en `c:/xampp/htdocs/next22`).
- **Frontend**:
  - Flutter SDK 3.19+ con Dart 3.3+.
  - Emulador Android / Simulador iOS o dispositivo móvil físico conectado.

---

## 2. Puesta en Marcha del Backend (Laravel)

```bash
# 1. Instalar dependencias del backend
composer install

# 2. Configurar entorno (.env)
cp .env.example .env
php artisan key:generate

# 3. Configurar conexión a la base de datos MySQL en .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=puntofrio_inventario
# DB_USERNAME=root
# DB_PASSWORD=

# 4. Ejecutar migraciones y seeds iniciales
php artisan migrate --seed

# 5. Enlazar almacenamiento para fotos de ingresos
php artisan storage:link

# 6. Levantar servidor local de desarrollo (o vía VirtualHost en XAMPP)
php artisan serve --port=8000
```

---

## 3. Puesta en Marcha del Frontend (Flutter)

```bash
# 1. Navegar al directorio de la app Flutter
cd frontend/puntofrio_app

# 2. Obtener paquetes de Flutter
flutter pub get

# 3. Configurar URL del backend (por defecto apunta a http://10.0.2.2:8000/api/v1 en emulador Android o http://localhost:8000/api/v1 en iOS)
# 4. Ejecutar aplicación en dispositivo
flutter run
```

---

## 4. Escenarios de Validación End-to-End

### Escenario 1: Login con PIN de 4 Dígitos y Apertura de Turno (12h)
1. **Acción**: En la pantalla de inicio de la app Flutter, seleccionar `Casa22`, tocar el usuario `Carlos Mendoza` y digitar el PIN `1234`.
2. **Validación**: La app valida el PIN contra `POST /api/v1/auth/login-pin`, recibe el token de sesión y muestra el formulario de corte inicial.
3. **Acción**: Registrar el corte de apertura (ej. Cerveza Lata: 48, Botella Corona: 24, Ron Bacardi: 3.50 cuartos).
4. **Resultado esperado**: Se emite `POST /api/v1/turnos/abrir`. El turno pasa a estado `abierto` y el dashboard principal de barra queda activo con botones gigantes "Tap & Go".

---

### Escenario 2: Registro de Relleno Atómico (Transformación)
1. **Acción**: El barman presiona el botón gigante "Relleno Corona".
2. **Entrada**: 
   - Materia prima consumida: 14 latas.
   - Producto terminado obtenido: 12 botellas Corona.
   - Roturas declaradas: 1 botella.
3. **Validación**: La app envía `POST /api/v1/transformaciones`.
4. **Resultado esperado**:
   - `DB::transaction()` descuenta 14 latas del inventario físico y suma 11 botellas netas (12 - 1).
   - Calcula el ratio de conversión empírico: `14 / 12 = 1.167 latas/botella`.
   - Asigna 11.00 Bs de comisión al barman.
   - Todo se ejecuta en menos de 1 segundo.

---

### Escenario 3: Ingreso con Fotografía Obligatoria
1. **Acción**: El barman presiona "Ingreso de Mercadería".
2. **Entrada**: Selecciona `Cerveza en Lata`, cantidad `48.00`, proveedor `Distribuidora SRL`.
3. **Validación de Regla de Evidencia**: El botón "Guardar" permanece inactivo hasta que se captura la foto de la nota de remisión con la cámara.
4. **Envío**: Se despacha la petición multipart a `POST /api/v1/ingresos`.
5. **Resultado esperado**: La imagen se almacena en `storage/app/public/ingresos/...`, se asienta el movimiento en `movimientos_inventario` y el stock físico de latas se incrementa en +48.

---

### Escenario 4: Cierre de Turno y Pantalla a Cajera (Anti-Fraude)
1. **Acción**: Al finalizar la jornada de 12 horas, el barman ingresa al cierre de turno, realiza el corte final y solicita la vista "Resumen a Cajera".
2. **Validación Visual**:
   - Se abre a pantalla completa con tipografía gigante: `Total a Pagar: 11.00 Bs`.
   - El reloj digital y segundero se mueven activamente en tiempo real (certificando que la app está viva y no es una captura de pantalla estática).
3. **Acción de Cobro**: La cajera entrega 11 Bs en efectivo. El barman presiona `"Cobro Recibido / Finalizar Turno"`.
4. **Resultado esperado**:
   - Se ejecuta `POST /api/v1/turnos/{id}/cobro-cajera`.
   - El turno pasa a estado `cobrado`.
   - Se despliega el recibo con código único `REC-8921-X` durante 60 segundos con temporizador regresivo.
   - Cualquier intento posterior de volver a abrir la pantalla de cobro muestra el banner inmutable de "Turno ya cobrado".

---

### Escenario 5: Auditoría con Desglose Automático de Combos (Admin Web)
1. **Acción**: El Administrador ingresa al panel web de auditoría e introduce las ventas del Ticket Z del turno cerrado:
   - 10 Coronas individuales.
   - 5 Baldes de 6 Coronas.
2. **Procesamiento**: El backend desglosa automáticamente `5 * 6 = 30 Coronas`, sumando un total vendido de `40 Coronas`.
3. **Resultado esperado**:
   - Compara las 40 ventas contra el consumo físico del turno:
     `Consumo Físico = Stock Inicial (24) + Ingresos (0) + Transformación (12) - Bajas (1) - Stock Final (0) = 35 Coronas`.
   - Discrepancia: faltan 5 unidades en el inventario respecto a la venta (o viceversa).
   - Si `Consumo Físico > Ventas`: Fila en color rojo (Faltante con imputación de sanción).
   - Si `Ventas > Consumo Físico`: Fila en color azul informativo (Sobrante sin sanción).

---

### Escenario 6: Validación de Resiliencia Offline-First (Flutter)
1. **Acción**: Activar el "Modo Avión" en el teléfono móvil del barman.
2. **Operación**: Registrar 1 Relleno de Corona y 1 Corte de turno.
3. **Comportamiento**: La app almacena las acciones en la tabla SQLite local `sync_queue` con estado `pending`. La interfaz responde de inmediato (`< 50ms`) sin errores de timeout.
4. **Restablecimiento**: Desactivar el "Modo Avión".
5. **Resultado esperado**: El `SyncCoordinator` detecta la reconexión, procesa en segundo plano los registros pendientes enviándolos a la API de Laravel y actualiza el indicador visual de la app a "Todo Sincronizado".

---

### Escenario 7: Compra Multi-Producto con Foto de Factura Obligatoria
1. **Acción**: El usuario accede a "Nueva Compra / Abastecimiento".
2. **Entrada**: 
   - Proveedor: `Cervecería Boliviana Nacional`, Factura: `F-90218`.
   - Captura fotográfica obligatoria de la nota física con la cámara.
   - Ítems: `Cerveza Paceña Lata` (48 u.) y `Cerveza Corona Botella` (24 u.).
3. **Validación**: Presionar "Guardar Compra".
4. **Resultado esperado**: La petición atómica `POST /api/v1/inventario/compras` crea la orden en `compras`, asienta los registros en `compras_detalles` e incrementa simultáneamente el stock físico de latas (+48) y botellas (+24) en la sucursal.

---

### Escenario 8: Asistente de Auditoría en 3 Pasos y Descarga de Reporte PDF
1. **Acción**: El Administrador abre el módulo "Auditoría de Turnos".
2. **Paso 1 (Selección)**: Elige la sucursal y selecciona un turno cerrado de la lista de turnos pendientes (`GET /api/v1/auditoria/turnos-pendientes`).
3. **Paso 2 (Ventas Ticket Z)**: Digita las ventas del Ticket Z desglosando combos (ej. 3 Baldes de Corona y 5 latas individuales).
4. **Paso 3 (Balance y PDF)**:
   - Visualiza el comparativo claro con semáforo: consumo físico vs ventas reales, con faltantes en rojo y sobrantes en azul.
   - Presiona el botón "Descargar Reporte PDF".
5. **Resultado esperado**: Se renderiza el documento PDF oficial con el membrete de Grupo Punto Frío, detalle de diferencias por producto, resumen de sanciones y campo de firmas para el auditor y el encargado.

---

### Escenario 9: Cobro de Comisión con Evidencia Fotográfica Obligatoria
1. **Acción**: En la pantalla "Resumen a Cajera", la cajera entrega el efectivo o comprobante de transferencia y el barman presiona `"Cobro Recibido / Finalizar Turno"`.
2. **Cámara y Vista Previa**: La aplicación abre de inmediato la cámara del dispositivo móvil. Se captura la foto del dinero en efectivo o del comprobante digital.
3. **Confirmación**: La app muestra una vista previa rápida de la fotografía tomada para verificar que sea legible. Al pulsar "Confirmar Pago":
4. **Resultado esperado**:
   - Se despacha `POST /api/v1/turnos/{id}/cobro-cajera` con la foto adjunta.
   - El turno pasa a estado `cobrado` vinculando la imagen como respaldo inmutable.
   - Se inicia la cuenta regresiva de 60 segundos con el código de recibo digital.

---

### Escenario 10: Corte de Apertura sin Error 400 y Compartir Acta PDF en WhatsApp
1. **Acción**: El barman ingresa al módulo "Corte Inventario" y selecciona "Corte de Apertura (Inicio de Turno)".
2. **Entrada de Inventario**: Selecciona la jornada (Día o Noche) y digita las unidades enteras y fracciones (0, 1/4, 1/2, 3/4) con visualización de nivel de botella. Todos los productos muestran sus nombres y tipo (`INSUMO` / `TERMINADO`) sin textos `(null)`.
3. **Confirmación**: Presiona "Confirmar y Abrir Turno".
4. **Resultado esperado**:
   - La petición `POST /api/v1/turnos/abrir` responde `201 Created` sin errores de truncamiento de ENUM (`tipo_corte`).
   - Se despliega el diálogo modal de confirmación con dos botones directos:
     - **"COMPARTIR EN WHATSAPP"**: Abre el menú nativo para compartir el PDF vectorial oficial con membrete del Grupo Punto Frío y líneas de firma directamente al grupo de WhatsApp de administración.
     - **"VER / IMPRIMIR PDF"**: Abre la vista previa de impresión nativa en alta resolución.

---

### Escenario 11: Recepción Multi-Producto para el Barman ("NUEVO INGRESO")
1. **Acción**: Desde el panel del barman, presiona la tarjeta morada "NUEVO INGRESO".
2. **Entrada Multi-Ítem**:
   - Proveedor pre-seleccionado por defecto: `Licorería Punto Frío (Central)`.
   - N° de Guía o Nota de Entrega digitado.
   - Foto obligatoria de la nota física mediante cámara o galería.
   - Presiona `+ AÑADIR OTRO PRODUCTO` para cargar múltiples productos de la misma nota (ej. 24 Latas de Huari y 12 Latas de Paceña).
3. **Confirmación**: Presiona "Confirmar Recepción de Mercadería".
4. **Resultado esperado**:
   - Se envía la compra multi-producto a `POST /api/v1/inventario/compras`.
   - El stock físico de todos los ítems de la nota se incrementa simultáneamente en la barra.

---

### Escenario 12: Insumos Intercambiables (H1) y Fórmulas Compuestas de Destilados
1. **Intercambio Rápido de Insumo Base (Admin)**:
   - En "Gestión de Recetas", el administrador presiona "Cambiar Insumo Base" en la receta de Corona.
   - Puede alternar en 1 solo tap entre `Corona Lata`, `Cerveza Moema`, `Cerveza Paceña` o `Cerveza Orureña`.
2. **Transformación con Insumo Físico Real (Barman)**:
   - En la pestaña "Relleno Cervezas", el barman selecciona la cerveza física que realmente utilizó (ej. Cerveza Moema o Paceña).
   - Se descuenta el stock del producto real utilizado y se incrementan las botellas de Corona terminadas.
3. **Mezcla Compuesta de Destilados**:
   - En la pestaña "Mezcla Destilados", el barman registra el consumo de fracciones de múltiples botellas:
     - `Whisky Blackstone 750ml` (0.50 btl).
     - `Whisky Chancellor 750ml` (0.50 btl).
     - Producción resultante: 1 botella de `Whisky Johnnie Walker Red Label 750ml`.
   - Se asienta la transformación compuesta mediante `POST /api/v1/transformaciones/relleno` descontando las fracciones de ambas botellas e incrementando el stock de Red Label con la respectiva comisión para el personal.

---

### Escenario 13: Gestión de Proveedores y Selección Rápida en Recepción (US19)
1. **Alta de Proveedor (Admin)**:
   - En `DashboardAdminScreen`, ingresar a "GESTIÓN DE PROVEEDORES".
   - Presionar "+ Nuevo Proveedor", ingresar `Distribuidora San Juan`, teléfono `70011223`, NIT `49582910` y confirmar.
2. **Selección en Recepción (Barman)**:
   - Ingresar a "RECEPCIÓN DE MERCADERÍA" (`IngresoMercaderiaScreen`).
   - Al abrir el desplegable de proveedores, escribir "San Juan" en el buscador y seleccionar al proveedor.
3. **Resultado esperado**:
   - La recepción se asienta vinculada formalmente a `Distribuidora San Juan` tanto en la compra como en el reporte de inventario.

---

### Escenario 14: Monitoreo por Sucursal y Generación de Informe PDF (US20)
1. **Acceso al Módulo (Admin)**:
   - En `DashboardAdminScreen`, presionar la tarjeta "MONITOREO Y REPORTES DE SUCURSAL".
2. **Selección de Sucursal y Turno**:
   - Seleccionar `Casa22`. El sistema carga la tarjeta del turno activo en vivo con su barman responsable y fecha de apertura.
3. **Generación de Informe PDF**:
   - Presionar "GENERAR INFORME OFICIAL PDF".
4. **Resultado esperado**:
   - Se renderiza el PDF consolidado con desglose completo: Conteo Inicial, Compras/Ingresos de proveedores con facturas, Traspasos entrantes/salientes, Bajas justificadas, Rellenos efectuados y Liquidación al barman.
   - Se puede compartir directamente al grupo de supervisores mediante el botón "COMPARTIR POR WHATSAPP".

---

### Escenario 15: Traspasos Reales con Validación de Stock y Bloqueo de Saldo Negativo (US21)
1. **Carga de Datos Reales**:
   - Ingresar a "Despachar Traspaso" (`EnviarTraspasoScreen`).
   - Verificar que el selector de destino liste sucursales reales de la base de datos (excluyendo la propia) y productos reales del catálogo.
2. **Validación de Stock Insuficiente**:
   - Para un producto con 4 botellas en stock en barra, ingresar cantidad `20.00`.
   - La app pinta el campo en rojo y muestra la alerta: `Stock insuficiente en barra (Disponible: 4, Solicitado: 20)`. El botón "DESPACHAR TRASPASO" se bloquea.
3. **Despacho Exitoso y Recepción en Destino**:
   - Cambiar la cantidad a `4.00` y confirmar el despacho.
   - El backend descuenta 4 botellas de la barra origen (`traspaso_salida`) y deja la orden `en_transito`.
   - En la sucursal receptora, el barman ingresa a "Recepcionar Traspasos" y confirma `4.00` conformes.
   - Se asienta `traspaso_entrada` y el stock se acredita formalmente en destino.

---

### Scenario 19: Ingresos Acumulados en PDF de Apertura y Referencia Visual en Corte de Cierre (US22)

1. **Apertura y Recepción**:
   - Abrir un turno en sucursal con conteo inicial (ej. Moema lata = 36.00).
   - Registrar una recepción de mercadería (ej. +12.00 latas de Moema).
   - En Dashboard Barman, presionar "📄 VER / RE-IMPRIMIR CONTEO DE APERTURA":
     - La tabla oficial en PDF muestra: `moema lata | 36.00 | +12.00 | 48.00` (eliminando guiones erróneos `-`).
2. **Referencia Visual en Corte de Cierre**:
   - Entrar al módulo "Corte de Cierre" (`CorteInventarioScreen`):
     - Debajo de "moema lata", se visualiza el pill informativo: `🏁 Inició: 36.00 | 📥 Ingresos: +12.00 | 📦 Disp: 48.00`.
     - Al ingresar un conteo de `10.00`, la app calcula en tiempo real: `📉 Salida / Venta estimada: 38.00 botellas`.
3. **Acta Oficial de Cierre en PDF**:
   - Confirmar el cierre de turno e inmutabilizar.
   - En el diálogo de éxito, presionar "VER / IMPRIMIR PDF":
     - El documento presenta el nombre real de cada producto (ej. "moema lata", sin nombres genéricos como "Producto #1").
     - Se eliminó la columna "Tipo".
     - La tabla despliega las columnas oficiales de reconciliación requeridas por el reglamento:
       `[#] | [PRODUCTO / INSUMO] | [INICIAL] | [INGRESOS (+)] | [RELLENOS (±)] | [BAJAS (-)] | [TOTAL CIERRE]`.
     - Al pie de la tabla se refleja el resumen de totales sumando unidades de inicio, entradas y entrega final.

---

### Scenario 20: Fiel Reflejo del Conteo Físico Inicial en Acta de Conteo en PDF (Apertura) y Persistencia (US23)

1. **Apertura de Turno con Conteo Físico Real**:
   - Entrar al módulo "Corte de Apertura" (`CorteInventarioScreen`).
   - Seleccionar tipo de turno (ej. Día o Noche) e ingresar cantidades reales en el selector de botellas (ej. Moema = `24.00`, Corona = `10.00`, Fernet 750 = `2.00`).
   - Presionar "CONFIRMAR Y ABRIR TURNO" y confirmar la inmutabilidad en el diálogo modal.
2. **Validación en Diálogo Inmediato**:
   - En el diálogo "¡Turno de Barra Iniciado!", presionar "VER / IMPRIMIR PDF".
   - En el visor del documento "ACTA OFICIAL DE CONTEO FÍSICO Y BALANCE EN TURNO", verificar:
     - La columna `APERTURA` muestra exactamente `24.00`, `10.00`, `2.00` para los ítems contados (no `0.00`).
     - La columna `TOTAL DISP.` totaliza `24.00`, `10.00`, `2.00`.
     - El resumen de pie de página suma correctamente las unidades contadas.
3. **Compartir por WhatsApp**:
   - Presionar "COMPARTIR EN WHATSAPP": constatar que el PDF compilado y enviado preserva las cantidades reales.
4. **Reimpresión desde el Dashboard del Barman**:
   - En `DashboardBarmanScreen`, presionar "📄 VER / RE-IMPRIMIR CONTEO DE APERTURA":
   - El sistema consulta `GET /turnos/{id}/corte-inicial` y compila el PDF con las cantidades asentadas en base de datos idénticas al conteo inicial.
