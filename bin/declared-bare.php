<?php

/*
 * A declared provider in plain PHP, in a process of its own: Testing\
 * DeclaredBareTestCase's child. Arguments: the autoloader, the definition
 * file, the recorded answers as JSON ({"pattern": [status, "@file"]}), the
 * factory's options as JSON, the query as JSON (country, from, to, region,
 * language, types). Prints the holidays it gave and every class and file
 * PHP loaded, as JSON.
 */
[, $autoload, $definition, $routes, $options, $query] = $argv;
require $autoload;

$routes = json_decode($routes, true);
$recorded = [];
foreach (is_array($routes) ? $routes : [] as $pattern => $route) {
    if (is_array($route) && is_int($route[0] ?? null) && is_string($route[1] ?? null)) {
        $recorded[(string) $pattern] = [$route[0], $route[1]];
    }
}
$query = Omnical\Http\Data::of(json_decode($query, true));
$options = Omnical\Http\Data::of(json_decode($options, true));
$http = Omnical\Testing\RecordedHttpClient::routes($recorded);
$factory = Omnical\Declarative\DeclarativeFactory::fromFile($definition, $http);
$registry = new Omnical\Registry([$factory], ['declared' => ['factory' => $factory->getName(), 'options' => array_combine(array_map('strval', $options->keys()), array_values($options->toArray()))]]);
$holidays = $registry->holidays('declared')->holidays(new Omnical\Model\HolidayQuery(
    (string) $query->string('country'),
    new DateTimeImmutable((string) $query->string('from')),
    new DateTimeImmutable((string) $query->string('to')),
    $query->string('region'),
    $query->string('language'),
    array_map(Omnical\Model\HolidayType::from(...), $query->strings('types')),
));

echo json_encode([
    'holidays' => array_map(static fn (Omnical\Model\Holiday $h) => [$h->first->format('Y-m-d'), $h->name], $holidays),
    'requests' => array_map(static fn (array $r) => $r['method'].' '.$r['url'], $http->requests),
    'loaded' => [
        'symbols' => [...get_declared_classes(), ...get_declared_interfaces(), ...get_declared_traits()],
        'files' => array_map('realpath', get_included_files()),
        'eager' => array_map(static fn (mixed $file) => is_string($file) ? realpath($file) : null, array_values(is_array($eager = is_file($f = dirname((string) (new ReflectionClass(Composer\Autoload\ClassLoader::class))->getFileName()).'/autoload_files.php') ? require $f : []) ? $eager : [])),
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
