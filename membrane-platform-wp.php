<?php
/**
 * @package Membrane
 */
/*
Plugin Name: Membrane
Plugin URI: https://getmembrane.com/
Description: Comments without haters
Version: 0.1
Author: Getmembrane
License: GPLv2 or later
Text Domain: membrane
*/

/*
Copyright since 2016 Membrane.
*/
define('MEMBRANE_API_URL','http://api.getmembrane.com/');

/***** ADMIN SECTION *****/

// Add an edit option to comment editing screen
add_action( 'add_meta_boxes_comment', 'extend_comment_add_meta_box' );
function extend_comment_add_meta_box() {
    add_meta_box( 'membrane_client', __( 'Comment Metadata - Extend Comment' ), 'extend_comment_meta_box', 'comment', 'normal', 'high' );
}

function extend_comment_meta_box ( $comment ) {
    $membraneClient = get_comment_meta( $comment->comment_ID, 'membrane_client', true );
    wp_nonce_field( 'extend_comment_update', 'extend_comment_update', false );
    ?>
    <p>
        <label for="membrane_client"><?php _e( 'Membrane Client' ); ?></label>
        <input type="text" name="membrane_client" value="<?php echo esc_attr( $membraneClient ); ?>" class="widefat" />
    </p>
    <?php
}

// Update comment meta data from comment editing screen
add_action( 'edit_comment', 'extend_comment_edit_metafields' );

function extend_comment_edit_metafields( $comment_id ) {
    if( ! isset( $_POST['extend_comment_update'] ) || ! wp_verify_nonce( $_POST['extend_comment_update'], 'extend_comment_update' ) ) return;

  if ( ( isset( $_POST['membrane_client'] ) ) && ( $_POST['membrane_client'] != '') ) :
  $membraneClient = wp_filter_nohtml_kses($_POST['membrane_client']);
  update_comment_meta( $comment_id, 'membrane_client', $membraneClient );
  else :
  delete_comment_meta( $comment_id, 'membrane_client');
  endif;
}

/***** PUBLIC SECTION ******/
add_filter('comment_form_default_fields', 'custom_fields');
function custom_fields($fields)
{
    $fields[ 'membrane_client' ] = '<p class="comment-form-membrane-client">'.
      '<label for="membrane_client">' . __( 'Membrane Client' ) . '</label>'.
      '<input id="membrane_client" name="membrane_client" type="text" size="30" /></p>';

  return $fields;
}

// Add the filter to call the membrane api and assess the client
add_filter( 'preprocess_comment', 'assess_membrane_client' );
function assess_membrane_client( $commentdata ) {
  if (!empty($_POST['membrane_client'])) {
      $response = wp_remote_get(MEMBRANE_API_URL.'clients/'.$_POST['membrane_client']);
      $body = json_decode($response['body'],true);

      if($body['assessment'] === 'reject') {
          wp_set_comment_status($commentdata['id'],'spam');
      }
      die('<pre>'.print_r($commentdata).'</pre>');
  }
  return $commentdata;
}

// Save the comment meta data along with comment
add_action( 'comment_post', 'save_comment_meta_data' );
function save_comment_meta_data( $comment_id ) {
  if ((isset( $_POST['membrane_client'])) && ($_POST['membrane_client'] != '')) {
     // $membraneClient = wp_filter_nohtml_kses($_POST['membrane_client']);
      add_comment_meta( $comment_id, 'membrane_client', $_POST['membrane_client']);
  }
}
