<?php

declare(strict_types=1);

// Throwaway prototype: does shared field mapping make native JSON round trips safe?
require __DIR__ . '/bootstrap.php';

use Eventjet\Json\DecodeError;
use Eventjet\Json\Json;
use FieldPrototype\BrokenReference;
use FieldPrototype\Collision;
use FieldPrototype\Document;
use FieldPrototype\Defaults;
use FieldPrototype\ManualReference;
use FieldPrototype\MissingInterface;
use FieldPrototype\Properties;
use FieldPrototype\Reference;
use FieldPrototype\SameNameMissingInterface;
use FieldPrototype\SpecialNames;
use FieldPrototype\UnmappedSerializer;

$cases = [
    'omitted constructor defaults' => [Defaults::class, '{}'],
    'mixed mapped and ordinary parameters, explicit null' => [Defaults::class, '{"$ref":null,"count":9}'],
    'trait-backed reference' => [Reference::class, '{"$ref":"#/$defs/person"}'],
    'nested references' => [Document::class, '{"$id":"urn:example","links":[{"$ref":"#/$defs/person"}]}'],
    'inherited public properties' => [Properties::class, '{"$anchor":"node","$ref":null}'],
    'missing interface, omitted field' => [MissingInterface::class, '{}'],
    'same-name annotation still requires interface' => [SameNameMissingInterface::class, '{}'],
    'manual matching serializer' => [ManualReference::class, '{"$ref":"#/$defs/person"}'],
    'manual inconsistent serializer' => [BrokenReference::class, '{"$ref":"#/$defs/person"}'],
    'unmapped custom serializer remains banned' => [UnmappedSerializer::class, '{"ref":"example"}'],
    'duplicate wire name' => [Collision::class, '{}'],
    'numeric, empty and swapped names' => [SpecialNames::class, '{"0":"zero","":"empty","second":"A","first":"B"}'],
    'nested error path uses wire names' => [Document::class, '{"$id":"urn:example","links":[{"$ref":42}]}'],
    'PHP property name is not an input alias' => [Properties::class, '{"ref":"ignored","$ref":"accepted"}'],
];

function inspectCase(string $name, string $class, string $json): array
{
    $decoded = Json::decode($json, $class);
    $state = ['case' => $name, 'target' => $class, 'input' => json_decode($json)];
    if ($decoded instanceof DecodeError) {
        $state['error'] = $decoded->getMessage();
        return $state;
    }
    $state['PHP properties'] = get_object_vars($decoded);
    $encoded = json_encode($decoded, JSON_THROW_ON_ERROR);
    $state['native encoding'] = json_decode($encoded);
    $again = Json::decode($encoded, $class);
    $state['object round trip'] = $again == $decoded;
    $state['JSON round trip'] = json_decode($encoded) == json_decode($json);
    if ($again instanceof DecodeError) {
        $state['second decode error'] = $again->getMessage();
    }
    return $state;
}

if (in_array('--all', $argv, true)) {
    foreach ($cases as $name => [$class, $json]) {
        echo json_encode(inspectCase($name, $class, $json), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n";
    }
    exit;
}

$selected = array_key_first($cases);
while (true) {
    [$class, $json] = $cases[$selected];
    echo "\033[2J\033[H\033[1mFIELD MAPPING — THROWAWAY PROTOTYPE\033[0m\n";
    echo json_encode(inspectCase($selected, $class, $json), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), "\n\n";
    foreach (array_keys($cases) as $index => $name) {
        echo '[', $index + 1, '] ', $name, "\n";
    }
    echo "[q] quit   Choose a case: ";
    $line = fgets(STDIN);
    if ($line === false || trim($line) === 'q') {
        break;
    }
    $selected = array_keys($cases)[(int) trim($line) - 1] ?? $selected;
}
