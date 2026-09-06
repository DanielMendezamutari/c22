import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/network/api_client.dart';
import '../../providers/auth_provider.dart';

class AlertasMermaScreen extends ConsumerStatefulWidget {
  const AlertasMermaScreen({super.key});

  @override
  ConsumerState<AlertasMermaScreen> createState() => _AlertasMermaScreenState();
}

class _AlertasMermaScreenState extends ConsumerState<AlertasMermaScreen> {
  bool _isLoading = false;
  String? _errorMessage;
  int _sucursalSeleccionada = 1;
  List<Map<String, dynamic>> _barmenRatios = [];
  double _umbralMerma = 1.25;

  final List<Map<String, dynamic>> _sucursales = [
    {'id': 1, 'nombre': 'Casa22'},
    {'id': 2, 'nombre': 'Casa Coron'},
    {'id': 3, 'nombre': 'Madan'},
  ];

  @override
  void initState() {
    super.initState();
    final auth = ref.read(authProvider);
    _sucursalSeleccionada = auth.sucursalId ?? 1;
    _cargarReporte();
  }

  Future<void> _cargarReporte() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final apiClient = ref.read(apiClientProvider);
      final res = await apiClient.get('/auditoria/ratios', queryParameters: {
        'sucursal_id': _sucursalSeleccionada,
      });

      if (res.statusCode == 200 && res.data['success'] == true) {
        final data = res.data['data'];
        setState(() {
          _umbralMerma = (data['umbral_merma_maximo'] as num?)?.toDouble() ?? 1.25;
          final list = (data['barmen'] as List? ?? []);
          _barmenRatios = list.map((e) => Map<String, dynamic>.from(e)).toList();
        });
      } else {
        setState(() => _errorMessage = res.data['error'] ?? 'Error al cargar reporte');
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'No se pudo conectar con el servidor. Mostrando simulación offline.';
        _barmenRatios = [
          {
            'barman_id': 4,
            'barman': 'Carlos Mendoza',
            'turnos_con_transformacion': 8,
            'ratio_promedio': 1.167,
            'ratio_minimo': 1.140,
            'ratio_maximo': 1.180,
            'total_insumo_usado': 112.0,
            'total_terminado_producido': 96.0,
            'alerta_merma': false,
            'estado_tolerancia': 'optimo',
          },
          {
            'barman_id': 5,
            'barman': 'Roberto Gómez',
            'turnos_con_transformacion': 5,
            'ratio_promedio': 1.420,
            'ratio_minimo': 1.280,
            'ratio_maximo': 1.550,
            'total_insumo_usado': 71.0,
            'total_terminado_producido': 50.0,
            'alerta_merma': true,
            'estado_tolerancia': 'merma_anomala',
          },
        ];
      });
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF121620),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1B2332),
        title: const Text('Ratios y Detección de Mermas', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: Column(
        children: [
          // Selector de Sucursal
          Container(
            padding: const EdgeInsets.all(16),
            color: const Color(0xFF1B2332),
            child: Row(
              children: [
                const Icon(Icons.storefront, color: Colors.amberAccent),
                const SizedBox(width: 12),
                const Text('Sucursal:', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                const SizedBox(width: 12),
                Expanded(
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    decoration: BoxDecoration(
                      color: const Color(0xFF121620),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: Colors.white24),
                    ),
                    child: DropdownButtonHideUnderline(
                      child: DropdownButton<int>(
                        value: _sucursalSeleccionada,
                        dropdownColor: const Color(0xFF1B2332),
                        style: const TextStyle(color: Colors.white),
                        items: _sucursales.map((s) {
                          return DropdownMenuItem<int>(
                            value: s['id'] as int,
                            child: Text(s['nombre'] as String),
                          );
                        }).toList(),
                        onChanged: (val) {
                          if (val != null) {
                            setState(() => _sucursalSeleccionada = val);
                            _cargarReporte();
                          }
                        },
                      ),
                    ),
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.refresh, color: Colors.white70),
                  onPressed: _cargarReporte,
                ),
              ],
            ),
          ),

          // Banner explicativo del ratio
          Container(
            margin: const EdgeInsets.all(12),
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFE67E22).withOpacity(0.15),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFE67E22).withOpacity(0.4)),
            ),
            child: Row(
              children: [
                const Icon(Icons.science, color: Color(0xFFF39C12), size: 28),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Ratio Óptimo: ~1.167 latas/botella (14 latas = 12 botellas). Alertas activadas cuando el ratio supera $_umbralMerma.',
                    style: const TextStyle(color: Colors.white70, fontSize: 12),
                  ),
                ),
              ],
            ),
          ),

          if (_errorMessage != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Text(_errorMessage!, style: const TextStyle(color: Colors.amberAccent, fontSize: 12)),
            ),

          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: Colors.amberAccent))
                : _barmenRatios.isEmpty
                    ? const Center(
                        child: Text(
                          'No hay registros de transformaciones para esta sucursal.',
                          style: TextStyle(color: Colors.white54),
                        ),
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        itemCount: _barmenRatios.length,
                        itemBuilder: (context, index) {
                          final barman = _barmenRatios[index];
                          final tieneAlerta = barman['alerta_merma'] == true;
                          final ratioProm = (barman['ratio_promedio'] as num).toDouble();

                          return Card(
                            color: const Color(0xFF1B2332),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                              side: BorderSide(
                                color: tieneAlerta ? const Color(0xFFE74C3C) : const Color(0xFF27AE60),
                                width: tieneAlerta ? 2 : 1,
                              ),
                            ),
                            margin: const EdgeInsets.symmetric(vertical: 8),
                            child: Padding(
                              padding: const EdgeInsets.all(16.0),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      CircleAvatar(
                                        backgroundColor: tieneAlerta
                                            ? const Color(0xFFE74C3C).withOpacity(0.2)
                                            : const Color(0xFF27AE60).withOpacity(0.2),
                                        child: Icon(
                                          tieneAlerta ? Icons.warning : Icons.verified,
                                          color: tieneAlerta ? const Color(0xFFE74C3C) : const Color(0xFF2ECC71),
                                        ),
                                      ),
                                      const SizedBox(width: 12),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              barman['barman'] ?? 'Barman',
                                              style: const TextStyle(
                                                color: Colors.white,
                                                fontSize: 16,
                                                fontWeight: FontWeight.bold,
                                              ),
                                            ),
                                            Text(
                                              'Turnos operados: ${barman['turnos_con_transformacion']}',
                                              style: const TextStyle(color: Colors.white60, fontSize: 12),
                                            ),
                                          ],
                                        ),
                                      ),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                                        decoration: BoxDecoration(
                                          color: tieneAlerta
                                              ? const Color(0xFFE74C3C).withOpacity(0.25)
                                              : const Color(0xFF27AE60).withOpacity(0.25),
                                          borderRadius: BorderRadius.circular(8),
                                        ),
                                        child: Column(
                                          children: [
                                            Text(
                                              ratioProm.toStringAsFixed(3),
                                              style: TextStyle(
                                                color: tieneAlerta ? const Color(0xFFFF7675) : const Color(0xFF55EFC4),
                                                fontWeight: FontWeight.w900,
                                                fontSize: 18,
                                              ),
                                            ),
                                            Text(
                                              tieneAlerta ? '¡MERMA ANÓMALA!' : 'NORMAL',
                                              style: TextStyle(
                                                color: tieneAlerta ? const Color(0xFFFF7675) : const Color(0xFF55EFC4),
                                                fontSize: 9,
                                                fontWeight: FontWeight.bold,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ],
                                  ),
                                  const Divider(color: Colors.white12, height: 24),
                                  Row(
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      _buildStatItem('Rango Min / Max', '${barman['ratio_minimo']} - ${barman['ratio_maximo']}'),
                                      _buildStatItem('Latas Consumidas', '${barman['total_insumo_usado']} u'),
                                      _buildStatItem('Botellas Obtenidas', '${barman['total_terminado_producido']} u'),
                                    ],
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatItem(String label, String value) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(color: Colors.white54, fontSize: 11)),
        const SizedBox(height: 2),
        Text(value, style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold)),
      ],
    );
  }
}
