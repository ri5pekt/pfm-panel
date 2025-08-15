<?php
// class-pfm-panel-plugin.php
class PFM_Panel_Plugin {
    public function __construct() {
        new PFMP_Admin();
        new PFMP_REST_Orders();
        new PFMP_REST_Stats();
        new PFMP_REST_Subscriptions();
        new PFMP_REST_Customers();
        new PFMP_REST_Replacements();
        new PFMP_REST_Reports();
    }
}