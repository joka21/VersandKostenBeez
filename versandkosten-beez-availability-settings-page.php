<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


if(!class_exists('VersandkostenBeezAvailabilitySettingsPage')) :

    // Load needed classes
    require_once('versandkosten-beez-shipping-availability-dao.php');
    require_once('versandkosten-beez-lieferwoche.php');

    class VersandkostenBeezAvailabilitySettingsPage {
        private VersandkostenBeezAvailabilityDao $shipping_availability_controller;

        private string $option_group = VERSANDKOSTEN_BEEZ_SHIPPING_MANAGEMENT_SLUG ?? 'Versandkosten Beez Lieferwochen Management';
        private string $option_name = 'VersandkostenBeezAvailabilitySettingsPage';


        /**
         * Plugin options page constructor.
         */
        public function __construct(){
            $this->init();
        }

        /**
         * Init the plugin options page.
         */
        public function init(){
            $this->shipping_availability_controller = VersandkostenBeezAvailabilityDao::getInstance();
            add_action( 'admin_init', array($this, 'settings_init' ));
            add_action( 'admin_menu', array($this, 'versandkosten_beez_options_page' ));
        }

        /**
         * Register settings
         */
        function settings_init() {
            // Register a new settings page
            register_setting($this->option_group, $this->option_name);
        }

        /**
         * Add the menu page
         */
        function versandkosten_beez_options_page() {
            add_menu_page(
                $this->option_group,
                'Versandkosten Beez Lieferwochen Management',
                'manage_options',
                $this->option_group,
                array($this, 'versandkosten_beez_options_page_html')
            );
        }

        /**
         * Output the options page
         */
        private function loadSettingsHtml(){
            $year = $_GET['year'] ?? date('Y');

            // add change to of year (left, right, input)
            $this->loadYearChangeHtml($year);

            // add table / list with all weeks of the year
            $this->loadWeeksTableHtml($year);

        }

        /**
         * Output year panel
         * @param $year
         * @return void
         */
        private function loadYearChangeHTML($year){
            ?>
            <div class="year-change">
                <button onclick="window.location.href = '<?php echo admin_url('admin.php?page='.$this->option_group.'&year=');?>' + (parseInt(document.getElementsByName('year')[0].value) - 1)">Vorheriges Jahr</button>
                <input type="number" name="year" value="<?php echo esc_attr($year);?>" onchange="window.location.href = '<?php echo admin_url('admin.php?page='.$this->option_group.'&year=');?>' + this.value">
                <button onclick="window.location.href = '<?php echo admin_url('admin.php?page='.$this->option_group.'&year=');?>' + (parseInt(document.getElementsByName('year')[0].value) + 1)">Nächstes Jahr</button>
            </div>
            <?php
        }

        /**
         * Output the table with all weeks of the year as a form
         * @param $year
         * @return void
         */
        private function loadWeeksTableHtml($year){
            ?>
            <form action="<?php echo admin_url('admin.php?page='.$this->option_group.'&year='.$year);?>" method="post">
                <table class="weeks-table">
                    <thead>
                    <tr>
                        <th>Woche</th>
                        <th>Maximale Kapazität</th>
                        <th>Verfügbare Kapazität</th>
                        <th>Temporär reservierte Kapazität</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    for($i = 1; $i <= 52; $i++){
                        // skip weeks that are in the past or this week
                        if($i < date('W',  strtotime('+1 week')) && $year <= date('Y')){
                            continue;
                        }

                        $week = new VersandkostenBeezLieferwoche($i, $year);
                        ?>
                        <tr style="text-align: center; font-size: 15px;">
                            <td><?php echo "KW ".$week->getWeek()." (".$week->getStart()." - ".$week->getEnd()." ";?></td>
                            <td><input type="number" name="week_<?php echo $week->getWeek();?>" value="<?php echo $week->getMaxCapacity();?>"></td>
                            <td style="background-color: <?php echo $week->getAvailableCapacity() === 0 ? 'red' : 'green';?>; color: white; ">
                                <?php echo $week->getAvailableCapacity(); ?>
                            </td>
                            <td>
                                <?php echo $week->getTemporarilyReservedCapacity(); ?>
                            </td>
                        </tr>
                        <?php
                    }
                    ?>
                    </tbody>
                </table>

                <?php
                // add save button
                $this->loadSaveButtonHtml();
                ?>

            </form>

            <?php
        }



        /**
         * Process the input of the new availability
         */
        public function processForm(){
            // get the year
            $year = $_GET['year'] ?? date('Y');

            // change the values in the database
            $changed = false;
            $success = true;
            foreach($_POST as $key => $value){
                if(strpos($key, 'week_') !== false){
                    $week = substr($key, 5);
                    $success = $this->shipping_availability_controller->set_availabilty($week, $year, $value) & $success;
                    $changed = true;
                }
            }

            // show success or error message
            if($changed){
                if($success){
                    $this->loadSuccessMessage();
                } else {
                    $this->loadErrorMessage();
                }
            }
        }

        /**
         * Show the success message
         */
        function loadSuccessMessage(){
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e( 'Die Änderungen wurden erfolgreich gespeichert!', 'sample-text-domain' ); ?></p>
            </div>
            <?php
        }

        /**
         * Show the error message
         */
        function loadErrorMessage(){
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e( 'Es ist ein Fehler aufgetreten!', 'sample-text-domain' ); ?></p>
            </div>
            <?php
        }


        /**
         * Output the save button
         */
        private function loadSaveButtonHtml(){
            ?>
            <div class="save-button">
                <input type="submit" value="Speichern">
            </div>
            <?php
        }


        /**
         * Top level menu callback function
         */
        function versandkosten_beez_options_page_html() {
            // check user capabilities
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $this->processForm();

            ?>
            <div class="wrap">
                <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
                <?php $this->loadSettingsHtml();?>
            </div>
            <?php
        }
    }

endif;