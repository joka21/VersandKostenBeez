'use strict';

jQuery(document).ready(function() {
    // function to fill the total cost overview
    function fill_total_cost_overview(plz) {
        var post_id = jQuery("#post").val();
        jQuery.ajax({
            url: php_vars.ajax_url,        //' . admin_url('admin-ajax.php') . '
            type: "POST",
            data: {
                action: "get_shipping_costs",
                post_id: post_id,
                postleitzahl: plz
            },
            success: function(response) {
                var product_name = php_vars.product_name //"'.wc_get_product()->get_name().'";
                var product_price = parseFloat(php_vars.product_price, 2); //"'. wc_get_product()->get_price() .'"
                var shipping_costs = parseFloat(response, 2);
                var total_costs = product_price + shipping_costs;

                jQuery("#product-name").text(product_name);
                jQuery("#single-product-price .price-value .woocommerce-Price-amount.amount").text(product_price.toFixed(2));
                jQuery("#shipping-costs .price-value .woocommerce-Price-amount.amount").text(shipping_costs.toFixed(2));
                jQuery("#total-costs .price-value .woocommerce-Price-amount.amount").text(total_costs.toFixed(2));

            },
            error: function(xhr, status, error) {
                alert("Error: " + error);
                console.log(xhr.responseText);
            }
        });
    }


    //function to change postleitzahl cookie
    function change_postleitzahl(plz) {
        document.cookie = "postleitzahl=" + plz;
        set_postleitzahl_output(postleitzahl);
        fill_total_cost_overview(plz);
    }

    //function to get postleitzahl cookie
    function get_postleitzahl_cookie() {
        var postleitzahl = "";
        var allcookies = document.cookie;
        cookiearray = allcookies.split(';');
        for(var i=0; i<cookiearray.length; i++) {
            let name = cookiearray[i].split('=')[0];
            let value = cookiearray[i].split('=')[1];
            if (name.trim() === "postleitzahl") {
                postleitzahl = value;
            }
        }
        return postleitzahl;
    }

    //function to set postleitzahl output
    function set_postleitzahl_output(plz) {
        jQuery("#postleitzahl-output").text("Lieferung zur Postleitzahl: " + plz);
    }


    // Check if postleitzahl-cookie has a value
    var postleitzahl = get_postleitzahl_cookie();

    if (postleitzahl === "") {
        // If postleitzahl is empty, show popup
        jQuery("#postleitzahl-popup").fadeIn();
    }else{
        // If postleitzahl is not empty, get shipping costs
        set_postleitzahl_output(postleitzahl)
        fill_total_cost_overview(postleitzahl);
    }
    
    // Handle popup and form submission
    jQuery(".postleitzahl-popup").click(function(event) {
        if (event.target === this) {
            var postleitzahl = jQuery("#postleitzahl-input").val();
            set_postleitzahl_output(postleitzahl);
            change_postleitzahl(postleitzahl);
            jQuery(this).fadeOut();
        }
    });

    jQuery(".btn-popup").click(function() {
        jQuery("#postleitzahl-popup").fadeIn();
        jQuery("#postleitzahl-input").val("");
        jQuery("#postleitzahl-input").focus();
    });

    jQuery("#postleitzahl-form").submit(function(event) {
        event.preventDefault();
        var postleitzahl = jQuery("#postleitzahl-input").val();
        if (postleitzahl !== "") {
            // Update Postleitzahl
            change_postleitzahl(postleitzahl);
            
            // Close popup
            jQuery("#postleitzahl-popup").fadeOut();
        } else {
            alert("Please enter a value for the postleitzahl.");
        }
    });
});