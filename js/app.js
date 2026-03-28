'use strict';
$(function () {
	navToggle();
	domSelector();
	eventCollapse();
	tagGallery();
});

$(window).on('load', function () {
	setTimeout(() => {
		$('body').removeClass('loading');
		$('.loader-wrap').remove();
	}, 400);
});

function domSelector() {
	if ($('.cta')) {
		$('.cta').addClass('d-inline-flex align-items-center justify-content-center rounded-3 py-2 px-4 text-uppercase text-decoration-none');
	}
}

function navToggle() {
	$('#navCall').on('click', function () {
		$(this).toggleClass('clicked');
		$('#siteNav').toggleClass('d-none open');
		$('#siteNav .nav').toggleClass('flex-column');
	});
}

function eventCollapse() {
	$('.event-collapse .more').on('click', function () {
		var $eventCollapse = $(this).closest('.event-collapse');
		var $icon = $(this).find('.icon');

		if ($eventCollapse.hasClass('clicked')) {
			$eventCollapse.removeClass('clicked').find('.event-content').slideUp();
			$icon.text('+');
		} else {
			$('.event-collapse').removeClass('clicked').find('.event-content').slideUp().end().find('.icon').text('+');

			$eventCollapse.addClass('clicked').find('.event-content').slideDown();
			$icon.text('-');
		}
	});
}

function tagGallery() {
	$('#tagGallery .selected-tag').on('click', function () {
		$(this).find('.arrow .bi').toggleClass('bi-caret-up-fill bi-caret-down-fill');
		$(this).next('.tag-gallery').toggleClass('d-none d-md-flex d-flex flex-column mt-5');
	});
	$('#tagGallery .cta-tag').on('click', function () {
		$('#tagGallery .cta-tag').removeClass('active');
		$(this).addClass('active');
		let text = $(this).text();
		$('#tagGallery .selected-tag').find('.text').text(text);
		$('#tagGallery .selected-tag').find('.arrow .bi').toggleClass('bi-caret-up-fill bi-caret-down-fill');
		$('#tagGallery .tag-gallery').toggleClass('d-none d-md-flex d-flex flex-column mt-5');
	});
}
