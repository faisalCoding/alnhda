<?php

use Pdo\Mysql;

/**
 * The file is read again rather than through config(), whose values were
 * loaded before the test could listen. Production runs PHP 8.5, where the
 * old PDO::MYSQL_ATTR_* constants raise a deprecation on every load.
 */
it('loads the database config without a deprecation', function () {
    $deprecations = [];

    set_error_handler(function (int $level, string $message) use (&$deprecations): bool {
        $deprecations[] = $message;

        return true;
    }, E_DEPRECATED | E_USER_DEPRECATED);

    try {
        $config = require config_path('database.php');
    } finally {
        restore_error_handler();
    }

    expect($deprecations)->toBe([])
        ->and($config['connections']['mysql'])->toHaveKey('options')
        ->and($config['connections']['mariadb'])->toHaveKey('options');
});

it('still passes the mysql certificate through when one is set', function () {
    putenv('MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca.pem');

    try {
        $config = require config_path('database.php');
    } finally {
        putenv('MYSQL_ATTR_SSL_CA');
    }

    expect($config['connections']['mysql']['options'])->toBe([Mysql::ATTR_SSL_CA => '/etc/ssl/certs/ca.pem'])
        ->and($config['connections']['mariadb']['options'])->toBe([Mysql::ATTR_SSL_CA => '/etc/ssl/certs/ca.pem']);
})->skip(! extension_loaded('pdo_mysql'), 'needs the pdo_mysql extension');
