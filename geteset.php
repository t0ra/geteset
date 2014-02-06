#!/usr/local/bin/php
<?php
$settings = parse_ini_file("settings.txt");

$tempeset = "/var/eset/";
if (!is_dir($settings['upload_dir'])){
        mkdir($settings['upload_dir']);
}
if (!is_dir($tempeset)){
        mkdir($tempeset);
}
$msg = "";

$varray = explode(",", $settings['ver']);

$sigarray = explode(",",$settings['signver']);
$cvarray = count($varray);
$sarray = array();
for($p=0;$p<$cvarray;$p++){
        $curver = $varray[$p];
        $cursign = $sigarray[$p];
        if (!is_dir($tempeset."v".$curver)){
                        mkdir($tempeset."v".$curver);
        }
    passthru("/bin/rm ".$tempeset."v".$curver."/update.rar" , $result);
    passthru("/bin/rm ".$tempeset."v".$curver."/update.ver" , $result);
		passthru("/usr/".getostype()."bin/wget -c http://update.eset.com/eset_upd/v".$curver."/update.ver -O ".$tempeset."v".$curver."/update.rar", $result);
		passthru("/usr/".getostype()."bin/unrar e -y ".$tempeset."v".$curver."/update.rar ".$tempeset."v".$curver, $result);
        $upfile = file_get_contents($tempeset.'v'.$curver.'/update.ver');


        $serv_list = explode('[HOSTS]', $upfile);
        $serv_list = explode('[Expire]', $serv_list[1]);
        preg_match_all('#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#', $serv_list[0], $servers);
        $ns = count($servers[0]);

        $srv = array();

        for ($i=0;$i<$ns;$i++){
                        $srv[] = parse_url($servers[0][$i], PHP_URL_HOST);
        }
        $strngfile = explode("\r\n", $upfile);

        $updfiles = array();
        $filenames = array();
        $idarr = array();
        $lines = count($strngfile);
		for ($j=0;$j<$lines;$j++){
                        preg_match('/^file=/i', $strngfile[$j], $result);
                        if (isset($result[0]) and $result[0] == 'file='){
                                        $nfile = preg_replace('/^file=/i', '', $strngfile[$j]);
                                        $nnfile = end(explode("/",$nfile));
                                        $updfiles[] = $nfile;
                                        $filenames[] = $nnfile;
                        }

                        preg_match('/versionid/i', $strngfile[$j], $result);
                        if(isset($result[0]) and $result[0] =="versionid"){
                                        $idstr = preg_split('/versionid=/i', $strngfile[$j]);
                                        $idarr[] = $idstr[1];
                        }

        }
        asort($idarr);
        $sarray[] = end($idarr);
        if($cursign == end($idarr)){
                        $msg = "Update for V".$curver." is not required!";
                        $sign = 0;
                        tolog($msg,$sign);
        } else {
        $numfiles = count($updfiles);
        for ($k=1;$k<$numfiles;$k++){
                                        getfilefromweb($updfiles[$k]);
        }
        passthru("/bin/rm ".$settings['upload_dir']."/v".$curver."/update.ver", $result);
        $getdir = scandir($settings['upload_dir']."/v".$curver);
        $dirfilenames = array();
        $cgd = count($getdir);
        for($n=0;$n<$cgd;$n++){
                        if($getdir[$n]!="." and $getdir[$n]!=".."){
                                        $dirfilenames[] = $getdir[$n];
                        }
        }
        $checkfiles = array_diff($filenames, $dirfilenames);
        $cchecked = count($checkfiles);
        $msg = "";
        $sign = "";
        $err_array = array();
		if($cchecked>0){
                        for($m=0;$m<$cchecked;$m++){
                                        if(isset($checkfiles[$m]) and $checkfiles[$m]!="update.ver" and $checkfiles[$m] != ""){
                                                        $err_array[] = $checkfiles[$m];
                                        }
                        }

        }
        if(count($err_array)>0){
                        $msg = "Error of downloading files! ";
                        $msg .= count($err_array)." files not downloading!";
                        $sign = "1";
                        }
                        else {
                                $msg = "Updating for V".$curver." complete!";
                                $sign = 0;
                                $pie = explode('[PCUVER]', $upfile);
                                $strfile = explode("\r\n", $pie[1]);
                                $nl = count($strfile);
                                for ($l=3;$l<$nl;$l++){
                                        preg_match('/^file=/i', $strfile[$l], $result);

                                        if (isset($result[0]) and $result[0] == 'file='){
                                                $nline = preg_replace('/^file=/i', '', $strfile[$l]);
                                                $nnline = end(explode("/",$nline));
                                                $strfile[$l] = "file=".$nnline;
                                        }
                                file_put_contents($settings['upload_dir']."/v".$curver."/update.ver", $strfile[$l]."\n",FILE_APPEND);
                                }
                                for($q=0;$q<count($sarray);$q++) {
                                        $newsign .= $sarray[$q].",";
                                }

                                $msg .= " New signature for V".$curver." is:".end($idarr);
                                passthru("/bin/rm ".$tempeset."v".$curver."/update.rar", $result);
                                passthru("/bin/rm ".$tempeset."v".$curver."/update.ver", $result);

                        }
                        tolog($msg,$sign);
                        unset($msg);

        }
}
$newsign = "";
for($q=0;$q<count($sarray);$q++) {
        $newsign .= trim($sarray[$q]).",";
}
$cv = 'signver="'.$newsign.'"';
$vfile = 'settings.txt';
$verfile = file_get_contents($vfile);
$verfile = preg_replace('/signver=".*"/i', $cv, $verfile);
file_put_contents($vfile, $verfile);

function getfilefromweb($gf){
                global $srv, $settings,$curver;
                $ns = count($srv);
                for ($i=0;$i<$ns;$i++){
                        $link = "http://".$srv[$i];
                        $state = get_headers($link);
                        preg_match('/403 Forbidden/i', $state[0], $searchhttp);
                        if ($searchhttp[0] == "403 Forbidden"){
                           $param = $settings['upload_dir']."/v".$curver." --http-user=".$settings['user']." --http-password=".$settings['user']." --http-password=".$settings['password']." ".$link.$gf;
                           passthru("/usr/".getostype()."bin/wget -N -P ".$param, $result);
                           return 0;
                        }

               }
        }
function getostype(){
        exec("/usr/bin/uname -o", $res);
        preg_match('/BSD/i', $res[0], $ostype);
        if(isset($ostype[0])){
                if($ostype[0] == "BSD"){
                        $ospath = "local/";
                } else {
                        $ospath = "";
                }
        return $ospath;
        } else {
                return 0;
        }
}
function tolog($logmsg, $logsign){
        global $settings,$tempeset;
        switch($settings['log_type'])
        {
                case "file":
                        file_put_contents($settings['log_file'], date("Y-m-d H:i:s").": ".$logmsg."\n",FILE_APPEND);
                break;
                case "db":
                                                if(isset($settings['log_db_table'])){

$con=mysqli_connect($settings['log_db_host'],$settings['log_db_user'],$settings['log_db_password'],$settings['log_db_base']);
                                                if (mysqli_connect_errno())
                                {
                                echo "Failed to connect to MySQL: " . mysqli_connect_error();
                                }
mysqli_query($con,"INSERT INTO ".$settings['log_db_table']." (`date`, `sign`, `log`) VALUES ('".date("Y-m-d
H:i:s")."','".$logsign."','".$logmsg."')");
                        mysqli_close($con);
                }
                break;
        default:
                         echo "";
        }
}
?>