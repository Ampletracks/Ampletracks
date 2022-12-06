$(()=>{
    function toggleNav(self) {
        let close = self.attr('aria-expanded')=='true';

        let menus = self.closest('.toggle-menu').find('.toggle-menu__menu');
        self.parent().children('.toggle-menu__toggle').attr('aria-expanded',close?'false':'true');
        if (close) menus.removeClass('is-expanded');
        else menus.first().addClass('is-expanded');
    }
    $('.toggle-menu__toggle').on('click',function(){
        console.log('foo');
        toggleNav($(this));
    })
    .attr('aria-expanded','false');
});
