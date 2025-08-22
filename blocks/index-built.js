/**
 * Primates Shoppers Gutenberg Blocks
 * WordPress-compatible version
 */

(function() {
    'use strict';

    // WordPress dependencies
    const { registerBlockType, registerBlockCollection } = wp.blocks;
    const { useBlockProps, RichText, InspectorControls, ColorPalette } = wp.blockEditor;
    const { PanelBody, TextControl } = wp.components;
    const { __ } = wp.i18n;

    // Register block collection
    registerBlockCollection('primates-shoppers', {
        title: 'Primates Shoppers',
        icon: 'store'
    });
    
    // Log successful loading
    console.log('✅ Primates Shoppers blocks loaded successfully!');

    // Hero Section Block
    registerBlockType('primates-shoppers/hero-section', {
        edit: ({ attributes, setAttributes }) => {
            const { title, subtitle, ctaText, ctaUrl, subtext, backgroundColor, textColor } = attributes;
            const blockProps = useBlockProps({
                className: 'ps-hero-section',
                style: {
                    background: backgroundColor,
                    color: textColor,
                    padding: '80px 20px',
                    textAlign: 'center',
                    position: 'relative',
                    overflow: 'hidden'
                }
            });

            return wp.element.createElement(
                wp.element.Fragment,
                null,
                wp.element.createElement(
                    InspectorControls,
                    null,
                    wp.element.createElement(
                        PanelBody,
                        { title: __('Hero Settings', 'primates-shoppers'), initialOpen: true },
                        wp.element.createElement(TextControl, {
                            label: __('CTA URL', 'primates-shoppers'),
                            value: ctaUrl,
                            onChange: (value) => setAttributes({ ctaUrl: value })
                        })
                    ),
                    wp.element.createElement(
                        PanelBody,
                        { title: __('Colors', 'primates-shoppers'), initialOpen: false },
                        wp.element.createElement('p', null, wp.element.createElement('strong', null, __('Background Color', 'primates-shoppers'))),
                        wp.element.createElement(ColorPalette, {
                            value: backgroundColor,
                            onChange: (color) => setAttributes({ backgroundColor: color })
                        }),
                        wp.element.createElement('p', null, wp.element.createElement('strong', null, __('Text Color', 'primates-shoppers'))),
                        wp.element.createElement(ColorPalette, {
                            value: textColor,
                            onChange: (color) => setAttributes({ textColor: color })
                        })
                    )
                ),
                wp.element.createElement(
                    'div',
                    blockProps,
                    wp.element.createElement(
                        'div',
                        { className: 'ps-hero-content' },
                        wp.element.createElement(RichText, {
                            tagName: 'h1',
                            className: 'ps-hero-title',
                            value: title,
                            onChange: (value) => setAttributes({ title: value }),
                            placeholder: __('Enter hero title...', 'primates-shoppers'),
                            style: {
                                fontSize: '3.5rem',
                                fontWeight: '700',
                                marginBottom: '1rem',
                                lineHeight: '1.2'
                            }
                        }),
                        wp.element.createElement(RichText, {
                            tagName: 'p',
                            className: 'ps-hero-subtitle',
                            value: subtitle,
                            onChange: (value) => setAttributes({ subtitle: value }),
                            placeholder: __('Enter hero subtitle...', 'primates-shoppers'),
                            style: {
                                fontSize: '1.3rem',
                                marginBottom: '2rem',
                                opacity: '0.95',
                                maxWidth: '800px',
                                marginLeft: 'auto',
                                marginRight: 'auto'
                            }
                        }),
                        wp.element.createElement(
                            'div',
                            { className: 'ps-hero-cta' },
                            wp.element.createElement(RichText, {
                                tagName: 'span',
                                className: 'ps-cta-button ps-cta-primary',
                                value: ctaText,
                                onChange: (value) => setAttributes({ ctaText: value }),
                                placeholder: __('Enter CTA text...', 'primates-shoppers'),
                                style: {
                                    display: 'inline-block',
                                    background: '#ff6b6b',
                                    color: 'white',
                                    padding: '15px 30px',
                                    borderRadius: '8px',
                                    textDecoration: 'none',
                                    fontSize: '1.2rem',
                                    fontWeight: '600',
                                    transition: 'all 0.3s ease',
                                    cursor: 'pointer'
                                }
                            }),
                            wp.element.createElement(RichText, {
                                tagName: 'p',
                                className: 'ps-hero-subtext',
                                value: subtext,
                                onChange: (value) => setAttributes({ subtext: value }),
                                placeholder: __('Enter subtext...', 'primates-shoppers'),
                                style: {
                                    marginTop: '1rem',
                                    fontSize: '0.9rem',
                                    opacity: '0.9'
                                }
                            })
                        )
                    )
                )
            );
        },
        
        save: ({ attributes }) => {
            const { title, subtitle, ctaText, ctaUrl, subtext, backgroundColor, textColor } = attributes;
            const blockProps = useBlockProps.save({
                className: 'ps-hero-section',
                style: {
                    background: backgroundColor,
                    color: textColor
                }
            });

            return wp.element.createElement(
                'div',
                blockProps,
                wp.element.createElement(
                    'div',
                    { className: 'ps-hero-content' },
                    wp.element.createElement(RichText.Content, {
                        tagName: 'h1',
                        className: 'ps-hero-title',
                        value: title
                    }),
                    wp.element.createElement(RichText.Content, {
                        tagName: 'p',
                        className: 'ps-hero-subtitle',
                        value: subtitle
                    }),
                    wp.element.createElement(
                        'div',
                        { className: 'ps-hero-cta' },
                        wp.element.createElement(
                            'a',
                            { href: ctaUrl, className: 'ps-cta-button ps-cta-primary', id: 'ps-hero-cta' },
                            wp.element.createElement(RichText.Content, { value: ctaText })
                        ),
                        wp.element.createElement(RichText.Content, {
                            tagName: 'p',
                            className: 'ps-hero-subtext',
                            value: subtext
                        })
                    )
                )
            );
        }
    });

    console.log('✅ Primates Shoppers blocks loaded successfully!');

})(); 