<?php

// ============================================================
// SEO HEALTH PANEL
// ============================================================

function roci_seo_health_html( $default_og_image ) {
    return array(
        'type' => 'custom_html',
        'std'  => '
        <style>
            #roci-health-panel {
                padding: 4px 0;
            }
            .roci-health-item {
                display: flex;
                align-items: flex-start;
                gap: 10px;
                padding: 8px 0;
                border-bottom: 1px solid #f0f0f0;
                font-size: 13px;
                line-height: 1.4;
            }
            .roci-health-item:last-child { border-bottom: none; }
            .roci-health-icon {
                font-size: 15px;
                flex-shrink: 0;
                margin-top: 1px;
            }
            .roci-health-label { font-weight: 600; color: #1e1e1e; }
            .roci-health-note  { color: #888; font-size: 12px; margin-top: 2px; }
            .roci-health-pass  { color: #00a32a; }
            .roci-health-fail  { color: #d63638; }
            .roci-health-skip  { color: #aaa; }
            .roci-health-info  { color: #2271b1; }
            .roci-health-section {
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #888;
                padding: 12px 0 4px;
            }
        </style>

        <script>
        document.addEventListener("DOMContentLoaded", function() {

            var defaultOgImg = "' . esc_js( $default_og_image ) . '";
            var cachedSlug   = "";
            var slugFetched  = false;
            var slugFetching = false;
            var postId       = new URLSearchParams( window.location.search ).get("post");

            // ------------------------------------------------
            // FETCH SLUG VIA REST API
            // Tries pages first, falls back to posts
            // ------------------------------------------------
            function fetchSlug( callback ) {
                if ( !postId ) { callback(""); return; }

                fetch( "/wp-json/wp/v2/pages/" + postId )
                    .then(function(r) {
                        if ( r.ok ) return r.json();
                        return fetch( "/wp-json/wp/v2/posts/" + postId ).then(function(r2) {
                            if ( r2.ok ) return r2.json();
                            return null;
                        });
                    })
                    .then(function(data) {
                        if ( data && data.slug ) {
                            cachedSlug  = data.slug;
                            slugFetched = true;
                            callback( cachedSlug );
                        } else {
                            slugFetched = true;
                            callback("");
                        }
                    })
                    .catch(function() {
                        slugFetched = true;
                        callback("");
                    });
            }

            // ------------------------------------------------
            // MAIN HEALTH UPDATE
            // ------------------------------------------------
            window.rociUpdateHealth = function() {

                var panel = document.getElementById("roci-health-panel");
                if ( !panel ) return;

                // Show loading state if slug not yet fetched
                if ( !slugFetched ) {
                    if ( slugFetching ) return;
                    slugFetching = true;
                    panel.innerHTML = "<div style=\"color:#888;font-size:13px;padding:12px 0;\">Loading SEO Health...</div>";
                    fetchSlug(function() {
                        slugFetching = false;
                        window.rociUpdateHealth();
                    });
                    return;
                }

                // Gather field values
                var query     = (document.getElementById("roci_target_query")     || {}).value || "";
                var title     = (document.getElementById("roci_meta_title")       || {}).value || "";
                var desc      = (document.getElementById("roci_meta_description") || {}).value || "";
                var canonical = (document.getElementById("roci_canonical")        || {}).value || "";
                var schemaEl  = document.getElementById("roci_schema_json");
                var schema    = schemaEl ? schemaEl.value.trim() : "";
                var slug      = cachedSlug;

                // OG Image data
                var ogData = window.rociCurrentOgImageData || null;

                // Featured Image
                var featImg = document.querySelector(".editor-post-featured-image__preview-image");
                var featUrl = featImg ? featImg.src : "";

                var queryLower = query.toLowerCase();
                var titleLower = title.toLowerCase();
                var descLower  = desc.toLowerCase();
                var slugLower  = slug.toLowerCase().replace(/-/g, " ");

                // ------------------------------------------------
                // BUILD HEALTH ITEMS
                // ------------------------------------------------
                var items = [];

                function item( label, pass, note, type ) {
                    items.push({ label: label, pass: pass, note: note || "", type: type || "check" });
                }
                function section( label ) {
                    items.push({ section: label });
                }
                function info( label, note ) {
                    items.push({ label: label, pass: null, note: note || "", type: "info" });
                }

                // ---- TARGET SEARCH QUERY ----
                section("Target Search Query");
                if ( !query ) {
                    item( "Target Search Query", null, "Not set — query checks below are skipped.", "skip" );
                } else {
                    item( "Target Search Query set", true, "\u201c" + query + "\u201d" );
                    item(
                        "Query in Meta Title",
                        titleLower.indexOf( queryLower ) !== -1,
                        title ? "" : "Meta title not set — using post title"
                    );
                    item(
                        "Query in Meta Description",
                        descLower.indexOf( queryLower ) !== -1,
                        desc ? "" : "Meta description not set"
                    );
                    item(
                        "Query in Slug",
                        slugLower.indexOf( queryLower ) !== -1,
                        "Slug: " + slug
                    );
                }

                // ---- META TAGS ----
                section("Meta Tags");
                item( "Meta Title set", !!title,
                    title ? title.length + " characters" : "Using post title as fallback"
                );
                item( "Meta Title length",
                    title ? ( title.length >= 50 && title.length <= 60 ) : null,
                    title
                        ? ( title.length < 50 ? "Too short — aim for 50-60" : title.length > 60 ? "Too long — trim to 60" : "Good length" )
                        : "Set a meta title to check length",
                    title ? "check" : "skip"
                );
                item( "Meta Description set", !!desc,
                    desc ? desc.length + " characters" : "Using site default as fallback"
                );
                item( "Meta Description length",
                    desc ? ( desc.length >= 150 && desc.length <= 160 ) : null,
                    desc
                        ? ( desc.length < 150 ? "Too short — aim for 150-160" : desc.length > 160 ? "Too long — trim to 160" : "Good length" )
                        : "Set a meta description to check length",
                    desc ? "check" : "skip"
                );

                // Canonical — informational only
                info( "Canonical",
                    canonical ? canonical + " (custom)" : "Using default page URL"
                );

                // ---- SLUG ----
                section("Slug");
                if ( slug ) {
                    var slugClean = /^[a-z0-9-]+$/.test( slug );
                    item( "Slug readable", slugClean,
                        slugClean ? slug : "Contains uppercase or special characters: " + slug
                    );
                    item( "Slug not auto-generated",
                        !slug.match(/^page-\d+$/) && !slug.match(/^\d+$/),
                        slug.match(/^page-\d+$/) ? "Looks auto-generated — set a descriptive slug" : slug
                    );
                } else {
                    item( "Slug", null, "Could not read slug", "skip" );
                }

                // ---- SCHEMA ----
                section("Schema");
                item( "Schema JSON-LD set", !!schema,
                    schema ? "Schema present" : "No schema set for this page"
                );

                // ---- OG IMAGE ----
                section("OG Image");
                if ( ogData ) {
                    var ogW    = ogData.width   || 0;
                    var ogH    = ogData.height  || 0;
                    var ogMime = ogData.mime     || "";
                    var ogFile = ogData.filename || ogData.url || "";
                    item( "OG Image set", true, ogFile );
                    item( "OG Image dimensions",
                        ogW >= 1200 && ogH >= 630,
                        ogW && ogH ? ogW + "x" + ogH + "px (min 1200x630)" : "Dimensions unavailable"
                    );
                    item( "OG Image is WebP",
                        ogMime === "image/webp",
                        ogMime === "image/webp" ? "WebP confirmed" : "Format: " + ( ogMime || "unknown" ) + " — WebP recommended"
                    );
                } else {
                    item( "OG Image set", false, "Not set — using featured image or site default as fallback" );
                    item( "OG Image dimensions", null, "Set an OG image to check dimensions", "skip" );
                    item( "OG Image is WebP",    null, "Set an OG image to check format",     "skip" );
                }

                // ---- FEATURED IMAGE ----
                section("Featured Image");
                if ( featUrl ) {
                    var isWebP = featUrl.toLowerCase().indexOf(".webp") !== -1;
                    item( "Featured Image set", true, featUrl.split("/").pop() );
                    item( "Featured Image is WebP", isWebP,
                        isWebP ? "WebP confirmed" : "Not WebP — consider converting for better performance"
                    );
                    item( "Featured Image dimensions", null, "Dimensions require DOM check — V2", "skip" );
                } else {
                    item( "Featured Image set", false, "No featured image set" );
                    item( "Featured Image is WebP",    null, "Set a featured image to check", "skip" );
                    item( "Featured Image dimensions", null, "Set a featured image to check", "skip" );
                }

                // ------------------------------------------------
                // RENDER
                // ------------------------------------------------
                var html = "";
                items.forEach(function(it) {
                    if ( it.section ) {
                        html += "<div class=\"roci-health-section\">" + it.section + "</div>";
                        return;
                    }

                    var icon, cls;
                    if ( it.type === "info" ) {
                        icon = "ℹ️"; cls = "roci-health-info";
                    } else if ( it.type === "skip" || it.pass === null ) {
                        icon = "—"; cls = "roci-health-skip";
                    } else if ( it.pass === true ) {
                        icon = "✅"; cls = "roci-health-pass";
                    } else {
                        icon = "❌"; cls = "roci-health-fail";
                    }

                    html += "<div class=\"roci-health-item\">";
                    html += "<span class=\"roci-health-icon\">" + icon + "</span>";
                    html += "<div>";
                    html += "<div class=\"roci-health-label " + cls + "\">" + it.label + "</div>";
                    if ( it.note ) html += "<div class=\"roci-health-note\">" + it.note + "</div>";
                    html += "</div>";
                    html += "</div>";
                });

                panel.innerHTML = html;
            };

            // ------------------------------------------------
            // WATCH FIELDS FOR CHANGES
            // ------------------------------------------------
            var watchIds = [
                "roci_target_query",
                "roci_meta_title",
                "roci_meta_description",
                "roci_canonical",
                "roci_schema_json"
            ];
            watchIds.forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener("input", window.rociUpdateHealth);
            });

            // Re-fetch slug after save
            var saveBtn = document.querySelector(".editor-post-publish-button, .editor-post-save-draft__button");
            if ( saveBtn ) {
                saveBtn.addEventListener("click", function() {
                    slugFetched  = false;
                    slugFetching = false;
                    setTimeout(function() {
                        fetchSlug(function() {
                            window.rociUpdateHealth();
                        });
                    }, 2000 );
                });
            }

            // Also watch for save via keyboard shortcut
            document.addEventListener("keydown", function(e) {
                if ( (e.ctrlKey || e.metaKey) && e.key === "s" ) {
                    slugFetched  = false;
                    slugFetching = false;
                    setTimeout(function() {
                        fetchSlug(function() {
                            window.rociUpdateHealth();
                        });
                    }, 2000 );
                }
            });

            // Initial fetch and render
            fetchSlug(function() {
                window.rociUpdateHealth();
            });

        });
        </script>
        ',
    );
}