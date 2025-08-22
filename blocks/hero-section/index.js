/**
 * Hero Section Block - WordPress Compatible
 */

(function() {
    'use strict';

    // WordPress dependencies
    const { registerBlockType } = wp.blocks;
    const { useBlockProps, RichText, InspectorControls } = wp.blockEditor;
    const { PanelBody, TextControl } = wp.components;
    const { __ } = wp.i18n;

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
                        wp.element.createElement(TextControl, {
                            label: __('Background Color', 'primates-shoppers'),
                            value: backgroundColor,
                            onChange: (value) => setAttributes({ backgroundColor: value })
                        }),
                        wp.element.createElement(TextControl, {
                            label: __('Text Color', 'primates-shoppers'),
                            value: textColor,
                            onChange: (value) => setAttributes({ textColor: value })
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
                            tagName: 'h1',
                            className: 'ps-hero-title',
                            value: title,
                            onChange: (value) => setAttributes({ title: value }),
                            placeholder: __('Enter hero title...', 'primates-shoppers')
                        }),
                        wp.element.createElement(RichText, {
                            tagName: 'p',
                            className: 'ps-hero-subtitle',
                            value: subtitle,
                            onChange: (value) => setAttributes({ subtitle: value }),
                            placeholder: __('Enter hero subtitle...', 'primates-shoppers')
                        }),
                        wp.element.createElement(
                            'div',
                            { className: 'ps-hero-cta' },
                            wp.element.createElement(
                                'a',
                                { 
                                    href: ctaUrl, 
                                    className: 'ps-cta-button' 
                                },
                                wp.element.createElement(RichText, {
                                    tagName: 'span',
                                    value: ctaText,
                                    onChange: (value) => setAttributes({ ctaText: value }),
                                    placeholder: __('Enter CTA text...', 'primates-shoppers')
                                })
                            )
                        ),
                        wp.element.createElement(RichText, {
                            tagName: 'p',
                            className: 'ps-hero-subtext',
                            value: subtext,
                            onChange: (value) => setAttributes({ subtext: value }),
                            placeholder: __('Enter subtext...', 'primates-shoppers')
                        })
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
                    { className: 'ps-container' },
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
                            { 
                                href: ctaUrl, 
                                className: 'ps-cta-button' 
                            },
                            wp.element.createElement(RichText.Content, {
                                tagName: 'span',
                                value: ctaText
                            })
                        )
                    ),
                    wp.element.createElement(RichText.Content, {
                        tagName: 'p',
                        className: 'ps-hero-subtext',
                        value: subtext
                    })
                )
            );
        }
    });

})(); 