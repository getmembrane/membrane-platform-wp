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


//define('MEMBRANE_API_URL','http://api.getmembrane.com/');
define('MEMBRANE_API_URL','http://api.membrane-endpoint.dev/');
define('MEMBRANE_PLUGIN_NAME', "membrane-platform-wp");

/***** ADMIN SECTION *****/

// Add an edit option to comment editing screen
add_action( 'add_meta_boxes_comment', 'membrane_extend_comment_add_meta_box' );
function membrane_extend_comment_add_meta_box() {
    add_meta_box( 'membrane_client', __( 'Comment Metadata' ), 'membrane_extend_comment_meta_box', 'comment', 'normal', 'high' );
}

//Add a field to the comments, enabling us to input a client id
function membrane_extend_comment_meta_box ( $comment ) {
    $membraneClient = get_comment_meta( $comment->comment_ID, 'membrane_client', true );
    wp_nonce_field( 'extend_comment_update', 'extend_comment_update', false );
    ?>
    <p>
        <label for="membrane_client"><?php _e( 'Membrane Client' ); ?></label>
        <input type="text" name="membrane_client" disabled value="<?php echo esc_attr( $membraneClient ); ?>" class="widefat" />
    </p>
    <?php
}

//Add a link to the row actions on comments
add_filter('comment_row_actions', 'add_membrane_report_link', 10, 2);
function add_membrane_report_link($actions, $comment)
{
    //Only show the link if it's not already spam
    if ($_GET['comment_status'] !== 'spam') {
        $actions['report_link'] = '<a href="'.admin_url('admin.php').'?action=membrane_report&commentid='.$comment->comment_ID.'"  class="membrane_report">' . __('Report hater') . '</a>';
    }
   return $actions;
}

//A notice on success
add_action( 'admin_notices', 'membrane_report_success_notice' );
function membrane_report_success_notice() {
    if ( !isset($_GET['membrane_admin_success_notice'])) {
      return;
    }
    ?>
    <div class="notice notice-success is-dismissible">
        <p>Comment successfully reported!</p>
    </div>
    <?php
}

//Report a client id to the backend
add_action( 'admin_action_membrane_report', 'membrane_report' );
function membrane_report()
{
    $commentID = htmlspecialchars($_GET['commentid'], ENT_QUOTES);
    $comment = get_comment($commentID);
    $clientID = get_comment_meta($commentID,'membrane_client',true);

    if (!empty($commentID)) {
        $response = wp_remote_post(MEMBRANE_API_URL.'reports',[
                            'method' => 'POST',
                            'body' => [
                                'report' => [
                                    'client_id' => $clientID,
                                    'source' => 'demosite',
                                    'content' => $comment->comment_content,
                                ]
                            ]
                        ]);

        $statusCode = wp_remote_retrieve_response_code( $response );
        if ($statusCode === 200) {

            add_action( 'admin_notices', 'membrane_report_success_notice' );
            //$body = wp_remote_retrieve_body( $response );

            wp_set_comment_status( $commentID, 'spam');
            wp_redirect($_SERVER['HTTP_REFERER'].'&membrane_admin_success_notice');
            exit();
        }
    }
}

//Load custom css
function load_membrane_admin_scripts($hook) {
        // Load only on ?page=mypluginname
        if($hook != 'edit-comments.php') {
                return;
        }
        wp_enqueue_style( 'membrane_admin_style', plugins_url('css/style.css', __FILE__));
}
add_action( 'admin_enqueue_scripts', 'load_membrane_admin_scripts' );


/***** PUBLIC SECTION ******/

//Add an extra field to the comments, containing the client id
add_filter('comment_form_default_fields', 'custom_fields');
function custom_fields($fields)
{
    $fields[ 'membrane_client' ] = '<p class="comment-form-membrane-client">'.
      '<label for="membrane_client">' . __( 'Membrane Client' ) . '</label>'.
      '<input id="membrane_client" name="membrane_client" type="text" size="30" /></p>';

  return $fields;
}

//Check with the API if the comment should be accepted or rejected
add_filter( 'pre_comment_approved' , 'comment_assessment' , '99', 2 );
function comment_assessment( $approved , $commentdata )
{
  if (!empty($_POST['membrane_client'])) {
      $response = wp_remote_get(MEMBRANE_API_URL.'clients/'.$_POST['membrane_client']);
      $body = wp_remote_retrieve_body( $response );
      $body = json_decode($body);

      if ($body->assessment === 'accept') {
          return 1;
      } else {
          return 'spam';
      }
  }
  return 1;
}

// Save the comment meta data along with comment
add_action( 'comment_post', 'save_comment_meta_data' );
function save_comment_meta_data( $comment_id ) {
  if ((isset( $_POST['membrane_client'])) && ($_POST['membrane_client'] != '')) {
     // $membraneClient = wp_filter_nohtml_kses($_POST['membrane_client']);
      add_comment_meta( $comment_id, 'membrane_client', $_POST['membrane_client']);
  }
}
