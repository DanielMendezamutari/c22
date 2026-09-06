import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../core/network/api_client.dart';
import '../../providers/auth_provider.dart';
import '../auth/login_screen.dart';
import 'alertas_merma_screen.dart';
import 'auditoria_ticket_z_screen.dart';
import 'catalogo_productos_screen.dart';
import 'liquidacion_semanal_screen.dart';
import 'motivos_baja_admin_screen.dart';
import 'recetas_screen.dart';
import 'sucursales_admin_screen.dart';
import 'usuarios_admin_screen.dart';
import '../inventario/registrar_compra_screen.dart';

class DashboardAdminScreen extends ConsumerStatefulWidget {
  const DashboardAdminScreen({super.key});

  @override
  ConsumerState<DashboardAdminScreen> createState() => _DashboardAdminScreenState();
}

class _DashboardAdminScreenState extends ConsumerState<DashboardAdminScreen> {
  bool _esCelularMaestro = false;
  bool _isLoadingDispositivos = false;
  List<Map<String, dynamic>> _discrepanciasPendientes = [];

  @override
  void initState() {
    super.initState();
    _cargarEstadoDispositivo();
    _cargarDiscrepancias();
  }

  Future<void> _cargarEstadoDispositivo() async {
    final prefs = ref.read(sharedPreferencesProvider);
    final vinculado = prefs.getBool('pref_celular_maestro_activo') ?? false;
    setState(() => _esCelularMaestro = vinculado);
  }

  Future<void> _cargarDiscrepancias() async {
    final client = ref.read(apiClientProvider);
    try {
      final res = await client.get('/alertas/discrepancias');
      if (res.data['success'] == true && res.data['data'] != null) {
        if (mounted) {
          setState(() {
            _discrepanciasPendientes = List<Map<String, dynamic>>.from(res.data['data']);
          });
        }
      }
    } catch (_) {}
  }

  Future<void> _toggleCelularMaestro(bool valor) async {
    final prefs = ref.read(sharedPreferencesProvider);
    final client = ref.read(apiClientProvider);

    setState(() => _isLoadingDispositivos = true);

    String? deviceId = prefs.getString('pref_device_unique_id');
    if (deviceId == null) {
      deviceId = 'device_${DateTime.now().millisecondsSinceEpoch}';
      await prefs.setString('pref_device_unique_id', deviceId);
    }

    try {
      if (valor) {
        await client.post('/dispositivos/registrar-maestro', data: {
          'device_id': deviceId,
          'nombre_dispositivo': 'Celular Maestro de Daniel',
        });
        await prefs.setBool('pref_celular_maestro_activo', true);
        setState(() => _esCelularMaestro = true);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('📱 Dispositivo vinculado como Celular Maestro. Recibirá alertas de discrepancias.'),
              backgroundColor: Color(0xFF27AE60),
            ),
          );
        }
      } else {
        await client.post('/dispositivos/desvincular', data: {
          'device_id': deviceId,
        });
        await prefs.setBool('pref_celular_maestro_activo', false);
        setState(() => _esCelularMaestro = false);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Dispositivo desvinculado. No recibirá notificaciones confidenciales.'),
              backgroundColor: Colors.orange,
            ),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error al cambiar dispositivo maestro: $e'), backgroundColor: Colors.redAccent),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoadingDispositivos = false);
    }
  }

  void _mostrarModalDiscrepancias() {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: const Color(0xFF1B1828),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => StatefulBuilder(
        builder: (context, setModalState) {
          return Padding(
            padding: const EdgeInsets.all(20.0),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(Icons.warning_amber_rounded, color: Color(0xFFE74C3C), size: 28),
                    const SizedBox(width: 8),
                    const Text(
                      'Discrepancias de Inventario Pendientes',
                      style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                    const Spacer(),
                    IconButton(
                      icon: const Icon(Icons.close, color: Colors.white54),
                      onPressed: () => Navigator.of(ctx).pop(),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                const Text(
                  'Diferencias registradas entre el corte de cierre saliente y la apertura entrante en sucursales:',
                  style: TextStyle(color: Colors.white60, fontSize: 12),
                ),
                const SizedBox(height: 16),
                if (_discrepanciasPendientes.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 24),
                    child: Center(
                      child: Text('No hay discrepancias pendientes.', style: TextStyle(color: Colors.white38)),
                    ),
                  )
                else
                  Flexible(
                    child: ListView.builder(
                      shrinkWrap: true,
                      itemCount: _discrepanciasPendientes.length,
                      itemBuilder: (context, idx) {
                        final disc = _discrepanciasPendientes[idx];
                        final prod = disc['producto']?['nombre'] ?? 'Producto #${disc['producto_id']}';
                        final sucursal = disc['sucursal']?['nombre'] ?? 'Sucursal #${disc['sucursal_id']}';
                        final esp = disc['stock_esperado'];
                        final dec = disc['stock_declarado'];
                        final dif = disc['diferencia'];

                        return Container(
                          margin: const EdgeInsets.only(bottom: 10),
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: const Color(0xFF2A1C29),
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: Colors.redAccent.withOpacity(0.4)),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(
                                    prod,
                                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                    decoration: BoxDecoration(
                                      color: Colors.redAccent.withOpacity(0.2),
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: Text(
                                      'Dif: $dif botellas',
                                      style: const TextStyle(color: Colors.redAccent, fontWeight: FontWeight.bold, fontSize: 12),
                                    ),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 4),
                              Text('📍 $sucursal', style: const TextStyle(color: Colors.amberAccent, fontSize: 12)),
                              const SizedBox(height: 2),
                              Text('Esperado saliente: $esp | Declarado entrante: $dec',
                                  style: const TextStyle(color: Colors.white60, fontSize: 11)),
                              const SizedBox(height: 8),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.end,
                                children: [
                                  TextButton.icon(
                                    style: TextButton.styleFrom(foregroundColor: const Color(0xFF25D366)),
                                    icon: const Icon(Icons.chat, size: 16),
                                    label: const Text('WhatsApp Daniel', style: TextStyle(fontSize: 12)),
                                    onPressed: () async {
                                      final url = Uri.parse(
                                        'https://wa.me/59167369293?text=Alerta%20de%20Discrepancia:%20$prod%20en%20$sucursal%20(Diferencia:%20$dif%20botellas)',
                                      );
                                      if (await canLaunchUrl(url)) {
                                        await launchUrl(url, mode: LaunchMode.externalApplication);
                                      }
                                    },
                                  ),
                                  const SizedBox(width: 8),
                                  ElevatedButton(
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: const Color(0xFF27AE60),
                                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                    ),
                                    onPressed: () async {
                                      final client = ref.read(apiClientProvider);
                                      final id = disc['id'];
                                      await client.post('/alertas/$id/resolver');
                                      await _cargarDiscrepancias();
                                      setModalState(() {});
                                      setState(() {});
                                    },
                                    child: const Text('Resolver', style: TextStyle(fontSize: 12, color: Colors.white)),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        );
                      },
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);

    return Scaffold(
      backgroundColor: const Color(0xFF0F141C),
      appBar: AppBar(
        backgroundColor: const Color(0xFF18202C),
        elevation: 0,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'PANEL DE AUDITORÍA Y CONTROL',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.cyanAccent),
            ),
            Text(
              'Administrador: ${auth.nombre ?? 'Auditor'}',
              style: const TextStyle(fontSize: 12, color: Colors.white60),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: Colors.white70),
            tooltip: 'Actualizar alertas',
            onPressed: _cargarDiscrepancias,
          ),
          IconButton(
            icon: const Icon(Icons.logout, color: Colors.white70),
            onPressed: () async {
              // Si no es el celular maestro personal de Daniel, revocar vínculo al salir
              if (!_esCelularMaestro) {
                final prefs = ref.read(sharedPreferencesProvider);
                final deviceId = prefs.getString('pref_device_unique_id');
                if (deviceId != null) {
                  final client = ref.read(apiClientProvider);
                  try {
                    await client.post('/dispositivos/desvincular', data: {'device_id': deviceId});
                  } catch (_) {}
                }
              }
              await ref.read(authProvider.notifier).logout();
              if (context.mounted) {
                Navigator.of(context).pushReplacement(
                  MaterialPageRoute(builder: (_) => const LoginScreen()),
                );
              }
            },
          ),
        ],
      ),
      body: SingleChildScrollView(
        child: Column(
          children: [
            // Switch: Dispositivo Personal Maestro
            Container(
              margin: const EdgeInsets.fromLTRB(16, 12, 16, 8),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xFF1B2332),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(
                  color: _esCelularMaestro ? Colors.cyanAccent.withOpacity(0.5) : Colors.white12,
                ),
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.phone_android,
                    color: _esCelularMaestro ? Colors.cyanAccent : Colors.white54,
                    size: 24,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          '📱 Celular Personal Maestro',
                          style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13),
                        ),
                        Text(
                          _esCelularMaestro
                              ? 'Este teléfono recibe alertas confidenciales'
                              : 'Activar sólo si este es su teléfono personal',
                          style: TextStyle(
                            color: _esCelularMaestro ? Colors.cyanAccent : Colors.white54,
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (_isLoadingDispositivos)
                    const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(color: Colors.cyanAccent, strokeWidth: 2),
                    )
                  else
                    Switch(
                      value: _esCelularMaestro,
                      activeColor: Colors.cyanAccent,
                      onChanged: _toggleCelularMaestro,
                    ),
                ],
              ),
            ),

            // Banner Flotante Rojo Neón 🚨 Si hay discrepancias detectadas
            if (_discrepanciasPendientes.isNotEmpty)
              InkWell(
                onTap: _mostrarModalDiscrepancias,
                child: Container(
                  margin: const EdgeInsets.fromLTRB(16, 4, 16, 12),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: const Color(0xFFB71C1C),
                    borderRadius: BorderRadius.circular(12),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.redAccent.withOpacity(0.6),
                        blurRadius: 12,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.notification_important, color: Colors.white, size: 28),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '🚨 ALERTA: ${_discrepanciasPendientes.length} DISCREPANCIA(S)',
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                            ),
                            const SizedBox(height: 2),
                            const Text(
                              'Faltantes detectados en apertura de turno. Toque para revisar y contactar.',
                              style: TextStyle(color: Colors.white70, fontSize: 11),
                            ),
                          ],
                        ),
                      ),
                      const Icon(Icons.arrow_forward_ios, color: Colors.white70, size: 16),
                    ],
                  ),
                ),
              ),

            // Grid de Módulos
            GridView.count(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 80),
              crossAxisCount: 2,
              crossAxisSpacing: 16,
              mainAxisSpacing: 16,
              childAspectRatio: 0.95,
              children: [
                _buildAdminCard(
                  context: context,
                  title: 'AUDITORÍA TICKET Z',
                  subtitle: 'Cruce con balance físico y combos',
                  icon: Icons.receipt_long,
                  gradient: const [Color(0xFF1E3C72), Color(0xFF2A5298)],
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const AuditoriaTicketZScreen()),
                    );
                  },
                ),
                _buildAdminCard(
                  context: context,
                  title: 'ALERTAS DE MERMA',
                  subtitle: 'Monitoreo de ratios empíricos',
                  icon: Icons.warning_amber_rounded,
                  gradient: const [Color(0xFFB71C1C), Color(0xFFE53935)],
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const AlertasMermaScreen()),
                    );
                  },
                ),
                _buildAdminCard(
                  context: context,
                  title: 'LIQUIDACIÓN SEMANAL',
                  subtitle: 'Consolidado barmen turno noche',
                  icon: Icons.payments_outlined,
                  gradient: const [Color(0xFF1B5E20), Color(0xFF388E3C)],
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const LiquidacionSemanalScreen()),
                    );
                  },
                ),
                _buildAdminCard(
                  context: context,
                  title: 'GESTIÓN DE RECETAS',
                  subtitle: 'Tarifas de comisión y combos',
                  icon: Icons.tune,
                  gradient: const [Color(0xFFE65100), Color(0xFFF57C00)],
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const RecetasScreen()),
                    );
                  },
                ),
                _buildAdminCard(
                  context: context,
                  title: 'CATÁLOGO PRODUCTOS',
                  subtitle: 'Altas, bajas y edición de insumos',
                  icon: Icons.inventory_2,
                  gradient: const [Color(0xFF0F766E), Color(0xFF14B8A6)],
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const CatalogoProductosScreen()),
                    );
                  },
                ),
                _buildAdminCard(
                  context: context,
                  title: 'GESTIÓN DE PERSONAL',
                  subtitle: 'Usuarios, sucursales y PINs',
                  icon: Icons.people_alt_outlined,
                  gradient: const [Color(0xFF6A1B9A), Color(0xFF8E24AA)],
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const UsuariosAdminScreen()),
                    );
                  },
                ),
                _buildAdminCard(
                  context: context,
                  title: 'COMPRAS Y FACTURAS',
                  subtitle: 'Abastecimiento multi-producto',
                  icon: Icons.receipt_long,
                  gradient: const [Color(0xFFEA580C), Color(0xFFF97316)],
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const RegistrarCompraScreen()),
                    );
                  },
                ),
                _buildAdminCard(
                  context: context,
                  title: 'GESTIÓN DE SUCURSALES',
                  subtitle: 'Altas, bajas y edición de sedes',
                  icon: Icons.storefront,
                  gradient: const [Color(0xFF0284C7), Color(0xFF0EA5E9)],
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const SucursalesAdminScreen()),
                    );
                  },
                ),
                _buildAdminCard(
                  context: context,
                  title: 'MOTIVOS DE BAJA',
                  subtitle: 'Administración de mermas/roturas',
                  icon: Icons.report_problem_outlined,
                  gradient: const [Color(0xFF7C2D12), Color(0xFFC2410C)],
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const MotivosBajaAdminScreen()),
                    );
                  },
                ),
              ],
            ),
          ],
        ),
      ),
      bottomNavigationBar: Container(
        color: const Color(0xFF111722),
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 16),
        child: SafeArea(
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: const [
              Icon(Icons.code, color: Colors.amberAccent, size: 14),
              SizedBox(width: 6),
              Text(
                'Desarrollado por: ING. DANIEL MÉNDEZ  |  WhatsApp: 67369293',
                style: TextStyle(color: Colors.white70, fontSize: 11, fontWeight: FontWeight.w600),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildAdminCard({
    required BuildContext context,
    required String title,
    required String subtitle,
    required IconData icon,
    required List<Color> gradient,
    required VoidCallback onTap,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Ink(
          decoration: BoxDecoration(
            gradient: LinearGradient(colors: gradient, begin: Alignment.topLeft, end: Alignment.bottomRight),
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(color: gradient.first.withOpacity(0.35), blurRadius: 10, offset: const Offset(0, 4)),
            ],
          ),
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(backgroundColor: Colors.white24, radius: 24, child: Icon(icon, color: Colors.white, size: 28)),
              const Spacer(),
              Text(
                title,
                style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 4),
              Text(
                subtitle,
                style: const TextStyle(color: Colors.white70, fontSize: 11),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
