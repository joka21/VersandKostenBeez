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
            
            // Include the main Versand_Kosten_Beez_Shipping_Method class.
            if(!class_exists('Versand_Kosten_Beez_Shipping_Method')){
                require_once('Breez-shipping-method.php');
            }

            // Init shipping method
            $this->shipping_method = new Versand_Kosten_Beez_Shipping_Method();

            // Set the plugin slug
            define( 'VERSAND_KOSTEN_BEEZ_SLUG', 'wc-settings' );

            // Setting action for plugin
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'Versand_Kosten_Beez_Plugin_action_links' );

            // Add popup and input field to WooCommerce product page
            add_action( 'woocommerce_single_product_summary', array($this, 'add_popup_input_field'), 30);

            // Add jQuery to footer
            add_action('wp_head', array($this, 'add_popup_jquery'));

            // Add ajax action to get shipping costs
            add_action('wp_ajax_get_shipping_costs', array($this, 'get_shipping_costs'));
            add_action('wp_ajax_nopriv_get_shipping_costs', array($this, 'get_shipping_costs'));
        }

        /**
         * Add popup and input field to WooCommerce product page
         */
        function add_popup_input_field() {
            if(is_product()) {
                $plz = $_COOKIE['plz'] ?? "";

                //Add text with current postleitzahl saved in cookie
                echo '<p id="plz-output">Postleitzahl: ' . $plz . '</p>';

                // Add button to open popup
                echo '<button type="button" class="btn-popup">Postleitzahl ändern</button>';
                
                // Add popup and input field
                ?>
                    <dialog id="plz-popup" class="plz-popup" style="display:none;z-index:99999;">
                        <div class="plz-popup-content">
                            <form id="plz-form">
                                <label for="plz-input">Bitte geben Sie die Postleitzahl des Liefergebiets ein:</label>
                                <input type="text" id="plz-input" name="plz" required>
                                <button type="submit" class="btn-popup-submit">Speichern</button>
                            </form>
                        </div>
                    </dialog>
                
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
                    </div>
                <?php
            }
        }


        /**
         * Add jQuery to footer of product page
         */
        function add_popup_jquery() {
            //check if we are on a product page
            if(is_product()) {
                ?>
                    <script>
                        jQuery(document).ready(function() {
                            // function to fill the total cost overview
                            function fill_total_cost_overview(plz) {
                                var post_id = jQuery("#post").val();
                                jQuery.ajax({
                                    url: "<?php echo admin_url('admin-ajax.php') ?>",
                                    type: "POST",
                                    data: {
                                        action: "get_shipping_costs",
                                        post_id: post_id,
                                        plz: plz
                                    },
                                    success: function(response) {
                                        var responseObj = JSON.parse(response);

                                        if(responseObj.status == "success") {
                                            var product_name = "<?php echo wc_get_product()->get_name() ?>";
                                            var product_price = parseFloat("<?php echo wc_get_product()->get_price()?>", 2)
                                            var shipping_costs = parseFloat(responseObj.shipping_costs, 2);
                                            var total_costs = product_price + shipping_costs;

                                            jQuery("#product-name").text(product_name);
                                            jQuery("#single-product-price .price-value .woocommerce-Price-amount.amount").text(product_price.toFixed(2));
                                            jQuery("#shipping-costs .price-value .woocommerce-Price-amount.amount").text(shipping_costs.toFixed(2));
                                            jQuery("#total-costs .price-value .woocommerce-Price-amount.amount").text(total_costs.toFixed(2));
                                        } else {
                                            alert("Error: " + responseObj.message);
                                            jQuery("#plz-popup").show();
                                        }
                                    },
                                    error: function(xhr, status, error) {
                                        alert("Error: " + error);
                                        console.log(xhr.responseText);
                                    }
                                });
                            }


                            //function to change postleitzahl cookie
                            function change_postleitzahl(plz) {
                                fill_total_cost_overview(plz);
                                set_postleitzahl_output(plz);
                                document.cookie="plz="+plz+";path=/";
                            }

                            //function to get postleitzahl cookie
                            function get_postleitzahl_cookie() {
                                var plz = "";
                                var allcookies = document.cookie;
                                cookiearray = allcookies.split(';');
                                for(var i=0; i<cookiearray.length; i++) {
                                    name = cookiearray[i].split('=')[0];
                                    value = cookiearray[i].split('=')[1];
                                    if (name.trim() === "plz") {
                                        plz = value;
                                    }
                                }
                                return plz;
                            }

                            //function to set postleitzahl output
                            function set_postleitzahl_output(plz) {
                                jQuery("#plz-output").text("Lieferung zur Postleitzahl: " + plz);
                            }


                            // Check if postleitzahl-cookie has a value
                            var plz = get_postleitzahl_cookie();

                            if (plz === "") {
                                // If postleitzahl is empty, show popup
                                jQuery("#plz-popup").fadeIn();
                            }else{
                                // If postleitzahl is not empty, get shipping costs
                                set_postleitzahl_output(plz)
                                fill_total_cost_overview(plz);
                            }
                            
                            // Handle popup and form submission
                            jQuery(".plz-popup").click(function(event) {
                                if (event.target === this) {
                                    var plz = jQuery("#plz-input").val();
                                    set_postleitzahl_output(plz);
                                    change_postleitzahl(plz);
                                    jQuery(this).fadeOut();
                                }
                            });

                            jQuery(".btn-popup").click(function() {
                                jQuery("#plz-popup").fadeIn();
                                jQuery("#plz-input").val("");
                                jQuery("#plz-input").focus();
                            });

                            jQuery("#plz-form").submit(function(event) {
                                event.preventDefault();
                                var plz = jQuery("#plz-input").val();
                                if (plz !== "") {
                                    // Update Postleitzahl
                                    change_postleitzahl(plz);
                                    
                                    // Close popup
                                    jQuery("#plz-popup").fadeOut();
                                } else {
                                    alert("Bitte gen Sie eine Postleizahl ein.");
                                }
                            });
                        });
                    </script>
                <?php
            }
        }

        /**
         * Get shipping costs from Shipping Method
         */
        function get_shipping_costs() {
            $plz = $_POST['plz'];
            try{
                $shipping_costs = $this->shipping_method->get_shipping_costs($plz);

                //change shipping postcode of user
                $woocommerce = WC();
                $woocommerce->customer->set_shipping_postcode($plz);
                $woocommerce->customer->set_shipping_city("Berlin"); // mit API ersetzen
                //$woocommerce->customer->set_shipping_state("NRW"); -> herausfinden wie
                $woocommerce->customer->set_shipping_country("DE");
                $woocommerce->customer->save();

                $ret = array(
                    'status' => "success",
                    'shipping_costs' => $shipping_costs
                );
            }catch(Exception $e){
                $ret = array(
                    'status' => "error",
                    'message' => $e->getMessage()
                );
            }

            echo json_encode($ret);
            wp_die();
        }
    }

    $Versand_Kosten_Beez_Plugin = new Versand_Kosten_Beez_Plugin( __FILE__ );
    function Versand_Kosten_Beez_Plugin_action_links( $links ) {
        $links[] = '<a href="'. menu_page_url( VERSAND_KOSTEN_BEEZ_SLUG, false ) .'&tab=shipping&section=versand-kosten-beez-shipping-method">Settings</a>';
        return $links;
    }

endif; 



?>