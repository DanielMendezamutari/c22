import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import '../../providers/cobro_provider.dart';

class ResumenCajeraScreen extends ConsumerStatefulWidget {
  final int turnoId;

  const ResumenCajeraScreen({super.key, required this.turnoId});

  @override
  ConsumerState<ResumenCajeraScreen> createState() => _ResumenCajeraScreenState();
}

class _ResumenCajeraScreenState extends ConsumerState<ResumenCajeraScreen>
    with SingleTickerProviderStateMixin {
  late Timer _clockTimer;
  DateTime _currentTime = DateTime.now();
  late AnimationController _pulseController;
  final ImagePicker _picker = ImagePicker();
  bool _isProcessingCobro = false;

  @override
  void initState() {
    super.initState();
    Future.microtask(() {
      ref.read(cobroProvider.notifier).cargarResumen(widget.turnoId);
    });

    _clockTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (mounted) {
        setState(() {
          _currentTime = DateTime.now();
        });
      }
    });

    _pulseController = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 1),
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _clockTimer.cancel();
    _pulseController.dispose();
    super.dispose();
  }

  Future<void> _iniciarFlujoCobro() async {
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF1E293B),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (modalCtx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.white24,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'Respaldo Obligatorio de Pago',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Seleccione cómo registrar el comprobante o foto del dinero en efectivo:',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.white70, fontSize: 13),
              ),
              const SizedBox(height: 20),
              ListTile(
                leading: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: Colors.green.withOpacity(0.2),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.camera_alt, color: Colors.greenAccent),
                ),
                title: const Text('Tomar Foto con Cámara', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
                subtitle: const Text('Fotografiar billetes o recibo impreso', style: TextStyle(color: Colors.white54, fontSize: 12)),
                onTap: () {
                  Navigator.of(modalCtx).pop();
                  _capturarFoto(ImageSource.camera);
                },
              ),
              const Divider(color: Colors.white12),
              ListTile(
                leading: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: Colors.blue.withOpacity(0.2),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.photo_library, color: Colors.cyanAccent),
                ),
                title: const Text('Subir Comprobante Digital', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
                subtitle: const Text('Captura de transferencia QR / Banco', style: TextStyle(color: Colors.white54, fontSize: 12)),
                onTap: () {
                  Navigator.of(modalCtx).pop();
                  _capturarFoto(ImageSource.gallery);
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _capturarFoto(ImageSource source) async {
    try {
      final XFile? foto = await _picker.pickImage(
        source: source,
        imageQuality: 70,
        maxWidth: 1200,
      );

      if (foto == null) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              backgroundColor: Colors.redAccent,
              content: Row(
                children: [
                  Icon(Icons.warning_amber_rounded, color: Colors.white),
                  SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Es obligatorio adjuntar la fotografía del efectivo o comprobante transferido.',
                      style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
              duration: Duration(seconds: 4),
            ),
          );
        }
        return;
      }

      if (mounted) {
        _mostrarDialogoConfirmacionFoto(foto);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            backgroundColor: Colors.redAccent,
            content: Text('Error al acceder a la cámara: $e'),
          ),
        );
      }
    }
  }

  void _mostrarDialogoConfirmacionFoto(XFile foto) {
    final state = ref.read(cobroProvider);

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogCtx) {
        return StatefulBuilder(
          builder: (ctx, setDialogState) {
            return AlertDialog(
              backgroundColor: const Color(0xFF161F30),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
              title: const Row(
                children: [
                  Icon(Icons.camera_alt, color: Colors.amberAccent),
                  SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Respaldo de Cobro',
                      style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold),
                    ),
                  ),
                ],
              ),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      height: 220,
                      width: double.infinity,
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: Colors.white24),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: Image.file(
                        File(foto.path),
                        fit: BoxFit.cover,
                      ),
                    ),
                    const SizedBox(height: 14),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFF0F172A),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Column(
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('Monto Liquidado:', style: TextStyle(color: Colors.white70)),
                              Text(
                                '${state.montoNetoPagarBs.toStringAsFixed(2)} Bs',
                                style: const TextStyle(color: Color(0xFFFFD700), fontWeight: FontWeight.bold, fontSize: 16),
                              ),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              const Text('Corona Rellenadas:', style: TextStyle(color: Colors.white70)),
                              Text(
                                '${state.totalUnidadesNetas} uds',
                                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 10),
                    const Text(
                      '¿La fotografía del dinero o comprobante es legible?',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: Colors.white54, fontSize: 13),
                    ),
                  ],
                ),
              ),
              actionsPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              actions: [
                TextButton.icon(
                  onPressed: _isProcessingCobro
                      ? null
                      : () {
                          Navigator.of(dialogCtx).pop();
                          _iniciarFlujoCobro();
                        },
                  icon: const Icon(Icons.refresh, color: Colors.white70),
                  label: const Text('Tomar otra', style: TextStyle(color: Colors.white70)),
                ),
                ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF10B981),
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  onPressed: _isProcessingCobro
                      ? null
                      : () async {
                          setDialogState(() => _isProcessingCobro = true);
                          try {
                            final bytes = await File(foto.path).readAsBytes();
                            final base64Image = 'data:image/jpeg;base64,${base64Encode(bytes)}';

                            final ok = await ref.read(cobroProvider.notifier).confirmarCobroRecibido(
                                  widget.turnoId,
                                  fotoComprobante: base64Image,
                                );

                            if (mounted) {
                              Navigator.of(dialogCtx).pop();
                              if (!ok && state.errorMessage != null) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(
                                    backgroundColor: Colors.redAccent,
                                    content: Text(state.errorMessage ?? 'Error al procesar cobro'),
                                  ),
                                );
                              }
                            }
                          } catch (e) {
                            if (mounted) {
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(backgroundColor: Colors.redAccent, content: Text('Error al enviar: $e')),
                              );
                            }
                          } finally {
                            if (mounted) {
                              setDialogState(() => _isProcessingCobro = false);
                            }
                          }
                        },
                  icon: _isProcessingCobro
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                        )
                      : const Icon(Icons.check, color: Colors.white),
                  label: Text(
                    _isProcessingCobro ? 'Procesando...' : 'Confirmar y Finalizar',
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                  ),
                ),
              ],
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(cobroProvider);
    final timeFormat = DateFormat('HH:mm:ss');
    final dateFormat = DateFormat('dd/MM/yyyy');

    return Scaffold(
      backgroundColor: const Color(0xFF0A0E17),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 24.0, vertical: 16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Cabecera con Reloj Anti-Captura en Vivo
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  IconButton(
                    icon: const Icon(Icons.arrow_back_ios, color: Colors.white70),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                    decoration: BoxDecoration(
                      color: const Color(0xFF161F30),
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: Colors.cyanAccent.withValues(alpha: 0.4), width: 1.5),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        FadeTransition(
                          opacity: _pulseController,
                          child: const Icon(Icons.fiber_manual_record, color: Colors.redAccent, size: 14),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          timeFormat.format(_currentTime),
                          style: const TextStyle(
                            color: Colors.cyanAccent,
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                            fontFamily: 'monospace',
                            letterSpacing: 1.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              // Datos del Turno y Barman
              Center(
                child: Column(
                  children: [
                    Text(
                      state.sucursal.toUpperCase(),
                      style: const TextStyle(
                        color: Colors.amberAccent,
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 2,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'BARMAN: ${state.barmanNombre}',
                      style: const TextStyle(color: Colors.white70, fontSize: 16, fontWeight: FontWeight.w600),
                    ),
                    Text(
                      dateFormat.format(_currentTime),
                      style: const TextStyle(color: Colors.white38, fontSize: 13),
                    ),
                  ],
                ),
              ),
              const Spacer(),

              // Monto Gigante para Cajera
              Center(
                child: Column(
                  children: [
                    const Text(
                      'TOTAL A PAGAR EN EFECTIVO',
                      style: TextStyle(color: Colors.white60, fontSize: 15, letterSpacing: 1.5, fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 10),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
                      decoration: BoxDecoration(
                        color: const Color(0xFF131D2E),
                        borderRadius: BorderRadius.circular(24),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.amber.withValues(alpha: 0.15),
                            blurRadius: 30,
                            spreadRadius: 2,
                          ),
                        ],
                      ),
                      child: Text(
                        '${state.montoNetoPagarBs.toStringAsFixed(2)} Bs',
                        style: const TextStyle(
                          color: Color(0xFFFFD700),
                          fontSize: 64,
                          fontWeight: FontWeight.w900,
                          letterSpacing: -1.0,
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      '${state.totalUnidadesNetas} Botellas Corona Rellenadas Netas',
                      style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                    if (state.deudaDescontadaBs > 0) ...[
                      const SizedBox(height: 6),
                      Text(
                        '(Descuento por faltantes previos: -${state.deudaDescontadaBs.toStringAsFixed(2)} Bs)',
                        style: const TextStyle(color: Colors.redAccent, fontSize: 14),
                      ),
                    ],
                  ],
                ),
              ),
              const Spacer(),

              // Pantalla de Recibo Bloqueado o Botón de Cobro con Foto Obligatoria
              if (state.yaCobrado) ...[
                Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    color: const Color(0xFF1A2E20),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: Colors.greenAccent, width: 2),
                  ),
                  child: Column(
                    children: [
                      const Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.check_circle, color: Colors.greenAccent, size: 28),
                          SizedBox(width: 8),
                          Text('TURNO COBRADO EXITOSAMENTE',
                              style: TextStyle(color: Colors.greenAccent, fontSize: 16, fontWeight: FontWeight.bold)),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Text(
                        'RECIBO: ${state.codigoRecibo ?? 'REC-CONFIRMADO'}',
                        style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w900, letterSpacing: 2),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Esta pantalla se bloqueará en ${state.segundosRestantes}s',
                        style: const TextStyle(color: Colors.white54, fontSize: 13),
                      ),
                    ],
                  ),
                ),
              ] else ...[
                ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF27AE60),
                    padding: const EdgeInsets.symmetric(vertical: 20),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                    elevation: 8,
                  ),
                  onPressed: state.isLoading ? null : _iniciarFlujoCobro,
                  icon: const Icon(Icons.camera_alt, color: Colors.white, size: 26),
                  label: state.isLoading
                      ? const CircularProgressIndicator(color: Colors.white)
                      : const Text(
                          'COBRO RECIBIDO / FINALIZAR TURNO',
                          style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900, letterSpacing: 1.2),
                        ),
                ),
              ],
              const SizedBox(height: 16),
            ],
          ),
        ),
      ),
    );
  }
}
