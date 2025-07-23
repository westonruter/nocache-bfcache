/**
 * Adds a sparkle (✨) button after the "Remember Me" checkbox on the login screen to open a popover with information
 * about the bfcache opt-in.
 *
 * This is a JavaScript module, so the global namespace is not polluted.
 *
 * @since 1.1.0
 */

/**
 * Script module ID.
 *
 * @since 1.1.0
 * @type {string}
 */
const moduleId = '@nocache-bfcache/bfcache-opt-in';

/**
 * JSON script containing the PHP exports.
 *
 * @since 1.0.0
 * @type {HTMLScriptElement}
 */
const jsonScript = /** @type {HTMLScriptElement} */ (
	document.getElementById( `wp-script-module-data-${ moduleId }` )
);

/**
 * Exports from PHP.
 *
 * @since 1.1.0
 * @type {{
 *     buttonTemplateId: string,
 * }}
 */
const data = JSON.parse( jsonScript.text );

// Add a button that opens a popover with information about the instant navigation feature.
const p = document.querySelector(
	'p.forgetmenot:has(> input#rememberme ):has(> label:last-child[for="rememberme"] )'
);
if ( p ) {
	const tmpl = /** @type {HTMLTemplateElement} */ (
		document.getElementById( data.buttonTemplateId )
	);
	const button = /** @type {HTMLButtonElement} */ (
		tmpl.content.firstElementChild
	);
	p.append( button );
}
