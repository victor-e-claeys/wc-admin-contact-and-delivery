<?php
/*
Plugin Name: Woocommerce Delivery Date
Description: Allows to add delivery date in WooCommerce Backend
Version: 1.0
Author: Vincent Claeys
Author URI: https://www.vincentclaeys.com
License: GPLv2
*/

add_filter( 'manage_edit-shop_order_columns', 'wcvcdd_shop_order_column', 20 );
function wcvcdd_shop_order_column($columns)
{
    $columns['delivery-date'] = __( 'Delivery date','wcvcdd');
    return $columns;
}

add_action( 'manage_shop_order_posts_custom_column' , 'wcvcdd_orders_list_column_content', 20, 2 );
function wcvcdd_orders_list_column_content( $column, $post_id )
{
    if($column === 'delivery-date'){
        printf(
            '<span class="wcvcdd no-link" data-id="%d" data-value="%d" data-nonce="%s"></span>', 
            $post_id, 
            wcvcdd_get_delivery_date($post_id),
            wp_create_nonce('set_delivery_datetime_'.$post_id)
        );
    }
}

function wcvcdd_get_delivery_date($post_id = null){
    global $post;
    return get_post_meta($post_id ? $post_id : $post->ID, 'wcvcdd', TRUE);
}

function wcvcdd_get_datetime_format(){
    return get_option('date_format') . ' ' . get_option('time_format');
}

add_action( 'admin_enqueue_scripts', 'wcvcdd_admin_enqueue_scripts' );
function wcvcdd_admin_enqueue_scripts(){
    wp_enqueue_script( 'moment', 'https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.22.1/moment-with-locales.min.js' );
    wp_enqueue_script( 'jquery-datetimepicker', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.20/jquery.datetimepicker.full.min.js', array('jquery') );
    wp_enqueue_style( 'jquery-datetimepicker', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.20/jquery.datetimepicker.min.css' );
    
    wp_enqueue_style( 'wcvcdd', plugins_url('wc-vc-delivery-date.css', __FILE__) );
    wp_enqueue_script( 'wcvcdd', plugins_url('wc-vc-delivery-date.js', __FILE__), array('jquery','jquery-datetimepicker', 'moment') );
    wp_localize_script('wcvcdd', 'wcvcdd', array(
        'locale' => get_locale(),
        'format' => 'DD/MM/YYYY HH:mm',
        'add_note_ajax_time_format' => sprintf( __( 'added on %1$s at %2$s', 'woocommerce' ), wc_date_format(), wc_time_format() )
    ));
}


add_action( 'wp_ajax_set_delivery_datetime', 'wcvcdd_ajax_set_delivery_datetime' );
function wcvcdd_ajax_set_delivery_datetime(){
    if( !check_ajax_referer( 'set_delivery_datetime_'.$_POST['id'], 'security', false ) ){
        wp_die('', '', 401);
    }
    if( current_user_can('manage_woocommerce') && $_POST['id'] && $_POST['datetime'] ){
        wp_die( update_post_meta($_POST['id'], 'wcvcdd', $_POST['datetime']) );
    }
    wp_die('', '', 400);
}

add_action( 'wp_ajax_set_contacted', 'wcvcdd_ajax_set_contacted' );
function wcvcdd_ajax_set_contacted(){
    if( !check_ajax_referer( 'set_contacted_'.$_REQUEST['id'], 'security', false ) ){
        wp_die('', '', 401);
    }
    if( current_user_can('manage_woocommerce') && $_REQUEST['id'] ){
        $order      = wc_get_order( $_REQUEST['id'] );
        $comment_id = $order->add_order_note( __('Client was contacted', 'wcvcdd'), false, true );
        if( update_post_meta($_REQUEST['id'], 'contacted', $comment_id) && update_comment_meta($comment_id, 'contacted', $_REQUEST['id']) )
            wp_die( $comment_id );
        else
            wp_die(-1);
    }
    wp_die('','', 400);
}

add_action('delete_comment_meta', 'wcvcdd_delete_contacted', 10, 4);
function wcvcdd_delete_contacted($meta_ids, $object_id, $meta_key, $meta_value){
    if($meta_key === 'contacted'){
        delete_post_meta($meta_value, 'contacted');
    }
}

add_filter( 'woocommerce_admin_order_actions', 'add_custom_order_status_actions_button', 100, 2 );
function add_custom_order_status_actions_button( $actions, $order ) {
    if ( ! $order->has_status( array( 'complete' ) ) ) {

        $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
        $actions['notice'] = array(
            'url'       => '#post-' . $order_id,
            'name'      => __( 'Notes', 'wcvcdd' ),
            'action'    => "notes"
        );
        $contacted = get_post_meta($order_id, 'contacted', TRUE);
        $url = $contacted ? 
            '#contacted='.$contacted :
            wp_nonce_url( admin_url( 'admin-ajax.php?action=set_contacted&id=' . $order_id ), 'set_contacted_'.$order_id, 'security' );
        $actions['contacted'] = array(
            'url'       => $url,
            'name'      => __( 'Contacted', 'wcvcdd' ),
            'action'    => "contacted"
        );
    }
    return $actions;
}

add_action('admin_footer', 'wcvcdd_notes_modal');
function wcvcdd_notes_modal(){
    ?>
    <script type="text/template" id="tmpl-wc-modal-view-notes">
			<div class="wc-backbone-modal wc-order-preview">
				<div class="wc-backbone-modal-content wc-notes-modal">
					<section class="wc-backbone-modal-main" role="main">
						<header class="wc-backbone-modal-header">
                            <h1>
                                <?php _e('Order #{{data.id}} notes', 'wcvcdd') ?>
                            </h1>
                            <button class="modal-close modal-close-link dashicons dashicons-no-alt">
								<span class="screen-reader-text">Close modal panel</span>
							</button>
						</header>
						<article>
                            <ul class="notes">
                            <# _.each( data.notes, function( note ){ #>
                            <li rel="{{note.id}}" class="note <# if(note.id == data.comment_id) print('selected') #>">
                                <div class="note_content">
                                    {{note.content}}
                                </div>
                                <p class="meta">
                                    <abbr class="exact-date">
                                        <# print(moment.utc(note.date_created.date).format(wcvcdd.format)) #>
                                    </abbr>
                                    <?php printf(__( 'by %s', 'woocommerce' ), '' ); ?> {{note.added_by}}
                                    <a href="#" class="delete_note" role="button"><?php _e( 'Delete note', 'woocommerce' ); ?></a>
                                </p>
                            </li>
                            <# }) #>
                            </ul>
						</article>
						<footer>
							<div class="inner">
                                <div class="add-order-note" data-id="{{data.id}}" data-nonce="<?php echo wp_create_nonce('add-order-note') ?>">
                                    <input placeholder="<?php _e('Note', 'wcvcdd') ?>" class="new-note" name="note" />
                                    <a class="button wc-action-button">Ajouter</a>
                                </div>
                            </div>
						</footer>
					</section>
				</div>
			</div>
			<div class="wc-backbone-modal-backdrop modal-close"></div>
		</script>
    <?php
}

add_action('wp_ajax_woocommerce_get_order_notes', 'wcvcdd_get_order_notes');

function wcvcdd_get_order_notes() {

    if ( ! current_user_can( 'edit_shop_orders' ) ) {
        wp_die( -1 );
    }

    $post_id   = absint( $_POST['order_id'] );

    if ( $post_id > 0 ) {
        remove_filter( 'comments_clauses', array( 'WC_Comments', 'exclude_order_comments' ) );
        $note_ids = get_comments(array(
            'fields' => 'ids',
            'post_id' => $post_id,
            'type'    => 'order_note',
        ));
        $notes = array();
        foreach($note_ids as $id){
            $notes[] = wc_get_order_note($id);
        }
        echo wp_json_encode($notes);
        wp_die();
    }

}