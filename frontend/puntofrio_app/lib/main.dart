import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'core/sync/sync_coordinator.dart';
import 'presentation/providers/auth_provider.dart';
import 'presentation/screens/auth/login_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final prefs = await SharedPreferences.getInstance();

  runApp(
    ProviderScope(
      overrides: [
        sharedPreferencesProvider.overrideWithValue(prefs),
      ],
      child: const PuntoFrioApp(),
    ),
  );
}

class PuntoFrioApp extends ConsumerWidget {
  const PuntoFrioApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    // Inicializar coordinador de sincronización offline
    final client = ref.watch(apiClientProvider);
    SyncCoordinator(client);

    return MaterialApp(
      title: 'Punto Frío - Control de Inventario',
      debugShowCheckedModeBanner: false,
      theme: ThemeData.dark().copyWith(
        scaffoldBackgroundColor: const Color(0xFF0D1117),
        primaryColor: Colors.amberAccent,
        colorScheme: const ColorScheme.dark(
          primary: Colors.amberAccent,
          secondary: Colors.cyanAccent,
        ),
      ),
      home: const LoginScreen(),
    );
  }
}
