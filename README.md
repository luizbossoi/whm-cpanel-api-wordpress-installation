# Wordpress Install Wizard
This script allows you to create an account and install Wordpress on WHM using WHM API Token

# How to get WHM Api Token
To get your WHM API Token:

    - Login on your server's WHM
    - On the left menu, under "Development", click on "Manage API Tokens""
    - Click on "Generate Token", copy the token for to use in the future.
    
# How to use this script
You can simply run installer.php on your server, or you can run this script as you wish.
First you need to create this object with your host and whm token.

	$WCW = new WHMcPanelWizard($host, $whmtoken);

Where:

    - $host = your server IP or hostname
    - $whmtoken = Your WHM token ID
    
After that, you can run a method to create your WHM account and install your WP after account creation.

	$WCW->createAccountInstallWP($account_domain, $account_username, $account_password, $db_name, $db_user, $db_password);

Where:

	- $account_domain = your account domain (eg: yourwebsite.com)
    - $account_username = your account username (eg: yourweb)
    - $account_password = your account password  (try to use a strong password with chars, upper and lower cases, and numbers, eg: GJAi2na2gn54j)
    - $db_name = wordpress database name (avoid using big names, try "wpress" only, for example)
    - $db_user = wordpress database username name (avoid using big names, try "wpress" only, for example)
    - $db_password = wordpress datbase password, you can use "false" to generate a random password.
    
This createAccountInstallWP method calls the following submethods:

    - createAccountandDB($acct_domain, $acct_username, $acct_password, $db_name, $db_user, $db_password, $print_errors=false)
    - downloadWordpressZIP()
    - replaceWPCONFIG($file_path, $file_dest)
    - Zip($source, $destination)
    - uploadWPZIP($wp_zipfile)
    - unzipOnServer($username, $file_path)
    - movefileToTrash($username, $file_path)
    - cleanFiles()
    
These methods are kinda self explanatory, but if you need more details about it, feel free to dig the source and find what they do, or open a issue request.
Anyways, these methods can be called or changed as you need.

Everything was tested and worked fine on version v80.0.22 of WHM.

