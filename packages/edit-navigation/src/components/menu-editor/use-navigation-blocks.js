/**
 * WordPress dependencies
 */
import { createBlock } from '@wordpress/blocks';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState, useRef, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

function createBlockFromMenuItem( menuItem ) {
	return createBlock( 'core/navigation-link', {
		label: menuItem.title.raw,
		url: menuItem.url,
	} );
}

function createMenuItemAttributesFromBlock( block ) {
	return {
		title: block.attributes.label,
		url: block.attributes.url,
	};
}

export default function useNavigationBlocks( menuId ) {
	const menuItems = useSelect(
		( select ) => select( 'core' ).getMenuItems( { menus: menuId } ),
		[ menuId ]
	);
	const { receiveEntityRecords } = useDispatch( 'core' );

	const [ blocks, setBlocks ] = useState( [] );

	const menuItemsRef = useRef( {} );

	useEffect( () => {
		if ( ! menuItems ) {
			return;
		}

		menuItemsRef.current = {};

		const innerBlocks = [];

		for ( const menuItem of menuItems ) {
			const block = createBlockFromMenuItem( menuItem );
			menuItemsRef.current[ block.clientId ] = menuItem;
			innerBlocks.push( block );
		}

		setBlocks( [ createBlock( 'core/navigation', {}, innerBlocks ) ] );
	}, [ menuItems ] );

	const saveBlocks = async () => {
		const { clientId, innerBlocks } = blocks[ 0 ];
		const parentItemId = menuItemsRef.current[ clientId ]?.parent;

		const prepareRequestData = ( nestedBlocks, parentId = 0 ) =>
			nestedBlocks.flatMap( ( block ) => {
				const menuItem = menuItemsRef.current[ block.clientId ];
				const currentItemId = menuItem?.id || 0;

				return [
					{
						...( menuItem || {} ),
						...createMenuItemAttributesFromBlock( block ),
						menus: menuId,
						parent: parentId,
					},
					...prepareRequestData( block.innerBlocks, currentItemId ),
				];
			} );

		const requestData = prepareRequestData( innerBlocks, parentItemId );

		const saved = await apiFetch( {
			path: `/__experimental/menu-items/save-hierarchy?menus=${ menuId }`,
			method: 'PUT',
			data: { tree: requestData },
		} );

		const kind = 'root';
		const name = 'menuItem';
		receiveEntityRecords(
			kind,
			name,
			saved,
			// {
			// 	...item.data,
			// 	title: { rendered: 'experimental' },
			// },
			undefined,
			true
		);

		console.log( saved );
	};

	return [ blocks, setBlocks, saveBlocks ];
}
