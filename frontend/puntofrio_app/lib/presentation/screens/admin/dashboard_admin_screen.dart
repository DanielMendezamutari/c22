import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../auth/login_screen.dart';
import 'alertas_merma_screen.dart';
import 'auditoria_ticket_z_screen.dart';
import 'catalogo_productos_screen.dart';
import 'liquidacion_semanal_screen.dart';
import 'recetas_screen.dart';
import 'usuarios_admin_screen.dart';
import 'sucursales_admin_screen.dart';
import '../inventario/registrar_compra_screen.dart';
import '../../providers/auth_provider.dart';

class DashboardAdminScreen extends ConsumerWidget {
  const DashboardAdminScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
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
      body: GridView.count(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 80),
        physics: const AlwaysScrollableScrollPhysics(parent: BouncingScrollPhysics()),
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
          ],
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
