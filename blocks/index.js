/**
 * WordPress dependencies
 */
import { registerBlockCollection } from '@wordpress/blocks';

/**
 * Block imports
 */
import './hero-section';
import './value-proposition';
import './search-section';
import './testimonials';

/**
 * Register block collection
 */
registerBlockCollection('primates-shoppers', {
    title: 'Primates Shoppers',
    icon: 'store'
}); 