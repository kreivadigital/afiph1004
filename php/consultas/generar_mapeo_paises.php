<?php
/**
 * Script para generar mapeo ISO -> AFIP
 * Usa la lista de países de AFIP y la cruza con códigos ISO estándar
 */

// Lista estándar ISO 3166-1 alpha-2 -> Nombre país (en español/inglés)
$iso_paises = [
    'AF' => ['AFGANISTAN', 'AFGHANISTAN'],
    'AL' => ['ALBANIA'],
    'DZ' => ['ARGELIA', 'ALGERIA'],
    'AD' => ['ANDORRA'],
    'AO' => ['ANGOLA'],
    'AG' => ['ANTIGUA Y BARBUDA', 'ANTIGUA AND BARBUDA'],
    'AR' => ['ARGENTINA'],
    'AM' => ['ARMENIA'],
    'AU' => ['AUSTRALIA'],
    'AT' => ['AUSTRIA'],
    'AZ' => ['AZERBAIJAN', 'AZERBAIYAN'],
    'BS' => ['BAHAMAS'],
    'BH' => ['BAHREIN', 'BAHRAIN'],
    'BD' => ['BANGLADESH'],
    'BB' => ['BARBADOS'],
    'BY' => ['BIELORRUSIA', 'BELARUS'],
    'BE' => ['BELGICA', 'BELGIUM'],
    'BZ' => ['BELICE', 'BELIZE'],
    'BJ' => ['BENIN'],
    'BT' => ['BUTAN', 'BHUTAN'],
    'BO' => ['BOLIVIA'],
    'BA' => ['BOSNIA HERZEGOVINA', 'BOSNIA AND HERZEGOVINA'],
    'BW' => ['BOTSWANA'],
    'BR' => ['BRASIL', 'BRAZIL'],
    'BN' => ['BRUNEI'],
    'BG' => ['BULGARIA'],
    'BF' => ['BURKINA FASO'],
    'BI' => ['BURUNDI'],
    'CV' => ['CABO VERDE', 'CAPE VERDE'],
    'KH' => ['CAMBODYA', 'CAMBODIA', 'KAMPUCHE'],
    'CM' => ['CAMERUN', 'CAMEROON'],
    'CA' => ['CANADA'],
    'CF' => ['REP. CENTROAFRICANA', 'CENTRAL AFRICAN'],
    'TD' => ['CHAD'],
    'CL' => ['CHILE'],
    'CN' => ['CHINA'],
    'CO' => ['COLOMBIA'],
    'KM' => ['COMORAS', 'COMOROS'],
    'CG' => ['CONGO'],
    'CD' => ['REP.DEMOCRAT.DEL CONGO', 'DEMOCRATIC REPUBLIC OF CONGO', 'ZAIRE'],
    'KP' => ['COREA DEMOCRATICA', 'NORTH KOREA'],
    'KR' => ['COREA REPUBLICANA', 'SOUTH KOREA', 'KOREA'],
    'CR' => ['COSTA RICA'],
    'CI' => ['COSTA DE MARFIL', 'IVORY COAST', "COTE D'IVOIRE"],
    'HR' => ['CROACIA', 'CROATIA'],
    'CU' => ['CUBA'],
    'CY' => ['CHIPRE', 'CYPRUS'],
    'CZ' => ['REP. CHECA', 'CZECH', 'CZECHIA'],
    'DK' => ['DINAMARCA', 'DENMARK'],
    'DJ' => ['DJIBOUTI'],
    'DM' => ['DOMINICA'],
    'DO' => ['REPUBLICA DOMINICANA', 'DOMINICAN REPUBLIC'],
    'EC' => ['ECUADOR'],
    'EG' => ['EGIPTO', 'EGYPT'],
    'SV' => ['EL SALVADOR'],
    'AE' => ['EMIRATOS ARABES UNIDOS', 'UNITED ARAB EMIRATES', 'UAE'],
    'ER' => ['ERITREA'],
    'EE' => ['ESTONIA'],
    'SZ' => ['SWAZILANDIA', 'ESWATINI', 'SWAZILAND'],
    'ET' => ['ETIOPIA', 'ETHIOPIA'],
    'FJ' => ['FIJI'],
    'FI' => ['FINLANDIA', 'FINLAND'],
    'FR' => ['FRANCIA', 'FRANCE'],
    'GA' => ['GABON'],
    'GM' => ['GAMBIA'],
    'GE' => ['GEORGIA'],
    'DE' => ['ALEMANIA', 'GERMANY'],
    'GH' => ['GHANA'],
    'GR' => ['GRECIA', 'GREECE'],
    'GD' => ['GRENADA', 'GRANADA'],
    'GT' => ['GUATEMALA'],
    'GN' => ['GUINEA'],
    'GW' => ['GUINEA BISSAU', 'GUINEA-BISSAU'],
    'GQ' => ['GUINEA ECUATORIAL', 'EQUATORIAL GUINEA'],
    'GY' => ['GUYANA'],
    'HT' => ['HAITI'],
    'HN' => ['HONDURAS'],
    'HK' => ['HONG KONG'],
    'HU' => ['HUNGRIA', 'HUNGARY'],
    'IS' => ['ISLANDIA', 'ICELAND'],
    'IN' => ['INDIA'],
    'ID' => ['INDONESIA'],
    'IR' => ['IRAN'],
    'IQ' => ['IRAK', 'IRAQ'],
    'IE' => ['IRLANDA', 'IRELAND'],
    'IL' => ['ISRAEL'],
    'IT' => ['ITALIA', 'ITALY'],
    'JM' => ['JAMAICA'],
    'JP' => ['JAPON', 'JAPAN'],
    'JO' => ['JORDANIA', 'JORDAN'],
    'KZ' => ['KAZAJSTAN', 'KAZAKHSTAN'],
    'KE' => ['KENYA', 'KENIA'],
    'KI' => ['KIRIBATI'],
    'KW' => ['KUWAIT'],
    'KG' => ['KIRGUIZISTAN', 'KYRGYZSTAN'],
    'LA' => ['LAOS'],
    'LV' => ['LETONIA', 'LATVIA'],
    'LB' => ['LIBANO', 'LEBANON'],
    'LS' => ['LESOTHO'],
    'LR' => ['LIBERIA'],
    'LY' => ['LIBIA', 'LIBYA'],
    'LI' => ['LIECHTENSTEIN'],
    'LT' => ['LITUANIA', 'LITHUANIA'],
    'LU' => ['LUXEMBURGO', 'LUXEMBOURG'],
    'MO' => ['MACAO', 'MACAU'],
    'MK' => ['MACEDONIA', 'NORTH MACEDONIA'],
    'MG' => ['MADAGASCAR'],
    'MW' => ['MALAWI'],
    'MY' => ['MALASIA', 'MALAYSIA'],
    'MV' => ['MALDIVAS', 'MALDIVES'],
    'ML' => ['MALI'],
    'MT' => ['MALTA'],
    'MH' => ['MARSHALL', 'ISLAS MARSHALL'],
    'MR' => ['MAURITANIA'],
    'MU' => ['MAURICIO', 'MAURITIUS'],
    'MX' => ['MEXICO'],
    'FM' => ['MICRONESIA'],
    'MD' => ['MOLDAVIA', 'MOLDOVA'],
    'MC' => ['MONACO'],
    'MN' => ['MONGOLIA'],
    'ME' => ['MONTENEGRO'],
    'MA' => ['MARRUECOS', 'MOROCCO'],
    'MZ' => ['MOZAMBIQUE'],
    'MM' => ['MYANMAR', 'BIRMANIA', 'BURMA'],
    'NA' => ['NAMIBIA'],
    'NR' => ['NAURU'],
    'NP' => ['NEPAL'],
    'NL' => ['PAISES BAJOS', 'NETHERLANDS', 'HOLANDA'],
    'NZ' => ['NUEVA ZELANDIA', 'NEW ZEALAND'],
    'NI' => ['NICARAGUA'],
    'NE' => ['NIGER'],
    'NG' => ['NIGERIA'],
    'NO' => ['NORUEGA', 'NORWAY'],
    'OM' => ['OMAN'],
    'PK' => ['PAKISTAN'],
    'PW' => ['PALAU', 'PATAU'],
    'PS' => ['PALESTINA', 'PALESTINE'],
    'PA' => ['PANAMA'],
    'PG' => ['PAPUA NUEVA GUINEA', 'PAPUA NEW GUINEA'],
    'PY' => ['PARAGUAY'],
    'PE' => ['PERU'],
    'PH' => ['FILIPINAS', 'PHILIPPINES'],
    'PL' => ['POLONIA', 'POLAND'],
    'PT' => ['PORTUGAL'],
    'PR' => ['PUERTO RICO'],
    'QA' => ['QATAR'],
    'RO' => ['RUMANIA', 'ROMANIA'],
    'RU' => ['RUSIA', 'RUSSIA'],
    'RW' => ['RWANDA', 'RUANDA'],
    'KN' => ['S.CRISTOBAL Y NEVIS', 'SAINT KITTS AND NEVIS'],
    'LC' => ['SANTA LUCIA', 'SAINT LUCIA'],
    'VC' => ['SAN VICENTE Y LAS GRANADINAS', 'SAINT VINCENT'],
    'WS' => ['SAMOA', 'SAMOA OCCIDENTAL'],
    'SM' => ['SAN MARINO'],
    'ST' => ['STO.TOME Y PRINCIPE', 'SAO TOME'],
    'SA' => ['ARABIA SAUDITA', 'SAUDI ARABIA'],
    'SN' => ['SENEGAL'],
    'RS' => ['SERBIA'],
    'SC' => ['SEYCHELLES'],
    'SL' => ['SIERRA LEONA', 'SIERRA LEONE'],
    'SG' => ['SINGAPUR', 'SINGAPORE'],
    'SK' => ['ESLOVAQUIA', 'SLOVAKIA'],
    'SI' => ['ESLOVENIA', 'SLOVENIA'],
    'SB' => ['SALOMON', 'SOLOMON'],
    'SO' => ['SOMALIA'],
    'ZA' => ['SUDAFRICA', 'SOUTH AFRICA'],
    'SS' => ['SUDAN DEL SUR', 'SOUTH SUDAN'],
    'ES' => ['ESPAÑA', 'SPAIN', 'ESPANA'],
    'LK' => ['SRI LANKA'],
    'SD' => ['SUDAN'],
    'SR' => ['SURINAME', 'SURINAM'],
    'SE' => ['SUECIA', 'SWEDEN'],
    'CH' => ['SUIZA', 'SWITZERLAND'],
    'SY' => ['SIRIA', 'SYRIA'],
    'TW' => ['TAIWAN'],
    'TJ' => ['TAYIKISTAN', 'TAJIKISTAN'],
    'TZ' => ['TANZANIA'],
    'TH' => ['THAILANDIA', 'THAILAND', 'TAILANDIA'],
    'TL' => ['TIMOR ORIENTAL', 'TIMOR-LESTE', 'EAST TIMOR'],
    'TG' => ['TOGO'],
    'TO' => ['TONGA'],
    'TT' => ['TRINIDAD Y TOBAGO', 'TRINIDAD AND TOBAGO'],
    'TN' => ['TUNEZ', 'TUNISIA'],
    'TR' => ['TURQUIA', 'TURKEY'],
    'TM' => ['TURKMENISTAN'],
    'TV' => ['TUVALU'],
    'UG' => ['UGANDA'],
    'UA' => ['UCRANIA', 'UKRAINE'],
    'GB' => ['REINO UNIDO', 'UNITED KINGDOM', 'UK', 'GREAT BRITAIN'],
    'US' => ['ESTADOS UNIDOS', 'UNITED STATES', 'USA'],
    'UY' => ['URUGUAY'],
    'UZ' => ['UZBEKISTAN'],
    'VU' => ['VANATU', 'VANUATU'],
    'VA' => ['VATICANO', 'VATICAN'],
    'VE' => ['VENEZUELA'],
    'VN' => ['VIETNAM', 'VIET NAM'],
    'YE' => ['YEMEN'],
    'ZM' => ['ZAMBIA'],
    'ZW' => ['ZIMBABWE'],
    // Territorios especiales
    'AW' => ['ARUBA'],
    'CW' => ['CURAZAO', 'CURACAO'],
    'BM' => ['BERMUDAS', 'BERMUDA'],
    'KY' => ['ISLAS CAIMAN', 'CAYMAN'],
    'GI' => ['GIBRALTAR'],
    'GL' => ['GROENLANDIA', 'GREENLAND'],
    'GU' => ['GUAM'],
    'IM' => ['ISLA DE MAN', 'ISLE OF MAN'],
    'JE' => ['JERSEY'],
    'GG' => ['GUERNESEY', 'GUERNSEY'],
    'MP' => ['MARIANAS', 'NORTHERN MARIANA'],
    'AS' => ['SAMOA AMERICANA', 'AMERICAN SAMOA'],
    'VI' => ['ISLAS VIRGENES', 'VIRGIN ISLANDS'],
    'VG' => ['ISLAS VIRGENES BRITANICAS', 'BRITISH VIRGIN'],
    'TC' => ['ISLAS TURCAS Y CAICOS', 'TURKS AND CAICOS'],
    'PF' => ['POLINESIA FRANCESA', 'FRENCH POLYNESIA'],
    'NC' => ['NUEVA CALEDONIA', 'NEW CALEDONIA'],
    'CK' => ['ISLA DE COOK', 'COOK ISLANDS'],
];

// Cargar países de AFIP
$afipFile = __DIR__ . '/paises_afip.json';
if (!file_exists($afipFile)) {
    echo "ERROR: No existe el archivo paises_afip.json\n";
    echo "Ejecuta primero test_paises.php\n";
    exit(1);
}

$paises_afip = json_decode(file_get_contents($afipFile), true);

echo "=== GENERANDO MAPEO ISO -> AFIP ===\n\n";

$mapeo = [];
$no_encontrados = [];
$encontrados = 0;

// Para cada código ISO, buscar el país correspondiente en AFIP
foreach ($iso_paises as $iso => $nombres) {
    $encontrado = false;

    foreach ($paises_afip as $codigo_afip => $nombre_afip) {
        $nombre_afip_upper = strtoupper($nombre_afip);

        foreach ($nombres as $nombre_buscar) {
            $nombre_buscar_upper = strtoupper($nombre_buscar);

            // Buscar coincidencia exacta o parcial
            if ($nombre_afip_upper === $nombre_buscar_upper ||
                strpos($nombre_afip_upper, $nombre_buscar_upper) !== false ||
                strpos($nombre_buscar_upper, $nombre_afip_upper) !== false) {

                $mapeo[$iso] = [
                    'codigo_afip' => (int)$codigo_afip,
                    'nombre_afip' => $nombre_afip
                ];
                $encontrado = true;
                $encontrados++;
                break 2;
            }
        }
    }

    if (!$encontrado) {
        $no_encontrados[$iso] = $nombres[0];
    }
}

// Ordenar por código ISO
ksort($mapeo);

// Mostrar resultados
echo "=== MAPEO ENCONTRADO ($encontrados países) ===\n\n";
echo sprintf("%-5s | %-8s | %-50s\n", "ISO", "AFIP", "NOMBRE AFIP");
echo str_repeat("-", 70) . "\n";

foreach ($mapeo as $iso => $data) {
    echo sprintf("%-5s | %-8s | %-50s\n", $iso, $data['codigo_afip'], $data['nombre_afip']);
}

// Mostrar no encontrados
if (!empty($no_encontrados)) {
    echo "\n\n=== NO ENCONTRADOS (" . count($no_encontrados) . " países) ===\n";
    echo "(Requieren mapeo manual)\n\n";
    foreach ($no_encontrados as $iso => $nombre) {
        echo "$iso => $nombre\n";
    }
}

// Generar array PHP para usar en código
echo "\n\n=== ARRAY PHP PARA COPIAR ===\n\n";
echo "// Mapeo CloudBeds (ISO 3166-1 alpha-2) -> Código AFIP\n";
echo "\$MAPEO_PAISES_ISO_AFIP = [\n";
foreach ($mapeo as $iso => $data) {
    echo "    '$iso' => " . $data['codigo_afip'] . ",  // " . $data['nombre_afip'] . "\n";
}
echo "];\n";

// Guardar mapeo en JSON
$mapeoSimple = [];
foreach ($mapeo as $iso => $data) {
    $mapeoSimple[$iso] = $data['codigo_afip'];
}

file_put_contents(__DIR__ . '/mapeo_iso_afip.json', json_encode($mapeoSimple, JSON_PRETTY_PRINT));
echo "\n\nMapeo guardado en: mapeo_iso_afip.json\n";

echo "\n=== FIN ===\n";
