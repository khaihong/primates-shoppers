/**
 * Value Proposition Block - WordPress Compatible
 */

(function() {
    'use strict';

    // WordPress dependencies
    const { registerBlockType } = wp.blocks;
    const { useBlockProps, RichText, InspectorControls } = wp.blockEditor;
    const { PanelBody, TextControl } = wp.components;
    const { __ } = wp.i18n;

    registerBlockType('primates-shoppers/value-proposition', {
        edit: ({ attributes, setAttributes }) => {
            const { 
                sectionTitle, 
                card1Icon, card1Title, card1Description,
                card2Icon, card2Title, card2Description,
                card3Icon, card3Title, card3Description,
                backgroundColor 
            } = attributes;
            
            const blockProps = useBlockProps({
                className: 'ps-value-section',
                style: {
                    backgroundColor: backgroundColor,
                    padding: '80px 20px'
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
                        { title: __('Card Icons', 'primates-shoppers'), initialOpen: true },
                        wp.element.createElement(TextControl, {
                            label: __('Card 1 Icon', 'primates-shoppers'),
                            value: card1Icon,
                            onChange: (value) => setAttributes({ card1Icon: value })
                        }),
                        wp.element.createElement(TextControl, {
                            label: __('Card 2 Icon', 'primates-shoppers'),
                            value: card2Icon,
                            onChange: (value) => setAttributes({ card2Icon: value })
                        }),
                        wp.element.createElement(TextControl, {
                            label: __('Card 3 Icon', 'primates-shoppers'),
                            value: card3Icon,
                            onChange: (value) => setAttributes({ card3Icon: value })
                        })
                    ),
                    wp.element.createElement(
                        PanelBody,
                        { title: __('Background Color', 'primates-shoppers'), initialOpen: false },
                        wp.element.createElement(TextControl, {
                            label: __('Background Color', 'primates-shoppers'),
                            value: backgroundColor,
                            onChange: (value) => setAttributes({ backgroundColor: value })
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
                        wp.element.createElement(
                            'div',
                            { className: 'ps-value-cards' },
                            wp.element.createElement(
                                'div',
                                { className: 'ps-value-card' },
                                wp.element.createElement(
                                    'div',
                                    { className: 'ps-card-icon' },
                                    card1Icon
                                ),
                                wp.element.createElement(RichText, {
                                    tagName: 'h3',
                                    className: 'ps-card-title',
                                    value: card1Title,
                                    onChange: (value) => setAttributes({ card1Title: value }),
                                    placeholder: __('Card 1 title...', 'primates-shoppers')
                                }),
                                wp.element.createElement(RichText, {
                                    tagName: 'p',
                                    className: 'ps-card-description',
                                    value: card1Description,
                                    onChange: (value) => setAttributes({ card1Description: value }),
                                    placeholder: __('Card 1 description...', 'primates-shoppers')
                                })
                            ),
                            wp.element.createElement(
                                'div',
                                { className: 'ps-value-card' },
                                wp.element.createElement(
                                    'div',
                                    { className: 'ps-card-icon' },
                                    card2Icon
                                ),
                                wp.element.createElement(RichText, {
                                    tagName: 'h3',
                                    className: 'ps-card-title',
                                    value: card2Title,
                                    onChange: (value) => setAttributes({ card2Title: value }),
                                    placeholder: __('Card 2 title...', 'primates-shoppers')
                                }),
                                wp.element.createElement(RichText, {
                                    tagName: 'p',
                                    className: 'ps-card-description',
                                    value: card2Description,
                                    onChange: (value) => setAttributes({ card2Description: value }),
                                    placeholder: __('Card 2 description...', 'primates-shoppers')
                                })
                            ),
                            wp.element.createElement(
                                'div',
                                { className: 'ps-value-card' },
                                wp.element.createElement(
                                    'div',
                                    { className: 'ps-card-icon' },
                                    card3Icon
                                ),
                                wp.element.createElement(RichText, {
                                    tagName: 'h3',
                                    className: 'ps-card-title',
                                    value: card3Title,
                                    onChange: (value) => setAttributes({ card3Title: value }),
                                    placeholder: __('Card 3 title...', 'primates-shoppers')
                                }),
                                wp.element.createElement(RichText, {
                                    tagName: 'p',
                                    className: 'ps-card-description',
                                    value: card3Description,
                                    onChange: (value) => setAttributes({ card3Description: value }),
                                    placeholder: __('Card 3 description...', 'primates-shoppers')
                                })
                            )
                        )
                    )
                )
            );
        },

        save: ({ attributes }) => {
            const { 
                sectionTitle, 
                card1Icon, card1Title, card1Description,
                card2Icon, card2Title, card2Description,
                card3Icon, card3Title, card3Description,
                backgroundColor 
            } = attributes;
            
            const blockProps = useBlockProps.save({
                className: 'ps-value-section',
                style: {
                    backgroundColor: backgroundColor
                }
            });

            return wp.element.createElement(
                'div',
                blockProps,
                wp.element.createElement(
                    'div',
                    { className: 'ps-container' },
                    wp.element.createElement(RichText.Content, {
                        tagName: 'h2',
                        className: 'ps-section-title',
                        value: sectionTitle
                    }),
                    wp.element.createElement(
                        'div',
                        { className: 'ps-value-cards' },
                        wp.element.createElement(
                            'div',
                            { className: 'ps-value-card' },
                            wp.element.createElement(
                                'div',
                                { className: 'ps-card-icon' },
                                card1Icon
                            ),
                            wp.element.createElement(RichText.Content, {
                                tagName: 'h3',
                                className: 'ps-card-title',
                                value: card1Title
                            }),
                            wp.element.createElement(RichText.Content, {
                                tagName: 'p',
                                className: 'ps-card-description',
                                value: card1Description
                            })
                        ),
                        wp.element.createElement(
                            'div',
                            { className: 'ps-value-card' },
                            wp.element.createElement(
                                'div',
                                { className: 'ps-card-icon' },
                                card2Icon
                            ),
                            wp.element.createElement(RichText.Content, {
                                tagName: 'h3',
                                className: 'ps-card-title',
                                value: card2Title
                            }),
                            wp.element.createElement(RichText.Content, {
                                tagName: 'p',
                                className: 'ps-card-description',
                                value: card2Description
                            })
                        ),
                        wp.element.createElement(
                            'div',
                            { className: 'ps-value-card' },
                            wp.element.createElement(
                                'div',
                                { className: 'ps-card-icon' },
                                card3Icon
                            ),
                            wp.element.createElement(RichText.Content, {
                                tagName: 'h3',
                                className: 'ps-card-title',
                                value: card3Title
                            }),
                            wp.element.createElement(RichText.Content, {
                                tagName: 'p',
                                className: 'ps-card-description',
                                value: card3Description
                            })
                        )
                    )
                )
            );
        }
    });

})(); 