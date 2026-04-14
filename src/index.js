/**
 * WordPress dependencies.
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { registerPlugin } from '@wordpress/plugins';
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { PluginPrePublishPanel } from '@wordpress/editor';
import { __ } from '@wordpress/i18n';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { ticket as ticketIcon } from '@wordpress/icons';

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
	title: __( 'GatherPress Tickets', 'telex-gatherpress-tickets' ),
	description: __(
		'A ticket button for GatherPress events. The URL is stored in post meta with intelligent fallback to venue website.',
		'telex-gatherpress-tickets'
	),
	category: 'widgets',
	icon: ticketIcon,
	attributes: {
		className: 'is-style-gatherpress-tickets',
		text: __( 'Get Tickets', 'telex-gatherpress-tickets' ),
		url: '#gatherpress-tickets',
	},
	isActive: ( blockAttributes ) =>
		blockAttributes.className?.includes( 'is-style-gatherpress-tickets' ),
	scope: [ 'inserter', 'block', 'transform' ],
} );

/**
 * Higher-order component that adds a Ticket Settings panel to the
 * inspector sidebar when a GatherPress Tickets variation is selected.
 *
 * Reads and writes the ticket URL from/to post meta using useEntityProp.
 */
const withTicketInspectorControls = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		if (
			props.name !== 'core/button' ||
			! props.attributes.className?.includes(
				'is-style-gatherpress-tickets'
			)
		) {
			return <BlockEdit { ...props } />;
		}

		return <GatherPressTicketsEdit { ...props } BlockEdit={ BlockEdit } />;
	},
	'withTicketInspectorControls'
);

/**
 * Inner component for the GatherPress Tickets variation editor.
 *
 * Separated to allow hooks (useSelect, useEntityProp) to be called
 * unconditionally at the top level of a component.
 *
 * @param {Object}   props           Component props.
 * @param {Function} props.BlockEdit The original BlockEdit component.
 *
 * @return {JSX.Element} The enhanced edit interface.
 */
function GatherPressTicketsEdit( { BlockEdit, ...props } ) {
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
		<>
			<BlockEdit { ...props } />
			<InspectorControls>
				<PanelBody
					title={ __(
						'Ticket Settings',
						'telex-gatherpress-tickets'
					) }
					initialOpen={ true }
				>
					<TextControl
						label={ __(
							'Ticket URL',
							'telex-gatherpress-tickets'
						) }
						help={ __(
							'Enter the URL where attendees can purchase tickets. Falls back to venue website if empty. The button link above is a placeholder and will be replaced on the frontend.',
							'telex-gatherpress-tickets'
						) }
						value={ ticketUrl }
						onChange={ setTicketUrl }
						type="url"
						__nextHasNoMarginBottom
					/>
				</PanelBody>
			</InspectorControls>
		</>
	);
}

addFilter(
	'editor.BlockEdit',
	'telex-gatherpress-tickets/inspector',
	withTicketInspectorControls
);

/**
 * Pre-publish checklist panel component.
 *
 * Displays a warning in the pre-publish sidebar when no ticket URL
 * has been set in the gatherpress_tickets_url post meta field,
 * and provides an inline text field so editors can add the URL
 * directly from the pre-publish checklist.
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
			title={ __( 'GatherPress Tickets', 'telex-gatherpress-tickets' ) }
			icon={ ticketIcon }
			initialOpen={ true }
		>
			<p style={ { margin: '0 0 12px' } }>
				{ __(
					'Set the URL where attendees can purchase tickets. If left empty, the button will fall back to the venue link.',
					'telex-gatherpress-tickets'
				) }
			</p>
			<TextControl
				label={ __( 'Ticket URL', 'telex-gatherpress-tickets' ) }
				value={ ticketUrl }
				onChange={ setTicketUrl }
				type="url"
				__nextHasNoMarginBottom
			/>
		</PluginPrePublishPanel>
	);
}

registerPlugin( 'telex-gatherpress-tickets-prepublish', {
	render: GatherPressTicketsPrePublishCheck,
} );
