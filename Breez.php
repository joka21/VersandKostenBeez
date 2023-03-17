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
        // Add button to open popup
        echo '<button type="button" class="btn-popup">Postleitzahl ändern</button>';
        
        // Add popup and input field
        echo '<dialog id="postleitzahl-popup" class="postleitzahl-popup" style="display:none;z-index:99999;">
                <div class="postleitzahl-popup-content">
                    <form id="postleitzahl-form">
                        <label for="postleitzahl-input">Postleitzahl Field:</label>
                        <input type="text" id="postleitzahl-input" name="postleitzahl" required>
                        <button type="submit">Eingabe</button>
                    </form>
                </div>
            </dialog>';
    }
    add_action( 'woocommerce_single_product_summary', 'add_popup_input_field', 30 );

    //TODO: Anzeige der eingegebenen Postleitzahl und des berechneten Versandpreises
    // Add jQuery to handle popup
    function add_popup_jquery() {
        echo '<script>
                jQuery(document).ready(function() {
                    // Check if postleitzahl has a value
                    var postleitzahlValue = jQuery("#postleitzahl-input").val();
                    if (postleitzahlValue === "") {
                        // If postleitzahl is empty, show popup
                        jQuery("#postleitzahl-popup").fadeIn();
                    }
                    
                    // Handle popup and form submission
                    jQuery(".postleitzahl-popup").click(function(event) {
                        if (event.target === this) {
                            jQuery(this).fadeOut();
                        }
                    });
                    jQuery("#postleitzahl-form").submit(function(event) {
                        event.preventDefault();
                        var postleitzahl = jQuery("#postleitzahl-input").val();
                        if (postleitzahl !== "") {
                            // Save postleitzahl value as post meta
                            var postID = jQuery("#post").val();
                            jQuery.ajax({
                                url: "' . admin_url('admin-ajax.php') . '",
                                type: "POST",
                                data: {
                                    action: "save_postleitzahl_postleitzahl",
                                    post_id: postID,
                                    postleitzahl: postleitzahl
                                },
                                success: function(response) {
                                    console.log(response);
                                },
                                error: function(xhr, status, error) {
                                    console.log(xhr.responseText);
                                }
                            });
                            
                            // Close popup
                            jQuery("#postleitzahl-popup").fadeOut();
                        } else {
                            alert("Please enter a value for the postleitzahl.");
                        }
                    });
                });
            </script>';
    }
    add_action( 'wp_footer', 'add_popup_jquery' );

    // Save postleitzahl value as post meta via AJAX
    function save_postleitzahl_postleitzahl() {
        $post_id = $_POST['post_id'];
        $postleitzahl_value = $_POST['postleitzahl'];
        update_post_meta( $post_id, '_postleitzahl_postleitzahl', $postleitzahl_value );
        echo "Postleitzahl erfolgreich gespeichert.";
        //wp_die();
    }
    add_action( 'wp_ajax_save_postleitzahl_postleitzahl', 'save_postleitzahl_postleitzahl' );
    add_action( 'wp_ajax_nopriv_save_postleitzahl_postleitzahl', 'save_postleitzahl_postleitzahl' );

endif; 

?>