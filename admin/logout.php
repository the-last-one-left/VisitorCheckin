<?php
/**
 * Admin Logout Handler
 * 
 * Terminates the administrator session and redirects to the main page.
 * 
 * Process:
 * 1. Start existing session
 * 2. Destroy all session data
 * 3. Redirect to main check-in page
 * 
 * @package    VisitorManagement
 * @subpackage Admin
 * @author     Yeyland Wutani LLC <yeyland.wutani@tcpip.network>
 * @version    1.0
 */

session_start();
session_destroy();
header('Location: ../index.php');
exit;
?>
