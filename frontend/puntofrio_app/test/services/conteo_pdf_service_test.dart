import 'dart:typed_data';
import 'package:flutter_test/flutter_test.dart';
import 'package:puntofrio_app/presentation/screens/turnos/conteo_pdf_service.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('ConteoPdfService Unit Tests', () {
    test('generarPdfBytes genera documento PDF válido con cantidades no nulas', () async {
      // Caso crítico resuelto en Feature 001 / US23:
      // Cuando el barman ingresa 24 botellas, 'cantidad_inicial' puede venir en 0.0
      // pero 'cantidad' viene en 24.0. El servicio nunca debe calcular 0.00 en totales.
      final itemsPrueba = [
        {
          'producto_id': 1,
          'nombre': 'Cerveza Moema 300ml',
          'codigo': 'MOE-300',
          'tipo_producto': 'terminado',
          'cantidad_inicial': 0.0,
          'cantidad': 24.0,
          'ingresos': 0.0,
          'rellenos': 0.0,
          'bajas': 0.0,
          'traspasos_salida': 0.0,
          'total_disponible': 24.0,
        },
        {
          'producto_id': 2,
          'nombre': 'Fernet Branca 750ml',
          'codigo': 'FER-750',
          'tipo_producto': 'insumo',
          'cantidad_inicial': 2.5,
          'cantidad': 2.5,
          'ingresos': 1.0,
          'rellenos': 0.0,
          'bajas': 0.0,
          'traspasos_salida': 0.0,
          'total_disponible': 3.5,
        },
      ];

      final Uint8List pdfBytes = await ConteoPdfService.generarPdfBytes(
        turnoId: 1,
        sucursal: 'Sucursal Central Punto Frío',
        barman: 'Juan Barman',
        tipoTurno: 'noche',
        items: itemsPrueba,
      );

      // 1. Validar que los bytes no están vacíos
      expect(pdfBytes, isNotNull);
      expect(pdfBytes.isNotEmpty, isTrue);

      // 2. Validar cabecera mágica de PDF estándar (%PDF = 0x25, 0x50, 0x44, 0x46)
      expect(pdfBytes.length, greaterThan(100));
      final header = String.fromCharCodes(pdfBytes.sublist(0, 4));
      expect(header, equals('%PDF'));
    });

    test('generarPdfBytes procesa lista vacía de items sin arrojar excepciones', () async {
      final Uint8List pdfBytes = await ConteoPdfService.generarPdfBytes(
        turnoId: 99,
        sucursal: 'Sucursal Test',
        barman: 'Tester',
        tipoTurno: 'dia',
        items: [],
      );

      expect(pdfBytes.isNotEmpty, isTrue);
      final header = String.fromCharCodes(pdfBytes.sublist(0, 4));
      expect(header, equals('%PDF'));
    });
  });
}
