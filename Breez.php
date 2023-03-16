<?php
/*
 * Plugin Name:       Versand Kosten Beez
 * 
 * Description:       Berechnet die Versandkosten!
 * Version:           1.0
 
 * Requires PHP:      7.2
 * Author:            Medienwerkstatt-niederrhein
 * Author URI:        https://author.example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.co
 * Text Domain:       my-basics-plugin
 
 */

if ( ! class_exists( 'Versand_Kosten_Beez_Plugin' ) ) :
    class Versand_Kosten_Beez_Plugin {
        /**
         * Construct the plugin
         */
        public function __construct() {
            add_action( 'plugins_loaded', array( $this, 'init' ) );
        }

        /**
        * Init the plugin
        */
        public function init() {
            // Checks if WooCommerce is installed.
            if ( class_exists( 'WC_Integration' ) ) {
                // Include our integration class.
                include_once 'Breez-integration.php';
                // Register the integration.
                add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );

                // Set the plugin slug
                define( 'VERSAND_KOSTEN_BEEZ_SLUG', 'wc-settings' );

                // Setting action for plugin
                add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'Versand_Kosten_Beez_Plugin_action_links' );
            }
        }

        /**
         * Add a new integration to WooCommerce.
         * @param array $integrations Integrations.
         * @return array $integrations Integrations.
         * @since 1.0
         *  */
        public function add_integration( $integrations ) {
            $integrations[] = 'Versand_Kosten_Beez_Integration';
            return $integrations;
        }

       

    }
$Versand_Kosten_Beez_Plugin = new Versand_Kosten_Beez_Plugin( __FILE__ );
function Versand_Kosten_Beez_Plugin_action_links( $links ) {
    $links[] = '<a href="'. menu_page_url( VERSAND_KOSTEN_BEEZ_SLUG, false ) .'&tab=integration">Settings</a>';
    return $links;
}
endif; 

?>