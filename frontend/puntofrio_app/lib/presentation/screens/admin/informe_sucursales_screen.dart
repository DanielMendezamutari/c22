import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/network/api_client.dart';
import '../../providers/auth_provider.dart';
import 'informe_operativo_turno_pdf_service.dart';

class InformeSucursalesScreen extends ConsumerStatefulWidget {
  const InformeSucursalesScreen({super.key});

  @override
  ConsumerState<InformeSucursalesScreen> createState() => _InformeSucursalesScreenState();
}

class _InformeSucursalesScreenState extends ConsumerState<InformeSucursalesScreen> {
  List<Map<String, dynamic>> _sucursales = [];
  int? _selectedSucursalId;
  int? _selectedTurnoId;

  bool _isLoadingSucursales = false;
  bool _isLoadingInforme = false;
  bool _isExportingPdf = false;

  Map<String, dynamic>? _informeData;

  @override
  void initState() {
    super.initState();
    _cargarSucursales();
  }

  Future<void> _cargarSucursales() async {
    setState(() => _isLoadingSucursales = true);
    try {
      final client = ref.read(apiClientProvider);
      final res = await client.get('/sucursales?activo=1');
      if (res.statusCode == 200 && res.data['success'] == true) {
        final List list = res.data['data'] ?? [];
        setState(() {
          _sucursales = List<Map<String, dynamic>>.from(list);
          if (_sucursales.isNotEmpty && _selectedSucursalId == null) {
            _selectedSucursalId = _sucursales.first['id'] as int;
            _cargarInformeTurno();
          }
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error al cargar sucursales: $e'), backgroundColor: Colors.redAccent),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoadingSucursales = false);
    }
  }

  Future<void> _cargarInformeTurno({int? turnoId}) async {
    if (_selectedSucursalId == null) return;
    setState(() => _isLoadingInforme = true);
    try {
      final client = ref.read(apiClientProvider);
      String url = '/auditoria/sucursal/$_selectedSucursalId/informe-turno';
      if (turnoId != null && turnoId > 0) {
        url += '?turno_id=$turnoId';
      }

      final res = await client.get(url);
      if (res.statusCode == 200 && res.data['success'] == true) {
        setState(() {
          _informeData = res.data['data'];
          final turno = _informeData?['turno'];
          if (turno != null) {
            _selectedTurnoId = turno['id'] as int?;
          }
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error al cargar informe de turno: $e'), backgroundColor: Colors.redAccent),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoadingInforme = false);
    }
  }

  Future<void> _generarPdf(bool compartirWhatsApp) async {
    if (_informeData == null || _informeData!['turno'] == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No hay información de turno disponible para generar el PDF.'), backgroundColor: Colors.orange),
      );
      return;
    }

    setState(() => _isExportingPdf = true);
    try {
      if (compartirWhatsApp) {
        await InformeOperativoTurnoPdfService.compartirPdfWhatsApp(
          context: context,
          data: _informeData!,
        );
      } else {
        await InformeOperativoTurnoPdfService.previsualizarEImprimir(
          context: context,
          data: _informeData!,
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error al exportar PDF: $e'), backgroundColor: Colors.redAccent),
        );
      }
    } finally {
      if (mounted) setState(() => _isExportingPdf = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final turno = _informeData?['turno'];
    final bool esEnVivo = turno != null && (turno['es_en_vivo'] == true || turno['estado'] == 'abierto');
    final List balance = (_informeData?['balance_inventario'] as List?) ?? [];
    final List compras = (_informeData?['compras'] as List?) ?? [];
    final List turnosHistorial = (_informeData?['turnos_historial'] as List?) ?? [];
    final liquidacion = _informeData?['liquidacion'] ?? {};

    return Scaffold(
      backgroundColor: const Color(0xFF0F172A),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1E293B),
        elevation: 0,
        title: const Row(
          children: [
            Icon(Icons.query_stats, color: Color(0xFF38BDF8)),
            SizedBox(width: 8),
            Text(
              'MONITOREO DE SUCURSAL',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, letterSpacing: 0.5),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Actualizar en vivo',
            onPressed: () => _cargarInformeTurno(turnoId: _selectedTurnoId),
          ),
        ],
      ),
      body: _isLoadingSucursales
          ? const Center(child: CircularProgressIndicator(color: Color(0xFF38BDF8)))
          : Column(
              children: [
                // Selector de Sucursales (Pestañas horizontales)
                Container(
                  color: const Color(0xFF1E293B),
                  padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 12),
                  child: SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: _sucursales.map((s) {
                        final isSelected = s['id'] == _selectedSucursalId;
                        return Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: ChoiceChip(
                            avatar: Icon(
                              Icons.storefront,
                              size: 16,
                              color: isSelected ? Colors.white : Colors.white60,
                            ),
                            label: Text(
                              (s['nombre'] ?? '').toString().toUpperCase(),
                              style: TextStyle(
                                color: isSelected ? Colors.white : Colors.white70,
                                fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                                fontSize: 13,
                              ),
                            ),
                            selected: isSelected,
                            selectedColor: const Color(0xFF0284C7),
                            backgroundColor: const Color(0xFF0F172A),
                            onSelected: (val) {
                              if (val) {
                                setState(() {
                                  _selectedSucursalId = s['id'] as int;
                                  _selectedTurnoId = null;
                                });
                                _cargarInformeTurno();
                              }
                            },
                          ),
                        );
                      }).toList(),
                    ),
                  ),
                ),

                // Selector de Turnos (En vivo o histórico)
                if (turnosHistorial.isNotEmpty)
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    decoration: BoxDecoration(
                      color: const Color(0xFF1E293B).withOpacity(0.5),
                      border: const Border(bottom: BorderSide(color: Colors.white10)),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.history, color: Color(0xFF38BDF8), size: 18),
                        const SizedBox(width: 8),
                        const Text('Turno a Auditar: ', style: TextStyle(color: Colors.white70, fontSize: 13)),
                        const SizedBox(width: 6),
                        Expanded(
                          child: DropdownButtonHideUnderline(
                            child: DropdownButton<int>(
                              value: _selectedTurnoId,
                              dropdownColor: const Color(0xFF1E293B),
                              style: const TextStyle(color: Colors.white, fontSize: 13),
                              items: turnosHistorial.map<DropdownMenuItem<int>>((t) {
                                final bool abierto = t['estado'] == 'abierto';
                                final String fecha = t['fecha_apertura'] ?? '';
                                final String tipo = (t['tipo_turno'] ?? '').toString().toUpperCase();
                                final String barman = t['barman_nombre'] ?? 'N/A';
                                return DropdownMenuItem<int>(
                                  value: t['id'] as int,
                                  child: Text(
                                    abierto
                                        ? '🔴 [EN VIVO] $tipo - $barman'
                                        : '#${t['id']} $tipo ($fecha) - $barman',
                                    overflow: TextOverflow.ellipsis,
                                    style: TextStyle(
                                      color: abierto ? const Color(0xFFF59E0B) : Colors.white,
                                      fontWeight: abierto ? FontWeight.bold : FontWeight.normal,
                                    ),
                                  ),
                                );
                              }).toList(),
                              onChanged: (newId) {
                                if (newId != null) {
                                  setState(() => _selectedTurnoId = newId);
                                  _cargarInformeTurno(turnoId: newId);
                                }
                              },
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),

                // Contenido del Informe
                Expanded(
                  child: _isLoadingInforme
                      ? const Center(child: CircularProgressIndicator(color: Color(0xFF38BDF8)))
                      : turno == null
                          ? Center(
                              child: Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(Icons.receipt_long_outlined, size: 64, color: Colors.white.withOpacity(0.2)),
                                  const SizedBox(height: 12),
                                  const Text('No se encontraron turnos en esta sucursal.', style: TextStyle(color: Colors.white60)),
                                ],
                              ),
                            )
                          : SingleChildScrollView(
                              padding: const EdgeInsets.all(16),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  // Tarjeta de Estado del Turno
                                  Container(
                                    padding: const EdgeInsets.all(16),
                                    decoration: BoxDecoration(
                                      gradient: LinearGradient(
                                        colors: esEnVivo
                                            ? const [Color(0xFF78350F), Color(0xFFB45309)]
                                            : const [Color(0xFF064E3B), Color(0xFF047857)],
                                        begin: Alignment.topLeft,
                                        end: Alignment.bottomRight,
                                      ),
                                      borderRadius: BorderRadius.circular(16),
                                      boxShadow: [
                                        BoxShadow(
                                          color: (esEnVivo ? Colors.amber : Colors.green).withOpacity(0.3),
                                          blurRadius: 10,
                                          offset: const Offset(0, 4),
                                        ),
                                      ],
                                    ),
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Row(
                                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                          children: [
                                            Row(
                                              children: [
                                                Icon(
                                                  esEnVivo ? Icons.radio_button_checked : Icons.verified,
                                                  color: Colors.white,
                                                  size: 20,
                                                ),
                                                const SizedBox(width: 8),
                                                Text(
                                                  esEnVivo ? 'TURNO ACTIVO EN VIVO' : 'TURNO CERRADO',
                                                  style: const TextStyle(
                                                    color: Colors.white,
                                                    fontWeight: FontWeight.bold,
                                                    fontSize: 15,
                                                    letterSpacing: 0.5,
                                                  ),
                                                ),
                                              ],
                                            ),
                                            Container(
                                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                              decoration: BoxDecoration(
                                                color: Colors.black26,
                                                borderRadius: BorderRadius.circular(8),
                                              ),
                                              child: Text(
                                                'Turno ${(turno['tipo_turno'] ?? 'dia').toString().toUpperCase()}',
                                                style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.bold),
                                              ),
                                            ),
                                          ],
                                        ),
                                        const SizedBox(height: 12),
                                        Row(
                                          children: [
                                            const Icon(Icons.person, color: Colors.white70, size: 16),
                                            const SizedBox(width: 6),
                                            Text(
                                              'Barman Responsable: ${turno['barman']?['nombre'] ?? 'N/A'}',
                                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                                            ),
                                          ],
                                        ),
                                        const SizedBox(height: 4),
                                        Row(
                                          children: [
                                            const Icon(Icons.access_time, color: Colors.white70, size: 16),
                                            const SizedBox(width: 6),
                                            Text(
                                              'Apertura: ${turno['fecha_apertura'] ?? 'N/A'}',
                                              style: const TextStyle(color: Colors.white70, fontSize: 12),
                                            ),
                                          ],
                                        ),
                                        if (!esEnVivo) ...[
                                          const SizedBox(height: 4),
                                          Row(
                                            children: [
                                              const Icon(Icons.lock_clock, color: Colors.white70, size: 16),
                                              const SizedBox(width: 6),
                                              Text(
                                                'Cierre: ${turno['fecha_cierre'] ?? 'N/A'}',
                                                style: const TextStyle(color: Colors.white70, fontSize: 12),
                                              ),
                                            ],
                                          ),
                                        ],
                                      ],
                                    ),
                                  ),
                                  const SizedBox(height: 16),

                                  // Resumen de Métricas Clave
                                  Row(
                                    children: [
                                      Expanded(
                                        child: _buildMetricTile(
                                          title: 'Rellenos',
                                          value: '${_informeData?['transformaciones']?['total_terminadas'] ?? 0} bot.',
                                          subtitle: '${_informeData?['transformaciones']?['comision_total'] ?? 0} Bs com.',
                                          icon: Icons.sync_alt,
                                          color: const Color(0xFFF59E0B),
                                        ),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: _buildMetricTile(
                                          title: 'Compras',
                                          value: '${compras.length} notas',
                                          subtitle: 'De proveedores',
                                          icon: Icons.local_shipping,
                                          color: const Color(0xFF10B981),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 12),
                                  Row(
                                    children: [
                                      Expanded(
                                        child: _buildMetricTile(
                                          title: 'Bajas / Mermas',
                                          value: '${(_informeData?['bajas'] as List?)?.length ?? 0} roturas',
                                          subtitle: 'Declaradas',
                                          icon: Icons.report_problem_outlined,
                                          color: const Color(0xFFEF4444),
                                        ),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: _buildMetricTile(
                                          title: 'Liquidación',
                                          value: (liquidacion['estado_cobro'] ?? 'abierto').toString().toUpperCase(),
                                          subtitle: liquidacion['es_turno_dia'] == true ? 'Jornal Diario' : 'Sueldo Noche',
                                          icon: Icons.payments_outlined,
                                          color: const Color(0xFF38BDF8),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 20),

                                  // Tabla Vista Previa de Balance
                                  Row(
                                    children: [
                                      const Icon(Icons.table_chart_outlined, color: Color(0xFF38BDF8), size: 18),
                                      const SizedBox(width: 8),
                                      const Text(
                                        'BALANCE DE MASA EN CUSTODIA',
                                        style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 8),
                                  Container(
                                    decoration: BoxDecoration(
                                      color: const Color(0xFF1E293B),
                                      borderRadius: BorderRadius.circular(12),
                                      border: Border.all(color: Colors.white10),
                                    ),
                                    child: balance.isEmpty
                                        ? const Padding(
                                            padding: EdgeInsets.all(16),
                                            child: Center(
                                              child: Text('Sin movimientos de inventario en este turno.', style: TextStyle(color: Colors.white54, fontSize: 12)),
                                            ),
                                          )
                                        : ListView.separated(
                                            shrinkWrap: true,
                                            physics: const NeverScrollableScrollPhysics(),
                                            itemCount: balance.length,
                                            separatorBuilder: (_, __) => const Divider(color: Colors.white10, height: 1),
                                            itemBuilder: (context, idx) {
                                              final b = balance[idx];
                                              final ini = b['stock_inicial'];
                                              final teo = b['stock_teorico_custodia'];
                                              final fis = b['stock_final_fisico'];
                                              final dif = b['diferencia'];

                                              return Padding(
                                                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                                                child: Row(
                                                  children: [
                                                    Expanded(
                                                      child: Column(
                                                        crossAxisAlignment: CrossAxisAlignment.start,
                                                        children: [
                                                          Text(
                                                            b['producto_nombre'] ?? 'N/A',
                                                            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 13),
                                                          ),
                                                          const SizedBox(height: 2),
                                                          Text(
                                                            'Inicial: $ini  |  Ingr: +${b['ingresos_compras']}  |  Baja: -${b['bajas_roturas']}',
                                                            style: const TextStyle(color: Colors.white54, fontSize: 11),
                                                          ),
                                                        ],
                                                      ),
                                                    ),
                                                    Column(
                                                      crossAxisAlignment: CrossAxisAlignment.end,
                                                      children: [
                                                        Text(
                                                          'Custodia: $teo',
                                                          style: const TextStyle(color: Color(0xFF38BDF8), fontWeight: FontWeight.bold, fontSize: 13),
                                                        ),
                                                        if (fis != null)
                                                          Text(
                                                            'Físico: $fis (${dif >= 0 ? "+$dif" : "$dif"})',
                                                            style: TextStyle(
                                                              color: dif < 0 ? Colors.redAccent : Colors.greenAccent,
                                                              fontWeight: FontWeight.bold,
                                                              fontSize: 11,
                                                            ),
                                                          )
                                                        else
                                                          const Text(
                                                            'En vivo',
                                                            style: TextStyle(color: Colors.amberAccent, fontSize: 11),
                                                          ),
                                                      ],
                                                    ),
                                                  ],
                                                ),
                                              );
                                            },
                                          ),
                                  ),
                                  const SizedBox(height: 24),

                                  // Botones de Generación de PDF y WhatsApp
                                  ElevatedButton.icon(
                                    style: ElevatedButton.styleFrom(
                                      backgroundColor: const Color(0xFF0284C7),
                                      foregroundColor: Colors.white,
                                      padding: const EdgeInsets.symmetric(vertical: 16),
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                      minimumSize: const Size.fromHeight(52),
                                      elevation: 4,
                                    ),
                                    icon: _isExportingPdf
                                        ? const SizedBox(
                                            width: 20,
                                            height: 20,
                                            child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                                          )
                                        : const Icon(Icons.picture_as_pdf),
                                    label: const Text(
                                      'GENERAR INFORME OFICIAL PDF',
                                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, letterSpacing: 0.5),
                                    ),
                                    onPressed: _isExportingPdf ? null : () => _generarPdf(false),
                                  ),
                                  const SizedBox(height: 12),
                                  OutlinedButton.icon(
                                    style: OutlinedButton.styleFrom(
                                      foregroundColor: const Color(0xFF25D366),
                                      side: const BorderSide(color: Color(0xFF25D366), width: 1.5),
                                      padding: const EdgeInsets.symmetric(vertical: 14),
                                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                      minimumSize: const Size.fromHeight(48),
                                    ),
                                    icon: const Icon(Icons.share, color: Color(0xFF25D366)),
                                    label: const Text(
                                      'COMPARTIR POR WHATSAPP',
                                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, letterSpacing: 0.5),
                                    ),
                                    onPressed: _isExportingPdf ? null : () => _generarPdf(true),
                                  ),
                                  const SizedBox(height: 30),
                                ],
                              ),
                            ),
                ),
              ],
            ),
    );
  }

  Widget _buildMetricTile({
    required String title,
    required String value,
    required String subtitle,
    required IconData icon,
    required Color color,
  }) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFF1E293B),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Row(
        children: [
          CircleAvatar(
            backgroundColor: color.withOpacity(0.15),
            radius: 18,
            child: Icon(icon, color: color, size: 20),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: Colors.white54, fontSize: 11)),
                Text(value, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14)),
                Text(subtitle, style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w600)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
