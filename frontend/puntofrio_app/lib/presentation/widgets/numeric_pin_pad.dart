import 'package:flutter/material.dart';

class NumericPinPad extends StatefulWidget {
  final ValueChanged<String> onPinCompleted;
  final ValueChanged<String>? onPinChanged;

  const NumericPinPad({
    super.key,
    required this.onPinCompleted,
    this.onPinChanged,
  });

  @override
  State<NumericPinPad> createState() => NumericPinPadState();
}

class NumericPinPadState extends State<NumericPinPad> {
  String _pin = '';

  void _onKeyPressed(String key) {
    if (_pin.length < 4) {
      setState(() {
        _pin += key;
      });
      widget.onPinChanged?.call(_pin);

      if (_pin.length == 4) {
        widget.onPinCompleted(_pin);
      }
    }
  }

  void _onBackspace() {
    if (_pin.isNotEmpty) {
      setState(() {
        _pin = _pin.substring(0, _pin.length - 1);
      });
      widget.onPinChanged?.call(_pin);
    }
  }

  void limpiar() {
    setState(() {
      _pin = '';
    });
    widget.onPinChanged?.call(_pin);
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        // Indicadores de 4 círculos
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: List.generate(4, (index) {
            final isFilled = index < _pin.length;
            return Container(
              margin: const EdgeInsets.symmetric(horizontal: 10),
              width: 18,
              height: 18,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: isFilled ? Colors.amberAccent : Colors.transparent,
                border: Border.all(
                  color: isFilled ? Colors.amberAccent : Colors.white38,
                  width: 2,
                ),
              ),
            );
          }),
        ),
        const SizedBox(height: 32),

        // Teclado Numérico 3x4
        _buildRow(['1', '2', '3']),
        const SizedBox(height: 14),
        _buildRow(['4', '5', '6']),
        const SizedBox(height: 14),
        _buildRow(['7', '8', '9']),
        const SizedBox(height: 14),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const SizedBox(width: 75), // Espacio vacío
            const SizedBox(width: 16),
            _buildButton('0'),
            const SizedBox(width: 16),
            SizedBox(
              width: 75,
              height: 75,
              child: IconButton(
                onPressed: _onBackspace,
                icon: const Icon(Icons.backspace_outlined, color: Colors.white70, size: 28),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildRow(List<String> keys) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: keys.map((k) {
        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8.0),
          child: _buildButton(k),
        );
      }).toList(),
    );
  }

  Widget _buildButton(String text) {
    return SizedBox(
      width: 75,
      height: 75,
      child: ElevatedButton(
        style: ElevatedButton.styleFrom(
          backgroundColor: const Color(0xFF1E2638),
          shape: const CircleBorder(),
          elevation: 4,
        ),
        onPressed: () => _onKeyPressed(text),
        child: Text(
          text,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 28,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
    );
  }
}
