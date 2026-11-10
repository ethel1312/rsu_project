<?php

// Run through migrate-tidb.ps1 from a terminal with network access.
require dirname(__DIR__).'/vendor/autoload.php';

function identifier(string $name): string { return '`'.str_replace('`', '``', $name).'`'; }
function fingerprint(array $rows): string {
    $hashes = [];
    foreach ($rows as $row) {
        $hashes[] = hash('sha256', serialize(array_map(fn ($v) => $v === null ? null : (string) $v, array_values($row))));
    }
    sort($hashes, SORT_STRING);
    return hash('sha256', implode('', $hashes));
}

try {
    $password = getenv('RSU_TIDB_PASSWORD');
    if (!$password) { throw new RuntimeException('Ejecuta scripts/migrate-tidb.ps1 para introducir la contraseña de forma privada.'); }
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if (config('database.default') !== 'mysql' || !in_array(config('database.connections.mysql.host'), ['localhost', '127.0.0.1'], true)) {
        throw new RuntimeException('La conexión de origen debe seguir siendo MySQL local. No se ha cambiado nada.');
    }
    $dir = storage_path('app/private/cloud-migration/'.date('Ymd-His'));
    if (!mkdir($dir, 0700, true) && !is_dir($dir)) { throw new RuntimeException('No se pudo crear el respaldo.'); }
    if (!copy(base_path('.env'), $dir.'/.env.local')) { throw new RuntimeException('No se pudo respaldar .env.'); }
    $source = Illuminate\Support\Facades\DB::connection()->getPdo();
    $source->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
    $source->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $source->beginTransaction();
    $tables = $source->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $manifest = [];
    foreach ($tables as $table) {
        $quoted = identifier($table);
        $ddl = $source->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM)[1];
        $rows = $source->query('SELECT * FROM '.$quoted)->fetchAll(PDO::FETCH_ASSOC);
        $file = $dir.'/'.bin2hex($table).'.bin';
        if (file_put_contents($file, serialize(['ddl' => $ddl, 'rows' => $rows])) === false) { throw new RuntimeException('Fallo al respaldar una tabla.'); }
        $manifest[$table] = ['file' => $file, 'count' => count($rows), 'hash' => fingerprint($rows)];
    }
    $source->commit();
    echo 'Respaldo local completo: '.count($tables)." tablas.\n";
    $ca = storage_path('app/private/tidb-isrgrootx1.pem');
    $certificate = file_get_contents('https://letsencrypt.org/certs/isrgrootx1.pem');
    if ($certificate === false || !openssl_x509_read($certificate) || file_put_contents($ca, $certificate) === false) {
        throw new RuntimeException('No se pudo descargar y validar el certificado CA oficial.');
    }
    $host = 'gateway01.sa-east-1.prod.aws.tidbcloud.com';
    $user = '3X7gkeC348hiLy8.root';
    $target = new PDO("mysql:host=$host;port=4000;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_STRINGIFY_FETCHES => true,
        PDO::MYSQL_ATTR_SSL_CA => $ca,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
    ]);
    $cipher = $target->query("SHOW STATUS LIKE 'Ssl_cipher'")->fetch(PDO::FETCH_NUM);
    if (empty($cipher[1])) { throw new RuntimeException('Conexión sin TLS: migración cancelada.'); }
    $target->exec('CREATE DATABASE IF NOT EXISTS `rsu_project` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $target->exec('USE `rsu_project`');
    if ($target->query('SHOW TABLES')->fetch()) { throw new RuntimeException('El destino ya contiene tablas. No se sobrescribe ningún dato; revisa la instancia antes de continuar.'); }
    $target->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($manifest as $table => $info) {
        $backup = unserialize(file_get_contents($info['file']), ['allowed_classes' => false]);
        $target->exec($backup['ddl']);
        if ($backup['rows']) {
            $columns = array_keys($backup['rows'][0]);
            $insert = $target->prepare('INSERT INTO '.identifier($table).' ('.implode(',', array_map('identifier', $columns)).') VALUES ('.implode(',', array_fill(0, count($columns), '?')).')');
            foreach ($backup['rows'] as $row) { $insert->execute(array_values($row)); }
        }
        $actual = $target->query('SELECT * FROM '.identifier($table))->fetchAll(PDO::FETCH_ASSOC);
        if (count($actual) !== $info['count'] || fingerprint($actual) !== $info['hash']) {
            throw new RuntimeException('La verificación del contenido falló en '.$table.'. La conexión local sigue intacta.');
        }
        echo $table.': '.$info['count']." filas verificadas\n";
    }
    $target->exec('SET FOREIGN_KEY_CHECKS=1');
    $env = file_get_contents(base_path('.env'));
    $values = ['DB_CONNECTION' => 'mysql', 'DB_HOST' => $host, 'DB_PORT' => '4000', 'DB_DATABASE' => 'rsu_project', 'DB_USERNAME' => $user, 'DB_PASSWORD' => $password, 'MYSQL_ATTR_SSL_CA' => str_replace('\\', '/', $ca)];
    foreach ($values as $key => $value) {
        $line = $key.'="'.str_replace(['\\', '"', '$', "\r", "\n"], ['\\\\', '\\"', '\\$', '\\r', '\\n'], $value).'"';
        $env = preg_match('/^'.preg_quote($key, '/').'=/m', $env)
            ? preg_replace_callback('/^'.preg_quote($key, '/').'=.*$/m', fn () => $line, $env)
            : $env."\n".$line."\n";
    }
    // A URL overrides individual DB_* values in Laravel.
    $env = preg_replace('/^DB_URL=.*$/m', 'DB_URL=', $env);
    if (file_put_contents(base_path('.env'), $env, LOCK_EX) === false) { throw new RuntimeException('Datos verificados, pero no se pudo actualizar .env.'); }
    Illuminate\Support\Facades\Artisan::call('config:clear');
    echo "Migración completada y verificada. Laravel configurado con TiDB y TLS.\nRespaldo privado: $dir\n";
} catch (Throwable $error) {
    // Do not print exception messages: drivers can include credentials or row data.
    fwrite(STDERR, 'No se completó la migración. Tipo: '.get_class($error).'. Código: '.$error->getCode().'. Línea: '.$error->getLine().".\n");
    if ($error instanceof RuntimeException && !($error instanceof PDOException)) { fwrite(STDERR, $error->getMessage()."\n"); }
    fwrite(STDERR, "No elimines el respaldo. No se borraron los datos de origen.\n");
    exit(1);
}
