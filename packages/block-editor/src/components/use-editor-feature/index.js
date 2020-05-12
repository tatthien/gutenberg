/**
 * External dependencies
 */
import { get, has } from 'lodash';

/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { useBlockEditContext } from '../block-edit';

/**
 * Hook that retrieves the setting for the given editor feature.
 * It works with nested objects using by finding the value at path.
 *
 * @param {string} featurePath The path to the feature.
 *
 * @return {any} Returns the value defined for the setting.
 *
 * @example
 * ```js
 * const isEnabled = useEditorFeature( 'typography.dropCapEnabled' );
 * ```
 */
export default function useEditorFeature( featurePath ) {
	const { name: blockName } = useBlockEditContext();

	const setting = useSelect(
		( select ) => {
			const { getSettings } = select( 'core/block-editor' );

			const path = featurePath.split( '.' );
			if (
				has( getSettings(), [
					'__experimentalBlocksConfig',
					blockName,
					'features',
					...path,
				] )
			) {
				return get( getSettings(), [
					'__experimentalBlocksConfig',
					blockName,
					'features',
					...path,
				] );
			}

			return get( getSettings(), [
				'__experimentalFeaturesConfig',
				...path,
			] );
		},
		[ blockName, featurePath ]
	);

	return setting;
}
