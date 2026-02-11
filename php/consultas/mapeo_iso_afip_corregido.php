<?php
/**
 * Mapeo de códigos de país CloudBeds (ISO 3166-1 alpha-2) a códigos AFIP
 *
 * Uso: $codigoAFIP = MAPEO_PAISES_ISO_AFIP[$codigoISO] ?? 298;
 * (298 = INDETERMINADO AMERICA como fallback)
 */

const MAPEO_PAISES_ISO_AFIP = [
    // === AMÉRICA ===
    'AR' => 200,  // ARGENTINA
    'BO' => 202,  // BOLIVIA
    'BR' => 203,  // BRASIL
    'CA' => 204,  // CANADA
    'CL' => 208,  // CHILE
    'CO' => 205,  // COLOMBIA
    'CR' => 206,  // COSTA RICA
    'CU' => 207,  // CUBA
    'DO' => 209,  // REPÚBLICA DOMINICANA (corregido)
    'EC' => 210,  // ECUADOR
    'SV' => 211,  // EL SALVADOR
    'US' => 212,  // ESTADOS UNIDOS
    'GT' => 213,  // GUATEMALA
    'GY' => 214,  // GUYANA
    'HT' => 215,  // HAITI
    'HN' => 216,  // HONDURAS
    'JM' => 217,  // JAMAICA
    'MX' => 218,  // MEXICO
    'NI' => 219,  // NICARAGUA
    'PA' => 220,  // PANAMA
    'PY' => 221,  // PARAGUAY
    'PE' => 222,  // PERU
    'PR' => 223,  // PUERTO RICO
    'TT' => 224,  // TRINIDAD Y TOBAGO
    'UY' => 225,  // URUGUAY
    'VE' => 226,  // VENEZUELA
    'SR' => 232,  // SURINAME
    'DM' => 233,  // DOMINICA (corregido)
    'LC' => 234,  // SANTA LUCIA
    'VC' => 235,  // SAN VICENTE Y LAS GRANADINAS
    'BZ' => 236,  // BELICE
    'AG' => 237,  // ANTIGUA Y BARBUDA
    'KN' => 238,  // S.CRISTOBAL Y NEVIS
    'BS' => 239,  // BAHAMAS
    'GD' => 240,  // GRENADA
    'AW' => 242,  // ARUBA
    'CW' => 244,  // CURAZAO
    'BB' => 201,  // BARBADOS

    // === EUROPA ===
    'AL' => 401,  // ALBANIA
    'AD' => 404,  // ANDORRA
    'AT' => 405,  // AUSTRIA
    'BE' => 406,  // BELGICA
    'BG' => 407,  // BULGARIA
    'DK' => 409,  // DINAMARCA (corregido)
    'ES' => 410,  // ESPAÑA (corregido)
    'FI' => 411,  // FINLANDIA
    'FR' => 412,  // FRANCIA (corregido)
    'GR' => 413,  // GRECIA
    'HU' => 414,  // HUNGRIA
    'IE' => 415,  // IRLANDA
    'IS' => 416,  // ISLANDIA
    'IT' => 417,  // ITALIA
    'LI' => 418,  // LIECHTENSTEIN
    'LU' => 419,  // LUXEMBURGO
    'MT' => 420,  // MALTA
    'MC' => 421,  // MONACO
    'NO' => 422,  // NORUEGA
    'NL' => 423,  // PAISES BAJOS
    'PL' => 424,  // POLONIA
    'PT' => 425,  // PORTUGAL
    'GB' => 426,  // REINO UNIDO
    'RO' => 427,  // RUMANIA (corregido)
    'SM' => 428,  // SAN MARINO
    'SE' => 429,  // SUECIA
    'CH' => 430,  // SUIZA
    'VA' => 431,  // VATICANO
    'CY' => 435,  // CHIPRE
    'TR' => 436,  // TURQUIA
    'DE' => 438,  // ALEMANIA
    'BY' => 439,  // BIELORRUSIA
    'EE' => 440,  // ESTONIA
    'LV' => 441,  // LETONIA
    'LT' => 442,  // LITUANIA
    'MD' => 443,  // MOLDAVIA
    'RU' => 444,  // RUSIA (corregido)
    'UA' => 445,  // UCRANIA
    'BA' => 446,  // BOSNIA HERZEGOVINA
    'HR' => 447,  // CROACIA
    'SK' => 448,  // ESLOVAQUIA
    'SI' => 449,  // ESLOVENIA
    'MK' => 450,  // MACEDONIA
    'CZ' => 451,  // REP. CHECA
    'ME' => 453,  // MONTENEGRO
    'RS' => 454,  // SERBIA

    // === ASIA ===
    'AF' => 301,  // AFGANISTAN
    'SA' => 302,  // ARABIA SAUDITA
    'BH' => 303,  // BAHREIN
    'MM' => 304,  // MYANMAR
    'BT' => 305,  // BUTAN
    'KH' => 306,  // CAMBODYA
    'LK' => 307,  // SRI LANKA
    'KP' => 308,  // COREA DEMOCRATICA
    'KR' => 309,  // COREA REPUBLICANA
    'CN' => 310,  // CHINA
    'PH' => 312,  // FILIPINAS
    'TW' => 313,  // TAIWAN
    'IN' => 315,  // INDIA
    'ID' => 316,  // INDONESIA
    'IQ' => 317,  // IRAK
    'IR' => 318,  // IRAN
    'IL' => 319,  // ISRAEL
    'JP' => 320,  // JAPON
    'JO' => 321,  // JORDANIA
    'QA' => 322,  // QATAR
    'KW' => 323,  // KUWAIT
    'LA' => 324,  // LAOS
    'LB' => 325,  // LIBANO
    'MY' => 326,  // MALASIA
    'MV' => 327,  // MALDIVAS
    'OM' => 328,  // OMAN
    'MN' => 329,  // MONGOLIA
    'NP' => 330,  // NEPAL
    'AE' => 331,  // EMIRATOS ARABES UNIDOS
    'PK' => 332,  // PAKISTÁN (agregado)
    'SG' => 333,  // SINGAPUR
    'SY' => 334,  // SIRIA
    'TH' => 335,  // THAILANDIA
    'VN' => 337,  // VIETNAM
    'HK' => 341,  // HONG KONG
    'MO' => 344,  // MACAO
    'BD' => 345,  // BANGLADESH
    'BN' => 346,  // BRUNEI
    'YE' => 348,  // YEMEN
    'AM' => 349,  // ARMENIA
    'AZ' => 350,  // AZERBAIJAN
    'GE' => 351,  // GEORGIA
    'KZ' => 352,  // KAZAJSTAN
    'KG' => 353,  // KIRGUIZISTAN
    'TJ' => 354,  // TAYIKISTAN
    'TM' => 355,  // TURKMENISTAN
    'UZ' => 356,  // UZBEKISTAN
    'PS' => 357,  // PALESTINA
    'TL' => 358,  // TIMOR ORIENTAL

    // === AFRICA ===
    'BF' => 101,  // BURKINA FASO
    'DZ' => 102,  // ARGELIA
    'BW' => 103,  // BOTSWANA
    'BI' => 104,  // BURUNDI
    'CM' => 105,  // CAMERUN
    'CF' => 107,  // REP. CENTROAFRICANA
    'CG' => 108,  // CONGO
    'CD' => 109,  // REP. DEM. CONGO (corregido)
    'CI' => 110,  // COSTA DE MARFIL
    'TD' => 111,  // CHAD
    'BJ' => 112,  // BENIN
    'EG' => 113,  // EGIPTO
    'GA' => 115,  // GABON
    'GM' => 116,  // GAMBIA
    'GH' => 117,  // GHANA
    'GN' => 118,  // GUINEA
    'GQ' => 119,  // GUINEA ECUATORIAL
    'KE' => 120,  // KENYA
    'LS' => 121,  // LESOTHO
    'LR' => 122,  // LIBERIA
    'LY' => 123,  // LIBIA
    'MG' => 124,  // MADAGASCAR
    'MW' => 125,  // MALAWI
    'ML' => 126,  // MALI
    'MA' => 127,  // MARRUECOS
    'MU' => 128,  // MAURICIO
    'MR' => 129,  // MAURITANIA
    'NE' => 130,  // NIGER
    'NG' => 131,  // NIGERIA (corregido)
    'ZW' => 132,  // ZIMBABWE
    'RW' => 133,  // RWANDA
    'SN' => 134,  // SENEGAL
    'SL' => 135,  // SIERRA LEONA
    'SO' => 136,  // SOMALIA (corregido)
    'SZ' => 137,  // SWAZILANDIA
    'TZ' => 139,  // TANZANIA
    'TG' => 140,  // TOGO
    'TN' => 141,  // TUNEZ
    'UG' => 142,  // UGANDA
    'ZM' => 144,  // ZAMBIA
    'AO' => 149,  // ANGOLA
    'CV' => 150,  // CABO VERDE
    'MZ' => 151,  // MOZAMBIQUE
    'SC' => 152,  // SEYCHELLES
    'DJ' => 153,  // DJIBOUTI
    'KM' => 155,  // COMORAS
    'GW' => 156,  // GUINEA BISSAU
    'ST' => 157,  // STO.TOME Y PRINCIPE
    'NA' => 158,  // NAMIBIA
    'ZA' => 159,  // SUDAFRICA
    'ER' => 160,  // ERITREA
    'ET' => 161,  // ETIOPIA
    'SD' => 162,  // SUDAN
    'SS' => 163,  // SUDAN DEL SUR (corregido)

    // === OCEANÍA ===
    'AU' => 501,  // AUSTRALIA
    'NR' => 503,  // NAURU
    'NZ' => 504,  // NUEVA ZELANDIA
    'VU' => 505,  // VANUATU
    'WS' => 506,  // SAMOA OCCIDENTAL
    'FJ' => 512,  // FIJI
    'PG' => 513,  // PAPUA NUEVA GUINEA (corregido)
    'KI' => 514,  // KIRIBATI
    'FM' => 515,  // MICRONESIA
    'PW' => 516,  // PALAU
    'TV' => 517,  // TUVALU
    'SB' => 518,  // ISLAS SALOMON
    'TO' => 519,  // TONGA
    'MH' => 520,  // ISLAS MARSHALL
    'MP' => 521,  // ISLAS MARIANAS

    // === TERRITORIOS ESPECIALES ===
    'BM' => 663,  // BERMUDAS
    'GI' => 665,  // GIBRALTAR
    'GL' => 666,  // GROENLANDIA
    'GU' => 667,  // GUAM
    'HK' => 668,  // HONG KONG (alternativo)
    'IM' => 676,  // ISLA DE MAN
    'KY' => 671,  // ISLAS CAIMAN
    'TC' => 678,  // ISLAS TURCAS Y CAICOS
    'VG' => 682,  // ISLAS VIRGENES BRITANICAS
    'VI' => 683,  // ISLAS VIRGENES USA
    'AS' => 695,  // SAMOA AMERICANA
    'CK' => 654,  // ISLAS COOK
    'PF' => 656,  // POLINESIA FRANCESA
];

/**
 * Obtiene el código AFIP a partir del código ISO del país
 *
 * @param string $codigoISO Código ISO 3166-1 alpha-2 (ej: "BR", "US", "CL")
 * @param int $default Código por defecto si no se encuentra (298 = INDETERMINADO AMERICA)
 * @return int Código AFIP del país
 */
function obtenerCodigoAFIP($codigoISO, $default = 298) {
    $iso = strtoupper(trim($codigoISO));
    return MAPEO_PAISES_ISO_AFIP[$iso] ?? $default;
}

/**
 * Verifica si un país es Argentina
 *
 * @param string $codigoISO Código ISO del país
 * @return bool
 */
function esArgentina($codigoISO) {
    return strtoupper(trim($codigoISO)) === 'AR';
}

/**
 * Verifica si un huésped es extranjero (no argentino)
 * Útil para determinar si aplica Factura T
 *
 * @param string $codigoISO Código ISO del país
 * @return bool
 */
function esExtranjero($codigoISO) {
    return !esArgentina($codigoISO);
}
