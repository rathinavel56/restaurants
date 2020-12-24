$(window).scroll(function () { $(".navbar-fixed-top").toggleClass("scrolled", $(this).scrollTop() > 200) }),

    $(document).ready(function () {
        $(".select-dropdown .dropdown-menu a").on("click", function (e) {
            $(e.currentTarget).parents(".select-dropdown").find(".dropdown-toggle").html($(this).html() + '<span class="fa fa-angle-right"></span>')
        });

        $("#create_page").on("click", function (e) {
            $('.clsAfterCreate').removeClass('hide');
            $('.clsBeforeCreate').addClass('hide');
        });
    });

// color picker

$(document).ready(function () {

    $('.demo').each(function () {
        $(this).minicolors({
            control: $(this).attr('data-control') || 'hue',
            defaultValue: $(this).attr('data-defaultValue') || '',
            format: $(this).attr('data-format') || 'hex',
            keywords: $(this).attr('data-keywords') || '',
            inline: $(this).attr('data-inline') === 'true',
            letterCase: $(this).attr('data-letterCase') || 'lowercase',
            opacity: $(this).attr('data-opacity'),
            position: $(this).attr('data-position') || 'bottom',
            swatches: $(this).attr('data-swatches') ? $(this).attr('data-swatches').split('|') : [],
            change: function (value, opacity) {
                if (!value) return;
                if (opacity) value += ', ' + opacity;
                if (typeof console === 'object') {
                    console.log(value);
                }
            },
            theme: 'bootstrap'
        });

    });

});

$(document).ready(function () {
    $(".clsDataOpt ul li .media-left input:checkbox").on('click', function (e) {
        $(e.currentTarget).parents(".clsDataOpt ul li .media").toggleClass("clsChecked");
    });

    $("#loginBtn").on("click", function () {
        $('.clsAfterLogin').removeClass('hide');
        $('.clsBeforeLogin').addClass('hide');
    });

    $("#forgetBtn").on("click", function () {
        $('.clsAfterForget').removeClass('hide');
        $('.clsBeforeForget').addClass('hide');
    });

    $(function () {
        $('[data-toggle="tooltip"]').tooltip()
    });

    $("#replyBtn").on("click", function () {
        $('.clsReplyText').removeClass('hide');
        $('.clsReply').addClass('hide');
    });

    $("#sentBtn").on("click", function () {
        $('.clsReplyMedia').removeClass('hide');
        $('.clsReplyText').addClass('hide');
    });
});


    // color picker