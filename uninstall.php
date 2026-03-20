<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit;
delete_option('wabe_options');
delete_option('wabe_logs');
delete_transient('wabe_license_check');
