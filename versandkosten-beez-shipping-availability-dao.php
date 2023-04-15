<?php
    if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        if ( ! class_exists('VersandkostenBeezAvailabilityDao')){
            class VersandkostenBeezAvailabilityDao{
                private string $table_name_capacity;
                private string $table_name_takenavailability;
                private string $table_name_temporary_reserve;
                private $wpdb;

                private static ?VersandkostenBeezAvailabilityDao $instance = null;

                /**
                 * Get the instance via lazy initialization (created on first usage)
                 * @return VersandkostenBeezAvailabilityDao
                 */
                public static function getInstance(){
                    if(self::$instance == null){
                        self::$instance = new VersandkostenBeezAvailabilityDao();
                    }
                    return self::$instance;
                }

                /**
                 * Private constructor to prevent creating a new instance of the singleton
                 */
                private function __construct()
                {
                    //create database connection with wordpress
                    global $wpdb;
                    $this->wpdb = $wpdb;
                    $this->table_name_capacity = $wpdb->prefix . 'breez_shipping_capicity';
                    $this->table_name_takenavailability = $wpdb->prefix . 'breez_shipping_takenavailability';
                    $this->table_name_temporary_reserve = $wpdb->prefix . 'breez_shipping_temporary_reserve';
                    $this->create_table();
                }

                /**
                 * Private function to drop a table (only needed for testing or changing the table structure)
                 */
                private function drop_table($name)
                {
                    $sql = "
                        DROP TABLE IF EXISTS $name;
                    ";
                    $this->wpdb->query($sql);
                }

                /**
                 * Private function to create the database tables if they do not exist
                 */
                private function create_table()
                {
                    /*
                    $this->drop_table($this->table_name_takenavailability);
                    $this->drop_table($this->table_name_temporary_reserve);
                    $this->drop_table($this->table_name_capacity );
                    */

                    $table_name_capacity = $this->table_name_capacity;
                    $table_name_takenavailability = $this->table_name_takenavailability;
                    $table_name_temporary_reserve = $this->table_name_temporary_reserve;


                    // create table for managing the capacity
                    $sql_table_capacity = "
                        CREATE TABLE IF NOT EXISTS $table_name_capacity (
                            calendar_week int(2) NOT NULL,
                            year int(4) NOT NULL,
                            availability int(1) NOT NULL DEFAULT 0,
                            PRIMARY KEY (calendar_week, year)
                        );
                    ";
                    $this->wpdb->query($sql_table_capacity);

                    // create table for managing the usage of the capacity for each order
                    $sql_table_taken_availability = "
                        CREATE TABLE IF NOT EXISTS $table_name_takenavailability (
                            calendar_week int(2) NOT NULL,
                            year int(4) NOT NULL,
                            order_id BIGINT UNSIGNED NOT NULL,
                            PRIMARY KEY (calendar_week, year, order_id),
                            FOREIGN KEY (calendar_week, year) REFERENCES $table_name_capacity(calendar_week, year)
                            ON DELETE CASCADE,
                            FOREIGN KEY (order_id) REFERENCES {$this->wpdb->prefix}posts(ID)
                            ON DELETE CASCADE
                            ON UPDATE CASCADE
                        );
                    ";
                    $this->wpdb->query($sql_table_taken_availability);

                    // create a table for temporary reserving the capacity for a given uuid (it's mariadb)
                    $sql_table_temporary_availability = "
                        CREATE TABLE IF NOT EXISTS $table_name_temporary_reserve (
                            uuid varchar(36) NOT NULL,
                            calendar_week int(2) NOT NULL,
                            year int(4) NOT NULL,
                            expires_at timestamp NOT NULL DEFAULT DATE_ADD(NOW(), INTERVAL 10 MINUTE),
                            PRIMARY KEY (uuid, calendar_week, year),
                            FOREIGN KEY (calendar_week, year) REFERENCES $table_name_capacity(calendar_week, year)
                            ON DELETE CASCADE
                        );
                    ";
                    $this->wpdb->query($sql_table_temporary_availability);

                    // create a trigger that rejects new order entries if the capacity is already full
                    $sql = "SELECT count(*) FROM information_schema.triggers WHERE trigger_name = '${table_name_takenavailability}_trigger';";
                    $count = (int)$this->wpdb->get_var($sql);
                    if ($count == 0) {
                        //create a mysql db trigger that rejects decreases in availability if the availability is smaller or equal to the taken availability
                        $sql_trigger = "
                        CREATE TRIGGER ${table_name_takenavailability}_trigger
                        BEFORE INSERT ON $table_name_takenavailability
                        FOR EACH ROW
                        BEGIN
                            DECLARE taken_availability int(1);
                            DECLARE temp_reserve int(1);
                            DECLARE availability int(1);
                            
                            SELECT count(*) INTO taken_availability FROM $table_name_takenavailability WHERE calendar_week = NEW.calendar_week and year = NEW.year;
                            SELECT count(*) INTO temp_reserve FROM $table_name_temporary_reserve WHERE calendar_week = NEW.calendar_week and year = NEW.year;
                            SELECT availability INTO availability FROM $table_name_capacity WHERE calendar_week = NEW.calendar_week and year = NEW.year;
                            
                            IF (taken_availability + temp_reserve) >= availability THEN
                                RESIGNAL;
                            END IF;
                        END;
                    ";
                        $this->wpdb->query($sql_trigger);
                    }

                    // create a trigger that rejects new order entries if the capacity is already full
                    $sql = "SELECT count(*) FROM information_schema.triggers WHERE trigger_name = '${table_name_temporary_reserve}_trigger';";
                    $count = (int)$this->wpdb->get_var($sql);
                    if ($count == 0) {
                        //create a mysql db trigger that rejects decreases in availability if the availability is smaller or equal to the taken availability
                        $sql_trigger = "
                        CREATE TRIGGER ${table_name_temporary_reserve}_trigger
                        BEFORE INSERT ON $table_name_temporary_reserve
                        FOR EACH ROW
                        BEGIN
                            DECLARE taken_availability int(1);
                            DECLARE temp_reserve int(1);
                            DECLARE availability int(1);
                            
                            SELECT count(*) INTO taken_availability FROM $table_name_takenavailability WHERE calendar_week = NEW.calendar_week and year = NEW.year;
                            SELECT count(*) INTO temp_reserve FROM $table_name_temporary_reserve WHERE calendar_week = NEW.calendar_week and year = NEW.year;
                            SELECT availability INTO availability FROM $table_name_capacity WHERE calendar_week = NEW.calendar_week and year = NEW.year;
                            
                            IF (taken_availability + temp_reserve) >= availability THEN
                                RESIGNAL;
                            END IF;
                        END;
                    ";
                        $this->wpdb->query($sql_trigger);
                    }


                    //create a db trigger that rejects decreases in availability on table_name_capacity if the availability is smaller or equal to the taken availability by table_name_takenavailability
                    $sql = "SELECT count(*) FROM information_schema.triggers WHERE trigger_name = '${table_name_capacity}_trigger2';";
                    $count = (int)$this->wpdb->get_var($sql);
                    if ($count == 0) {
                        //create a mysql db trigger that rejects decreases in availability if the availability is smaller or equal to the taken availability
                        $sql_trigger = "
                        CREATE TRIGGER ${table_name_capacity}_trigger2
                        BEFORE UPDATE ON $table_name_capacity
                        FOR EACH ROW
                        BEGIN
      
                            DECLARE taken_availability int(1);
                            DECLARE temp_reserve int(1);
                           
                            SELECT count(*) INTO taken_availability FROM $table_name_takenavailability WHERE calendar_week = NEW.calendar_week and year = NEW.year;
                            SELECT count(*) INTO temp_reserve FROM $table_name_temporary_reserve WHERE calendar_week = NEW.calendar_week and year = NEW.year;
                            
                            IF NEW.availability < (taken_availability + temp_reserve) THEN
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
                public function create_calendar_week_if_not_exists($calendar_week, $year)
                {
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
                public function set_availabilty($calendar_week, $year, $availability) : bool
                {
                    $this->clean_reserve();

                    $calendar_week = (int)$calendar_week;
                    $year = (int)$year;
                    $availability = (int)$availability;

                    $sql = "INSERT INTO $this->table_name_capacity (calendar_week, year, availability) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE availability = %s";
                    $this->wpdb->query($this->wpdb->prepare($sql, $calendar_week, $year, $availability, $availability));
                    return $this->wpdb->last_error === '';
                }

                /**
                 * Returns the maximal availability for a given calendar week
                 * @param $calendar_week
                 * @param $year
                 * @return int
                 */
                public function get_max_availability($calendar_week, $year): int
                {
                    $calendar_week = (int)$calendar_week;
                    $year = (int)$year;

                    $this->create_calendar_week_if_not_exists($calendar_week, $year);

                    $sql = "SELECT availability FROM $this->table_name_capacity WHERE calendar_week = %s and year = %s";
                    return (int) $this->wpdb->get_var($this->wpdb->prepare($sql, $calendar_week, $year));
                }


                /**
                 * Returns the taken availability for a given calendar week
                 * @param $calendar_week
                 * @param $year
                 * @param null $reserved_uuid
                 * @return int
                 */
                public function get_taken_availability($calendar_week, $year, $reserved_uuid = null): int
                {
                    $this->clean_reserve();

                    $calendar_week = (int)$calendar_week;
                    $year = (int)$year;

                    $this->create_calendar_week_if_not_exists($calendar_week, $year);

                    $sql = "SELECT count(*) FROM $this->table_name_takenavailability WHERE calendar_week = %s and year = %s";
                    $ret = (int) $this->wpdb->get_var($this->wpdb->prepare($sql, $calendar_week, $year));

                    // Add reserved availability (but not for the current reserved_uuid)
                    if($reserved_uuid){
                        $sql = "SELECT count(*) FROM $this->table_name_temporary_reserve WHERE calendar_week = %s and year = %s and uuid <> %s";
                        $ret += (int) $this->wpdb->get_var($this->wpdb->prepare($sql, $calendar_week, $year, $reserved_uuid));
                    }else{
                        $sql = "SELECT count(*) FROM $this->table_name_temporary_reserve WHERE calendar_week = %s and year = %s";
                        $ret += (int) $this->wpdb->get_var($this->wpdb->prepare($sql, $calendar_week, $year));
                    }

                    return $ret;
                }


                /**
                 * Checks if the order is taking availability already
                 * @param $order_id
                 * @return bool
                 */
                public function is_order_taking_availability($order_id): bool
                {
                    $sql = "
                        SELECT count(*) FROM $this->table_name_takenavailability WHERE order_id = %s;
                    ";
                    $count = (int)$this->wpdb->get_var($this->wpdb->prepare($sql, $order_id));
                    return $count > 0;
                }

                /**
                 * Checks if the calendar week is available
                 * @param $calendar_week
                 * @param $year
                 * @param null $reserved_uuid
                 * @return bool
                 */
                public function is_available($calendar_week, $year, $reserved_uuid = null): bool
                {
                    $calendar_week = (int)$calendar_week;
                    $year = (int)$year;

                    // get current day info
                    $current_day = date('w');
                    $current_week = date('W');
                    $current_year = date('Y');

                    // check if the remaining availability is not 0 and if the week is not in the past and today is not friday or later
                    return ($this->get_max_availability($calendar_week, $year) > $this->get_taken_availability($calendar_week, $year, $reserved_uuid)) &&
                        ($year >= $current_year && ($calendar_week > $current_week || ($calendar_week == $current_week && $current_day < 5)));
                }

                /**
                 * Check if all orders are available
                 * @param $lieferwochen
                 * @param null $reserved_uuid
                 * @return array|true[]
                 */
                public function are_all_orders_available($lieferwochen, $reserved_uuid = null): array
                {
                    $ret = array('status' => true);
                    foreach($lieferwochen as $lieferwoche){
                        $calendar_week = $lieferwoche['woche'] ?? 0;
                        $year = $lieferwoche['jahr'] ?? 0;
                        if(!$this->is_available($calendar_week, $year, $reserved_uuid)){
                            $ret['status'] = false;
                            $ret['lieferwochen'][] = $lieferwoche;
                        }
                    }
                    return $ret;
                }

                /**
                 * Try to take availability for an order at given calendar week
                 * @param $calendar_week
                 * @param $year
                 * @param $order_id
                 * @param null $reserve_uuid
                 * @return bool success
                 */
                public function take_availability($calendar_week, $year, $order_id, $reserve_uuid = null): bool
                {
                    $calendar_week = (int)$calendar_week;
                    $year = (int)$year;
                    $order_id = (int)$order_id;

                    // check if the calendar week is available
                    if(!$this->is_available($calendar_week, $year, $reserve_uuid)){
                        return false;
                    }

                    // free reserved availability
                    if($reserve_uuid){
                        if(!$this->unreserve_availability($reserve_uuid)){
                            return false;
                        }
                    }

                    // take the availability
                    $sql = "INSERT INTO $this->table_name_takenavailability (calendar_week, year, order_id) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE order_id = %s";
                    $this->wpdb->query($this->wpdb->prepare($sql, array($calendar_week, $year, $order_id, $order_id)));
                    return $this->wpdb->last_error === '';
                }

                /**
                 * Delete the taken availability for an order at given calendar week
                 * @param $calendar_week
                 * @param $year
                 * @param $order_id
                 * @return bool
                 */
                public function free_availability($calendar_week, $year, $order_id): bool
                {
                    $calendar_week = (int)$calendar_week;
                    $year = (int)$year;
                    $order_id = (int)$order_id;

                    $sql = "DELETE FROM $this->table_name_takenavailability WHERE calendar_week = $calendar_week and year = $year and order_id = $order_id";
                    $this->wpdb->query($this->wpdb->prepare($sql, array($calendar_week, $year, $order_id, $order_id)));
                    return $this->wpdb->last_error === '';
                }

                /**
                 * Delete all temporary reservations that are expired
                 * @return bool
                 */
                public function clean_reserve(): bool
                {
                    $sql = "DELETE FROM $this->table_name_temporary_reserve WHERE expires_at < NOW()";
                    $this->wpdb->query($sql);
                    return $this->wpdb->last_error === '';
                }

                /**
                 * Get the amount of reserved capacity
                 * @param $week
                 * @param $year
                 * @return int
                 */
                public function get_reserved_capacity($week, $year): int
                {
                    $this->clean_reserve();

                    $sql = "SELECT COUNT(*) FROM $this->table_name_temporary_reserve WHERE calendar_week = %s AND year = %s";
                    return (int)$this->wpdb->get_var($this->wpdb->prepare($sql, $week, $year));
                }

                /**
                 * Reserve capacity or update time by 10 Minutes
                 * @param $calendar_week
                 * @param $year
                 * @param $reserve_uuid
                 * @return bool
                 */
                public function reserve_availability($calendar_week, $year, $reserve_uuid): bool
                {
                    $this->clean_reserve();

                    $calendar_week = (int)$calendar_week;
                    $year = (int)$year;
                    $reserve_uuid = (string)$reserve_uuid;

                    if(!$this->is_available($calendar_week, $year, $reserve_uuid)){
                        return false;
                    }

                    $sql = "INSERT INTO $this->table_name_temporary_reserve (calendar_week, year, uuid) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE)";
                    $this->wpdb->query($this->wpdb->prepare($sql, array($calendar_week, $year, $reserve_uuid)));
                    return $this->wpdb->last_error === '';
                }


                /**
                 * Delete the capacity reservation
                 * @param $reserve_uuid
                 * @return bool
                 */
                public function unreserve_availability($reserve_uuid): bool
                {
                    $this->clean_reserve();

                    $reserve_uuid = (string)$reserve_uuid;

                    $sql = "DELETE FROM $this->table_name_temporary_reserve WHERE uuid = %s";
                    $this->wpdb->query($this->wpdb->prepare($sql, $reserve_uuid));
                    return $this->wpdb->last_error === '';
                }
            }
        }
    }
?>