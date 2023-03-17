<?php
/**
 * Integration
 */
if ( ! class_exists( 'Versand_Kosten_Beez_Integration' ) ) :
    class Versand_Kosten_Beez_Integration extends WC_Integration {




        /**
         * Init and hook in the integration.
         */
        public function __construct() {
            global $woocommerce;
            $this->id = 'versand-kosten-beez-integration';
            $this->method_title = __( 'Versand Kosten Beez Einstellungen');
            $this->method_description = __( 'Einstellungen für die Berechnung der Versandkosten' );

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables.
            $this->spritpreis = $this->get_option( 'spritpreis' );
            $this->spritverbrauch = $this->get_option( 'spritverbrauch' );
            $this->lohnkosten = $this->get_option( 'lohnkosten' );
            $this->abschreibung_pro_km = $this->get_option( 'abschreibung_pro_km' );
            $this->wartungskosten = $this->get_option( 'wartungskosten' );
            $this->origin_zip = $this->get_option( 'origin_zip' );

            // Actions.
            add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
        }


        /**
        * Initialize integration settings form fields.
        */
        public function init_form_fields() {
            $this->form_fields = array(
                'spritpreis' => array(
                    'title'             => __( 'Spritpreis'),
                    'description'       => __( 'Aktueller Spritpreis pro Liter'),
                    'type'              => 'decimal',
                    'css'      => 'width:170px;',
                ),
                'spritverbrauch' => array(
                    'title'             => __( 'Spritverbrauch'),
                    'description'       => __( 'Spritverbrauch in Litern pro 100 Kilometer'),
                    'type'              => 'decimal',
                    'css'      => 'width:170px;',
                ),
                'lohnkosten' => array(
                    'title'             => __( 'Lohnkosten'),
                    'description'       => __( 'Lohnkosten in € pro Stunde'),
                    'type'              => 'decimal',
                    'css'      => 'width:170px;',
                ),
                'abschreibung_pro_km' => array(
                    'title'             => __( 'Abschreibungen'),
                    'description'       => __( 'Abschreibungen in € pro km'),
                    'type'              => 'decimal',
                    'css'      => 'width:170px;',
                ),
                'wartungskosten' => array(
                    'title'             => __( 'Wartungskosten'),
                    'description'       => __( 'Wartungskosten in € pro km'),
                    'type'              => 'decimal',
                    'css'      => 'width:170px;',
                ),
                'origin_zip' => array(
                    'title'             => __( 'Versand Postleitzahl'),
                    'description'       => __( 'Postleitzahl des Versandorts'),
                    'type'              => 'text',
                    'css'      => 'width:170px;',
                ),
            );
        }

        /*
         * Validate German zip codes 
         * */
        function validate_german_zip($zip) {
            $url = 'https://www.postdirekt.de/plzserver/PlzAjaxServlet';
            $params = array(
                'finda' => 'city',
                'lang' => 'de_DE',
                'cacheable' => 'true',
                'city' => '',
                'location' => '',
                'state' => '',
                'plz' => $zip,
                'submit' => 'Suchen',
            );
            $url .= '?' . http_build_query($params);
            $response = file_get_contents($url);
            $data = json_decode($response);
            if ($data->status == 'true' && $data['count']>0) {
                return true;
            }else{
                return false;
            }
        }
        

        /**
         * Calculate distance and duration between two zip codes with Google Maps API
         * @param string $origin_zip
         * @param string $destination_zip
         * @param string $travel_mode
         * @return array $distance, $duration in meters and seconds
         */
        function get_distance_duration($origin_zip, $destination_zip, $travel_mode = 'driving') {
            $api_key = 'your_api_key'; // TODO: Replace with your Google Maps API key
            $url = 'https://maps.googleapis.com/maps/api/distancematrix/json';
            $params = array(
                'origins' => $origin_zip . ', Germany',
                'destinations' => $destination_zip . ', Germany',
                'mode' => $travel_mode,
                'key' => $api_key,
            );
            $url .= '?' . http_build_query($params);
            $response = file_get_contents($url);
            $data = json_decode($response);
            if ($data->status == 'OK') {
                $distance = $data->rows[0]->elements[0]->distance->value;
                $duration = $data->rows[0]->elements[0]->duration->value;
                return array('distance' => $distance, 'duration' => $duration);
            } else {
                return false;
            }
        }
        

        /**
        * Calculate shipping function.
        *
        * @param string $destination_plz
        * @return Integer $versandkosten
        */
        public function calculate_shipping($destination_plz) {
            if(validate_german_zip($destination_plz)){
                $result = get_distance_duration($this->$origin_zip, $destination_plz);
                if ($result) {
                    // Convert meters to kilometers and seconds to hours
                    $entfernung = $result['distance'] / 1000;
                    $fahrzeit = $result['duration'] / 3600;
                    
                    $result = calculate_shipping_costs($entfernung, $fahrzeit);
                    if ($result) {
                        return $result;
                    } else {
                        throw new Exception('Error in calculate_shipping_costs');
                        return false;
                    }
                } else {
                    throw new Exception('Error in Google Maps API');
                    return false;
                }
            }else{
                throw new Exception('Invalid German Zip Code');
                return false;
            }
        }

        public function calculate_shipping_costs($entfernung, $fahrzeit){
            if(isset($this->abschreibung_pro_km) && isset($this->spritpreis) && isset($this->spritverbrauch) && isset($this->lohnkosten)){
                $kosten_pro_km = ($this->$spritpreis * $this->$spritverbrauch)/100 - $this->$abschreibung_pro_km + $this->$wartungskosten;

                // Berechnung der Versandkosten
                $versandkosten = ($entfernung * $kosten_pro_km + ($this->$lohnkosten * $fahrzeit)) * 2;
                $versandkosten = round($kosten, 2);
                return $versandkosten;
            }
            return false;
        }
    }
endif; 
?>