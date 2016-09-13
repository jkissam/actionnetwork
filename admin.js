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
	
});