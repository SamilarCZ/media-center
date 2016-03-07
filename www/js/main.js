$(document).ready(function(){
    $('.close').click(function(e){
        var posterID = $(this).data('id');
        $('#' + posterID).hide();
        e.preventDefault();
        return false;
    });

    $('.posterLink').click(function(e){
        var posterID = $(this).data('id');
        $('#' + posterID).show();
        e.preventDefault();
        return false;
    });

    $('.closeDetail').click(function(e){
        var detailId = $(this).data('id');
        $('#detail' + detailId).hide();
        e.preventDefault();
        return false;
    });

    $('.detail').click(function(e){
        var detailId = $(this).data('id');
        $('#detail' + detailId).toggle();
        e.preventDefault();
        return false;
    });

    $('.togglePlayer').click(function(e){
        e.preventDefault();
        $('#player').slideToggle();
    });

    $('#player').slideToggle();
});

