import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

class InformeOperativoTurnoPdfService {
  static Future<Uint8List> generarPdfBytes(Map<String, dynamic> data) async {
    final pdf = pw.Document();

    final sucursal = data['sucursal'] ?? {};
    final turno = data['turno'] ?? {};
    final barman = turno['barman'] ?? {};
    final liquidacion = data['liquidacion'] ?? {};
    final List balance = (data['balance_inventario'] as List?) ?? [];
    final List compras = (data['compras'] as List?) ?? [];
    final List traspasosSal = (data['traspasos_salientes'] as List?) ?? [];
    final List traspasosEnt = (data['traspasos_entrantes'] as List?) ?? [];
    final List bajas = (data['bajas'] as List?) ?? [];

    final bool esEnVivo = turno['es_en_vivo'] == true || turno['estado'] == 'abierto';
    final String estadoTexto = esEnVivo ? 'TURNO EN VIVO (EN SERVICIO)' : 'TURNO CERRADO / HISTÓRICO';
    final PdfColor estadoColor = esEnVivo ? PdfColors.amber800 : PdfColors.teal800;

    final String sucursalNombre = (sucursal['nombre'] ?? 'Sucursal').toString().toUpperCase();
    final String turnoId = (turno['id'] ?? '-').toString();
    final String tipoTurno = (turno['tipo_turno'] ?? 'dia').toString().toUpperCase();
    final String barmanNombre = (barman['nombre'] ?? 'Barman no asignado').toString();
    final String fechaApertura = (turno['fecha_apertura'] ?? 'N/A').toString();
    final String fechaCierre = esEnVivo ? 'EN CURSO' : (turno['fecha_cierre'] ?? 'N/A').toString();

    pdf.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.letter,
        margin: const pw.EdgeInsets.all(28),
        build: (pw.Context ctx) => [
          // 1. Encabezado Corporativo
          pw.Container(
            padding: const pw.EdgeInsets.only(bottom: 10),
            decoration: const pw.BoxDecoration(
              border: pw.Border(bottom: pw.BorderSide(color: PdfColors.blueGrey800, width: 2)),
            ),
            child: pw.Row(
              mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
              crossAxisAlignment: pw.CrossAxisAlignment.start,
              children: [
                pw.Column(
                  crossAxisAlignment: pw.CrossAxisAlignment.start,
                  children: [
                    pw.Text(
                      'GRUPO PUNTO FRÍO',
                      style: pw.TextStyle(fontSize: 20, fontWeight: pw.FontWeight.bold, color: PdfColors.blue900),
                    ),
                    pw.SizedBox(height: 2),
                    pw.Text(
                      'INFORME OPERATIVO INTEGRAL Y AUDITORÍA DE TURNO',
                      style: pw.TextStyle(fontSize: 10, fontWeight: pw.FontWeight.bold, color: PdfColors.grey700),
                    ),
                    pw.SizedBox(height: 2),
                    pw.Text(
                      'Sede: $sucursalNombre  |  Turno: $tipoTurno  |  ID #$turnoId',
                      style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey600),
                    ),
                  ],
                ),
                pw.Column(
                  crossAxisAlignment: pw.CrossAxisAlignment.end,
                  children: [
                    pw.Container(
                      padding: const pw.EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: pw.BoxDecoration(
                        color: estadoColor,
                        borderRadius: pw.BorderRadius.circular(4),
                      ),
                      child: pw.Text(
                        estadoTexto,
                        style: pw.TextStyle(color: PdfColors.white, fontSize: 8, fontWeight: pw.FontWeight.bold),
                      ),
                    ),
                    pw.SizedBox(height: 4),
                    pw.Text(
                      'Emisión: ${DateTime.now().toString().substring(0, 16)}',
                      style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey600),
                    ),
                  ],
                ),
              ],
            ),
          ),
          pw.SizedBox(height: 12),

          // 2. Ficha de Datos del Turno y Personal
          pw.Container(
            padding: const pw.EdgeInsets.all(8),
            decoration: pw.BoxDecoration(
              color: PdfColors.grey100,
              borderRadius: pw.BorderRadius.circular(6),
              border: pw.Border.all(color: PdfColors.grey300),
            ),
            child: pw.Row(
              mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
              children: [
                pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.start, children: [
                  pw.Text('Barman en Custodia:', style: pw.TextStyle(fontSize: 8, fontWeight: pw.FontWeight.bold, color: PdfColors.grey700)),
                  pw.Text(barmanNombre, style: pw.TextStyle(fontSize: 10, fontWeight: pw.FontWeight.bold)),
                ]),
                pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.start, children: [
                  pw.Text('Hora de Apertura:', style: pw.TextStyle(fontSize: 8, fontWeight: pw.FontWeight.bold, color: PdfColors.grey700)),
                  pw.Text(fechaApertura, style: const pw.TextStyle(fontSize: 9)),
                ]),
                pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.start, children: [
                  pw.Text('Hora de Cierre:', style: pw.TextStyle(fontSize: 8, fontWeight: pw.FontWeight.bold, color: PdfColors.grey700)),
                  pw.Text(fechaCierre, style: const pw.TextStyle(fontSize: 9)),
                ]),
                pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.start, children: [
                  pw.Text('Estado Liquidación:', style: pw.TextStyle(fontSize: 8, fontWeight: pw.FontWeight.bold, color: PdfColors.grey700)),
                  pw.Text(
                    (liquidacion['estado_cobro'] ?? 'abierto').toString().toUpperCase(),
                    style: pw.TextStyle(
                      fontSize: 9,
                      fontWeight: pw.FontWeight.bold,
                      color: (liquidacion['estado_cobro'] == 'cobrado') ? PdfColors.green800 : PdfColors.orange800,
                    ),
                  ),
                ]),
              ],
            ),
          ),
          pw.SizedBox(height: 14),

          // 3. Balance de Masa de Inventario
          pw.Text(
            '1. BALANCE GENERAL DE INVENTARIO FÍSICO (UNIDADES Y FRACCIONES)',
            style: pw.TextStyle(fontSize: 10, fontWeight: pw.FontWeight.bold, color: PdfColors.blueGrey900),
          ),
          pw.SizedBox(height: 6),

          if (balance.isEmpty)
            pw.Container(
              padding: const pw.EdgeInsets.all(8),
              alignment: pw.Alignment.center,
              child: pw.Text('No se registraron movimientos en este turno.', style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey600)),
            )
          else
            pw.Table(
              border: pw.TableBorder.all(color: PdfColors.grey300, width: 0.5),
              columnWidths: {
                0: const pw.FlexColumnWidth(2.8), // Producto
                1: const pw.FlexColumnWidth(1.0), // Inicial
                2: const pw.FlexColumnWidth(1.1), // Ingresos
                3: const pw.FlexColumnWidth(1.1), // Trasp. Ent
                4: const pw.FlexColumnWidth(1.1), // Trasp. Sal
                5: const pw.FlexColumnWidth(0.9), // Bajas
                6: const pw.FlexColumnWidth(1.1), // Rellenos
                7: const pw.FlexColumnWidth(1.4), // Teórico Custodia
                8: const pw.FlexColumnWidth(1.2), // Final Físico
                9: const pw.FlexColumnWidth(1.1), // Dif
              },
              children: [
                pw.TableRow(
                  decoration: const pw.BoxDecoration(color: PdfColors.blueGrey100),
                  children: [
                    _cellHeader('Producto'),
                    _cellHeader('Inicial'),
                    _cellHeader('+Ingr.'),
                    _cellHeader('+Tr.In'),
                    _cellHeader('-Tr.Out'),
                    _cellHeader('-Baja'),
                    _cellHeader('Rell.'),
                    _cellHeader('Custodia'),
                    _cellHeader('Físico'),
                    _cellHeader('Dif.'),
                  ],
                ),
                ...balance.map((b) {
                  final ini = (b['stock_inicial'] as num?)?.toDouble() ?? 0.0;
                  final ing = (b['ingresos_compras'] as num?)?.toDouble() ?? 0.0;
                  final tin = (b['traspasos_entrantes'] as num?)?.toDouble() ?? 0.0;
                  final tout = (b['traspasos_salientes'] as num?)?.toDouble() ?? 0.0;
                  final baj = (b['bajas_roturas'] as num?)?.toDouble() ?? 0.0;
                  final rel = ((b['transformacion_destino'] as num?)?.toDouble() ?? 0.0) -
                              ((b['transformacion_origen'] as num?)?.toDouble() ?? 0.0);
                  final teo = (b['stock_teorico_custodia'] as num?)?.toDouble() ?? 0.0;
                  final fis = (b['stock_final_fisico'] as num?)?.toDouble();
                  final dif = (b['diferencia'] as num?)?.toDouble();

                  return pw.TableRow(
                    children: [
                      _cellText(b['producto_nombre'] ?? 'N/A', bold: true, align: pw.TextAlign.left),
                      _cellText(ini.toStringAsFixed(ini % 1 == 0 ? 0 : 2)),
                      _cellText(ing > 0 ? '+${ing.toStringAsFixed(ing % 1 == 0 ? 0 : 2)}' : '-'),
                      _cellText(tin > 0 ? '+${tin.toStringAsFixed(tin % 1 == 0 ? 0 : 2)}' : '-'),
                      _cellText(tout > 0 ? '-${tout.toStringAsFixed(tout % 1 == 0 ? 0 : 2)}' : '-'),
                      _cellText(baj > 0 ? '-${baj.toStringAsFixed(baj % 1 == 0 ? 0 : 2)}' : '-'),
                      _cellText(rel != 0 ? (rel > 0 ? '+$rel' : '$rel') : '-'),
                      _cellText(teo.toStringAsFixed(teo % 1 == 0 ? 0 : 2), bold: true),
                      _cellText(fis != null ? fis.toStringAsFixed(fis % 1 == 0 ? 0 : 2) : (esEnVivo ? 'Vivo' : '-')),
                      _cellText(
                        dif != null
                            ? (dif == 0 ? '0' : (dif > 0 ? '+$dif' : '$dif'))
                            : (esEnVivo ? 'Pend.' : '-'),
                        bold: true,
                        color: dif != null
                            ? (dif < 0 ? PdfColors.red800 : (dif > 0 ? PdfColors.blue800 : PdfColors.green800))
                            : PdfColors.grey700,
                      ),
                    ],
                  );
                }),
              ],
            ),
          pw.SizedBox(height: 14),

          // 4. Recepción de Proveedores
          pw.Text(
            '2. INGRESOS DE MERCADERÍA / PROVEEDORES REGISTRADOS (${compras.length})',
            style: pw.TextStyle(fontSize: 10, fontWeight: pw.FontWeight.bold, color: PdfColors.blueGrey900),
          ),
          pw.SizedBox(height: 4),
          if (compras.isEmpty)
            pw.Text('No se recepcionó mercadería externa durante este turno.', style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey600))
          else
            pw.Column(
              children: compras.map((c) {
                final prov = c['proveedor'] ?? 'Proveedor General';
                final nota = c['numero_nota'] ?? 'S/N';
                final hora = c['fecha'] ?? '';
                final items = (c['items'] as List?) ?? [];
                final itemsStr = items.map((i) => '${i['cantidad']}x ${i['producto_nombre']}').join(', ');

                return pw.Container(
                  margin: const pw.EdgeInsets.only(bottom: 4),
                  padding: const pw.EdgeInsets.all(6),
                  decoration: pw.BoxDecoration(color: PdfColors.grey50, border: pw.Border.all(color: PdfColors.grey200)),
                  child: pw.Row(
                    mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
                    children: [
                      pw.Expanded(
                        child: pw.Text(
                          '• $prov [Nota: $nota - $hora]: $itemsStr',
                          style: const pw.TextStyle(fontSize: 8.5),
                        ),
                      ),
                    ],
                  ),
                );
              }).toList(),
            ),
          pw.SizedBox(height: 10),

          // 5. Traspasos y Bajas
          pw.Row(
            crossAxisAlignment: pw.CrossAxisAlignment.start,
            children: [
              // Traspasos
              pw.Expanded(
                child: pw.Column(
                  crossAxisAlignment: pw.CrossAxisAlignment.start,
                  children: [
                    pw.Text('3. TRASPASOS INTER-SUCURSALES', style: pw.TextStyle(fontSize: 9, fontWeight: pw.FontWeight.bold)),
                    pw.SizedBox(height: 3),
                    if (traspasosSal.isEmpty && traspasosEnt.isEmpty)
                      pw.Text('Sin traspasos en el turno.', style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey600))
                    else ...[
                      ...traspasosSal.map((t) {
                        final items = ((t['items'] as List?) ?? []).map((i) => '${i['cantidad_enviada']}x ${i['producto_nombre']}').join(', ');
                        return pw.Text('• ENVIADO a ${t['destino']}: $items', style: const pw.TextStyle(fontSize: 8, color: PdfColors.red800));
                      }),
                      ...traspasosEnt.map((t) {
                        final items = ((t['items'] as List?) ?? []).map((i) => '${i['cantidad_recibida']}x ${i['producto_nombre']}').join(', ');
                        return pw.Text('• RECIBIDO de ${t['origen']}: $items', style: const pw.TextStyle(fontSize: 8, color: PdfColors.teal800));
                      }),
                    ],
                  ],
                ),
              ),
              pw.SizedBox(width: 16),
              // Bajas
              pw.Expanded(
                child: pw.Column(
                  crossAxisAlignment: pw.CrossAxisAlignment.start,
                  children: [
                    pw.Text('4. BAJAS Y ROTURAS DECLARADAS', style: pw.TextStyle(fontSize: 9, fontWeight: pw.FontWeight.bold)),
                    pw.SizedBox(height: 3),
                    if (bajas.isEmpty)
                      pw.Text('Sin roturas ni mermas reportadas.', style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey600))
                    else
                      ...bajas.map((b) {
                        return pw.Text('• ${b['cantidad']}x ${b['producto_nombre']} (${b['observaciones'] ?? "Merma"})', style: const pw.TextStyle(fontSize: 8, color: PdfColors.red900));
                      }),
                  ],
                ),
              ),
            ],
          ),
          pw.SizedBox(height: 14),

          // 6. Liquidación Económica y Comisiones
          pw.Container(
            padding: const pw.EdgeInsets.all(10),
            decoration: pw.BoxDecoration(
              color: PdfColors.teal50,
              borderRadius: pw.BorderRadius.circular(6),
              border: pw.Border.all(color: PdfColors.teal200),
            ),
            child: pw.Row(
              mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
              children: [
                pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.start, children: [
                  pw.Text('Botellas Rellenadas:', style: const pw.TextStyle(fontSize: 8, color: PdfColors.teal900)),
                  pw.Text('${data['transformaciones']?['total_terminadas'] ?? 0} unidades', style: pw.TextStyle(fontSize: 11, fontWeight: pw.FontWeight.bold, color: PdfColors.teal900)),
                ]),
                pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.start, children: [
                  pw.Text('Comisión Relleno:', style: const pw.TextStyle(fontSize: 8, color: PdfColors.teal900)),
                  pw.Text('${(data['transformaciones']?['comision_total'] ?? 0)} Bs', style: pw.TextStyle(fontSize: 11, fontWeight: pw.FontWeight.bold, color: PdfColors.teal900)),
                ]),
                pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.start, children: [
                  pw.Text('Modalidad de Sueldo:', style: const pw.TextStyle(fontSize: 8, color: PdfColors.teal900)),
                  pw.Text(
                    liquidacion['es_turno_dia'] == true ? 'Jornal Diario' : 'Sueldo Semanal Noche',
                    style: pw.TextStyle(fontSize: 10, fontWeight: pw.FontWeight.bold, color: PdfColors.teal900),
                  ),
                ]),
                pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.start, children: [
                  pw.Text('Recibo de Cobro:', style: const pw.TextStyle(fontSize: 8, color: PdfColors.teal900)),
                  pw.Text(
                    (liquidacion['codigo_recibo_cobro'] ?? 'PENDIENTE').toString(),
                    style: pw.TextStyle(fontSize: 10, fontWeight: pw.FontWeight.bold, color: PdfColors.blue900),
                  ),
                ]),
              ],
            ),
          ),
          pw.SizedBox(height: 24),

          // 7. Firmas de Custodia y Auditoría
          pw.Row(
            mainAxisAlignment: pw.MainAxisAlignment.spaceAround,
            children: [
              pw.Column(
                children: [
                  pw.Container(width: 160, height: 1, color: PdfColors.grey700),
                  pw.SizedBox(height: 4),
                  pw.Text('FIRMA BARMAN EN TURNO', style: pw.TextStyle(fontSize: 8, fontWeight: pw.FontWeight.bold)),
                  pw.Text(barmanNombre, style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey700)),
                  pw.Text('Custodio Directo de Stock', style: const pw.TextStyle(fontSize: 7, color: PdfColors.grey500)),
                ],
              ),
              pw.Column(
                children: [
                  pw.Container(width: 160, height: 1, color: PdfColors.grey700),
                  pw.SizedBox(height: 4),
                  pw.Text('FIRMA AUDITOR / ADMINISTRADOR', style: pw.TextStyle(fontSize: 8, fontWeight: pw.FontWeight.bold)),
                  pw.Text('Fiscalización Grupo Punto Frío', style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey700)),
                  pw.Text('Validación Conforme de Sistema', style: const pw.TextStyle(fontSize: 7, color: PdfColors.grey500)),
                ],
              ),
            ],
          ),
        ],
      ),
    );

    return pdf.save();
  }

  static pw.Widget _cellHeader(String text) {
    return pw.Container(
      padding: const pw.EdgeInsets.symmetric(vertical: 4, horizontal: 2),
      alignment: pw.Alignment.center,
      child: pw.Text(
        text,
        style: pw.TextStyle(fontSize: 7.5, fontWeight: pw.FontWeight.bold, color: PdfColors.blueGrey900),
      ),
    );
  }

  static pw.Widget _cellText(
    String text, {
    bool bold = false,
    PdfColor? color,
    pw.TextAlign align = pw.TextAlign.center,
  }) {
    return pw.Container(
      padding: const pw.EdgeInsets.symmetric(vertical: 3.5, horizontal: 2),
      alignment: align == pw.TextAlign.left ? pw.Alignment.centerLeft : pw.Alignment.center,
      child: pw.Text(
        text,
        style: pw.TextStyle(
          fontSize: 7.5,
          fontWeight: bold ? pw.FontWeight.bold : pw.FontWeight.normal,
          color: color ?? PdfColors.black,
        ),
      ),
    );
  }

  static Future<void> previsualizarEImprimir({
    required BuildContext context,
    required Map<String, dynamic> data,
  }) async {
    final bytes = await generarPdfBytes(data);
    final sucursal = data['sucursal']?['nombre'] ?? 'Sucursal';
    final turnoId = data['turno']?['id'] ?? 'Turno';

    await Printing.layoutPdf(
      onLayout: (format) async => bytes,
      name: 'Informe_Operativo_${sucursal}_Turno_$turnoId.pdf',
    );
  }

  static Future<void> compartirPdfWhatsApp({
    required BuildContext context,
    required Map<String, dynamic> data,
  }) async {
    final bytes = await generarPdfBytes(data);
    final sucursal = data['sucursal']?['nombre'] ?? 'Sucursal';
    final turnoId = data['turno']?['id'] ?? 'Turno';
    final filename = 'Informe_Operativo_${sucursal}_Turno_$turnoId.pdf';

    await Printing.sharePdf(
      bytes: bytes,
      filename: filename,
    );
  }
}
