/**
 * Search Section Block - WordPress Compatible
 */

(function() {
    'use strict';

    // WordPress dependencies
    const { registerBlockType } = wp.blocks;
    const { useBlockProps, RichText, InspectorControls } = wp.blockEditor;
    const { PanelBody, TextControl } = wp.components;
    const { __ } = wp.i18n;

    registerBlockType('primates-shoppers/search-section', {
        edit: ({ attributes, setAttributes }) => {
            const { 
                sectionTitle, 
                sectionSubtitle,
                anchorId,
                feature1Icon, feature1Text,
                feature2Icon, feature2Text,
                feature3Icon, feature3Text,
                feature4Icon, feature4Text,
                backgroundColor 
            } = attributes;
            
            const blockProps = useBlockProps({
                className: 'ps-search-section',
                style: {
                    backgroundColor: backgroundColor,
                    padding: '80px 20px'
                },
                id: anchorId
            });

            return wp.element.createElement(
                wp.element.Fragment,
                null,
                wp.element.createElement(
                    InspectorControls,
                    null,
                    wp.element.createElement(
                        PanelBody,
                        { title: __('Section Settings', 'primates-shoppers'), initialOpen: true },
                        wp.element.createElement(TextControl, {
                            label: __('Anchor ID', 'primates-shoppers'),
                            value: anchorId,
                            onChange: (value) => setAttributes({ anchorId: value })
                        }),
                        wp.element.createElement(TextControl, {
                            label: __('Background Color', 'primates-shoppers'),
                            value: backgroundColor,
                            onChange: (value) => setAttributes({ backgroundColor: value })
                        })
                    ),
                    wp.element.createElement(
                        PanelBody,
                        { title: __('Feature Icons', 'primates-shoppers'), initialOpen: false },
                        wp.element.createElement(TextControl, {
                            label: __('Feature 2 Icon', 'primates-shoppers'),
                            value: feature2Icon,
                            onChange: (value) => setAttributes({ feature2Icon: value })
                        }),
                        wp.element.createElement(TextControl, {
                            label: __('Feature 3 Icon', 'primates-shoppers'),
                            value: feature3Icon,
                            onChange: (value) => setAttributes({ feature3Icon: value })
                        })
                    )
                ),
                wp.element.createElement(
                    'div',
                    blockProps,
                    wp.element.createElement(
                        'div',
                        { className: 'ps-container' },
                        wp.element.createElement(RichText, {
                            tagName: 'h2',
                            className: 'ps-section-title',
                            value: sectionTitle,
                            onChange: (value) => setAttributes({ sectionTitle: value }),
                            placeholder: __('Enter section title...', 'primates-shoppers')
                        }),
                        wp.element.createElement(RichText, {
                            tagName: 'p',
                            className: 'ps-section-subtitle',
                            value: sectionSubtitle,
                            onChange: (value) => setAttributes({ sectionSubtitle: value }),
                            placeholder: __('Enter section subtitle...', 'primates-shoppers')
                        }),
                        wp.element.createElement(
                            'div',
                            { className: 'ps-search-features' },
                            wp.element.createElement(
                                'div',
                                { className: 'ps-feature' },
                                wp.element.createElement('span', { className: 'ps-feature-icon' }, feature2Icon),
                                wp.element.createElement(RichText, {
                                    tagName: 'span',
                                    value: feature2Text,
                                    onChange: (value) => setAttributes({ feature2Text: value }),
                                    placeholder: __('Feature 2...', 'primates-shoppers')
                                })
                            ),
                            wp.element.createElement(
                                'div',
                                { className: 'ps-feature' },
                                wp.element.createElement('span', { className: 'ps-feature-icon' }, feature3Icon),
                                wp.element.createElement(RichText, {
                                    tagName: 'span',
                                    value: feature3Text,
                                    onChange: (value) => setAttributes({ feature3Text: value }),
                                    placeholder: __('Feature 3...', 'primates-shoppers')
                                })
                            )
                        ),
                        wp.element.createElement(
                            'div',
                            { className: 'ps-search-form-container' },
                            wp.element.createElement(
                                'div',
                                { 
                                    style: { 
                                        padding: '2rem', 
                                        background: '#f0f0f0', 
                                        borderRadius: '8px', 
                                        textAlign: 'center' 
                                    }
                                },
                                __('[primates_shoppers] shortcode will be rendered here', 'primates-shoppers')
                            )
                        )
                    )
                )
            );
        },

        save: () => {
            // This block uses server-side rendering, so save returns null
            return null;
        }
    });

})(); 