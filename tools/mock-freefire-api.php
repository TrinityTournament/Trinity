<?php
header('Content-Type: application/json; charset=utf-8');

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (!str_ends_with($path, '/bhau')) {
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint no simulado']);
    return;
}

$claveEsperada = getenv('FF_MOCK_KEY') ?: '';
if ($claveEsperada !== '' && ($_GET['key'] ?? '') !== $claveEsperada) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid key']);
    return;
}

$uid    = $_GET['uid']    ?? '';
$region = strtoupper($_GET['region'] ?? '');

if ($uid === '500500500') {
    http_response_code(500);
    echo json_encode(['error' => 'Fallo interno simulado']);
    return;
}

$jugadores = [
    '219110511' => [
        'basicInfo' => ['nickname' => 'Donato', 'level' => 68, 'accountId' => '219110511', 'region' => $region ?: 'BR', 'maxRank' => 'Gran Maestro', 'csMaxRank' => 'Heroico'],
        'clanBasicInfo' => ['clanName' => 'Trinity Squad', 'clanLevel' => 7, 'clanId' => '3000000001', 'memberNum' => 38, 'capacity' => 50],
    ],
    '100000001' => [
        'basicInfo' => ['nickname' => 'LoboSolitario', 'level' => 42, 'accountId' => '100000001', 'region' => $region ?: 'US', 'maxRank' => 'Diamante', 'csMaxRank' => 'Platino'],
    ],
];

if (!isset($jugadores[$uid])) {
    http_response_code(404);
    echo json_encode(['error' => 'Player not found']);
    return;
}
echo json_encode($jugadores[$uid]);
