
function htmlspecialchars( str ) {
    return $('<div/>').text(str).html()
}

$(document).ready(function(){
	
	// Resize any images where the image has been squished to fit in the specified dimensions
	
	$('img.fitOnLoad').one('load',function(){
		// First check for exact match - nothing to do in this case
		if (this.width==this.naturalWidth && this.height==this.naturalHeight) return;
		
		let self = $(this);
		// Then check the aspect ratio - if this is the same the image has simply been scaled which is OK
		if (Math.abs(this.width/this.height - this.naturalWidth/this.naturalHeight)<0.01) return;
		
		let inWidth = this.naturalWidth;
		let inHeight = this.naturalHeight;
		let outWidth = this.width;
		let outHeight = this.height;
		
        let scale = Math.min( outWidth/inWidth, outHeight/inHeight );
        resizedWidth = inWidth*scale;
        resizedHeight = inHeight*scale;
        vPadding = outHeight-resizedHeight;
        hPadding = outWidth-resizedWidth;

        console.log(resizedWidth,resizedHeight);
		this.width=resizedWidth;
		this.height=resizedHeight;
        console.log(hPadding,outWidth,resizedWidth);
		self.css('padding-top',(parseInt($('img').css('padding-top'))+vPadding/2)+'px');
		self.css('padding-bottom',(parseInt($('img').css('padding-bottom'))+vPadding/2)+'px');
		self.css('padding-left',(parseInt($('img').css('padding-left'))+hPadding/2)+'px');
		self.css('padding-right',(parseInt($('img').css('padding-right'))+hPadding/2)+'px');
        self.css('box-sizing','content-box');
		
	}).each(function() {
		if(this.complete) $(this).trigger('load');
	});

	$('img.resizeOnLoad').one('load',function(){
		this.width=this.naturalWidth;
		this.height=this.naturalHeight;
	}).each(function() {
		if(this.complete) $(this).trigger('load');
	});
})
