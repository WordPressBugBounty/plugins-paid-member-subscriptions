<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function pms_render_blocks( $block_content, $block ) {
    $block_attrs = isset( $block['attrs']['pmsContentRestriction'] ) ? $block['attrs']['pmsContentRestriction'] : null;

    if ( !isset( $block_attrs ) ) {
        return $block_content;
    }

    $block_attrs = wp_parse_args( $block_attrs, array(
        'subscription_plans'        => array(),
        'display_to'                => 'all',
        'not_subscribed'            => false,
        'show_logged_out'           => false,
        'enable_message_logged_in'  => false,
        'enable_message_logged_out' => false,
        'message_logged_in'         => '',
        'message_logged_out'        => '',
    ) );

    // Abort if:
    // the block is to be displayed to all users or
    // the current block is the Content Restriction Start block
    if ( $block_attrs['display_to'] === 'all' || $block['blockName'] === 'pms/content-restriction-start' ) {
        return $block_content;
    }

	if ( is_array( $block_attrs['subscription_plans'] ) ){
		$block_attrs['subscription_plans'] = implode(",", $block_attrs['subscription_plans']);
	}

    // Map the block content restriction settings to the pms-restrict shortcode parameters
    $atts = array(
            'subscription_plans'    => !empty( $block_attrs['subscription_plans'] ) ? $block_attrs['subscription_plans'] : '',
            'display_to'            => !empty( $block_attrs['not_subscribed'] ) ? 'not_subscribed' : $block_attrs['display_to'],
            'show_logged_out'       => !empty( $block_attrs['not_subscribed'] ) && !empty( $block_attrs['show_logged_out'] ) ? 'true' : '',
            'message'               => $block_attrs['display_to'] === 'not_logged_in'
                ? ( !empty( $block_attrs['enable_message_logged_out'] ) ? $block_attrs['message_logged_out'] : '' )
                : ( !empty( $block_attrs['enable_message_logged_in'] )  ? $block_attrs['message_logged_in']  : '' ),
        );

    return '<div>' . PMS_Shortcodes::restrict_content( $atts, $block_content ) . '</div>';
}
add_filter( 'render_block', 'pms_render_blocks', 10, 2 );

// excerpt_remove_blocks() renders only the leaves of wrapper blocks, so render_block never runs for a restricted wrapper
add_filter( 'content_pagination', 'pms_hide_restricted_blocks_from_excerpt', 999, 2 );
function pms_hide_restricted_blocks_from_excerpt( $pages, $post ) {
    if ( !doing_filter( 'get_the_excerpt' ) ) {
        return $pages;
    }

    foreach ( $pages as $index => $page ) {
        if ( strpos( $page, 'pmsContentRestriction' ) === false ) {
            continue;
        }

        $stripped = false;
        $blocks   = pms_strip_hidden_blocks_content( parse_blocks( $page ), $stripped );

        if ( $stripped ) {
            $pages[ $index ] = serialize_blocks( $blocks );
        }
    }

    return $pages;
}

function pms_strip_hidden_blocks_content( $blocks, &$stripped ) {
    foreach ( $blocks as $index => $block ) {
        if ( pms_is_block_hidden( $block ) ) {
            $blocks[ $index ]['innerBlocks']  = array();
            $blocks[ $index ]['innerHTML']    = '';
            $blocks[ $index ]['innerContent'] = array();
            $stripped = true;
        } elseif ( !empty( $block['innerBlocks'] ) ) {
            $blocks[ $index ]['innerBlocks'] = pms_strip_hidden_blocks_content( $block['innerBlocks'], $stripped );
        }
    }

    return $blocks;
}

function pms_is_block_hidden( $block ) {
    $visibility_probe = 'pms-block-visibility-probe';

    return strpos( pms_render_blocks( $visibility_probe, $block ), $visibility_probe ) === false;
}

/**
 * Adds the `pmsContentRestriction` attribute to all blocks
 */
add_action( 'wp_loaded', 'pms_add_custom_attributes_to_blocks', 199 );
function pms_add_custom_attributes_to_blocks() {

	$registered_blocks = WP_Block_Type_Registry::get_instance()->get_all_registered();

	foreach( $registered_blocks as $name => $block ) {

		$block->attributes['pmsContentRestriction'] = array(
			'type'    => 'object',
            'properties' => array(
                'subscription_plans' => array(
                    'type' => 'array',
                ),
                'display_to' => array(
                    'type' => 'string',
                ),
                'not_subscribed' => array(
                    'type' => 'boolean',
                ),
                'show_logged_out' => array(
                    'type' => 'boolean',
                ),
                'enable_message_logged_in' => array(
                    'type' => 'boolean',
                ),
                'enable_message_logged_out' => array(
                    'type' => 'boolean',
                ),
                'message_logged_in' => array(
                    'type' => 'string',
                ),
                'message_logged_out' => array(
                    'type' => 'string',
                ),
                'panel_open' => array(
                    'type' => 'boolean',
                ),
            ),
			'default' => array(
                'subscription_plans'       => array(),
                'display_to'               => 'all',
                'not_subscribed'           => false,
                'show_logged_out'          => false,
                'enable_message_logged_in' => false,
                'enable_message_logged_out'=> false,
                'message_logged_in'        => '',
                'message_logged_out'       => '',
                'panel_open'               => false,
            ),
		);
	}

}
