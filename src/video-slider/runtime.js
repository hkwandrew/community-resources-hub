const SLIDER_SELECTOR = '[data-bci-video-slider]';
const SLIDE_SELECTOR = '[data-bci-video-slider-slide]';
const LOGO_SELECTOR = '[data-bci-video-slider-logo]';
const PLAY_SELECTOR = '[data-bci-video-slider-play]';
const STOP_SELECTOR = '[data-bci-video-slider-stop]';
const FRAME_SELECTOR = '[data-bci-video-slider-frame]';
const PLACEHOLDER_SELECTOR = '[data-bci-video-slider-placeholder]';
const ACTIVE_CLASS = 'is-active';
const PLAYING_CLASS = 'is-playing';
const SWIPE_THRESHOLD = 48;
const TRACK_TRANSITION_MS = 260;

function wrapIndex( index, length ) {
	if ( length < 1 ) {
		return 0;
	}

	return ( ( index % length ) + length ) % length;
}

function addAutoplay( url ) {
	if ( ! url ) {
		return '';
	}

	if ( url.includes( 'autoplay=1' ) ) {
		return url;
	}

	return `${ url }${ url.includes( '?' ) ? '&' : '?' }autoplay=1`;
}

function getParts( slider ) {
	return {
		stage: slider.querySelector( '.bci-video-slider__stage' ),
		track: slider.querySelector( '[data-bci-video-slider-track]' ),
		slides: Array.from( slider.querySelectorAll( SLIDE_SELECTOR ) ),
		logos: Array.from( slider.querySelectorAll( LOGO_SELECTOR ) ),
	};
}

function getVisualOrder( index, activeIndex, length ) {
	if ( length < 2 ) {
		return 0;
	}

	const distance = wrapIndex( index - activeIndex, length );

	return distance === length - 1 ? 0 : distance + 1;
}

function syncSlideOrder( slides, activeIndex ) {
	slides.forEach( ( slide, index ) => {
		slide.style.order = String(
			getVisualOrder( index, activeIndex, slides.length )
		);
	} );
}

function getFirstVisualSlide( slides ) {
	return slides.reduce( ( firstSlide, slide ) => {
		if ( ! firstSlide ) {
			return slide;
		}

		const firstOrder = Number.parseInt( firstSlide.style.order || '0', 10 );
		const slideOrder = Number.parseInt( slide.style.order || '0', 10 );

		return slideOrder < firstOrder ? slide : firstSlide;
	}, null );
}

function setTrackOffset( track, offset ) {
	track.style.transform = `translateX(-${ offset }px)`;
}

function getTrackOffset( slider, activeIndex ) {
	const { slides } = getParts( slider );
	const firstVisualSlide = getFirstVisualSlide( slides );

	if ( ! slides[ activeIndex ] || ! firstVisualSlide ) {
		return 0;
	}

	return Math.max(
		0,
		slides[ activeIndex ].offsetLeft - firstVisualSlide.offsetLeft
	);
}

function syncTrackOffset( slider, activeIndex ) {
	const { track } = getParts( slider );

	if ( ! track ) {
		return;
	}

	setTrackOffset( track, getTrackOffset( slider, activeIndex ) );
}

function resetStageScroll( slider ) {
	const { stage } = getParts( slider );

	if ( stage ) {
		stage.scrollLeft = 0;
	}
}

function resetSlidePlayback( slide ) {
	const frame = slide.querySelector( FRAME_SELECTOR );
	const placeholder = slide.querySelector( PLACEHOLDER_SELECTOR );
	const playButton = slide.querySelector( PLAY_SELECTOR );
	const stopButton = slide.querySelector( STOP_SELECTOR );

	if ( frame ) {
		frame.removeAttribute( 'src' );
	}

	if ( placeholder ) {
		placeholder.hidden = false;
	}

	slide.classList.remove( PLAYING_CLASS );

	if ( playButton ) {
		playButton.hidden = false;
	}

	if ( stopButton ) {
		stopButton.hidden = true;
	}
}

function commitActiveBciVideoSlide( slider, activeIndex ) {
	const { slides, logos } = getParts( slider );

	syncSlideOrder( slides, activeIndex );

	slides.forEach( ( slide, index ) => {
		const isActive = index === activeIndex;
		const playButton = slide.querySelector( PLAY_SELECTOR );

		slide.classList.toggle( ACTIVE_CLASS, isActive );
		slide.setAttribute( 'aria-hidden', isActive ? 'false' : 'true' );

		if ( playButton ) {
			if ( isActive ) {
				playButton.removeAttribute( 'tabindex' );
			} else {
				playButton.setAttribute( 'tabindex', '-1' );
			}
		}

		if ( ! isActive ) {
			resetSlidePlayback( slide );
		}
	} );

	logos.forEach( ( logo, index ) => {
		const isActive = index === activeIndex;

		logo.classList.toggle( ACTIVE_CLASS, isActive );
		logo.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );

		if ( isActive ) {
			logo.removeAttribute( 'tabindex' );
		} else {
			logo.setAttribute( 'tabindex', '-1' );
		}
	} );

	slider.style.setProperty(
		'--bci-video-slider-active-index',
		String( activeIndex )
	);
	slider.dataset.bciVideoSliderActiveIndex = String( activeIndex );
	syncTrackOffset( slider, activeIndex );
	resetStageScroll( slider );
}

function getNavigationDirection( currentSlideIndex, activeIndex, length ) {
	const forwardDistance = wrapIndex(
		activeIndex - currentSlideIndex,
		length
	);
	const backwardDistance = wrapIndex(
		currentSlideIndex - activeIndex,
		length
	);

	if ( forwardDistance === backwardDistance ) {
		return activeIndex > currentSlideIndex ? 1 : -1;
	}

	return forwardDistance < backwardDistance ? 1 : -1;
}

function isAdjacentNavigation( currentSlideIndex, activeIndex, length ) {
	return (
		1 === wrapIndex( activeIndex - currentSlideIndex, length ) ||
		1 === wrapIndex( currentSlideIndex - activeIndex, length )
	);
}

function nextFrame( callback ) {
	if ( 'function' === typeof window.requestAnimationFrame ) {
		window.requestAnimationFrame( callback );
		return;
	}

	window.setTimeout( callback, 0 );
}

function forceTrackLayout( track ) {
	track.getBoundingClientRect();
}

function normalizeHash( hash ) {
	const value = hash.startsWith( '#' ) ? hash.slice( 1 ) : hash;

	try {
		return window.decodeURIComponent( value );
	} catch {
		return value;
	}
}

function getHashSlideIndex( slides ) {
	const hash = normalizeHash( window.location.hash || '' );

	if ( ! hash ) {
		return null;
	}

	const index = slides.findIndex(
		( slide ) =>
			slide.id === hash || slide.dataset.bciVideoSliderAnchor === hash
	);

	return index < 0 ? null : index;
}

function activateHashSlide( slider ) {
	const { slides } = getParts( slider );
	const slideIndex = getHashSlideIndex( slides );

	if ( null === slideIndex ) {
		return false;
	}

	setActiveBciVideoSlide( slider, slideIndex, { animate: false } );

	return true;
}

export function setActiveBciVideoSlide( slider, nextIndex, options = {} ) {
	const { track, slides } = getParts( slider );

	if ( ! slides.length ) {
		return;
	}

	const activeIndex = wrapIndex( nextIndex, slides.length );
	const currentSlideIndex = currentIndex( slider );

	if (
		activeIndex === currentSlideIndex &&
		slider.classList.contains( 'is-initialized' )
	) {
		return;
	}

	if ( 'true' === slider.dataset.bciVideoSliderAnimating ) {
		return;
	}

	const shouldAnimate =
		track &&
		slides.length > 1 &&
		false !== options.animate &&
		slider.classList.contains( 'is-initialized' ) &&
		isAdjacentNavigation( currentSlideIndex, activeIndex, slides.length );
	const offset = shouldAnimate
		? getTrackOffset( slider, currentSlideIndex )
		: 0;

	if ( ! shouldAnimate || offset <= 0 ) {
		commitActiveBciVideoSlide( slider, activeIndex );
		return;
	}

	const currentSlide = slides[ currentSlideIndex ];
	const direction = getNavigationDirection(
		currentSlideIndex,
		activeIndex,
		slides.length
	);
	const targetOffset = direction > 0 ? offset * 2 : 0;

	if ( currentSlide ) {
		resetSlidePlayback( currentSlide );
	}

	slider.dataset.bciVideoSliderAnimating = 'true';

	let isFinished = false;
	let fallbackTimer = null;
	const onTransitionEnd = ( event ) => {
		if ( event.target === track && 'transform' === event.propertyName ) {
			finish();
		}
	};
	const finish = () => {
		if ( isFinished ) {
			return;
		}

		isFinished = true;

		if ( fallbackTimer ) {
			window.clearTimeout( fallbackTimer );
		}

		track.removeEventListener( 'transitionend', onTransitionEnd );
		slider.classList.remove( 'is-initialized' );
		forceTrackLayout( track );
		commitActiveBciVideoSlide( slider, activeIndex );
		forceTrackLayout( track );
		slider.dataset.bciVideoSliderAnimating = 'false';
		nextFrame( () => {
			nextFrame( () => {
				slider.classList.add( 'is-initialized' );
			} );
		} );
	};

	track.addEventListener( 'transitionend', onTransitionEnd );
	fallbackTimer = window.setTimeout( finish, TRACK_TRANSITION_MS + 100 );
	setTrackOffset( track, targetOffset );
}

export function playBciVideoSlide( slider, slide ) {
	const frame = slide.querySelector( FRAME_SELECTOR );
	const videoSrc = frame?.dataset.videoSrc || '';

	if ( ! frame || ! videoSrc ) {
		return;
	}

	const placeholder = slide.querySelector( PLACEHOLDER_SELECTOR );

	getParts( slider ).slides.forEach( ( item ) => {
		if ( item !== slide ) {
			resetSlidePlayback( item );
		}
	} );

	frame.setAttribute( 'src', addAutoplay( videoSrc ) );

	if ( placeholder ) {
		placeholder.hidden = true;
	}

	const playButton = slide.querySelector( PLAY_SELECTOR );
	const stopButton = slide.querySelector( STOP_SELECTOR );

	if ( playButton ) {
		playButton.hidden = true;
	}

	if ( stopButton ) {
		stopButton.hidden = false;
	}

	slide.classList.add( PLAYING_CLASS );
}

function currentIndex( slider ) {
	const parsed = Number.parseInt(
		slider.dataset.bciVideoSliderActiveIndex || '0',
		10
	);

	return Number.isNaN( parsed ) ? 0 : parsed;
}

function bindSlider( slider ) {
	if ( slider.dataset.bciVideoSliderInitialized === 'true' ) {
		return;
	}

	const { slides, logos } = getParts( slider );
	let pointerStartX = null;
	let resizeFrame = null;

	if ( slides.length < 1 ) {
		return;
	}

	const prev = slider.querySelector( '[data-bci-video-slider-prev]' );
	const next = slider.querySelector( '[data-bci-video-slider-next]' );

	slides.forEach( ( slide, index ) => {
		slide.dataset.slideIndex = String( index );
		const playButton = slide.querySelector( PLAY_SELECTOR );

		if ( playButton ) {
			playButton.addEventListener( 'click', () => {
				playBciVideoSlide( slider, slide );
			} );
		}

		const stopButton = slide.querySelector( STOP_SELECTOR );

		if ( stopButton ) {
			stopButton.addEventListener( 'click', () => {
				resetSlidePlayback( slide );

				if ( playButton ) {
					playButton.focus();
				}
			} );
		}
	} );

	logos.forEach( ( logo, index ) => {
		logo.dataset.slideIndex = String( index );
		logo.addEventListener( 'click', () => {
			setActiveBciVideoSlide( slider, index );
		} );
	} );

	window.addEventListener( 'hashchange', () => {
		activateHashSlide( slider );
	} );

	if ( prev ) {
		prev.addEventListener( 'click', () => {
			setActiveBciVideoSlide( slider, currentIndex( slider ) - 1 );
		} );
	}

	if ( next ) {
		next.addEventListener( 'click', () => {
			setActiveBciVideoSlide( slider, currentIndex( slider ) + 1 );
		} );
	}

	slider.addEventListener( 'keydown', ( event ) => {
		if ( 'ArrowLeft' === event.key ) {
			event.preventDefault();
			setActiveBciVideoSlide( slider, currentIndex( slider ) - 1 );
		}

		if ( 'ArrowRight' === event.key ) {
			event.preventDefault();
			setActiveBciVideoSlide( slider, currentIndex( slider ) + 1 );
		}

		if ( 'Escape' === event.key ) {
			resetSlidePlayback( slides[ currentIndex( slider ) ] );
		}
	} );

	slider.addEventListener( 'pointerdown', ( event ) => {
		pointerStartX = event.clientX;
	} );

	slider.addEventListener( 'pointerup', ( event ) => {
		if ( null === pointerStartX ) {
			return;
		}

		const deltaX = event.clientX - pointerStartX;
		pointerStartX = null;

		if ( Math.abs( deltaX ) < SWIPE_THRESHOLD ) {
			return;
		}

		setActiveBciVideoSlide(
			slider,
			currentIndex( slider ) + ( deltaX < 0 ? 1 : -1 )
		);
	} );

	window.addEventListener( 'resize', () => {
		if ( resizeFrame ) {
			window.cancelAnimationFrame( resizeFrame );
		}

		resizeFrame = window.requestAnimationFrame( () => {
			syncTrackOffset( slider, currentIndex( slider ) );
		} );
	} );

	slider.dataset.bciVideoSliderInitialized = 'true';
	if ( ! activateHashSlide( slider ) ) {
		setActiveBciVideoSlide( slider, 0, { animate: false } );
	}
	slider.classList.add( 'is-initialized' );
}

export function initBciVideoSliders( root = document ) {
	const scope = root?.querySelectorAll ? root : document;
	const sliders =
		scope.matches && scope.matches( SLIDER_SELECTOR )
			? [ scope ]
			: Array.from( scope.querySelectorAll( SLIDER_SELECTOR ) );

	sliders.forEach( bindSlider );
}
