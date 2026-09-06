import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../auth/login_screen.dart';
import '../cobro/resumen_cajera_screen.dart';
import '../ingreso/ingreso_mercaderia_screen.dart';
import '../transformacion/transformacion_screen.dart';
import '../turnos/corte_inventario_screen.dart';
import '../turnos/bajas_roturas_screen.dart';
import '../traspasos/enviar_traspaso_screen.dart';
import '../traspasos/recibir_traspaso_screen.dart';
import '../../providers/auth_provider.dart';

class DashboardBarmanScreen extends ConsumerWidget {
  const DashboardBarmanScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authProvider);

    return Scaffold(
      backgroundColor: const Color(0xFF121620),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1B2332),
        elevation: 0,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              auth.sucursalNombre ?? 'Grupo Punto Frío',
              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.amberAccent),
            ),
            Text(
              'Barman: ${auth.nombre ?? 'Operador'}',
              style: const TextStyle(fontSize: 13, color: Colors.white70),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout, color: Colors.white70),
            onPressed: () async {
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
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: GridView.count(
          crossAxisCount: 2,
          crossAxisSpacing: 16,
          mainAxisSpacing: 16,
          childAspectRatio: 0.95,
          children: [
            _buildActionCard(
              context: context,
              title: 'REGISTRAR RELLENO',
              subtitle: 'Transformación Corona (1 Bs/u)',
              icon: Icons.local_bar,
              gradient: const [Color(0xFFE67E22), Color(0xFFD35400)],
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const TransformacionScreen()),
                );
              },
            ),
            _buildActionCard(
              context: context,
              title: 'RESUMEN A CAJERA',
              subtitle: 'Cobro inmediato con reloj dinámico',
              icon: Icons.point_of_sale,
              gradient: const [Color(0xFF27AE60), Color(0xFF2ECC71)],
              onTap: () {
                final turnoId = auth.turnoActivoId ?? 1;
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => ResumenCajeraScreen(turnoId: turnoId)),
                );
              },
            ),
            _buildActionCard(
              context: context,
              title: 'CORTE INVENTARIO',
              subtitle: 'Apertura / Cierre con fracciones 1/4',
              icon: Icons.inventory_2,
              gradient: const [Color(0xFF2980B9), Color(0xFF3498DB)],
              onTap: () {
                showModalBottomSheet(
                  context: context,
                  backgroundColor: const Color(0xFF1B2332),
                  shape: const RoundedRectangleBorder(
                    borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
                  ),
                  builder: (ctx) => Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Text(
                          'Corte Físico de Inventario',
                          style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 16),
                        ListTile(
                          leading: const Icon(Icons.login, color: Color(0xFF5DADE2)),
                          title: const Text('Corte de Apertura (Inicio de Turno)', style: TextStyle(color: Colors.white)),
                          subtitle: const Text('Asentar stock físico recibido', style: TextStyle(color: Colors.white60)),
                          onTap: () {
                            Navigator.of(ctx).pop();
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => const CorteInventarioScreen(tipoOperacion: TipoOperacionCorte.apertura),
                              ),
                            );
                          },
                        ),
                        const Divider(color: Colors.white12),
                        ListTile(
                          leading: const Icon(Icons.lock_clock, color: Color(0xFFE74C3C)),
                          title: const Text('Corte de Cierre (Final de Jornada)', style: TextStyle(color: Colors.white)),
                          subtitle: const Text('Inmutable - Cierra turno y congela corte', style: TextStyle(color: Colors.white60)),
                          onTap: () {
                            Navigator.of(ctx).pop();
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => CorteInventarioScreen(
                                  tipoOperacion: TipoOperacionCorte.cierre,
                                  turnoId: auth.turnoActivoId,
                                ),
                              ),
                            );
                          },
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
            _buildActionCard(
              context: context,
              title: 'NUEVO INGRESO',
              subtitle: 'Recepción con foto de nota',
              icon: Icons.camera_alt,
              gradient: const [Color(0xFF8E44AD), Color(0xFF9B59B6)],
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const IngresoMercaderiaScreen()),
                );
              },
            ),
            _buildActionCard(
              context: context,
              title: 'TRASPASOS',
              subtitle: 'Custodia entre Casa22, Coron y Madan',
              icon: Icons.swap_horiz,
              gradient: const [Color(0xFF16A085), Color(0xFF1ABC9C)],
              onTap: () {
                showModalBottomSheet(
                  context: context,
                  backgroundColor: const Color(0xFF1B2332),
                  shape: const RoundedRectangleBorder(
                    borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
                  ),
                  builder: (ctx) => Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Text(
                          'Traspasos Inter-Sucursales',
                          style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 16),
                        ListTile(
                          leading: const Icon(Icons.outbox, color: Color(0xFF1ABC9C)),
                          title: const Text('Despachar Mercadería (Salida)', style: TextStyle(color: Colors.white)),
                          subtitle: const Text('Enviar a Casa22, Casa Coron o Madan', style: TextStyle(color: Colors.white60)),
                          onTap: () {
                            Navigator.of(ctx).pop();
                            Navigator.of(context).push(
                              MaterialPageRoute(builder: (_) => const EnviarTraspasoScreen()),
                            );
                          },
                        ),
                        const Divider(color: Colors.white12),
                        ListTile(
                          leading: const Icon(Icons.move_to_inbox, color: Color(0xFF2ECC71)),
                          title: const Text('Recepcionar Mercadería (Entrada)', style: TextStyle(color: Colors.white)),
                          subtitle: const Text('Contar bultos y registrar mermas en tránsito', style: TextStyle(color: Colors.white60)),
                          onTap: () {
                            Navigator.of(ctx).pop();
                            Navigator.of(context).push(
                              MaterialPageRoute(builder: (_) => const RecibirTraspasoScreen()),
                            );
                          },
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
            _buildActionCard(
              context: context,
              title: 'BAJAS / ROTURAS',
              subtitle: 'Declaración de botellas rotas',
              icon: Icons.remove_circle_outline,
              gradient: const [Color(0xFFC0392B), Color(0xFFE74C3C)],
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const BajasRoturasScreen()),
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildActionCard({
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
              BoxShadow(color: gradient.first.withOpacity(0.3), blurRadius: 10, offset: const Offset(0, 4)),
            ],
          ),
          padding: const EdgeInsets.all(14),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                backgroundColor: Colors.white24,
                radius: 24,
                child: Icon(icon, color: Colors.white, size: 28),
              ),
              const Spacer(),
              Text(
                title,
                style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w900, letterSpacing: 0.5),
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
