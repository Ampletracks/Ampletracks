$(function(){

	var uploadHandler = '/asyncUpload.php';
    var uploadsInProgress = 0;

    $('body').on('submit','form',function(){
        if (!uploadsInProgress) return true;
        let abortUploads;
        if (window.onCheckAbortUploads) abortUploads = window.onCheckAbortUploads(uploadsInProgress);
        else {
            let message = uploadsInProgress>1 ?
                'There are still uploads in progress. If you continue these uploads will be cancelled.':
                'There is still an upload in progress. If you continue this upload will be cancelled.';
            message += ' Are you sure you wish to continue';
            abortUploads = window.confirm(message);
        }
        if (abortUploads) return true;
        return false;
    });

    function humanFileSize(bytes, si) {
        var thresh = si ? 1000 : 1024;
        if(Math.abs(bytes) < thresh) {
            return bytes + ' B';
        }
        var units = si
            ? ['kB','MB','GB','TB','PB','EB','ZB','YB']
            : ['KiB','MiB','GiB','TiB','PiB','EiB','ZiB','YiB'];
        var u = -1;
        do {
            bytes /= thresh;
            ++u;
        } while(Math.abs(bytes) >= thresh && u < units.length - 1);
        return bytes.toFixed(1)+' '+units[u];
    }
    
	function ajaxUpload( input, url, data, onSuccess ) {
		
        var file = input.get(0).files[0];
        
        var formData = new FormData();
        
        if (typeof(data)!=='undefined') {
            for (var key in data) {
                formData.append( key, data[key] );
            }
        }
        formData.append( 'MAX_FILE_SIZE', file.size );
        formData.append( 'file', file );
        
        var uploadContainer = input.closest('.asyncUploadContainer');
        uploadContainer.find('.error,.info').remove();

		var resetInput = function() {
			uploadContainer.find('.clickToCancel').show();
            progressBarContainer.hide();
            input.val('');
			input.show();
			uploadContainer.find('.error,.info').remove();
			uploadContainer.find('.cancelUpload').hide();
		}
        
        var progressBarContainer = uploadContainer.find('.progressBarContainer');
        var progressBar = progressBarContainer.find('.progressBar');
        progressBarContainer.show();
        progressBar.width(0);
        progressBarContainer.find('.message').text('Uploading: '+file.name);
        progressBarContainer.width(input.width());
        uploadContainer.find('.clickToCancel').hide();
        input.hide();

        var xhr;
        uploadContainer.find('.cancelUpload').show().off().on('click',function(e){
			if (xhr) xhr.abort();
			resetInput();
			e.preventDefault();
		});
        
        var displayError = function( message ){
            progressBarContainer.hide();
            $('<div class="error"></div>').html(message).appendTo(uploadContainer);
        }

        uploadsInProgress++;
		$.ajax({
            url: url,
            type: "POST",
            processData: false,
            contentType: false,
            data: formData,
            success: function(result) {
				resetInput();
                uploadsInProgress--;
                progressBarContainer.fadeOut('slow');
                var message = '';
                if (typeof(result.message)!='undefined' && result.message.length) message = result.message;

                if (typeof(result.status)==='undefined' || result.status!=='OK') {
					if (!message.length) message = 'Unknown error processing upload';
					displayError(message);
				} else {
					input.val('');
                    if (!message.length) {
						message = 'File uploaded: ' + file.name + ' (' + humanFileSize(file.size) + ')';
						var replaceButton = $('<button class="clickToEdit" >Replace</button>');
						uploadContainer.find('div.existing').text(message).append(replaceButton);
					} else {
						uploadContainer.find('div.existing').html(message);
					}
					uploadContainer.find('.clickToCancel').trigger('click');
					if (onSuccess) onSuccess();
                }
            },
            error: function(result) {
				resetInput();
                uploadsInProgress--;
				// Don't do anything else if upload was aborted..
				if (result.readyState == XMLHttpRequest.UNSENT) return;
                displayError(result.responseText);
            },
            dataType: 'json',
            xhr: function() {
                xhr = new window.XMLHttpRequest();

                xhr.upload.addEventListener("progress", function(evt) {
                  if (evt.lengthComputable) {
                    var percentComplete = evt.loaded / evt.total;
                    percentComplete = parseInt(percentComplete * 100);
                    progressBar.css('width',percentComplete+'%').text(percentComplete+'%');
                  }
                }, false);

                return xhr;
            }
        });
    }
		
	var uploadFormCount = 0;
	var fileRemoved = false;
	
	$('div.asyncUploadContainer').each(function(){
		var self = $(this);
		var input = self.find('input[type=file][data-asyncUploadId]').eq(0);

        // Add in the progress bar
        $('<button class="cancelUpload">Cancel</button>').insertAfter(input).hide();
        $('<div class="progressBarContainer"><div class="message"></div><div class="progressBar"></div></div>').insertAfter(input).hide();

		// Add in the "file removed" UI
		$('<div class="removing">Existing file will be removed on save<button class="clickToCancel">Keep existing</button></div>').hide().appendTo(self);


        // If there is an existing file displayed and it has something with "clickToEdit" class
        // then hide the edit interface until they click that button
        var edittingInterface = self.find('div.editting');
        var removingInterface = self.find('div.removing');
        var previewInterface = self.find('div.existing');
        var clickToEdit = self.find('.clickToEdit');
        var clickToCancel = self.find('.clickToCancel');
        
        var hasExistingFile = clickToEdit.length>0;
        if (hasExistingFile) edittingInterface.hide();
        
		else clickToCancel.hide();
        
        self.on('click','.clickToEdit', function(e){
			// Remove any error class on the container
			self.closest('.questionAndAnswer').removeClass('error');
			previewInterface.hide();
			edittingInterface.show();
			window.setTimeout(function(){ edittingInterface.find('input:file').trigger('click'); },100);
			e.preventDefault();
			return false;
		});
		
		// Also add a cancel button in so they can go back to the preview of the current file
		self.on('click','.clickToCancel',function(e){
	        self.find('.error,.info').remove();
			if (fileRemoved) {
				$.post(uploadHandler,{
					'mode'			: 'keep',
					'asyncUploadId'	: input.data('asyncuploadid'),
				});
			}
			previewInterface.show();
			edittingInterface.hide();
			removingInterface.hide();
			e.preventDefault();
			return false;
		});

		// Also add a "remove" button so they can ask for the current file to be removed
		self.on('click','.clickToRemove',function(e){
	        self.find('.error,.info').remove();

			$.post(uploadHandler,{
				'mode'			: 'remove',
				'asyncUploadId'	: input.data('asyncuploadid'),
			});
			fileRemoved = true;
			previewInterface.hide();
			if (hasExistingFile) {
				removingInterface.show();
			} else {
				edittingInterface.show();
			}
		});
        				
		// Add the upload button
		// Create a new form for it so it doesn't interfere with any existing form
		// The new form is at the end of the page, but we can insert the input outside the form
		// ...as long as we set the form="..." attribute on the file input

		uploadFormCount++;
		var formId = 'fileUploadForm_'+uploadFormCount;
		
		var uploadForm = $('<form></form>').appendTo('body');
		uploadForm.attr({
			id		: formId,
		})
        .on('submit',function(){ return false });
			
		input.attr('form',formId);
        input.on('change',function(){
            var self = $(this);
            var file = self.get(0).files[0];
            if (file.name.length && file.size) {
                ajaxUpload( self, uploadHandler, {
                    'mode'  : 'asyncUpload',
                    'asyncUploadId'    : self.data('asyncuploadid'),
                },
                function(){
					if (!hasExistingFile) previewInterface.find('.clickToRemove').hide();
					fileRemoved=false;
				});
            }
        });
	});

});
