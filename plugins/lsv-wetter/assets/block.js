/**
 * Block "Wetter am Platz" für den Block-Editor (ohne Build-Schritt)
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var ToggleControl = wp.components.ToggleControl;
	var TextControl = wp.components.TextControl;
	var ServerSideRender = wp.serverSideRender;

	var icon = el(
		'svg',
		{ viewBox: '0 0 24 24', xmlns: 'http://www.w3.org/2000/svg' },
		el( 'path', { d: 'M3 8h10a3 3 0 1 0-3-3M3 12h15a3 3 0 1 1-3 3M3 16h7', fill: 'none', stroke: 'currentColor', strokeWidth: 2, strokeLinecap: 'round' } )
	);

	wp.blocks.registerBlockType( 'lsv/wetter', {
		apiVersion: 2,
		title: 'Wetter am Platz',
		description: 'Aktuelle Wetterdaten vom Flugplatz: Wind, Piste, Temperatur, QNH, Wolkenbasis.',
		category: 'widgets',
		icon: icon,
		keywords: [ 'wetter', 'wind', 'qnh', 'flugplatz', 'metar' ],
		supports: { align: [ 'wide', 'full' ], html: false },
		attributes: {
			kompakt: { type: 'boolean', default: false },
			titel: { type: 'string', default: 'Wetter am Platz' },
			link: { type: 'boolean', default: true },
			align: { type: 'string', default: 'wide' }
		},

		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;

			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Einstellungen', initialOpen: true },
						el( TextControl, {
							label: 'Überschrift',
							help: 'Leer lassen für keine Überschrift.',
							value: a.titel,
							onChange: function ( v ) { set( { titel: v } ); }
						} ),
						el( ToggleControl, {
							label: 'Kompakte Ansicht',
							help: a.kompakt ? 'Nur Wind, Temperatur, QNH und Wolkenbasis.' : 'Alle Werte inkl. QFE, Dichtehöhe und Feuchte.',
							checked: a.kompakt,
							onChange: function ( v ) { set( { kompakt: v } ); }
						} ),
						el( ToggleControl, {
							label: 'Link zur Wetterseite anzeigen',
							checked: a.link,
							onChange: function ( v ) { set( { link: v } ); }
						} )
					)
				),
				el( ServerSideRender, {
					block: 'lsv/wetter',
					attributes: { kompakt: a.kompakt, titel: a.titel, link: a.link }
				} )
			);
		},

		save: function () {
			return null; // wird vom Server gerendert
		}
	} );
}( window.wp ) );
