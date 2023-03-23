<?php
/**
 * Integration
 */
if ( ! class_exists( 'Versand_Kosten_Beez_Integration' ) ) :
    class Versand_Kosten_Beez_Integration extends WC_Integration {
        public $id                   = 'versand-kosten-beez-integration';
        public $method_title         = ('Versand Kosten Beez Einstellungen');
        public $method_description   = ('Einstellungen für die Berechnung der Versandkosten' );


        private float $spritpreis;
        private float $spritverbrauch;
        private float $lohnkosten;
        private float $abschreibung_pro_km;
        private float $wartungskosten;
        private int $origin_plz;


        /**
         * Init and hook in the integration.
         */
        public function __construct() {
            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables.
            $this->spritpreis           = doubleval($this->get_option( 'spritpreis' ));
            $this->spritverbrauch       = doubleval($this->get_option( 'spritverbrauch'));
            $this->lohnkosten           = doubleval($this->get_option( 'lohnkosten' ));
            $this->abschreibung_pro_km  = doubleval($this->get_option( 'abschreibung_pro_km'));
            $this->wartungskosten       = doubleval($this->get_option( 'wartungskosten' ));
            $this->origin_plz           = intval($this->get_option( 'origin_plz' ));

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
                'origin_plz' => array(
                    'title'             => __( 'Versand Postleitzahl'),
                    'description'       => __( 'Postleitzahl des Versandorts'),
                    'type'              => 'text',
                    'css'      => 'width:170px;',
                ),
            );
        }

        /**
         * Validate German zip code with GeoNames API
         * @License: Creative COmmons Attribution 4.0 License -> https://creativecommons.org/licenses/by/4.0/
         * -> TODO: Geonames muss mit Link genannt werden (oder man baut es aus)
         * Premium-Lizenzen möglich
         * @param string $plz
         * @return bool
         */
        function validate_german_zip($plz) {
            if(!preg_match('/^\d{5}$/',$plz)) return false;
            $url = 'http://geonames.org/postalCodeLookupJSON?postalcode='.$plz.'&country=DE';
            $response = file_get_contents($url);
            $resp_arr = json_decode($response,true);
            $rw = isset($resp_arr["postalcodes"]) && count($resp_arr["postalcodes"]) >= 1;
            return $rw; 
        }
        

        /**
         * Calculate distance and duration between two zip codes with Google Maps API
         * TODO: Google Maps API key ersetzen
         * @param string $origin_zip
         * @param string $destination_zip
         * @param string $travel_mode
         * @return array $distance, $duration in meters and seconds
         * @throws Exception
         */
        private function get_distance_duration($origin_zip, $destination_zip, $travel_mode = 'driving') {
            //DUMMY-WERT:
            return array('distance' => 120000, 'duration' => 7200);


            $api_key = 'your_api_key';
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
                throw new Exception('Error in Google Maps API');
            }
        }
        

        /**
        * Shipping cost function.
        * @param string $destination_plz
        * @return double $versandkosten
        */
        public function get_shipping_costs($destination_plz) {
            // Validate German zip code
            if($this->validate_german_zip($destination_plz)){
                // Get distance and duration between origin and destination
                $result = $this->get_distance_duration($this->origin_plz, $destination_plz);
                    
                // Convert meters to kilometers and seconds to hours
                $entfernung = $result['distance'] / 1000;
                $fahrzeit = $result['duration'] / 3600;
                    
                // Calculate shipping costs and return
                return $this->calculate_shipping_costs($entfernung, $fahrzeit);
            }else{
                throw new Exception('Der eingegebene Wert entspricht keiner deutschen Postleitzahl.');
            }
        }


        /**
         * Calculate shipping costs
         * @param double $entfernung in kilometers
         * @param double $fahrzeit in hours
         * @return double $versandkosten
         */
        private function calculate_shipping_costs($entfernung, $fahrzeit){
            if(isset($entfernung) && isset($fahrzeit) && $entfernung != null && $fahrzeit != null && $entfernung > 0 && $fahrzeit > 0 &&
                isset($this->spritpreis) && isset($this->spritverbrauch) && isset($this->abschreibung_pro_km)  && isset($this->lohnkosten) && isset($this->wartungskosten) &&
                $this->spritpreis != null && $this->spritverbrauch != null && $this->abschreibung_pro_km != null && $this->lohnkosten != null && $this->wartungskosten != null
            ){
                // Convert all values to double
                $entfernung = doubleval($entfernung);
                $fahrzeit = doubleval($fahrzeit);
                $spritpreis = doubleval($this->spritpreis);
                $spritverbrauch = doubleval($this->spritverbrauch);
                $abschreibung_pro_km = doubleval($this->abschreibung_pro_km);
                $lohnkosten = doubleval($this->lohnkosten);
                $wartungskosten = doubleval($this->wartungskosten);

                // Calculate costs per km
                $kosten_pro_km = (($spritpreis * $spritverbrauch) / 100) - $abschreibung_pro_km + $wartungskosten;

                // Calculate total costs
                $versandkosten = (($entfernung * $kosten_pro_km) + ($lohnkosten * $fahrzeit)) * 2;

                // round to 2 decimal places and return
                return round($versandkosten, 2);
            }
            throw new Exception('Es sind nicht alle Variablen für die Versandkostenberechnung gesetzt.');
        }
    }
endif; 
?>