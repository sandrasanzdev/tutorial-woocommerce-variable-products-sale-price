<?php
/**
 * Display regular price alongside sale price in variable and grouped products.
 * 
 * In most cases, when a product is on sale, WooCommerce displays the (crossed-out)
 * regular price alongside the price on sale. However, when it displays a price
 * range (which can happen in variable and grouped products), it only shows the
 * final (sale) price. The following tutorial aims to show how to display the
 * regular price in variable and grouped products on sale.
 *
 * @link https://www.sandrasanz.dev/woocommerce/regular-price-variable-products-sale/
 */

 namespace SSanzDev_WC_Price_Range_Sale;

defined('ABSPATH') || exit;

/**
 * Display regular price crossed out in variable products on sale.
 * 
 * WooCommerce by default doesn't show the previous price in products on sale when
 * displaying a price range, which can happen in variable products.
 * 
 * This function imitates the content of the WC_Product_Variable::get_price_html()
 * method, but slightly changes the behavior to display the regular price alongside
 * the sale price in variable products on sale.
 * 
 * @see WC_Product_Variable::get_price_html()
 * @see wc_format_price_range()
 * @see wc_format_sale_price()
 * 
 * @param string              $price_html The price of the product in html format.
 * @param WC_Product_Variable $product    The product which price is being returned. 
 * 
 * @return string The price of the product in html format, having added the regular
 * price crossed out.
 */
function variable_product_tag_sale( $price_html, $product )
{

    // Apply only to products on sale.
    if ($product->is_on_sale() ) {

        $prices = $product->get_variation_prices(true);

        // No need to check if $prices['price'] is empty as in the original function,
        // as the woocommerce_variable_price_html filter is only applied if $prices
        // is not empty.        
        $min_sale_price = current($prices['sale_price']);
        $max_sale_price = end($prices['sale_price']);
        $min_reg_price = current($prices['regular_price']);
        $max_reg_price = end($prices['regular_price']);   
        
        if (
            // If either the regular price or the sale price are a range, 
            // modify WooCommerce's behavior to include the regular price in the
            // tag.
            ( $min_sale_price !== $max_sale_price || $min_reg_price !== $max_reg_price )
            // Check that the regular and sale price ranges are distinct from each
            // other. 
            && ( $min_sale_price !== $min_reg_price || $max_reg_price !== $max_sale_price )
        ) {
        
            // Format differently depending on price being a range or not.
            if ($min_reg_price !== $max_reg_price ) {
                $regular_price = wc_format_price_range($min_reg_price, $max_reg_price);
            } else {
                $regular_price = $min_reg_price;
            }

            if ($min_sale_price !== $max_sale_price ) {
                $sale_price = wc_format_price_range($min_sale_price, $max_sale_price);
            } else {
                $sale_price = $min_sale_price;
            }

            // Aside from formatting numbers, the wc_format_sale_price() function is
            // prepared to deal with strings, such as our price range.
            $price_html = wc_format_sale_price($regular_price, $sale_price);
        
        }

    }

    return $price_html;

}
add_filter('woocommerce_variable_price_html', 'SSanzDev_WC_Price_Range_Sale\variable_product_tag_sale', 10, 2);

/**
 * Get the minimum and maximum prices of a grouped product.
 * 
 * This function partially reproduces the content of the
 * WC_Product_Grouped::get_price_html() method, but allows us to choose via the
 * $price_type parameter if we want to get the regular, sale, or default price
 * range.
 * 
 * @see WC_Product_Grouped::get_price_html()
 * @see wc_get_price_including_tax()
 * @see wc_format_price_range()
 * @see wc_format_sale_price()
 * 
 * @param WC_Product_Grouped $product    The grouped product which price range we
 *                                       want to find out. 
 * @param string             $price_type Whether we want to get the regular, sale,
 *                                       or default price range.
 * 
 * @return array The minimum and maximum prices of a grouped product.
 */
function get_grouped_product_prices( $product, $price_type = '' )
{

    $price_range = array(
    'min' => '',
    'max' => ''
    );
    
    // Checking if the product is a grouped product.
    if ($product->is_type('grouped') ) {
        
        // Making sure that the product is on sale if we want to get the sale price
        // (grouped products are flagged as on sale if any of its children are on sale).
        // If the product doesn't meet these requirements, we will return the
        // $price_range array with the values left empty.
        if ('sale' !== $price_type || ( 'sale' === $price_type && $product->is_on_sale() ) ) {
        
            // Checking if we are supposed to display the price including taxes or
            // not in our shop.
            $tax_display_mode = get_option('woocommerce_tax_display_shop');
            // Initializing the array where we are going to store each child's
            // price.
            $child_prices = array();
            // Getting the group's children products.
            $children = array_filter(
                array_map('wc_get_product', $product->get_children()),
                'wc_products_array_filter_visible_grouped'
            );

            //For each product in the group
            foreach ( $children as $child ) {
                
                // Get the specified product's price.
                switch( $price_type ) {
                case 'regular':
                    $child_price = $child->get_regular_price();
                    break;
                case 'sale':
                    // Check if this specific child product is on sale, if not return
                    // its regular price.
                    if ($child->is_on_sale() ) {
                        $child_price = $child->get_sale_price();
                    } else {
                        $child_price = $child->get_regular_price();
                    }                    
                    break;
                default:
                    $child_price = $child->get_price();
                    break;
                }

                if ('' !== $child_price ) {
                    // Calculate the final child product's price depending on
                    // whether we are supposed to display the price including taxes
                    // or not.
                    // The reason why we need to send the price as an argument to
                    // the wc_get_price_including_tax() function is that, if we
                    // leave it blank, it will use the product's default price for
                    // its calculations.
                    $args = array( 'price' => $child_price );

                    if('incl' === $tax_display_mode ) {
                        $child_prices[] = wc_get_price_including_tax($child, $args);
                    } else {
                        $child_prices[] = wc_get_price_excluding_tax($child, $args);
                    }
                }

            }

            // Getting the minimum and maximum prices in the group.
            if (! empty($child_prices) ) {
                $price_range['min'] = min($child_prices);
                $price_range['max'] = max($child_prices);
            }

        }

    }

    return $price_range;

}

/**
 * Display regular price crossed out in grouped products on sale.
 * 
 * WooCommerce by default doesn't show the previous price in products on sale when
 * displaying a price range, which happens in grouped products.
 * 
 * This function partially reproduces the content of the
 * WC_Product_Grouped::get_price_html() method, but slightly changes the behavior
 * to display the regular price alongside the sale price in grouped products on
 * sale.
 * 
 * @see WC_Product_Grouped::get_price_html()
 * @see wc_format_price_range()
 * @see wc_format_sale_price()
 * 
 * @param string             $price_html The price of the product in html format.
 * @param WC_Product_Grouped $product    The product which price is being returned. 
 * 
 * @return string The price of the product in html format, having added the regular
 * price crossed out.
 */
function grouped_product_tag_sale( $price_html, $product )
{

    // Apply only to products on sale.
    if ($product->is_on_sale() ) {

        // Get both the regular and sale maximum and minimum prices.
        $price_range_regular = get_grouped_product_prices($product, 'regular');
        $price_range_sale = get_grouped_product_prices($product, 'sale');

        if (
            // If either the regular price or the sale price are a range, 
            // modify WooCommerce's behavior to include the regular price in the
            // tag.
            ( $price_range_regular['min'] !== $price_range_regular['max'] || $price_range_sale['min'] !== $price_range_sale['max'] )
            // Check that the regular and sale price ranges are distinct from each
            // other. 
            && ( $price_range_regular['min'] !== $price_range_sale['min'] || $price_range_regular['max'] !== $price_range_sale['max'] )
        ) {

            // Format differently depending on price being a range or not.
            if ($price_range_regular['min'] !== $price_range_regular['max'] ) {
                $regular_price = wc_format_price_range($price_range_regular['min'], $price_range_regular['max']);
            } else {
                $regular_price = $price_range_regular['min'];
            }

            if ($price_range_sale['min'] !== $price_range_sale['max'] ) {
                $sale_price = wc_format_price_range($price_range_sale['min'], $price_range_sale['max']);
            } else {
                $sale_price = $price_range_sale['min'];
            }

            // Aside from formatting numbers, the wc_format_sale_price() function is
            // prepared to deal with strings, such as our price range.
            $price_html = wc_format_sale_price($regular_price, $sale_price);

        }

    }

    return $price_html;

}
add_filter('woocommerce_grouped_price_html', 'SSanzDev_WC_Price_Range_Sale\grouped_product_tag_sale', 10, 2);

/**
 * Display regular price crossed out in grouped products when sale price is free.
 * 
 * WooCommerce by default doesn't show the previous price in grouped products on
 * sale when the sale price is zero.
 * 
 * This function partially reproduces the content of the
 * WC_Product_Grouped::get_price_html() method, but slightly changes the behavior
 * to display the regular price alongside the sale price in grouped products on sale
 * when the sale price is zero and regular price is different from zero.
 * 
 * @see WC_Product_Grouped::get_price_html()
 * @see wc_format_price_range()
 * @see wc_format_sale_price()
 * 
 * @param string             $price_html The "Free!" message in html format.
 * @param WC_Product_Grouped $product    The product which price is being returned. 
 * 
 * @return string The "Free!" message in html format, having added the regular price
 * crossed out.
 */
function grouped_product_tag_sale_free( $price_html, $product )
{
    
    // Apply only to products on sale.
    if ($product->is_on_sale() ) {

        // We know the product on sale is free. Let's check if the regular price was
        // also free.
        $price_range_regular = get_grouped_product_prices($product, 'regular');

        // Display regular price only if product was not free before sale.
        if ($price_range_regular['max'] !== 0 ) {
            
            // Format differently depending on the regular price being a range or
            // not.  
            if ($price_range_regular['min'] !== $price_range_regular['max'] ) {
                $regular_price = wc_format_price_range($price_range_regular['min'], $price_range_regular['max']);
            } else {
                $regular_price = $price_range_regular['min'];
            }
            
            // Aside from formatting numbers, the wc_format_sale_price() function is
            // prepared to deal with strings, such as our price range and our
            // "Free!" tag.
            $price_html = wc_format_sale_price($regular_price, __('Free!', 'woocommerce'));
        
        }

    }

    return $price_html;

}

add_filter('woocommerce_grouped_free_price_html', 'SSanzDev_WC_Price_Range_Sale\grouped_product_tag_sale_free', 10, 2);
