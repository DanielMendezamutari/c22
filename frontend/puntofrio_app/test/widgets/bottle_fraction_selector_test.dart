import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:puntofrio_app/presentation/widgets/bottle_fraction_selector.dart';

void main() {
  group('BottleFractionSelector Widget Tests', () {
    testWidgets('Muestra correctamente unidades enteras, etiqueta y fraccion', (WidgetTester tester) async {
      double valorActual = 2.50;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: BottleFractionSelector(
              value: valorActual,
              onChanged: (nuevo) => valorActual = nuevo,
              productName: 'Fernet Branca 750ml',
            ),
          ),
        ),
      );

      // Validar visualización del producto y conteo
      expect(find.text('Fernet Branca 750ml'), findsOneWidget);
      expect(find.text('2.50 botellas'), findsOneWidget);
      expect(find.text('2'), findsOneWidget);
      expect(find.text('1/2'), findsWidgets); // En botón y en gráfico
    });

    testWidgets('Seleccionar botón de fracción emite valores decimales exactos', (WidgetTester tester) async {
      double valorEmitido = 0.0;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: StatefulBuilder(
              builder: (context, setState) {
                return BottleFractionSelector(
                  value: valorEmitido,
                  onChanged: (nuevo) {
                    setState(() {
                      valorEmitido = nuevo;
                    });
                  },
                  productName: 'Vodka Absolut 1L',
                );
              },
            ),
          ),
        ),
      );

      // 1. Tocar botón '1/4' -> Debe emitir 0.25
      await tester.tap(find.text('1/4').first);
      await tester.pumpAndSettle();
      expect(valorEmitido, equals(0.25));

      // 2. Tocar botón '1/2' -> Debe emitir 0.50
      await tester.tap(find.text('1/2').first);
      await tester.pumpAndSettle();
      expect(valorEmitido, equals(0.50));

      // 3. Tocar botón '3/4' -> Debe emitir 0.75
      await tester.tap(find.text('3/4').first);
      await tester.pumpAndSettle();
      expect(valorEmitido, equals(0.75));

      // 4. Tocar botón '0 (Cero)' -> Debe emitir 0.00
      await tester.tap(find.text('0 (Cero)'));
      await tester.pumpAndSettle();
      expect(valorEmitido, equals(0.00));
    });

    testWidgets('Botones + y - incrementan y decrementan enteros preservando fraccion', (WidgetTester tester) async {
      double valor = 3.25;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: StatefulBuilder(
              builder: (context, setState) {
                return BottleFractionSelector(
                  value: valor,
                  onChanged: (nuevo) {
                    setState(() {
                      valor = nuevo;
                    });
                  },
                  productName: 'Whisky Chivas 750ml',
                );
              },
            ),
          ),
        ),
      );

      // Tocar botón sumar '+' (Icons.add)
      await tester.tap(find.byIcon(Icons.add));
      await tester.pumpAndSettle();
      expect(valor, equals(4.25));

      // Tocar botón restar '-' (Icons.remove)
      await tester.tap(find.byIcon(Icons.remove));
      await tester.pumpAndSettle();
      expect(valor, equals(3.25));
    });

    testWidgets('Chips de atajo rápido por caja (+12) incrementan correctamente', (WidgetTester tester) async {
      double valor = 1.50;

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: StatefulBuilder(
              builder: (context, setState) {
                return BottleFractionSelector(
                  value: valor,
                  onChanged: (nuevo) {
                    setState(() {
                      valor = nuevo;
                    });
                  },
                  productName: 'Cerveza Huari 300ml',
                );
              },
            ),
          ),
        ),
      );

      // Tocar chip '+12 (Caja)'
      await tester.tap(find.text('+12 (Caja)'));
      await tester.pumpAndSettle();
      expect(valor, equals(13.50));
    });
  });
}
