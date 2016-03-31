<?php
/*
Plugin Name: Advanced Custom Fields: Manual Image Crop
Version: 1.0.0
Author: Matthias Bloch
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/


// 1. set text domain
// Reference: https://codex.wordpress.org/Function_Reference/load_plugin_textdomain
load_plugin_textdomain( 'acf-manual_image_crop', false, dirname( plugin_basename(__FILE__) ) . '/lang/' );

// 2. Include field type for ACF5
// $version = 5 and can be ignored until ACF6 exists
function include_field_types_image_crop( $version ) {
    include_once('acf-manual-image-crop-v5.php');

}

add_action('acf/include_field_types', 'include_field_types_image_crop');


add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'acf_manual_image_crop_action_links' );

function acf_manual_image_crop_action_links( $links ) {
// changed
   $links[] = '<a href="'. get_admin_url(null, 'options-media.php') .'">'.__('Settings','acf-manual_image_crop').'</a>';
// changed END
   return $links;
}

?>
