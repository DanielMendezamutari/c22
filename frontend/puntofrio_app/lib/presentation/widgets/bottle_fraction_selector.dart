import 'package:flutter/material.dart';

class BottleFractionSelector extends StatelessWidget {
  final double value;
  final ValueChanged<double> onChanged;
  final String productName;
  final double? cantidadInicial;
  final double? ingresos;
  final double? totalDisponible;
  final bool esCierre;

  const BottleFractionSelector({
    super.key,
    required this.value,
    required this.onChanged,
    required this.productName,
    this.cantidadInicial,
    this.ingresos,
    this.totalDisponible,
    this.esCierre = false,
  });

  int get enteros => value.floor();
  double get fraccion => double.parse((value - enteros).toStringAsFixed(2));

  void _updateValue(int newEnteros, double newFraccion) {
    final clampEnteros = newEnteros < 0 ? 0 : newEnteros;
    final total = double.parse((clampEnteros + newFraccion).toStringAsFixed(2));
    onChanged(total);
  }

  void _incrementarEnteros(int delta) {
    final nuevo = (enteros + delta).clamp(0, 99999);
    _updateValue(nuevo, fraccion);
  }

  void _mostrarDialogoEdicionDirecta(BuildContext context) {
    final controller = TextEditingController(text: enteros.toString());
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF1B2332),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: Row(
          children: const [
            Icon(Icons.edit_note, color: Color(0xFF3498DB)),
            SizedBox(width: 8),
            Text(
              'Cantidad de Enteras',
              style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold),
            ),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              productName,
              style: const TextStyle(color: Colors.white70, fontSize: 13),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
            const SizedBox(height: 12),
            TextField(
              controller: controller,
              keyboardType: TextInputType.number,
              autofocus: true,
              style: const TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.bold),
              decoration: InputDecoration(
                filled: true,
                fillColor: const Color(0xFF121620),
                labelText: 'Unidades enteras (cajas/botellas)',
                labelStyle: const TextStyle(color: Colors.white60, fontSize: 13),
                hintText: 'Ej. 120, 360...',
                hintStyle: const TextStyle(color: Colors.white24),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                suffixIcon: IconButton(
                  icon: const Icon(Icons.clear, color: Colors.white38),
                  onPressed: () => controller.clear(),
                ),
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                _buildChipAtajoDialogo('+12', 12, controller),
                _buildChipAtajoDialogo('+24', 24, controller),
                _buildChipAtajoDialogo('+60', 60, controller),
                _buildChipAtajoDialogo('+120', 120, controller),
                _buildChipAtajoDialogo('+360', 360, controller),
              ],
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(ctx).pop(),
            child: const Text('Cancelar', style: TextStyle(color: Colors.white60)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF27AE60),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            onPressed: () {
              final val = int.tryParse(controller.text.trim());
              if (val != null) {
                _updateValue(val, fraccion);
              }
              Navigator.of(ctx).pop();
            },
            child: const Text('ACEPTAR', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
          ),
        ],
      ),
    );
  }

  Widget _buildChipAtajoDialogo(String label, int addVal, TextEditingController ctrl) {
    return ActionChip(
      backgroundColor: const Color(0xFF242E42),
      side: const BorderSide(color: Color(0xFF3498DB), width: 0.8),
      label: Text(label, style: const TextStyle(color: Colors.amberAccent, fontSize: 12, fontWeight: FontWeight.bold)),
      onPressed: () {
        final current = int.tryParse(ctrl.text.trim()) ?? 0;
        ctrl.text = (current + addVal).toString();
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(vertical: 8),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFF1B2332),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.white12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(
                  productName,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                    fontSize: 15,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFF2980B9).withOpacity(0.25),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: const Color(0xFF3498DB)),
                ),
                child: Text(
                  '${value.toStringAsFixed(2)} botellas',
                  style: const TextStyle(
                    color: Color(0xFF5DADE2),
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                  ),
                ),
              ),
            ],
          ),
          if (esCierre && cantidadInicial != null) ...[
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(
                color: const Color(0xFF141923),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.white12),
              ),
              child: Row(
                children: [
                  const Icon(Icons.info_outline, size: 14, color: Color(0xFF3498DB)),
                  const SizedBox(width: 6),
                  Expanded(
                    child: RichText(
                      text: TextSpan(
                        style: const TextStyle(fontSize: 12, color: Colors.white70),
                        children: [
                          const TextSpan(text: 'Inició: ', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.white60)),
                          TextSpan(text: cantidadInicial!.toStringAsFixed(2), style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
                          if ((ingresos ?? 0.0) > 0) ...[
                            const TextSpan(text: '  |  Ingresos: ', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.white60)),
                            TextSpan(text: '+${(ingresos ?? 0.0).toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF27AE60))),
                          ],
                          const TextSpan(text: '  |  Disp: ', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.white60)),
                          TextSpan(
                            text: (totalDisponible ?? (cantidadInicial! + (ingresos ?? 0.0))).toStringAsFixed(2),
                            style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF5DADE2)),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 6),
            Builder(builder: (ctx) {
              final disp = totalDisponible ?? (cantidadInicial! + (ingresos ?? 0.0));
              final salidaEstimada = double.parse((disp - value).toStringAsFixed(2));
              final esNegativo = salidaEstimada < 0;

              return Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  Icon(
                    !esNegativo ? Icons.trending_down : Icons.warning_amber_rounded,
                    size: 13,
                    color: !esNegativo ? Colors.amberAccent : const Color(0xFFE74C3C),
                  ),
                  const SizedBox(width: 4),
                  Text(
                    !esNegativo
                        ? 'Salida / Venta estimada: ${salidaEstimada.toStringAsFixed(2)} botellas'
                        : '¡Conteo mayor al disponible (+${(-salidaEstimada).toStringAsFixed(2)})!',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: !esNegativo ? Colors.amberAccent : const Color(0xFFE74C3C),
                    ),
                  ),
                ],
              );
            }),
          ],
          const SizedBox(height: 14),

          // Selector de Unidades Enteras + Visual de Fracción
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              // Botón restar 1 entero
              IconButton(
                style: IconButton.styleFrom(
                  backgroundColor: const Color(0xFF242E42),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                icon: const Icon(Icons.remove, color: Colors.white),
                onPressed: enteros > 0 ? () => _updateValue(enteros - 1, fraccion) : null,
              ),
              const SizedBox(width: 8),

              // Unidades Enteras (Toque para editar directamente con teclado numérico)
              InkWell(
                onTap: () => _mostrarDialogoEdicionDirecta(context),
                borderRadius: BorderRadius.circular(10),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                  decoration: BoxDecoration(
                    color: const Color(0xFF141923),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: const Color(0xFF3498DB).withOpacity(0.4), width: 1.5),
                  ),
                  child: Column(
                    children: [
                      Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            '$enteros',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 26,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(width: 4),
                          const Icon(Icons.edit, size: 14, color: Color(0xFF3498DB)),
                        ],
                      ),
                      const Text(
                        'Tocar para escribir',
                        style: TextStyle(color: Color(0xFF5DADE2), fontSize: 10, fontWeight: FontWeight.bold),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 8),

              // Botón sumar 1 entero
              IconButton(
                style: IconButton.styleFrom(
                  backgroundColor: const Color(0xFF242E42),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                icon: const Icon(Icons.add, color: Colors.white),
                onPressed: () => _updateValue(enteros + 1, fraccion),
              ),
              const Spacer(),

              // Gráfico de botella estilizada con nivel de líquido
              _buildBottleGraphic(fraccion),
            ],
          ),
          const SizedBox(height: 10),

          // Chips de Atajo Rápido por Caja (+6, +12, +24, -12)
          Row(
            children: [
              _buildShortcutChip('+6', () => _incrementarEnteros(6)),
              const SizedBox(width: 6),
              _buildShortcutChip('+12 (Caja)', () => _incrementarEnteros(12)),
              const SizedBox(width: 6),
              _buildShortcutChip('+24 (2 Cajas)', () => _incrementarEnteros(24)),
              const SizedBox(width: 6),
              _buildShortcutChip('-12', enteros >= 12 ? () => _incrementarEnteros(-12) : null, isNegative: true),
            ],
          ),
          const SizedBox(height: 14),

          // Botones de Fracción (0.00, 0.25, 0.50, 0.75)
          const Text(
            'Fracción de botella abierta:',
            style: TextStyle(color: Colors.white70, fontSize: 12, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              _buildFractionButton(0.00, '0 (Cero)'),
              const SizedBox(width: 6),
              _buildFractionButton(0.25, '1/4'),
              const SizedBox(width: 6),
              _buildFractionButton(0.50, '1/2'),
              const SizedBox(width: 6),
              _buildFractionButton(0.75, '3/4'),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildShortcutChip(String label, VoidCallback? onTap, {bool isNegative = false}) {
    return Expanded(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 6),
          decoration: BoxDecoration(
            color: onTap == null
                ? Colors.white10
                : isNegative
                    ? const Color(0xFFC0392B).withOpacity(0.2)
                    : const Color(0xFF27AE60).withOpacity(0.2),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
              color: onTap == null
                  ? Colors.white12
                  : isNegative
                      ? const Color(0xFFE74C3C)
                      : const Color(0xFF2ECC71),
              width: 1,
            ),
          ),
          child: Center(
            child: Text(
              label,
              style: TextStyle(
                color: onTap == null
                    ? Colors.white30
                    : isNegative
                        ? const Color(0xFFE74C3C)
                        : const Color(0xFF2ECC71),
                fontSize: 11,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildFractionButton(double fracValue, String label) {
    final isSelected = (fraccion - fracValue).abs() < 0.01;
    return Expanded(
      child: InkWell(
        onTap: () => _updateValue(enteros, fracValue),
        borderRadius: BorderRadius.circular(8),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: BoxDecoration(
            color: isSelected ? const Color(0xFF3498DB) : const Color(0xFF141923),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
              color: isSelected ? const Color(0xFF5DADE2) : Colors.white12,
              width: 1.5,
            ),
          ),
          child: Center(
            child: Text(
              label,
              style: TextStyle(
                color: isSelected ? Colors.white : Colors.white70,
                fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                fontSize: 13,
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildBottleGraphic(double frac) {
    final double fillPercent = frac == 0.0 ? 0.05 : frac;

    return Container(
      width: 48,
      height: 72,
      decoration: BoxDecoration(
        color: const Color(0xFF121620),
        borderRadius: const BorderRadius.only(
          topLeft: Radius.circular(8),
          topRight: Radius.circular(8),
          bottomLeft: Radius.circular(12),
          bottomRight: Radius.circular(12),
        ),
        border: Border.all(color: const Color(0xFF3498DB), width: 2),
      ),
      child: Stack(
        alignment: Alignment.bottomCenter,
        children: [
          // Nivel de líquido
          FractionallySizedBox(
            heightFactor: fillPercent,
            widthFactor: 1.0,
            child: Container(
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFFE67E22), Color(0xFFF39C12)],
                  begin: Alignment.bottomCenter,
                  end: Alignment.topCenter,
                ),
                borderRadius: BorderRadius.only(
                  bottomLeft: const Radius.circular(10),
                  bottomRight: const Radius.circular(10),
                  topLeft: fillPercent > 0.9 ? const Radius.circular(6) : Radius.zero,
                  topRight: fillPercent > 0.9 ? const Radius.circular(6) : Radius.zero,
                ),
              ),
            ),
          ),
          // Cuello estilizado de la botella arriba
          Positioned(
            top: 2,
            child: Container(
              width: 16,
              height: 8,
              decoration: BoxDecoration(
                color: Colors.amberAccent.withOpacity(0.8),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          // Indicador de fracción en el centro
          Center(
            child: Text(
              frac == 0.0
                  ? '-'
                  : frac == 0.25
                      ? '1/4'
                      : frac == 0.50
                          ? '1/2'
                          : '3/4',
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w900,
                fontSize: 12,
                shadows: [
                  Shadow(color: Colors.black87, blurRadius: 4),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
