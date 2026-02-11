function search_despacho() {

    var valueinput = document.getElementById('despacho_field_search').value;

    jQuery('#overlay_web').show();
    jQuery.ajax({
        url: ajax_var.admin_url,
        type: "POST",
        dataType: "json",
        data: {
            action: 'foo', 
            web_view: ajax_var.nonce,
            valueinput: valueinput
        },
        success: function (data) {
            jQuery('#overlay_web').hide();
            toastr.success('Status actualizado.');
            console.log(data);
            // Get the modal
            jQuery("#modal-body").empty();
            jQuery("#modal-body").append(data.data);
            jQuery("#myModalWeb").modal();

        }
    });
    
}