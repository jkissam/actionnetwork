jQuery(document).ready(function($){

	/**
	 * Admin tabs
	 */
	
	if (!$('.wrap .nav-tab-wrapper .nav-tab-active').length) {
		$('.wrap .nav-tab-wrapper .nav-tab:eq(0)').addClass('nav-tab-active');
	}

	var currentTab = $('.wrap .nav-tab-wrapper .nav-tab-active').attr('href');
	$(currentTab).show();

	$('.wrap .nav-tab-wrapper .nav-tab, .wrap h1 .page-title-action').click(function(event){
		var showTab = $(this).attr('href');
		$('.wrap .nav-tab-wrapper .nav-tab-active').removeClass('nav-tab-active');
		if ($(this).hasClass('nav-tab')) {
			$(this).addClass('nav-tab-active');
		} else {
			$('.wrap .nav-tab-wrapper .nav-tab[href="'+showTab+'"]').addClass('nav-tab-active');
		}
		if ($(showTab).length && $(showTab).hasClass('actionnetwork-admin-tab')) {
			event.preventDefault();
			$('.actionnetwork-admin-tab').hide();
			$(showTab).show();
		}
	});
	
	/**
	 * Form validation
	 */
	
	$('form .error').focus(function(){
		$(this).removeClass('error');
	});

	/**
	 * Copy to clipboard
	 */
	$('button.copy').click(function(event){
		event.preventDefault();
		var $self = $(this);
		var $input = $($self.attr('data-copytarget'));
		if ($input.length && ( $input.is('input') || $input.is('textarea') ) ) {
			$input.select();
			try {
				// copy text
				document.execCommand('copy');
				var copyText = $self.text();
				$self.addClass('copied').text(actionnetworkText.copied);
				setTimeout(function() { $self.removeClass('copied').text(copyText); }, 400);
				$input.blur();
			}
			catch (err) {
				alert(actionnetworkText.pressCtrlCToCopy);
			}
		}
	});

	/**
	 * If there is an existing API Key, make sure user is sure they want to change it
	 */
	if ($('#actionnetwork_api_key').val().length) {
		$('#actionnetwork_api_key').attr('readonly','readonly');
		$('#actionnetwork_api_key').after('<button id="actionnetwork_api_key_change">'+actionnetworkText.changeAPIKey+'</button>');
		$('#actionnetwork_api_key_change').click(function(event){
			event.preventDefault();
			if ( confirm( actionnetworkText.confirmChangeAPIKey ) ) {
				$('#actionnetwork_api_key').removeAttr('readonly').focus();
			}
		});
	}

});
