import 'package:sqflite/sqflite.dart';
import 'package:path/path.dart';

class SqliteHelper {
  static const String dbName = 'puntofrio_local.db';
  static const int dbVersion = 1;

  static Database? _database;

  static Future<Database> get database async {
    if (_database != null) return _database!;
    _database = await _initDatabase();
    return _database!;
  }

  static Future<Database> _initDatabase() async {
    final dbPath = await getDatabasesPath();
    final path = join(dbPath, dbName);

    return openDatabase(
      path,
      version: dbVersion,
      onCreate: (db, version) async {
        // 1. Cola de sincronización offline (Local-First Sync Queue)
        await db.execute('''
          CREATE TABLE sync_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            uuid TEXT UNIQUE NOT NULL,
            endpoint TEXT NOT NULL,
            method TEXT NOT NULL,
            payload TEXT NOT NULL,
            foto_local_path TEXT,
            reintentos INTEGER DEFAULT 0,
            estado TEXT DEFAULT 'pendiente',
            error_mensaje TEXT,
            created_at TEXT NOT NULL
          )
        ''');

        // 2. Turnos locales en barra
        await db.execute('''
          CREATE TABLE turnos_locales (
            id INTEGER PRIMARY KEY,
            sucursal_id INTEGER NOT NULL,
            barman_id INTEGER NOT NULL,
            tipo_turno TEXT NOT NULL,
            estado TEXT NOT NULL,
            fecha_apertura TEXT NOT NULL,
            codigo_recibo_cobro TEXT,
            sincronizado INTEGER DEFAULT 0
          )
        ''');

        // 3. Movimientos locales (Rellenos, cortes, bajas, ingresos)
        await db.execute('''
          CREATE TABLE movimientos_locales (
            uuid TEXT PRIMARY KEY,
            turno_id INTEGER NOT NULL,
            sucursal_id INTEGER NOT NULL,
            producto_id INTEGER NOT NULL,
            tipo_movimiento TEXT NOT NULL,
            cantidad REAL NOT NULL,
            foto_path TEXT,
            receta_id INTEGER,
            ratio_calculado REAL,
            fecha_movimiento TEXT NOT NULL,
            sincronizado INTEGER DEFAULT 0
          )
        ''');
      },
    );
  }

  static Future<int> encolarOperacion({
    required String uuid,
    required String endpoint,
    required String method,
    required String payload,
    String? fotoLocalPath,
  }) async {
    final db = await database;
    return db.insert(
      'sync_queue',
      {
        'uuid': uuid,
        'endpoint': endpoint,
        'method': method,
        'payload': payload,
        'foto_local_path': fotoLocalPath,
        'reintentos': 0,
        'estado': 'pendiente',
        'created_at': DateTime.now().toIso8601String(),
      },
      conflictAlgorithm: ConflictAlgorithm.ignore,
    );
  }

  static Future<List<Map<String, dynamic>>> obtenerPendientes() async {
    final db = await database;
    return db.query(
      'sync_queue',
      where: "estado IN ('pendiente', 'fallido') AND reintentos < 5",
      orderBy: 'id ASC',
      limit: 20,
    );
  }

  static Future<void> marcarSincronizado(int id) async {
    final db = await database;
    await db.update(
      'sync_queue',
      {'estado': 'sincronizado'},
      where: 'id = ?',
      whereArgs: [id],
    );
  }

  static Future<void> registrarFallo(int id, String error) async {
    final db = await database;
    await db.rawUpdate('''
      UPDATE sync_queue 
      SET reintentos = reintentos + 1, 
          estado = 'fallido', 
          error_mensaje = ? 
      WHERE id = ?
    ''', [error, id]);
  }
}
