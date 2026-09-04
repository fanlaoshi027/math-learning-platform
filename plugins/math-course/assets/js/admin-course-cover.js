jQuery(function($){
    var frame;
    $('#mc-select-cover').on('click',function(e){
        e.preventDefault();
        if(frame){ frame.open(); return; }
        frame=wp.media({title:'选择课程封面',button:{text:'使用此封面'},multiple:false,library:{type:'image'}});
        frame.on('select',function(){
            var a=frame.state().get('selection').first().toJSON();
            var url=a.sizes&&a.sizes.medium?a.sizes.medium.url:a.url;
            $('input[name="mathcourse_cover_id"]').val(a.id||0);
            $('input[name="mathcourse_cover"]').val(url||'');
            $('#mc-cover-preview').html(url?'<img src="'+url.replace(/"/g,'&quot;')+'" style="display:block;width:100%;height:auto;border-radius:10px;">':'');
        });
        frame.open();
    });
    $('#mc-remove-cover').on('click',function(e){
        e.preventDefault();
        $('input[name="mathcourse_cover_id"]').val('0');
        $('input[name="mathcourse_cover"]').val('');
        $('#mc-cover-preview').empty();
    });
});