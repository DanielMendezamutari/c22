import 'package:flutter/material.dart';

class BottleFractionSelector extends StatelessWidget {
  final double value;
  final ValueChanged<double> onChanged;
  final String productName;

  const BottleFractionSelector({
    super.key,
    required this.value,
    required this.onChanged,
    required this.productName,
  });

  int get enteros => value.floor();
  double get fraccion => double.parse((value - enteros).toStringAsFixed(2));

  void _updateValue(int newEnteros, double newFraccion) {
    final total = double.parse((newEnteros + newFraccion).toStringAsFixed(2));
    onChanged(total);
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
          const SizedBox(height: 14),

          // Selector de Unidades Enteras + Visual de Fracción
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              // Botón restar entero
              IconButton(
                style: IconButton.styleFrom(
                  backgroundColor: const Color(0xFF242E42),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                icon: const Icon(Icons.remove, color: Colors.white),
                onPressed: enteros > 0 ? () => _updateValue(enteros - 1, fraccion) : null,
              ),
              const SizedBox(width: 8),

              // Unidades Enteras
              Column(
                children: [
                  Text(
                    '$enteros',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 26,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const Text(
                    'Enteras',
                    style: TextStyle(color: Colors.white60, fontSize: 11),
                  ),
                ],
              ),
              const SizedBox(width: 8),

              // Botón sumar entero
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
    // Altura del líquido según la fracción (0.0, 0.25, 0.5, 0.75)
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
