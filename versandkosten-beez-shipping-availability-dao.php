<?php
    if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        if ( ! class_exists('VersandkostenBeezAvailabilityDao')){
            class VersandkostenBeezAvailabilityDao{
                private $table_name_capacity;
                private $table_name_takenavailability;
                private $wpdb;

                private static ?VersandkostenBeezAvailabilityDao $instance = null;

                public static function getInstance(){
                    if(self::$instance == null){
                        self::$instance = new VersandkostenBeezAvailabilityDao();
                    }
                    return self::$instance;
                }
                
                private function __construct(){
                    //create database connection with wordpress
                    global $wpdb;
                    $this->wpdb = $wpdb;
                    $this->table_name_capacity = $wpdb->prefix . 'breez_shipping_availability_capicity';
                    $this->table_name_takenavailability = $wpdb->prefix . 'breez_shipping_availability_takenavailability';
                    $this->create_table();
                }

                private function drop_table($name){
                    $sql = "
                        DROP TABLE IF EXISTS $name;
                    ";
                    $this->wpdb->query($sql);
                }

                public function create_table(){
                    //$this->drop_table($this->table_name_takenavailability);
                    //$this->drop_table($this->table_name_capacity );

                    $sql_table_capacity = "
                        CREATE TABLE IF NOT EXISTS $this->table_name_capacity (
                            calendar_week int(2) NOT NULL,
                            year int(4) NOT NULL,
                            availability int(1) NOT NULL DEFAULT 0,
                            PRIMARY KEY (calendar_week, year)
                        );";

                    $this->wpdb->query($sql_table_capacity);

                    $sql_table_taken_availability = "
                        CREATE TABLE IF NOT EXISTS $this->table_name_takenavailability (
                            calendar_week int(2) NOT NULL,
                            year int(4) NOT NULL,
                            order_id int(11) NOT NULL,
                            PRIMARY KEY (calendar_week, year, order_id),
                            FOREIGN KEY (calendar_week, year) REFERENCES $this->table_name_capacity(calendar_week, year)
                            ON DELETE CASCADE
                        );
                    ";
                    $this->wpdb->query($sql_table_taken_availability);

                    //check if trigger doesnot exist
                    $sql = "SELECT count(*) FROM information_schema.triggers WHERE trigger_name = '$this->table_name_takenavailability"."_trigger'";
                    $count = (int) $this->wpdb->get_var($sql);
                    if($count == 0) {
                        //create a mysql db trigger that rejects decreases in availability if the availability is smaller or equal to the taken availability
                        $sql_trigger = "
                            CREATE TRIGGER $this->table_name_takenavailability" . "_trigger
                            BEFORE INSERT ON $this->table_name_takenavailability
                            FOR EACH ROW
                            BEGIN
                                IF (SELECT availability FROM $this->table_name_capacity  WHERE calendar_week = NEW.calendar_week and year = NEW.year) < (SELECT count(*) FROM $this->table_name_takenavailability WHERE calendar_week = NEW.calendar_week and year = NEW.year) THEN
                                    RESIGNAL;
                                END IF;
                            END;
                        ";
                        $this->wpdb->query($sql_trigger);
                    }


                    //create a db trigger that rejects decreases in availability on table_name_capacity if the availability is smaller or equal to the taken availability by table_name_takenavailability

                    $sql = "SELECT count(*) FROM information_schema.triggers WHERE trigger_name = '$this->table_name_capacity"."_trigger2'";
                    $count = (int) $this->wpdb->get_var($sql);
                    if($count == 0) {
                        //create a mysql db trigger that rejects decreases in availability if the availability is smaller or equal to the taken availability
                        $sql_trigger = "
                            CREATE TRIGGER $this->table_name_capacity" . "_trigger2
                            BEFORE UPDATE ON $this->table_name_capacity
                            FOR EACH ROW
                            BEGIN
                                DECLARE taken_availability int(1);
                                SELECT count(*) INTO taken_availability FROM $this->table_name_takenavailability WHERE calendar_week = NEW.calendar_week and year = NEW.year;
                                IF NEW.availability < taken_availability THEN
                                    RESIGNAL;
                                END IF;
                            END;
                        ";
                        $this->wpdb->query($sql_trigger);
                    }
                }

                /**
                 * Creates a new calendar week if it does not exist
                 * @param $calendar_week
                 * @param $year
                 * @return void
                 */
                public function create_calendar_week_if_not_exists($calendar_week, $year){
                    $calendar_week = (int)$calendar_week;
                    $year = (int)$year;

                    $sql = "SELECT count(*) FROM $this->table_name_capacity WHERE calendar_week = %s and year = %s";
                    $count = (int) $this->wpdb->get_var($this->wpdb->prepare($sql, $calendar_week, $year));

                    if($count == 0){
                        $this->set_availabilty($calendar_week, $year, 0);
                    }
                }

                /**
                 * Sets the availability for a given calendar week
                 * @param $calendar_week
                 * @param $year
                 * @param $availability
                 * @return bool
                 */
                public function set_availabilty($calendar_week, $year, $availability) : bool{
                    $calendar_week = (int)$calendar_week;
                    $year = (int)$year;
                    $availability = (int)$availability;

                    $sql = "INSERT INTO $this->table_name_capacity (calendar_week, year, availability) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE availability = %s";
                    $this->wpdb->query($this->wpdb->prepare($sql, $calendar_week, $year, $availability, $availability));
                    //return $this->wpdb->last_error === '';
                    return ($this->wpdb->last_error . " " . $this->wpdb->last_query);
                }

                public function get_max_availability($calendar_week, $year){
                    $this->create_calendar_week_if_not_exists($calendar_week, $year);

                    $sql = "SELECT availability FROM $this->table_name_capacity WHERE calendar_week = %s and year = %s";
                    return (int) $this->wpdb->get_var($this->wpdb->prepare($sql, $calendar_week, $year));
                }


                public function get_taken_availability($calendar_week, $year){
                    $calendar_week = (int)$calendar_week;
                    $year = (int)$year;

                    $this->create_calendar_week_if_not_exists($calendar_week, $year);

                    $sql = "SELECT count(*) FROM $this->table_name_takenavailability WHERE calendar_week = %s and year = %s";
                    return (int) $this->wpdb->get_var($this->wpdb->prepare($sql, $calendar_week, $year));
                }


                public function is_available($calendar_week, $year){
                    $calendar_week = (int)$calendar_week;
                    $year = (int)$year;

                    // get current day info
                    $current_day = date('w');
                    $current_week = date('W');
                    $current_year = date('Y');

                    // check if the availability is not 0 and if the week is not in the past and today is not friday or later
                    return ($this->get_max_availability($calendar_week, $year) > $this->get_taken_availability($calendar_week, $year)) &&
                        ($year >= $current_year && ($calendar_week > $current_week || ($calendar_week == $current_week && $current_day < 5)));
                }

                public function decrease_availabilty($calendar_week, $year, $order_id){
                    $calendar_week = (int)$calendar_week;
                    $year = (int)$year;
                    $order_id = (int)$order_id;

                    if(!$this->is_available($calendar_week, $year)){
                        return false;
                    }

                    $sql = "INSERT INTO $this->table_name_takenavailability (calendar_week, year, order_id) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE order_id = %s";

                    $this->wpdb->query($this->wpdb->prepare($sql, array($calendar_week, $year, $order_id, $order_id)));
                    return $this->wpdb->last_error === '';
                }

                public function increase_availabilty($calendar_week, $year, $order_id){
                    $calendar_week = (int)$calendar_week;
                    $year = (int)$year;
                    $order_id = (int)$order_id;

                    $sql = "DELETE FROM $this->table_name_takenavailability WHERE calendar_week = $calendar_week and year = $year and order_id = $order_id";
                    $this->wpdb->query($this->wpdb->prepare($sql, array($calendar_week, $year, $order_id, $order_id)));
                    return $this->wpdb->last_error === '';
                }
            }
        }
    }
?>