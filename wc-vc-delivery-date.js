jQuery(function($){
    $.datetimepicker.setLocale( wcvcdd.locale.substr(0,2) );
    $('.wcvcdd').each(function(){
        var value = null;
        if($(this).data('value'))
            value = $(this).data('value');
        $(this).text( moment(value).format(wcvcdd.format) );
        $(this).datetimepicker({
            onChangeDateTime: function(datetime, $element){
                $element.text( moment(datetime).format(wcvcdd.format) );
                $.post(
                    ajaxurl,
                    {
                        security: $element.data('nonce'),
                        action: 'set_delivery_datetime',
                        datetime: datetime.getTime(),
                        id: $element.data('id')
                    },
                    function(response){
                        console.log(response);
                    }
                );
            },
            value: value
        });
    });
    
    $(document).on('click', '.wc-action-button-contacted:not([href^="#"])', function(e){
        var $button    = $( this );
        e.preventDefault();
        $.ajax({
            url:     this.href,
            type:    'GET',
            success: function( response ) {

                if ( response && response[0] ) {
                    $button.attr( 'href', '#contacted=' + response );
                }
            }
        });
    });
    
    $(document).on('click', '.wc-action-button-contacted[href^="#"]', function(e){
        get_order_notes($(this).parents('.type-shop_order').attr('id').substr(5), this.hash.substr(11));
    });

    $('.wc-action-button-notes').on('click', function(){
        get_order_notes(this.hash.substr(6));
    });

    function get_order_notes(order_id, comment_id){
        $.ajax({
            url:     wc_orders_params.ajax_url,
            data:    {
                order_id: order_id,
                action  : 'woocommerce_get_order_notes'
            },
            dataType: 'json',
            type:    'POST',
            success: function( response ) {

                if ( response && response[0] ) {
                    $(this).WCBackboneModal({
                        template: 'wc-modal-view-notes',
                        variable: {
                            comment_id: comment_id,
                            id: order_id,
                            notes: response
                        }
                    });
                }
            }
        });
    }

    function add_order_note(id, note, nonce){
        if(!note || note.length === 0) return;
        $.ajax({
            url:     wc_orders_params.ajax_url,
            data:    {
                note_type: '', //TODO
                note: note,
                security: nonce,
                post_id: id,
                action  : 'woocommerce_add_order_note'
            },
            type:    'POST',
            success: function( response ) {
                if ( response ) {
                    var $response = $(response);
                    $(".wc-notes-modal .notes").prepend($response);
                    $(".wc-notes-modal .new-note").val('');
                }
            }
        });
    }

    $(document).on('keydown', '.wc-notes-modal .add-order-note', function(e){
        var $addnote = $(this);
        if(e.key === 'Enter'){
            add_order_note( $addnote.data('id'), $addnote.find('.new-note').val(), $addnote.data('nonce') );
        }
    });
    $(document).on('click', '.wc-notes-modal .add-order-note .wc-action-button', function(e){
        var $addnote = $(this).parent();
        add_order_note( $addnote.data('id'), $addnote.find('.new-note').val(), $addnote.data('nonce') );
    });
})