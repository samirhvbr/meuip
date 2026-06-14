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

$ver = "1.0";
$last = "02/02/2023";

include('folder_default.php'); exit();

while ( $go != 1 ){
    $f_goasn = goasn();
    $go = $f_goasn['go'];
    if ( $go == "error" ){
        echo "db error!";
        exit();
    }
} 




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
        $ASN['pais'] = shell_exec("curl -s 'ip-api.com/csv/{$IP}?fields=country,countryCode,lang=en'");
        $ASN['regiao'] = shell_exec("curl -s 'ip-api.com/csv/{$IP}?fields=city,regionName,lang=en'");
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

/*
 * variavel
 */
$user_agent     =   $_SERVER['HTTP_USER_AGENT'];

/*
 * SEPARANDO O A INFORMACAO DO OS DO USUARIO
 */
function getOS() { 
    global $user_agent;
    $os_platform    =   "Unknown OS Platform";
    $os_array       =   array(
                            '/windows nt 10/i'     =>  'Windows 10',
                            '/windows nt 6.3/i'     =>  'Windows 8.1',
                            '/windows nt 6.2/i'     =>  'Windows 8',
                            '/windows nt 6.1/i'     =>  'Windows 7',
                            '/windows nt 6.0/i'     =>  'Windows Vista',
                            '/windows nt 5.2/i'     =>  'Windows Server 2003/XP x64',
                            '/windows nt 5.1/i'     =>  'Windows XP',
                            '/windows xp/i'         =>  'Windows XP',
                            '/windows nt 5.0/i'     =>  'Windows 2000',
                            '/windows me/i'         =>  'Windows ME',
                            '/win98/i'              =>  'Windows 98',
                            '/win95/i'              =>  'Windows 95',
                            '/win16/i'              =>  'Windows 3.11',
                            '/macintosh|mac os x/i' =>  'Mac OS X',
                            '/mac_powerpc/i'        =>  'Mac OS 9',
                            '/linux/i'              =>  'Linux',
                            '/ubuntu/i'             =>  'Ubuntu',
                            '/iphone/i'             =>  'iPhone',
                            '/ipod/i'               =>  'iPod',
                            '/ipad/i'               =>  'iPad',
                            '/android/i'            =>  'Android',
                            '/blackberry/i'         =>  'BlackBerry',
                            '/webos/i'              =>  'Mobile'
                        );

    foreach ($os_array as $regex => $value) { 
        if (preg_match($regex, $user_agent)) {
            $os_platform    =   $value;
        }
    }   
    return $os_platform;
}

/*
 * SEPARANDO A INFORMACAO DO BROWSER DO USUARIO
 */
function getBrowser() {
    global $user_agent;
    $browser        =   "Unknown Browser";
    $browser_array  =   array(
                            '/mobile/i'     =>  'Handheld Browser',
                            '/msie/i'       =>  'Internet Explorer',
                            '/firefox/i'    =>  'Firefox',
                            '/safari/i'     =>  'Safari',
                            '/chrome/i'     =>  'Chrome',
                            '/edge/i'       =>  'Edge',
                            '/opera/i'      =>  'Opera',
                            '/netscape/i'   =>  'Netscape',
                            '/maxthon/i'    =>  'Maxthon',
                            '/konqueror/i'  =>  'Konqueror'
                        );

    foreach ($browser_array as $regex => $value) { 
        if (preg_match($regex, $user_agent)) {
            $browser    =   $value;
        }
    }
    return $browser;
}

//$IP = $_SERVER['REMOTE_ADDR'];
$IP = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
$AS = ip_asn($IP);
$method = filter_input(INPUT_SERVER, 'REQUEST_METHOD');
$agent = getBrowser();

$end = microtime(true);
$final = $end - $start;
script_reg($start,$end,$final,$f_goasn['id']);
?>
<!DOCTYPE html>
<html>
<head>
	<title>BLUE3 - meuip.rs</title>
	<meta http-equiv="Content-Language" content="pt-br" />
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <link rel="canonical" href="https://blue3.com.br/" />
        <meta name="description" content="Provedor de Internet em Porto Alegre Blue3. Solução de Internet para Empresas em Porto Alegre/RS.">
        <meta name="keywords" content="internet para empresas, internet corporativa, porto alegre, rs.">
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

<div id="container">
    <table class="table">
        <caption><!-- descrição --></caption>
        <thead>
            <tr>
                <th colspan="2" id="title">SUA CONEXÃO</td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td id="t_left"><B>IP ADDRESS</B></td>
                <td id="t_right"><?php echo $IP; ?></td>
            </tr>
            <tr>
                <td id="t_left">ASN</td>
                <td id="t_right"><?php echo $AS['as']; ?></td>
            </tr>
            <tr>
                <td id="t_left">ASN CORP</td>
                <td id="t_right"><?php echo $AS['corp']; ?></td>
            </tr>
            
            <tr>
                <td id="t_left">METHOD</td>
                <td id="t_right"><?php echo $method; ?></td>
            </tr>
            <tr>
                <td id="t_left">AGENT</td>
                <td id="t_right"><?php echo "<p>{$agent}</p><p>{$user_agent}</p>"; ?></td>
            </tr>
            <tr>
                <td id="t_left">PAÍS</td>
                <td id="t_right"><?php echo $AS['pais']; ?></td>
            </tr>
            <tr>
                <td id="t_left">REGIÃO</td>
                <td id="t_right"><?php echo $AS['regiao']; ?></td>
            </tr>
        </tbody>
    </table>
    
    
    <table class="table">
        <caption><!-- descrição --></caption>
        <thead>
            <tr><th colspan="3" id="title" style="height: 50px;">&nbsp;</th></tr>
            <tr>
                <th colspan="3" id="title">DOCUMENTAÇÃO</td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td id="doc_left">IP ADDRESS</td>
                <td id="doc_center">curl meuip.rs/ip</td>
                <td id="doc_right"><?php echo $IP; ?></td>
            </tr>
            <tr>
                <td id="doc_left">ASN</td>
                <td id="doc_center">curl meuip.rs/asn</td>
                <td id="doc_right"><?php echo $AS['as']; ?></td>
            </tr>
            <tr>
                <td id="doc_left">ISP</td>
                <td id="doc_center">curl meuip.rs/isp</td>
                <td id="doc_right"><?php echo $AS['corp']; ?></td>
            </tr>
            <tr>
                <td id="doc_left">PAÍS</td>
                <td id="doc_center">curl meuip.rs/country</td>
                <td id="doc_right"><?php echo $AS['pais']; ?></td>
            </tr>
            <tr>
                <td id="doc_left">REGIÃO</td>
                <td id="doc_center">curl meuip.rs/region</td>
                <td id="doc_right"><?php echo $AS['regiao']; ?></td>
            </tr>
            <tr>
                <td id="doc_left">COORDENADAS</td>
                <td id="doc_center">curl meuip.rs/coord</td>
                <td id="doc_right"><?php echo "LAT / LONG" ?></td>
            </tr>
            <tr>
                <td id="doc_left"><B>TODOS OS DADOS</B></td>
                <td id="doc_center">curl meuip.rs/all</td>
                <td id="doc_right">ALL RETURN</td>
            </tr>
            
            <tr><th colspan="3" id="title" style="height: 50px;">&nbsp;</th></tr>
            
        </tbody>
        
    </table>
    <footer id="footer">BLUE3 - ©2023 &nbsp; &nbsp; &nbsp; <?php echo "Versão: {$ver} - Última Atualização: {$last}"; ?></footer>
</div>

</body>
</html>
