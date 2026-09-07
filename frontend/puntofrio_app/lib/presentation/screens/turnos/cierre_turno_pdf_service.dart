import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

class CierreTurnoPdfService {
  static Future<Uint8List> generarPdfBytes({
    required int turnoId,
    required String sucursal,
    required String barman,
    required String tipoTurno,
    required List<Map<String, dynamic>> items,
  }) async {
    final pdf = pw.Document();
    final ahora = DateTime.now();
    final fechaStr =
        '${ahora.day.toString().padLeft(2, '0')}/${ahora.month.toString().padLeft(2, '0')}/${ahora.year} ${ahora.hour.toString().padLeft(2, '0')}:${ahora.minute.toString().padLeft(2, '0')}';

    final totalItems = items.length;
    final totalInicial = items.fold<double>(0.0, (acc, item) => acc + ((item['cantidad_inicial'] as num?)?.toDouble() ?? 0.0));
    final totalIngresos = items.fold<double>(0.0, (acc, item) => acc + ((item['ingresos'] as num?)?.toDouble() ?? 0.0));
    final totalRellenos = items.fold<double>(0.0, (acc, item) => acc + ((item['rellenos'] as num?)?.toDouble() ?? 0.0));
    final totalBajas = items.fold<double>(0.0, (acc, item) => acc + ((item['bajas'] as num?)?.toDouble() ?? 0.0));
    final totalUnidades = items.fold<double>(0.0, (acc, item) => acc + ((item['cantidad'] as num?)?.toDouble() ?? 0.0));

    pdf.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.letter,
        margin: const pw.EdgeInsets.all(32),
        build: (pw.Context ctx) => [
          // 1. Membrete Oficial
          pw.Container(
            padding: const pw.EdgeInsets.only(bottom: 12),
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
                      style: pw.TextStyle(fontSize: 22, fontWeight: pw.FontWeight.bold, color: PdfColors.blue900),
                    ),
                    pw.SizedBox(height: 4),
                    pw.Text(
                      'ACTA OFICIAL DE CONTEO FINAL Y CIERRE DE TURNO',
                      style: pw.TextStyle(fontSize: 10, fontWeight: pw.FontWeight.bold, color: PdfColors.red900),
                    ),
                    pw.SizedBox(height: 2),
                    pw.Text(
                      'BALANCE INMUTABLE DE ENTREGA DE BARRA',
                      style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey700),
                    ),
                  ],
                ),
                pw.Column(
                  crossAxisAlignment: pw.CrossAxisAlignment.end,
                  children: [
                    pw.Container(
                      padding: const pw.EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: pw.BoxDecoration(
                        color: PdfColors.red50,
                        border: pw.Border.all(color: PdfColors.red800),
                        borderRadius: const pw.BorderRadius.all(pw.Radius.circular(6)),
                      ),
                      child: pw.Text('TURNO #$turnoId (CERRADO)',
                          style: pw.TextStyle(fontSize: 12, fontWeight: pw.FontWeight.bold, color: PdfColors.red900)),
                    ),
                    pw.SizedBox(height: 4),
                    pw.Text('Cierre: $fechaStr', style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey600)),
                  ],
                ),
              ],
            ),
          ),
          pw.SizedBox(height: 16),

          // 2. Ficha de Información del Turno
          pw.Container(
            padding: const pw.EdgeInsets.all(12),
            decoration: pw.BoxDecoration(
              color: PdfColors.grey100,
              borderRadius: const pw.BorderRadius.all(pw.Radius.circular(8)),
              border: pw.Border.all(color: PdfColors.grey300),
            ),
            child: pw.Row(
              mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
              children: [
                pw.Column(
                  crossAxisAlignment: pw.CrossAxisAlignment.start,
                  children: [
                    pw.RichText(
                      text: pw.TextSpan(
                        children: [
                          pw.TextSpan(text: 'Sucursal: ', style: pw.TextStyle(fontWeight: pw.FontWeight.bold, fontSize: 11)),
                          pw.TextSpan(text: sucursal, style: const pw.TextStyle(fontSize: 11)),
                        ],
                      ),
                    ),
                    pw.SizedBox(height: 4),
                    pw.RichText(
                      text: pw.TextSpan(
                        children: [
                          pw.TextSpan(text: 'Barman Saliente: ', style: pw.TextStyle(fontWeight: pw.FontWeight.bold, fontSize: 11)),
                          pw.TextSpan(text: barman, style: const pw.TextStyle(fontSize: 11)),
                        ],
                      ),
                    ),
                  ],
                ),
                pw.Column(
                  crossAxisAlignment: pw.CrossAxisAlignment.end,
                  children: [
                    pw.RichText(
                      text: pw.TextSpan(
                        children: [
                          pw.TextSpan(text: 'Horario: ', style: pw.TextStyle(fontWeight: pw.FontWeight.bold, fontSize: 11)),
                          pw.TextSpan(text: tipoTurno.toUpperCase(), style: const pw.TextStyle(fontSize: 11)),
                        ],
                      ),
                    ),
                    pw.SizedBox(height: 4),
                    pw.RichText(
                      text: pw.TextSpan(
                        children: [
                          pw.TextSpan(text: 'Total Físico Cierre: ', style: pw.TextStyle(fontWeight: pw.FontWeight.bold, fontSize: 11)),
                          pw.TextSpan(text: '${totalUnidades.toStringAsFixed(2)} botellas ($totalItems ítems)', style: const pw.TextStyle(fontSize: 11, color: PdfColors.blue800)),
                        ],
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          pw.SizedBox(height: 16),

          // 3. Tabla de Inventario de Cierre
          pw.Text('Detalle del Inventario Final Entregado:',
              style: pw.TextStyle(fontSize: 12, fontWeight: pw.FontWeight.bold, color: PdfColors.blueGrey900)),
          pw.SizedBox(height: 8),

          pw.TableHelper.fromTextArray(
            headers: ['#', 'PRODUCTO / INSUMO', 'INICIAL', 'INGRESOS (+)', 'RELLENOS (±)', 'BAJAS (-)', 'TOTAL CIERRE'],
            data: List<List<String>>.generate(items.length, (idx) {
              final it = items[idx];
              final String nombre = (it['nombre'] ?? it['producto_nombre'] ?? 'Producto #${it['producto_id']}').toString();
              final double ini = (it['cantidad_inicial'] as num?)?.toDouble() ?? 0.0;
              final double ing = (it['ingresos'] as num?)?.toDouble() ?? 0.0;
              final double rel = (it['rellenos'] as num?)?.toDouble() ?? 0.0;
              final double baj = (it['bajas'] as num?)?.toDouble() ?? 0.0;
              final double cant = (it['cantidad'] as num?)?.toDouble() ?? 0.0;

              return [
                (idx + 1).toString(),
                nombre,
                ini.toStringAsFixed(2),
                ing > 0 ? '+${ing.toStringAsFixed(2)}' : '-',
                rel != 0 ? (rel > 0 ? '+${rel.toStringAsFixed(2)}' : rel.toStringAsFixed(2)) : '-',
                baj > 0 ? '-${baj.toStringAsFixed(2)}' : '-',
                cant.toStringAsFixed(2),
              ];
            }),
            border: pw.TableBorder.all(color: PdfColors.grey300, width: 0.5),
            headerStyle: pw.TextStyle(color: PdfColors.white, fontWeight: pw.FontWeight.bold, fontSize: 8),
            headerDecoration: const pw.BoxDecoration(color: PdfColors.blueGrey800),
            cellStyle: const pw.TextStyle(fontSize: 8),
            cellAlignment: pw.Alignment.centerLeft,
            cellAlignments: {
              0: pw.Alignment.center,
              1: pw.Alignment.centerLeft,
              2: pw.Alignment.centerRight,
              3: pw.Alignment.centerRight,
              4: pw.Alignment.centerRight,
              5: pw.Alignment.centerRight,
              6: pw.Alignment.centerRight,
            },
            columnWidths: {
              0: const pw.FlexColumnWidth(0.8),
              1: const pw.FlexColumnWidth(3.8),
              2: const pw.FlexColumnWidth(1.6),
              3: const pw.FlexColumnWidth(1.6),
              4: const pw.FlexColumnWidth(1.6),
              5: const pw.FlexColumnWidth(1.6),
              6: const pw.FlexColumnWidth(1.8),
            },
            cellPadding: const pw.EdgeInsets.symmetric(horizontal: 5, vertical: 4),
          ),
          pw.SizedBox(height: 8),

          // Resumen de Totales
          pw.Container(
            padding: const pw.EdgeInsets.all(8),
            decoration: pw.BoxDecoration(
              color: PdfColors.grey100,
              borderRadius: const pw.BorderRadius.all(pw.Radius.circular(6)),
              border: pw.Border.all(color: PdfColors.grey300),
            ),
            child: pw.Row(
              mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
              children: [
                pw.Text('Total Artículos: $totalItems', style: pw.TextStyle(fontSize: 9, fontWeight: pw.FontWeight.bold)),
                pw.Text(
                  'Inicial: ${totalInicial.toStringAsFixed(2)} | Ingresos: +${totalIngresos.toStringAsFixed(2)} | Rellenos: ${totalRellenos >= 0 ? '+' : ''}${totalRellenos.toStringAsFixed(2)} | Bajas: -${totalBajas.toStringAsFixed(2)} | Total Cierre: ${totalUnidades.toStringAsFixed(2)} u.',
                  style: pw.TextStyle(fontSize: 8.5, fontWeight: pw.FontWeight.bold, color: PdfColors.blueGrey900),
                ),
              ],
            ),
          ),

          pw.SizedBox(height: 24),

          // 4. Cláusula de Validez e Inmutabilidad
          pw.Container(
            padding: const pw.EdgeInsets.all(8),
            decoration: pw.BoxDecoration(
              border: pw.Border.all(color: PdfColors.grey400, width: 0.5),
              borderRadius: const pw.BorderRadius.all(pw.Radius.circular(4)),
            ),
            child: pw.Text(
              'DECLARACIÓN: El presente conteo final certifica la entrega del inventario físico remanente en barra al cierre de turno. Cualquier faltante detectado en el siguiente turno será imputable al personal saliente según el reglamento interno de Grupo Punto Frío.',
              style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey700),
              textAlign: pw.TextAlign.justify,
            ),
          ),

          pw.SizedBox(height: 48),

          // 5. Firmas
          pw.Row(
            mainAxisAlignment: pw.MainAxisAlignment.spaceAround,
            children: [
              pw.Column(
                children: [
                  pw.Container(width: 170, height: 1, color: PdfColors.black),
                  pw.SizedBox(height: 4),
                  pw.Text('Firma Barman Saliente', style: pw.TextStyle(fontSize: 9, fontWeight: pw.FontWeight.bold)),
                  pw.Text(barman, style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey700)),
                ],
              ),
              pw.Column(
                children: [
                  pw.Container(width: 170, height: 1, color: PdfColors.black),
                  pw.SizedBox(height: 4),
                  pw.Text('Firma Supervisor / Auditoría', style: pw.TextStyle(fontSize: 9, fontWeight: pw.FontWeight.bold)),
                  pw.Text('Grupo Punto Frío', style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey700)),
                ],
              ),
            ],
          ),
        ],
      ),
    );

    return pdf.save();
  }

  static Future<void> compartirEnWhatsApp({
    required BuildContext context,
    required int turnoId,
    required String sucursal,
    required String barman,
    required String tipoTurno,
    required List<Map<String, dynamic>> items,
  }) async {
    final pdfBytes = await generarPdfBytes(
      turnoId: turnoId,
      sucursal: sucursal,
      barman: barman,
      tipoTurno: tipoTurno,
      items: items,
    );

    final nombreArchivo = 'Acta_Cierre_Turno_${turnoId}_${sucursal.replaceAll(' ', '_')}.pdf';
    await Printing.sharePdf(
      bytes: pdfBytes,
      filename: nombreArchivo,
      subject: 'Acta Oficial de Cierre y Balance - Turno #$turnoId ($sucursal)',
    );
  }

  static Future<void> previsualizarOImprimir({
    required BuildContext context,
    required int turnoId,
    required String sucursal,
    required String barman,
    required String tipoTurno,
    required List<Map<String, dynamic>> items,
  }) async {
    final pdfBytes = await generarPdfBytes(
      turnoId: turnoId,
      sucursal: sucursal,
      barman: barman,
      tipoTurno: tipoTurno,
      items: items,
    );

    await Printing.layoutPdf(
      onLayout: (PdfPageFormat format) async => pdfBytes,
      name: 'Acta_Cierre_Turno_$turnoId',
    );
  }
}
