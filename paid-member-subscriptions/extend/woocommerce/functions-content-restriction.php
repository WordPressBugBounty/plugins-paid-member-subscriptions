<?php
/**
 * Functions that handle product content restriction on front-end
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Function that displays "Purchase options" for restricted products in Woocommerce individual product page. This way you can select which members (subscription plans) are allowed to purchase a specific product.
 *
 * @param int $post_id
 */
function pms_woo_content_restrict_add_product_purchase_options( $post_id ){

    if ($post_id) {

        $post_type = get_post_type($post_id);

        if ( !empty($post_type) && ($post_type == 'product') ) {

            // this is a WooCommerce product, so we need to display the Content Restrictions -> product purchase options
            include_once 'views/view-content-restriction-product-purchase-options.php';

        }
    }
}
add_action('pms_view_meta_box_content_restrict_display_options', 'pms_woo_content_restrict_add_product_purchase_options');


/**
 * Function that displays the editor for customizing the "purchase restricted" message for individual WooCommerce products.
 *
 * @param int $post_id
 */
function pms_woo_content_restrict_add_purchasing_restricted_message( $post_id ){

    if ($post_id) {

        $post_type = get_post_type($post_id);

        if ( !empty($post_type) && ($post_type == 'product') ) {

            echo '<div class="cozmoslabs-form-field-wrapper cozmoslabs-wysiwyg-wrapper cozmoslabs-wysiwyg-indented">
    
                    <label class="cozmoslabs-form-field-label">' . esc_html(__( 'Messages for restricted product purchase', 'paid-member-subscriptions' ) ). '</label>';
                    wp_editor( wp_kses_post( get_post_meta( $post_id, 'pms-content-restrict-message-purchasing_restricted', true ) ), 'messages_purchasing_restricted', array( 'textarea_name' => 'pms-content-restrict-message-purchasing_restricted', 'editor_height' => 180 ) );

            echo '</div>';

        }
    }

}
add_action( 'pms_view_meta_box_content_restrict_restriction_messages_bottom', 'pms_woo_content_restrict_add_purchasing_restricted_message');


/**
 * Function that adds a default "purchasing restricted" message for WooCommerce products under PMS Settings -> Content Restriction messages
 *
 * @param array $options PMS settings options
 */
function pms_woo_settings_page_add_default_purchasing_restricted_message( $options ) {

    echo '<div class="cozmoslabs-form-subsection-wrapper" id="cozmoslabs-restriction-woo-product">';
        echo '<h4 class="cozmoslabs-subsection-title">' . esc_html( __( 'WooCommerce Restriction Messages', 'paid-member-subscriptions' ) ) . '</h4>';

        echo '<div class="cozmoslabs-form-field-wrapper cozmoslabs-wysiwyg-wrapper cozmoslabs-wysiwyg-indented">
    
                <label class="cozmoslabs-form-field-label">' . esc_html(__( 'Messages for restricted product purchase', 'paid-member-subscriptions' ) ). '</label>';
        wp_editor( pms_get_restriction_content_message( 'purchasing_restricted' ), 'messages_purchasing_restricted', array( 'textarea_name' => 'pms_content_restriction_settings[purchasing_restricted]', 'editor_height' => 180 ) );

        echo '</div>';
    echo '</div>';

}
add_action('pms-settings-page_tab_content_restriction_restrict_messages_bottom', 'pms_woo_settings_page_add_default_purchasing_restricted_message');


/**
 * Function that sets the default "purchasing restricted" message for WooCommerce products used under PMS Settings Page -> Content Restriction messages
 *
 * @param string $message The message to return
 * @param string $type The type of message
 * @param array $settings The PMS settings array
 * @return string
 */
function pms_woo_set_default_purchasing_restricted_message($message, $type, $settings) {

    if ($type == 'purchasing_restricted') {

        $message = isset($settings['purchasing_restricted']) ? $settings['purchasing_restricted'] : __('You need the proper subscription plan to be able to purchase this product.', 'paid-member-subscriptions');
    }

    return wp_kses_post($message);
}
add_filter('pms_get_restriction_content_message_default', 'pms_woo_set_default_purchasing_restricted_message', 10, 3);


/**
 * Function that saves the "Purchasing Options" and "purchase restricted" messages set in the Content Restriction metabox for individual WooCommerce products
 *
 * @param int $post_id The post ID
 */
function pms_woo_save_custom_purchasing_restricted_message( $post_id ) {
    //verify nonce
    if ( empty($_POST['pmstkn']) || !wp_verify_nonce( sanitize_text_field( $_POST['pmstkn'] ), 'pms_meta_box_single_content_restriction_nonce' ) )
        return;

    delete_post_meta( $post_id, 'pms-purchase-restrict-subscription-plan' );
    delete_post_meta( $post_id, 'pms-purchase-restrict-all-subscription-plans' );
    if( ( isset( $_POST['pms-purchase-restrict-subscription-plan'] ) && is_array( $_POST['pms-purchase-restrict-subscription-plan'] ) ) || isset( $_POST['pms-purchase-restrict-all-subscription-plans'] ) ) {

        if( isset( $_POST['pms-purchase-restrict-all-subscription-plans'] ) )
            update_post_meta( $post_id, 'pms-purchase-restrict-all-subscription-plans', 'all' );

        if( isset( $_POST['pms-purchase-restrict-all-subscription-plans'] ) ){

            $active_plans = pms_get_subscription_plans();
            $subscription_ids = array();

            foreach( $active_plans as $active_plan ){
                $subscription_ids[] = (int)$active_plan->id;
            }
        }
        else
            $subscription_ids = array_map( 'absint', $_POST['pms-purchase-restrict-subscription-plan'] );

        foreach ( $subscription_ids as $subscription_id ) {
            if( ! empty( $subscription_id ) )
                add_post_meta( $post_id, 'pms-purchase-restrict-subscription-plan', $subscription_id );
        }
    }


    if( isset( $_POST['pms-purchase-restrict-user-status'] ) && $_POST['pms-purchase-restrict-user-status'] === 'loggedin' )
        update_post_meta( $post_id, 'pms-purchase-restrict-user-status', 'loggedin' );
    else
        delete_post_meta( $post_id, 'pms-purchase-restrict-user-status' );

    // save custom "product purchase restricted" message
    update_post_meta( $post_id, 'pms-content-restrict-message-purchasing_restricted', ( ! empty( $_POST['pms-content-restrict-message-purchasing_restricted'] ) ? wp_kses_post($_POST['pms-content-restrict-message-purchasing_restricted']) : '' ) );

}
add_action( 'pms_save_meta_box_product', 'pms_woo_save_custom_purchasing_restricted_message');


/**
 * Restrict single product view by marking restricted products as password-protected
 *
 * - Uses 'template_redirect' so this runs early enough for both classic and block themes
 * - Applies only to 'message' restriction type
 * - 'redirect' and 'template' restriction modes are handled separately
 *
 */
function pms_woo_maybe_password_protect_product() {
    global $post;

    if ( ! is_singular( 'product' ) || empty( $post ) || empty( $post->ID ) )
        return;

    $post_restriction_type = get_post_meta( $post->ID, 'pms-content-restrict-type', true );
    $settings              = get_option( 'pms_content_restriction_settings', array() );

    if ( $post_restriction_type == 'default' || empty( $post_restriction_type ) )
        $post_restriction_type = ( ! empty( $settings['content_restrict_type'] ) ? $settings['content_restrict_type'] : 'message' );

    // `redirect` and `template` restriction types are handled by dedicated flows
    if ( $post_restriction_type !== 'message' )
        return;

    // If the product is restricted and does not already require a password, set a temporary password before Woo renders product templates
    if ( pms_is_post_restricted( $post->ID ) && ! post_password_required() ) {
        $post->post_password = uniqid( 'pms_woo_product_restricted_' );

        if ( ! has_filter( 'the_password_form', 'pms_woo_restrict_product_content' ) )
            add_filter( 'the_password_form', 'pms_woo_restrict_product_content' );
    }
}
add_action( 'template_redirect', 'pms_woo_maybe_password_protect_product', 0 );


/**
 * Function that restricts product content
 *
 * @param $output What is returned
 * @return string
 */
function pms_woo_restrict_product_content( $output ){
    global $post, $user_ID;

    if ( strpos( $post->post_password, 'pms_woo_product_restricted_' ) !== false ) {

        // user does not have access, filter the content
        $output = '';

        // check if restricted post preview is set
        $settings       = get_option( 'pms_content_restriction_settings' );
        $preview_option = ( !empty( $settings['restricted_post_preview']['option'] ) ? $settings['restricted_post_preview']['option'] : '' );

        if ( !empty($preview_option) && ($preview_option != 'none') ) {
            // display product title

            ob_start();

            echo '<div class="summary entry-summary">';
            wc_get_template( 'single-product/title.php' );
            echo '</div>';

            $output = ob_get_clean();
        }

        $output .= pms_get_restricted_post_message();

        $post->post_password = null;

    }

    return $output;
}

/**
 * Function that sets the "purchasing_restricted" message type inside "pms_get_restricted_post_message" function
 *
 * @param string $message The message type to return
 *
 */
function pms_woo_set_purchasing_restricted_message_type( $message, $post_id ) {

    if( get_post_type( $post_id ) != 'product' )
        return $message;

    if  ( !pms_is_post_restricted() && !pms_is_product_purchasable() ) {

        // this product can be viewed, but purchase is restricted
        $message = 'purchasing_restricted';
    }

    return $message;
}
add_filter( 'pms_get_restricted_post_message_type', 'pms_woo_set_purchasing_restricted_message_type', 10, 2 );


/**
 * Function that hides the price for view-restricted products
 *
 * @param float $price The product price
 * @param WC_Product $product The product
 * @return string
 */
function pms_woo_hide_restricted_product_price( $price, WC_Product $product ){
    // check if current user can view this product, and if not, remove the price
    if ( pms_is_post_restricted( $product->get_id() ) ) {

        $price = '';
    }

    return $price;
}
add_filter( 'woocommerce_get_price_html', 'pms_woo_hide_restricted_product_price', 9, 2);


/**
 * Function that hides the description, short description and prices of view-restricted products from Store API product responses
 *
 * Block themes load the same responses into the page's interactivity state, so this also covers the product and shop page source
 *
 * @param WP_REST_Response|WP_Error|mixed $response The route response
 * @param array $handler The route handler
 * @return WP_REST_Response|WP_Error|mixed
 */
function pms_woo_hide_restricted_products_from_store_api( $response, $handler ){

    $callback = isset( $handler['callback'] ) ? $handler['callback'] : null;
    $route    = is_array( $callback ) && isset( $callback[0] ) ? $callback[0] : null;

    $is_product_route = $route instanceof \Automattic\WooCommerce\StoreApi\Routes\V1\Products
        || $route instanceof \Automattic\WooCommerce\StoreApi\Routes\V1\ProductsById
        || $route instanceof \Automattic\WooCommerce\StoreApi\Routes\V1\ProductsBySlug;

    if ( ! $is_product_route || ! $response instanceof WP_REST_Response )
        return $response;

    $data = $response->get_data();

    if ( isset( $data['id'] ) )
        $data = pms_woo_hide_restricted_store_api_product( $data );
    elseif ( is_array( $data ) )
        $data = array_map( 'pms_woo_hide_restricted_store_api_product', $data );

    $response->set_data( $data );

    return $response;
}
add_filter( 'rest_request_after_callbacks', 'pms_woo_hide_restricted_products_from_store_api', 10, 2 );
add_filter( 'woocommerce_hydration_request_after_callbacks', 'pms_woo_hide_restricted_products_from_store_api', 10, 2 );


/**
 * Function that blanks the description, short description and prices of a Store API product if the current user cannot view it
 *
 * @param array $product The Store API product data
 * @return array
 */
function pms_woo_hide_restricted_store_api_product( $product ){

    if ( ! is_array( $product ) || empty( $product['id'] ) )
        return $product;

    $is_restricted = pms_is_post_restricted( $product['id'] );

    // variations carry no restriction rules of their own, they follow the parent product
    if ( ! $is_restricted && isset( $product['type'] ) && $product['type'] === 'variation' && ! empty( $product['parent'] ) )
        $is_restricted = pms_is_post_restricted( $product['parent'] );

    if ( ! $is_restricted )
        return $product;

    $product['description']       = '';
    $product['short_description'] = '';
    $product['price_html']        = '';

    $prices                  = (array) $product['prices'];
    $prices['price']         = '';
    $prices['regular_price'] = '';
    $prices['sale_price']    = '';
    $prices['price_range']   = null;
    $product['prices']       = (object) $prices;

    return $product;
}


/**
 * Function that tags the product queries of a Store API products or collection data request with the current viewer's product visibility
 *
 * Collection data always applies it, because the price range of a single product is its hidden price.
 *
 * The tag travels in the request's own exclude param, so ProductQuery copies it into every product query the route runs
 * and the filter data WooCommerce caches by query vars is cached per visibility.
 *
 * @param WP_REST_Response|WP_Error|mixed $response The route response, a WP_Error when the request already failed
 * @param array $handler The route handler
 * @param WP_REST_Request $request The request the route callback receives
 * @return WP_REST_Response|WP_Error|mixed
 */
function pms_woo_tag_store_api_product_queries( $response, $handler, $request ){

    $callback = isset( $handler['callback'] ) ? $handler['callback'] : null;
    $route    = is_array( $callback ) && isset( $callback[0] ) ? $callback[0] : null;

    if ( $route instanceof \Automattic\WooCommerce\StoreApi\Routes\V1\ProductCollectionData ) {
        $applies = true;
    } elseif ( $route instanceof \Automattic\WooCommerce\StoreApi\Routes\V1\Products ) {
        $content_settings = get_option( 'pms_content_restriction_settings' );
        $woo_settings     = get_option( 'pms_woocommerce_settings' );

        $applies = ( ! isset( $content_settings['pms_includeRestrictedPosts'] ) || $content_settings['pms_includeRestrictedPosts'] != 'yes' )
            && isset( $woo_settings['exclude_products_from_queries'] ) && $woo_settings['exclude_products_from_queries'] == 1;
    } else {
        $applies = false;
    }

    if ( ! $applies || is_wp_error( $response ) )
        return $response;

    $visibility = pms_woo_get_store_api_product_visibility();

    if ( $visibility === null )
        return $response;

    // hydration passes the raw query params
    $excluded = empty( $request['exclude'] ) ? array() : wp_parse_id_list( $request['exclude'] );

    $excluded['pms_restricted_products'] = $visibility['key'];

    $request->set_param( 'exclude', $excluded );

    return $response;
}
add_filter( 'rest_request_before_callbacks', 'pms_woo_tag_store_api_product_queries', 10, 3 );


/**
 * Function that tags the product queries of a hydrated Store API request, since hydration does not fire the REST callback hooks
 *
 * @param mixed $result The hydration result, null unless another callback already answered the request
 * @param WP_REST_Request $request The request the route callback receives
 * @param string $path The request path
 * @param array $handler The route handler
 * @return mixed
 */
function pms_woo_tag_hydrated_store_api_product_queries( $result, $request, $path, $handler ){

    pms_woo_tag_store_api_product_queries( null, $handler, $request );

    return $result;
}
add_filter( 'woocommerce_hydration_dispatch_request', 'pms_woo_tag_hydrated_store_api_product_queries', 10, 4 );


/**
 * Function that applies the current viewer's product visibility to a product query tagged by pms_woo_tag_store_api_product_queries()
 *
 * @param WP_Query $query The query
 */
function pms_woo_apply_store_api_product_visibility( $query ){

    $excluded = $query->get( 'post__not_in' );

    if ( ! is_array( $excluded ) || ! array_key_exists( 'pms_restricted_products', $excluded ) )
        return;

    $visibility = pms_woo_get_store_api_product_visibility();

    if ( $visibility === null )
        return;

    unset( $excluded['pms_restricted_products'] );

    $included = wp_parse_id_list( $query->get( 'post__in' ) );

    if ( empty( $included ) && $visibility['mode'] === 'hide' ) {
        $query->set( 'post__not_in', array_merge( $excluded, $visibility['ids'] ) );
        return;
    }

    // WP_Query ignores post__not_in whenever post__in is set
    if ( empty( $included ) )
        $kept = array_diff( $visibility['ids'], $excluded );
    elseif ( $visibility['mode'] === 'only' )
        $kept = array_intersect( $included, $visibility['ids'] );
    else
        $kept = array_diff( $included, $visibility['ids'] );

    $query->set( 'post__in', empty( $kept ) ? array( 0 ) : array_values( $kept ) );
    $query->set( 'post__not_in', $excluded );
}
add_action( 'pre_get_posts', 'pms_woo_apply_store_api_product_visibility' );


/**
 * Function that returns the current viewer's product visibility for Store API product queries
 *
 * @return array|null Null when the viewer may see every product, otherwise array( 'mode' => 'hide'|'only', 'ids' => int[], 'key' => string )
 */
function pms_woo_get_store_api_product_visibility(){

    global $wpdb;

    static $visibility_by_user = array();

    $user_id = get_current_user_id();

    if ( array_key_exists( $user_id, $visibility_by_user ) )
        return $visibility_by_user[ $user_id ];

    if ( current_user_can( 'manage_options' ) )
        return null;

    $inputs     = pms_woo_get_product_restriction_inputs();
    $groups     = pms_woo_group_products_by_restriction_signature( $inputs );
    $outside_id = pms_woo_get_product_outside_restriction_rules( $inputs );

    $representatives = array();

    foreach ( $groups as $product_ids )
        $representatives[] = $product_ids[0];

    if ( $outside_id )
        $representatives[] = $outside_id;

    _prime_post_caches( $representatives, true, true );

    $hidden  = array();
    $visible = array();

    foreach ( $groups as $product_ids ) {
        if ( pms_is_post_restricted( $product_ids[0] ) )
            $hidden[] = $product_ids;
        else
            $visible[] = $product_ids;
    }

    $hidden  = array_merge( array(), ...$hidden );
    $visible = array_merge( array(), ...$visible );

    if ( $outside_id )
        $list_visible = pms_is_post_restricted( $outside_id );
    else
        $list_visible = count( $visible ) < count( $hidden );

    $ids = $list_visible ? $visible : $hidden;

    if ( ! $list_visible && empty( $ids ) ) {
        $visibility_by_user[ $user_id ] = null;
        return null;
    }

    if ( ! empty( $ids ) ) {
        $variation_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_parent IN ( " . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ' )',
            array_merge( array( 'product_variation' ), $ids )
        ) );

        $ids = array_merge( $ids, array_map( 'intval', $variation_ids ) );
    }

    sort( $ids );

    $mode = $list_visible ? 'only' : 'hide';

    $visibility_by_user[ $user_id ] = array(
        'mode' => $mode,
        'ids'  => $ids,
        // keyed with a secret, because the tag shows up in the pagination links of the product collection
        'key'  => 'pms-' . wp_hash( $mode . ':' . implode( ',', $ids ) ),
    );

    return $visibility_by_user[ $user_id ];
}


/**
 * Function that reads which terms and listed products the restriction rules of the core plugin, Global Content Restriction and Content Dripping reach
 *
 * @return array array( 'term_facts' => array( term_taxonomy_id => string[] ), 'post_facts' => array( product_id => string[] ) )
 */
function pms_woo_get_product_restriction_inputs(){

    global $wpdb;

    $term_facts         = array();
    $post_facts         = array();
    $product_taxonomies = get_object_taxonomies( 'product' );

    $restricted_term_ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT tt.term_taxonomy_id FROM {$wpdb->termmeta} tm INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = tm.term_id
        WHERE tm.meta_key IN ( %s, %s, %s ) AND tt.taxonomy IN ( " . implode( ',', array_fill( 0, count( $product_taxonomies ), '%s' ) ) . ' )',
        array_merge( array( 'pms-content-restrict-user-status', 'pms-content-restrict-subscription-plan', 'pms-content-restrict-all-subscription-plans' ), $product_taxonomies )
    ) );

    foreach ( $restricted_term_ids as $term_taxonomy_id )
        $term_facts[ $term_taxonomy_id ][] = 't:' . $term_taxonomy_id;

    if ( class_exists( 'PMS_IN_Meta_Box_Subscription_Content_Restriction' ) ) {

        foreach ( pms_get_subscription_plans() as $plan ) {

            $nr_of_rules           = (int) get_post_meta( $plan->id, 'pms_nr_of_rules', true );
            $rule_taxonomies       = array();
            $reaches_every_product = false;

            for ( $k = 1; $k <= $nr_of_rules; $k++ ) {

                if ( get_post_meta( $plan->id, 'pms_content_rule_post_type_' . $k, true ) != 'product' )
                    continue;

                $taxonomies = get_post_meta( $plan->id, 'pms_content_rule_taxonomies_' . $k, true );

                if ( empty( $taxonomies ) ) {
                    $reaches_every_product = true;
                    break;
                }

                $rule_taxonomies = array_merge( $rule_taxonomies, explode( ',', $taxonomies ) );
            }

            if ( $reaches_every_product )
                continue;

            foreach ( array_intersect( $product_taxonomies, $rule_taxonomies ) as $taxonomy ) {

                $terms = get_post_meta( $plan->id, 'pms_content_rule_tax_terms_product_' . $taxonomy, true );
                $terms = empty( $terms ) ? array( '' ) : explode( ',', $terms );

                // has_term() reads an empty term as any term of the taxonomy
                if ( count( array_filter( $terms ) ) < count( $terms ) )
                    $term_taxonomy_ids = $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $taxonomy ) );
                else
                    $term_taxonomy_ids = pms_woo_get_term_taxonomy_ids_by_terms( $taxonomy, $terms );

                foreach ( $term_taxonomy_ids as $term_taxonomy_id )
                    $term_facts[ $term_taxonomy_id ][] = 'g:' . $plan->id;
            }
        }
    }

    if ( function_exists( 'pms_in_cd_filter_content' ) ) {

        foreach ( get_posts( array( 'post_type' => 'pms-content-dripping', 'numberposts' => -1, 'post_status' => 'active' ) ) as $set ) {

            foreach ( (array) get_post_meta( $set->ID, 'pms-content-dripping-rules', true ) as $index => $rule ) {

                if ( empty( $rule['content'] ) || ! isset( $rule['post_type'] ) || $rule['post_type'] !== 'product' )
                    continue;

                $fact = 'd:' . $set->ID . ':' . $index;

                if ( $rule['type'] == 'pms_list_of_posts' ) {
                    foreach ( wp_parse_id_list( $rule['content'] ) as $product_id )
                        $post_facts[ $product_id ][] = $fact;

                    continue;
                }

                $term_ids = wp_parse_id_list( $rule['content'] );

                $term_taxonomy_ids = $wpdb->get_col( $wpdb->prepare(
                    "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s AND term_id IN ( " . implode( ',', array_fill( 0, count( $term_ids ), '%d' ) ) . ' )',
                    array_merge( array( $rule['type'] ), $term_ids )
                ) );

                foreach ( $term_taxonomy_ids as $term_taxonomy_id )
                    $term_facts[ $term_taxonomy_id ][] = $fact;
            }
        }
    }

    return array(
        'term_facts' => $term_facts,
        'post_facts' => $post_facts,
    );
}


/**
 * Function that returns the term taxonomy IDs of the terms of a taxonomy that has_term() matches with the given term IDs, names or slugs
 *
 * @param string $taxonomy The taxonomy
 * @param string[] $terms Term IDs, names or slugs
 * @return int[]
 */
function pms_woo_get_term_taxonomy_ids_by_terms( $taxonomy, $terms ){

    global $wpdb;

    $term_ids = wp_parse_id_list( $terms );

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT tt.term_taxonomy_id, t.term_id, t.name, t.slug FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
        WHERE tt.taxonomy = %s AND ( t.term_id IN ( " . implode( ',', array_fill( 0, count( $term_ids ), '%d' ) ) . ' )
        OR t.name IN ( ' . implode( ',', array_fill( 0, count( $terms ), '%s' ) ) . ' )
        OR t.slug IN ( ' . implode( ',', array_fill( 0, count( $terms ), '%s' ) ) . ' ) )',
        array_merge( array( $taxonomy ), $term_ids, $terms, $terms )
    ) );

    // the query also matches other letter cases and accents, is_object_in_term() compares exactly
    $numbers           = array_map( 'intval', array_filter( $terms, 'is_numeric' ) );
    $term_taxonomy_ids = array();

    foreach ( $rows as $row ) {
        if ( in_array( (int) $row->term_id, $numbers, true ) || in_array( $row->name, $terms, true ) || in_array( $row->slug, $terms, true ) )
            $term_taxonomy_ids[] = (int) $row->term_taxonomy_id;
    }

    return $term_taxonomy_ids;
}


/**
 * Function that groups the published products that have restriction facts by the facts they share
 *
 * @param array $inputs The rule inputs from pms_woo_get_product_restriction_inputs()
 * @return array Sorted facts, JSON encoded => int[] product IDs; products without facts are not listed
 */
function pms_woo_group_products_by_restriction_signature( $inputs ){

    global $wpdb;

    $facts = array();

    $meta_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT pm.post_id, pm.meta_key, pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key IN ( %s, %s, %s ) AND p.post_type = %s AND p.post_status = %s",
        'pms-content-restrict-user-status',
        'pms-content-restrict-subscription-plan',
        'pms-content-restrict-all-subscription-plans',
        'product',
        'publish'
    ) );

    foreach ( $meta_rows as $row )
        $facts[ $row->post_id ][] = 'm:' . $row->meta_key . '=' . $row->meta_value;

    if ( ! empty( $inputs['term_facts'] ) ) {

        $term_taxonomy_ids = array_keys( $inputs['term_facts'] );

        $term_rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT tr.object_id, tr.term_taxonomy_id FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
            WHERE tr.term_taxonomy_id IN ( " . implode( ',', array_fill( 0, count( $term_taxonomy_ids ), '%d' ) ) . ' ) AND p.post_type = %s AND p.post_status = %s',
            array_merge( $term_taxonomy_ids, array( 'product', 'publish' ) )
        ) );

        foreach ( $term_rows as $row ) {
            foreach ( $inputs['term_facts'][ $row->term_taxonomy_id ] as $fact )
                $facts[ $row->object_id ][] = $fact;
        }
    }

    if ( ! empty( $inputs['post_facts'] ) ) {

        $listed_ids = array_keys( $inputs['post_facts'] );

        $published_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE ID IN ( " . implode( ',', array_fill( 0, count( $listed_ids ), '%d' ) ) . ' ) AND post_type = %s AND post_status = %s',
            array_merge( $listed_ids, array( 'product', 'publish' ) )
        ) );

        foreach ( $published_ids as $product_id ) {
            foreach ( $inputs['post_facts'][ $product_id ] as $fact )
                $facts[ $product_id ][] = $fact;
        }
    }

    $groups = array();

    foreach ( $facts as $product_id => $product_facts ) {
        $product_facts = array_unique( $product_facts );
        sort( $product_facts );

        $groups[ wp_json_encode( $product_facts ) ][] = $product_id;
    }

    return $groups;
}


/**
 * Function that returns one published product without restriction facts
 *
 * @param array $inputs The rule inputs from pms_woo_get_product_restriction_inputs()
 * @return int The product ID, 0 when every published product has restriction facts
 */
function pms_woo_get_product_outside_restriction_rules( $inputs ){

    global $wpdb;

    $sql  = "SELECT p.ID FROM {$wpdb->posts} p
        WHERE p.post_type = %s AND p.post_status = %s
        AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key IN ( %s, %s, %s ) )";
    $args = array( 'product', 'publish', 'pms-content-restrict-user-status', 'pms-content-restrict-subscription-plan', 'pms-content-restrict-all-subscription-plans' );

    if ( ! empty( $inputs['term_facts'] ) ) {
        $sql .= " AND NOT EXISTS ( SELECT 1 FROM {$wpdb->term_relationships} tr WHERE tr.object_id = p.ID AND tr.term_taxonomy_id IN ( " . implode( ',', array_fill( 0, count( $inputs['term_facts'] ), '%d' ) ) . ' ) )';
        $args = array_merge( $args, array_keys( $inputs['term_facts'] ) );
    }

    if ( ! empty( $inputs['post_facts'] ) ) {
        $sql .= ' AND p.ID NOT IN ( ' . implode( ',', array_fill( 0, count( $inputs['post_facts'] ), '%d' ) ) . ' )';
        $args = array_merge( $args, array_keys( $inputs['post_facts'] ) );
    }

    return (int) $wpdb->get_var( $wpdb->prepare( $sql . ' LIMIT 1', $args ) );
}


/**
 * Function that hides the product image thumbnail for view-restricted products
 *
 */
function pms_woo_maybe_remove_product_thumbnail(){
    global $post, $pms_woo_product_thumbnail_restricted;;

    $pms_woo_product_thumbnail_restricted = false;

    // skip if the product thumbnail is not shown anyway
    if ( ! has_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail' ) ) {
        return;
    }

    // if product is view restricted, do not display the product thumbnail
    if ( pms_is_post_restricted($post->ID) ) {

        // indicate that we removed the product thumbnail
        $pms_woo_product_thumbnail_restricted = true;

        // remove the product thumbnail and replace it with the placeholder image
        remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail' );
        add_action( 'woocommerce_before_shop_loop_item_title', 'pms_woo_template_loop_product_thumbnail_placeholder' , 10 );
    }

}
add_action( 'woocommerce_before_shop_loop_item_title', 'pms_woo_maybe_remove_product_thumbnail', 5 );


// return placeholder thumbnail instead of image for view-restricted products
function pms_woo_template_loop_product_thumbnail_placeholder(){
    if ( wc_placeholder_img_src() ) {

        echo wp_kses_post( wc_placeholder_img( 'shop_catalog' ) );
    }
}

// restore product thumbnail for the next product in the loop
function pms_woo_restore_product_thumbnail(){
    global $pms_woo_product_thumbnail_restricted;

    if (  $pms_woo_product_thumbnail_restricted
        && ! has_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail' ) ) {

        add_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
        remove_action( 'woocommerce_before_shop_loop_item_title', 'pms_woo_template_loop_product_thumbnail_placeholder' );
    }
}
add_action( 'woocommerce_after_shop_loop_item_title', 'pms_woo_restore_product_thumbnail', 5 );


/**
 * Function that restricts product purchasing
 *
 * @param boolean $purchasable Whether the product is purchasable or not
 * @param $product The product
 * @return bool
 */
function pms_woo_product_is_purchasable( $purchasable, $product ){

    // if the product is view-restricted or purchase-restricted it cannot be purchased
    if ( pms_is_post_restricted( $product->get_id() ) || !pms_is_product_purchasable( $product ) ) {

        $purchasable = false;
    }

    // double-check for variations; if parent is not purchasable, then neither should be the variation
    if ( $purchasable && $product->is_type( array( 'variation' ) ) ) {

        $parent = wc_get_product( $product->get_parent_id() );

        if( !empty( $parent ) && is_object( $parent ) )
            $purchasable = $parent->is_purchasable();

    }

    return $purchasable;

}
add_filter( 'woocommerce_is_purchasable', 'pms_woo_product_is_purchasable', 10, 2 );
add_filter( 'woocommerce_variation_is_purchasable', 'pms_woo_product_is_purchasable', 10, 2 );


/**
 * Function that checks if current user can purchase the product
 *
 * @param string|WC_Product $product The product
 * @return bool
 */
function pms_is_product_purchasable( $product = '' ){
    global $user_ID, $post;

    if ( empty($product) )
        $product = wc_get_product( $post->ID );

    if( false == $product )
        return false;

    /**
     * Show "buy now" for the `manage_options` and `pms_bypass_content_restriction` capabilities
     */
    if( current_user_can( 'manage_options' ) || current_user_can( apply_filters( 'pms_content_restriction_capability', 'pms_bypass_content_restriction' ) ) )
        return true;

    // if is variation, use the id of the parent product
    if ( $product->is_type( 'variation' ) )
        $product_id = $product->get_parent_id();
    else
        $product_id = $product->get_id();

    // Get subscription plans that can purchase this product
    $user_status                = get_post_meta( $product_id, 'pms-purchase-restrict-user-status', true );
    $product_subscription_plans = get_post_meta( $product_id, 'pms-purchase-restrict-subscription-plan' );

    if( empty( $user_status ) && empty( $product_subscription_plans ) ) {
        //everyone can purchase
        return true;

    } else if( !empty( $product_subscription_plans ) && is_user_logged_in() ) {

        if ( pms_is_member( $user_ID, $product_subscription_plans ) )
            return true;
        else
            return false;

    } else if ( !is_user_logged_in() && (
                ( !empty( $user_status ) && $user_status == 'loggedin' ) || ( !empty( $product_subscription_plans ) )
            ) ) {

        return false;
    }

    return true;
}
/**
 * Function that shows the product purchasing restricted message
 *
 **/
function pms_woo_single_product_purchasing_restricted_message(){
    global $pms_show_content;

    // View restrictions already render their own message
    if ( pms_is_post_restricted() ) {
        return;
    }

    if ( !pms_is_product_purchasable() ) {

        // product purchasing is restricted
        $pms_show_content = false;
        $message = pms_get_restricted_post_message();

        echo wp_kses_post( $message );
    }
}
add_action( 'woocommerce_single_product_summary', 'pms_woo_single_product_purchasing_restricted_message', 30 );


/**
 * Add divs to restriction message which will be used for styling, separating the message from the content in case we have set a post/product preview
 *
 * @param $message The message to return
 * @return string
 *
 */
function pms_woo_add_divs_to_restriction_message( $message ) {

    global $post;

    if( 'product' === get_post_type( $post ) )
        $message = '<div class="woocommerce">' . '<div class="woocommerce-info pms-woo-restriction-message pms-woo-restricted-product-purchasing-message">' . $message . '</div>' . '</div>';

    return $message;

}
add_filter('pms_restriction_message_wpautop', 'pms_woo_add_divs_to_restriction_message');

// Apply wpautop() to "purchasing restricted" messages as well
add_filter( 'pms_restriction_message_purchasing_restricted', 'pms_restriction_message_wpautop', 30, 1 );


if( function_exists( 'wc_get_page_id' ) ) :
    /**
     * Function that redirects the user if the Shop page (set in WooCommerce Settings -> Products -> Display) is restricted and a redirect is set
     *
     */
    function pms_woo_restricted_shop_redirect() {

        // check if it's the WooCommerce shop page
        if ( !is_post_type_archive('product')  )
            return;

        // get the ID of the shop page
        $post_id = wc_get_page_id('shop');

        // make sure we have the page id
        if ( $post_id != -1 ) {

            $redirect_url = '';
            $post_restriction_type = get_post_meta($post_id, 'pms-content-restrict-type', true);
            $settings = get_option( 'pms_content_restriction_settings', array() );
            $general_restriction_type = (!empty($settings['content_restrict_type']) ? $settings['content_restrict_type'] : 'message');
            $post_subscription_plans = get_post_meta( $post_id, 'pms-content-restrict-subscription-plan' );

            if ($post_restriction_type !== 'redirect' && $general_restriction_type !== 'redirect')
                return;

            if ( !in_array($post_restriction_type, array('default', 'redirect')) )
                return;

            if ( !pms_is_post_restricted( $post_id ) )
                return;

            /**
             * Get the redirect URL from the post meta if enabled
             *
             */
            if ($post_restriction_type === 'redirect') {

                $post_redirect_url_enabled = get_post_meta($post_id, 'pms-content-restrict-custom-redirect-url-enabled', true);
                $post_redirect_url = get_post_meta($post_id, 'pms-content-restrict-custom-redirect-url', true);
                $post_non_member_redirect_url   = get_post_meta( $post_id, 'pms-content-restrict-custom-non-member-redirect-url', true );

                $redirect_url = (!empty($post_redirect_url_enabled) && !empty($post_redirect_url) ? $post_redirect_url : '');
                $non_member_redirect_url = ( ! empty( $post_redirect_url_enabled ) && ! empty( $post_non_member_redirect_url ) ? $post_non_member_redirect_url : '' );

            }

            if ( !empty( $non_member_redirect_url ) ){
                if ( is_user_logged_in() && isset( $post_subscription_plans ) && !pms_is_member( get_current_user_id(), $post_subscription_plans ) ){
                    $redirect_url = $non_member_redirect_url;
                }
            }

            /**
             * If the post doesn't have a custom redirect URL set, get the default from the Settings page
             *
             */
            if (empty($redirect_url)) {

                $redirect_url = ( ! empty( $settings['content_restrict_redirect_url'] ) ? $settings['content_restrict_redirect_url'] : '' );
                $non_member_redirect_url = ( ! empty( $settings['content_restrict_non_member_redirect_url'] ) ? $settings['content_restrict_non_member_redirect_url'] : '' );
                if ( !empty( $non_member_redirect_url ) ){
                    if ( is_user_logged_in() && isset( $post_subscription_plans ) && !pms_is_member( get_current_user_id(), $post_subscription_plans ) ){
                        $redirect_url = $non_member_redirect_url;
                    }
                }
            }

            if (empty($redirect_url))
                return;

            /**
             * To avoid a redirect loop we break in case the redirect URL is the same as
             * the current page URl
             *
             */
            $current_url = pms_get_current_page_url();

            if ($current_url == $redirect_url)
                return;

            /**
             * Redirect
             *
             */
            nocache_headers();
            wp_redirect( pms_add_missing_http( $redirect_url ) );
            exit;
        }

    }
    add_action( 'wp', 'pms_woo_restricted_shop_redirect' );


    /**
     * Restrict the Shop page
     *
     * @param $template The shop page template to return
     * @return string
     */
    function pms_woo_restrict_shop_page( $template ){

        // check if we're on the Shop page (set under WooCommerce Settings -> Products -> Display)
        if ( is_post_type_archive( 'product' ) || is_page( wc_get_page_id( 'shop' )) ) {

            // get the ID of the shop page
            $post_id = wc_get_page_id('shop');

            if ( ($post_id != -1) && pms_is_post_restricted($post_id) ) {

                $post_restriction_type = get_post_meta( $post_id, 'pms-content-restrict-type', true );
                $settings              = get_option( 'pms_content_restriction_settings', array() );

                if ( $post_restriction_type == 'default' || empty( $post_restriction_type ) )
                    $post_restriction_type = ( ! empty( $settings['content_restrict_type'] ) ? $settings['content_restrict_type'] : 'message' );

                if ( $post_restriction_type === 'redirect' )
                    return $template;

                if ( $post_restriction_type === 'template' ) {

                    $restrict_template = ( ! empty( $settings['content_restrict_template'] ) ? $settings['content_restrict_template'] : '' );

                    if ( empty( $restrict_template ) )
                        return $template;

                    if ( did_action( 'elementor/loaded' ) && strpos( $restrict_template, 'elementor_template_' ) !== false ) {

                        $elementor_template = pms_elementor_render_template( str_replace( 'elementor_template_', '', $restrict_template ) );

                        if ( ! empty( $elementor_template ) )
                            return $elementor_template;

                        return $template;
                    }

                    $new_template = locate_template( array( $restrict_template ) );

                    if ( ! empty( $new_template ) )
                        return $new_template;

                    return $template;
                }

                $shop_page = get_post( $post_id );

                setup_postdata( $shop_page );

                // archive-product.php uses get_header() / get_footer(), which break on block (FSE) themes since they have no header/footer files
                // so on those themes render the message with the shared helper, which builds the page from the theme's own block header and footer
                if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() && function_exists( 'pms_render_restricted_message_template' ) ) {

                    $message = pms_get_restricted_post_message( $post_id );

                    wp_reset_postdata();

                    return pms_render_restricted_message_template( $message, 'pms//restricted-shop' );
                }

                $template = PMS_PLUGIN_DIR_PATH . 'extend/woocommerce/templates/archive-product.php';

                wp_reset_postdata();
            }

        }

        return $template;
    }
    add_filter( 'template_include', 'pms_woo_restrict_shop_page', 30);
endif;
