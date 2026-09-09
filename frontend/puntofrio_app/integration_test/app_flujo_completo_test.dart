import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:puntofrio_app/main.dart';
import 'package:puntofrio_app/presentation/providers/auth_provider.dart';
import 'package:puntofrio_app/presentation/screens/auth/login_screen.dart';
import 'package:puntofrio_app/presentation/widgets/numeric_pin_pad.dart';

void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  group('Punto Frío - Test de Integración End-to-End', () {
    testWidgets('Flujo de arranque de la aplicación y renderizado de pantalla de autenticación PIN', (tester) async {
      SharedPreferences.setMockInitialValues({});
      final prefs = await SharedPreferences.getInstance();

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            sharedPreferencesProvider.overrideWithValue(prefs),
          ],
          child: const PuntoFrioApp(),
        ),
      );

      await tester.pumpAndSettle();

      // 1. Validar que la pantalla inicial renderice LoginScreen con éxito
      expect(find.byType(LoginScreen), findsOneWidget);

      // 2. Validar que el teclado numérico de PIN esté listo e interactuable
      expect(find.byType(NumericPinPad), findsOneWidget);

      // 3. Simular pulsación de dígitos en el PIN pad (ej. '1', '2', '3', '4')
      final btn1 = find.text('1');
      if (btn1.evaluate().isNotEmpty) {
        await tester.tap(btn1.first);
        await tester.pump();
      }

      final btn2 = find.text('2');
      if (btn2.evaluate().isNotEmpty) {
        await tester.tap(btn2.first);
        await tester.pump();
      }

      final btn3 = find.text('3');
      if (btn3.evaluate().isNotEmpty) {
        await tester.tap(btn3.first);
        await tester.pump();
      }

      final btn4 = find.text('4');
      if (btn4.evaluate().isNotEmpty) {
        await tester.tap(btn4.first);
        await tester.pump();
      }

      await tester.pumpAndSettle();
    });
  });
}
