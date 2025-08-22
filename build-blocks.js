/**
 * Simple build script for Primates Shoppers blocks
 * This concatenates the block files into a single index.js file that WordPress can load
 */

const fs = require('fs');
const path = require('path');

// Read the individual block files
const heroSection = fs.readFileSync(path.join(__dirname, 'blocks/hero-section/index.js'), 'utf8');
const valueProposition = fs.readFileSync(path.join(__dirname, 'blocks/value-proposition/index.js'), 'utf8');
const searchSection = fs.readFileSync(path.join(__dirname, 'blocks/search-section/index.js'), 'utf8');
const testimonials = fs.readFileSync(path.join(__dirname, 'blocks/testimonials/index.js'), 'utf8');

// Combine all blocks into a single file
const combinedBlocks = `
/**
 * Primates Shoppers Gutenberg Blocks
 * Combined blocks file for WordPress compatibility
 */

// Import WordPress dependencies
const { registerBlockType } = wp.blocks;
const { useBlockProps, RichText, InspectorControls, ColorPalette } = wp.blockEditor;
const { PanelBody, TextControl } = wp.components;
const { __ } = wp.i18n;

// Register block collection
wp.blocks.registerBlockCollection('primates-shoppers', {
    title: 'Primates Shoppers',
    icon: 'store'
});

${heroSection.replace(/import.*from.*;\n/g, '').replace(/registerBlockType/g, 'wp.blocks.registerBlockType')}

${valueProposition.replace(/import.*from.*;\n/g, '').replace(/registerBlockType/g, 'wp.blocks.registerBlockType')}

${searchSection.replace(/import.*from.*;\n/g, '').replace(/registerBlockType/g, 'wp.blocks.registerBlockType')}

${testimonials.replace(/import.*from.*;\n/g, '').replace(/registerBlockType/g, 'wp.blocks.registerBlockType')}
`;

// Write the combined file
fs.writeFileSync(path.join(__dirname, 'blocks/index.js'), combinedBlocks);

console.log('✅ Blocks built successfully!');
console.log('📁 Combined file saved to: blocks/index.js');
console.log('🎉 Ready for WordPress!'); 