<?php


if(!class_exists('Versand_Kosten_Beez_Shipping_Week_Selection')) {
    class Versand_Kosten_Beez_Shipping_Week_Selection{
        static ?Versand_Kosten_Beez_Shipping_Week_Selection $instance = null;

        private function __construct(){
            add_shortcode('shipping_week_selection', array($this, 'add_shipping_week_selection'));
        }

        public static function getInstance(){
            if(self::$instance == null){
                self::$instance = new Versand_Kosten_Beez_Shipping_Week_Selection();
            }
            return self::$instance;
        }

        function add_shipping_week_selection(){
            ?>


            <div id="shipping-informations-week">
                <label>Lieferwoche *</label>
                <select id="select-lieferwochen">
                    <option value="">--Bitte auswählen --</option>
                    <?php
                    $lieferwochen = (new Versand_Kosten_Beez_Shipping_Method())->get_lieferwochen();
                    foreach($lieferwochen as $lieferwoche) {
                        $week = $lieferwoche["week"];
                        $year = $lieferwoche["year"];
                        $start = $lieferwoche["start"];
                        $end = $lieferwoche["end"];
                        $enabled = $lieferwoche["enabled"];

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
    }
}

?>