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

---

## 8. Gestión de Proveedores (Admin y Recepción)

### `GET /proveedores`
Lista todos los proveedores registrados. Admite filtro opcional `?activo=1`.

- **Response 200 OK**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nombre": "Licorería Punto Frío (Central)",
      "contacto_nombre": "Oficina Central",
      "telefono": "78912345",
      "nit_o_ci": "102938475",
      "direccion": "Av. Principal #123",
      "activo": true
    },
    {
      "id": 2,
      "nombre": "Cervecería Boliviana Nacional (CBN)",
      "contacto_nombre": "Preventista Zona",
      "telefono": "67369293",
      "nit_o_ci": "495829102",
      "direccion": "Parque Industrial",
      "activo": true
    }
  ]
}
```

### `POST /proveedores`
Crea un nuevo proveedor en el catálogo comercial.

- **Headers**: `Authorization: Bearer <admin_token>`
- **Request**:
```json
{
  "nombre": "Embol / Coca-Cola",
  "contacto_nombre": "Distribuidor Norte",
  "telefono": "70011223",
  "nit_o_ci": "33445566",
  "direccion": "Av. Cristo Redentor",
  "activo": true
}
```
- **Response 201 Created**:
```json
{
  "success": true,
  "message": "Proveedor registrado exitosamente",
  "data": {
    "id": 3,
    "nombre": "Embol / Coca-Cola",
    "activo": true
  }
}
```

---

## 9. Monitoreo e Informe Operativo Integral por Sucursal / Turno

### `GET /auditoria/sucursal/{sucursal_id}/informe-turno`
Retorna el estado consolidado de la sucursal: el turno activo en vivo (o el último turno cerrado) con el balance detallado de masa y la liquidación del personal.

- **Query Parameters**:
  - `turno_id` (opcional): ID del turno específico a auditar. Si se omite, retorna el turno activo en curso o el último cerrado.
- **Response 200 OK**:
```json
{
  "success": true,
  "data": {
    "turno": {
      "id": 15,
      "sucursal": "Casa22",
      "barman": "Víctor Valverde",
      "tipo_turno": "noche",
      "estado": "abierto",
      "fecha_apertura": "2026-09-07 19:00:00",
      "fecha_cierre": null
    },
    "conteo_inicial": [
      { "producto_id": 1, "nombre": "Corona Botella 355ml", "cantidad": 48.00 },
      { "producto_id": 3, "nombre": "Moema Lata 355ml", "cantidad": 24.00 }
    ],
    "ingresos_compras": [
      {
        "id": 8,
        "proveedor": "Cervecería Boliviana Nacional (CBN)",
        "numero_nota_factura": "F-8921",
        "detalles": [
          { "producto_id": 3, "nombre": "Moema Lata 355ml", "cantidad": 24.00 }
        ]
      }
    ],
    "traspasos": {
      "entrantes": [],
      "salientes": [
        {
          "traspaso_id": 4,
          "sucursal_destino": "Madan",
          "producto": "Corona Botella 355ml",
          "cantidad": 12.00,
          "estado": "en_transito"
        }
      ]
    },
    "bajas_roturas": [
      { "producto": "Corona Botella 355ml", "cantidad": 1.00, "motivo": "Botella defectuosa" }
    ],
    "transformaciones": [
      {
        "receta": "Relleno Corona",
        "producido": 20.00,
        "consumido": 20.00,
        "comision_bs": 20.00
      }
    ],
    "balance_stock": [
      {
        "producto_id": 1,
        "nombre": "Corona Botella 355ml",
        "inicial": 48.00,
        "ingresos": 0.00,
        "traspasos_netos": -12.00,
        "bajas": 1.00,
        "rellenos_producidos": 20.00,
        "stock_actual_esperado": 55.00
      }
    ],
    "liquidacion": {
      "sueldo_base_semanal": 500.00,
      "comision_rellenos_bs": 20.00,
      "sanciones_bs": 0.00,
      "total_liquidado_bs": 520.00,
      "estado_pago": "pendiente"
    }
  }
}
```

---

## 10. Traspasos Inter-Sucursales con Validación Estricta de Stock

### `POST /traspasos/enviar`
Valida previamente que la sucursal de origen disponga de existencias físicas suficientes. Descuenta el inventario local mediante `traspaso_salida`.

- **Request**:
```json
{
  "sucursal_origen_id": 1,
  "sucursal_destino_id": 2,
  "items": [
    { "producto_id": 1, "cantidad": 12.00 }
  ],
  "observaciones": "Envío de urgencia para viernes noche"
}
```
- **Response 201 Created**:
```json
{
  "success": true,
  "data": {
    "traspaso_id": 6,
    "estado": "en_transito",
    "total_items": 1,
    "fecha_envio": "2026-09-07 20:15:00"
  }
}
```
- **Response 400 Bad Request** (Stock Insuficiente):
```json
{
  "success": false,
  "error": "Stock insuficiente de 'Corona Botella 355ml'. Disponible en tu turno: 4, requerido para traspaso: 12."
}
```

---

## 11. Endpoints para Rol Cajera y Suplencia Operativa (US24)

### `POST /turnos/{id}/confirmar-pago-comision`
Permite a la Cajera confirmar de forma autónoma el desembolso de comisiones devengadas por el barman, adjuntando la fotografía obligatoria del efectivo o comprobante.

- **Headers**: `Content-Type: multipart/form-data`, `Authorization: Bearer <token>`
- **Request Parameters**:
  - `foto`: Archivo de imagen (JPG/PNG, máx 5MB)
  - `observacion`: String opcional
- **Response 200 OK**:
```json
{
  "success": true,
  "message": "Desembolso de comisiones confirmado exitosamente",
  "data": {
    "turno_id": 14,
    "estado": "cobrado",
    "total_comision_neta_pagada": 18.00,
    "cobrado_por": "Cajera Andrea",
    "fecha_cobro": "2026-10-01 17:30:00",
    "codigo_recibo": "REC-8291"
  }
}
```

---

## 12. Endpoints para Conteo Resiliente y Productos Provisionales (US25)

### `POST /alertas/{id}/aprobar-producto`
Permite al Administrador convertir un producto contabilizado provisionalmente en un ítem oficial del catálogo maestro.

- **Request**:
```json
{
  "nombre_oficial": "Fernet Branca Menta 750ml",
  "codigo_barra": "7791234567890",
  "tipo": "terminado",
  "unidad_medida": "fraccion_cuartos",
  "precio_venta": 80.00
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "message": "Producto provisional aprobado y consolidado en el catálogo oficial",
  "data": {
    "producto_id": 28,
    "nombre": "Fernet Branca Menta 750ml",
    "alerta_resuelta": true
  }
}
```

### `POST /alertas/{id}/unificar-producto`
Permite al Administrador asociar el ítem provisional a un producto preexistente si el barman utilizó un nombre coloquial o erróneo.

- **Request**:
```json
{
  "producto_id_oficial": 12
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "message": "Conteo provisional transferido al producto oficial existente exitosamente",
  "data": {
    "producto_id_oficial": 12,
    "nombre_oficial": "Fernet Branca 750ml",
    "unidades_transferidas": 3.00,
    "alerta_resuelta": true
  }
}
```

---

## 13. Endpoints para Desbloqueo y Aplicación de Reconteo (US26)

### `POST /turnos/{id}/autorizar-reconteo`
Permite al Administrador conceder permiso efímero de corrección de conteo para un turno sellado.

- **Headers**: `Authorization: Bearer <token_admin>`
- **Request**:
```json
{
  "tipo_corte": "apertura",
  "motivo": "Garzón se equivocó digitando 2 Coronas en vez de 24"
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "message": "Reconteo autorizado exitosamente para corte de apertura",
  "data": {
    "turno_id": 15,
    "permite_reconteo": true,
    "reconteo_tipo": "apertura",
    "reconteo_autorizado_por": "Administrador General",
    "reconteo_autorizado_at": "2026-10-01 22:15:00",
    "reconteo_motivo": "Garzón se equivocó digitando 2 Coronas en vez de 24"
  }
}
```
- **Response 403 Forbidden**:
```json
{
  "success": false,
  "error": "Solo un usuario con rol de Administrador puede autorizar reconteos"
}
```

---

### `POST /turnos/{id}/aplicar-reconteo`
Permite al barman o cajera enviar las cantidades corregidas (conservando la memoria precargada del conteo previo). Se ejecuta bajo `DB::transaction()`, registra la auditoría de diferencias y auto-consume el permiso.

- **Headers**: `Authorization: Bearer <token>`
- **Request**:
```json
{
  "tipo_corte": "apertura",
  "corte_corregido": [
    { "producto_id": 1, "cantidad": 48.00 },
    { "producto_id": 2, "cantidad": 24.00 },
    { "producto_id": 3, "cantidad": 3.75 }
  ]
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "message": "Reconteo aplicado exitosamente. El turno ha sido re-sellado y el inventario recalculado.",
  "data": {
    "turno_id": 15,
    "tipo_corte": "apertura",
    "permite_reconteo": false,
    "auditoria_id": 3,
    "cambios_realizados": [
      {
        "producto_id": 2,
        "nombre_producto": "Corona Botella 330ml",
        "valor_anterior": 2.00,
        "valor_nuevo": 24.00,
        "diferencia": 22.00
      }
    ],
    "fecha_reconteo": "2026-10-01 22:18:30"
  }
}
```
- **Response 400 Bad Request**:
```json
{
  "success": false,
  "error": "El turno no tiene autorización activa para reconteo o el permiso ya expiró"
}
```

---

## 4.10. Sincronización POS RestoTech y Conciliación Triangulada

### `POST /sync/pos-transacciones`
Ingesta en lotes de transacciones de ventas y pagos enviadas por el Agente Local de Windows.
- **Headers**:
  - `X-Branch-Token: <token_secreto_sucursal>`
  - `Content-Type: application/json`
- **Request**:
```json
{
  "sucursal_id": 1,
  "fecha_envio": "2026-10-02 23:45:00",
  "transacciones": [
    {
      "pos_detalle_id": "10492",
      "pos_cuenta_id": "3021",
      "fecha_hora": "2026-10-02 23:30:12",
      "pos_producto_id": "COR-BOT",
      "pos_nombre_producto": "Cerveza Corona 330ml",
      "cantidad": 5.00,
      "precio_unitario": 25.00,
      "subtotal": 125.00,
      "metodo_pago": "efectivo"
    },
    {
      "pos_detalle_id": "10493",
      "pos_cuenta_id": "3022",
      "fecha_hora": "2026-10-02 23:35:40",
      "pos_producto_id": "BALDE-COR-6",
      "pos_nombre_producto": "Balde Corona x6",
      "cantidad": 2.00,
      "precio_unitario": 120.00,
      "subtotal": 240.00,
      "metodo_pago": "qr"
    }
  ]
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "data": {
    "recibidas": 2,
    "insertadas": 2,
    "mapeadas": 1,
    "pendientes_mapeo": 1,
    "mensaje": "Sincronización procesada exitosamente."
  }
}
```

---

### `GET /pos/mapeo-productos`
Listado de productos del POS y su asociación en c22 para la consola web.
- **Headers**: `Authorization: Bearer <token_admin>`
- **Query Params**: `sucursal_id=1`
- **Response 200 OK**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "pos_producto_id": "COR-BOT",
      "pos_nombre_producto": "Cerveza Corona 330ml",
      "c22_producto_id": 2,
      "c22_producto_nombre": "Corona Botella 330ml",
      "c22_combo_id": null,
      "estado": "mapeado"
    },
    {
      "id": 2,
      "pos_producto_id": "BALDE-COR-6",
      "pos_nombre_producto": "Balde Corona x6",
      "c22_producto_id": null,
      "c22_combo_id": 1,
      "c22_combo_nombre": "Balde Corona 6 unidades",
      "estado": "mapeado"
    }
  ]
}
```

---

### `POST /pos/mapeo-productos`
Crea o actualiza la vinculación entre un producto de RestoTech y un producto/combo de c22.
- **Headers**: `Authorization: Bearer <token_admin>`
- **Request**:
```json
{
  "sucursal_id": 1,
  "pos_producto_id": "BALDE-COR-6",
  "pos_nombre_producto": "Balde Corona x6",
  "c22_producto_id": null,
  "c22_combo_id": 1
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "message": "Mapeo actualizado exitosamente. El inventario retroactivo ha sido recalculado."
}
```

---

### `GET /auditoria/conciliacion-triangulada/{turno_id}`
Devuelve la matriz en 3 columnas comparativas para la pantalla de auditoría ejecutiva.
- **Headers**: `Authorization: Bearer <token_admin>`
- **Response 200 OK**:
```json
{
  "success": true,
  "data": {
    "turno_id": 15,
    "sucursal": "Casa22 Central",
    "fecha_turno": "2026-10-02",
    "responsables": {
      "caja": "Aracely Salas (Cajera)",
      "barra": "Carlos Mendoza (Barman)"
    },
    "auditoria_financiera": {
      "pos_ventas_brutas_bs": 8500.00,
      "planilla_efectivo_declarado_bs": 8200.00,
      "voucher_banco_depositado_bs": 8200.00,
      "diferencia_caja_bs": -300.00,
      "estado": "rojo_discrepancia",
      "imputado_a": "Aracely Salas (Cajera)"
    },
    "auditoria_inventario": {
      "botellas_vendidas_pos": 180.00,
      "botellas_consumo_fisico_barra": 180.00,
      "diferencia_botellas": 0.00,
      "estado": "verde_cuadrado",
      "imputado_a": null
    },
    "estado_general_semaforo": "ambar_observado"
  }
}
```

---

## 10. Webhook Inbound WhatsApp (Bot Silencioso y Gemini Vision)

### `POST /api/v1/webhook/whatsapp`
Punto de entrada invocado por el microservicio Node.js (Baileys) al recibir un mensaje o archivo en el grupo.

- **Headers**:
  - `Content-Type: application/json`
  - `X-Webhook-Secret: <secreto_configurado_en_env>`
- **Request**:
```json
{
  "remote_jid": "1203630283921@g.us",
  "sender_phone": "59170123456",
  "sender_name": "Mariela Encargada Casa22",
  "message_timestamp": 1728168000,
  "tipo": "imagen",
  "caption": "Planilla cierre domingo c22",
  "media_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
  "mimetype": "image/jpeg"
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "message": "Mensaje encolado para procesamiento autónomo con IA",
  "inbound_id": 104,
  "status": "pendiente_proceso"
}
```

---

### `POST /api/v1/whatsapp/confirmar-sucursal`
Permite a la contadora o administrador confirmar la sucursal de un mensaje que quedó con estado `[SUCURSAL_POR_CONFIRMAR]`.

- **Headers**: `Authorization: Bearer <token_admin>`
- **Request**:
```json
{
  "inbound_id": 104,
  "sucursal_id": 1,
  "turno_id": 15
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "message": "Sucursal vinculada y conciliación recalculada exitosamente."
}
```

### `GET /api/v1/whatsapp/grupos-auditables`
Consulta rápida invocada por el microservicio Node.js para cargar o refrescar en caliente la whitelist de JIDs autorizados (filtro Zero-Leakage).

- **Headers**: `X-Webhook-Secret: <secreto_configurado_en_env>`
- **Response 200 OK**:
```json
{
  "success": true,
  "data": [
    {
      "remote_jid": "1203630283921@g.us",
      "sucursal_id": 1,
      "sucursal_nombre": "Casa22",
      "tipo_auditoria": "cierre_recaudacion"
    },
    {
      "remote_jid": "1203630987654@g.us",
      "sucursal_id": 1,
      "sucursal_nombre": "Casa22",
      "tipo_auditoria": "gastos_caja_chica"
    },
    {
      "remote_jid": "1203630112233@g.us",
      "sucursal_id": 2,
      "sucursal_nombre": "Madan",
      "tipo_auditoria": "cierre_recaudacion"
    }
  ]
}
```

---

### `POST /api/v1/whatsapp/grupos`
Permite a Daniel vincular un nuevo grupo descubierto a una sucursal y tipo de auditoría desde la plataforma web.

- **Headers**: `Authorization: Bearer <token_admin>`
- **Request**:
```json
{
  "sucursal_id": 1,
  "remote_jid": "1203630283921@g.us",
  "nombre_grupo": "[C22] Cierres y Recaudación",
  "tipo_auditoria": "cierre_recaudacion"
}
```
- **Response 201 Created**:
```json
{
  "success": true,
  "message": "Grupo de WhatsApp vinculado exitosamente a la sucursal.",
  "data": {
    "id": 12,
    "sucursal_id": 1,
    "remote_jid": "1203630283921@g.us",
    "nombre_grupo": "[C22] Cierres y Recaudación",
    "tipo_auditoria": "cierre_recaudacion",
    "activo": true
  }
}
```

---

## 11. Liquidación de Jornal para Garzones (Turno Día)

### `POST /api/v1/turnos/{id}/liquidar-garzon`
Liquida de inmediato el jornal de barra del garzón de turno día, descontando al costo las botellas faltantes.

- **Headers**: `Authorization: Bearer <token_usuario>`
- **Request**:
```json
{
  "usuario_id": 8,
  "jornal_base_bs": 120.00,
  "faltante_botellas_unidades": 1.00,
  "descuento_faltante_bs": 15.00,
  "total_neto_pagado_bs": 105.00,
  "foto_comprobante_url": "storage/comprobantes/jornal_turno_15_garzon_8.jpg"
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "message": "Jornal de garzón liquidado exitosamente",
  "data": {
    "jornal_id": 42,
    "turno_id": 15,
    "total_neto_pagado_bs": 105.00,
    "codigo_recibo": "JRN-8921",
    "expira_en_segundos": 60
  }
}
```

---

## 12. Panel Web de Gestión y Vinculación de WhatsApp (US36)

### `POST /api/v1/whatsapp/bot-status`
Invocado por el microservicio en Node.js (Baileys) para notificar cambios de estado en caliente, emitir el código QR en Base64 o informar el número de teléfono conectado.

- **Headers**:
  - `Content-Type: application/json`
  - `X-Webhook-Secret: <secreto_configurado_en_env>`
- **Request**:
```json
{
  "estado": "esperando_qr",
  "qr_code_data_url": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...",
  "telefono": null
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "message": "Estado del bot actualizado exitosamente"
}
```

---

### `GET /api/v1/whatsapp/bot-status`
Consultado por la interfaz web (`frontend_web`) para renderizar el código QR en tiempo real, el badge de estado y el número del teléfono enlazado.

- **Headers**: `Authorization: Bearer <token_admin>`
- **Response 200 OK**:
```json
{
  "success": true,
  "data": {
    "estado": "conectado",
    "qr_code_data_url": null,
    "telefono": "59167369293",
    "ultimo_ping": "2026-10-09 16:10:00"
  }
}
```

---

### `POST /api/v1/whatsapp/grupos-descubiertos`
Invocado por el bot de WhatsApp al conectarse (`groupFetchAllParticipating`) para sincronizar todos los grupos donde el número es participante.

- **Headers**:
  - `Content-Type: application/json`
  - `X-Webhook-Secret: <secreto_configurado_en_env>`
- **Request**:
```json
{
  "grupos": [
    {
      "jid": "1203630283921@g.us",
      "nombre": "[C22] Cierres y Recaudación",
      "participantes_count": 8
    },
    {
      "jid": "1203630987654@g.us",
      "nombre": "[Madan] Cierres de Turno",
      "participantes_count": 6
    }
  ]
}
```
- **Response 200 OK**:
```json
{
  "success": true,
  "message": "Grupos descubiertos sincronizados exitosamente",
  "total_sincronizados": 2
}
```

---

### `GET /api/v1/whatsapp/grupos-disponibles`
Consultado por la vista web de gestión para listar todos los grupos descubiertos en WhatsApp y su estado de vinculación a sucursales.

- **Headers**: `Authorization: Bearer <token_admin>`
- **Response 200 OK**:
```json
{
  "success": true,
  "data": [
    {
      "remote_jid": "1203630283921@g.us",
      "nombre_grupo": "[C22] Cierres y Recaudación",
      "participantes_count": 8,
      "vinculado": true,
      "sucursal_id": 1,
      "sucursal_nombre": "Casa22",
      "tipo_auditoria": "cierre_recaudacion",
      "activo": true
    },
    {
      "remote_jid": "1203630987654@g.us",
      "nombre_grupo": "[Madan] Cierres de Turno",
      "participantes_count": 6,
      "vinculado": false,
      "sucursal_id": null,
      "sucursal_nombre": null,
      "tipo_auditoria": null,
      "activo": false
    }
  ]
}
```

---

### `POST /api/v1/whatsapp/desconectar`
Permite a Daniel solicitar la desconexión del teléfono desde la web para generar un nuevo QR y vincular otro número.

- **Headers**: `Authorization: Bearer <token_admin>`
- **Response 200 OK**:
```json
{
  "success": true,
  "message": "Solicitud de desconexión encolada. El bot generará un nuevo QR para vinculación."
}
```
