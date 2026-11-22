<?php
/**
 * Unit test bootstrap: Composer autoloader plus minimal stand-ins for the
 * WordPress classes the plugin uses. WordPress functions are mocked per
 * test with Brain Monkey.
 */

define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require __DIR__ . '/stubs.php';
