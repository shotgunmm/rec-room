'use strict';
$(function () {
	navToggle();
	domSelector();
	eventCollapse();
	tagGallery();
	modalContentClick();
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
	let $gallery = $('.gallery-row .gallery');
	let $items = $gallery.find('.img');
	let colSpans = {pic2: 2, pic6: 2, pic11: 3};
	let originalHeights = null;

	$('#tagGallery .cta-tag').on('click', function () {
		$('#tagGallery .cta-tag').removeClass('active');
		$(this).addClass('active');

		let category = $(this).data('category');
		if (category === 'all') {
			$gallery.css('grid-template-rows', '');
			$items.css({display: '', 'grid-area': '', 'grid-column': '', height: ''});
		} else {
			if (!originalHeights) {
				originalHeights = [];
				$items.each(function (i) {
					originalHeights[i] = $(this).outerHeight();
				});
			}
			$gallery.css('grid-template-rows', 'auto');
			$items.each(function (i) {
				let $item = $(this);
				let span = 1;
				$.each(colSpans, function (cls, s) {
					if ($item.hasClass(cls)) { span = s; return false; }
				});
				$item.css({display: 'none', 'grid-area': 'unset', 'grid-column': 'span ' + span, height: originalHeights[i] + 'px'});
			});
			$items.filter('[data-category="' + category + '"]').css('display', '');
		}
	});
}

function modalContentClick() {
	const ctaButtons = document.querySelectorAll('.cta[data-bs-toggle="modal"]');
	const userModal = document.getElementById('userModal');

	if (!userModal) return;

	const headName = userModal.querySelector('.head-name');
	const textName = userModal.querySelector('.name');
	const modalPosition = userModal.querySelector('.position');
	const modalBio = userModal.querySelector('.bio');

	ctaButtons.forEach((cta) => {
		cta.addEventListener('click', (e) => {
			const box = cta.closest('.box');
			const name = box?.querySelector('.text .team-title')?.textContent.trim();
			const position = box?.querySelector('.text .team-position')?.textContent.trim();
			const bio = box?.querySelector('.text .team-bio')?.textContent.trim();
			
			if (headName && name) headName.textContent = name;
			if (textName && name) textName.textContent = name;
			if (modalPosition && position) modalPosition.textContent = position;
			if (modalBio && bio) modalBio.textContent = bio;
		});
	});
}
