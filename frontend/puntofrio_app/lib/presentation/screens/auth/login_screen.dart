import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/auth_provider.dart';
import '../../widgets/numeric_pin_pad.dart';
import '../admin/dashboard_admin_screen.dart';
import '../dashboard/dashboard_barman_screen.dart';
import '../settings/network_settings_dialog.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  int? _sucursalSeleccionadaId;
  List<dynamic> _sucursales = [];
  bool _isLoadingBootstrap = true;
  final GlobalKey<NumericPinPadState> _pinPadKey = GlobalKey<NumericPinPadState>();

  @override
  void initState() {
    super.initState();
    Future.microtask(_cargarBootstrap);
  }

  Future<void> _cargarBootstrap() async {
    setState(() {
      _isLoadingBootstrap = true;
    });

    try {
      final client = ref.read(apiClientProvider);
      final config = ref.read(apiConfigProvider);
      final res = await client.get('/auth/sucursales');

      if (res.statusCode == 200 && res.data['success'] == true) {
        final data = res.data['data'] as List<dynamic>;
        setState(() {
          _sucursales = data;

          // Restaurar sucursal recordada de la semana si existe
          final guardada = config.sucursalActivaId;
          if (guardada != null && _sucursales.any((s) => s['id'] == guardada)) {
            _sucursalSeleccionadaId = guardada;
          } else if (_sucursales.isNotEmpty) {
            _sucursalSeleccionadaId = _sucursales.first['id'] as int;
          }
          _isLoadingBootstrap = false;
        });
      }
    } catch (_) {
      final config = ref.read(apiConfigProvider);
      // Datos offline predeterminados de contingencia
      setState(() {
        _sucursales = [
          {'id': 1, 'nombre': 'Casa22', 'codigo': 'C22'},
          {'id': 2, 'nombre': 'Casa Coron', 'codigo': 'CCORON'},
          {'id': 3, 'nombre': 'Madan', 'codigo': 'MDN'},
        ];
        _sucursalSeleccionadaId = config.sucursalActivaId ?? 1;
        _isLoadingBootstrap = false;
      });
    }
  }

  Future<void> _onPinIngresado(String pin) async {
    final success = await ref.read(authProvider.notifier).loginConPin(
          sucursalId: _sucursalSeleccionadaId,
          pin: pin,
        );

    if (success && mounted) {
      final auth = ref.read(authProvider);
      if (auth.esAdmin) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => const DashboardAdminScreen()),
        );
      } else {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => const DashboardBarmanScreen()),
        );
      }
    } else {
      _pinPadKey.currentState?.limpiar();
    }
  }

  @override
  Widget build(BuildContext context) {
    final authState = ref.watch(authProvider);

    return Scaffold(
      backgroundColor: const Color(0xFF0D1117),
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.settings, color: Colors.white54),
            onPressed: () {
              showDialog(
                context: context,
                builder: (_) => const NetworkSettingsDialog(),
              );
            },
          ),
        ],
      ),
      body: SafeArea(
        child: _isLoadingBootstrap
            ? const Center(child: CircularProgressIndicator(color: Colors.amberAccent))
            : SingleChildScrollView(
                padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 8.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    // Logo C22 Oficial
                    ClipRRect(
                      borderRadius: BorderRadius.circular(16),
                      child: Image.asset(
                        'assets/images/logo_c22.png',
                        height: 90,
                        fit: BoxFit.contain,
                      ),
                    ),
                    const SizedBox(height: 10),
                    const Text(
                      'C22 INVENTARIO',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                        letterSpacing: 2,
                      ),
                    ),
                    const Text(
                      'Control de Inventario & Transformación',
                      style: TextStyle(color: Colors.white54, fontSize: 12),
                    ),
                    const SizedBox(height: 20),

                    // Selector de Sucursal
                    Align(
                      alignment: Alignment.centerLeft,
                      child: Text(
                        'SUCURSAL ACTIVA DE LA SEMANA',
                        style: TextStyle(
                          color: Colors.amberAccent.withOpacity(0.8),
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                          letterSpacing: 1.1,
                        ),
                      ),
                    ),
                    const SizedBox(height: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      decoration: BoxDecoration(
                        color: const Color(0xFF161F2E),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Colors.white12),
                      ),
                      child: DropdownButtonHideUnderline(
                        child: DropdownButton<int>(
                          value: _sucursalSeleccionadaId,
                          isExpanded: true,
                          dropdownColor: const Color(0xFF161F2E),
                          icon: const Icon(Icons.storefront, color: Colors.amberAccent),
                          style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                          items: _sucursales.map<DropdownMenuItem<int>>((s) {
                            return DropdownMenuItem<int>(
                              value: s['id'] as int,
                              child: Text('${s['nombre']} (${s['codigo']})'),
                            );
                          }).toList(),
                          onChanged: (id) {
                            setState(() {
                              _sucursalSeleccionadaId = id;
                            });
                            if (id != null) {
                              ref.read(apiConfigProvider).setSucursalActivaId(id);
                            }
                          },
                        ),
                      ),
                    ),
                    const SizedBox(height: 28),

                    // Teclado PIN ciego
                    const Text(
                      'INGRESA TU PIN DE 4 DÍGITOS',
                      style: TextStyle(color: Colors.white70, fontSize: 13, letterSpacing: 1.2, fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 6),
                    const Text(
                      'El sistema reconocerá automáticamente tu usuario y rol',
                      style: TextStyle(color: Colors.white38, fontSize: 11),
                    ),
                    const SizedBox(height: 16),
                    NumericPinPad(
                      key: _pinPadKey,
                      onPinCompleted: _onPinIngresado,
                    ),
                    const SizedBox(height: 16),

                    if (authState.isLoading)
                      const CircularProgressIndicator(color: Colors.amberAccent),

                    if (authState.errorMessage != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 8.0),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                          decoration: BoxDecoration(
                            color: Colors.redAccent.withValues(alpha: 0.15),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(color: Colors.redAccent.withValues(alpha: 0.3)),
                          ),
                          child: Text(
                            authState.errorMessage!,
                            style: const TextStyle(color: Colors.redAccent, fontSize: 14, fontWeight: FontWeight.bold),
                            textAlign: TextAlign.center,
                          ),
                        ),
                      ),

                    const SizedBox(height: 24),

                    // Créditos de Autoría / Desarrollador
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.04),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Colors.white12),
                      ),
                      child: Column(
                        children: [
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            children: const [
                              Icon(Icons.code, color: Colors.amberAccent, size: 14),
                              SizedBox(width: 6),
                              Text(
                                'Desarrollado por: ING. DANIEL MÉNDEZ',
                                style: TextStyle(
                                  color: Colors.white70,
                                  fontSize: 11,
                                  fontWeight: FontWeight.bold,
                                  letterSpacing: 0.5,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            children: const [
                              Icon(Icons.chat, color: Colors.greenAccent, size: 14),
                              SizedBox(width: 6),
                              Text(
                                'WhatsApp: 67369293',
                                style: TextStyle(
                                  color: Colors.white60,
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                  ],
                ),
              ),
      ),
    );
  }
}
