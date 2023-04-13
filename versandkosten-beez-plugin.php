<?php
/*
 * Plugin Name:             Versandkosten Beez
 * Description:             Berechnet die Versandkosten und verwaltet die verfügbaren Lieferwochen!
 * Version:                 1.0
 * Requires PHP:            7.2
 * Requires WordPress:      5.2
 * Requires WooCommerce:    3.6
 * Author:                  Medienwerkstatt-niederrhein
 * Author URI:              https://medienwerkstatt-niederrhein.de/
 * License:                 GPL v2 or later
 * License URI:             https://www.gnu.org/licenses/gpl-2.0.html
 */


// Set the plugin slugs
const VERSANDKOSTEN_BEEZ_SHIPPING_METHOD_SLUG = 'wc-settings';
const VERSANDKOSTEN_BEEZ_SHIPPING_MANAGEMENT_SLUG = 'Versandkosten Beez Lieferwochen Management';

require_once('versandkosten-beez-availability-settings-page.php');

// Create Plugin Main Class
if ( ! class_exists( 'Versand_Kosten_Beez_Plugin' ) ) :
    class Versand_Kosten_Beez_Plugin {
        private Versand_Kosten_Beez_Shipping_Method $shipping_method;
        private VersandkostenBeezAvailabilityDao $shipping_availability_controller;
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

            // Include required files
            require_once('versandkosten-beez-shipping-availability-dao.php');
            require_once('versandkosten-beez-shipping-method.php');
            require_once('versandkosten-beez-product-controller.php');

            // Init shipping method
            $this->shipping_method = new Versand_Kosten_Beez_Shipping_Method();

            // Init shipping availability controller
            $this->shipping_availability_controller = VersandkostenBeezAvailabilityDao::getInstance();

            // Init product controller
            $this->product_controller = new versandkosten_beez_product_controller();

            // Setting action for plugin
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array($this, 'Versand_Kosten_Beez_Plugin_action_links'));
        }

        /**
         * Add the settings link to the plugins page
         */
        function Versand_Kosten_Beez_Plugin_action_links( $links ) {
            $links[] = '<a href="'. menu_page_url( VERSANDKOSTEN_BEEZ_SHIPPING_METHOD_SLUG, false ) .'&tab=shipping&section=versand-kosten-beez-shipping-method">Lieferkosten Einstellungen</a>';
            $links[] = '<a href="'. menu_page_url( VERSANDKOSTEN_BEEZ_SHIPPING_MANAGEMENT_SLUG, false ). '">Lieferwochen verwalten</a>';
            return $links;
        }
    }

    $Versand_Kosten_Beez_Plugin = new Versand_Kosten_Beez_Plugin( __FILE__ );

    // Add the lieferwochen management page
    $shipping_availability_settings = new VersandkostenBeezAvailabilitySettingsPage();
    add_action( 'init', array($shipping_availability_settings, 'init'));

endif;
?>