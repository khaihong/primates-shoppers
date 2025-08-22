/**
 * Testimonials Block - WordPress Compatible
 */

(function() {
    'use strict';

    // WordPress dependencies
    const { registerBlockType } = wp.blocks;
    const { useBlockProps, RichText, InspectorControls } = wp.blockEditor;
    const { PanelBody, TextControl } = wp.components;
    const { __ } = wp.i18n;

    registerBlockType('primates-shoppers/testimonials', {
        edit: ({ attributes, setAttributes }) => {
            const { 
                sectionTitle,
                testimonial1Content, testimonial1Author, testimonial1Role,
                testimonial2Content, testimonial2Author, testimonial2Role,
                testimonial3Content, testimonial3Author, testimonial3Role,
                backgroundColor 
            } = attributes;
            
            const blockProps = useBlockProps({
                className: 'ps-testimonials',
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
                        { title: __('Background Color', 'primates-shoppers'), initialOpen: true },
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
                            { className: 'ps-testimonials-grid' },
                            wp.element.createElement(
                                'div',
                                { className: 'ps-testimonial' },
                                wp.element.createElement(RichText, {
                                    tagName: 'blockquote',
                                    className: 'ps-testimonial-content',
                                    value: testimonial1Content,
                                    onChange: (value) => setAttributes({ testimonial1Content: value }),
                                    placeholder: __('Enter testimonial...', 'primates-shoppers')
                                }),
                                wp.element.createElement(
                                    'div',
                                    { className: 'ps-testimonial-author' },
                                    wp.element.createElement(RichText, {
                                        tagName: 'cite',
                                        className: 'ps-author-name',
                                        value: testimonial1Author,
                                        onChange: (value) => setAttributes({ testimonial1Author: value }),
                                        placeholder: __('Author name...', 'primates-shoppers')
                                    }),
                                    wp.element.createElement(RichText, {
                                        tagName: 'span',
                                        className: 'ps-author-role',
                                        value: testimonial1Role,
                                        onChange: (value) => setAttributes({ testimonial1Role: value }),
                                        placeholder: __('Author role...', 'primates-shoppers')
                                    })
                                )
                            ),
                            wp.element.createElement(
                                'div',
                                { className: 'ps-testimonial' },
                                wp.element.createElement(RichText, {
                                    tagName: 'blockquote',
                                    className: 'ps-testimonial-content',
                                    value: testimonial2Content,
                                    onChange: (value) => setAttributes({ testimonial2Content: value }),
                                    placeholder: __('Enter testimonial...', 'primates-shoppers')
                                }),
                                wp.element.createElement(
                                    'div',
                                    { className: 'ps-testimonial-author' },
                                    wp.element.createElement(RichText, {
                                        tagName: 'cite',
                                        className: 'ps-author-name',
                                        value: testimonial2Author,
                                        onChange: (value) => setAttributes({ testimonial2Author: value }),
                                        placeholder: __('Author name...', 'primates-shoppers')
                                    }),
                                    wp.element.createElement(RichText, {
                                        tagName: 'span',
                                        className: 'ps-author-role',
                                        value: testimonial2Role,
                                        onChange: (value) => setAttributes({ testimonial2Role: value }),
                                        placeholder: __('Author role...', 'primates-shoppers')
                                    })
                                )
                            ),
                            wp.element.createElement(
                                'div',
                                { className: 'ps-testimonial' },
                                wp.element.createElement(RichText, {
                                    tagName: 'blockquote',
                                    className: 'ps-testimonial-content',
                                    value: testimonial3Content,
                                    onChange: (value) => setAttributes({ testimonial3Content: value }),
                                    placeholder: __('Enter testimonial...', 'primates-shoppers')
                                }),
                                wp.element.createElement(
                                    'div',
                                    { className: 'ps-testimonial-author' },
                                    wp.element.createElement(RichText, {
                                        tagName: 'cite',
                                        className: 'ps-author-name',
                                        value: testimonial3Author,
                                        onChange: (value) => setAttributes({ testimonial3Author: value }),
                                        placeholder: __('Author name...', 'primates-shoppers')
                                    }),
                                    wp.element.createElement(RichText, {
                                        tagName: 'span',
                                        className: 'ps-author-role',
                                        value: testimonial3Role,
                                        onChange: (value) => setAttributes({ testimonial3Role: value }),
                                        placeholder: __('Author role...', 'primates-shoppers')
                                    })
                                )
                            )
                        )
                    )
                )
            );
        },

        save: ({ attributes }) => {
            const { 
                sectionTitle,
                testimonial1Content, testimonial1Author, testimonial1Role,
                testimonial2Content, testimonial2Author, testimonial2Role,
                testimonial3Content, testimonial3Author, testimonial3Role,
                backgroundColor 
            } = attributes;
            
            const blockProps = useBlockProps.save({
                className: 'ps-testimonials',
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
                        { className: 'ps-testimonials-grid' },
                        wp.element.createElement(
                            'div',
                            { className: 'ps-testimonial' },
                            wp.element.createElement(RichText.Content, {
                                tagName: 'blockquote',
                                className: 'ps-testimonial-content',
                                value: testimonial1Content
                            }),
                            wp.element.createElement(
                                'div',
                                { className: 'ps-testimonial-author' },
                                wp.element.createElement(RichText.Content, {
                                    tagName: 'cite',
                                    className: 'ps-author-name',
                                    value: testimonial1Author
                                }),
                                wp.element.createElement(RichText.Content, {
                                    tagName: 'span',
                                    className: 'ps-author-role',
                                    value: testimonial1Role
                                })
                            )
                        ),
                        wp.element.createElement(
                            'div',
                            { className: 'ps-testimonial' },
                            wp.element.createElement(RichText.Content, {
                                tagName: 'blockquote',
                                className: 'ps-testimonial-content',
                                value: testimonial2Content
                            }),
                            wp.element.createElement(
                                'div',
                                { className: 'ps-testimonial-author' },
                                wp.element.createElement(RichText.Content, {
                                    tagName: 'cite',
                                    className: 'ps-author-name',
                                    value: testimonial2Author
                                }),
                                wp.element.createElement(RichText.Content, {
                                    tagName: 'span',
                                    className: 'ps-author-role',
                                    value: testimonial2Role
                                })
                            )
                        ),
                        wp.element.createElement(
                            'div',
                            { className: 'ps-testimonial' },
                            wp.element.createElement(RichText.Content, {
                                tagName: 'blockquote',
                                className: 'ps-testimonial-content',
                                value: testimonial3Content
                            }),
                            wp.element.createElement(
                                'div',
                                { className: 'ps-testimonial-author' },
                                wp.element.createElement(RichText.Content, {
                                    tagName: 'cite',
                                    className: 'ps-author-name',
                                    value: testimonial3Author
                                }),
                                wp.element.createElement(RichText.Content, {
                                    tagName: 'span',
                                    className: 'ps-author-role',
                                    value: testimonial3Role
                                })
                            )
                        )
                    )
                )
            );
        }
    });

})(); 