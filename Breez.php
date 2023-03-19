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


    // Add popup and input field to WooCommerce product page
    function add_popup_input_field() {
        //Add text with current postleitzahl saved in cookie
        echo '<p id="postleitzahl-output">Postleitzahl: ' . $_COOKIE['postleitzahl'] . '</p>';

        // Add button to open popup
        echo '<button type="button" class="btn-popup">Postleitzahl ändern</button>';
        
        // Add popup and input field
        echo '<dialog id="postleitzahl-popup" class="postleitzahl-popup" style="display:none;z-index:99999;">
                <div class="postleitzahl-popup-content">
                    <form id="postleitzahl-form">
                        <label for="postleitzahl-input">Bitte geben Sie die Postleitzahl des Liefergebiets ein:</label>
                        <input type="text" id="postleitzahl-input" name="postleitzahl" required>
                        <button type="cancel">Abbrechen</button>
                        <button type="submit">Weiter zum Preis</button>
                    </form>
                </div>
            </dialog>';

        echo /*html*/'
        <div id="total-cost-overview">
            <h2>Preise:</h2>
            <!-- ------------------------------------------------------------ -->
            <div id="single-product-price">
                <span id="product-name" class="price-label">placeholder</span>
                <span class="price-value">
                    <span class="woocommerce-Price-amount amount">
                        placeholder
                    </span>
                    <span class="woocommerce-Price-currencySymbol">€</span>
                </span>
            </div>
            <div id="shipping-costs">
                <span class="price-label">+ Lieferkosten:</span>
                <span class="price-value">
                    <span class="woocommerce-Price-amount amount">
                        placeholder
                    </span>
                    <span class="woocommerce-Price-currencySymbol">€</span>
                </span>
            </div>
            <!-- ============================================================= -->
            <div id="total-costs">
                <span class="price-label">Gesamt:</span>
                <span class="price-value">
                    <span class="woocommerce-Price-amount amount">
                        placeholder
                    </span>
                    <span class="woocommerce-Price-currencySymbol">€</span>
                </span>
            </div>
        </div>';
    }
    add_action( 'woocommerce_single_product_summary', 'add_popup_input_field', 30 );

    // Add javascript-code for product page
    function add_popup_jquery() {
        
        echo /*html*/'<script>
                jQuery(document).ready(function() {
                    // function to fill the total cost overview
                    function fill_total_cost_overview(plz) {
                        var post_id = jQuery("#post").val();
                        jQuery.ajax({
                            url: "' . admin_url('admin-ajax.php') . '",
                            type: "POST",
                            data: {
                                action: "get_shipping_costs",
                                post_id: post_id,
                                postleitzahl: plz
                            },
                            success: function(response) {
                                var product_name = "'.wc_get_product()->get_name().'";
                                var product_price = parseFloat("'. wc_get_product()->get_price() .'", 2)
                                var shipping_costs = parseFloat(response, 2);
                                var total_costs = product_price + shipping_costs;

                                jQuery("#product-name").text(product_name);
                                jQuery("#single-product-price .price-value .woocommerce-Price-amount.amount").text(product_price.toFixed(2));
                                jQuery("#shipping-costs .price-value .woocommerce-Price-amount.amount").text(shipping_costs.toFixed(2));
                                jQuery("#total-costs .price-value .woocommerce-Price-amount.amount").text(total_costs.toFixed(2));

                            },
                            error: function(xhr, status, error) {
                                alert("Error: " + error);
                                console.log(xhr.responseText);
                            }
                        });
                    }


                    //function to change postleitzahl cookie
                    function change_postleitzahl(plz) {
                        document.cookie = "postleitzahl=" + plz;
                        set_postleitzahl_output(postleitzahl);
                        fill_total_cost_overview(plz);
                    }

                    //function to get postleitzahl cookie
                    function get_postleitzahl_cookie() {
                        var postleitzahl = "";
                        var allcookies = document.cookie;
                        cookiearray = allcookies.split(\';\');
                        for(var i=0; i<cookiearray.length; i++) {
                            name = cookiearray[i].split(\'=\')[0];
                            value = cookiearray[i].split(\'=\')[1];
                            if (name.trim() === "postleitzahl") {
                                postleitzahl = value;
                            }
                        }
                        return postleitzahl;
                    }

                    //function to set postleitzahl output
                    function set_postleitzahl_output(plz) {
                        jQuery("#postleitzahl-output").text("Lieferung zur Postleitzahl: " + plz);
                    }


                    // Check if postleitzahl-cookie has a value
                    var postleitzahl = get_postleitzahl_cookie();

                    if (postleitzahl === "") {
                        // If postleitzahl is empty, show popup
                        jQuery("#postleitzahl-popup").fadeIn();
                    }else{
                        // If postleitzahl is not empty, get shipping costs
                        set_postleitzahl_output(postleitzahl)
                        fill_total_cost_overview(postleitzahl);
                    }
                    
                    // Handle popup and form submission
                    jQuery(".postleitzahl-popup").click(function(event) {
                        if (event.target === this) {
                            var postleitzahl = jQuery("#postleitzahl-input").val();
                            set_postleitzahl_output(postleitzahl);
                            change_postleitzahl(postleitzahl);
                            jQuery(this).fadeOut();
                        }
                    });

                    jQuery(".btn-popup").click(function() {
                        jQuery("#postleitzahl-popup").fadeIn();
                        jQuery("#postleitzahl-input").val("");
                        jQuery("#postleitzahl-input").focus();
                    });

                    jQuery("#postleitzahl-form").submit(function(event) {
                        event.preventDefault();
                        var postleitzahl = jQuery("#postleitzahl-input").val();
                        if (postleitzahl !== "") {
                            // Update Postleitzahl
                            change_postleitzahl(postleitzahl);
                            
                            // Close popup
                            jQuery("#postleitzahl-popup").fadeOut();
                        } else {
                            alert("Please enter a value for the postleitzahl.");
                        }
                    });
                });
            </script>';
            
    }

    /**
     * Get shipping costs from Integration
     */
    function get_shipping_costs() {
        $plz = $_POST['postleitzahl'];
        echo "170,00";
        /*
        $integration = new Versand_Kosten_Beez_Integration();
        $shipping_costs = $integration->get_shipping_costs($plz);

        echo $shipping_costs;
        */
        wp_die();
    }

    /*
    function init_product_scripts() {
        wp_register_script('breez-product-js', plugins_url(__FILE__).'/js/Breez_product.js', array('jquery'), '1.0', false);
        wp_enqueue_style('breez-product-js');
        
        wp_localize_script('breez-product-js', 'php_vars', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'product_name' => wc_get_product()->get_name(),
            'product_price' => wc_get_product()->get_price()
        ));
    }*/

    //add_action('wp_enqueue_scripts','init_product_scripts');
    add_action('wp_footer', 'add_popup_jquery' );
    add_action('wp_ajax_get_shipping_costs', 'get_shipping_costs' );
    add_action('wp_ajax_nopriv_get_shipping_costs', 'get_shipping_costs' );
endif; 

?>