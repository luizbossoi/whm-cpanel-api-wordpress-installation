<?php
class WHMcPanelWizard {

    # wordpress config vars
    private $wpconfig_databasename  = 'nome_do_banco_de_dados_aqui';
    private $wpconfig_databaseuser  = 'nome_de_usuario_aqui';
    private $wpconfig_databasepass  = 'senha_aqui';
    
    # vars
    private $wordpress_url          = 'https://br.wordpress.org/latest-pt_BR.zip';
    private $remote_wp_path         = '/public_html/';
    private $temporary_folder       = '/temp/';
    private $ftp_mode               = 'passive';
    private $ftp_transfer           = FTP_BINARY; // or FTP_ASCII
    private $api_user               = 'root';
    private $api_token              = false;
    private $api_url                = false;
    private $cpanel_prefixleng      = 8;
    
    // vars - runtime
    private $cpanel_username        = false;
    private $cpanel_password        = false;
    private $cpanel_dbname          = false;
    private $cpanel_dbuser          = false;
    private $cpanel_dbpass          = false;

    # Validation functions
    function is_valid_domain_name($domain_name)
    {
        return (preg_match("/^([a-z\d](-*[a-z\d])*)(\.([a-z\d](-*[a-z\d])*))*$/i", $domain_name) //valid chars check
                && preg_match("/^.{1,253}$/", $domain_name) //overall length check
                && preg_match("/^[^\.]{1,63}(\.[^\.]{1,63})*$/", $domain_name)   ); //length of each label
    }
    
    public static function deleteDir($dirPath) {
        if (!is_dir($dirPath)) {
            throw new InvalidArgumentException("$dirPath must be a directory");
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = scandir($dirPath); 
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if (is_dir($dirPath.$file)) {
                self::deleteDir($dirPath.$file);
            } else {
                if ($dirPath.$file !== __FILE__) {
                    unlink($dirPath.$file);
                }
            }
        }
        rmdir($dirPath);
    }
    
    function getPrefix($username) {
        return substr($username, 0, $this->cpanel_prefixleng) . "_";
    }
    
    function randomPassword() {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); //turn the array into a string
    }
    
    function Zip($source, $destination)
    {
        if (!extension_loaded('zip') || !file_exists($source)) {
            return false;
        }

        $zip = new ZipArchive();
        if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
            return false;
        }

        $source = str_replace('\\', '/', realpath($source));

        if (is_dir($source) === true)
        {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::SELF_FIRST);

            foreach ($files as $file)
            {
                $file = str_replace('\\', '/', $file);

                // Ignore "." and ".." folders
                if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
                    continue;

                $file = realpath($file);

                if (is_dir($file) === true)
                {
                    $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                }
                else if (is_file($file) === true)
                {
                    $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                }
            }
        }
        else if (is_file($source) === true)
        {
            $zip->addFromString(basename($source), file_get_contents($source));
        }

        return $zip->close();
    }
    
    # WHM Functions
	function __construct($api_url, $api_token) {
        $this->api_url          = $api_url;
        $this->api_token        = $api_token;
        
        if (!extension_loaded('zip')) { echo 'ZIP Extension not installed'; exit(); } 
        if (!extension_loaded('curl')) { echo 'CURL Extension not installed'; exit(); } 
        
        $this->temporary_folder = getcwd() . $this->temporary_folder;
        $this->cleanFiles();
	}
    
    function setCpanelUserCredentials($cpanel_username, $cpanel_password) {
        $this->cpanel_username = $cpanel_username;
        $this->cpanel_password = $cpanel_password;
    }
    
    function parse($arr_ret, $print=false) {
        if(isset($arr_ret['api_res'])) {
            if(isset($arr_ret['api_res']['metadata'])) {
                if(isset($arr_ret['api_res']['metadata']['result'])) {
                    if($arr_ret['api_res']['metadata']['result']==0) {
                        $arr_ret['action'] = 'error'; 
                        $arr_ret['message'] = $arr_ret['api_res']['metadata']['reason']; 
                    } else {
                        $arr_ret['action'] = 'ok';
                    }
                }            
            }
        }

        if(isset($arr_ret['api_res'])) {
            if(isset($arr_ret['api_res']['cpanelresult'])) {
                if(isset($arr_ret['api_res']['cpanelresult']['event'])) {
                    if($arr_ret['api_res']['cpanelresult']['event']['result']==0) {
                        $arr_ret['action'] = 'error'; 
                        $arr_ret['message'] = $arr_ret['api_res']['cpanelresult']['event']['reason']; 
                    } else {
                        $arr_ret['action'] = 'ok';
                    }
                }            
            }
        }

        if($print==true) {  header('Content-type: application/json'); print(json_encode($arr_ret)); } else { return $arr_ret; }
    }

	function http_call($query, $args, $path='whm') {
        $str_args = http_build_query($args);
        if($path=='whm') $query = "https://". $this->api_url .":2087/json-api/". $query ."?api.version=1&" . $str_args;
        if($path=='cpanel') $query = "https://". $this->api_url .":2087/json-api/cpanel?" . $str_args;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);

        $header[0] = "Authorization: whm $this->api_user:$this->api_token";
        curl_setopt($curl,CURLOPT_HTTPHEADER,$header);
        curl_setopt($curl, CURLOPT_URL, $query);

        $result = curl_exec($curl);
        $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($http_status != 200) {
            echo "[!] Error: " . $http_status . " returned\n";
        } else {
            $json_array = json_decode($result, true);
            return $json_array;
        }
        
        curl_close($curl);
    }
    
    function createAccount($domain, $username, $password, $additional_fields=array()) {
        if(!$this->is_valid_domain_name($domain))   { $return = array('action'=>'error', 'message'=>'invalid domain name'); }
        if(strlen($username)<8)                     { $return = array('action'=>'error', 'message'=>'username must have at least 8 chars'); }
        if(strlen($password)<8)                     { $return = array('action'=>'error', 'message'=>'password must have at least 8 chars'); }
        if(!is_array($additional_fields))           { $return = array('action'=>'error', 'message'=>'invalid additional_fields format, must be array'); } 
        if(isset($return['action'])) return $this->parse($return);
        
        // create account
        $args       =   array(
                                'domain'    => $domain,
                                'username'  => $username,
                                'password'  => $password,
                                $additional_fields
                        );
        
        $api_res    =   $this->http_call('createacct', $args);
        $return['api_res'] = $api_res;    
        return $this->parse($return);
    }
    
    function createMySQLDatabase($username, $db_name) {
        $args   = array(
                        'cpanel_jsonapi_user'=>$username,
                        'cpanel_jsonapi_apiversion'=>2,
                        'cpanel_jsonapi_module'=>'MysqlFE',
                        'cpanel_jsonapi_func'=>'createdb',
                        'db'=>$this->getPrefix($username) . $db_name
                       );
        
        // create database
        $api_res    = $this->http_call('createdb',$args,'cpanel');
        $return['api_res'] = $api_res;    
        return $this->parse($return);
        
    }
    
    function createMySQLDBUser($username, $db_user, $password) {
        if(strlen($this->getPrefix($username) . $db_user)>=16)  { $return = array('action'=>'error', 'message'=>'database name exceed 16 chars'); }
        if(isset($return['action'])) return $this->parse($return);

        $args   = array(
                        'cpanel_jsonapi_user'=>$username,
                        'cpanel_jsonapi_apiversion'=>2,
                        'cpanel_jsonapi_module'=>'MysqlFE',
                        'cpanel_jsonapi_func'=>'createdbuser',
                        'dbuser'=>$this->getPrefix($username) . $db_user,
                        'password'=>$password,
                       );
        
        // create database
        $api_res    = $this->http_call('createdbuser',$args,'cpanel');
        $return['api_res'] = $api_res;    
        return $this->parse($return);
    }
    
    function setMySQLDBPrivileges($username, $db_user, $db, $privileges='ALL PRIVILEGES') {
        $args   = array(
                        'cpanel_jsonapi_user'=>$username,
                        'cpanel_jsonapi_apiversion'=>2,
                        'cpanel_jsonapi_module'=>'MysqlFE',
                        'cpanel_jsonapi_func'=>'setdbuserprivileges',
                        'db'=>$this->getPrefix($username) . $db,
                        'dbuser'=>$this->getPrefix($username) . $db_user,
                        'privileges'=>$privileges,
                       );
        
        // create database
        $api_res    = $this->http_call('setdbuserprivileges',$args,'cpanel');
        $return['api_res'] = $api_res;    
        return $this->parse($return);
    }
    
    function unzipOnServer($username, $file_path) {
        $args   = array(
                        'cpanel_jsonapi_user'=>$username,
                        'cpanel_jsonapi_apiversion'=>2,
                        'cpanel_jsonapi_module'=>'Fileman',
                        'cpanel_jsonapi_func'=>'fileop',
                        'op'=>'extract',
                        'sourcefiles'=>$file_path,
                        'destfiles'=>$this->remote_wp_path,
                       );
        
        // create database
        $api_res    = $this->http_call('fileop',$args,'cpanel');
        $return['api_res'] = $api_res;    
        return $this->parse($return);
    }
    
    function movefileToTrash($username, $file_path) {
        $args   = array(
                        'cpanel_jsonapi_user'=>$username,
                        'cpanel_jsonapi_apiversion'=>2,
                        'cpanel_jsonapi_module'=>'Fileman',
                        'cpanel_jsonapi_func'=>'fileop',
                        'op'=>'trash',
                        'sourcefiles'=>$file_path,
                       );
        
        // create database
        $api_res    = $this->http_call('fileop',$args,'cpanel');
        $return['api_res'] = $api_res;    
        return $this->parse($return);
    }
    

    
    function createAccountandDB($acct_domain, $acct_username, $acct_password, $db_name, $db_user, $db_password) {
        $return = array('action'=>'error', 'message'=>'');
        
        $create_account     = $this->createAccount($acct_domain, $acct_username, $acct_password);
        if($create_account['action']!='ok') {
            $return['message'] = 'Account creation error: ' . $create_account['message'];
            return $this->parse($return, true);
        }
        
        $create_db          = $this->createMySQLDatabase($acct_username, $db_name);
        if($create_db['action']!='ok') {
            $return['message'] = 'Database creation error: ' . $create_db['message'];
            return $this->parse($return, true);
        }
        
        $create_dbuser      = $this->createMySQLDBUser($acct_username, $db_user, $db_password);
        if($create_dbuser['action']!='ok') {
            $return['message'] = 'Database user creation error: ' . $create_dbuser['message'];
            return $this->parse($return, true);
        }
        
        $createdb_privileges    = $this->setMySQLDBPrivileges($acct_username, $db_name, $db_user);  
        if($createdb_privileges['action']!='ok') {
            var_dump($createdb_privileges);
            $return['message'] = 'Database user privileges set  error: ' . $createdb_privileges['message'];
            return $this->parse($return, true);
        }
        
        // all ok
        $return = array('action'=>'ok', 'message'=>'account created');
        
        $this->cpanel_username  = $acct_username;
        $this->cpanel_password  = $acct_password;
        $this->cpanel_dbname    = $db_name;
        $this->cpanel_dbuser    = $db_user;
        $this->cpanel_dbpass    = $db_password;
        
        return $this->parse($return, true);
    }
    
    function createAccountInstallWP($acct_domain, $acct_username, $acct_password, $db_name='wpress', $db_user='wpress', $db_password==false) {
        if($db_password==false) $db_password = $this->randomPassword();
        
        $create_account = $this->createAccountandDB($acct_domain, $acct_username, $acct_password, $db_name, $db_user, $db_password);
        if($create_account['action']=='error') {
            print("Cannot create account: " . $create_account['message']);
            exit();
        }
        
        $this->downloadWordpressZIP();
        $this->replaceWPCONFIG($this->temporary_folder . "wordpress/wp-config-sample.php", $this->temporary_folder . "wordpress/wp-config.php");
        $this->Zip($this->temporary_folder . "wordpress/", getcwd() . "/wordpress-release.zip");
        $this->uploadWPZIP(getcwd() . "/wordpress-release.zip");
        $this->unzipOnServer($account_username, $this->remote_wp_path "wordpress-release.zip");
        $this->movefileToTrash($account_username, $this->remote_wp_path . "wordpress-release.zip");
        $this->cleanFiles();
        print("Wordpress Installed");
        exit();
    }
    
    function downloadWordpressZIP() {
        $wordpress_zip = file_get_contents($this->wordpress_url);
        file_put_contents("wordpress-latest.zip", $wordpress_zip);
        if(!is_dir($this->temporary_folder)) mkdir($this->temporary_folder);
        
        $zip = new ZipArchive;
        $res = $zip->open("wordpress-latest.zip");
        if ($res === TRUE) {
            $zip->extractTo($this->temporary_folder);
            $zip->close();
        } else {
            echo "Could not open/extract wordpress file";
            exit();
        }
    }
    
    function replaceWPCONFIG($file_path, $file_dest) {
        $file_content   = file_get_contents($file_path);
        $file_content   = str_replace($this->wpconfig_databasename, $this->cpanel_dbname, $file_content);
        $file_content   = str_replace($this->wpconfig_databaseuser, $this->cpanel_dbuser, $file_content);
        $file_content   = str_replace($this->wpconfig_databasepass, $this->cpanel_dbpass, $file_content);

        file_put_contents($file_dest, $file_content);
    }
    
    function uploadWPZIP($wp_zipfile) {
        $conn_id = ftp_connect($this->api_url);
        if($this->ftp_mode=='passive') ftp_pasv($conn_id, true);
        $login_result = ftp_login($conn_id, $this->cpanel_username, $this->cpanel_password);

        if (!ftp_put($conn_id, $this->remote_wp_path . "/wordpress-release.zip", $wp_zipfile, $this->ftp_transfer)) {
            echo "Couldn't upload wordpress installer";
        }

        ftp_close($conn_id);
    }
    
    function cleanFiles() {
        // delete temp directory
        if(is_dir($this->temporary_folder)) WHMcPanelWizard::deleteDir($this->temporary_folder);
        if(is_file("wordpress-latest.zip")) unlink("wordpress-latest.zip");
        if(is_file("wordpress-release.zip")) unlink("wordpress-release.zip");
    }
}