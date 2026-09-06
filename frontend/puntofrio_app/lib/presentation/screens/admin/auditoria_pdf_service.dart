import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';

class AuditoriaPdfService {
  static Future<void> generarEImprimirPdf({
    required BuildContext context,
    required Map<String, dynamic> datos,
  }) async {
    final pdf = pw.Document();

    final turnoId = datos['turno_id'] ?? datos['id'] ?? '-';
    final sucursal = datos['sucursal_nombre'] ?? datos['sucursal'] ?? 'Grupo Punto Frío';
    final barman = datos['barman_nombre'] ?? datos['barman'] ?? 'N/A';
    final auditor = datos['auditor_nombre'] ?? 'Administrador General';
    final fecha = DateTime.now();
    final fechaStr = '${fecha.day.toString().padLeft(2, '0')}/${fecha.month.toString().padLeft(2, '0')}/${fecha.year} ${fecha.hour.toString().padLeft(2, '0')}:${fecha.minute.toString().padLeft(2, '0')}';

    final detalles = datos['detalles'] is List ? (datos['detalles'] as List) : [datos];

    pdf.addPage(
      pw.MultiPage(
        pageFormat: PdfPageFormat.letter,
        margin: const pw.EdgeInsets.all(32),
        build: (pw.Context ctx) => [
          // 1. Encabezado Oficial
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
                      'INFORME OFICIAL DE AUDITORÍA Y CONCILIACIÓN DE INVENTARIO',
                      style: pw.TextStyle(fontSize: 10, fontWeight: pw.FontWeight.bold, color: PdfColors.grey700),
                    ),
                  ],
                ),
                pw.Column(
                  crossAxisAlignment: pw.CrossAxisAlignment.end,
                  children: [
                    pw.Text('TURNO #$turnoId', style: pw.TextStyle(fontSize: 14, fontWeight: pw.FontWeight.bold, color: PdfColors.red800)),
                    pw.SizedBox(height: 2),
                    pw.Text('Fecha: $fechaStr', style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey600)),
                  ],
                ),
              ],
            ),
          ),
          pw.SizedBox(height: 16),

          // 2. Ficha de Información General
          pw.Container(
            padding: const pw.EdgeInsets.all(10),
            decoration: pw.BoxDecoration(
              color: PdfColors.grey100,
              borderRadius: pw.BorderRadius.circular(6),
              border: pw.Border.all(color: PdfColors.grey300),
            ),
            child: pw.Row(
              mainAxisAlignment: pw.MainAxisAlignment.spaceBetween,
              children: [
                pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.start, children: [
                  pw.Text('Sucursal:', style: pw.TextStyle(fontSize: 9, fontWeight: pw.FontWeight.bold, color: PdfColors.grey700)),
                  pw.Text('$sucursal', style: pw.TextStyle(fontSize: 11, fontWeight: pw.FontWeight.bold)),
                ]),
                pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.start, children: [
                  pw.Text('Barman Responsable:', style: pw.TextStyle(fontSize: 9, fontWeight: pw.FontWeight.bold, color: PdfColors.grey700)),
                  pw.Text('$barman', style: pw.TextStyle(fontSize: 11, fontWeight: pw.FontWeight.bold)),
                ]),
                pw.Column(crossAxisAlignment: pw.CrossAxisAlignment.start, children: [
                  pw.Text('Auditor Fiscalizador:', style: pw.TextStyle(fontSize: 9, fontWeight: pw.FontWeight.bold, color: PdfColors.grey700)),
                  pw.Text('$auditor', style: pw.TextStyle(fontSize: 11, fontWeight: pw.FontWeight.bold)),
                ]),
              ],
            ),
          ),
          pw.SizedBox(height: 20),

          // 3. Tabla Comparativa de Inventario Físico vs Ticket Z
          pw.Text('BALANCE FÍSICO VS VENTAS DE TICKET Z', style: pw.TextStyle(fontSize: 11, fontWeight: pw.FontWeight.bold, color: PdfColors.blueGrey900)),
          pw.SizedBox(height: 8),

          pw.Table(
            border: pw.TableBorder.all(color: PdfColors.grey300, width: 0.5),
            columnWidths: {
              0: const pw.FlexColumnWidth(3.0), // Producto
              1: const pw.FlexColumnWidth(1.2), // Stock Inicial
              2: const pw.FlexColumnWidth(1.2), // Consumo Físico
              3: const pw.FlexColumnWidth(1.2), // Ventas Z
              4: const pw.FlexColumnWidth(1.2), // Diferencia
              5: const pw.FlexColumnWidth(1.8), // Estado
            },
            children: [
              pw.TableRow(
                decoration: const pw.BoxDecoration(color: PdfColors.blueGrey800),
                children: [
                  _headerCell('PRODUCTO'),
                  _headerCell('INICIAL'),
                  _headerCell('CONSUMO'),
                  _headerCell('VENTAS Z'),
                  _headerCell('DIF.'),
                  _headerCell('ESTADO'),
                ],
              ),
              ...detalles.map((det) {
                final prod = det['producto'] ?? det['producto_nombre'] ?? 'Corona';
                final inicial = (det['stock_inicial'] ?? 0.0).toString();
                final consumo = (det['consumo_fisico_calculado'] ?? det['consumo_fisico'] ?? 0.0).toString();
                final ventas = (det['ventas_ticket_z_desglosadas'] ?? det['ventas_ticket_z'] ?? 0.0).toString();
                final dif = (det['diferencia'] ?? 0.0).toDouble();
                final resultado = det['resultado'] ?? (dif == 0 ? 'cuadrado' : (dif < 0 ? 'faltante' : 'sobrante'));

                PdfColor statusColor = PdfColors.green700;
                String statusText = 'CUADRADO';
                if (resultado == 'faltante' || dif < 0) {
                  statusColor = PdfColors.red700;
                  statusText = 'FALTANTE (${dif.abs().toStringAsFixed(0)} u)';
                } else if (resultado == 'sobrante' || dif > 0) {
                  statusColor = PdfColors.blue700;
                  statusText = 'SOBRANTE (+${dif.toStringAsFixed(0)} u)';
                }

                return pw.TableRow(
                  children: [
                    pw.Padding(
                      padding: const pw.EdgeInsets.all(6),
                      child: pw.Text(prod, style: const pw.TextStyle(fontSize: 9)),
                    ),
                    _dataCell(inicial),
                    _dataCell(consumo),
                    _dataCell(ventas),
                    _dataCell(dif.toStringAsFixed(0)),
                    pw.Padding(
                      padding: const pw.EdgeInsets.all(6),
                      child: pw.Text(
                        statusText,
                        style: pw.TextStyle(fontSize: 8, fontWeight: pw.FontWeight.bold, color: statusColor),
                        textAlign: pw.TextAlign.center,
                      ),
                    ),
                  ],
                );
              }).toList(),
            ],
          ),
          pw.SizedBox(height: 20),

          // 4. Observaciones y Resumen de Sanción
          pw.Container(
            padding: const pw.EdgeInsets.all(12),
            decoration: pw.BoxDecoration(
              border: pw.Border.all(color: PdfColors.grey400),
              borderRadius: pw.BorderRadius.circular(6),
            ),
            child: pw.Column(
              crossAxisAlignment: pw.CrossAxisAlignment.start,
              children: [
                pw.Text('DICTAMEN Y OBSERVACIONES DE AUDITORÍA:', style: pw.TextStyle(fontSize: 9, fontWeight: pw.FontWeight.bold, color: PdfColors.grey700)),
                pw.SizedBox(height: 4),
                pw.Text(
                  datos['observaciones'] ?? 'Auditoría realizada sin observaciones adicionales. La conciliación de Ticket Z concuerda con las políticas de control del Grupo Punto Frío.',
                  style: const pw.TextStyle(fontSize: 9, color: PdfColors.grey800),
                ),
              ],
            ),
          ),
          pw.SizedBox(height: 40),

          // 5. Bloque de Firmas
          pw.Row(
            mainAxisAlignment: pw.MainAxisAlignment.spaceAround,
            children: [
              pw.Column(
                children: [
                  pw.Container(width: 180, height: 1, color: PdfColors.black),
                  pw.SizedBox(height: 4),
                  pw.Text('FIRMA DEL BARMAN RESPONSABLE', style: pw.TextStyle(fontSize: 8, fontWeight: pw.FontWeight.bold)),
                  pw.Text('$barman', style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey700)),
                ],
              ),
              pw.Column(
                children: [
                  pw.Container(width: 180, height: 1, color: PdfColors.black),
                  pw.SizedBox(height: 4),
                  pw.Text('FIRMA DEL AUDITOR FISCALIZADOR', style: pw.TextStyle(fontSize: 8, fontWeight: pw.FontWeight.bold)),
                  pw.Text('$auditor', style: const pw.TextStyle(fontSize: 8, color: PdfColors.grey700)),
                ],
              ),
            ],
          ),
          pw.SizedBox(height: 30),

          // Pie de Página
          pw.Center(
            child: pw.Text(
              'Documento generado por el Sistema de Inteligencia y Control de Inventario Grupo Punto Frío',
              style: const pw.TextStyle(fontSize: 7, color: PdfColors.grey500),
            ),
          ),
        ],
      ),
    );

    // Abrir visor de impresión y descarga nativo
    await Printing.layoutPdf(
      onLayout: (PdfPageFormat format) async => pdf.save(),
      name: 'Auditoria_Turno_${turnoId}_${sucursal.replaceAll(' ', '_')}.pdf',
    );
  }

  static pw.Widget _headerCell(String text) {
    return pw.Padding(
      padding: const pw.EdgeInsets.symmetric(vertical: 6, horizontal: 4),
      child: pw.Text(
        text,
        style: pw.TextStyle(color: PdfColors.white, fontSize: 8, fontWeight: pw.FontWeight.bold),
        textAlign: pw.TextAlign.center,
      ),
    );
  }

  static pw.Widget _dataCell(String text) {
    return pw.Padding(
      padding: const pw.EdgeInsets.all(6),
      child: pw.Text(text, style: const pw.TextStyle(fontSize: 9), textAlign: pw.TextAlign.center),
    );
  }
}
