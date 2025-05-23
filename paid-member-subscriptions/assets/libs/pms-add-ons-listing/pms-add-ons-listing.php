<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class PMS_Addons_List_Table extends WP_List_Table {

    public $header;

    public $section_header;
    public $section_header_free;
    public $section_versions;
    public $sections;

    public $images_folder;
    public $text_domain;

    public $all_addons;
    public $all_plugins;

    public $current_version;

    public $tooltip_header;
    public $tooltip_content;

    function __construct(){

        //Set parent defaults
        parent::__construct(array(
            'singular' => 'add-on',     //singular name of the listed records
            'plural' => 'add-ons',    //plural name of the listed records
            'ajax' => false        //does this table support ajax?
        ));

        //this is necessary because of WP_List_Table
        $this->prepare_items();

        //enqueue scripts and styles
        add_action('admin_footer', array($this, 'pms_print_assets'));

    }

    /**
     * Print js and css
     * @param $hook
     */
    function pms_print_assets(){
        wp_enqueue_style('wp-pointer');
        wp_enqueue_script('wp-pointer');
        wp_localize_script( 'wp-pointer', 'pms_add_ons_pointer', array( 'tooltip_header' => $this->tooltip_header, 'tooltip_content' => $this->tooltip_content ) );

        wp_enqueue_style('pms-add-ons-listing-css', plugin_dir_url(__FILE__) . 'assets/css/pms-add-ons-listing.css', false);
        wp_enqueue_script('pms-add-ons-listing-js', plugin_dir_url(__FILE__) . 'assets/js/pms-add-ons-listing.js', array('jquery'));

    }

    /**
     * Define the columns here and their headers
     * @return array
     */
    function get_columns(){
        $columns = array(
            'cb'        	=> '<input type="checkbox" />', //Render a checkbox instead of text
            'icon'     	=> '',
            'add_on'    => __('Add-On', $this->text_domain ), //phpcs:ignore
            'actions'     => '',
        );
        return $columns;
    }

    /**
     * The checkbox column
     * @param object $item
     * @return string|void
     */
    function column_cb($item){
        if( $item['type'] === 'add-on') {
            in_array( $this->current_version, $this->section_versions ) ? $disabled = '' : $disabled = 'disabled';
            return '<input type="checkbox" name="pms_add_ons[]" ' . checked($this->is_add_on_active($item['slug']), true, false) . ' '. $disabled .' value="' . $item['slug'] . '" />';
        }elseif( $item['type'] === 'plugin') {
            $all_wp_plugins = get_plugins();
            array_key_exists( $item['slug'], $all_wp_plugins ) ? $disabled = '' : $disabled = 'disabled';//add disabled if the current version isn't eligible
            if( empty($disabled) ){
                is_plugin_active_for_network( $item['slug'] ) ? $disabled = 'disabled' : $disabled = '';
            }

            return '<input type="checkbox" name="pms_plugins[]" ' . checked(is_plugin_active($item['slug']), true, false) . ' '. $disabled .' value="' . $item['slug'] . '" />';
        }
    }

    /**
     * The icon column
     * @param $item
     * @return string
     */
    function column_icon($item){
        return '<img src="'.$this->images_folder. $item['icon'] .'" width="64" height="64" alt="'. $item['name'] .'">';
    }

    /**
     * The column where we display the addon name and description
     * @param $item
     * @return string
     */
    function column_add_on($item){
        return '<strong class="pms-add-ons-name">'. $item['name'] . '</strong><br/>'. $item['description'];
    }

    /**
     * The actions column for the addons
     * @param $item
     * @return string
     */
    function column_actions($item){

        $action = '';
        //for plugins we can do something general
        if( $item['type'] === 'plugin' ) {

            $all_wp_plugins = get_plugins();
            if( array_key_exists( $item['slug'], $all_wp_plugins ) ) {
                if( is_plugin_active_for_network( $item['slug'] ) ){
                    $action = '<a class="right button button-secondary" href="' . esc_url(network_admin_url( 'plugins.php' )) . '">' . __('Manage in Network', $this->text_domain) . '</a>'; //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                }
                else {
                    if( isset( $_REQUEST['page'] ) ) {
                        if (is_plugin_active($item['slug'])) {
                            $action = '<a class="right button button-secondary" href="' . esc_url(wp_nonce_url(add_query_arg('pms_plugins', $item['slug'], admin_url('admin.php?page=' . sanitize_text_field( $_REQUEST['page'] ) . '&pms_add_ons_action=deactivate')), 'pms_add_ons_action')) . '">' . __('Deactivate', $this->text_domain) . '</a>'; //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                        } else {
                            $action = '<a class="right button button-primary" href="' . esc_url(wp_nonce_url(add_query_arg('pms_plugins', $item['slug'], admin_url('admin.php?page=' .sanitize_text_field( $_REQUEST['page'] ) . '&pms_add_ons_action=activate')), 'pms_add_ons_action')) . '">' . __('Activate', $this->text_domain) . '</a>'; //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
                        }
                    }
                }
            }
            else{
                $action = '<a target="_blank" class="right button button-secondary" href="'. $item['download_url'] .'">' . __('Download', $this->text_domain) . '</a>'; //phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
            }


        }
        elseif ( $item['type'] === 'add-on' ){//this is more complicated as there are multiple cases, I think it should be done through filters in each plugin

            in_array( $this->current_version, $this->section_versions ) ? $disabled = '' : $disabled = 'disabled'; //add disabled if the current version isn't eligible

            if ( $this->is_add_on_active( $item['slug'] ) ) {
                $action = '<a class="right button button-secondary" '.$disabled.' href="'. esc_url( wp_nonce_url( add_query_arg( 'pms_add_ons', $item['slug'], admin_url( 'admin.php?page='. sanitize_text_field( $_REQUEST['page'] ) . '&pms_add_ons_action=deactivate' ) ), 'pms_add_ons_action' ) ) .'">' . __('Deactivate', $this->text_domain) . '</a>';//phpcs:ignore
            } else {
                $action = '<a class="right button button-primary" '.$disabled.' href="'. esc_url( wp_nonce_url( add_query_arg( 'pms_add_ons', $item['slug'], admin_url( 'admin.php?page='. sanitize_text_field( $_REQUEST['page'] ). '&pms_add_ons_action=activate' ) ), 'pms_add_ons_action' ) ) .'">' . __('Activate', $this->text_domain) . '</a>';//phpcs:ignore
            }
        }


        $documentation = '<a target="_blank" class="right" href="'. $item['doc_url']  . '">' . __( 'Documentation', $this->text_domain ) . '</a>';//phpcs:ignore

        return $action . $documentation;
    }


    //don't generate a bulk actions dropdown by returning an empty array
    function get_bulk_actions() {
        return array( );//don't show bulk actions
    }



    /**
     * Function that initializez the object properties
     */
    function prepare_items() {
        $columns = $this->get_columns();
        $this->_column_headers = array($columns, array(), array());//the two empty arrays are hidden and sortable
        $this->set_pagination_args( array( ) ); //we do not need pagination
    }


    /** Here start the customizations for multiple tables and our custom html **/


    /**
     * Show our own search box, we don't use the default search of Table listing
     */
    function show_search_box(){
        ?>
        <p class="pms-add-ons-search-box">
            <input type="text" id="pms-add-ons-search-input" name="s" value="" placeholder="<?php esc_html_e( 'Search for add-ons...', $this->text_domain ); //phpcs:ignore ?>">
        </p>
        <?php
    }

    /**
     * Show the submit button
     */
    function show_sumbit_button(){
        ?>
        <input type="submit" class="button-primary" value="<?php esc_html_e('Save Add-ons', $this->text_domain); //phpcs:ignore?>">
        <?php
    }

    /**
     * This is the function that adds more sections (tables) to the listing
     */
    function add_section(){
        ob_start();
        ?>
        <div class="pms-add-ons-section">
            <?php if( !empty( $this->section_header ) ): ?>

                <h2><?php echo esc_html( $this->section_header['title'] );?></h2>

                <?php if( !$this->current_version != 'Paid Member Subscriptions' && !empty( $this->section_header_free ) && !in_array( $this->current_version, $this->section_versions ) ) : ?>
                    <p class="description cozmoslabs-description-upsell"><?php echo wp_kses_post( $this->section_header_free['description'] ); ?></p>
                <?php elseif( !empty( $this->section_header['description'] ) ) : ?>
                    <p class="description"><?php echo esc_html( $this->section_header['description'] ); ?></p>
                <?php endif; ?>
            <?php endif; ?>

            <?php
            foreach( $this->items as $item ) {
                if( $item['type'] === 'add-on' )
                    $this->all_addons[] = $item['slug'];
                elseif( $item['type'] === 'plugin' )
                    $this->all_plugins[] = $item['slug'];
            }
            $this->display(); /* this is the function from the table listing class */
            ?>
        </div>
        <?php

        $output = ob_get_contents();

        ob_end_clean();

        $this->sections[] = $output;
    }


    /**
     * The function that actually displays all the tables and the surrounding html
     */
    function display_addons(){
        ?>
        <div class="wrap" id="pms-add-ons-listing">
            <h1></h1>
            <!-- WordPress Notices are added after the h1 tag -->

            <div class="pms-add-on-notice">
                <h3><?php esc_html_e( 'Leverage Proven Ways to Get Paid for Your Work', 'paid-member-subscriptions' ) ?></h3>
                <p><?php esc_html_e( 'Your membership site to the next level with a feature-packed WordPress membership plugin.', 'paid-member-subscriptions' ) ?></p>
            </div>

            <form id="pms-addons" method="post">

                <?php

                if( !empty( $this->sections ) ){
                    foreach ( $this->sections as $section ){
                        echo $section;/* phpcs:ignore */ /* escaped inside the variable */
                    }
                }
                ?>

                <!-- For plugins, we also need to ensure that the form posts back to our current page -->
                <input type="hidden" name="pms_all_add_ons" value="<?php echo esc_attr( implode( '|' ,$this->all_addons ) ); ?>" />
                <input type="hidden" name="pms_all_plugins" value="<?php echo( !empty( $this->all_plugins ) ? esc_attr( implode( '|' ,$this->all_plugins ) ) : '' ); ?>" />
                <input type="hidden" name="pms_add_ons_action" value="bulk_action" />
                <?php wp_nonce_field('pms_add_ons_action'); ?>
            </form>
        </div>

        <?php

    }

    static function is_add_on_active( $slug ){
        return apply_filters( 'pms_add_on_is_active', false, $slug );
    }
}

/**
 * process the actions for the Add-ons page
 */
add_action( 'admin_init', 'pms_add_ons_listing_process_actions', 1 );
function pms_add_ons_listing_process_actions(){
    if (current_user_can( 'manage_options' ) && isset( $_REQUEST['pms_add_ons_action'] ) && isset($_REQUEST['_wpnonce']) && wp_verify_nonce( sanitize_text_field( $_REQUEST['_wpnonce'] ), 'pms_add_ons_action' ) ){

        if( $_REQUEST['pms_add_ons_action'] === 'bulk_action' ){
            if( !empty( $_POST['pms_all_plugins'] ) || !empty( $_POST['pms_all_add_ons'] ) ){//make sure we have all the data
                //sanitize all data
                $all_plugins = explode( '|', sanitize_text_field( $_POST['pms_all_plugins'] ) );
                $all_add_ons = explode( '|', sanitize_text_field( $_POST['pms_all_add_ons'] ) );
                $plugins_to_activate = array();
                if( !empty($_POST['pms_plugins']) && is_array($_POST['pms_plugins']) ){
                    $plugins_to_activate = array_map( 'sanitize_text_field', $_POST['pms_plugins'] );
                }
                $add_ons_to_activate = array();
                if( !empty($_POST['pms_add_ons']) && is_array($_POST['pms_add_ons']) ){
                    $add_ons_to_activate = array_map( 'sanitize_text_field', $_POST['pms_add_ons'] );
                }

                foreach( $all_plugins as $plugin ){
                    if( in_array( $plugin, $plugins_to_activate ) ){
                        if( !is_plugin_active( $plugin ) ) {
                            activate_plugin( $plugin );
                        }
                    }
                    else{
                        if( is_plugin_active( $plugin ) ) {
                            deactivate_plugins( $plugin );
                        }
                    }
                }

                foreach( $all_add_ons as $add_on ){
                    if( in_array( $add_on, $add_ons_to_activate ) ){
                        do_action( 'pms_add_ons_activate', $add_on );
                    }
                    else{
                        do_action( 'pms_add_ons_deactivate', $add_on );
                    }
                }

            }
        }
        elseif ( $_REQUEST['pms_add_ons_action'] === 'activate' ){
            if( !empty( $_REQUEST['pms_plugins'] ) ){//we have a plugin
                $plugin_slug = sanitize_text_field( $_REQUEST['pms_plugins'] );
                if( !is_plugin_active( $plugin_slug ) ) {
                    activate_plugin( $plugin_slug );
                }
            }
            elseif( !empty( $_REQUEST['pms_add_ons'] ) ){//we have a add-on
                do_action( 'pms_add_ons_activate', sanitize_text_field($_REQUEST['pms_add_ons']) );
            }
        }
        elseif ( $_REQUEST['pms_add_ons_action'] === 'deactivate' ){
            if( !empty( $_REQUEST['pms_plugins'] ) ){//we have a plugin
                $plugin_slug = sanitize_text_field( $_REQUEST['pms_plugins'] );
                if( is_plugin_active( $plugin_slug ) ) {
                    deactivate_plugins( $plugin_slug );
                }
            }
            elseif( !empty( $_REQUEST['pms_add_ons'] ) ){//we have a add-on
                do_action( 'pms_add_ons_deactivate', sanitize_text_field($_REQUEST['pms_add_ons']) );
            }
        }

        wp_safe_redirect( add_query_arg( 'pms_add_ons_listing_success', 'true', admin_url( 'admin.php?page='. sanitize_text_field( $_REQUEST['page'] ) ) ) );//phpcs:ignore
    }
}

/**
 * Add a notice on the add-ons page if the save was successful
 */
function pms_add_ons_listing_success_notice() {
    if ( isset($_GET['pms_add_ons_listing_success']) ){
        if( class_exists('PMS_Add_General_Notices') ) {
            new PMS_Add_General_Notices('pms_add_ons_listing_success',
                sprintf(__('%1$sAdd-ons settings saved successfully%2$s', 'paid-member-subscriptions'), "<p>", "</p>"),
                'updated notice is-dismissible');
        }
    }
}
add_action( 'init', 'pms_add_ons_listing_success_notice' );

