import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

class ConteoPdfService {
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
    final totalInicial = items.fold<double>(0.0, (acc, item) => acc + ((item['cantidad_inicial'] ?? item['cantidad'] as num?)?.toDouble() ?? 0.0));
    final totalIngresos = items.fold<double>(0.0, (acc, item) => acc + ((item['ingresos'] as num?)?.toDouble() ?? 0.0));
    final totalDisponible = items.fold<double>(0.0, (acc, item) => acc + ((item['total_disponible'] as num?)?.toDouble() ?? ((item['cantidad'] as num?)?.toDouble() ?? 0.0)));

    pdf.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.letter,
        margin: const pw.EdgeInsets.all(32),
        build: (pw.Context ctx) => [
          // 1. Membrete
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
                      'ACTA OFICIAL DE CONTEO FÍSICO Y BALANCE EN TURNO',
                      style: pw.TextStyle(fontSize: 10, fontWeight: pw.FontWeight.bold, color: PdfColors.grey700),
                    ),
                  ],
                ),
                pw.Column(
                  crossAxisAlignment: pw.CrossAxisAlignment.end,
                  children: [
                    pw.Text('TURNO #$turnoId',
                        style: pw.TextStyle(fontSize: 14, fontWeight: pw.FontWeight.bold, color: PdfColors.blue800)),
                    pw.SizedBox(height: 2),
                    pw.Text('Fecha: $fechaStr', style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey600)),
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
              borderRadius: pw.BorderRadius.circular(6),
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
                          pw.TextSpan(text: 'Barman Receptor: ', style: pw.TextStyle(fontWeight: pw.FontWeight.bold, fontSize: 11)),
                          pw.TextSpan(text: barman, style: const pw.TextStyle(fontSize: 11)),
                        ],
                      ),
                    ),
                  ],
                ),
                pw.Column(
                  crossAxisAlignment: pw.CrossAxisAlignment.start,
                  children: [
                    pw.RichText(
                      text: pw.TextSpan(
                        children: [
                          pw.TextSpan(text: 'Jornada: ', style: pw.TextStyle(fontWeight: pw.FontWeight.bold, fontSize: 11)),
                          pw.TextSpan(
                              text: tipoTurno.toUpperCase() == 'DIA' ? 'Día (12h - Diario)' : 'Noche (12h - Semanal)',
                              style: const pw.TextStyle(fontSize: 11)),
                        ],
                      ),
                    ),
                    pw.SizedBox(height: 4),
                    pw.RichText(
                      text: pw.TextSpan(
                        children: [
                          pw.TextSpan(text: 'Estado: ', style: pw.TextStyle(fontWeight: pw.FontWeight.bold, fontSize: 11)),
                          pw.TextSpan(text: 'ABIERTO / RECIBIDO CONFORME', style: pw.TextStyle(fontSize: 11, color: PdfColors.green800)),
                        ],
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          pw.SizedBox(height: 16),

          // 3. Tabla de Productos del Conteo Físico
          pw.Text('DETALLE DE STOCK FÍSICO Y MOVIMIENTOS EN BARRA',
              style: pw.TextStyle(fontSize: 12, fontWeight: pw.FontWeight.bold, color: PdfColors.blueGrey800)),
          pw.SizedBox(height: 8),

          pw.Table(
            border: pw.TableBorder.all(color: PdfColors.grey300, width: 0.5),
            columnWidths: {
              0: const pw.FlexColumnWidth(0.8),
              1: const pw.FlexColumnWidth(4.2),
              2: const pw.FlexColumnWidth(1.8),
              3: const pw.FlexColumnWidth(2.0),
              4: const pw.FlexColumnWidth(2.2),
            },
            children: [
              pw.TableRow(
                decoration: const pw.BoxDecoration(color: PdfColors.blueGrey700),
                children: [
                  _th('#'),
                  _th('PRODUCTO / INSUMO'),
                  _th('APERTURA'),
                  _th('INGRESOS (+)'),
                  _th('TOTAL DISP.'),
                ],
              ),
              ...items.asMap().entries.map((entry) {
                final idx = entry.key + 1;
                final item = entry.value;
                final nombre = item['nombre']?.toString() ?? item['producto_nombre']?.toString() ?? 'Producto';
                final cantInicial = (item['cantidad_inicial'] as num?)?.toDouble() ?? (item['cantidad'] as num?)?.toDouble() ?? 0.0;
                final ingresos = (item['ingresos'] as num?)?.toDouble() ?? 0.0;
                final totalDisp = (item['total_disponible'] as num?)?.toDouble() ?? (cantInicial + ingresos);

                return pw.TableRow(
                  decoration: pw.BoxDecoration(color: idx % 2 == 0 ? PdfColors.grey50 : PdfColors.white),
                  children: [
                    _td(idx.toString(), align: pw.TextAlign.center),
                    _td(nombre),
                    _td(cantInicial.toStringAsFixed(2), align: pw.TextAlign.right),
                    _td(ingresos > 0 ? '+${ingresos.toStringAsFixed(2)}' : '-', align: pw.TextAlign.right, color: ingresos > 0 ? PdfColors.green800 : PdfColors.black),
                    _td(totalDisp.toStringAsFixed(2), align: pw.TextAlign.right, isBold: true),
                  ],
                );
              }),
            ],
          ),
          pw.SizedBox(height: 12),

          // Resumen de Totales
          pw.Container(
            padding: const pw.EdgeInsets.all(10),
            decoration: pw.BoxDecoration(
              color: PdfColors.blueGrey50,
              borderRadius: pw.BorderRadius.circular(6),
              border: pw.Border.all(color: PdfColors.blueGrey200),
            ),
            child: pw.Row(
              mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
              children: [
                pw.Text('Total de Artículos: $totalItems',
                    style: pw.TextStyle(fontWeight: pw.FontWeight.bold, fontSize: 10)),
                pw.Text('Apertura: ${totalInicial.toStringAsFixed(2)} | Ingresos: +${totalIngresos.toStringAsFixed(2)} | Disp: ${totalDisponible.toStringAsFixed(2)} u.',
                    style: pw.TextStyle(fontWeight: pw.FontWeight.bold, fontSize: 10, color: PdfColors.blue900)),
              ],
            ),
          ),
          pw.SizedBox(height: 30),

          // 4. Firmas de Responsabilidad
          pw.Row(
            mainAxisAlignment: pw.MainAxisAlignment.spaceAround,
            children: [
              pw.Column(
                children: [
                  pw.Container(width: 180, height: 1, color: PdfColors.black),
                  pw.SizedBox(height: 6),
                  pw.Text('Firma Barman Entrante', style: pw.TextStyle(fontSize: 10, fontWeight: pw.FontWeight.bold)),
                  pw.Text(barman, style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey600)),
                  pw.Text('Custodio de Inventario', style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey500)),
                ],
              ),
              pw.Column(
                children: [
                  pw.Container(width: 180, height: 1, color: PdfColors.black),
                  pw.SizedBox(height: 6),
                  pw.Text('Firma Supervisor / Administrador', style: pw.TextStyle(fontSize: 10, fontWeight: pw.FontWeight.bold)),
                  pw.Text('Control de Operaciones', style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey600)),
                  pw.Text('Grupo Punto Frío', style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey500)),
                ],
              ),
            ],
          ),
          pw.SizedBox(height: 20),
          pw.Center(
            child: pw.Text(
              'Este documento certifica el estado físico inicial de la barra. Cualquier faltante posterior será atribuido al turno.',
              style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey500),
            ),
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

    final fecha = DateTime.now();
    final fechaSimple = '${fecha.day.toString().padLeft(2, '0')}-${fecha.month.toString().padLeft(2, '0')}';
    final nombreArchivo = 'Conteo_Inicial_Turno_${turnoId}_$fechaSimple.pdf';

    await Printing.sharePdf(
      bytes: pdfBytes,
      filename: nombreArchivo,
      subject: 'Conteo Inicial de Barra - Turno #$turnoId ($sucursal)',
      body: 'Adjunto Acta Oficial de Conteo Físico Inicial de Apertura para el Turno #$turnoId a cargo de $barman en $sucursal.',
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
      name: 'Conteo_Inicial_Turno_$turnoId.pdf',
    );
  }

  static pw.Widget _th(String texto) {
    return pw.Container(
      padding: const pw.EdgeInsets.symmetric(horizontal: 6, vertical: 5),
      child: pw.Text(
        texto,
        style: pw.TextStyle(fontSize: 9, fontWeight: pw.FontWeight.bold, color: PdfColors.white),
        textAlign: pw.TextAlign.center,
      ),
    );
  }

  static pw.Widget _td(String texto, {pw.TextAlign align = pw.TextAlign.left, bool isBold = false, PdfColor color = PdfColors.black}) {
    return pw.Container(
      padding: const pw.EdgeInsets.symmetric(horizontal: 6, vertical: 4),
      child: pw.Text(
        texto,
        style: pw.TextStyle(fontSize: 9, fontWeight: isBold ? pw.FontWeight.bold : pw.FontWeight.normal, color: color),
        textAlign: align,
      ),
    );
  }
}
