# Phase 1: API RESTful Contracts Specification

**Backend**: Laravel (Hexagonal Architecture)  
**Consumer**: Flutter Mobile App (Roles: Barman y Administrador/Auditor)  
**Base URL**: `https://api.grupopuntofrio.com/api/v1` (o configurable localmente ej. `http://192.168.0.7:81/api/v1`)  

---

## 1. Autenticación y Perfil

### `POST /auth/login-pin`
Autenticación rápida de barra mediante PIN ciego de 4 dígitos (reconocimiento implícito de usuario y rol). Si es Administrador, no requiere sucursal y otorga acceso global.

- **Request**:
```json
{
  "pin": "1234",
  "sucursal_id": 1
}
```
*(Nota: `sucursal_id` es opcional si el PIN pertenece a un Administrador)*
- **Response 200 OK**:
```json
{
  "success": true,
  "data": {
    "token": "1|laravel_sanctum_token_string...",
    "usuario": {
      "id": 4,
      "nombre": "Carlos",
      "apellido": "Mendoza",
      "rol": "barman",
      "modalidad_cobro": "diario",
      "saldo_deudor": 0.00
    },
    "sucursal": {
      "id": 1,
      "nombre": "Casa22"
    },
    "turno_activo": {
      "id": 12,
      "tipo_turno": "dia",
      "estado": "abierto",
      "fecha_apertura": "2026-09-05 08:00:00"
    }
  }
}
```
- **Response 401 Unauthorized**:
```json
{
  "success": false,
  "error": "PIN incorrecto o usuario inactivo"
}
```

---

## 2. Gestión de Turnos y Cortes

### `POST /turnos/abrir`
Inicia un turno de 12 horas y asienta el corte físico de apertura.

- **Headers**: `Authorization: Bearer <token>`
- **Request**:
```json
{
  "sucursal_id": 1,
  "tipo_turno": "dia",
  "corte_inicial": [
    { "producto_id": 1, "cantidad": 48.00 },
    { "producto_id": 2, "cantidad": 24.00 },
    { "producto_id": 3, "cantidad": 3.75 }
  ]
}
```
- **Response 201 Created**:
```json
{
  "success": true,
  "message": "Turno abierto exitosamente",
  "data": {
    "turno_id": 15,
    "estado": "abierto",
    "fecha_apertura": "2026-09-05 08:00:00"
  }
}
```

---

### `POST /turnos/{id}/cerrar`
Registra el conteo final de inventario y cierra la jornada operativa.

- **Request**:
```json
{
  "corte_final": [
    { "producto_id": 1, "cantidad": 20.00 },
    { "producto_id": 2, "cantidad": 10.00 },
    { "producto_id": 3, "cantidad": 3.00 }
  ]
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "message": "Turno cerrado exitosamente",
  "data": {
    "turno_id": 15,
    "estado": "cerrado",
    "fecha_cierre": "2026-09-05 20:00:00",
    "total_comision_bruta": 14.00,
    "saldo_deudor_descontado": 0.00,
    "total_neto": 14.00
  }
}
```

---

### `POST /turnos/{id}/cobro-cajera`
Finaliza el turno, valida la evidencia fotográfica obligatoria del dinero en efectivo o comprobante de transferencia y genera el recibo digital con código único de 60 segundos.

- **Request** (`multipart/form-data` o `application/json` con base64):
```json
{
  "confirmacion_barman": true,
  "foto_comprobante": "data:image/jpeg;base64,/9j/4AAQSkZJRg..."
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "data": {
    "turno_id": 15,
    "estado": "cobrado",
    "codigo_recibo": "REC-8921-X",
    "total_neto_pagado": 14.00,
    "foto_comprobante_url": "https://api.grupopuntofrio.com/storage/cobros/recibo_15_foto.jpg",
    "moneda": "Bs",
    "tiempo_validez_segundos": 60,
    "fecha_cobro": "2026-09-05 20:05:12"
  }
}
```

---

## 3. Movimientos de Barra (Transformaciones, Bajas, Ingresos)

### `POST /transformaciones`
Ejecución atómica del relleno de botellas Corona consumiendo latas.

- **Request**:
```json
{
  "uuid_local": "550e8400-e29b-41d4-a716-446655440000",
  "turno_id": 15,
  "receta_id": 1,
  "cantidad_insumo_consumido": 14.00,
  "cantidad_terminado_obtenido": 12.00,
  "unidades_rotas": 1.00,
  "fecha_movimiento": "2026-09-05 14:30:00"
}
```
- **Response 201 Created**:
```json
{
  "success": true,
  "data": {
    "movimiento_consumo_id": 101,
    "movimiento_produccion_id": 102,
    "ratio_calculado": 1.167,
    "unidades_netas_comisionables": 11.00,
    "comision_generada": 11.00,
    "alerta_merma": false
  }
}
```

---

### `POST /ingresos` (Multipart Form Data)
Ingreso de mercadería externa con fotografía obligatoria.

- **Content-Type**: `multipart/form-data`
- **Fields**:
  - `uuid_local`: string (UUIDv4)
  - `turno_id`: integer
  - `producto_id`: integer
  - `cantidad`: decimal (ej. 48.00)
  - `foto`: file (image/jpeg, image/png)
  - `proveedor`: string (ej. 'Distribuidora Cervezas SRL')
  - `fecha_movimiento`: string (YYYY-MM-DD HH:MM:SS)
- **Response 201 Created**:
```json
{
  "success": true,
  "data": {
    "movimiento_id": 105,
    "foto_url": "https://api.grupopuntofrio.com/storage/ingresos/2026/09/foto_abc123.jpg",
    "cantidad_agregada": 48.00
  }
}
```

---

### `POST /inventario/compras` (Multi-Producto con Foto Obligatoria)
Registro atómico de facturas o notas de abastecimiento con múltiples productos.

- **Content-Type**: `multipart/form-data` o `application/json`
- **Request**:
```json
{
  "sucursal_id": 1,
  "proveedor": "Cervecería Boliviana Nacional",
  "numero_nota_factura": "F-90218",
  "foto_comprobante": "data:image/jpeg;base64,...",
  "total_costo_estimado": 1450.00,
  "observaciones": "Abastecimiento regular para fin de semana",
  "items": [
    { "producto_id": 1, "cantidad": 48.00, "costo_unitario": 8.50 },
    { "producto_id": 2, "cantidad": 24.00, "costo_unitario": 12.00 },
    { "producto_id": 4, "cantidad": 4.00, "costo_unitario": 110.00 }
  ]
}
```
- **Response 201 Created**:
```json
{
  "success": true,
  "message": "Compra registrada e inventario actualizado exitosamente",
  "data": {
    "compra_id": 12,
    "proveedor": "Cervecería Boliviana Nacional",
    "total_items": 3,
    "total_unidades": 76.00,
    "foto_comprobante_url": "https://api.grupopuntofrio.com/storage/compras/factura_12.jpg",
    "fecha_compra": "2026-09-05 16:30:00"
  }
}
```

---

## 4. Traspasos Inter-Sucursales Multi-Producto

### `POST /traspasos/enviar`
Envío de uno o varios productos en una sola orden de despacho inter-sucursal.

- **Request**:
```json
{
  "sucursal_destino_id": 2,
  "items": [
    { "producto_id": 2, "cantidad": 24.00 },
    { "producto_id": 1, "cantidad": 12.00 }
  ],
  "observaciones": "Envío de stock para fin de semana"
}
```
- **Response 201 Created**:
```json
{
  "success": true,
  "data": {
    "traspaso_id": 34,
    "estado": "en_transito",
    "fecha_envio": "2026-09-05 16:00:00",
    "total_items": 2
  }
}
```

---

### `POST /traspasos/{id}/recibir`
Confirmación física en destino revisando producto por producto con declaración de conformes y mermas en tránsito individuales.

- **Request**:
```json
{
  "items": [
    { "producto_id": 2, "cantidad_recibida_conforme": 22.00, "cantidad_merma_transito": 2.00 },
    { "producto_id": 1, "cantidad_recibida_conforme": 12.00, "cantidad_merma_transito": 0.00 }
  ],
  "observaciones": "2 botellas quebradas en la caja durante el traslado"
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "data": {
    "traspaso_id": 34,
    "estado": "recibido_con_discrepancia",
    "fecha_recepcion": "2026-09-05 17:15:00",
    "detalles": [
      { "producto_id": 2, "recibido_conforme": 22.00, "merma_transito": 2.00 },
      { "producto_id": 1, "recibido_conforme": 12.00, "merma_transito": 0.00 }
    ]
  }
}
```

---

## 4.1. Declaración Directa de Bajas y Roturas en Barra

### `POST /inventario/bajas`
Registro de rotura o merma operativa directa por el barman durante el turno.

- **Request**:
```json
{
  "turno_id": 12,
  "producto_id": 2,
  "cantidad": 1.00,
  "motivo": "Botella rota accidentalmente al destapar"
}
```
- **Response 201 Created**:
```json
{
  "success": true,
  "data": {
    "movimiento_id": 110,
    "turno_id": 12,
    "producto_id": 2,
    "cantidad_baja": 1.00,
    "motivo": "Botella rota accidentalmente al destapar"
  }
}
```

---

## 4.2. Gestión de Usuarios y Cambio de PIN (Módulo Admin)

### `GET /usuarios`
Listado completo de personal (requiere token de Admin).

- **Response 200 OK**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nombre": "Administrador",
      "apellido": "General",
      "rol": "admin",
      "activo": true,
      "sucursal_actual_id": null
    },
    {
      "id": 2,
      "nombre": "Carlos",
      "apellido": "Mendoza",
      "rol": "barman",
      "activo": true,
      "sucursal_actual_id": 1
    }
  ]
}
```

### `POST /usuarios`
Creación de un nuevo usuario por el Administrador.

- **Request**:
```json
{
  "nombre": "Lucas",
  "apellido": "Vargas",
  "rol": "barman",
  "pin": "5555",
  "sucursal_actual_id": 1,
  "modalidad_cobro": "diario"
}
```

### `PUT /usuarios/{id}/pin`
Cambio o restablecimiento de código PIN de un empleado.

- **Request**:
```json
{
  "nuevo_pin": "7777"
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "message": "PIN actualizado exitosamente"
}
```

---

## 5. Auditoría y Liquidación Semanal

### `GET /auditoria/turnos-pendientes`
Paso 1 del Asistente: Lista los turnos cerrados de una sucursal pendientes de conciliar con el Ticket Z.

- **Query Parameters**:
  - `sucursal_id`: integer (opcional si es Admin global)
- **Response 200 OK**:
```json
{
  "success": true,
  "data": [
    {
      "turno_id": 15,
      "sucursal": "Casa22",
      "barman": "Carlos Mendoza",
      "tipo_turno": "noche",
      "fecha_apertura": "2026-09-05 20:00:00",
      "fecha_cierre": "2026-09-06 08:00:00",
      "estado": "cerrado",
      "total_productos_contados": 18
    }
  ]
}
```

---

### `POST /auditoria/calcular`
Paso 2 y 3 del Asistente: Cruce de ventas del Ticket Z con desglose automático de combos y balance de inventario.

- **Request**:
```json
{
  "turno_id": 15,
  "ventas_ticket_z": {
    "productos_individuales": [
      { "producto_id": 2, "cantidad_vendida": 10.00 }
    ],
    "combos": [
      { "combo_id": 1, "cantidad_vendida": 5 }
    ]
  }
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "data": {
    "auditoria_id": 8,
    "turno_id": 15,
    "sucursal": "Casa22",
    "barman": "Carlos Mendoza",
    "detalles": [
      {
        "producto": "Corona en Botella",
        "stock_inicial": 24.00,
        "ingresos": 48.00,
        "traspasos_netos": 0.00,
        "transformaciones_producidas": 12.00,
        "bajas_roturas": 1.00,
        "stock_final": 43.00,
        "consumo_fisico_calculado": 40.00,
        "ventas_ticket_z_desglosadas": 40.00,
        "diferencia": 0.00,
        "resultado": "cuadrado",
        "alerta_color": "verde",
        "sancion_generada": 0.00
      }
    ]
  }
}
```

---

### `POST /auditoria/reporte-pdf`
Genera el informe oficial de auditoría con membrete del Grupo Punto Frío, detalle de diferencias y espacio de firma.

- **Request**:
```json
{
  "auditoria_id": 8
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "data": {
    "pdf_url": "https://api.grupopuntofrio.com/storage/reportes/auditoria_turno_15.pdf",
    "nombre_archivo": "Auditoria_Casa22_Turno15_20260905.pdf"
  }
}
```

---

### `GET /liquidaciones/semanal`
Liquidación semanal de sueldos de barmen titulares de noche (Sueldo Base Semanal menos sanciones por faltantes de inventario).
Soporta parámetros `fecha_inicio` y `fecha_fin` (o semana predeterminada `actual` / `anterior`).

- **Query Parameters**:
  - `fecha_inicio`: `2026-08-31` (opcional)
  - `fecha_fin`: `2026-09-06` (opcional)
  - `sucursal_id`: `1` (opcional, si es null consolida todas las sucursales para el Admin)
- **Response 200 OK**:
```json
{
  "success": true,
  "data": {
    "periodo": "2026-08-31 al 2026-09-06",
    "liquidaciones": [
      {
        "barman_id": 9,
        "barman": "Roberto Gómez",
        "rol": "barman",
        "turnos_noche_trabajados": 5,
        "sueldo_base_semanal": 700.00,
        "total_sanciones_faltantes": 50.00,
        "sueldo_neto_a_pagar": 650.00,
        "estado": "pendiente"
      }
    ]
  }
}
```

### `POST /liquidaciones/pagar`
Registrar el desembolso en efectivo del sueldo semanal del barman y sellar las sanciones de la semana como liquidadas.

- **Request**:
```json
{
  "barman_id": 9,
  "fecha_inicio": "2026-08-31",
  "fecha_fin": "2026-09-06",
  "monto_pagado": 650.00,
  "observaciones": "Pago semanal en efectivo"
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "message": "Sueldo liquidado y pagado exitosamente"
}
```

---

## 6. Catálogo Dinámico de Recetas y Combos (Módulo Administrativo)

### `GET /recetas/transformacion`
Lista todas las recetas de transformación activas e inactivas.

- **Response 200 OK**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "insumo_origen_id": 1,
      "insumo_nombre": "Cerveza en Lata",
      "producto_terminado_id": 2,
      "producto_nombre": "Corona en Botella",
      "tarifa_comision_bs": 1.00,
      "ratio_consumo_esperado": 1.15,
      "umbral_tolerancia_max": 1.30,
      "activo": true
    }
  ]
}
```

---

### `POST /recetas/transformacion`
Crea una nueva receta de transformación en el sistema.

- **Headers**: `Authorization: Bearer <admin_token>`
- **Request**:
```json
{
  "insumo_origen_id": 1,
  "producto_terminado_id": 2,
  "tarifa_comision_bs": 1.00,
  "ratio_consumo_esperado": 1.15,
  "umbral_tolerancia_max": 1.30,
  "activo": true
}
```
- **Response 201 Created**:
```json
{
  "success": true,
  "message": "Receta de transformación creada exitosamente",
  "data": {
    "id": 2,
    "insumo_origen_id": 1,
    "producto_terminado_id": 2,
    "tarifa_comision_bs": 1.00,
    "ratio_consumo_esperado": 1.15,
    "umbral_tolerancia_max": 1.30,
    "activo": true
  }
}
```

---

### `PUT /recetas/transformacion/{id}`
Actualiza parámetros operativos de una receta de transformación (tarifas, ratios, estado).

- **Headers**: `Authorization: Bearer <admin_token>`
- **Request**:
```json
{
  "tarifa_comision_bs": 1.50,
  "ratio_consumo_esperado": 1.20,
  "umbral_tolerancia_max": 1.35,
  "activo": true
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "message": "Receta actualizada exitosamente",
  "data": {
    "id": 1,
    "tarifa_comision_bs": 1.50,
    "ratio_consumo_esperado": 1.20,
    "umbral_tolerancia_max": 1.35,
    "activo": true
  }
}
```

---

### `GET /recetas/combos`
Lista las fórmulas de equivalencia de combos o baldes para auditoría de caja.

- **Response 200 OK**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nombre_combo": "Balde 6 Coronas",
      "producto_base_id": 2,
      "producto_base_nombre": "Corona en Botella",
      "unidades_equivalentes": 6,
      "activo": true
    }
  ]
}
```

---

### `POST /recetas/combos`
Crea una nueva regla de combo para el desglose automático de Ticket Z.

- **Headers**: `Authorization: Bearer <admin_token>`
- **Request**:
```json
{
  "nombre_combo": "Balde 10 Coronas",
  "producto_base_id": 2,
  "unidades_equivalentes": 10,
  "activo": true
}
```
- **Response 201 Created**:
```json
{
  "success": true,
  "message": "Receta de combo creada exitosamente",
  "data": {
    "id": 2,
    "nombre_combo": "Balde 10 Coronas",
    "producto_base_id": 2,
    "unidades_equivalentes": 10,
    "activo": true
  }
}
```

