<?php

/*
 * The omnical/* providers the harness installs beside the core: their
 * tests' namespace, and what their tests need besides (require-dev).
 */
return [
    'openholidays' => ['Omnical\\Openholidays\\Tests\\'],
    'nager' => ['Omnical\\Nager\\Tests\\'],
    'caldav' => ['Omnical\\Caldav\\Tests\\', ['sabre/dav' => '^4.7']],
    'google' => ['Omnical\\Google\\Tests\\'],
    'microsoft' => ['Omnical\\Microsoft\\Tests\\'],
];
