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

            // Add js to header
            add_action('wp_head', array($this, 'add_popup_jquery'));

            //add custom product css to header
            add_action('wp_head', array($this, 'add_product_css'));

            // Add ajax action to get shipping costs
            add_action('wp_ajax_get_shipping_costs', array($this, 'get_shipping_costs'));
            add_action('wp_ajax_nopriv_get_shipping_costs', array($this, 'get_shipping_costs'));         
            
            // change customer info by plz on add to cart
            add_action('woocommerce_add_to_cart', array($this, 'custom_add_to_cart'));
        }

        /**
         * Add popup and input field to WooCommerce product page
         */
        function add_popup_input_field() {
            if(is_product()) {
                $plz = $_COOKIE['plz'] ?? "";

                //Add text with current postleitzahl saved in cookie
                // Add button to open popup, popup and input field
                ?>
                    <div id="shipping-informations-product">
                        <div>
                            <span id="plz-output">Postleitzahl: <? echo htmlspecialchars($plz); ?></span>
                            <button type="button" class="btn-popup">Postleitzahl ändern</button>
                        </div>

                        <div id="shipping-informations-week">
                            <label>Lieferwoche *</label>
                            <select id="select-lieferwochen">
                                <option value="">--Bitte auswählen --</option>
                                <?php
                                    $current_calendar_week = date("W");
                                    $lieferwochen = array();
                                    for($i = 0; $i < 4; $i++) {
                                        //get start and end date of current calendar week
                                        $start_date = date("d.m.Y", strtotime("+" . ($i - 1) . " week monday"));
                                        $end_date = date("d.m.Y", strtotime("+" . $i . " week friday"));

                                        //TODO: Anbindung an Datenbank hinzufügen
                                        $lieferwochen[] = array(
                                            "week" => $current_calendar_week + $i, 
                                            "start" => $start_date,
                                            "end" => $end_date,
                                            "enabled" =>  rand(0, 1) == 1 ? true : false
                                        );
                                    }

                                    foreach($lieferwochen as $lieferwoche) {
                                        $week = $lieferwoche["week"];
                                        $start = $lieferwoche["start"];
                                        $end = $lieferwoche["end"];
                                        $enabled = $lieferwoche["enabled"];

                                        echo "<option value='$week'";
                                        if($enabled) {
                                            echo ">KW $week ($start - $end)";
                                        } else {
                                            echo "disabled>
                                                KW $week ($start - $end) - ausgebucht!";
                                        }
                                        echo "</option>";
                                    }
                                ?>
                            </select>
                        </div>
                    </div>

                    <dialog id="plz-popup" class="plz-popup">
                        <div class="plz-popup-content">
                            <form id="plz-form">
                                <label for="plz-input">Bitte geben Sie die Postleitzahl des Liefergebiets ein:</label>
                                <input type="text" id="plz-input" name="plz" minlength="5" maxlength="5" title="Bitte geben Sie eine Postleitzahl mit Stellen ein" required>
                                <button type="submit" class="btn-popup-submit">Speichern</button>
                            </form>
                        </div>
                    </dialog>
                
                    <div id="total-cost-overview">
                        <span>Preise:</span>
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
                                // return a ajax promise
                                return jQuery.ajax({
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
                                        console.log(xhr.responseText);
                                    }
                                });
                            }

                            //function to set postleitzahl output
                            function set_postleitzahl_output(plz) {
                                jQuery("#plz-output").text("Lieferung zur Postleitzahl: " + plz);
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
                            
                            function open_popup() {
                                jQuery("#plz-popup").fadeIn();
                                jQuery("#plz-input").val("");
                                jQuery("#plz-input").focus();
                            }

                            jQuery(".btn-popup").click(open_popup);

                            jQuery("#plz-form").submit(function(event) {
                                event.preventDefault();
                                let reload = get_postleitzahl_cookie() === "";

                                let plz = jQuery("#plz-input").val();
                                if (plz !== "") {
                                    // Update Postleitzahl
                                    change_postleitzahl(plz);
                                
                                    // Close popup
                                    jQuery("#plz-popup").fadeOut();
                                } else {
                                    alert("Bitte geben Sie eine Postleitzahl ein.");
                                }
                            });

                            function startup(){
                                // Check if postleitzahl-cookie has a value
                                let plz = get_postleitzahl_cookie();

                                if (plz === "") {
                                    // If postleitzahl is empty, show popup
                                    open_popup();
                                }else{
                                    // If postleitzahl is not empty, get shipping costs
                                    set_postleitzahl_output(plz);
                                    fill_total_cost_overview(plz);
                                }
                            }

                            startup();
                        });
                    </script>
                <?php
            }
        }

        function add_product_css(){
            if(is_product()){
                //add css from css/product.css
                wp_enqueue_style('product', plugin_dir_url(__FILE__) . 'css/product.css');
            }
        }

        /**
         * Get shipping costs from Shipping Method
         */
        function get_shipping_costs() {
            $plz = $_POST['plz'];
            try{
                $shipping_costs = $this->shipping_method->get_shipping_costs($plz);
                
                $this->change_customer_info_by_plz($plz);
                
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

        /**
         * Change shipping information of user
         */
        function change_customer_info_by_plz($plz){
            //change shipping information of user
            GLOBAL $woocommerce;
            $woocommerce->customer->set_shipping_postcode($plz);
            $woocommerce->customer->set_shipping_country("DE");

            try{
                $info = $this->shipping_method->get_plz_info($plz);
                $woocommerce->customer->set_shipping_city($info['placeName']);
                $woocommerce->customer->set_shipping_state("DE-".$info['adminCode1']);
            }catch(Exception $e){/* do nothing*/}

            $woocommerce->customer->save();
        }
        
        /**
         * Change customer info by plz on add to cart
         */
        function custom_add_to_cart(){
            $plz = $_COOKIE['plz'] ?? "";
            $this->change_customer_info_by_plz($plz);
        }

    }

    $Versand_Kosten_Beez_Plugin = new Versand_Kosten_Beez_Plugin( __FILE__ );
    function Versand_Kosten_Beez_Plugin_action_links( $links ) {
        $links[] = '<a href="'. menu_page_url( VERSAND_KOSTEN_BEEZ_SLUG, false ) .'&tab=shipping&section=versand-kosten-beez-shipping-method">Settings</a>';
        return $links;
    }


endif; 


    /*

    // Hook in
    add_filter( 'woocommerce_default_address_fields' , 'custom_override_checkout_fields', 50, 1 );

    // Our hooked in function – $fields is passed via the filter!
    function custom_override_checkout_fields( $fields ) {
        $fields['billing']['shipping_phone'] = array(
            'label'     => __('Phone', 'woocommerce'),
        'placeholder'   => _x('Phone', 'placeholder', 'woocommerce'),
        'required'  => false,
        'class'     => array('form-row-wide'),
        'clear'     => true
        );

        return $fields;
    }

    /**
     * Display field value on the order edit page
     */
    /*
    add_action( 'woocommerce_admin_order_data_after_billing_address', 'my_custom_checkout_field_display_admin_order_meta', 10, 1 );

    function my_custom_checkout_field_display_admin_order_meta($order){
        echo '<p><strong>'.__('Phone From Checkout Form').':</strong> ' . get_post_meta( $order->get_id(), '_shipping_phone', true ) . '</p>';
    }*/


?>