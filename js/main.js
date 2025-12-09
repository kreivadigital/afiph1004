// (function ($) {
    	
// 	$("#btnObtenerTurno").click(function () {
//         alert('probando');
//     });

// })

function initAuthCodeFunctionToken() {

    // jQuery('#overlay').show();
    jQuery.ajax({
        url: ajax_var.admin_url,
        type: "POST",
        dataType: "json",
        data: {
            action: 'foo', 
            init: ajax_var.nonce
        },
        success: function (data) {
            // toastr.success(data.data);
            console.log(data.data);
            // location.reload();
            window.location.href = data.data;
            // jQuery('#overlay').hide();
        },
        error: function (data) {
            // toastr.error(data.data);
        }
    });

}

function closeThickbox() {
    tb_remove();
    importTransactions();
}

function closeThickboxTwo() {
    tb_remove();
    importTransactionsDateToday();
}

function importTransactions(){

    jQuery('#overlay').show();
    jQuery.ajax({
        url: ajax_var.admin_url,
        type: "POST",
        dataType: "json",
        data: {
            action: 'foo', 
            imp_transac: ajax_var.nonce,
            resultsFrom: sessionStorage.getItem("resultsFrom"),
            resultsTo: sessionStorage.getItem("resultsTo"),
            reservationID: sessionStorage.getItem("reservationID")
        },
        success: function (data) {
            jQuery('#overlay').hide();
            toastr.success('Importación realizada con éxito.');
            console.log(data.data);
            window.location.href = data.data.url_search;
        },
        error: function (data) {
            jQuery('#overlay').hide();
            toastr.error('Error al realizar la importación.');
            console.log(data.data);
        }
    });

}

function importTransactionsDateToday(){

    jQuery('#overlay').show();
    jQuery.ajax({
        url: ajax_var.admin_url,
        type: "POST",
        dataType: "json",
        data: {
            action: 'foo', 
            imp_transac_date_today: ajax_var.nonce
        },
        success: function (data) {
            jQuery('#overlay').hide();
            toastr.success('Importación realizada con éxito.');
            console.log(data.data);
            window.location.href = data.data.url_search;
        },
        error: function (data) {
            jQuery('#overlay').hide();
            toastr.error('Error al realizar la importación.');
            console.log(data.data);
        }
    });

}

function checkGetTransactionsNow(){

    jQuery('#overlay').show();
    jQuery.ajax({
        url: ajax_var.admin_url,
        type: "POST",
        dataType: "json",
        data: {
            action: 'foo', 
            act_values_transactions: ajax_var.nonce
        },
        success: function (data) {
            jQuery('#overlay').hide();
            toastr.success('Transacciones actualizadas con éxito.');
            console.log(data.data);
        },
        error: function (data) {
            jQuery('#overlay').hide();
            toastr.error('Error al actualizar las transacciones.');
            console.log(data.data);
        }
    });

}

function initLoadRecordsTransactions(){

    jQuery('#overlay').show();
    jQuery.ajax({
        url: ajax_var.admin_url,
        type: "POST",
        dataType: "json",
        data: {
            action: 'foo', 
            init_load_records: ajax_var.nonce
        },
        success: function (data) {
            jQuery('#overlay').hide();
            console.log(data.data);
            window.location.href = data.data.url_search;
        },
        error: function (data) {
            jQuery('#overlay').hide();
            console.log(data.data);
        }
    });

}

function genFacturar(val){
    
    console.log("llego");
    
    jQuery('#overlay').show();
    jQuery.ajax({
        url: ajax_var.admin_url,
        type: "POST",
        dataType: "json",
        data: {
            action: 'foo', 
            gen_facturar: ajax_var.nonce,
            code_transaction: val,
            type_gen: 'NULL'
        },
        success: function (data) {
            
            console.log("funciono llamado");
            
            jQuery('#overlay').hide();
           
            console.log(data.data);
            
            if(data.data.data != 'error'){
                document.getElementById(val).style.color = "#22b162";
                document.getElementById(val).style.borderColor = "#22b162";
                document.getElementById(val).textContent="Descargar";
                toastr.success(data.data.data);
                if (typeof data.data.rell !== 'undefined') {
                    var link = document.createElement('a');
                    link.href = data.data.rell;
                    link.download = data.data.name_file;
                    link.click();
                    link.remove();
                }
            }else{
                
                console.log("error de llamado");
                
                toastr.error('Error de facturación');
                Swal.fire({
                  title: 'Error en la conexión con AFIP',
                  showDenyButton: true,
                  showCancelButton: true,
                  confirmButtonText: 'Volver a intentar',
                  denyButtonText: `Probar con fecha de hoy`,
                  cancelButtonText: `Cancelar`,
                }).then((result) => {
                    
                    console.log("segundo llamado");
                    
                  /* Read more about isConfirmed, isDenied below */
                  if (result.isConfirmed) {//repetimos el ciclo

                    jQuery('#overlay').show();
                    jQuery.ajax({
                        url: ajax_var.admin_url,
                        type: "POST",
                        dataType: "json",
                        data: {
                            action: 'foo', 
                            gen_facturar: ajax_var.nonce,
                            code_transaction: val,
                            type_gen: 'NULL'
                        },
                        success: function (data) {
                            
                            console.log("funciona el segundo");
                            
                            jQuery('#overlay').hide();
                            console.log(data.data);
                            if(data.data.data != 'error'){
                                document.getElementById(val).style.color = "#22b162";
                                document.getElementById(val).style.borderColor = "#22b162";
                                document.getElementById(val).textContent="Descargar";
                                toastr.success(data.data.data);
                                if (typeof data.data.rell !== 'undefined') {
                                    var link = document.createElement('a');
                                    link.href = data.data.rell;
                                    link.download = data.data.name_file;
                                    link.click();
                                    link.remove();
                                }
                            }else{
                                
                                console.log("tampoco el segundo");
                                
                                toastr.error('Error de facturación');
                                Swal.fire({
                                  icon: 'error',
                                  title: 'Hubo un error...',
                                  text: 'Revisar la fecha de la factura o intente de nuevo en algunos minutos!'
                                })
                            }
                        },
                        error: function (data) {
                            jQuery('#overlay').hide();
                            // console.log(data.data);
                        }
                    });

                  } else if (result.isDenied) {//repetimos el ciclo pero llamando la fecha de hoy
                    
                    console.log("repetimos el ciclo pero llamando la fecha de hoy");
                    
                    jQuery('#overlay').show();
                    jQuery.ajax({
                        url: ajax_var.admin_url,
                        type: "POST",
                        dataType: "json",
                        data: {
                            action: 'foo', 
                            gen_facturar: ajax_var.nonce,
                            code_transaction: val,
                            type_gen: 'now'
                        },
                        success: function (data) {
                            
                            console.log("funciono");
                            
                            jQuery('#overlay').hide();
                            console.log(data.data);
                            if(data.data.data != 'error'){
                                document.getElementById(val).style.color = "#22b162";
                                document.getElementById(val).style.borderColor = "#22b162";
                                document.getElementById(val).textContent="Descargar";
                                toastr.success(data.data.data);
                                if (typeof data.data.rell !== 'undefined') {
                                    var link = document.createElement('a');
                                    link.href = data.data.rell;
                                    link.download = data.data.name_file;
                                    link.click();
                                    link.remove();
                                }
                            }else{
                                
                                console.log("no funciono");
                                
                                
                                toastr.error('Error de facturación');
                                Swal.fire({
                                  icon: 'error',
                                  title: 'Hubo un error...',
                                  text: 'Revisar la fecha de la factura o intente de nuevo en algunos minutos!'
                                })
                            }
                        },
                        error: function (data) {
                            jQuery('#overlay').hide();
                            // console.log(data.data);
                        }
                    });

                  }else{}
                })
            }
        },
        error: function (data) {
            jQuery('#overlay').hide();
            console.log(data.data);
        }
    });

}

function genFacturarT(val){
    
    console.log("llego T");
    
    jQuery('#overlay').show();
    jQuery.ajax({
        url: ajax_var.admin_url,
        type: "POST",
        dataType: "json",
        data: {
            action: 'foo', 
            gen_facturar_tipo_t: ajax_var.nonce,
            code_transaction: val,
            type_gen: 'NULL'
        },
        success: function (data) {
            
            console.log("funciono llamado");
            
            jQuery('#overlay').hide();
           
            console.log(data);
            
            // if(data.data.data != 'error'){
            //     document.getElementById(val).style.color = "#22b162";
            //     document.getElementById(val).style.borderColor = "#22b162";
            //     document.getElementById(val).textContent="Descargar";
            //     toastr.success(data.data.data);
            //     if (typeof data.data.rell !== 'undefined') {
            //         var link = document.createElement('a');
            //         link.href = data.data.rell;
            //         link.download = data.data.name_file;
            //         link.click();
            //         link.remove();
            //     }
            // }else{
                
            //     console.log("error de llamado");
                
            //     toastr.error('Error de facturación');
            //     Swal.fire({
            //       title: 'Error en la conexión con AFIP',
            //       showDenyButton: true,
            //       showCancelButton: true,
            //       confirmButtonText: 'Volver a intentar',
            //       denyButtonText: `Probar con fecha de hoy`,
            //       cancelButtonText: `Cancelar`,
            //     }).then((result) => {
                    
            //         console.log("segundo llamado");
                    
            //       /* Read more about isConfirmed, isDenied below */
            //       if (result.isConfirmed) {//repetimos el ciclo

            //         jQuery('#overlay').show();
            //         jQuery.ajax({
            //             url: ajax_var.admin_url,
            //             type: "POST",
            //             dataType: "json",
            //             data: {
            //                 action: 'foo', 
            //                 gen_facturar: ajax_var.nonce,
            //                 code_transaction: val,
            //                 type_gen: 'NULL'
            //             },
            //             success: function (data) {
                            
            //                 console.log("funciona el segundo");
                            
            //                 jQuery('#overlay').hide();
            //                 console.log(data.data);
            //                 if(data.data.data != 'error'){
            //                     document.getElementById(val).style.color = "#22b162";
            //                     document.getElementById(val).style.borderColor = "#22b162";
            //                     document.getElementById(val).textContent="Descargar";
            //                     toastr.success(data.data.data);
            //                     if (typeof data.data.rell !== 'undefined') {
            //                         var link = document.createElement('a');
            //                         link.href = data.data.rell;
            //                         link.download = data.data.name_file;
            //                         link.click();
            //                         link.remove();
            //                     }
            //                 }else{
                                
            //                     console.log("tampoco el segundo");
                                
            //                     toastr.error('Error de facturación');
            //                     Swal.fire({
            //                       icon: 'error',
            //                       title: 'Hubo un error...',
            //                       text: 'Revisar la fecha de la factura o intente de nuevo en algunos minutos!'
            //                     })
            //                 }
            //             },
            //             error: function (data) {
            //                 jQuery('#overlay').hide();
            //                 // console.log(data.data);
            //             }
            //         });

            //       } else if (result.isDenied) {//repetimos el ciclo pero llamando la fecha de hoy
                    
            //         console.log("repetimos el ciclo pero llamando la fecha de hoy");
                    
            //         jQuery('#overlay').show();
            //         jQuery.ajax({
            //             url: ajax_var.admin_url,
            //             type: "POST",
            //             dataType: "json",
            //             data: {
            //                 action: 'foo', 
            //                 gen_facturar: ajax_var.nonce,
            //                 code_transaction: val,
            //                 type_gen: 'now'
            //             },
            //             success: function (data) {
                            
            //                 console.log("funciono");
                            
            //                 jQuery('#overlay').hide();
            //                 console.log(data.data);
            //                 if(data.data.data != 'error'){
            //                     document.getElementById(val).style.color = "#22b162";
            //                     document.getElementById(val).style.borderColor = "#22b162";
            //                     document.getElementById(val).textContent="Descargar";
            //                     toastr.success(data.data.data);
            //                     if (typeof data.data.rell !== 'undefined') {
            //                         var link = document.createElement('a');
            //                         link.href = data.data.rell;
            //                         link.download = data.data.name_file;
            //                         link.click();
            //                         link.remove();
            //                     }
            //                 }else{
                                
            //                     console.log("no funciono");
                                
                                
            //                     toastr.error('Error de facturación');
            //                     Swal.fire({
            //                       icon: 'error',
            //                       title: 'Hubo un error...',
            //                       text: 'Revisar la fecha de la factura o intente de nuevo en algunos minutos!'
            //                     })
            //                 }
            //             },
            //             error: function (data) {
            //                 jQuery('#overlay').hide();
            //                 // console.log(data.data);
            //             }
            //         });

            //       }else{}
            //     })
            // }
        },
        error: function (data) {
            jQuery('#overlay').hide();
            console.log(data.data);
        }
    });

}

// Función para mostrar el modal
function editModal(id) {
    const modal = document.getElementById('editModal');
    const montoInput = document.getElementById('montoInput'); // ¡Accedemos al input por su ID!

    modal.style.display = 'flex'; // Cambia a 'flex' para mostrar y centrar

    // 1. Almacenar el ID en un atributo de datos del modal
    modal.setAttribute('data-current-id', id);

    // Opcional: Limpiar el input o cargar un valor inicial asociado al ID
    montoInput.value = ''; // Limpiar el campo al abrir el modal
}

// Función para cerrar el modal
function closeModal() {
    const modal = document.getElementById('editModal');
    const montoInput = document.getElementById('montoInput');

    modal.style.display = 'none'; // Vuelve a 'none' para ocultar

    // Opcional: Limpiar el ID almacenado y el valor del input al cerrar
    modal.removeAttribute('data-current-id');
    montoInput.value = '';
}

// Función que se ejecuta al hacer clic en "Guardar"
function guardarMonto() {
    const modal = document.getElementById('editModal');
    const montoInput = document.getElementById('montoInput'); // Accedemos al input por su ID

    // 1. Obtener el ID de la transacción del atributo de datos del modal
    const currentId = modal.getAttribute('data-current-id');
    const nuevoMonto = montoInput.value;

    if (!currentId) {
        console.error("Error: No se pudo obtener el ID de la transacción.");
        alert("Error: ID de transacción no encontrado. Por favor, intente de nuevo.");
        return;
    }

    jQuery('#overlay').show();
    jQuery.ajax({
        url: ajax_var.admin_url,
        type: "POST",
        dataType: "json",
        data: {
            action: 'foo', 
            update_amount: ajax_var.nonce,
            transaction_id: currentId,
            new_amount: nuevoMonto
        },
        success: function (data) {
            
            console.log("funciono llamado");
            
            jQuery('#overlay').hide();
            closeModal();
           
            console.log(data.data);
            
            // Validar la respuesta
            if (data.success) { // Accede a la propiedad 'success' en la raíz del objeto
                console.log('Mensaje de éxito:', data.data.message); // Accede a 'message' DENTRO de 'data'
                console.log('Filas afectadas:', data.data.rows_affected); // Accede a 'rows_affected' DENTRO de 'data'

                location.reload();

            } else { // 
                alert(data.data.message);
                console.error('Error del servidor:', data.data.message); // Accede a 'message' DENTRO de 'data'
                console.error('Detalles del error (si aplica):', data.data.error_details); // Accede a 'error_details' DENTRO de 'data'
            }
            
        },
        error: function (data) {
            jQuery('#overlay').hide();
            console.log(data.data);
        }
    });

}

// Opcional: Cerrar el modal haciendo clic fuera de él (en el overlay)
document.getElementById('editModal').addEventListener('click', function(event) {
    if (event.target === this) { // Si el clic fue directamente en el overlay y no en el contenido
        closeModal();
    }
});

function genFacturarOld(val){

    jQuery('#overlay').show();
    jQuery.ajax({
        url: ajax_var.admin_url,
        type: "POST",
        dataType: "json",
        data: {
            action: 'foo', 
            gen_facturar: ajax_var.nonce,
            code_transaction: val,
        },
        success: function (data) {
            jQuery('#overlay').hide();
            toastr.success(data.data.data);
            console.log(data.data);
                if (typeof data.data.rell !== 'undefined') {
                    var link = document.createElement('a');
                    link.href = data.data.rell;
                    link.download = data.data.name_file;
                    link.click();
                    link.remove();
                }
        },
        error: function (data) {
            jQuery('#overlay').hide();
            toastr.error('Error en conexión afip.');
            console.log(data.data);
        }
    });

}


function validateCamps(){

    var datefrom = document.getElementById("search_desde");
    var dateto = document.getElementById("search_hasta");
    var reservation_id = document.getElementById("search_reservaid");
    var metpago = document.getElementById("search_metpago");

    if (datefrom.value !== '' && dateto.value !== '' && reservation_id.value  === '') {

        jQuery('#overlay').show();
        jQuery.ajax({
            url: ajax_var.admin_url,
            type: "POST",
            dataType: "json",
            data: {
                action: 'foo', 
                imp_transac: ajax_var.nonce,
                resultsFrom: datefrom.value,
                resultsTo: dateto.value,
                reservationID: reservation_id.value,
                met_pago: metpago.value
            },
            success: function (data) {
                jQuery('#overlay').hide();
                toastr.success('Importación realizada con éxito.');
                console.log(data.data);
                window.location.href = data.data.url_search;
            },
            error: function (data) {
                jQuery('#overlay').hide();
                toastr.error('Error al realizar la importación.');
                console.log(data.data);
            }
        });

    }else if(datefrom.value === '' && dateto.value === '' && reservation_id.value  !== ''){

        jQuery('#overlay').show();
        jQuery.ajax({
            url: ajax_var.admin_url,
            type: "POST",
            dataType: "json",
            data: {
                action: 'foo', 
                imp_transac: ajax_var.nonce,
                resultsFrom: datefrom.value,
                resultsTo: dateto.value,
                reservationID: reservation_id.value,
                met_pago: metpago.value
            },
            success: function (data) {
                jQuery('#overlay').hide();
                toastr.success('Importación realizada con éxito.');
                console.log(data.data);
                window.location.href = data.data.url_search;
            },
            error: function (data) {
                jQuery('#overlay').hide();
                toastr.error('Error al realizar la importación.');
                console.log(data.data);
            }
        });

    }else if(datefrom.value === '' && dateto.value === '' && reservation_id.value  === '' && metpago.value !== ''){

        window.location.href = window.location.origin + '/wp-admin/admin.php?page=transactions_list&resultsFrom&resultsTo&reservationID&metpago=' + metpago.value;

    }else{
        toastr.error("Error");
    }

}