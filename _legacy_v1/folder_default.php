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
$ver = "1.0";
$last = "02/02/2023";



# Definindo Timezone
date_default_timezone_set('America/Sao_Paulo');

ini_set('session.referer_check', 'TRUE');



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











$IP = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
$method = filter_input(INPUT_SERVER, 'REQUEST_METHOD');

$cookie = "";
$fkn = 0;
foreach ($_COOKIE as $coo) {
    $fkn++;
    $cookie .= " R" . $fkn . "=>" . $coo;
}

$docroot = $_SERVER['DOCUMENT_ROOT'];
$ipreq = $_SERVER['REMOTE_ADDR'];
$request = $_SERVER['REQUEST_URI'];
$scriptn = $_SERVER['SCRIPT_NAME'];
$scriptu = $_SERVER['SCRIPT_URI'];
$self = $_SERVER['PHP_SELF'];
$httpr = $_SERVER['HTTP_REFERER'];
$scookie = $_SERVER['HTTP_COOKIE'];
$user_os        =   getOS();
$user_browser   =   getBrowser();
$dominio = $_SERVER['HTTP_HOST'];
$sport = $_SERVER['SERVER_PORT'];
$rport = $_SERVER['REMOTE_PORT'];
$scheme = $_SERVER['REQUEST_SCHEME'];
$sfilename = $_SERVER['SCRIPT_FILENAME'];
$query_string = $_SERVER['QUERY_STRING'];

$var['IP'] = $IP;
$var['sport'] = $sport;
$var['rport'] = $rport;
$var['scheme'] = $scheme;
$var['dominio'] = $dominio;
$var['sfilename'] = $sfilename;
$var['request'] = $request;
$var['scriptu'] = $scriptu;
$var['self'] = $self;
$var['httpr'] = $httpr;
$var['user_os'] = $user_os;
$var['user_browser'] = $user_browser;
$var['user_agent'] = $user_agent;
$var['docroot'] = $docroot;
$var['query_string'] = $query_string;





/*
 * CONEXÃO COM O BANCO DE DADOS
 */
//################################################## AQUI
$dbase = "mdata";    // database name
$server = "100.64.66.211";       // host
$user = "mdataUser";           // usuario
$password = "%q4j45B!@%4c#9NU2r!L9qx%LMS%7&aD";  // password - coloquei o 1 na frente para dar erro e gerar o log de erro
$dbchar = "utf8";                // charset
$tb = 'access_log';
try {
    $pdo = new PDO("mysql:host=$server;dbname=$dbase", $user, $password, array(PDO::ERRMODE_WARNING));
} catch (PDOException $error) {
    $dt = date('Ymd-His');
}

$inn = "INSERT INTO `{$tb}` (`ipreq`,`server_port`,`remote_port`,`request_scheme`,`dominio`,`script_filename`,`request_uri`,`script_uri`,`php_self`,`http_referer`,`sys_os`,`browser`,`user_agent`,`document_root`,`http_cookie`,`server_cookie`,`query_string`) "
. "VALUES ('{$IP}','{$sport}','{$rport}','{$scheme}','{$dominio}','{$sfilename}','{$request}','{$scriptu}','{$self}','{$httpr}','{$user_os}','{$user_browser}','{$user_agent}','{$docroot}','{$cookie}','{$scookie}','{$query_string}')";

try {        $inn = $pdo->query($inn);
} catch (PDOException $error) {
    error("API - INSERT MDATA",$error->getMessage());
}




/*
 * CONEXÃO COM O BANCO DE DADOS
 */
//################################################## AQUI
$dbase = "blue3_api_asn";    // database name
$server = "100.64.66.211";       // host
$user = "asnUser";           // usuario
$password = "3^@GBy*v7v@H#ec7KMYtRd9%&Dn6%PoE";  // password - coloquei o 1 na frente para dar erro e gerar o log de erro
$dbchar = "utf8";                // charset
$tb = 'include';
try {
    $pdo_asn = new PDO("mysql:host=$server;dbname=$dbase", $user, $password, array(PDO::ERRMODE_WARNING));
} catch (PDOException $error) {
    # NÃO IMPRIMIR ERRO, SÓ SAIR SEM EXECUTAR, NO MAXIMO REPORT LOG
    #echo 'Connection Failed: ' . $error->getMessage();
    $dt = date('Ymd-His');
    $logE = $_SERVER['DOCUMENT_ROOT']."/LOG/dberror.log";
    $writeLOG = "echo API - CONNECT DB ERROR - DATA:{$dt} - SQLE:{$error->getMessage()}>> {$logE}";
    shell_exec($writeLOG);
    if (!file_exists($logE)){
        $logE = "/tmp/api.blue3.net.dberror.log";
        $writeLOG = "echo API - CONNECT DB ERROR - DATA:{$dt} - SQLE:{$error->getMessage()}>> {$logE}";
        shell_exec($writeLOG);
    }
}




function goasn () {
    global $pdo_asn;
    global $tb;
    global $var;
    //$IPs = shell_exec("curl ifconfig.me/ip");
    
    $sel = "SELECT COUNT(*) FROM `{$tb}` WHERE `request_uri` != '/ip/' AND `timestamp` >= DATE_SUB(NOW(), INTERVAL 60 SECOND)";
    //É interessante inserir o IP publico do servidor, pois o contador é pelo IP publico, se usar dois servidores com IPs diferentes é só usar a linha abaixo;
    //$sel = "SELECT COUNT(*) FROM `{$tb}` WHERE `request_uri` != '/ip/' AND `server`='{$IPs}' AND `timestamp` >= DATE_SUB(NOW(), INTERVAL 60 SECOND)";
    try {
        $xcount = $pdo_asn->query($sel);
    } catch (PDOException $error) { return "error"; }
    $xcountSleep = $xcount->fetchColumn();
    if ( $xcountSleep <= 10 ) {
        $restGO['go'] = 1;
    } elseif ( $xcountSleep > 41 ) {
        $restGO['go'] = 0;
        sleep(4);
    } elseif ( $xcountSleep >= 30 ) {
        $restGO['go'] = 1;
        sleep(3);
    } elseif ( $xcountSleep >= 20 ) {
        $restGO['go'] = 1;
        sleep(2);
    } elseif ( $xcountSleep > 10 ) {
        $restGO['go'] = 1;
        sleep(1.5);
    }    
    
    $inn = "INSERT INTO `{$tb}` (`server`,`server_local`,`ip`,`server_port`,`request_scheme`,`dominio`,`request_uri`,`script_filename`,`php_self`,`user_agent`) "
    . "VALUES ('{$IPs}','{$_SERVER['SERVER_ADDR']}','{$var['IP']}','{$var['sport']}','{$var['scheme']}','{$var['dominio']}','{$var['request']}','{$var['sfilename']}','{$var['self']}','{$var['user_agent']}')";
    $inn = $pdo_asn->query($inn);
     
    $restGO['id'] = $pdo_asn->lastInsertId();
    
    return $restGO;
}




function script_reg($start,$end,$final,$id) {
    global $pdo_asn;
    global $var;
    $tb = 'script_reg';
    
    $inn = "INSERT INTO `{$tb}` (`inc_id`,`server_local`,`ip_client`,`start`,`end`,`final`) "
    . "VALUES ('{$id}','{$_SERVER['SERVER_ADDR']}','{$var['IP']}','{$start}','{$end}','{$final}')";
    $inn = $pdo_asn->query($inn);
}




if ( isset($_GET['count'])) {    
    $sel = "SELECT COUNT(*) FROM `include` WHERE `timestamp` >= DATE_SUB(NOW(), INTERVAL 60 SECOND)";
    global $pdo_asn;
    try {
        $xcount = $pdo_asn->query($sel);
    } catch (PDOException $error) { return "error"; }
    $xcount = $xcount->fetchColumn();
    
    echo "<BR><BR>Count: {$xcount}";
}
