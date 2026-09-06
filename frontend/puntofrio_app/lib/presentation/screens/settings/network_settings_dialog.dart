import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../providers/auth_provider.dart';

class NetworkSettingsDialog extends ConsumerStatefulWidget {
  const NetworkSettingsDialog({super.key});

  @override
  ConsumerState<NetworkSettingsDialog> createState() => _NetworkSettingsDialogState();
}

class _NetworkSettingsDialogState extends ConsumerState<NetworkSettingsDialog> {
  late final TextEditingController _urlController;
  bool _isTesting = false;
  String? _testResult;
  bool _testSuccess = false;

  @override
  void initState() {
    super.initState();
    final config = ref.read(apiConfigProvider);
    _urlController = TextEditingController(text: config.baseUrl);
  }

  @override
  void dispose() {
    _urlController.dispose();
    super.dispose();
  }

  Future<void> _probarConexion() async {
    setState(() {
      _isTesting = true;
      _testResult = null;
    });

    final testUrl = _urlController.text.trim();
    final config = ref.read(apiConfigProvider);
    await config.setBaseUrl(testUrl);

    try {
      final client = ref.read(apiClientProvider);
      final res = await client.get('/auth/sucursales-usuarios');
      if (res.statusCode == 200) {
        setState(() {
          _isTesting = false;
          _testSuccess = true;
          _testResult = '✓ Conexión exitosa con el servidor';
        });
      } else {
        setState(() {
          _isTesting = false;
          _testSuccess = false;
          _testResult = '✗ Servidor respondió con código ${res.statusCode}';
        });
      }
    } catch (e) {
      setState(() {
        _isTesting = false;
        _testSuccess = false;
        _testResult = '✗ Error de red al conectar: $e';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      backgroundColor: const Color(0xFF1E2638),
      title: const Row(
        children: [
          Icon(Icons.settings_ethernet, color: Colors.amberAccent),
          SizedBox(width: 8),
          Text('Ajustes Técnicos de Red', style: TextStyle(color: Colors.white)),
        ],
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Configura la URL base de la API (Hosting compartido o IP local de contingencia):',
              style: TextStyle(color: Colors.white70, fontSize: 13),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _urlController,
              style: const TextStyle(color: Colors.white),
              decoration: InputDecoration(
                filled: true,
                fillColor: const Color(0xFF131D2E),
                hintText: 'http://192.168.0.7:81/api/v1',
                hintStyle: const TextStyle(color: Colors.white30),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [
                TextButton(
                  onPressed: () {
                    _urlController.text = 'http://10.0.2.2:8000/api/v1';
                  },
                  child: const Text('Emulador', style: TextStyle(color: Colors.cyanAccent)),
                ),
                TextButton(
                  onPressed: () {
                    _urlController.text = 'https://api.grupopuntofrio.com/api/v1';
                  },
                  child: const Text('Hosting Nube', style: TextStyle(color: Colors.cyanAccent)),
                ),
              ],
            ),
            if (_testResult != null) ...[
              const SizedBox(height: 8),
              Text(
                _testResult!,
                style: TextStyle(
                  color: _testSuccess ? Colors.greenAccent : Colors.redAccent,
                  fontWeight: FontWeight.bold,
                  fontSize: 13,
                ),
              ),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _isTesting ? null : _probarConexion,
          child: _isTesting
              ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
              : const Text('Probar Conexión', style: TextStyle(color: Colors.amberAccent)),
        ),
        ElevatedButton(
          style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF27AE60)),
          onPressed: () async {
            final config = ref.read(apiConfigProvider);
            await config.setBaseUrl(_urlController.text.trim());
            if (context.mounted) {
              Navigator.of(context).pop();
            }
          },
          child: const Text('Guardar'),
        ),
      ],
    );
  }
}
