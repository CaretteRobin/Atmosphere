<?php
// Atmosphere: page de synthese environnementale pour le choix voiture / alternatives.
// Respecte les contraintes webetu (file_get_contents + proxy) et XSL pour la meteo.

ini_set('display_errors', 0);
date_default_timezone_set('Europe/Paris');

$FALLBACK_LAT = 48.6937; // IUT Charlemagne (fallback si IP non geolocalisable)
$FALLBACK_LON = 6.1840;

$proxyOpts = [
    'http' => [
        'proxy' => 'tcp://127.0.0.1:8080',
        'request_fulluri' => true,
        'timeout' => 6
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false
    ]
];

function fetch_url(string $url): array
{
    if (!function_exists('curl_init')) {
        global $proxyOpts;
        $attempts = [
            ['mode' => 'proxy', 'opts' => $proxyOpts],
            ['mode' => 'direct', 'opts' => null]
        ];
        $last = null;
        foreach ($attempts as $attempt) {
            $http_response_header = null;
            $context = $attempt['opts'] ? stream_context_create($attempt['opts']) : null;
            $data = @file_get_contents($url, false, $context);
            $headers = $http_response_header ?? [];
            $status = null;
            foreach ($headers as $header) {
                if (preg_match('~^HTTP/\\d\\.\\d\\s+(\\d+)~i', $header, $m)) {
                    $status = (int) $m[1];
                    break;
                }
            }
            if ($data !== false && $status >= 200 && $status < 300) {
                return ['ok' => true, 'status' => $status, 'data' => $data, 'url' => $url, 'mode' => $attempt['mode']];
            }
            $last = ['ok' => false, 'status' => $status, 'error' => 'Lecture impossible', 'url' => $url, 'mode' => $attempt['mode']];
        }
        return $last ?? ['ok' => false, 'status' => null, 'error' => 'Echec requete', 'url' => $url, 'mode' => 'unknown'];
    }

    $attempts = [
        ['mode' => 'proxy', 'proxy' => '127.0.0.1:8080'],
        ['mode' => 'direct', 'proxy' => null]
    ];

    $lastError = null;
    foreach ($attempts as $attempt) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Atmosphere/1.0',
        ];
        if (!empty($attempt['proxy'])) {
            $opts[CURLOPT_PROXY] = $attempt['proxy'];
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($body === false || $status < 200 || $status >= 300) {
            $lastError = [
                'ok' => false,
                'status' => $status ?: null,
                'error' => $err ?: 'Code HTTP ' . $status,
                'url' => $finalUrl ?: $url,
                'mode' => $attempt['mode']
            ];
            continue;
        }

        return [
            'ok' => true,
            'status' => $status,
            'data' => $body,
            'url' => $finalUrl ?: $url,
            'mode' => $attempt['mode']
        ];
    }

    return $lastError ?? [
        'ok' => false,
        'status' => null,
        'error' => 'Echec requete',
        'url' => $url,
        'mode' => 'unknown'
    ];
}

function fetch_url_post(string $url, array $fields): array
{
    if (!function_exists('curl_init')) {
        global $proxyOpts;
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($fields),
                'proxy' => $proxyOpts['http']['proxy'] ?? '',
                'request_fulluri' => $proxyOpts['http']['request_fulluri'] ?? true,
                'timeout' => 8
            ],
            'ssl' => $proxyOpts['ssl']
        ]);
        $data = @file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        $status = null;
        foreach ($headers as $h) {
            if (preg_match('~^HTTP/\\d\\.\\d\\s+(\\d+)~i', $h, $m)) {
                $status = (int) $m[1];
                break;
            }
        }
        if ($data !== false && $status >= 200 && $status < 300) {
            return ['ok' => true, 'status' => $status, 'data' => $data, 'url' => $url . '?' . http_build_query($fields), 'mode' => 'proxy'];
        }
        return ['ok' => false, 'status' => $status, 'error' => 'Echec POST', 'url' => $url . '?' . http_build_query($fields), 'mode' => 'proxy'];
    }

    $attempts = [
        ['mode' => 'proxy', 'proxy' => '127.0.0.1:8080'],
        ['mode' => 'direct', 'proxy' => null]
    ];
    $lastError = null;
    foreach ($attempts as $attempt) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Atmosphere/1.0',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
        ];
        if (!empty($attempt['proxy'])) {
            $opts[CURLOPT_PROXY] = $attempt['proxy'];
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        if ($body === false || $status < 200 || $status >= 300) {
            $lastError = [
                'ok' => false,
                'status' => $status ?: null,
                'error' => $err ?: 'Code HTTP ' . $status,
                'url' => ($finalUrl ?: $url) . '?' . http_build_query($fields),
                'mode' => $attempt['mode'],
                'body' => is_string($body) ? substr($body, 0, 200) : ''
            ];
            continue;
        }

        return [
            'ok' => true,
            'status' => $status,
            'data' => $body,
            'url' => ($finalUrl ?: $url) . '?' . http_build_query($fields),
            'mode' => $attempt['mode']
        ];
    }

    return $lastError ?? [
        'ok' => false,
        'status' => null,
        'error' => 'Echec requete POST',
        'url' => $url . '?' . http_build_query($fields),
        'mode' => 'unknown'
    ];
}

function geolocate_ip(string $ip): array
{
    $url = 'https://ipapi.co/' . urlencode($ip) . '/xml/';
    $resp = fetch_url($url);
    if (!$resp['ok']) {
        return [
            'ok' => false,
            'error' => $resp['error'] ?? 'API geoloc indisponible',
            'url' => $url
        ];
    }
    $xml = @simplexml_load_string($resp['data']);
    if (!$xml) {
        return [
            'ok' => false,
            'error' => 'Reponse XML invalide',
            'url' => $url
        ];
    }

    $lat = (float) ($xml->latitude ?? 0);
    $lon = (float) ($xml->longitude ?? 0);
    $city = (string) ($xml->city ?? '');
    $region = (string) ($xml->region ?? '');
    $country = (string) ($xml->country_name ?? '');
    $zip = (string) ($xml->postal ?? '');
    $deptCode = '';
    if (!empty($xml->region_code)) {
        $deptCode = substr((string) $xml->region_code, 0, 2);
    } elseif ($zip !== '') {
        $deptCode = substr($zip, 0, 2);
    }

    return [
        'ok' => true,
        'lat' => $lat,
        'lon' => $lon,
        'city' => $city,
        'region' => $region,
        'country' => $country,
        'departmentCode' => $deptCode,
        'provider' => 'ipapi.co',
        'url' => $url
    ];
}

function geocode_address(string $address): array
{
    $url = 'https://api-adresse.data.gouv.fr/search/?q=' . urlencode($address) . '&limit=1';
    $resp = fetch_url($url);
    if (!$resp['ok']) {
        return ['ok' => false, 'url' => $url, 'error' => $resp['error'] ?? ''];
    }
    $json = @json_decode($resp['data'], true);
    if (!$json || empty($json['features'][0]['geometry']['coordinates'])) {
        return ['ok' => false, 'url' => $url, 'error' => 'Reponse geocodage vide'];
    }
    $coords = $json['features'][0]['geometry']['coordinates'];
    $props = $json['features'][0]['properties'] ?? [];
    return [
        'ok' => true,
        'lon' => (float) $coords[0],
        'lat' => (float) $coords[1],
        'label' => $props['label'] ?? $address,
        'city' => $props['city'] ?? ($props['context'] ?? ''),
        'url' => $url
    ];
}

function build_weather_block(string $city, float $lat, float $lon): array
{
    $fallbackWeather = function (string $message) use ($city) {
        $summary = [
            ['label' => 'Matin', 'condition' => 'Eclaircies', 'icon' => '⛅', 'temp' => '12'],
            ['label' => 'Midi', 'condition' => 'Ensoleille', 'icon' => '☀️', 'temp' => '18'],
            ['label' => 'Soir', 'condition' => 'Averses possibles', 'icon' => '🌧️', 'temp' => '14']
        ];
        $customXml = "<?xml version=\"1.0\"?>\n";
        $customXml .= "<!DOCTYPE weather [\n";
        $customXml .= "<!ELEMENT weather (location, source, period+)>\n";
        $customXml .= "<!ELEMENT location (#PCDATA)>\n";
        $customXml .= "<!ELEMENT source (#PCDATA)>\n";
        $customXml .= "<!ELEMENT period (name, time, icon, condition, temp, precip, wind)>\n";
        $customXml .= "<!ELEMENT name (#PCDATA)>\n";
        $customXml .= "<!ELEMENT time (#PCDATA)>\n";
        $customXml .= "<!ELEMENT icon (#PCDATA)>\n";
        $customXml .= "<!ELEMENT condition (#PCDATA)>\n";
        $customXml .= "<!ELEMENT temp (#PCDATA)>\n";
        $customXml .= "<!ELEMENT precip (#PCDATA)>\n";
        $customXml .= "<!ELEMENT wind (#PCDATA)>\n";
        $customXml .= "]>\n";
        $customXml .= "<weather>\n";
        $customXml .= "  <location>" . htmlspecialchars($city) . "</location>\n";
        $customXml .= "  <source>Donnees meteo de secours (" . htmlspecialchars($message) . ")</source>\n";
        foreach ($summary as $item) {
            $customXml .= "  <period>\n";
            $customXml .= "    <name>" . htmlspecialchars($item['label']) . "</name>\n";
            $customXml .= "    <time></time>\n";
            $customXml .= "    <icon>" . htmlspecialchars($item['icon']) . "</icon>\n";
            $customXml .= "    <condition>" . htmlspecialchars($item['condition']) . "</condition>\n";
            $customXml .= "    <temp>" . htmlspecialchars($item['temp']) . "</temp>\n";
            $customXml .= "    <precip></precip>\n";
            $customXml .= "    <wind></wind>\n";
            $customXml .= "  </period>\n";
        }
        $customXml .= "</weather>";

        $xmlDoc = new DOMDocument();
        $xmlDoc->loadXML($customXml);
        $xsl = new DOMDocument();
        $xsl->load(__DIR__ . '/xsl/meteo.xsl');
        $proc = new XSLTProcessor();
        $proc->importStylesheet($xsl);

        return [
            'html' => $proc->transformToXML($xmlDoc),
            'periods' => $summary,
            'severity' => 1,
            'source' => 'Fallback meteo',
            'fallback' => true
        ];
    };

    $url = 'https://api.open-meteo.com/v1/forecast'
        . '?latitude=' . $lat
        . '&longitude=' . $lon
        . '&hourly=temperature_2m,precipitation,wind_speed_10m,weathercode'
        . '&forecast_days=1&timezone=Europe/Paris';

    $resp = fetch_url($url);
    if (!$resp['ok']) {
        return $fallbackWeather($resp['error'] ?? 'erreur reseau');
    }

    $json = @json_decode($resp['data'], true);
    if (!$json || empty($json['hourly']['time'])) {
        return $fallbackWeather('format meteo inattendu');
    }

    $times = $json['hourly']['time'];
    $temps = $json['hourly']['temperature_2m'] ?? [];
    $precips = $json['hourly']['precipitation'] ?? [];
    $winds = $json['hourly']['wind_speed_10m'] ?? [];
    $codes = $json['hourly']['weathercode'] ?? [];

    $targets = [
        ['id' => 'matin', 'label' => 'Matin', 'hour' => '08:00'],
        ['id' => 'midi', 'label' => 'Midi', 'hour' => '12:00'],
        ['id' => 'soir', 'label' => 'Soir', 'hour' => '18:00']
    ];

    $summary = [];
    $maxSeverity = 0;

    foreach ($targets as $target) {
        $index = null;
        foreach ($times as $idx => $iso) {
            $h = substr($iso, 11, 5);
            if ($h === $target['hour'] || $h > $target['hour']) {
                $index = $idx;
                break;
            }
        }
        if ($index === null) {
            $index = count($times) - 1;
        }

        $temp = isset($temps[$index]) ? round($temps[$index]) : null;
        $precip = isset($precips[$index]) ? (float) $precips[$index] : 0.0;
        $wind = isset($winds[$index]) ? (float) $winds[$index] : 0.0;
        $code = isset($codes[$index]) ? (int) $codes[$index] : 0;

        $icon = '☀️';
        $tone = 'Clair';
        $severity = 0;
        if (in_array($code, [1, 2, 3])) {
            $icon = '⛅';
            $tone = 'Nuageux';
        } elseif (in_array($code, [45, 48])) {
            $icon = '🌫️';
            $tone = 'Brouillard';
            $severity = 1;
        } elseif (in_array($code, [51, 53, 55, 56, 57])) {
            $icon = '🌧️';
            $tone = 'Bruine';
            $severity = 1;
        } elseif (in_array($code, [61, 63, 65, 66, 67, 80, 81, 82])) {
            $icon = '🌧️';
            $tone = 'Pluie';
            $severity = 2;
        } elseif (in_array($code, [71, 73, 75, 77, 85, 86])) {
            $icon = '❄️';
            $tone = 'Neige';
            $severity = 2;
        } elseif (in_array($code, [95, 96, 99])) {
            $icon = '⛈️';
            $tone = 'Orage';
            $severity = 2;
        }
        if ($precip > 5 || $wind > 50) {
            $severity = 2;
        } elseif ($precip > 0.2 || $wind > 35) {
            $severity = max($severity, 1);
        }
        if ($temp !== null && $temp < 0) {
            $severity = max($severity, 1);
        }
        $maxSeverity = max($maxSeverity, $severity);
        $summary[] = [
            'label' => $target['label'],
            'time' => $times[$index] ?? '',
            'condition' => $tone,
            'icon' => $icon,
            'temp' => $temp,
            'precip' => $precip,
            'wind' => $wind
        ];
    }

    $customXml = "<?xml version=\"1.0\"?>\n";
    $customXml .= "<!DOCTYPE weather [\n";
    $customXml .= "<!ELEMENT weather (location, source, period+)>\n";
    $customXml .= "<!ELEMENT location (#PCDATA)>\n";
    $customXml .= "<!ELEMENT source (#PCDATA)>\n";
    $customXml .= "<!ELEMENT period (name, time, icon, condition, temp, precip, wind)>\n";
    $customXml .= "<!ELEMENT name (#PCDATA)>\n";
    $customXml .= "<!ELEMENT time (#PCDATA)>\n";
    $customXml .= "<!ELEMENT icon (#PCDATA)>\n";
    $customXml .= "<!ELEMENT condition (#PCDATA)>\n";
    $customXml .= "<!ELEMENT temp (#PCDATA)>\n";
    $customXml .= "<!ELEMENT precip (#PCDATA)>\n";
    $customXml .= "<!ELEMENT wind (#PCDATA)>\n";
    $customXml .= "]>\n";
    $customXml .= "<weather>\n";
    $customXml .= "  <location>" . htmlspecialchars($city) . "</location>\n";
    $customXml .= "  <source>Open-Meteo (JSON → XML → XSL)</source>\n";
    foreach ($summary as $item) {
        $customXml .= "  <period>\n";
        $customXml .= "    <name>" . htmlspecialchars($item['label']) . "</name>\n";
        $customXml .= "    <time>" . htmlspecialchars($item['time']) . "</time>\n";
        $customXml .= "    <icon>" . htmlspecialchars($item['icon']) . "</icon>\n";
        $customXml .= "    <condition>" . htmlspecialchars($item['condition']) . "</condition>\n";
        $customXml .= "    <temp>" . htmlspecialchars((string) $item['temp']) . "</temp>\n";
        $customXml .= "    <precip>" . htmlspecialchars((string) $item['precip']) . "</precip>\n";
        $customXml .= "    <wind>" . htmlspecialchars((string) $item['wind']) . "</wind>\n";
        $customXml .= "  </period>\n";
    }
    $customXml .= "</weather>";

    $xmlDoc = new DOMDocument();
    $xmlDoc->loadXML($customXml);
    $xsl = new DOMDocument();
    $xsl->load(__DIR__ . '/xsl/meteo.xsl');
    $proc = new XSLTProcessor();
    $proc->importStylesheet($xsl);
    $html = $proc->transformToXML($xmlDoc);

    return [
        'html' => $html,
        'periods' => $summary,
        'severity' => $maxSeverity,
        'source' => $url
    ];
}

function fetch_traffic(float $lat, float $lon): array
{
    $url = 'https://carto.g-ny.eu/data/cifs/cifs_waze_v2.json';
    $resp = fetch_url($url);
    if ($resp['ok']) {
        $json = @json_decode($resp['data'], true);
        if ($json) {
            $records = $json['incidents'] ?? (is_array($json) ? $json : []);
            $items = [];
            foreach ($records as $rec) {
                $loc = $rec['location'] ?? $rec['geometry'] ?? null;
                $lonVal = null;
                $latVal = null;
                if (is_array($loc)) {
                    if (!empty($loc['polyline']) && is_string($loc['polyline'])) {
                        $parts = preg_split('~\\s+~', trim($loc['polyline']));
                        if (count($parts) >= 2) {
                            $latVal = (float) $parts[0];
                            $lonVal = (float) $parts[1];
                        }
                    }
                    if ($lonVal === null || $latVal === null) {
                        $lonVal = $loc['x'] ?? $loc['lon'] ?? ($loc[0] ?? null);
                        $latVal = $loc['y'] ?? $loc['lat'] ?? ($loc[1] ?? null);
                    }
                }
                if ($lonVal === null || $latVal === null) {
                    continue;
                }
                $items[] = [
                    'title' => $rec['event_type'] ?? $rec['type'] ?? 'Incident',
                    'description' => $rec['street'] ?? $rec['description'] ?? $rec['comment'] ?? 'Perturbation',
                    'start' => $rec['starttime'] ?? $rec['start_time'] ?? '',
                    'end' => $rec['endtime'] ?? $rec['end_time'] ?? '',
                    'lon' => (float) $lonVal,
                    'lat' => (float) $latVal
                ];
            }
            if (!empty($items)) {
                return ['ok' => true, 'items' => $items, 'url' => $resp['url'] ?? $url];
            }
        }
    }

    $items = [
        [
            'title' => 'Travaux',
            'description' => 'Chantier urbain',
            'start' => date('Y-m-d'),
            'end' => date('Y-m-d', strtotime('+3 days')),
            'lon' => $lon,
            'lat' => $lat
        ]
    ];
    return ['ok' => false, 'items' => $items, 'url' => $url, 'error' => $resp['error'] ?? 'Donnees trafic indisponibles (fallback)', 'fallback' => true];
}

function fetch_wastewater(string $deptCode): array
{
    $baseUrl = 'https://odisse.santepubliquefrance.fr/api/explore/v2.1/catalog/datasets/sum-eau-indicateurs/records';
    $where = 'commune="NANCY"';
    $paramsDesc = [
        'select' => 'semaine,date_complet,mesure,mesure_national',
        'where' => $where,
        'order_by' => 'semaine desc',
        'limit' => 100
    ];
    $paramsAsc = [
        'select' => 'semaine,date_complet,mesure,mesure_national',
        'where' => $where,
        'order_by' => 'semaine asc',
        'limit' => 100
    ];

    $records = [];
    $urls = [];
    foreach ([$paramsDesc, $paramsAsc] as $params) {
        $url = $baseUrl . '?' . http_build_query($params);
        $urls[] = $url;
        $resp = fetch_url($url);
        if (!$resp['ok']) {
            continue;
        }
        $json = @json_decode($resp['data'], true);
        if (!$json || empty($json['results'])) {
            continue;
        }
        foreach ($json['results'] as $row) {
            $week = $row['semaine'] ?? null;
            if (!$week) {
                continue;
            }
            $records[$week] = $row;
        }
    }

    if (empty($records)) {
        return [
            'ok' => false,
            'labels' => [],
            'values' => [],
            'trend' => 'indisponible',
            'url' => implode(' | ', $urls),
            'error' => 'Donnees SRAS indisponibles',
            'fallback' => true
        ];
    }

    ksort($records);
    $labels = [];
    $values = [];
    foreach ($records as $week => $row) {
        $labels[] = $week;
        $values[] = (float) ($row['mesure'] ?? $row['mesure_national'] ?? 0);
    }

    $trendText = 'stable';
    if (count($values) >= 3) {
        $diff1 = $values[count($values) - 1] - $values[count($values) - 2];
        $diff2 = $values[count($values) - 2] - $values[count($values) - 3];
        $avgDiff = ($diff1 + $diff2) / 2;
        if ($avgDiff > 5) {
            $trendText = 'en hausse';
        } elseif ($avgDiff < -5) {
            $trendText = 'en baisse';
        }
    }

    return [
        'ok' => true,
        'labels' => $labels,
        'values' => $values,
        'trend' => $trendText,
        'url' => implode(' | ', $urls)
    ];
}

function fetch_air_quality(string $city, string $deptCode): array
{
    $zoneRaw = $city !== '' ? $city : 'Nancy';
    $zoneClean = trim(preg_replace('/[^A-Za-zÀ-ÿ\\-\\s\']/', '', $zoneRaw));
    if ($zoneClean === '') {
        $zoneClean = 'Nancy';
    }
    $zones = [$zoneClean, 'Grand Nancy'];
    $base = 'https://services3.arcgis.com/Is0UwT37raQYl9Jj/arcgis/rest/services/ind_grandest/FeatureServer/0/query';

    foreach ($zones as $zone) {
        $fields = [
            'f' => 'pjson',
            'where' => "lib_zone='" . $zone . "'",
            'outFields' => 'lib_zone,lib_qual,coul_qual,code_qual,date_ech,date_dif,x_wgs84,y_wgs84',
            'returnGeometry' => 'false',
            'resultRecordCount' => 10
        ];

        $resp = fetch_url_post($base, $fields);
        if ($resp['ok']) {
            $json = @json_decode($resp['data'], true);
            if (!empty($json['features'][0]['attributes'])) {
                $attrs = $json['features'][0]['attributes'];
                $level = $attrs['code_qual'] ?? $attrs['valeur'] ?? $attrs['indice'] ?? null;
                $label = $attrs['lib_qual'] ?? $attrs['qualif'] ?? $attrs['indice_qualif'] ?? null;
                $dateRaw = $attrs['date_ech'] ?? $attrs['date_dif'] ?? '';
                $dateStr = '';
                if (is_numeric($dateRaw)) {
                    $dateStr = date('Y-m-d', $dateRaw / 1000);
                } else {
                    $dateStr = substr((string) $dateRaw, 0, 10);
                }

                if ($level === null) {
                    $level = 5;
                }
                if ($label === null) {
                    if ($level <= 3) {
                        $label = 'Bon';
                    } elseif ($level <= 6) {
                        $label = 'Moyen';
                    } else {
                        $label = 'Mauvais';
                    }
                }

                return [
                    'ok' => true,
                    'level' => (int) $level,
                    'label' => $label,
                    'date' => $dateStr,
                    'url' => $resp['url'] ?? ($base . '?' . http_build_query($fields))
                ];
            }
        }
    }

    return [
        'ok' => false,
        'level' => 5,
        'label' => 'Moyen',
        'date' => date('Y-m-d'),
        'url' => $base,
        'error' => 'Donnees air indisponibles pour la zone demandee',
        'fallback' => true
    ];
}

function compute_decision(array $air, array $covid, array $traffic, array $weather): array
{
    $score = 0;
    $reasons = [];

    if (($air['level'] ?? 0) >= 7) {
        $score -= 2;
        $reasons[] = 'Qualite de l\'air degradee';
    } elseif (($air['level'] ?? 0) >= 4) {
        $score -= 1;
        $reasons[] = 'Qualite de l\'air moyenne';
    } else {
        $score += 1;
    }

    if (($covid['trend'] ?? '') === 'en hausse') {
        $score -= 1;
        $reasons[] = 'Virus en hausse dans les eaux usees';
    }

    $trafficCount = count($traffic['items'] ?? []);
    if ($trafficCount > 15) {
        $score -= 2;
        $reasons[] = 'Beaucoup de perturbations trafic';
    } elseif ($trafficCount > 5) {
        $score -= 1;
    } else {
        $score += 1;
    }

    if (($weather['severity'] ?? 0) >= 2) {
        $score -= 1;
        $reasons[] = 'Meteo compliquee';
    }

    $label = 'acceptable';
    if ($score <= -1) {
        $label = 'deconseille';
    } elseif ($score >= 2) {
        $label = 'recommande';
    }

    return ['label' => $label, 'score' => $score, 'reasons' => $reasons];
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
$isPrivateIp = !filter_var($clientIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
$geoloc = $isPrivateIp ? ['ok' => false] : geolocate_ip($clientIp);
$geocodeIut = geocode_address('IUT Nancy-Charlemagne, boulevard Charlemagne, Nancy');
$geocodeGare = geocode_address('Gare de Nancy, Nancy');

$usingFallbackNancy = false;
if (!$geoloc['ok'] || stripos($geoloc['city'] ?? '', 'nancy') === false) {
    $geoloc = [
        'ok' => true,
        'lat' => $geocodeIut['ok'] ? $geocodeIut['lat'] : $GLOBALS['FALLBACK_LAT'],
        'lon' => $geocodeIut['ok'] ? $geocodeIut['lon'] : $GLOBALS['FALLBACK_LON'],
        'city' => 'Nancy',
        'region' => 'Grand Est',
        'country' => 'France',
        'departmentCode' => '54',
        'provider' => $geocodeIut['ok'] ? $geocodeIut['label'] : 'Fallback fixe IUT',
        'url' => $geocodeIut['ok'] ? $geocodeIut['url'] : ''
    ];
    $usingFallbackNancy = true;
}

$weather = build_weather_block($geoloc['city'] ?? 'Nancy', (float) ($geoloc['lat'] ?? 48.6921), (float) ($geoloc['lon'] ?? 6.1844));
$traffic = fetch_traffic((float) ($geoloc['lat'] ?? 48.6921), (float) ($geoloc['lon'] ?? 6.1844));
$covid = fetch_wastewater($geoloc['departmentCode'] ?? '54');
$air = fetch_air_quality($geoloc['city'] ?? 'Nancy', $geoloc['departmentCode'] ?? '54');

$decision = compute_decision($air, $covid, $traffic, $weather);
$lastUpdate = date('d/m/Y H:i');

$apiSources = [
    'Geolocalisation IP (ipapi.co)' => $geoloc['url'] ?? 'n/a',
    'Geocodage adresse (api-adresse)' => $geocodeIut['url'] ?? 'n/a',
    'Meteo (InfoClimat XML + XSL)' => $weather['source'] ?? 'n/a',
    'Trafic Grand Nancy (CIFS Waze)' => $traffic['url'] ?? 'n/a',
    'SRAS eaux usees (SUM\'eau)' => $covid['url'] ?? 'n/a',
    'Qualite de l\'air' => $air['url'] ?? 'n/a'
];

$repoLink = 'https://github.com/CaretteRobin/Atmosphere.git';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atmosphere - Mobilite responsable</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <link rel="icon" href="data:,">
</head>
<body>
    <header class="hero">
        <div>
            <p class="eyebrow">Projet Atmosphere</p>
            <h1>Voiture ou pas ?</h1>
            <p class="lead">Synthese meteo, trafic, sante et air pour guider vos deplacements.</p>
            <p class="meta">IP client : <?php echo htmlspecialchars($clientIp); ?> <?php echo $usingFallbackNancy ? '(localisation forcee sur Nancy)' : ''; ?></p>
            <p class="meta">Derniere mise a jour : <?php echo htmlspecialchars($lastUpdate); ?></p>
        </div>
        <div class="decision">
            <span class="pill pill-<?php echo htmlspecialchars($decision['label']); ?>">Aujourd'hui, utiliser sa voiture est plutot <?php echo htmlspecialchars($decision['label']); ?></span>
            <?php if (!empty($decision['reasons'])): ?>
                <ul>
                    <?php foreach ($decision['reasons'] as $reason): ?>
                        <li><?php echo htmlspecialchars($reason); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </header>

    <main class="content">
        <section class="grid two">
            <article class="card">
                <h2>Localisation</h2>
                <?php if ($geoloc['ok']): ?>
                    <p><?php echo htmlspecialchars($geoloc['city'] ?? ''); ?> (<?php echo htmlspecialchars($geoloc['country'] ?? ''); ?>)</p>
                    <p>Lat: <?php echo htmlspecialchars($geoloc['lat'] ?? ''); ?> / Lon: <?php echo htmlspecialchars($geoloc['lon'] ?? ''); ?></p>
                    <p class="source">Source : <?php echo htmlspecialchars($geoloc['provider'] ?? ''); ?></p>
                <?php else: ?>
                    <p class="error">Localisation indisponible.</p>
                <?php endif; ?>
            </article>
            <article class="card">
                <h2>Meteo</h2>
                <div class="meteo">
                    <?php echo $weather['html']; ?>
                </div>
                <p class="source">Source meteo : <?php echo htmlspecialchars($weather['source'] ?? ''); ?></p>
            </article>
        </section>

        <section class="grid two">
            <article class="card wide">
                <div class="card-header">
                    <div>
                        <p class="eyebrow">Grand Nancy</p>
                        <h2>Trafic et perturbations</h2>
                    </div>
                    <p class="meta"><?php echo count($traffic['items'] ?? []); ?> signalements</p>
                </div>
                <div id="map"></div>
                <?php if (!$traffic['ok']): ?>
                    <p class="error">Trafic indisponible : <?php echo htmlspecialchars($traffic['error'] ?? ''); ?></p>
                <?php elseif (!empty($traffic['fallback'])): ?>
                    <p class="meta warning">Donnees trafic de secours affichees.</p>
                <?php endif; ?>
                <p class="source">Source : <?php echo htmlspecialchars($traffic['url'] ?? ''); ?></p>
            </article>
            <article class="card chart-card">
                <p class="eyebrow">Sante</p>
                <h2>SRAS dans les eaux usees</h2>
                <div class="chart-wrap">
                    <canvas id="covidChart"></canvas>
                </div>
                <?php if (!$covid['ok']): ?>
                    <p class="status status-error">Donnees non disponibles : <?php echo htmlspecialchars($covid['error'] ?? ''); ?></p>
                <?php elseif (!empty($covid['fallback'])): ?>
                    <p class="status warning">Donnees de secours (serie courte).</p>
                <?php else: ?>
                    <p class="meta">Tendance : <?php echo htmlspecialchars($covid['trend'] ?? ''); ?></p>
                <?php endif; ?>
                <p class="source">Source : <a id="covidSource" href="<?php echo htmlspecialchars($covid['url'] ?? ''); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($covid['url'] ?? ''); ?></a></p>
            </article>
        </section>

        <section class="grid two">
            <article class="card">
                <p class="eyebrow">Air</p>
                <h2>Qualite de l'air</h2>
                <?php if ($air['ok']): ?>
                    <div class="air air-<?php echo strtolower($air['label']); ?>">
                        <p class="air-index"><?php echo htmlspecialchars($air['level'] ?? '?'); ?></p>
                        <p class="air-label"><?php echo htmlspecialchars($air['label']); ?></p>
                        <p class="meta">Mise a jour : <?php echo htmlspecialchars($air['date'] ?? ''); ?></p>
                    </div>
                    <?php if (!empty($air['fallback'])): ?>
                        <p class="meta warning">Indice de secours affiche (API ATMO indisponible).</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="error">Qualite indisponible : <?php echo htmlspecialchars($air['error'] ?? ''); ?></p>
                    <?php if (!empty($air['fallback'])): ?>
                        <p class="meta warning">Indice de secours affiche.</p>
                    <?php endif; ?>
                <?php endif; ?>
                <p class="source">Source : <?php echo htmlspecialchars($air['url'] ?? ''); ?></p>
            </article>
            <article class="card">
                <p class="eyebrow">Synthese</p>
                <h2>Utilisation de la voiture</h2>
                <p>En croisant meteo, trafic, air et tendance virale, la voiture est aujourd\'hui <strong><?php echo htmlspecialchars($decision['label']); ?></strong>.</p>
                <ul>
                    <li>Meteo : <?php echo $weather['severity'] >= 2 ? 'prudence' : 'RAS'; ?></li>
                    <li>Trafic : <?php echo count($traffic['items'] ?? []); ?> perturbations</li>
                    <li>Sante : <?php echo htmlspecialchars($covid['trend'] ?? ''); ?></li>
                    <li>Air : <?php echo htmlspecialchars($air['label'] ?? ''); ?></li>
                </ul>
                <p>Alternatives : transports en commun, velo en ville, covoiturage.</p>
            </article>
        </section>

        <section class="card sources">
            <h2>APIs utilisees</h2>
            <ul>
                <?php foreach ($apiSources as $label => $url): ?>
                    <li><strong><?php echo htmlspecialchars($label); ?> :</strong> <a href="<?php echo htmlspecialchars($url); ?>"><?php echo htmlspecialchars($url); ?></a></li>
                <?php endforeach; ?>
            </ul>
            <p>Depot Git : <a href="<?php echo htmlspecialchars($repoLink); ?>"><?php echo htmlspecialchars($repoLink); ?></a></p>
        </section>
    </main>

    <script>
        window.atmoData = {
            mapCenter: { lat: <?php echo json_encode((float) ($geoloc['lat'] ?? 48.6921)); ?>, lon: <?php echo json_encode((float) ($geoloc['lon'] ?? 6.1844)); ?> },
            traffic: <?php echo json_encode($traffic['items'] ?? []); ?>,
            wastewater: {
                ok: <?php echo json_encode($covid['ok'] ?? false); ?>,
                labels: <?php echo json_encode(($covid['ok'] ?? false) ? ($covid['labels'] ?? []) : []); ?>,
                values: <?php echo json_encode(($covid['ok'] ?? false) ? ($covid['values'] ?? []) : []); ?>,
                trend: <?php echo json_encode($covid['trend'] ?? ''); ?>
            },
            markers: [
                <?php if ($geoloc['ok']): ?>
                {lat: <?php echo json_encode((float) $geoloc['lat']); ?>, lon: <?php echo json_encode((float) $geoloc['lon']); ?>, label: 'Vous'},
                <?php endif; ?>
                <?php if ($geocodeIut['ok']): ?>
                {lat: <?php echo json_encode((float) $geocodeIut['lat']); ?>, lon: <?php echo json_encode((float) $geocodeIut['lon']); ?>, label: 'IUT Charlemagne'},
                <?php endif; ?>
                <?php if ($geocodeGare['ok']): ?>
                {lat: <?php echo json_encode((float) $geocodeGare['lat']); ?>, lon: <?php echo json_encode((float) $geocodeGare['lon']); ?>, label: 'Gare de Nancy'},
                <?php endif; ?>
            ]
        };
    </script>
    <script src="js/charts.js"></script>
</body>
</html>
