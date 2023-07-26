$(function(){
  $('.defaultValueUsed').parent().on('change',function(){
    $(this).find('.defaultValueUsed').hide();
  });
});