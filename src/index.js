/**
 * WordPress dependencies.
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { registerPlugin } from '@wordpress/plugins';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { useState } from '@wordpress/element';
import { Notice, PanelBody, TextControl } from '@wordpress/components';
import { PluginDocumentSettingPanel, PluginPrePublishPanel } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';

const ticketIcon = 'dashicons-tickets-alt';

/**
 * The variation name used to identify GatherPress Tickets buttons.
 *
 * @type {string}
 */
const VARIATION_NAME = 'gatherpress/tickets';

/**
 * The post meta key for the ticket URL.
 *
 * @type {string}
 */
const META_KEY = 'gatherpress_tickets_url';

/**
 * Register the GatherPress Tickets variation of core/button.
 *
 * This variation inherits all core/button styling controls (color,
 * typography, border, spacing, width) and adds a custom className
 * to identify it. The `isActive` callback ensures the variation is
 * recognized when the block has the matching class.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
 */
registerBlockVariation( 'core/button', {
	name: VARIATION_NAME,
	title: __( 'GatherPress Tickets', 'gatherpress-tickets' ),
	description: __(
		'A ticket button for GatherPress events. The URL is stored in post meta with intelligent fallback to venue website.',
		'gatherpress-tickets'
	),
	category: 'widgets',
	icon: ticketIcon,
	attributes: {
		className: 'is-style-gatherpress-tickets',
		text: __( 'Get Tickets', 'gatherpress-tickets' ),
		url: '#gatherpress-tickets',
	},
	isActive: ( blockAttributes ) =>
		blockAttributes.className?.includes( 'is-style-gatherpress-tickets' ),
	scope: [ 'inserter', 'block', 'transform' ],
} );

/**
 * Returns true when the value is a valid absolute http/https URL, or empty.
 *
 * Empty is valid — it means "no ticket URL set yet".
 * Plain strings like "123" or scheme-less values are rejected.
 *
 * @param {string} value The URL string to test.
 * @return {boolean} Whether the value is acceptable.
 */
function isValidTicketUrl( value ) {
	if ( '' === value.trim() ) {
		return true;
	}
	try {
		const { protocol } = new URL( value );
		return protocol === 'https:' || protocol === 'http:';
	} catch {
		return false;
	}
}

/**
 * Shared URL field with draft state, blur-validation, and inline error notice.
 *
 * Renders a TextControl bound to the gatherpress_tickets_url post meta.
 * Keystrokes update local draft state only; the value is validated and
 * committed on blur. An invalid entry shows an inline Notice and reverts
 * to the last saved value.
 *
 * @param {Object} props
 * @param {string} props.postType Current post type.
 * @param {number} props.postId   Current post ID.
 * @return {JSX.Element} The field (and optional error notice).
 */
function TicketUrlField( { postType, postId } ) {
	const [ meta, setMeta ] = useEntityProp(
		'postType',
		postType,
		'meta',
		postId
	);

	const savedUrl = meta?.[ META_KEY ] || '';
	const [ draft, setDraft ] = useState( savedUrl );
	const [ error, setError ] = useState( '' );

	const handleChange = ( value ) => {
		setDraft( value );
		if ( error ) {
			setError( '' );
		}
	};

	const handleBlur = () => {
		const trimmed = draft.trim();
		if ( ! isValidTicketUrl( trimmed ) ) {
			setError(
				__( 'Please enter a valid URL (e.g. https://example.com).', 'gatherpress-tickets' )
			);
			setDraft( savedUrl );
			return;
		}
		setError( '' );
		if ( trimmed !== savedUrl ) {
			setMeta( { ...meta, [ META_KEY ]: trimmed } );
		}
	};

	return (
		<>
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			<TextControl
				label={ __( 'Ticket URL', 'gatherpress-tickets' ) }
				help={ __(
					'URL where attendees can buy tickets. Falls back to the venue website when left empty. The button link in the editor is a placeholder and will be replaced on the frontend.',
					'gatherpress-tickets'
				) }
				placeholder="https://…"
				value={ draft }
				onChange={ handleChange }
				onBlur={ handleBlur }
				type="url"
				hideLabelFromVision
				__nextHasNoMarginBottom
			/>
		</>
	);
}

/**
 * Higher-order component that adds a Ticket Settings panel to the
 * inspector sidebar when the GatherPress Tickets variation is selected.
 *
 * Renders the same TicketUrlField used in the document panel so both
 * surfaces share identical UX and validation behaviour.
 */
const withTicketInspectorControls = createHigherOrderComponent(
	( BlockEdit ) =>
		( props ) => {
			const postType = useSelect(
				( select ) =>
					props.context?.postType ||
					select( 'core/editor' )?.getCurrentPostType(),
				[ props.context?.postType ]
			);

			const postId = useSelect(
				( select ) =>
					props.context?.postId ||
					select( 'core/editor' )?.getCurrentPostId(),
				[ props.context?.postId ]
			);

			if (
				props.name !== 'core/button' ||
				! props.attributes.className?.includes( 'is-style-gatherpress-tickets' )
			) {
				return <BlockEdit { ...props } />;
			}

			return (
				<>
					<BlockEdit { ...props } />
					<InspectorControls>
						<PanelBody
							title={ __( 'Ticket Settings', 'gatherpress-tickets' ) }
							initialOpen={ true }
						>
							<TicketUrlField postType={ postType } postId={ postId } />
						</PanelBody>
					</InspectorControls>
				</>
			);
		},
	'withTicketInspectorControls'
);

addFilter(
	'editor.BlockEdit',
	'gatherpress-tickets/inspector',
	withTicketInspectorControls
);

/**
 * Document-settings panel for the ticket URL.
 *
 * Always visible in the event editor sidebar — whether the Tickets button
 * block is placed directly in post content or used inside a block template.
 *
 * @return {JSX.Element|null} The panel, or null when not editing a gatherpress_event.
 */
function GatherPressTicketsDocumentPanel() {
	const postType = useSelect(
		( select ) => select( 'core/editor' )?.getCurrentPostType(),
		[]
	);

	const postId = useSelect(
		( select ) => select( 'core/editor' )?.getCurrentPostId(),
		[]
	);

	const [ meta, setMeta ] = useEntityProp(
		'postType',
		postType,
		'meta',
		postId
	);

	if ( 'gatherpress_event' !== postType ) {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name="gatherpress-tickets"
			title={ __( 'Ticket URL', 'gatherpress-tickets' ) }
			icon={ ticketIcon }
			className="gatherpress-tickets-panel"
		>
			<TicketUrlField postType={ postType } postId={ postId } />
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'gatherpress-tickets-document-panel', {
	render: GatherPressTicketsDocumentPanel,
} );

/**
 * Pre-publish checklist panel.
 *
 * Surfaces the ticket URL field in the pre-publish sidebar so editors
 * are reminded to set a URL before publishing. Uses simple immediate-write
 * onChange (no blur validation) because the pre-publish flow is a final
 * review step, not a free-typing context.
 *
 * @return {JSX.Element} The panel component.
 */
function GatherPressTicketsPrePublishCheck() {
	const postType = useSelect(
		( select ) => select( 'core/editor' )?.getCurrentPostType(),
		[]
	);

	const postId = useSelect(
		( select ) => select( 'core/editor' )?.getCurrentPostId(),
		[]
	);

	const [ meta, setMeta ] = useEntityProp(
		'postType',
		postType,
		'meta',
		postId
	);

	const ticketUrl = meta?.[ META_KEY ] || '';

	const setTicketUrl = ( value ) => {
		setMeta( { ...meta, [ META_KEY ]: value } );
	};

	return (
		<PluginPrePublishPanel
			title={ __( 'GatherPress Tickets', 'gatherpress-tickets' ) }
			icon={ ticketIcon }
			initialOpen={ true }
		>
			<p style={ { margin: '0 0 12px' } }>
				{ __(
					'Set the URL where attendees can purchase tickets. If left empty, the button will fall back to the venue link.',
					'gatherpress-tickets'
				) }
			</p>
			<TextControl
				label={ __( 'Ticket URL', 'gatherpress-tickets' ) }
				value={ ticketUrl }
				onChange={ setTicketUrl }
				type="url"
				__nextHasNoMarginBottom
			/>
		</PluginPrePublishPanel>
	);
}

registerPlugin( 'gatherpress-tickets-prepublish', {
	render: GatherPressTicketsPrePublishCheck,
} );
