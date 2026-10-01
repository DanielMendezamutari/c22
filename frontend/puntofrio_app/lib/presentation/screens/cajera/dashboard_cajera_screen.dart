import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:printing/printing.dart';
import '../../providers/auth_provider.dart';
import '../auth/login_screen.dart';
import '../turnos/conteo_pdf_service.dart';
import '../turnos/corte_inventario_screen.dart';

class DashboardCajeraScreen extends ConsumerStatefulWidget {
  const DashboardCajeraScreen({super.key});

  @override
  ConsumerState<DashboardCajeraScreen> createState() => _DashboardCajeraScreenState();
}

class _DashboardCajeraScreenState extends ConsumerState<DashboardCajeraScreen> {
  bool _isLoading = false;
  Map<String, dynamic>? _turnoActivo;
  List<dynamic> _barmen = [];

  @override
  void initState() {
    super.initState();
    _cargarEstadoTurno();
    _cargarBarmen();
  }

  Future<void> _cargarEstadoTurno() async {
    setState(() => _isLoading = true);
    final auth = ref.read(authProvider);
    final apiClient = ref.read(apiClientProvider);

    try {
      final sucursalId = auth.sucursalId ?? 1;
      final res = await apiClient.get('/turnos/activo?sucursal_id=$sucursalId');
      if (res.statusCode == 200 && res.data['success'] == true) {
        setState(() {
          _turnoActivo = res.data['data'];
        });
      }
    } catch (_) {
      // Ignorar o mantener null
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _cargarBarmen() async {
    final apiClient = ref.read(apiClientProvider);
    try {
      final res = await apiClient.get('/usuarios');
      if (res.statusCode == 200 && res.data['success'] == true) {
        final usuarios = res.data['data'] as List<dynamic>;
        setState(() {
          _barmen = usuarios.where((u) => u['rol'] == 'barman' && (u['activo'] == true || u['activo'] == 1)).toList();
        });
      }
    } catch (_) {}
  }

  Future<void> _abrirSuplenciaApertura() async {
    if (_barmen.isEmpty) {
      await _cargarBarmen();
    }

    if (!mounted) return;

    int? barmanSeleccionadoId;
    if (_barmen.isNotEmpty) {
      barmanSeleccionadoId = _barmen.first['id'] as int;
    }

    final barmanId = await showDialog<int>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setDialogState) {
            return AlertDialog(
              backgroundColor: const Color(0xFF1E2638),
              title: const Row(
                children: [
                  Icon(Icons.person_add_alt_1, color: Colors.amberAccent),
                  SizedBox(width: 8),
                  Text('Suplencia de Apertura', style: TextStyle(color: Colors.white, fontSize: 18)),
                ],
              ),
              content: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Seleccione al Barman titular que se encuentra ausente:',
                    style: TextStyle(color: Colors.white70, fontSize: 13),
                  ),
                  const SizedBox(height: 16),
                  if (_barmen.isEmpty)
                    const Text('No se encontraron barmen registrados.', style: TextStyle(color: Colors.redAccent))
                  else
                    DropdownButtonFormField<int>(
                      value: barmanSeleccionadoId,
                      dropdownColor: const Color(0xFF151C2C),
                      style: const TextStyle(color: Colors.white),
                      decoration: InputDecoration(
                        filled: true,
                        fillColor: const Color(0xFF26324A),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                      items: _barmen.map<DropdownMenuItem<int>>((b) {
                        return DropdownMenuItem<int>(
                          value: b['id'] as int,
                          child: Text('${b['nombre']} ${b['apellido'] ?? ''}'),
                        );
                      }).toList(),
                      onChanged: (val) {
                        setDialogState(() => barmanSeleccionadoId = val);
                      },
                    ),
                ],
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(ctx, null),
                  child: const Text('Cancelar', style: TextStyle(color: Colors.white54)),
                ),
                ElevatedButton(
                  style: ElevatedButton.styleFrom(backgroundColor: Colors.amberAccent, foregroundColor: Colors.black),
                  onPressed: barmanSeleccionadoId != null ? () => Navigator.pop(ctx, barmanSeleccionadoId) : null,
                  child: const Text('Continuar a Conteo', style: TextStyle(fontWeight: FontWeight.bold)),
                ),
              ],
            );
          },
        );
      },
    );

    if (barmanId != null && mounted) {
      final res = await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => CorteInventarioScreen(
            esCierre: false,
            esSuplencia: true,
            barmanSuplidoId: barmanId,
          ),
        ),
      );
      if (res == true) {
        _cargarEstadoTurno();
      }
    }
  }

  Future<void> _auditarConteoPdf() async {
    final turnoId = _turnoActivo?['id'] as int?;
    if (turnoId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No hay turno activo para auditar.')),
      );
      return;
    }

    setState(() => _isLoading = true);
    final apiClient = ref.read(apiClientProvider);

    try {
      final res = await apiClient.get('/turnos/$turnoId/corte-inicial');
      if (res.statusCode == 200 && res.data['success'] == true) {
        final turnoData = res.data['data'];
        final pdfBytes = await ConteoPdfService.generarPdf(
          turnoData: turnoData,
          items: List<Map<String, dynamic>>.from(turnoData['items'] ?? []),
        );

        if (!mounted) return;
        await showDialog(
          context: context,
          builder: (ctx) => AlertDialog(
            backgroundColor: const Color(0xFF1E2638),
            title: const Text('Acta Oficial de Apertura en Vivo', style: TextStyle(color: Colors.white, fontSize: 16)),
            content: SizedBox(
              width: double.maxFinite,
              height: 480,
              child: PdfPreview(
                build: (format) => pdfBytes,
                canChangePageFormat: false,
                canChangeOrientation: false,
                actions: [
                  PdfPreviewAction(
                    icon: const Icon(Icons.share, color: Colors.greenAccent),
                    onPressed: (context, build, pageFormat) async {
                      await Printing.sharePdf(
                        bytes: pdfBytes,
                        filename: 'Acta_Apertura_Turno_$turnoId.pdf',
                      );
                    },
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx),
                child: const Text('Cerrar', style: TextStyle(color: Colors.amberAccent)),
              ),
            ],
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(backgroundColor: Colors.redAccent, content: Text('Error al cargar conteo: $e')),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _liquidarComisionesModal() async {
    final turnoId = _turnoActivo?['id'] as int?;
    if (turnoId == null) return;

    final comision = (_turnoActivo?['total_comision_bruta'] as num?)?.toDouble() ?? 0.0;
    final picker = ImagePicker();
    XFile? fotoSeleccionada;
    final obsController = TextEditingController();
    bool enviando = false;

    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: const Color(0xFF1B2332),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (bctx) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return Padding(
              padding: EdgeInsets.only(
                left: 20,
                right: 20,
                top: 20,
                bottom: MediaQuery.of(context).viewInsets.bottom + 20,
              ),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.monetization_on, color: Colors.greenAccent, size: 28),
                        const SizedBox(width: 10),
                        const Text(
                          'Desembolso de Comisiones',
                          style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                        ),
                        const Spacer(),
                        IconButton(
                          icon: const Icon(Icons.close, color: Colors.white54),
                          onPressed: () => Navigator.pop(bctx),
                        ),
                      ],
                    ),
                    const Divider(color: Colors.white12),
                    const SizedBox(height: 10),
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: Colors.greenAccent.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Colors.greenAccent.withValues(alpha: 0.3)),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Total Devengado en Turno:', style: TextStyle(color: Colors.white70, fontSize: 14)),
                          Text(
                            'Bs. ${comision.toStringAsFixed(2)}',
                            style: const TextStyle(color: Colors.greenAccent, fontSize: 20, fontWeight: FontWeight.w900),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    const Text(
                      'FOTOGRAFÍA OBLIGATORIA DEL COMPROBANTE/DINERO:',
                      style: TextStyle(color: Colors.amberAccent, fontSize: 12, fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 8),
                    InkWell(
                      onTap: () async {
                        final picked = await picker.pickImage(source: ImageSource.camera, maxWidth: 1200);
                        if (picked != null) {
                          setModalState(() => fotoSeleccionada = picked);
                        }
                      },
                      child: Container(
                        height: 140,
                        width: double.infinity,
                        decoration: BoxDecoration(
                          color: const Color(0xFF26324A),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(
                            color: fotoSeleccionada != null ? Colors.greenAccent : Colors.white24,
                            width: 1.5,
                          ),
                        ),
                        child: fotoSeleccionada != null
                            ? ClipRRect(
                                borderRadius: BorderRadius.circular(12),
                                child: Image.file(File(fotoSeleccionada!.path), fit: BoxFit.cover),
                              )
                            : const Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Icon(Icons.camera_alt, color: Colors.white54, size: 38),
                                  SizedBox(height: 6),
                                  Text('Tocar para capturar foto', style: TextStyle(color: Colors.white54, fontSize: 13)),
                                ],
                              ),
                      ),
                    ),
                    const SizedBox(height: 14),
                    TextField(
                      controller: obsController,
                      style: const TextStyle(color: Colors.white),
                      decoration: InputDecoration(
                        hintText: 'Observación opcional (ej. Pago turno noche)',
                        hintStyle: const TextStyle(color: Colors.white38),
                        filled: true,
                        fillColor: const Color(0xFF26324A),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                    ),
                    const SizedBox(height: 18),
                    SizedBox(
                      width: double.infinity,
                      height: 48,
                      child: ElevatedButton(
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.greenAccent,
                          foregroundColor: Colors.black,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        ),
                        onPressed: (fotoSeleccionada != null && !enviando)
                            ? () async {
                                setModalState(() => enviando = true);
                                try {
                                  final apiClient = ref.read(apiClientProvider);
                                  final formData = FormData.fromMap({
                                    'foto': await MultipartFile.fromFile(
                                      fotoSeleccionada!.path,
                                      filename: 'comprobante_$turnoId.jpg',
                                    ),
                                    'observacion': obsController.text.trim(),
                                  });

                                  final res = await apiClient.post(
                                    '/turnos/$turnoId/confirmar-pago-comision',
                                    data: formData,
                                  );

                                  if (res.statusCode == 200 && res.data['success'] == true) {
                                    if (context.mounted) {
                                      Navigator.pop(bctx);
                                      ScaffoldMessenger.of(context).showSnackBar(
                                        SnackBar(
                                          backgroundColor: Colors.green,
                                          content: Text(
                                            '✅ Desembolso confirmado: ${res.data['data']['codigo_recibo']}',
                                          ),
                                        ),
                                      );
                                      _cargarEstadoTurno();
                                    }
                                  }
                                } catch (err) {
                                  setModalState(() => enviando = false);
                                  if (context.mounted) {
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      SnackBar(backgroundColor: Colors.redAccent, content: Text('Error: $err')),
                                    );
                                  }
                                }
                              }
                            : null,
                        child: enviando
                            ? const CircularProgressIndicator(color: Colors.black)
                            : const Text('CONFIRMAR DESEMBOLSO Y SELLAR', style: TextStyle(fontWeight: FontWeight.bold)),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _cerrarSuplenciaCierre() async {
    final turnoId = _turnoActivo?['id'] as int?;
    if (turnoId == null) return;

    final res = await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => CorteInventarioScreen(
          esCierre: true,
          esSuplencia: true,
        ),
      ),
    );
    if (res == true) {
      _cargarEstadoTurno();
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);
    final tieneTurno = _turnoActivo != null && _turnoActivo?['estado'] != 'cerrado';

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
              style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: Colors.amberAccent),
            ),
            Text(
              'Cajera: ${auth.nombre ?? 'Operadora'}',
              style: const TextStyle(fontSize: 12, color: Colors.white70),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: Colors.white70),
            onPressed: _cargarEstadoTurno,
          ),
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
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Colors.amberAccent))
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Banner Estado de la Barra
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: tieneTurno
                            ? [const Color(0xFF1E3A8A), const Color(0xFF0F172A)]
                            : [const Color(0xFF374151), const Color(0xFF1F2937)],
                      ),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(
                        color: tieneTurno ? Colors.blueAccent.withValues(alpha: 0.5) : Colors.white12,
                      ),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(
                              tieneTurno ? Icons.check_circle : Icons.nightlife,
                              color: tieneTurno ? Colors.greenAccent : Colors.amberAccent,
                              size: 20,
                            ),
                            const SizedBox(width: 8),
                            Text(
                              tieneTurno ? 'BARRA OPERANDO (TURNO ACTIVO)' : 'BARRA SIN TURNO ACTIVO',
                              style: TextStyle(
                                color: tieneTurno ? Colors.greenAccent : Colors.amberAccent,
                                fontSize: 13,
                                fontWeight: FontWeight.bold,
                                letterSpacing: 1,
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 10),
                        if (tieneTurno) ...[
                          Text(
                            'Barman: ${_turnoActivo?['barman_nombre'] ?? 'Asignado'}',
                            style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w600),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Comisiones acumuladas: Bs. ${((_turnoActivo?['total_comision_bruta'] as num?)?.toDouble() ?? 0.0).toStringAsFixed(2)}',
                            style: const TextStyle(color: Colors.amberAccent, fontSize: 13),
                          ),
                        ] else
                          const Text(
                            'El barman no ha iniciado sesión o no se encuentra en el establecimiento.',
                            style: TextStyle(color: Colors.white70, fontSize: 13),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

                  const Text(
                    'OPERACIONES DE AUDITORÍA Y SUPLENCIA',
                    style: TextStyle(
                      color: Colors.white54,
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 1.1,
                    ),
                  ),
                  const SizedBox(height: 12),

                  // Tarjeta 1: Apertura por Suplencia
                  _buildOptionCard(
                    title: 'APERTURA POR SUPLENCIA',
                    subtitle: 'Efectuar conteo inicial si el barman no asistió',
                    icon: Icons.assignment_add,
                    color: Colors.orangeAccent,
                    habilitado: !tieneTurno,
                    onTap: _abrirSuplenciaApertura,
                  ),
                  const SizedBox(height: 12),

                  // Tarjeta 2: Auditar Conteo de Barra en Vivo (PDF)
                  _buildOptionCard(
                    title: 'AUDITAR CONTEO DE BARRA (PDF)',
                    subtitle: 'Verificar inventario físico sin pedir el celular al barman',
                    icon: Icons.picture_as_pdf,
                    color: Colors.blueAccent,
                    habilitado: tieneTurno,
                    onTap: _auditarConteoPdf,
                  ),
                  const SizedBox(height: 12),

                  // Tarjeta 3: Liquidar Comisiones Devengadas
                  _buildOptionCard(
                    title: 'LIQUIDAR COMISIONES DE BARRA',
                    subtitle: 'Confirmar pago con fotografía obligatoria del dinero',
                    icon: Icons.monetization_on,
                    color: Colors.greenAccent,
                    habilitado: tieneTurno,
                    onTap: _liquidarComisionesModal,
                  ),
                  const SizedBox(height: 12),

                  // Tarjeta 4: Cierre por Suplencia
                  _buildOptionCard(
                    title: 'CIERRE DE TURNO EN SUPLENCIA',
                    subtitle: 'Cerrar el turno de barra si el barman se retiró',
                    icon: Icons.lock_clock,
                    color: Colors.redAccent,
                    habilitado: tieneTurno,
                    onTap: _cerrarSuplenciaCierre,
                  ),
                ],
              ),
            ),
    );
  }

  Widget _buildOptionCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required Color color,
    required bool habilitado,
    required VoidCallback onTap,
  }) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: habilitado ? onTap : null,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: habilitado ? const Color(0xFF1E2638) : const Color(0xFF161C28),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: habilitado ? color.withValues(alpha: 0.4) : Colors.white10,
              width: 1.2,
            ),
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: habilitado ? color.withValues(alpha: 0.15) : Colors.white.withValues(alpha: 0.05),
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, color: habilitado ? color : Colors.white30, size: 26),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: TextStyle(
                        color: habilitado ? Colors.white : Colors.white38,
                        fontSize: 14,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      subtitle,
                      style: TextStyle(
                        color: habilitado ? Colors.white70 : Colors.white24,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right, color: habilitado ? color : Colors.white24),
            ],
          ),
        ),
      ),
    );
  }
}
