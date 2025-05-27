+ update cache time after each filter?
+ show 'no cached results'?
+ get only product html
+ 3s search cooldown
+ filters: rating, delivery

the rating_count currently shows "", it is the next visible text after the title span, so in this example it is 67,934, update the code to get the count accordingly

<h2 aria-label="Sponsored Ad – Mens 2-Pack Loose-Fit Performance ShortsShort" class="a-size-base-plus a-spacing-none a-color-base a-text-normal"><span>Mens 2-Pack Loose-Fit Performance ShortsShort</span></h2></a> </div><div data-cy="reviews-block" class="a-section a-spacing-none a-spacing-top-micro"><div class="a-row a-size-small"><span class="a-declarative" data-version-id="v1731uh453810s2dcx8qxjfig2c" data-render-id="r1clbz4cxnw8q81yleajn1ar1h2" data-action="a-popover" data-csa-c-func-deps="aui-da-a-popover" data-a-popover="{&quot;position&quot;:&quot;triggerBottom&quot;,&quot;popoverLabel&quot;:&quot;4.5 out of 5 stars, rating details&quot;,&quot;url&quot;:&quot;/review/widgets/average-customer-review/popover/ref=acr_search__popover?ie=UTF8&amp;asin=B08JLGKCB6&amp;ref_=acr_search__popover&amp;contextId=search&quot;,&quot;closeButton&quot;:true,&quot;closeButtonLabel&quot;:&quot;&quot;}" data-csa-c-type="widget"><a aria-label="4.5 out of 5 stars, rating details" href="javascript:void(0)" role="button" class="a-popover-trigger a-declarative"><i data-cy="reviews-ratings-slot" aria-hidden="true" class="a-icon a-icon-star-small a-star-small-4-5"><span class="a-icon-alt">4.5 out of 5 stars</span></i><i class="a-icon a-icon-popover"></i></a></span> <a aria-label="67,934 ratings" class="a-link-normal s-underline-text s-underline-link-text s-link-style" href="/sspa/click?ie=UTF8&amp;spc=MTo1NDMwNTgwNjk1NzA1ODE0OjE3NDc3OTE4NTQ6c3BfYXRmOjIwMDAzMDcwNjE5MTI1MTo6MDo6&amp;url=%2FAmazon-Essentials-Loose-Fit-Performance-Burgundy%2Fdp%2FB08JLGKCB6%2Fref%3Dsr_1_1_ffob_sspa%3Fdib%3DeyJ2IjoiMSJ9.zcEAY9UmOl4Hh4EKr-LlJ0htnjGa38YANIi9PuxuzXolcXHoU1b13GwoDiw88v3uCllq5T3pgAzl89a2dSZx0kn5ZJTTuZPv1vnD-25A_69BviQB3wDKWLH0FG8ee4IKsCl4J2cu66uLqUdoC7tI5Oo6-f7Zx6W8Jga6kNk5f3rj0MRxa2t5FM5b3H4UVsgIV_CbQ97pQLhJ8OnjNWtsSIQrVUxNdUA0AE5xSXYsosiLT87I4Iwcp6P1AH-E_Z8ZOzJdzQH5m_dmV3E3J3sTnX4NfVXZ7ZdO8JsTzSvMvAo.2o5um1RPiuw_vc2XVA6oSf8cGqn11v-lhUlMzmcLsWg%26dib_tag%3Dse%26keywords%3Dmen%2Bshorts%2Bcotton%26qid%3D1747791854%26sr%3D8-1-spons%26sp_csd%3Dd2lkZ2V0TmFtZT1zcF9hdGY%26psc%3D1#customerReviews"><span aria-hidden="true" class="a-size-base s-underline-text">67,934</span> </a> </div>

the cache is loaded correctly on page refresh and filtering mostly works, however the filtering function seems to break when the original search term is used as the filter, eg. if the original search term 'men cotton shorts' returns 60 products, page refresh loads all 60 from cache and filtering works for terms like cotton (29 results), lounge (7results), etc, but 'men cotton shorts' breaks the filtering and it gets stuck with same 7 results, and filtering for cotton again still shows the same 7 results even though it showed 29 before

<h2 aria-label="Sponsored Ad – PARIS REPAIR Restructuring Conditioner, Vegan, Silicone Free Conditioner, Repairing, Strengthening for Damaged Hair and Brittle Hair, 5.91 fl.oz." class="a-size-base-plus a-spacing-none a-color-base a-text-normal"><span>PARIS REPAIR Restructuring Conditioner, Vegan, Silicone Free Conditioner, Repairing, Strengthening for Damaged Hair and Brittle Hair, 5.91 fl.oz.</span></h2></a> </div><div data-cy="reviews-block" class="a-section a-spacing-none a-spacing-top-micro"><div class="a-row a-size-small"><span class="a-declarative" data-version-id="v27ipgh3xceadv2h68f3w09upuu" data-render-id="r1cj7hddwz0zvs2wqz4bzjfnnqf" data-action="a-popover" data-csa-c-func-deps="aui-da-a-popover" data-a-popover="{&quot;position&quot;:&quot;triggerBottom&quot;,&quot;popoverLabel&quot;:&quot;4.2 out of 5 stars, rating details&quot;,&quot;url&quot;:&quot;/review/widgets/average-customer-review/popover/ref=acr_search__popover?ie=UTF8&amp;asin=B0CLH8H8GZ&amp;ref_=acr_search__popover&amp;contextId=search&quot;,&quot;closeButton&quot;:true,&quot;closeButtonLabel&quot;:&quot;&quot;}" data-csa-c-type="widget"><a aria-label="4.2 out of 5 stars, rating details" href="javascript:void(0)" role="button" class="a-popover-trigger a-declarative"><i data-cy="reviews-ratings-slot" aria-hidden="true" class="a-icon a-icon-star-small a-star-small-4-5"><span class="a-icon-alt">4.2 out of 5 stars</span></i><i class="a-icon a-icon-popover"></i></a></span> <a aria-label="49 ratings" class="a-link-normal s-underline-text s-underline-link-text s-link-style" href="/sspa/click?ie=UTF8&amp;spc=MToxMzI4NTIxODgxNzU3NTkwOjE3NDgwNDE5NTM6c3BfYXRmOjMwMDY3MDY2MTI2NTkwMjo6MDo6&amp;url=%2FRestructuring-Conditioner-Silicone-Repairing-Strengthening%2Fdp%2FB0CLH8H8GZ%2Fref%3Dsr_1_1_sspa%3Fdib%3DeyJ2IjoiMSJ9.DbsJZ2miDMghoBgatkSGKc7S0eF17mwKQ6KFDTfQMVMmRjK2kt3LtWMFzLbN4dYQSNJN0TObVNY2WoJcNomMfVC0L91eQyw31pjjkiVWDbODs9Mxg0u4fHeD6b1EzhIbP5QsrLJ2b86maWlfywk7jYfeSbzAYWP6HnUTH4q_TCWkxLHl6yWb5MLu22nBIAtfaJZFunLhd8QKnGwEPpOoQykFaO77sT2KUjv2-n07IMNONkS2N1QdQ0s1Oia6fDLFiYA0jmiVFd3RQQ8xgyPtrCYafnm7fcrINOwSHI-Injg.XB1-YUIfC6Q2J_84k6tcnbstec8qhq04Ba2neFZV28I%26dib_tag%3Dse%26keywords%3Dphyto%2Bconditioner%26qid%3D1748041953%26sr%3D8-1-spons%26sp_csd%3Dd2lkZ2V0TmFtZT1zcF9hdGY%26psc%3D1#customerReviews"><span aria-hidden="true" class="a-size-base s-underline-text">49</span> </a> </div></div><div data-cy="price-recipe" class="a-section a-spacing-none a-spacing-top-small s-price-instructions-style"><div class="a-row a-size-base a-color-base"><div class="a-row"><span id="price-link" class="aok-offscreen">Price, product page</span><a aria-describedby="price-link" class="a-link-normal s-no-hover s-underline-text s-underline-link-text s-link-style a-text-normal" href="/sspa/click?ie=UTF8&amp;spc=MToxMzI4NTIxODgxNzU3NTkwOjE3NDgwNDE5NTM6c3BfYXRmOjMwMDY3MDY2MTI2NTkwMjo6MDo6&amp;url=%2FRestructuring-Conditioner-Silicone-Repairing-Strengthening%2Fdp%2FB0CLH8H8GZ%2Fref%3Dsr_1_1_sspa%3Fdib%3DeyJ2IjoiMSJ9.DbsJZ2miDMghoBgatkSGKc7S0eF17mwKQ6KFDTfQMVMmRjK2kt3LtWMFzLbN4dYQSNJN0TObVNY2WoJcNomMfVC0L91eQyw31pjjkiVWDbODs9Mxg0u4fHeD6b1EzhIbP5QsrLJ2b86maWlfywk7jYfeSbzAYWP6HnUTH4q_TCWkxLHl6yWb5MLu22nBIAtfaJZFunLhd8QKnGwEPpOoQykFaO77sT2KUjv2-n07IMNONkS2N1QdQ0s1Oia6fDLFiYA0jmiVFd3RQQ8xgyPtrCYafnm7fcrINOwSHI-Injg.XB1-YUIfC6Q2J_84k6tcnbstec8qhq04Ba2neFZV28I%26dib_tag%3Dse%26keywords%3Dphyto%2Bconditioner%26qid%3D1748041953%26sr%3D8-1-spons%26sp_csd%3Dd2lkZ2V0TmFtZT1zcF9hdGY%26psc%3D1"><span class="a-price" data-a-size="xl" data-a-color="base"><span class="a-offscreen">$22.40</span><span aria-hidden="true"><span class="a-price-symbol">$</span><span class="a-price-whole">22<span class="a-price-decimal">.</span></span><span class="a-price-fraction">40</span></span></span> <span class="a-size-base a-color-secondary">(<span class="a-price a-text-price" data-a-size="b" data-a-color="secondary"><span class="a-offscreen">$379.02</span><span aria-hidden="true">$379.02</span></span>/100 ml)</span> <span class="a-offscreen">Was: $26.00</span><div aria-hidden="Was: $26.00" class="a-section aok-inline-block"><span class="a-size-base a-color-secondary">Was: </span><span class="a-price a-text-price" data-a-size="b" data-a-strike="true" data-a-color="secondary"><span class="a-offscreen">$26.00</span><span aria-hidden="true">$26.00</span></span></div></a></div><div class="a-row"></div></div><div class="a-row a-size-base a-color-secondary"><span>$21.28 with Subscribe &amp; Save discount</span></div></div><div data-cy="delivery-recipe" class="a-section a-spacing-none a-spacing-top-micro"><div class="a-row a-size-base a-color-secondary s-align-children-center"><span aria-label="FREE delivery Wed, May 28 on your first order"><span class="a-color-base">FREE delivery </span><span class="a-color-base a-text-bold">Wed, May 28 </span>

<div class="a-section a-spacing-small puis-padding-left-small puis-padding-right-small"><div data-cy="title-recipe" class="a-section a-spacing-none a-spacing-top-small s-title-instructions-style"><div class="a-row a-spacing-micro"><span class="a-declarative" data-version-id="v27ipgh3xceadv2h68f3w09upuu" data-render-id="r1lma8lfbs7ls82bv0w58rz788b" data-action="a-popover" data-csa-c-func-deps="aui-da-a-popover" data-a-popover="{&quot;name&quot;:&quot;sp-info-popover-B0BZQSY8YW&quot;,&quot;position&quot;:&quot;triggerVertical&quot;,&quot;popoverLabel&quot;:&quot;View Sponsored information or leave ad feedback&quot;,&quot;closeButtonLabel&quot;:&quot;Close pop-up&quot;,&quot;closeButton&quot;:&quot;true&quot;,&quot;dataStrategy&quot;:&quot;preload&quot;}" data-csa-c-type="widget" data-csa-c-id="4xdlhz-48r2j6-udplnr-fnav63"><a href="javascript:void(0)" role="button" style="text-decoration: none;" class="puis-label-popover puis-sponsored-label-text"><span class="puis-label-popover-default"><span aria-label="View Sponsored information or leave ad feedback" class="a-color-secondary">Sponsored</span></span><span class="puis-label-popover-hover"><span aria-hidden="true" class="a-color-base">Sponsored</span></span> <span class="aok-inline-block puis-sponsored-label-info-icon"></span></a></span></div><div class="a-row a-color-secondary"><h2 class="a-size-mini s-line-clamp-1"><span class="a-size-base-plus a-color-base">Phyto</span></h2></div><a class="a-link-normal s-line-clamp-3 s-link-style a-text-normal" href="/sspa/click?ie=UTF8&amp;spc=MTo2NDA4NTEzMDQ2MDc0ODg1OjE3NDgwNDc4NzU6c3BfYXRmOjMwMDY1NjQxOTU1OTkwMjo6MDo6&amp;url=%2FPhyto-Softness-Express-Detangling-Leave%2Fdp%2FB0BZQSY8YW%2Fref%3Dsr_1_4_sspa%3Fdib%3DeyJ2IjoiMSJ9.DbsJZ2miDMghoBgatkSGKc7S0eF17mwKQ6KFDTfQMVMmRjK2kt3LtWMFzLbN4dYQSNJN0TObVNY2WoJcNomMfVC0L91eQyw31pjjkiVWDbODs9Mxg0u4fHeD6b1EzhIbP5QsrLJ2b86maWlfywk7jZ_RrCN50I54zJEXvmQ0oUgar06MSHVDiTewT4oBSDqwzoBluhgT9RdJg29T_WzTzUfueddvWMpKRYxR6KEh5iJm6yhkaHL-BFF3ha48jZEDInjjvsw73tzGbwSU-mRCyG0-OryCdtajTI9evW6qlMg._GqjRpg0ABAbzP39lpnMRnALDqjFpE0AHNx1vQNx70k%26dib_tag%3Dse%26keywords%3Dphyto%2Bconditioner%26qid%3D1748047874%26sr%3D8-4-spons%26sp_csd%3Dd2lkZ2V0TmFtZT1zcF9hdGY%26psc%3D1"><h2 aria-label="Sponsored Ad – Softness Express Detangling Milk - Leave-In Conditioner for Dry Damaged Hair, Natural Oat Milk, White Mallow, Rosemary &amp; Calendula Oil, For Family Use Detangler and Styler Hair Care |150ml" class="a-size-base-plus a-spacing-none a-color-base a-text-normal"><span>Softness Express Detangling Milk - Leave-In Conditioner for Dry Damaged Hair, Natural Oat Milk, White Mallow, Rosemary &amp; Calendula Oil, For Family Use Detangler and Styler Hair Care |150ml</span></h2></a> </div><div data-cy="reviews-block" class="a-section a-spacing-none a-spacing-top-micro"><div class="a-row a-size-small"><span class="a-declarative" data-version-id="v27ipgh3xceadv2h68f3w09upuu" data-render-id="r1lma8lfbs7ls82bv0w58rz788b" data-action="a-popover" data-csa-c-func-deps="aui-da-a-popover" data-a-popover="{&quot;position&quot;:&quot;triggerBottom&quot;,&quot;popoverLabel&quot;:&quot;4.2 out of 5 stars, rating details&quot;,&quot;url&quot;:&quot;/review/widgets/average-customer-review/popover/ref=acr_search__popover?ie=UTF8&amp;asin=B0BZQSY8YW&amp;ref_=acr_search__popover&amp;contextId=search&quot;,&quot;closeButton&quot;:true,&quot;closeButtonLabel&quot;:&quot;&quot;}" data-csa-c-type="widget" data-csa-c-id="wh57bo-t2hsei-fs31hl-4sntz3"><a aria-label="4.2 out of 5 stars, rating details" href="javascript:void(0)" role="button" class="a-popover-trigger a-declarative"><i data-cy="reviews-ratings-slot" aria-hidden="true" class="a-icon a-icon-star-small a-star-small-4"><span class="a-icon-alt">4.2 out of 5 stars</span></i><i class="a-icon a-icon-popover"></i></a></span> <a aria-label="59 ratings" class="a-link-normal s-underline-text s-underline-link-text s-link-style" href="/sspa/click?ie=UTF8&amp;spc=MTo2NDA4NTEzMDQ2MDc0ODg1OjE3NDgwNDc4NzU6c3BfYXRmOjMwMDY1NjQxOTU1OTkwMjo6MDo6&amp;url=%2FPhyto-Softness-Express-Detangling-Leave%2Fdp%2FB0BZQSY8YW%2Fref%3Dsr_1_4_sspa%3Fdib%3DeyJ2IjoiMSJ9.DbsJZ2miDMghoBgatkSGKc7S0eF17mwKQ6KFDTfQMVMmRjK2kt3LtWMFzLbN4dYQSNJN0TObVNY2WoJcNomMfVC0L91eQyw31pjjkiVWDbODs9Mxg0u4fHeD6b1EzhIbP5QsrLJ2b86maWlfywk7jZ_RrCN50I54zJEXvmQ0oUgar06MSHVDiTewT4oBSDqwzoBluhgT9RdJg29T_WzTzUfueddvWMpKRYxR6KEh5iJm6yhkaHL-BFF3ha48jZEDInjjvsw73tzGbwSU-mRCyG0-OryCdtajTI9evW6qlMg._GqjRpg0ABAbzP39lpnMRnALDqjFpE0AHNx1vQNx70k%26dib_tag%3Dse%26keywords%3Dphyto%2Bconditioner%26qid%3D1748047874%26sr%3D8-4-spons%26sp_csd%3Dd2lkZ2V0TmFtZT1zcF9hdGY%26psc%3D1#customerReviews"><span aria-hidden="true" class="a-size-base s-underline-text">59</span> </a> </div></div><div data-cy="price-recipe" class="a-section a-spacing-none a-spacing-top-small s-price-instructions-style"><div class="a-row a-size-base a-color-base"><div class="a-row"><span id="price-link" class="aok-offscreen">Price, product page</span><a aria-describedby="price-link" class="a-link-normal s-no-hover s-underline-text s-underline-link-text s-link-style a-text-normal" href="/sspa/click?ie=UTF8&amp;spc=MTo2NDA4NTEzMDQ2MDc0ODg1OjE3NDgwNDc4NzU6c3BfYXRmOjMwMDY1NjQxOTU1OTkwMjo6MDo6&amp;url=%2FPhyto-Softness-Express-Detangling-Leave%2Fdp%2FB0BZQSY8YW%2Fref%3Dsr_1_4_sspa%3Fdib%3DeyJ2IjoiMSJ9.DbsJZ2miDMghoBgatkSGKc7S0eF17mwKQ6KFDTfQMVMmRjK2kt3LtWMFzLbN4dYQSNJN0TObVNY2WoJcNomMfVC0L91eQyw31pjjkiVWDbODs9Mxg0u4fHeD6b1EzhIbP5QsrLJ2b86maWlfywk7jZ_RrCN50I54zJEXvmQ0oUgar06MSHVDiTewT4oBSDqwzoBluhgT9RdJg29T_WzTzUfueddvWMpKRYxR6KEh5iJm6yhkaHL-BFF3ha48jZEDInjjvsw73tzGbwSU-mRCyG0-OryCdtajTI9evW6qlMg._GqjRpg0ABAbzP39lpnMRnALDqjFpE0AHNx1vQNx70k%26dib_tag%3Dse%26keywords%3Dphyto%2Bconditioner%26qid%3D1748047874%26sr%3D8-4-spons%26sp_csd%3Dd2lkZ2V0TmFtZT1zcF9hdGY%26psc%3D1"><span class="a-price" data-a-size="xl" data-a-color="base"><span class="a-offscreen">$28.00</span><span aria-hidden="true"><span class="a-price-symbol">$</span><span class="a-price-whole">28<span class="a-price-decimal">.</span></span><span class="a-price-fraction">00</span></span></span> <span class="a-size-base a-color-secondary">(<span class="a-price a-text-price" data-a-size="b" data-a-color="secondary"><span class="a-offscreen">$18.67</span><span aria-hidden="true">$18.67</span></span>/100 ml)</span></a></div><div class="a-row"></div></div><div class="a-row a-size-base a-color-secondary"><div class="a-row"><span>$26.60 with Subscribe &amp; Save discount</span></div><div class="a-row"><span>Buy any 3, Get 1  free</span></div></div></div><div data-cy="delivery-recipe" class="a-section a-spacing-none a-spacing-top-micro"><div class="a-row a-size-base a-color-secondary s-align-children-center"><div data-cy="delivery-block" class="a-section a-spacing-none a-padding-none udm-delivery-block"><div class="a-row a-color-base udm-primary-delivery-message"><div class="a-column a-span12">FREE delivery <span id="WVCRIAFWG" class="a-text-bold">Thu, May 29</span> on your first order</div></div><div class="a-row a-color-base udm-secondary-delivery-message"><div class="a-column a-span12"></div></div><div class="a-row a-color-base udm-legal-and-regulatory-message"><div class="a-column a-span12"></div></div></div></div></div><div class="a-section a-spacing-none a-spacing-top-mini"><div class="a-row"><div class="puis-atcb-container" data-cy="add-to-cart" data-atcb-uid="atcb-B0BZQSY8YW-4" data-atcb-props="{&quot;cartType&quot;:&quot;DEFAULT&quot;,&quot;csrfToken&quot;:&quot;1@g8Q851P2WmfYb1QMgCCih+NDIo5MK16WCnbIseFGRZpJAAAAAQAAAABoMRgDcmF3AAAAAGfA1H5nd8xGEcC3127HUQ==@ML8U5V&quot;,&quot;sessionId&quot;:&quot;139-1487385-1370642&quot;,&quot;locale&quot;:&quot;en-CA&quot;}"><div class="addToCartShoppingPortalCSRFToken aok-hidden"><!-- sp:csrf --><meta name="anti-csrftoken-a2z" content="hBRaItyVheitkyDSU+MywIJDHd+MDGLwbH+gHKWBROBcAAAAAGgxGAMyMTg1N2VhNC04MTNkLTQ1NWQtOTFiNS00ZjYyNGIwMGZhYjc="><!-- sp:end-csrf --></div><div class="a-section puis-atcb-add-container aok-inline-block"><div class="a-section atc-faceout-container"><form method="post" action="/cart/add-to-cart?ref=sr_atc_rt_add_4_sspa&amp;sr=8-4&amp;qid=1748047874&amp;discoveredAsins.0=B0BZQSY8YW" class="a-spacing-none"><!-- sp:csrf --><input type="hidden" name="anti-csrftoken-a2z" value="hBRaItyVheitkyDSU+MywIJDHd+MDGLwbH+gHKWBROBcAAAAAGgxGAMyMTg1N2VhNC04MTNkLTQ1NWQtOTFiNS00ZjYyNGIwMGZhYjc="><!-- sp:end-csrf --><input type="hidden" name="clientName" value="EUIC_AddToCart_Search"><input type="hidden" name="items[0.base][asin]" value="B0BZQSY8YW"><input type="hidden" name="items[0.base][offerListingId]" value="%2BC6SYTsRDYY%2FzILuUyWbXj1OuN3Gm58gz59B%2F6tpaedwE9v8ch%2BBsO%2Fwfd0eY9NOpTzPoo%2Fr2WDHf6g5YaCGUZX78qp7CnuW6Zg6mMxib%2BYkxe0gVPAG%2F6FWZaqlpHUU2OSx8usxGZQGRN5l%2FuLgWSpTKn%2FYI35QrF8dTSudWnxviAQ%2B7Y7uko1L%2Fm1GcN9G"><input type="hidden" name="items[0.base][quantity]" value="1"><div class="a-section ax-replace a-spacing-none"><div class="ax-atc celwidget atc-btn-container" data-csa-c-type="item" data-csa-c-content-id="ax-atc-EUIC_AddToCart_Search-content" data-csa-c-slot-id="ax-atc-EUIC_AddToCart_Search" data-csa-c-device-type="DESKTOP" data-csa-c-device-env="WEB" data-csa-c-device-os="UNRECOGNIZED" data-csa-c-item-type="asin" data-csa-c-item-id="B0BZQSY8YW" data-csa-c-pos="4" id="ax-atc-EUIC_AddToCart_Search" data-csa-c-id="1h9izr-ik4jfy-uwps9-xcd023"><span class="a-declarative" data-version-id="v27ipgh3xceadv2h68f3w09upuu" data-render-id="r1lma8lfbs7ls82bv0w58rz788b" data-action="puis-atcb-add-action-retail" data-csa-c-func-deps="aui-da-puis-atcb-add-action-retail" data-puis-atcb-add-action-retail="{&quot;clientName&quot;:&quot;EUIC_AddToCart_Search&quot;,&quot;messageSuccess&quot;:&quot;Item added&quot;,&quot;sponsoredLoggingUrl&quot;:&quot;https://www.amazon.ca/sspa/click?ie=UTF8&amp;action=clickAddToCart&amp;spc=MTo2NDA4NTEzMDQ2MDc0ODg1OjE3NDgwNDc4NzU6c3BfYXRmOjMwMDY1NjQxOTU1OTkwMjo6MDo6&quot;,&quot;spAttributionURL&quot;:&quot;https://www.amazon.ca/sspa/click?ie=UTF8&amp;action=clickAddToCart&amp;spc=MTo2NDA4NTEzMDQ2MDc0ODg1OjE3NDgwNDc4NzU6c3BfYXRmOjMwMDY1NjQxOTU1OTkwMjo6MDo6&quot;,&quot;neoAtcUrl&quot;:&quot;/cart/add-to-cart?ref=sr_atc_rt_add_4_sspa&amp;sr=8-4&amp;qid=1748047874&amp;discoveredAsins.0=B0BZQSY8YW&quot;,&quot;messageError&quot;:&quot;Failed to add item&quot;,&quot;additionalParameters&quot;:{},&quot;asin&quot;:&quot;B0BZQSY8YW&quot;,&quot;spAttributionMethod&quot;:&quot;POST&quot;,&quot;url&quot;:&quot;https://data.amazon.ca/api/marketplaces/A2EUQ1WTGCTBG2/cart/carts/retail/items?ref=sr_atc_rt_add_4_sspa&amp;sr=8-4&amp;qid=1748047874&amp;discoveredAsins.0=B0BZQSY8YW&quot;,&quot;offerListingId&quot;:&quot;%2BC6SYTsRDYY%2FzILuUyWbXj1OuN3Gm58gz59B%2F6tpaedwE9v8ch%2BBsO%2Fwfd0eY9NOpTzPoo%2Fr2WDHf6g5YaCGUZX78qp7CnuW6Zg6mMxib%2BYkxe0gVPAG%2F6FWZaqlpHUU2OSx8usxGZQGRN5l%2FuLgWSpTKn%2FYI35QrF8dTSudWnxviAQ%2B7Y7uko1L%2Fm1GcN9G&quot;}" data-csa-c-type="widget" data-csa-c-id="voklsa-94g1oo-trnz3o-v4czue"><div data-csa-c-type="action" data-csa-c-content-id="s-search-add-to-cart-action" data-csa-c-device-type="DESKTOP" data-csa-c-device-env="WEB" data-csa-c-device-os="UNRECOGNIZED" data-csa-c-action-name="addToCart" data-csa-c-item-type="asin" data-csa-c-item-id="B0BZQSY8YW" data-csa-c-id="8bpvs-9gyu1z-x2a0k3-msi7eb"><span class="a-button a-button-primary a-button-icon puis-atcb-button" id="a-autoid-4"><span class="a-button-inner"><i class="a-icon a-icon-cart"></i><button name="submit.addToCart" aria-label="Add to cart" class="a-button-text" type="button" id="a-autoid-4-announce">Add to cart</button></span></span></div></span></div></div></form></div></div><div class="a-section puis-atcb-error-container aok-hidden"><div class="a-box a-alert-inline a-alert-inline-error" role="alert"><div class="a-box-inner a-alert-container"><i class="a-icon a-icon-alert"></i><div class="a-alert-content"><span class="a-size-mini puis-atcb-error-message"></span></div></div></div></div><div class="a-section puis-atcb-extra-container"></div></div></div></div></div>

## Title Flow from Cache to Display

### 1. **Title Extraction from Amazon** (Initial Scraping)
The title is first extracted from Amazon's HTML using multiple fallback methods in `includes/amazon-api.php` (lines 295-390):

```295:390:includes/amazon-api.php
// Method 1: h2 with aria-label
$h2WithAriaLabel = $xpath->query('.//h2[@aria-label]', $element)->item(0);
if ($h2WithAriaLabel) {
    $spanInsideH2 = $xpath->query('.//span', $h2WithAriaLabel)->item(0);
    if ($spanInsideH2) {
        $title = trim(preg_replace('/\s+/', ' ', $spanInsideH2->textContent));
        $title_extraction_method = 'h2_aria_label_span';
    }
}

// Method 2: Standard a-text-normal selector
if (empty($title)) {
    $titleNode = $xpath->query('.//span[contains(@class, "a-text-normal")]', $element)->item(0);
    if ($titleNode) {
        $title = trim(preg_replace('/\s+/', ' ', $titleNode->textContent));
        $title_extraction_method = 'a_text_normal';
    }
}
// ... more fallback methods
```

### 2. **Storing in Cache** 
The extracted title is stored as part of the product data in the cache table via `ps_cache_results()` (lines 840-920):

```564:580:includes/amazon-api.php
$product_data = array(
    'title' => $title,
    'link' => $link,
    'price' => $price_str,
    'price_value' => $price_value,
    'image' => $image,
    // ... other fields
    'title_extraction_method' => $title_extraction_method // For debugging
);
```

The product data is JSON-encoded and stored in the `results` column of the `ps_cache` table.

### 3. **Retrieving from Cache**
When a user loads the page or performs a search, titles are retrieved via `ps_get_cached_results()` (lines 780-835):

```780:835:primates-shoppers.php
$cached_data = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM $table_name 
        WHERE query_hash = %s 
        AND user_id = %s
        AND (expires_at IS NOT NULL AND expires_at > DATE_SUB(NOW(), INTERVAL 300 SECOND))
        ORDER BY created_at DESC 
        LIMIT 1",
        $query_hash,
        $user_id
    )
);

if ($cached_data) {
    // Parse the JSON data
    $results = json_decode($cached_data->results, true);
    return $results;
}
```

The cached results contain an array of products, each with a `title` field.

### 4. **AJAX Response**
The cached data is sent back to the frontend via AJAX in `ps_ajax_search()` or `ps_ajax_filter()`:

```460-520:primates-shoppers.php
$display_results = array(
    'success' => true,
    'items' => $cached_results['items'], // Contains title field
    'count' => $cached_results['count'],
    // ... other fields
);

wp_send_json_success($display_results);
```

### 5. **Frontend Processing**
The JavaScript receives the AJAX response and processes the items in `renderProducts()` (lines 364-569 in `assets/js/search.js`):

```420-430:assets/js/search.js
// Replace standard placeholders
productHtml = productHtml
    .replace(/{{brand}}/g, item.brand || '')
    .replace(/{{title}}/g, item.title || '')
    .replace(/{{link}}/g, item.link || '')
    .replace(/{{image}}/g, item.image || '')
    .replace(/{{price}}/g, item.price || '')
    // ... other replacements
```

### 6. **HTML Template Rendering**
The title is inserted into the HTML template defined in `templates/search-form.php` (lines 115-118):

```115:118:templates/search-form.php
<h3 class="ps-product-title">
    <a href="{{link}}" target="_blank">{{title}}</a>
</h3>
```

### 7. **Final Display**
The processed HTML is appended to the results container:

```460:assets/js/search.js
$results.append(productHtml);
```

## Key Points:

1. **Multiple Extraction Methods**: The system uses 7 different XPath methods to extract titles from Amazon's HTML, with fallbacks for reliability.

2. **Cache Structure**: Titles are stored in the database as JSON within the `results` column, associated with user IDs and query hashes.

3. **Filtering**: When filtering cached results, the title field is used for include/exclude matching in `ps_filter_amazon_products()` (line 1341):

```1341:primates-shoppers.php
if ($term !== '' && strpos($title, $term) === false) {
    return false; // Must match all include terms
}
```

4. **Template System**: The frontend uses a simple template replacement system rather than a full templating engine, replacing `{{title}}` placeholders with actual values.

5. **User-Specific Caching**: Each user gets their own cached results based on their user identifier, so titles are retrieved per-user.

The title flows from Amazon's HTML → XPath extraction → JSON cache storage → AJAX retrieval → JavaScript template processing → DOM insertion for display.
