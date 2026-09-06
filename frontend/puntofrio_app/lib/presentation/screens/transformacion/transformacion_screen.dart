import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/auth_provider.dart';
import '../../providers/transformacion_provider.dart';

class TransformacionScreen extends ConsumerStatefulWidget {
  const TransformacionScreen({super.key});

  @override
  ConsumerState<TransformacionScreen> createState() => _TransformacionScreenState();
}

class _TransformacionScreenState extends ConsumerState<TransformacionScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;

  // --- Cerveza Corona ---
  final _latasController = TextEditingController();
  final _botellasController = TextEditingController();
  final _roturasController = TextEditingController(text: '0');
  int _selectedCervezaId = 1;

  final List<Map<String, dynamic>> _cervezasDisponibles = [
    {'id': 1, 'nombre': 'Corona en Lata 355ml (Original)'},
    {'id': 7, 'nombre': 'Cerveza Moema Lata 355ml'},
    {'id': 6, 'nombre': 'Cerveza Paceña Lata 355ml'},
    {'id': 8, 'nombre': 'Cerveza Orureña Lata 355ml'},
    {'id': 9, 'nombre': 'Cerveza Huari Lata 355ml'},
  ];

  // --- Destilados Compuestos ---
  double _cantBlackstone = 0.5;
  double _cantChancellor = 0.5;
  int _botellasRedLabel = 1;
  int _roturasDestilados = 0;
  bool _isSubmittingDestilado = false;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    _latasController.dispose();
    _botellasController.dispose();
    _roturasController.dispose();
    super.dispose();
  }

  void _incrementarLatas(double cantidad) {
    final actual = double.tryParse(_latasController.text) ?? 0.0;
    final nuevo = actual + cantidad;
    _latasController.text = nuevo.toStringAsFixed(0);
    ref.read(transformacionProvider.notifier).setCantidadInsumo(nuevo);
  }

  void _incrementarBotellas(int cantidad) {
    final actual = int.tryParse(_botellasController.text) ?? 0;
    final nuevo = actual + cantidad;
    _botellasController.text = nuevo.toString();
    ref.read(transformacionProvider.notifier).setCantidadProducida(nuevo);
  }

  Future<void> _guardarDestiladosCompuestos() async {
    final authState = ref.read(authProvider);
    final turnoId = authState.turnoActivoId ?? 1;

    if (_cantBlackstone <= 0 && _cantChancellor <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          backgroundColor: Colors.redAccent,
          content: Text('Debe ingresar al menos una fracción de Blackstone o Chancellor'),
        ),
      );
      return;
    }

    if (_botellasRedLabel <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          backgroundColor: Colors.redAccent,
          content: Text('Debe producir al menos 1 botella de Johnnie Walker Red Label'),
        ),
      );
      return;
    }

    setState(() => _isSubmittingDestilado = true);

    try {
      final totalInsumo = _cantBlackstone + _cantChancellor;
      final insumos = <Map<String, dynamic>>[];
      if (_cantBlackstone > 0) {
        insumos.add({'insumo_id': 10, 'cantidad': _cantBlackstone}); // Blackstone
      }
      if (_cantChancellor > 0) {
        insumos.add({'insumo_id': 11, 'cantidad': _cantChancellor}); // Chancellor
      }

      final ok = await ref.read(transformacionProvider.notifier).registrarRelleno(
            turnoId: turnoId,
            recetaId: 2, // Receta destilados compuestos
            insumoOrigenId: 10,
            insumosOrigen: insumos,
            cantidadInsumo: totalInsumo,
            cantidadProducida: _botellasRedLabel,
            cantidadRoturas: _roturasDestilados,
            observaciones: 'Mezcla compuesta: Blackstone ($_cantBlackstone btl) + Chancellor ($_cantChancellor btl) ➔ Johnnie Walker Red Label',
          );

      if (ok && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            backgroundColor: Colors.green,
            content: Text('¡Mezcla compuesta registrada con éxito (Blackstone + Chancellor ➔ Red Label)!'),
          ),
        );
        setState(() {
          _cantBlackstone = 0.5;
          _cantChancellor = 0.5;
          _botellasRedLabel = 1;
          _roturasDestilados = 0;
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            backgroundColor: Colors.redAccent,
            content: Text('Error: $e'),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmittingDestilado = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(transformacionProvider);
    final authState = ref.watch(authProvider);

    ref.listen<TransformacionState>(transformacionProvider, (prev, next) {
      if (next.submitSuccess && _tabController.index == 0) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            backgroundColor: Colors.green,
            content: Text('¡Relleno de Corona registrado con éxito!'),
          ),
        );
        _latasController.clear();
        _botellasController.clear();
        _roturasController.text = '0';
      }
      if (next.errorMessage != null && _tabController.index == 0) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            backgroundColor: Colors.redAccent,
            content: Text(next.errorMessage!),
          ),
        );
      }
    });

    return Scaffold(
      backgroundColor: const Color(0xFF121212),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1E1E1E),
        title: const Text('Registro de Transformaciones', style: TextStyle(fontWeight: FontWeight.bold)),
        elevation: 0,
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: Colors.amberAccent,
          labelColor: Colors.amberAccent,
          unselectedLabelColor: Colors.white60,
          tabs: const [
            Tab(icon: Icon(Icons.sports_bar), text: 'Relleno Cervezas'),
            Tab(icon: Icon(Icons.local_bar), text: 'Mezcla Destilados'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          // ----------------- TAB 1: CERVEZAS (CORONA) -----------------
          SingleChildScrollView(
            padding: const EdgeInsets.all(16.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Banner de Información en Vivo
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF2C3E50), Color(0xFF3498DB)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Comisión a Ganar:', style: TextStyle(color: Colors.white70, fontSize: 16)),
                          Text('${state.comisionDevengada} Bs',
                              style: const TextStyle(color: Colors.amberAccent, fontSize: 26, fontWeight: FontWeight.bold)),
                        ],
                      ),
                      const Divider(color: Colors.white24, height: 16),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text('Unidades Netas: ${state.unidadesNetas}',
                              style: const TextStyle(color: Colors.white, fontSize: 14)),
                          Text('Ratio Empírico: ${state.ratioEmpirico}',
                              style: TextStyle(
                                color: state.ratioEmpirico > 1.30 ? Colors.redAccent : Colors.lightGreenAccent,
                                fontWeight: FontWeight.bold,
                                fontSize: 14,
                              )),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),

                // Selector de Cerveza Física
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                  decoration: BoxDecoration(
                    color: const Color(0xFF1E293B),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.amberAccent.withOpacity(0.5)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: const [
                          Icon(Icons.swap_horiz, color: Colors.amberAccent, size: 18),
                          SizedBox(width: 8),
                          Text('Insumo Físico Utilizado (Intercambiable):',
                              style: TextStyle(color: Colors.amberAccent, fontSize: 13, fontWeight: FontWeight.bold)),
                        ],
                      ),
                      const SizedBox(height: 6),
                      DropdownButtonHideUnderline(
                        child: DropdownButton<int>(
                          value: _selectedCervezaId,
                          isExpanded: true,
                          dropdownColor: const Color(0xFF1E293B),
                          style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.bold),
                          items: _cervezasDisponibles.map((c) {
                            return DropdownMenuItem<int>(
                              value: c['id'] as int,
                              child: Text(c['nombre'] as String),
                            );
                          }).toList(),
                          onChanged: (val) {
                            if (val != null) setState(() => _selectedCervezaId = val);
                          },
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),

                // Insumo: Cerveza en Lata
                const Text('1. Latas Consumidas (Materia Prima)',
                    style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w600)),
                const SizedBox(height: 8),
                TextField(
                  controller: _latasController,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  style: const TextStyle(color: Colors.white, fontSize: 20),
                  decoration: InputDecoration(
                    filled: true,
                    fillColor: const Color(0xFF1E1E1E),
                    hintText: 'Ej. 24',
                    hintStyle: const TextStyle(color: Colors.white38),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  onChanged: (val) {
                    final d = double.tryParse(val) ?? 0.0;
                    ref.read(transformacionProvider.notifier).setCantidadInsumo(d);
                  },
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    _buildQuickButton('+6', () => _incrementarLatas(6)),
                    const SizedBox(width: 8),
                    _buildQuickButton('+12', () => _incrementarLatas(12)),
                    const SizedBox(width: 8),
                    _buildQuickButton('+24', () => _incrementarLatas(24)),
                  ],
                ),
                const SizedBox(height: 20),

                // Destino: Botellas Corona
                const Text('2. Botellas Corona Terminadas Obtenidas',
                    style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w600)),
                const SizedBox(height: 8),
                TextField(
                  controller: _botellasController,
                  keyboardType: TextInputType.number,
                  style: const TextStyle(color: Colors.white, fontSize: 20),
                  decoration: InputDecoration(
                    filled: true,
                    fillColor: const Color(0xFF1E1E1E),
                    hintText: 'Ej. 20',
                    hintStyle: const TextStyle(color: Colors.white38),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  onChanged: (val) {
                    final i = int.tryParse(val) ?? 0;
                    ref.read(transformacionProvider.notifier).setCantidadProducida(i);
                  },
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    _buildQuickButton('+6', () => _incrementarBotellas(6)),
                    const SizedBox(width: 8),
                    _buildQuickButton('+10', () => _incrementarBotellas(10)),
                    const SizedBox(width: 8),
                    _buildQuickButton('+20', () => _incrementarBotellas(20)),
                  ],
                ),
                const SizedBox(height: 20),

                // Bajas / Roturas
                const Text('3. Botellas Rotas / Dañadas (0% Comisión)',
                    style: TextStyle(color: Colors.white70, fontSize: 14)),
                const SizedBox(height: 6),
                TextField(
                  controller: _roturasController,
                  keyboardType: TextInputType.number,
                  style: const TextStyle(color: Colors.white, fontSize: 18),
                  decoration: InputDecoration(
                    filled: true,
                    fillColor: const Color(0xFF1E1E1E),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  onChanged: (val) {
                    final i = int.tryParse(val) ?? 0;
                    ref.read(transformacionProvider.notifier).setCantidadRoturas(i);
                  },
                ),
                const SizedBox(height: 28),

                // Botón Guardar
                ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF27AE60),
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  onPressed: state.isSubmitting
                      ? null
                      : () {
                          final turnoId = authState.turnoActivoId ?? 1;
                          ref.read(transformacionProvider.notifier).registrarRelleno(
                                turnoId: turnoId,
                                recetaId: 1, // Receta Corona
                                insumoOrigenId: _selectedCervezaId,
                                observaciones: 'Relleno Corona con insumo ID: $_selectedCervezaId',
                              );
                        },
                  child: state.isSubmitting
                      ? const CircularProgressIndicator(color: Colors.white)
                      : const Text('GUARDAR RELLENO CORONA',
                          style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, letterSpacing: 1.1)),
                ),
              ],
            ),
          ),

          // ----------------- TAB 2: DESTILADOS COMPUESTOS -----------------
          SingleChildScrollView(
            padding: const EdgeInsets.all(16.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Banner Destilados
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF4A148C), Color(0xFF8E24AA)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Producto Resultante:', style: TextStyle(color: Colors.white70, fontSize: 14)),
                          const Text('Johnnie Walker Red Label',
                              style: TextStyle(color: Colors.amberAccent, fontSize: 16, fontWeight: FontWeight.bold)),
                        ],
                      ),
                      const Divider(color: Colors.white24, height: 16),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text('Comisión Barman: ${_botellasRedLabel * 2} Bs',
                              style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.bold)),
                          Text('Total Insumo: ${(_cantBlackstone + _cantChancellor).toStringAsFixed(2)} btl',
                              style: const TextStyle(color: Colors.lightGreenAccent, fontSize: 14, fontWeight: FontWeight.bold)),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),

                // Insumo 1: Whisky Blackstone
                Card(
                  color: const Color(0xFF1E1E1E),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Whisky Blackstone 750ml (Insumo 1)',
                                style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15)),
                            Text('${_cantBlackstone.toStringAsFixed(2)} btl',
                                style: const TextStyle(color: Colors.amberAccent, fontWeight: FontWeight.bold, fontSize: 16)),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            _buildFractionButton('0.25 btl', () => setState(() => _cantBlackstone = 0.25)),
                            const SizedBox(width: 8),
                            _buildFractionButton('0.50 btl', () => setState(() => _cantBlackstone = 0.50)),
                            const SizedBox(width: 8),
                            _buildFractionButton('0.75 btl', () => setState(() => _cantBlackstone = 0.75)),
                            const SizedBox(width: 8),
                            _buildFractionButton('1.00 btl', () => setState(() => _cantBlackstone = 1.00)),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),

                // Insumo 2: Whisky Chancellor
                Card(
                  color: const Color(0xFF1E1E1E),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            const Text('Whisky Chancellor 750ml (Insumo 2)',
                                style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15)),
                            Text('${_cantChancellor.toStringAsFixed(2)} btl',
                                style: const TextStyle(color: Colors.amberAccent, fontWeight: FontWeight.bold, fontSize: 16)),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            _buildFractionButton('0.25 btl', () => setState(() => _cantChancellor = 0.25)),
                            const SizedBox(width: 8),
                            _buildFractionButton('0.50 btl', () => setState(() => _cantChancellor = 0.50)),
                            const SizedBox(width: 8),
                            _buildFractionButton('0.75 btl', () => setState(() => _cantChancellor = 0.75)),
                            const SizedBox(width: 8),
                            _buildFractionButton('1.00 btl', () => setState(() => _cantChancellor = 1.00)),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 20),

                // Destino: Botellas Red Label Obtenidas
                const Text('Botellas Red Label Terminadas Obtenidas',
                    style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w600)),
                const SizedBox(height: 8),
                Row(
                  children: [
                    IconButton(
                      icon: const Icon(Icons.remove_circle, color: Colors.redAccent, size: 32),
                      onPressed: _botellasRedLabel > 1 ? () => setState(() => _botellasRedLabel--) : null,
                    ),
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        decoration: BoxDecoration(
                          color: const Color(0xFF1E1E1E),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text(
                          '$_botellasRedLabel botella(s)',
                          textAlign: TextAlign.center,
                          style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold),
                        ),
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.add_circle, color: Colors.greenAccent, size: 32),
                      onPressed: () => setState(() => _botellasRedLabel++),
                    ),
                  ],
                ),
                const SizedBox(height: 24),

                // Botón Guardar Mezcla
                ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF8E24AA),
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  onPressed: _isSubmittingDestilado ? null : _guardarDestiladosCompuestos,
                  child: _isSubmittingDestilado
                      ? const CircularProgressIndicator(color: Colors.white)
                      : const Text('GUARDAR MEZCLA DESTILADOS',
                          style: TextStyle(fontSize: 17, fontWeight: FontWeight.bold, letterSpacing: 1.1)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildQuickButton(String label, VoidCallback onPressed) {
    return Expanded(
      child: OutlinedButton(
        style: OutlinedButton.styleFrom(
          foregroundColor: Colors.amberAccent,
          side: const BorderSide(color: Colors.amberAccent),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
        onPressed: onPressed,
        child: Text(label, style: const TextStyle(fontWeight: FontWeight.bold)),
      ),
    );
  }

  Widget _buildFractionButton(String label, VoidCallback onPressed) {
    return Expanded(
      child: OutlinedButton(
        style: OutlinedButton.styleFrom(
          foregroundColor: Colors.white70,
          side: const BorderSide(color: Colors.white24),
          padding: const EdgeInsets.symmetric(vertical: 8),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
        ),
        onPressed: onPressed,
        child: Text(label, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
      ),
    );
  }
}
