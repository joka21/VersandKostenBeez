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

// Include the main Versand_Kosten_Beez_Plugin class.
if ( ! class_exists( 'Versand_Kosten_Beez_Plugin' ) ) :
    class Versand_Kosten_Beez_Plugin {
        private Versand_Kosten_Beez_Shipping_Method $shipping_method;
        private Breez_shipping_availability_controller $shipping_availability_controller;
        private versandkosten_beez_product_controller $product_controller;

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
            require_once('Breez-shipping-availability-controller.php');
            require_once('Breez-shipping-method.php');
            require_once('versandkosten-beez-product-controller.php');

            // Init shipping method
            $this->shipping_method = new Versand_Kosten_Beez_Shipping_Method();

            // Init shipping availability controller
            $this->shipping_availability_controller = Breez_shipping_availability_controller::getInstance();

            // Init product controller
            $this->product_controller = new versandkosten_beez_product_controller();

            // Set the plugin slug
            define( 'VERSAND_KOSTEN_BEEZ_SLUG', 'wc-settings' );

            // Setting action for plugin
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'Versand_Kosten_Beez_Plugin_action_links' );
        }
    }

    $Versand_Kosten_Beez_Plugin = new Versand_Kosten_Beez_Plugin( __FILE__ );
    function Versand_Kosten_Beez_Plugin_action_links( $links ) {
        $links[] = '<a href="'. menu_page_url( VERSAND_KOSTEN_BEEZ_SLUG, false ) .'&tab=shipping&section=versand-kosten-beez-shipping-method">Settings</a>';
        return $links;
    }

endif;

?>