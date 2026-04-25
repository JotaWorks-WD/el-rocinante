<?php

// ============================================================
// SEO PREVIEW PANEL — Google, Facebook, Twitter tabs
// ============================================================

function roci_seo_preview_html( $default_og_image ) {
    return array(
        'type' => 'custom_html',
        'std'  => '
        <style>
            .roci-preview-wrap { margin-top: 10px; }
            .roci-preview-tabs {
                display: flex;
                gap: 0;
                margin-bottom: 0;
                border-bottom: 2px solid #e0e0e0;
            }
            .roci-preview-tab {
                padding: 8px 20px;
                cursor: pointer;
                font-size: 13px;
                font-weight: 600;
                color: #666;
                border: none;
                background: none;
                border-bottom: 2px solid transparent;
                margin-bottom: -2px;
            }
            .roci-preview-tab.active {
                color: #2271b1;
                border-bottom: 2px solid #2271b1;
            }
            .roci-preview-panel {
                display: none;
                padding: 20px;
                background: #f9f9f9;
                border: 1px solid #e0e0e0;
                border-top: none;
            }
            .roci-preview-panel.active { display: block; }
            .roci-image-source {
                font-size: 11px;
                color: #888;
                margin-top: 6px;
                font-style: italic;
            }

            /* Google */
            .roci-google-preview { font-family: arial, sans-serif; max-width: 600px; }
            .roci-google-row { display: flex; align-items: flex-start; gap: 16px; }
            .roci-google-image {
                width: 92px; height: 92px; border-radius: 8px;
                flex-shrink: 0; background: #e4e6eb;
                display: flex; align-items: center; justify-content: center;
                font-size: 11px; color: #999; text-align: center; overflow: hidden;
            }
            .roci-google-image img { width: 100%; height: 100%; object-fit: cover; }
            .roci-google-text { flex: 1; }
            .roci-google-url { font-size: 14px; color: #202124; margin-bottom: 2px; }
            .roci-google-title {
                font-size: 20px; color: #1a0dab; margin-bottom: 4px;
                line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            }
            .roci-google-desc {
                font-size: 14px; color: #4d5156; line-height: 1.5;
                display: -webkit-box; -webkit-line-clamp: 2;
                -webkit-box-orient: vertical; overflow: hidden;
            }
            .roci-char-count { font-size: 11px; margin-top: 4px; }
            .roci-char-count.over { color: #d63638; }
            .roci-char-count.good { color: #00a32a; }
            .roci-char-count.low  { color: #dba617; }

            /* Social */
            .roci-social-preview { max-width: 500px; border: 1px solid #dddfe2; font-family: Helvetica, Arial, sans-serif; }
            .roci-social-image {
                width: 100%; height: 260px; background: #e4e6eb;
                display: flex; align-items: center; justify-content: center;
                color: #bcc0c4; font-size: 13px; overflow: hidden; text-align: center;
            }
            .roci-social-image img { width: 100%; height: 100%; object-fit: cover; }
            .roci-social-body { padding: 12px; background: #f0f2f5; border-top: 1px solid #dddfe2; }
            .roci-social-domain { font-size: 12px; color: #65676b; text-transform: uppercase; margin-bottom: 4px; }
            .roci-social-title { font-size: 16px; font-weight: 600; color: #1c1e21; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .roci-social-desc { font-size: 14px; color: #65676b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        </style>

        <div class="roci-preview-wrap">
            <div class="roci-preview-tabs">
                <button class="roci-preview-tab active" data-tab="google">Google Preview</button>
                <button class="roci-preview-tab" data-tab="facebook">Facebook Preview</button>
                <button class="roci-preview-tab" data-tab="twitter">Twitter Preview</button>
                <button class="roci-preview-tab" data-tab="health">SEO Health</button>
            </div>

            <!-- Google -->
            <div class="roci-preview-panel active" id="roci-tab-google">
                <div class="roci-google-preview">
                    <div class="roci-google-row">
                        <div class="roci-google-image" id="roci-google-img-wrap"><span>No image</span></div>
                        <div class="roci-google-text">
                            <div class="roci-google-url" id="roci-preview-url"></div>
                            <div class="roci-google-title" id="roci-preview-gtitle">Page Title</div>
                            <div class="roci-char-count" id="roci-title-count"></div>
                            <div class="roci-google-desc" id="roci-preview-gdesc">Meta description will appear here...</div>
                            <div class="roci-char-count" id="roci-desc-count"></div>
                        </div>
                    </div>
                    <div class="roci-image-source" id="roci-google-img-source"></div>
                </div>
            </div>

            <!-- Facebook -->
            <div class="roci-preview-panel" id="roci-tab-facebook">
                <div class="roci-social-preview">
                    <div class="roci-social-image" id="roci-fb-image-wrap"><span>No image set</span></div>
                    <div class="roci-social-body">
                        <div class="roci-social-domain" id="roci-fb-domain"></div>
                        <div class="roci-social-title" id="roci-fb-title">Page Title</div>
                        <div class="roci-social-desc" id="roci-fb-desc">Meta description...</div>
                    </div>
                </div>
                <div class="roci-image-source" id="roci-fb-img-source"></div>
            </div>

            <!-- Twitter -->
            <div class="roci-preview-panel" id="roci-tab-twitter">
                <div class="roci-social-preview">
                    <div class="roci-social-image" id="roci-tw-image-wrap"><span>No image set</span></div>
                    <div class="roci-social-body">
                        <div class="roci-social-domain" id="roci-tw-domain"></div>
                        <div class="roci-social-title" id="roci-tw-title">Page Title</div>
                        <div class="roci-social-desc" id="roci-tw-desc">Meta description...</div>
                    </div>
                </div>
                <div class="roci-image-source" id="roci-tw-img-source"></div>
            </div>

            <!-- SEO Health placeholder — populated by metabox-seo-health.php -->
            <div class="roci-preview-panel" id="roci-tab-health">
                <div id="roci-health-panel"></div>
            </div>

        </div>

        <script>
        document.addEventListener("DOMContentLoaded", function() {

            var titleField   = document.getElementById("roci_meta_title");
            var descField    = document.getElementById("roci_meta_description");
            var siteUrl      = window.location.hostname;
            var defaultOgImg = "' . esc_js( $default_og_image ) . '";

            window.rociCurrentOgImageUrl     = "";
            window.rociCurrentOgImageData    = null;
            window.rociCurrentFeaturedImgUrl = "";
            window.rociCurrentFeaturedImgData = null;

            function getActiveImage() {
                if ( window.rociCurrentOgImageUrl ) {
                    return { url: window.rociCurrentOgImageUrl, source: "Using: OG Image field" };
                } else if ( window.rociCurrentFeaturedImgUrl ) {
                    return { url: window.rociCurrentFeaturedImgUrl, source: "Using: Featured Image (fallback)" };
                } else if ( defaultOgImg ) {
                    return { url: defaultOgImg, source: "Using: Site default OG image (fallback)" };
                }
                return { url: "", source: "No image set" };
            }

            function setImageInElement( wrapId, sourceId, imageData ) {
                var wrap   = document.getElementById( wrapId );
                var source = document.getElementById( sourceId );
                if ( wrap ) {
                    wrap.innerHTML = imageData.url
                        ? "<img src=\'" + imageData.url + "\' alt=\'\'>"
                        : "<span>No image set</span>";
                }
                if ( source ) source.textContent = imageData.source;
            }

            window.rociUpdatePreviews = function() {
                var title = titleField ? titleField.value.trim() : "";
                var desc  = descField  ? descField.value.trim()  : "";

                if ( !title ) {
                    var wpTitle = document.getElementById("title");
                    title = wpTitle ? wpTitle.value.trim() : "Page Title";
                }
                if ( !desc ) desc = "No meta description set.";

                var imageData = getActiveImage();

                // Google
                var el = function(id) { return document.getElementById(id); };
                if (el("roci-preview-gtitle")) el("roci-preview-gtitle").textContent = title;
                if (el("roci-preview-gdesc"))  el("roci-preview-gdesc").textContent  = desc;
                if (el("roci-preview-url"))    el("roci-preview-url").textContent    = siteUrl;

                if (el("roci-title-count") && titleField) {
                    var tlen = titleField.value.length;
                    el("roci-title-count").textContent = tlen + " / 60 characters";
                    el("roci-title-count").className   = "roci-char-count " + (tlen > 60 ? "over" : tlen >= 50 ? "good" : "low");
                }
                if (el("roci-desc-count") && descField) {
                    var dlen = descField.value.length;
                    el("roci-desc-count").textContent = dlen + " / 160 characters";
                    el("roci-desc-count").className   = "roci-char-count " + (dlen > 160 ? "over" : dlen >= 150 ? "good" : "low");
                }

                setImageInElement("roci-google-img-wrap", "roci-google-img-source", imageData);

                // Facebook
                if (el("roci-fb-title"))  el("roci-fb-title").textContent  = title;
                if (el("roci-fb-desc"))   el("roci-fb-desc").textContent   = desc;
                if (el("roci-fb-domain")) el("roci-fb-domain").textContent = siteUrl;
                setImageInElement("roci-fb-image-wrap", "roci-fb-img-source", imageData);

                // Twitter
                if (el("roci-tw-title"))  el("roci-tw-title").textContent  = title;
                if (el("roci-tw-desc"))   el("roci-tw-desc").textContent   = desc;
                if (el("roci-tw-domain")) el("roci-tw-domain").textContent = siteUrl;
                setImageInElement("roci-tw-image-wrap", "roci-tw-img-source", imageData);

                // Trigger health update if available
                if ( typeof window.rociUpdateHealth === "function" ) {
                    window.rociUpdateHealth();
                }
            };

            // Watch OG Image field
            function watchOgImageField() {
                var input = document.querySelector("input.rwmb-image_advanced[name=\"roci_og_image\"]");
                if ( !input ) return;

                function parseOgImage() {
                    var attachments = input.getAttribute("data-attachments");
                    if ( !attachments ) {
                        window.rociCurrentOgImageUrl  = "";
                        window.rociCurrentOgImageData = null;
                        window.rociUpdatePreviews();
                        return;
                    }
                    try {
                        var data = JSON.parse( attachments );
                        if ( data && data.length > 0 ) {
                            window.rociCurrentOgImageData = data[0];
                            window.rociCurrentOgImageUrl  = data[0].sizes && data[0].sizes.large
                                ? data[0].sizes.large.url
                                : data[0].url;
                        } else {
                            window.rociCurrentOgImageUrl  = "";
                            window.rociCurrentOgImageData = null;
                        }
                    } catch(e) {
                        window.rociCurrentOgImageUrl  = "";
                        window.rociCurrentOgImageData = null;
                    }
                    window.rociUpdatePreviews();
                }

                new MutationObserver( parseOgImage ).observe( input, {
                    attributes: true,
                    attributeFilter: ["data-attachments", "value"]
                });
                parseOgImage();
            }

            // Watch Featured Image
            function watchFeaturedImage() {
                function parseFeaturedImage() {
                    var img = document.querySelector(".editor-post-featured-image__preview-image");
                    window.rociCurrentFeaturedImgUrl = img ? img.src : "";
                    window.rociUpdatePreviews();
                }

                var featWrap = document.querySelector(".editor-post-featured-image");
                if ( featWrap ) {
                    new MutationObserver( parseFeaturedImage ).observe( featWrap, {
                        childList: true, subtree: true, attributes: true
                    });
                }

                new MutationObserver(function() {
                    var img = document.querySelector(".editor-post-featured-image__preview-image");
                    if ( img && img.src !== window.rociCurrentFeaturedImgUrl ) {
                        window.rociCurrentFeaturedImgUrl = img.src;
                        window.rociUpdatePreviews();
                    }
                }).observe( document.body, { childList: true, subtree: true });

                parseFeaturedImage();
            }

            // Tabs
            var tabs = document.querySelectorAll(".roci-preview-tab");
            tabs.forEach(function(tab) {
                tab.addEventListener("click", function() {
                    tabs.forEach(function(t) { t.classList.remove("active"); });
                    document.querySelectorAll(".roci-preview-panel").forEach(function(p) { p.classList.remove("active"); });
                    tab.classList.add("active");
                    var panel = document.getElementById("roci-tab-" + tab.dataset.tab);
                    if (panel) panel.classList.add("active");
                    if ( tab.dataset.tab === "health" && typeof window.rociUpdateHealth === "function" ) {
                        window.rociUpdateHealth();
                    }
                });
            });

            // Init
            if (titleField) titleField.addEventListener("input", window.rociUpdatePreviews);
            if (descField)  descField.addEventListener("input", window.rociUpdatePreviews);

            watchOgImageField();
            watchFeaturedImage();
            window.rociUpdatePreviews();

        });
        </script>
        ',
    );
}