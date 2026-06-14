<?php
/*
 * Samir Hanna Verza  (samir@blue3.com.br)
 * Criado em: 05/02/2023
 * 
 * last update: 05/02/2023
 * TABLEA:      none 
 * version 1.0
 * 
 */
$start = microtime(true);



/*
 * FUNÇÕES E SCRIPTS
 */
include('folder_default.php');


function ip_asn($IP) {
    $ASN['corp'] = '';
    $ASN['as'] = '';
    $ip_api = shell_exec("curl -s 'ip-api.com/csv/{$IP}?fields=status,message,as,lang=en'");
    $bool = $ip_api[0] . $ip_api[1] . $ip_api[2] . $ip_api[3] . $ip_api[4] . $ip_api[5] . $ip_api[6];
    if ($bool == 'success') {
        $AS = $ip_api[8] . $ip_api[9];
        if ($AS == "AS") {
            $ASN['as'] = $ip_api[8] . $ip_api[9] . $ip_api[10] . $ip_api[11] . $ip_api[12] . $ip_api[13] . $ip_api[14] . $ip_api[15];
            for ($x = 0; $x <= (strlen($ip_api)); $x++) {
                if ($x > 15) {
                    $ASN['corp'] .= $ip_api[$x];
                }
            }
            
        } else {
            $ASN['as'] = $ip_api[8] . $ip_api[9] . $ip_api[10] . $ip_api[11] . $ip_api[12] . $ip_api[13] . $ip_api[14];
            for ($x = 0; $x <= (strlen($ip_api)); $x++) {
                if ($x > 15)
                    $ASN['corp'] .= $ip_api[$x];
            }
        }
        /*
        $go = goasn();
        if ( $go = 1 )
            $ASN['pais'] = shell_exec("curl -s 'ip-api.com/csv/{$IP}?fields=country,countryCode,lang=en'");
        $go = goasn();
        if ( $go = 1 )
            $ASN['regiao'] = shell_exec("curl -s 'ip-api.com/csv/{$IP}?fields=city,regionName,lang=en'");
         * 
         */
        $ipinfo = '418dd6100df878';
        $reg = shell_exec("curl 'ipinfo.io/{$IP}/json?token={$ipinfo}'");
        $var = json_decode($reg);
        $ASN['regiao'] = $var->{"region"};
        $ASN['pais'] = $var->{"country"};
        $ASN['hostname'] = $var->{"hostname"};
    } else {
        $ipinfo = '418dd6100df878';
        $ipi = shell_exec("curl -s 'ipinfo.io/{$IP}/org?token={$ipinfo}'");
        $AS = $ipi[0] . $ipi[1];
        if ($AS == "AS") {
            if (is_numeric($ipi[7])) {
                $ASN['as'] = $ipi[0] . $ipi[1] . $ipi[2] . $ipi[3] . $ipi[4] . $ipi[5] . $ipi[6] . $ipi[7];
                for ($x = 0; $x <= (strlen($ip_api)); $x++) {
                    if ($x > 15)
                        $ASN['corp'] .= $ip_api[$x];
                }
            } else {
                $ASN['as'] = $ipi[0] . $ipi[1] . $ipi[2] . $ipi[3] . $ipi[4] . $ipi[5] . $ipi[6];
                for ($x = 0; $x <= (strlen($ip_api)); $x++) {
                    if ($x > 15)
                        $ASN['corp'] .= $ip_api[$x];
                }
            }
        }
    }
    return $ASN;
}


$go = goasn();
if ( $go['go'] == 1 )
    $AS = ip_asn($IP);

//$IP = $_SERVER['REMOTE_ADDR'];
$IP = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
$method = filter_input(INPUT_SERVER, 'REQUEST_METHOD');
$agent = getBrowser();

$end = microtime(true);
$final = $end - $start;
script_reg($start,$end,$final,$go['id']);
?>
<!DOCTYPE html>
<html>
<head>
	<title>BLUE3 - meuip.rs</title>
	<meta http-equiv="Content-Language" content="pt-br" />
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <link rel="canonical" href="https://blue3.com.br/" />
        <meta name="description" content="Descubra seu IP - Provedor de Internet em Porto Alegre Blue3. Solução de Internet para Empresas em Porto Alegre/RS.">
        <meta name="keywords" content="descubra o seu ip - BLUE3 internet para empresas, internet corporativa, porto alegre, rs.">
        <meta name="author" content="BLUE3">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="apple-touch-icon-precomposed" sizes="57x57" href="favicon/apple-touch-icon-57x57.png" />
        <link rel="apple-touch-icon-precomposed" sizes="114x114" href="favicon/apple-touch-icon-114x114.png" />
        <link rel="apple-touch-icon-precomposed" sizes="72x72" href="favicon/apple-touch-icon-72x72.png" />
        <link rel="apple-touch-icon-precomposed" sizes="144x144" href="favicon/apple-touch-icon-144x144.png" />
        <link rel="apple-touch-icon-precomposed" sizes="60x60" href="favicon/apple-touch-icon-60x60.png" />
        <link rel="apple-touch-icon-precomposed" sizes="120x120" href="favicon/apple-touch-icon-120x120.png" />
        <link rel="apple-touch-icon-precomposed" sizes="76x76" href="favicon/apple-touch-icon-76x76.png" />
        <link rel="apple-touch-icon-precomposed" sizes="152x152" href="favicon/apple-touch-icon-152x152.png" />
        <link rel="icon" type="image/png" href="favicon/favicon-196x196.png" sizes="196x196" />
        <link rel="icon" type="image/png" href="favicon/favicon-96x96.png" sizes="96x96" />
        <link rel="icon" type="image/png" href="favicon/favicon-32x32.png" sizes="32x32" />
        <link rel="icon" type="image/png" href="favicon/favicon-16x16.png" sizes="16x16" />
        <link rel="icon" type="image/png" href="favicon/favicon-128.png" sizes="128x128" />
        <meta name="application-name" content="&nbsp;"/>
        <meta name="msapplication-TileColor" content="#FFFFFF" />
        <meta name="msapplication-TileImage" content="favicon/mstile-144x144.png" />
        <meta name="msapplication-square70x70logo" content="favicon/mstile-70x70.png" />
        <meta name="msapplication-square150x150logo" content="favicon/mstile-150x150.png" />
        <meta name="msapplication-wide310x150logo" content="favicon/mstile-310x150.png" />
        <meta name="msapplication-square310x310logo" content="favicon/mstile-310x310.png" />
        
        <link rel="stylesheet" type="text/css" href="stylev1.css" />
</head>
<body style="margin: 0px 0px 0px 0px;">

<h1><?php echo $_SERVER['REMOTE_ADDR']; ?></h1>
<BR><BR>

</body>
</html>
