    <?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);

require_once('classes/WHMcPanelWizard.class.php');


if(@$_SERVER['REQUEST_METHOD']=='POST') {
    $host               = addslashes($_POST['host']);
    $whmtoken           = addslashes($_POST['whmtoken']);
    $account_domain     = addslashes($_POST['account_domain']);
    $account_username   = addslashes($_POST['account_username']);
    $account_password   = addslashes($_POST['account_password']);
    
    $WCW = new WHMcPanelWizard($host, $whmtoken);
    $WCW->createAccountInstallWP($account_domain, $account_username, $account_password, null, null, false);
}
?>
<h1>Instalador WordPress</h1>
<form method="post">
<table>
    <tr>
        <td>Host:</td>
        <td><input type="text" name="host"></td>
    </tr>
    <tr>
        <td>WHM API Token:</td>
        <td><input type="text" name="whmtoken"></td>
    </tr>
    <tr>
        <td>Account Domain:</td>
        <td><input type="text" name="account_domain"></td>
    </tr>
    <tr>
        <td>Account Username:</td>
        <td><input type="text" name="account_username"></td>
    </tr>
    <tr>
        <td>Account Password:</td>
        <td><input type="text" name="account_password"></td>
    </tr>
    <tr>
        <td colspan="2"><input type="submit"></td>
    </tr>
    
</table>
</form>
