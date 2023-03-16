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
            $this->id                 = 'versand-kosten-beez-integration';
            $this->method_title       = __( 'Versand Kosten Beez Integration');
            $this->method_description = __( 'Berechnet die Versandkosten!');

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables.
            $this->custom_name          = $this->get_option( 'custom_name' );

            // Actions.
            add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
        }


        /**
        * Initialize integration settings form fields.
        */
        public function init_form_fields() {
            $this->form_fields = array(
                'custom_name ' => array(
                    'title'             => __( 'Versandkosten für Krefeld und Münster'),
                    'type'              => 'text',
                    'description'       => __( 'Test' ),
                    'desc_tip'          => true,
                    'default'           => '',
                    'css'      => 'width:170px;',
                ),
            );
        }


        /**
         * Calculate distance and duration between two german zip codes.
         * @param $plz1
         * @param $plz2
         * @return array $data  Distance and duration between two zip codes.
         */
        private function calculate_distance_and_duration($plz1, $plz2){
            //calculate distance between two german zip codes
            $url = "https://www.distance24.org/route.json?stops=".$plz1."|".$plz2;
            $json = file_get_contents($url);
            $data = json_decode($json, true);
            return $data;
        }

        /**
        * Calculate shipping function.
        *
        * @param array $package Order package.
        */
        public function calculate_shipping($plz) {
            $data = $this->calculate_distance_and_duration($plz, $plz2);
            $entfernung = $data['distance'];
            $fahrzeit = $data['duration'];

            //TODO: dynamisch gestalten
            $spritpreis = 1.5;
            $spritverbrauch = 7;
            $lohnkosten = 10;
            $abschreibung_pro_km = 0.1;
            $Wartungskosten = 0.1;

            $kosten_pro_km = ($spritpreis * $spritverbrauch)/100 + ($lohnkosten * $fahrzeit) - $abschreibung_pro_km + $Wartungskosten;

            $versandkosten = $entfernung * $kosten_pro_km * 2;
            $versandkosten = round($kosten, 2);
            $versandkosten = $kosten . "€";
            $versandkosten = str_replace(".", ",", $kosten);
            $versandkosten = "Versandkosten: " . $kosten;
            return $versandkosten;
        }
    }
endif; 
?>