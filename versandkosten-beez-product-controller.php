<?php


if( !class_exists('versandkosten_beez_product_controller')):

    /**
     * Class for controlling the product page
     */
    class versandkosten_beez_product_controller{
        private Versand_Kosten_Beez_Shipping_Method $shipping_method;
        private static bool $hooks_loaded = false;

        /**
         * Construct the plugin
         */
        public function __construct(){
            // Init shipping method
            $this->shipping_method = new Versand_Kosten_Beez_Shipping_Method();

            if(!self::$hooks_loaded) {
                self::$hooks_loaded = true;

                // Add popup and input field to WooCommerce product page
                add_action('woocommerce_single_product_summary', array($this, 'add_popup_input_field'), 30);

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
                        <span id="plz-output">Postleitzahl: <?php echo htmlspecialchars($plz); ?></span>
                        <button type="button" class="btn-popup">Postleitzahl ändern</button>
                    </div>

                    <?php
                        $this->add_shipping_week_selection();
                    ?>
                </div>

                <dialog id="plz-popup" class="plz-popup">
                    <div class="plz-popup-content">
                        <form id="plz-form">
                            <label for="plz-input">Bitte geben Sie die Postleitzahl des Liefergebiets ein:</label>
                            <input type="text" id="plz-input" name="plz" minlength="5" maxlength="5" required>
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
            if(is_product()) {
                ?>
                <script>
                    jQuery(document).ready(function() {
                        // create hidden input for lieferwoche and lieferjahr
                        jQuery(".cart").append('' +
                            '<input type="hidden" name="lieferwoche" id="lieferwoche-input-hidden">' +
                            '<input type="hidden" name="lieferjahr" id="lieferjahr-input-hidden">'
                        );

                        // fill hidden input with value of selected shipping week and year
                        jQuery("#select-lieferwochen").change(function() {
                            jQuery("#lieferwoche-input-hidden").val(jQuery(this).val());
                            jQuery("#lieferjahr-input-hidden").val(jQuery(this).find(":selected").data("year"));
                        });

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

                                    if(responseObj.status === "success") {
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
                                error: function(xhr) {
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
                            document.cookie="plz="+plz+";path=/;days=30";
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

        /**
         * Add css to product page
         */
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
            $plz = $_POST['plz'] ?? "";
            try{
                // get shipping costs
                $shipping_costs = $this->shipping_method->get_shipping_costs($plz);

                // change shipping information of user to plz
                $this->change_customer_info_by_plz($plz);

                // return shipping costs
                $ret = array(
                    'status' => "success",
                    'shipping_costs' => $shipping_costs
                );
            }catch(Exception $e){
                // return error
                $ret = array(
                    'status' => "error",
                    'message' => $e->getMessage()
                );
            }
            // return json and die
            wp_die(json_encode($ret));
        }

        /**
         * Change shipping information of user
         */
        function change_customer_info_by_plz($plz){
            //validate plz
            if(!$this->shipping_method->validate_german_zip($plz)){
                return;
            }

            //change shipping information of user
            GLOBAL $woocommerce;
            $woocommerce->customer->set_shipping_postcode($plz);
            $woocommerce->customer->set_shipping_country("DE");

            try{
                $info = $this->shipping_method->get_plz_info($plz);
                $woocommerce->customer->set_shipping_city($info['placeName']);
                $woocommerce->customer->set_shipping_state("DE-".$info['adminCode1']);
            }catch(Exception $e){/* do nothing*/}

            // save changes
            $woocommerce->customer->save();
        }


        /**
         * Adds shipping week selection html to product page
         * @return void
         */
        public function add_shipping_week_selection(){
            ?>
            <div id="shipping-informations-week">
                <label for="select-lieferwochen">Lieferwoche *</label>
                <select id="select-lieferwochen">
                    <option value="">--Bitte auswählen --</option>
                    <?php
                    $lieferwochen = $this->shipping_method->get_lieferwochen();
                    foreach($lieferwochen as $lieferwoche) {
                        $week = $lieferwoche->getWeek();
                        $year = $lieferwoche->getYear();
                        $start = $lieferwoche->getStart();
                        $end = $lieferwoche->getEnd();
                        $enabled = $lieferwoche->getEnabled();

                        echo "<option data-year='$year' value='$week'";
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

            <?php
        }

        /**
         * Change customer info by plz on add to cart
         */
        function custom_add_to_cart() {
            $plz = $_COOKIE['plz'] ?? "";
            $this->change_customer_info_by_plz($plz);
        }
    }
endif;

?>