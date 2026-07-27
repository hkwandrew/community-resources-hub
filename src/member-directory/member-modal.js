import { __ } from '../shared/i18n.js';

import {
	findMember,
	setText,
} from '../../blocks/opportunity-hub/src/view/shared-utils.js';

const SPOTLIGHT_VIDEO_LABEL = __(
	'Watch Our Spotlight Video',
	'community-resources-hub'
);
const VISIT_WEBSITE_LABEL = __( 'Visit Website', 'community-resources-hub' );
const HEX_COLOR_PATTERN = /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/iu;
const MODAL_LINEWORK_BLACK_FILL = '#191919';
const MODAL_LINEWORK_CHANNEL_OFFSET = 10;

function normalizeHexColor( color ) {
	const value = `${ color || '' }`.trim().toLowerCase();

	if ( ! HEX_COLOR_PATTERN.test( value ) ) {
		return '';
	}

	if ( 4 !== value.length ) {
		return value;
	}

	return `#${ value[ 1 ] }${ value[ 1 ] }${ value[ 2 ] }${ value[ 2 ] }${ value[ 3 ] }${ value[ 3 ] }`;
}

function modalLineworkFillColor( backgroundColor ) {
	const normalizedColor = normalizeHexColor( backgroundColor );

	if ( ! normalizedColor ) {
		return '';
	}

	if ( '#000000' === normalizedColor ) {
		return MODAL_LINEWORK_BLACK_FILL;
	}

	const channels = [ 1, 3, 5 ].map( ( start ) => {
		const channel = Number.parseInt(
			normalizedColor.slice( start, start + 2 ),
			16
		);

		return Math.max( 0, channel - MODAL_LINEWORK_CHANNEL_OFFSET );
	} );

	return `#${ channels
		.map( ( channel ) => channel.toString( 16 ).padStart( 2, '0' ) )
		.join( '' ) }`;
}

function toggleMemberRow( modal, key, isVisible ) {
	const row = modal.querySelector(
		`[data-wm-bci-member-modal-row="${ key }"]`
	);
	const divider = modal.querySelector(
		`[data-wm-bci-member-modal-divider="${ key }"]`
	);

	if ( row ) {
		row.hidden = ! isVisible;
	}

	if ( divider ) {
		divider.hidden = ! isVisible;
	}
}

function normalizedSocialPlatform( platform ) {
	const value = `${ platform || '' }`.toLowerCase();

	return value.replace( /[^a-z0-9]/g, '' );
}

function memberSocialIconFilename( platform ) {
	const normalized = normalizedSocialPlatform( platform );

	if ( normalized.includes( 'linkedin' ) ) {
		return 'bci-member-modal-linkedin.svg';
	}

	if ( normalized.includes( 'instagram' ) ) {
		return 'bci-member-modal-instagram.svg';
	}

	if ( normalized.includes( 'facebook' ) ) {
		return 'bci-member-modal-facebook.svg';
	}

	if ( normalized.includes( 'tiktok' ) ) {
		return 'bci-member-modal-tiktok.svg';
	}

	if ( 'x' === normalized || normalized.includes( 'twitter' ) ) {
		return 'bci-member-modal-x.svg';
	}

	return '';
}

function memberSocialIconMarkup( platform ) {
	const normalized = normalizedSocialPlatform( platform );

	if ( normalized.includes( 'youtube' ) ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M21.6 7.2a2.7 2.7 0 0 0-1.9-1.9C18 4.8 12 4.8 12 4.8s-6 0-7.7.45a2.7 2.7 0 0 0-1.9 1.9A28 28 0 0 0 2 12a28 28 0 0 0 .4 4.8 2.7 2.7 0 0 0 1.9 1.9c1.7.5 7.7.5 7.7.5s6 0 7.7-.45a2.7 2.7 0 0 0 1.9-1.9A28 28 0 0 0 22 12a28 28 0 0 0-.4-4.8ZM10 15.2V8.8l5.2 3.2Z"/></svg>';
	}

	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 4a8 8 0 1 0 8 8h-2a6 6 0 1 1-1.76-4.24L13 11h7V4l-2.34 2.34A7.95 7.95 0 0 0 12 4Z"/></svg>';
}

export function hydrateMemberModal( modal, member ) {
	if ( ! modal || ! member ) {
		return;
	}

	const ownerDocument = modal.ownerDocument || document;
	const hero = modal.querySelector( '[data-wm-bci-member-modal-hero]' );
	const logo = modal.querySelector( '[data-wm-bci-member-modal-logo]' );
	const socials = modal.querySelector( '[data-wm-bci-member-modal-socials]' );
	const videoLink = modal.querySelector( '[data-wm-bci-member-modal-video]' );
	const videoAction = modal.querySelector(
		'[data-wm-bci-member-modal-action-video]'
	);
	const websiteAction = modal.querySelector(
		'[data-wm-bci-member-modal-action-website]'
	);
	const actions = modal.querySelector( '[data-wm-bci-member-modal-actions]' );
	const emailLink = modal.querySelector( '[data-wm-bci-member-modal-email]' );
	const websiteLink = modal.querySelector(
		'[data-wm-bci-member-modal-website]'
	);
	const emailText = modal.querySelector(
		'[data-wm-bci-member-modal-email-text]'
	);
	const websiteText = modal.querySelector(
		'[data-wm-bci-member-modal-website-text]'
	);
	const overview = modal.querySelector(
		'[data-wm-bci-member-modal-overview]'
	);
	const programs = modal.querySelector(
		'[data-wm-bci-member-modal-programs]'
	);
	const attachments = modal.querySelector(
		'[data-wm-bci-member-modal-attachments]'
	);
	const connect = modal.querySelector( '[data-wm-bci-member-modal-connect]' );

	if ( hero ) {
		hero.classList.toggle( 'is-empty', ! member.logoUrl );

		const heroBackgroundColor = normalizeHexColor(
			member.heroBackgroundColor
		);

		if ( heroBackgroundColor ) {
			hero.style.setProperty(
				'--wm-bci-member-modal-hero-background',
				heroBackgroundColor
			);
			hero.style.setProperty(
				'--wm-bci-member-modal-linework-fill',
				modalLineworkFillColor( heroBackgroundColor )
			);
		} else {
			hero.style.removeProperty(
				'--wm-bci-member-modal-hero-background'
			);
			hero.style.removeProperty( '--wm-bci-member-modal-linework-fill' );
		}
	}

	if ( logo ) {
		logo.hidden = ! member.logoUrl;
		if ( member.logoUrl ) {
			logo.src = member.logoUrl;
			logo.alt = member.title || '';
		} else {
			logo.removeAttribute( 'src' );
			logo.alt = '';
		}
	}

	setText( modal, '[data-wm-bci-member-modal-title]', member.title );
	const overviewHtml =
		'string' === typeof member.overviewHtml
			? member.overviewHtml.trim()
			: '';

	if ( overview ) {
		overview.innerHTML = overviewHtml;
	}

	setText(
		modal,
		'[data-wm-bci-member-modal-community-served]',
		member.communityServed || ''
	);
	setText(
		modal,
		'[data-wm-bci-member-modal-founded]',
		member.foundedYear || ''
	);
	setText(
		modal,
		'[data-wm-bci-member-modal-main-office]',
		member.mainOffice || ''
	);
	setText( modal, '[data-wm-bci-member-modal-phone]', member.phone || '' );

	if ( emailLink ) {
		emailLink.hidden = ! member.contactEmail;
		if ( member.contactEmail ) {
			emailLink.href = `mailto:${ member.contactEmail }`;
			if ( emailText ) {
				emailText.textContent = member.contactEmail;
			} else {
				emailLink.textContent = member.contactEmail;
			}
		} else {
			emailLink.removeAttribute( 'href' );
			if ( emailText ) {
				emailText.textContent = '';
			} else {
				emailLink.textContent = '';
			}
		}
	}

	if ( websiteLink ) {
		websiteLink.hidden = ! member.websiteUrl;
		if ( member.websiteUrl ) {
			websiteLink.href = member.websiteUrl;
			const websiteLabel = member.websiteUrl.replace(
				/^https?:\/\/(www\.)?/u,
				''
			);
			if ( websiteText ) {
				websiteText.textContent = websiteLabel;
			} else {
				websiteLink.textContent = websiteLabel;
			}
		} else {
			websiteLink.removeAttribute( 'href' );
			if ( websiteText ) {
				websiteText.textContent = '';
			} else {
				websiteLink.textContent = '';
			}
		}
	}

	if ( socials ) {
		socials.replaceChildren();
		const iconBaseUrl = socials.dataset.wmBciMemberModalIconBase || '';
		( member.socialLinks || [] ).forEach( ( socialLink ) => {
			if ( ! socialLink?.url ) {
				return;
			}

			const anchor = ownerDocument.createElement( 'a' );
			const label =
				socialLink.label ||
				socialLink.platform ||
				__( 'Social Link', 'community-resources-hub' );
			anchor.className = 'wm-bci-member-modal__social-link';
			anchor.href = socialLink.url;
			anchor.target = '_blank';
			anchor.rel = 'noopener noreferrer';
			anchor.setAttribute(
				'aria-label',
				socialLink.platform && socialLink.label
					? `${ socialLink.platform } ${ socialLink.label }`
					: label
			);
			const iconFilename = memberSocialIconFilename(
				socialLink.platform
			);

			if ( iconFilename ) {
				const icon = ownerDocument.createElement( 'img' );

				icon.src = `${ iconBaseUrl }${ iconFilename }`;
				icon.alt = '';
				icon.setAttribute( 'aria-hidden', 'true' );
				anchor.appendChild( icon );
			} else {
				anchor.innerHTML = memberSocialIconMarkup(
					socialLink.platform
				);
			}
			socials.appendChild( anchor );
		} );
	}

	const spotlightHref =
		'string' === typeof member.spotlightHref
			? member.spotlightHref.trim()
			: '';

	if ( videoLink ) {
		videoLink.hidden = ! spotlightHref;
		if ( spotlightHref ) {
			videoLink.href = spotlightHref;
			videoLink.removeAttribute( 'target' );
			videoLink.removeAttribute( 'rel' );
		} else {
			videoLink.removeAttribute( 'href' );
		}
	}

	if ( videoAction ) {
		videoAction.hidden = ! spotlightHref;
		if ( spotlightHref ) {
			videoAction.href = spotlightHref;
			videoAction.removeAttribute( 'target' );
			videoAction.removeAttribute( 'rel' );
			const videoActionText = videoAction.querySelector( '.button-text' );
			if ( videoActionText ) {
				videoActionText.textContent = SPOTLIGHT_VIDEO_LABEL;
			}
		} else {
			videoAction.removeAttribute( 'href' );
		}
	}

	if ( websiteAction ) {
		websiteAction.hidden = ! member.websiteUrl;
		if ( member.websiteUrl ) {
			websiteAction.href = member.websiteUrl;
			const websiteActionText =
				websiteAction.querySelector( '.button-text' );
			if ( websiteActionText ) {
				websiteActionText.textContent = VISIT_WEBSITE_LABEL;
			}
		} else {
			websiteAction.removeAttribute( 'href' );
		}
	}

	if ( actions ) {
		actions.hidden = ! member.websiteUrl && ! spotlightHref;
	}

	if ( connect ) {
		connect.hidden = ! socials?.children.length && ! spotlightHref;
	}

	if ( programs ) {
		programs.innerHTML =
			'string' === typeof member.programsHtml
				? member.programsHtml.trim()
				: '';
	}

	let attachmentCount = 0;

	if ( attachments ) {
		attachments.replaceChildren();
		( member.attachments || [] ).forEach( ( attachment ) => {
			if ( ! attachment?.url ) {
				return;
			}

			const anchor = ownerDocument.createElement( 'a' );
			anchor.className = 'wm-bci-member-modal__attachment';
			anchor.href = attachment.url;
			anchor.target = '_blank';
			anchor.rel = 'noopener noreferrer';
			anchor.textContent =
				attachment.label ||
				attachment.url.replace( /^https?:\/\/(www\.)?/u, '' );
			attachments.appendChild( anchor );
			attachmentCount += 1;
		} );
	}

	toggleMemberRow( modal, 'overview', Boolean( overviewHtml ) );
	toggleMemberRow(
		modal,
		'community-served',
		Boolean( member.communityServed )
	);
	toggleMemberRow( modal, 'founded', Boolean( member.foundedYear ) );
	toggleMemberRow( modal, 'email', Boolean( member.contactEmail ) );
	toggleMemberRow( modal, 'website', Boolean( member.websiteUrl ) );
	toggleMemberRow( modal, 'main-office', Boolean( member.mainOffice ) );
	toggleMemberRow( modal, 'phone', Boolean( member.phone ) );
	toggleMemberRow( modal, 'attachments', Boolean( attachmentCount ) );
	toggleMemberRow(
		modal,
		'programs',
		Boolean(
			'string' === typeof member.programsHtml &&
				member.programsHtml.trim()
		)
	);
}

export function bindMemberModalTriggers(
	section,
	modal,
	payload,
	openModal = null
) {
	if ( ! section || ! modal ) {
		return;
	}

	section
		.querySelectorAll( '[data-wm-bci-member-open]' )
		.forEach( ( trigger ) => {
			if ( trigger.dataset.wmBciMemberModalBound ) {
				return;
			}

			trigger.addEventListener( 'click', ( event ) => {
				const member = findMember( payload, trigger.dataset.memberId );

				if ( ! member ) {
					event.preventDefault();
					return;
				}

				hydrateMemberModal( modal, member );

				if ( 'function' === typeof openModal ) {
					event.preventDefault();
					openModal( trigger, member );
				}
			} );

			trigger.dataset.wmBciMemberModalBound = 'true';
		} );
}
