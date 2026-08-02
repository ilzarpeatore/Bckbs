$(document).on('click', '[data-modal-form="form"]', function () {

    $("#formModal input ,#formModal select,#formModal textarea").prop("disabled", false);
    var app_title = $(this).attr('data-app-title');
    var app_size = $(this).attr('data-size');
    var app_icon = $(this).attr('data-icon');
    var url = $(this).attr('data--href');
    var render = $(this).attr('data-render');
    var _this = $(this);
    openModal(app_title,app_size,app_icon,url,render,_this);
});

function openModal(app_title = '',app_size,app_icon = 'assignment',url,render,_this){
    if (_this !== undefined){
        if(_this.attr('data-custom-icon') === 'font_icon'){
            $('.card-icon').html('<i class="'+_this.attr('data-icon-class')+'"></i>');
        }
    }

    if (app_size === 'small')
    {
        $('.modal-dialog').removeClass('modal-extra-large modal-small')
    }else{
        $('.modal-dialog').addClass('modal-extra-large modal-lg')
    }

    $.get(url, function (data) {
        var html = data.data;
        var footer = data.footer;
        if( footer == undefined ) {
            $('.modal-footer').addClass('d-none')
            $('.form_footer').html('');
        }
        
        if (render !== undefined && render !== '' && render !== null){
            $('.'+render).html(html);
        } else{
            $(".main_form").html(html);
            if (typeof window.initCommunityPostForms === 'function') {
                window.initCommunityPostForms(document.querySelector('.main_form'));
            }
            if (typeof window.initCommunityCommentComposers === 'function') {
                window.initCommunityCommentComposers(document.querySelector('.main_form'));
            }
            // $('form').validator();
            if ( footer !== undefined && footer !== '' && footer !== null ) {
                $('.form_footer').html(footer);
                $('.modal-footer').removeClass('d-none')
                if (typeof window.initCommunityCommentComposers === 'function') {
                    window.initCommunityCommentComposers(document.querySelector('.form_footer'));
                }
            }
            $("#formTitle").empty().append(app_title);
            $("#form-icon").html(app_icon);
            $("#formModal").modal("show");
        }
        // setTimeout(function () {
        //     if (_this.attr('data-plugin-init') !== 'false'){
        //         pluginInti();
        //     }
        // },200);
    });
}