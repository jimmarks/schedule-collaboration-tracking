<?php
/**
 * SEO — Sitemap, robots.txt, and noindex management
 *
 * All configuration is stored in wp_options under 'ftt_seo_settings'.
 * Each WordPress page gets three per-page settings (keyed by page ID):
 *   - in_sitemap   (bool)
 *   - noindex      (bool)
 *   - changefreq   (string: always|hourly|daily|weekly|monthly|yearly|never)
 *   - priority     (string: 0.0 – 1.0)
 *
 * The home page (ID 0) is always included as a special entry.
 *
 * @package Family_Travel_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class FTT_SEO {

    const OPTION_KEY    = 'ftt_seo_settings';
    const NONCE_ACTION  = 'ftt_seo_save';
    const NONCE_FIELD   = 'ftt_seo_nonce';
    const DOWNLOAD_QUERY = 'ftt_download_sitemap';

    // Plugin-created page slugs that default to noindex (auth-required / app pages).
    // Keep this in sync with FTT_Pages::get_page_definitions().
    // Note: 'pricing' is intentionally absent — it is a public marketing page.
    private static $default_noindex_slugs = [
        'ftt-dashboard',
        'ftt-calendar',
        'ftt-events',
        'ftt-manage-events',
        'ftt-register',
        'ftt-login',
        'manage-subscription',
        'checkout-success',
        'checkout-cancel',
        'manage-family',
        'ftt-groups',
        'ftt-profile',
        'ftt-onboarding',
        'ftt-trial-expired',
    ];

    // -------------------------------------------------------------------------
    // Bootstrap
    // -------------------------------------------------------------------------

    public static function init() {
        // WordPress 5.5+ has its own sitemap at /wp-sitemap.xml and redirects
        // /sitemap.xml to it, overriding our custom rewrite rule. Disable it.
        add_filter( 'wp_sitemaps_enabled', '__return_false' );

        add_action( 'init',               [ __CLASS__, 'register_sitemap_rewrite' ] );
        add_action( 'init',               [ __CLASS__, 'register_llmstxt_rewrite' ] );
        add_action( 'template_redirect',  [ __CLASS__, 'serve_sitemap' ],  1 );
        add_action( 'template_redirect',  [ __CLASS__, 'serve_llmstxt' ],  2 );
        add_action( 'admin_post_ftt_save_seo_settings', [ __CLASS__, 'handle_save' ] );
        add_filter( 'robots_txt',         [ __CLASS__, 'filter_robots_txt' ], 10, 2 );
        add_action( 'wp_head',            [ __CLASS__, 'output_canonical' ],    1 );
        add_action( 'wp_head',            [ __CLASS__, 'output_social_meta' ],  2 );
        add_action( 'wp_head',            [ __CLASS__, 'output_json_ld' ],      3 );
        add_action( 'wp_head',            [ __CLASS__, 'output_noindex_meta' ], 4 );
        add_action( 'wp_head',            [ __CLASS__, 'output_sitemap_link' ], 5 );
    }

    // -------------------------------------------------------------------------
    // Settings storage
    // -------------------------------------------------------------------------

    /**
     * Load saved settings. Returns array keyed by page ID (string).
     * ID '0' is reserved for the home page.
     */
    public static function get_settings() {
        return get_option( self::OPTION_KEY, [] );
    }

    /**
     * Get global branding / social / AI crawler settings, merged with defaults.
     */
    public static function get_global_settings() {
        $saved    = self::get_settings();
        $defaults = [
            'site_name'           => get_bloginfo( 'name' ),
            'tagline'             => get_bloginfo( 'description' ),
            'og_image_url'        => '',
            'og_image_square_url' => '',
            'fb_app_id'           => '',
            'tiktok_handle'       => '',
            'twitter_handle'      => '',
            'ai_crawlers'         => 'allow', // allow | block_training | block_all
            'llms_txt'            => '1',
        ];
        $stored = isset( $saved['_global'] ) ? (array) $saved['_global'] : [];
        return array_merge( $defaults, $stored );
    }

    /**
     * Get effective config for one page ID, merging saved values with defaults.
     */
    public static function get_page_config( $page_id, $slug = '' ) {
        $saved    = self::get_settings();
        $key      = (string) $page_id;
        $defaults = self::build_defaults( $slug );
        $stored   = isset( $saved[ $key ] ) ? $saved[ $key ] : [];

        return array_merge( $defaults, $stored );
    }

    /**
     * Determine sensible defaults for a page based on its slug.
     */
    private static function build_defaults( $slug ) {
        $is_app_page = in_array( $slug, self::$default_noindex_slugs, true );

        return [
            'in_sitemap'  => ! $is_app_page,
            'noindex'     => $is_app_page,
            'changefreq'  => 'monthly',
            'priority'    => $is_app_page ? '0.1' : '0.5',
            'description' => '',
            'og_title'    => '',
        ];
    }

    // -------------------------------------------------------------------------
    // Save handler (called from admin-post)
    // -------------------------------------------------------------------------

    public static function handle_save() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Forbidden' );
        }
        check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

        $raw      = isset( $_POST['ftt_seo'] ) ? (array) $_POST['ftt_seo'] : [];
        $settings = [];

        // Home page (ID 0)
        $settings['0'] = self::sanitize_page_entry(
            isset( $raw['0'] ) ? $raw['0'] : []
        );

        // All published pages
        $pages = get_pages( [ 'post_status' => 'publish' ] );
        foreach ( $pages as $page ) {
            $id = (string) $page->ID;
            $settings[ $id ] = self::sanitize_page_entry(
                isset( $raw[ $id ] ) ? $raw[ $id ] : []
            );
        }

        // Global branding / social / AI crawler settings
        $raw_global          = isset( $_POST['ftt_seo_global'] ) ? (array) $_POST['ftt_seo_global'] : [];
        $allowed_ai          = [ 'allow', 'block_training', 'block_all' ];
        $settings['_global'] = [
            'site_name'           => sanitize_text_field( $raw_global['site_name'] ?? get_bloginfo( 'name' ) ),
            'tagline'             => sanitize_text_field( $raw_global['tagline'] ?? '' ),
            'og_image_url'        => esc_url_raw( $raw_global['og_image_url'] ?? '' ),
            'og_image_square_url' => esc_url_raw( $raw_global['og_image_square_url'] ?? '' ),
            'fb_app_id'           => sanitize_text_field( $raw_global['fb_app_id'] ?? '' ),
            'tiktok_handle'       => sanitize_text_field( $raw_global['tiktok_handle'] ?? '' ),
            'twitter_handle'      => sanitize_text_field( $raw_global['twitter_handle'] ?? '' ),
            'ai_crawlers'         => in_array( $raw_global['ai_crawlers'] ?? '', $allowed_ai, true )
                                     ? $raw_global['ai_crawlers'] : 'allow',
            'llms_txt'            => ! empty( $raw_global['llms_txt'] ) ? '1' : '0',
        ];

        update_option( self::OPTION_KEY, $settings );

        wp_redirect( add_query_arg(
            [ 'post_type' => 'ftt_event', 'page' => 'ftt-settings', 'tab' => 'seo', 'seo-saved' => '1' ],
            admin_url( 'edit.php' )
        ) );
        exit;
    }

    private static function sanitize_page_entry( $entry ) {
        $allowed_freqs = [ 'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never' ];
        $allowed_prios = [ '1.0', '0.9', '0.8', '0.7', '0.6', '0.5', '0.4', '0.3', '0.2', '0.1', '0.0' ];

        return [
            'in_sitemap'  => ! empty( $entry['in_sitemap'] ),
            'noindex'     => ! empty( $entry['noindex'] ),
            'changefreq'  => in_array( $entry['changefreq'] ?? '', $allowed_freqs, true )
                             ? $entry['changefreq'] : 'monthly',
            'priority'    => in_array( $entry['priority'] ?? '', $allowed_prios, true )
                             ? $entry['priority'] : '0.5',
            'description' => sanitize_textarea_field( $entry['description'] ?? '' ),
            'og_title'    => sanitize_text_field( $entry['og_title'] ?? '' ),
        ];
    }

    // -------------------------------------------------------------------------
    // Sitemap XML
    // -------------------------------------------------------------------------

    public static function register_sitemap_rewrite() {
        add_rewrite_rule( '^sitemap\.xml$', 'index.php?ftt_sitemap=1', 'top' );
        add_rewrite_tag( '%ftt_sitemap%', '([0-9]+)' );
    }

    /**
     * Serve the sitemap when visiting /sitemap.xml
     * Also handles ?ftt_download_sitemap=1 from the admin download button.
     */
    public static function serve_sitemap() {
        $is_frontend = (bool) get_query_var( 'ftt_sitemap' );
        $is_download = isset( $_GET[ self::DOWNLOAD_QUERY ] )
                       && current_user_can( 'manage_options' )
                       && check_admin_referer( 'ftt_sitemap_download' );

        if ( ! $is_frontend && ! $is_download ) {
            return;
        }

        $xml = self::build_sitemap_xml();

        if ( $is_download ) {
            header( 'Content-Type: application/xml; charset=UTF-8' );
            header( 'Content-Disposition: attachment; filename="sitemap.xml"' );
        } else {
            header( 'Content-Type: application/xml; charset=UTF-8' );
            header( 'X-Robots-Tag: noindex, follow' );
        }

        // phpcs:ignore WordPress.Security.EscapeOutput -- XML output
        echo $xml;
        exit;
    }

    public static function build_sitemap_xml() {
        $settings = self::get_settings();
        $home     = trailingslashit( home_url() );
        $lines    = [];

        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        // Home page
        $home_cfg = self::get_page_config( 0, '' );
        if ( $home_cfg['in_sitemap'] ) {
            $modified = gmdate( 'Y-m-d' );
            $lines[]  = self::sitemap_url_block( $home, $modified, $home_cfg['changefreq'], $home_cfg['priority'] );
        }

        // All published pages
        $pages = get_pages( [ 'post_status' => 'publish' ] );
        foreach ( $pages as $page ) {
            $cfg = self::get_page_config( $page->ID, $page->post_name );
            if ( ! $cfg['in_sitemap'] ) {
                continue;
            }
            $url      = trailingslashit( get_permalink( $page->ID ) );
            $modified = gmdate( 'Y-m-d', strtotime( $page->post_modified_gmt ) );
            $lines[]  = self::sitemap_url_block( $url, $modified, $cfg['changefreq'], $cfg['priority'] );
        }

        $lines[] = '</urlset>';

        return implode( "\n", $lines );
    }

    private static function sitemap_url_block( $url, $lastmod, $changefreq, $priority ) {
        return "\t<url>\n"
            . "\t\t<loc>" . esc_url( $url ) . "</loc>\n"
            . "\t\t<lastmod>" . esc_html( $lastmod ) . "</lastmod>\n"
            . "\t\t<changefreq>" . esc_html( $changefreq ) . "</changefreq>\n"
            . "\t\t<priority>" . esc_html( $priority ) . "</priority>\n"
            . "\t</url>";
    }

    // -------------------------------------------------------------------------
    // robots.txt
    // -------------------------------------------------------------------------

    public static function filter_robots_txt( $output, $public ) {
        $sitemap_url = trailingslashit( home_url() ) . 'sitemap.xml';
        $disallowed  = [];

        // Collect pages marked noindex
        $pages = get_pages( [ 'post_status' => 'publish' ] );
        foreach ( $pages as $page ) {
            $cfg = self::get_page_config( $page->ID, $page->post_name );
            if ( $cfg['noindex'] ) {
                $disallowed[] = '/' . $page->post_name . '/';
            }
        }

        // Always disallow wp-json and wp-admin
        $always_disallow = [ '/wp-json/', '/wp-admin/' ];

        $rules  = "\n# Family Travel Tracker\n";
        $rules .= "User-agent: *\n";

        foreach ( array_merge( $disallowed, $always_disallow ) as $path ) {
            $rules .= 'Disallow: ' . $path . "\n";
        }
        $rules .= "Allow: /wp-admin/admin-ajax.php\n";
        $rules .= "\nSitemap: " . esc_url( $sitemap_url ) . "\n";

        // AI crawler rules based on admin setting
        $global     = self::get_global_settings();
        $ai_setting = $global['ai_crawlers'];

        // Crawlers used to train AI models
        $training_bots = [
            'GPTBot',          // OpenAI training
            'CCBot',           // Common Crawl (feeds many models)
            'anthropic-ai',    // Anthropic / Claude training
            'Google-Extended', // Google Gemini training
            'Bytespider',      // ByteDance / TikTok AI training
            'FacebookBot',     // Meta AI training (distinct from normal FB crawler)
        ];

        // Crawlers used for AI inference / search (not training)
        $inference_bots = [
            'PerplexityBot',
            'YouBot',
            'Applebot-Extended',
        ];

        if ( $ai_setting === 'block_training' ) {
            foreach ( $training_bots as $bot ) {
                $rules .= "\nUser-agent: {$bot}\nDisallow: /\n";
            }
        } elseif ( $ai_setting === 'block_all' ) {
            foreach ( array_merge( $training_bots, $inference_bots ) as $bot ) {
                $rules .= "\nUser-agent: {$bot}\nDisallow: /\n";
            }
        }

        return $output . $rules;
    }

    // -------------------------------------------------------------------------
    // Front-end head tags
    // -------------------------------------------------------------------------

    public static function output_noindex_meta() {
        if ( ! is_singular() ) {
            return;
        }
        global $post;
        if ( ! $post ) {
            return;
        }
        $cfg = self::get_page_config( $post->ID, $post->post_name );
        if ( $cfg['noindex'] ) {
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
            echo '<meta name="googlebot" content="noindex, nofollow">' . "\n";
        }
    }

    public static function output_sitemap_link() {
        $url = trailingslashit( home_url() ) . 'sitemap.xml';
        echo '<link rel="sitemap" type="application/xml" title="Sitemap" href="' . esc_url( $url ) . '">' . "\n";
    }

    // -------------------------------------------------------------------------
    // Canonical URL
    // -------------------------------------------------------------------------

    public static function output_canonical() {
        $url = null;
        if ( is_front_page() || is_home() ) {
            $url = trailingslashit( home_url( '/' ) );
        } elseif ( is_singular() ) {
            global $post;
            if ( $post ) {
                $url = trailingslashit( get_permalink( $post->ID ) );
            }
        }
        if ( $url ) {
            echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
        }
    }

    // -------------------------------------------------------------------------
    // Open Graph, Facebook, TikTok, Twitter/X Cards, and meta description
    // -------------------------------------------------------------------------

    public static function output_social_meta() {
        if ( ! is_singular() && ! is_front_page() ) {
            return;
        }

        $global       = self::get_global_settings();
        $site_name    = $global['site_name'];
        $image_wide   = $global['og_image_url'];        // 1200×630 — Facebook, LinkedIn, Twitter/X
        $image_square = $global['og_image_square_url']; // 1200×1200 — TikTok, Instagram
        $fb_app_id    = $global['fb_app_id'];
        $handle       = $global['twitter_handle'];
        $twitter_site = $handle
                        ? ( strpos( $handle, '@' ) === 0 ? $handle : '@' . $handle )
                        : '';

        if ( is_front_page() || is_home() ) {
            $cfg         = self::get_page_config( 0, '' );
            $title       = ! empty( $cfg['og_title'] ) ? $cfg['og_title'] : $site_name;
            $description = ! empty( $cfg['description'] ) ? $cfg['description'] : $global['tagline'];
            $url         = trailingslashit( home_url( '/' ) );
        } else {
            global $post;
            if ( ! $post ) {
                return;
            }
            $cfg         = self::get_page_config( $post->ID, $post->post_name );
            $title       = ! empty( $cfg['og_title'] ) ? $cfg['og_title'] : $post->post_title;
            $description = $cfg['description'] ?? '';
            $url         = trailingslashit( get_permalink( $post->ID ) );
        }

        // Standard meta description
        if ( $description ) {
            echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
        }

        // Open Graph — read by Facebook, LinkedIn, WhatsApp, Slack, iMessage,
        // Pinterest, and TikTok link previews.
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '">' . "\n";
        if ( $title )       { echo '<meta property="og:title" content="'       . esc_attr( $title )       . '">' . "\n"; }
        if ( $description ) { echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n"; }

        // Landscape image (1.91:1) — primary og:image for Facebook / LinkedIn / Twitter
        if ( $image_wide ) {
            echo '<meta property="og:image" content="'              . esc_url( $image_wide ) . '">' . "\n";
            echo '<meta property="og:image:width" content="1200">'  . "\n";
            echo '<meta property="og:image:height" content="630">'  . "\n";
            echo '<meta property="og:image:type" content="image/png">' . "\n";
        }

        // Square image (1:1) — second og:image entry, preferred by TikTok and Instagram
        if ( $image_square && $image_square !== $image_wide ) {
            echo '<meta property="og:image" content="'              . esc_url( $image_square ) . '">' . "\n";
            echo '<meta property="og:image:width" content="1200">'  . "\n";
            echo '<meta property="og:image:height" content="1200">' . "\n";
            echo '<meta property="og:image:type" content="image/png">' . "\n";
        }

        // Facebook App ID — unlocks Facebook Insights, Social Plugins, and Share Dialog
        if ( $fb_app_id ) {
            echo '<meta property="fb:app_id" content="' . esc_attr( $fb_app_id ) . '">' . "\n";
        }

        // Twitter/X Cards
        $card = $image_wide ? 'summary_large_image' : ( $image_square ? 'summary' : 'summary' );
        echo '<meta name="twitter:card" content="'        . esc_attr( $card )        . '">' . "\n";
        if ( $twitter_site  ) { echo '<meta name="twitter:site" content="'        . esc_attr( $twitter_site  ) . '">' . "\n"; }
        if ( $title         ) { echo '<meta name="twitter:title" content="'       . esc_attr( $title         ) . '">' . "\n"; }
        if ( $description   ) { echo '<meta name="twitter:description" content="' . esc_attr( $description   ) . '">' . "\n"; }
        if ( $image_wide    ) { echo '<meta name="twitter:image" content="'       . esc_url( $image_wide     ) . '">' . "\n"; }
    }

    // -------------------------------------------------------------------------
    // JSON-LD Structured Data (Schema.org)
    // -------------------------------------------------------------------------

    public static function output_json_ld() {
        if ( ! is_front_page() && ! is_singular() ) {
            return;
        }

        $global    = self::get_global_settings();
        $site_name = $global['site_name'];
        $home_url  = trailingslashit( home_url( '/' ) );
        $logo      = $global['og_image_url'];

        // Organization + WebSite schemas on the home page
        if ( is_front_page() || is_home() ) {
            $org = [
                '@context' => 'https://schema.org',
                '@type'    => 'Organization',
                'name'     => $site_name,
                'url'      => $home_url,
            ];
            if ( $logo ) {
                $org['logo'] = $logo;
            }

            $website = [
                '@context'        => 'https://schema.org',
                '@type'           => 'WebSite',
                'name'            => $site_name,
                'url'             => $home_url,
                'potentialAction' => [
                    '@type'       => 'SearchAction',
                    'target'      => [ '@type' => 'EntryPoint', 'urlTemplate' => $home_url . '?s={search_term_string}' ],
                    'query-input' => 'required name=search_term_string',
                ],
            ];

            $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
            echo '<script type="application/ld+json">' . "\n" . wp_json_encode( $org, $flags )     . "\n</script>\n";
            echo '<script type="application/ld+json">' . "\n" . wp_json_encode( $website, $flags ) . "\n</script>\n";
            return;
        }

        // WebPage schema on other singular pages
        global $post;
        if ( ! $post ) {
            return;
        }
        $cfg         = self::get_page_config( $post->ID, $post->post_name );
        $page_title  = ! empty( $cfg['og_title'] ) ? $cfg['og_title'] : $post->post_title;
        $description = $cfg['description'] ?? '';

        $webpage = [
            '@context' => 'https://schema.org',
            '@type'    => 'WebPage',
            'name'     => $page_title,
            'url'      => trailingslashit( get_permalink( $post->ID ) ),
            'isPartOf' => [ '@type' => 'WebSite', 'url' => $home_url ],
        ];
        if ( $description ) {
            $webpage['description'] = $description;
        }

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;
        echo '<script type="application/ld+json">' . "\n" . wp_json_encode( $webpage, $flags ) . "\n</script>\n";
    }

    // -------------------------------------------------------------------------
    // llms.txt — AI-readable site summary (llmstxt.org standard)
    // -------------------------------------------------------------------------

    public static function register_llmstxt_rewrite() {
        add_rewrite_rule( '^llms\.txt$', 'index.php?ftt_llmstxt=1', 'top' );
        add_rewrite_tag( '%ftt_llmstxt%', '([0-9]+)' );
    }

    public static function serve_llmstxt() {
        if ( ! get_query_var( 'ftt_llmstxt' ) ) {
            return;
        }
        $global = self::get_global_settings();
        if ( empty( $global['llms_txt'] ) || $global['llms_txt'] === '0' ) {
            status_header( 404 );
            exit;
        }
        header( 'Content-Type: text/plain; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex, follow' );
        // phpcs:ignore WordPress.Security.EscapeOutput -- plain text output
        echo self::build_llmstxt();
        exit;
    }

    public static function build_llmstxt() {
        $global    = self::get_global_settings();
        $site_name = $global['site_name'];
        $tagline   = $global['tagline'];
        $home      = trailingslashit( home_url() );

        $lines   = [];
        $lines[] = "# {$site_name}";
        if ( $tagline ) {
            $lines[] = '';
            $lines[] = "> {$tagline}";
        }
        $lines[] = '';
        $lines[] = '## About';
        $lines[] = '';
        $lines[] = "{$site_name} is a family travel coordination platform. It helps families plan and track travel for children in performing arts programs, with event scheduling, flight price tracking, and family group management.";
        $lines[] = '';

        // Public (non-noindex) pages
        $public_pages = [];
        $all_pages    = get_pages( [ 'post_status' => 'publish', 'sort_column' => 'menu_order' ] );

        $home_cfg = self::get_page_config( 0, '' );
        if ( ! $home_cfg['noindex'] ) {
            $desc           = ! empty( $home_cfg['description'] ) ? $home_cfg['description'] : $tagline;
            $public_pages[] = [ 'title' => 'Home', 'url' => $home, 'description' => $desc ];
        }
        foreach ( $all_pages as $page ) {
            $cfg = self::get_page_config( $page->ID, $page->post_name );
            if ( ! $cfg['noindex'] ) {
                $public_pages[] = [
                    'title'       => $page->post_title,
                    'url'         => trailingslashit( get_permalink( $page->ID ) ),
                    'description' => $cfg['description'] ?? '',
                ];
            }
        }

        if ( ! empty( $public_pages ) ) {
            $lines[] = '## Public Pages';
            $lines[] = '';
            foreach ( $public_pages as $p ) {
                $entry = "- [{$p['title']}]({$p['url']})";
                if ( ! empty( $p['description'] ) ) {
                    $entry .= ": {$p['description']}";
                }
                $lines[] = $entry;
            }
            $lines[] = '';
        }

        $lines[] = '## App (requires account)';
        $lines[] = '';
        $lines[] = 'The core application requires a registered account. Key features:';
        $lines[] = '';
        $lines[] = '- Event scheduling and calendar management for children in performing arts';
        $lines[] = '- Flight and travel coordination with real-time price tracking';
        $lines[] = '- Family group management and shared calendars';
        $lines[] = '- Price alerts for flights';
        $lines[] = '';
        $lines[] = '## Contact';
        $lines[] = '';
        $lines[] = "Sitemap: {$home}sitemap.xml";

        return implode( "\n", $lines ) . "\n";
    }

    // -------------------------------------------------------------------------
    // Admin settings page renderer (embedded in the SEO tab)
    // -------------------------------------------------------------------------

    public static function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $sitemap_url     = trailingslashit( home_url() ) . 'sitemap.xml';
        $download_url    = wp_nonce_url(
            add_query_arg( self::DOWNLOAD_QUERY, '1', home_url( '/' ) ),
            'ftt_sitemap_download'
        );
        $pages           = get_pages( [ 'post_status' => 'publish', 'sort_column' => 'menu_order' ] );
        $saved_message   = isset( $_GET['seo-saved'] ) && $_GET['seo-saved'] === '1';

        $freq_options    = [ 'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never' ];
        $prio_options    = [ '1.0', '0.9', '0.8', '0.7', '0.6', '0.5', '0.4', '0.3', '0.2', '0.1', '0.0' ];

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'SEO & Sitemap', 'schedule-collaboration-tracking' ); ?></h1>

            <?php if ( $saved_message ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'SEO settings saved.', 'schedule-collaboration-tracking' ); ?></p></div>
            <?php endif; ?>

            <!-- Submission URLs -->
            <div class="ftt-seo-submit-box" style="background:#fff;border:1px solid #c3c4c7;padding:16px 20px;margin:20px 0;border-radius:4px;">
                <h2 style="margin-top:0;"><?php esc_html_e( 'Sitemap URL', 'schedule-collaboration-tracking' ); ?></h2>
                <p style="color:#646970;margin-top:0;"><?php esc_html_e( 'Submit this URL to Google Search Console, Bing Webmaster Tools, and any other search engine you want to appear in.', 'schedule-collaboration-tracking' ); ?></p>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <input type="text" readonly value="<?php echo esc_attr( $sitemap_url ); ?>"
                           style="width:420px;max-width:100%;background:#f6f7f7;border:1px solid #c3c4c7;padding:6px 10px;font-family:monospace;"
                           onclick="this.select();" />
                    <a href="<?php echo esc_url( $sitemap_url ); ?>" target="_blank" class="button button-secondary">
                        <?php esc_html_e( 'Preview', 'schedule-collaboration-tracking' ); ?>
                    </a>
                    <a href="<?php echo esc_url( $download_url ); ?>" class="button button-secondary">
                        ⬇ <?php esc_html_e( 'Download sitemap.xml', 'schedule-collaboration-tracking' ); ?>
                    </a>
                </div>
                <p style="margin-bottom:0;margin-top:12px;font-size:13px;color:#646970;">
                    <?php esc_html_e( 'Google Search Console:', 'schedule-collaboration-tracking' ); ?>
                    <a href="https://search.google.com/search-console" target="_blank">search.google.com/search-console</a>
                    &nbsp;·&nbsp;
                    <?php esc_html_e( 'Bing Webmaster:', 'schedule-collaboration-tracking' ); ?>
                    <a href="https://www.bing.com/webmasters" target="_blank">bing.com/webmasters</a>
                </p>
            </div>

            <!-- Page table -->
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>
                <input type="hidden" name="action" value="ftt_save_seo_settings" />

                <!-- Branding & Social -->
                <?php $g = self::get_global_settings(); ?>
                <h2><?php esc_html_e( 'Branding & Social', 'schedule-collaboration-tracking' ); ?></h2>
                <p style="color:#646970;margin-top:0;"><?php esc_html_e( 'Controls Open Graph, Facebook, TikTok, Twitter/X Cards, and JSON-LD structured data on every page.', 'schedule-collaboration-tracking' ); ?></p>
                <div class="ftt-seo-card">
                    <table class="form-table">
                        <tr>
                            <th><label for="ftt_sg_name"><?php esc_html_e( 'Site Name', 'schedule-collaboration-tracking' ); ?></label></th>
                            <td><input type="text" id="ftt_sg_name" name="ftt_seo_global[site_name]" value="<?php echo esc_attr( $g['site_name'] ); ?>" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th><label for="ftt_sg_tagline"><?php esc_html_e( 'Tagline', 'schedule-collaboration-tracking' ); ?></label></th>
                            <td>
                                <input type="text" id="ftt_sg_tagline" name="ftt_seo_global[tagline]" value="<?php echo esc_attr( $g['tagline'] ); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e( 'Used as og:description and meta description on the home page when no per-page description is set.', 'schedule-collaboration-tracking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="ftt_sg_image"><?php esc_html_e( 'Social Image — Landscape', 'schedule-collaboration-tracking' ); ?></label></th>
                            <td>
                                <input type="url" id="ftt_sg_image" name="ftt_seo_global[og_image_url]" value="<?php echo esc_attr( $g['og_image_url'] ); ?>" class="large-text" placeholder="https://…/og-image-1200x630.png">
                                <p class="description"><?php esc_html_e( '1200×630 px (1.91:1). Primary og:image — used by Facebook, LinkedIn, WhatsApp, Slack, and Twitter/X.', 'schedule-collaboration-tracking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="ftt_sg_image_sq"><?php esc_html_e( 'Social Image — Square', 'schedule-collaboration-tracking' ); ?></label></th>
                            <td>
                                <input type="url" id="ftt_sg_image_sq" name="ftt_seo_global[og_image_square_url]" value="<?php echo esc_attr( $g['og_image_square_url'] ); ?>" class="large-text" placeholder="https://…/og-image-1200x1200.png">
                                <p class="description"><?php esc_html_e( '1200×1200 px (1:1). Optional. Added as a second og:image — preferred by TikTok and Instagram for in-feed link previews. Leave blank to use the landscape image for all platforms.', 'schedule-collaboration-tracking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="ftt_sg_fb"><?php esc_html_e( 'Facebook App ID', 'schedule-collaboration-tracking' ); ?></label></th>
                            <td>
                                <input type="text" id="ftt_sg_fb" name="ftt_seo_global[fb_app_id]" value="<?php echo esc_attr( $g['fb_app_id'] ); ?>" class="regular-text" placeholder="123456789012345">
                                <p class="description">
                                    <?php esc_html_e( 'Optional. Outputs fb:app_id on every page — connects your site to a Facebook App for Sharing Insights, Social Plugins, and the Share Dialog. Get your App ID at ', 'schedule-collaboration-tracking' ); ?>
                                    <a href="https://developers.facebook.com/apps/" target="_blank">developers.facebook.com/apps</a>.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="ftt_sg_tiktok"><?php esc_html_e( 'TikTok Handle', 'schedule-collaboration-tracking' ); ?></label></th>
                            <td>
                                <input type="text" id="ftt_sg_tiktok" name="ftt_seo_global[tiktok_handle]" value="<?php echo esc_attr( $g['tiktok_handle'] ); ?>" class="regular-text" placeholder="@yourbrand">
                                <p class="description"><?php esc_html_e( 'Your TikTok account handle. TikTok reads og:* tags for link previews — no separate meta tags are needed. The square image above gives the best in-feed appearance.', 'schedule-collaboration-tracking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="ftt_sg_twitter"><?php esc_html_e( 'Twitter/X Handle', 'schedule-collaboration-tracking' ); ?></label></th>
                            <td><input type="text" id="ftt_sg_twitter" name="ftt_seo_global[twitter_handle]" value="<?php echo esc_attr( $g['twitter_handle'] ); ?>" class="regular-text" placeholder="@yourbrand"></td>
                        </tr>
                    </table>
                </div>

                <!-- AI Crawlers & llms.txt -->
                <h2><?php esc_html_e( 'AI Crawlers & LLMs', 'schedule-collaboration-tracking' ); ?></h2>
                <p style="color:#646970;margin-top:0;"><?php esc_html_e( 'Control how AI systems interact with your site.', 'schedule-collaboration-tracking' ); ?></p>
                <div class="ftt-seo-card">
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'AI Training Crawlers', 'schedule-collaboration-tracking' ); ?></th>
                            <td>
                                <fieldset>
                                    <label><input type="radio" name="ftt_seo_global[ai_crawlers]" value="allow" <?php checked( $g['ai_crawlers'], 'allow' ); ?>> <?php esc_html_e( 'Allow all AI crawlers (default)', 'schedule-collaboration-tracking' ); ?></label><br>
                                    <label><input type="radio" name="ftt_seo_global[ai_crawlers]" value="block_training" <?php checked( $g['ai_crawlers'], 'block_training' ); ?>> <?php esc_html_e( 'Block training crawlers only — GPTBot, CCBot, anthropic-ai, Google-Extended, Bytespider, FacebookBot', 'schedule-collaboration-tracking' ); ?></label><br>
                                    <label><input type="radio" name="ftt_seo_global[ai_crawlers]" value="block_all" <?php checked( $g['ai_crawlers'], 'block_all' ); ?>> <?php esc_html_e( 'Block all AI crawlers including inference — also blocks PerplexityBot, YouBot, Applebot-Extended', 'schedule-collaboration-tracking' ); ?></label>
                                </fieldset>
                                <p class="description"><?php esc_html_e( 'Adds per-bot Disallow: / rules to robots.txt automatically.', 'schedule-collaboration-tracking' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="ftt_sg_llms"><?php esc_html_e( 'Publish llms.txt', 'schedule-collaboration-tracking' ); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="ftt_sg_llms" name="ftt_seo_global[llms_txt]" value="1" <?php checked( $g['llms_txt'], '1' ); ?>>
                                    <?php esc_html_e( 'Enable /llms.txt (llmstxt.org standard — lists public pages for AI assistants to understand your site)', 'schedule-collaboration-tracking' ); ?>
                                </label>
                                <?php $llms_url = trailingslashit( home_url() ) . 'llms.txt'; ?>
                                <p class="description">
                                    <?php if ( $g['llms_txt'] === '1' ) : ?>
                                        <a href="<?php echo esc_url( $llms_url ); ?>" target="_blank"><?php echo esc_html( $llms_url ); ?></a>
                                    <?php else : ?>
                                        <?php echo esc_html( $llms_url ); ?> <?php esc_html_e( '(currently disabled)', 'schedule-collaboration-tracking' ); ?>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <h2><?php esc_html_e( 'Page Settings', 'schedule-collaboration-tracking' ); ?></h2>
                <p style="color:#646970;">
                    <?php esc_html_e( 'Control which pages appear in your sitemap and which are blocked from search engines. Add a meta description for public pages to improve SERP click-through and social sharing.', 'schedule-collaboration-tracking' ); ?>
                </p>

                <style>
                .ftt-seo-table { border-collapse:collapse; width:100%; }
                .ftt-seo-table th { background:#f6f7f7; padding:10px 12px; text-align:left; border-bottom:2px solid #c3c4c7; font-size:13px; }
                .ftt-seo-table td { padding:9px 12px; border-bottom:1px solid #f0f0f0; vertical-align:top; font-size:13px; }
                .ftt-seo-table tr:hover td { background:#fafafa; }
                .ftt-seo-table .col-page { width:22%; }
                .ftt-seo-table .col-sitemap { width:9%; text-align:center; }
                .ftt-seo-table .col-noindex { width:9%; text-align:center; }
                .ftt-seo-table .col-freq { width:14%; }
                .ftt-seo-table .col-prio { width:10%; }
                .ftt-seo-table .col-desc { width:36%; }
                .ftt-seo-desc-input { width:100%;font-size:12px;padding:4px 6px;box-sizing:border-box;resize:vertical; }
                .ftt-seo-og-input { width:100%;font-size:12px;padding:4px 6px;box-sizing:border-box;margin-top:4px; }
                .ftt-seo-card { background:#fff;border:1px solid #c3c4c7;padding:20px 24px;margin:0 0 24px;border-radius:4px; }
                .ftt-seo-card h3 { margin-top:0;margin-bottom:14px;font-size:14px; }
                .ftt-seo-card .form-table th { width:190px;padding:8px 10px 8px 0;font-size:13px; }
                .ftt-seo-card .form-table td { padding:6px 0;font-size:13px; }
                </style>

                <table class="ftt-seo-table widefat">
                    <thead>
                        <tr>
                            <th class="col-page"><?php esc_html_e( 'Page', 'schedule-collaboration-tracking' ); ?></th>
                            <th class="col-sitemap"><?php esc_html_e( 'In Sitemap', 'schedule-collaboration-tracking' ); ?></th>
                            <th class="col-noindex"><?php esc_html_e( 'Noindex', 'schedule-collaboration-tracking' ); ?></th>
                            <th class="col-freq"><?php esc_html_e( 'Change Freq', 'schedule-collaboration-tracking' ); ?></th>
                            <th class="col-prio"><?php esc_html_e( 'Priority', 'schedule-collaboration-tracking' ); ?></th>
                            <th class="col-desc"><?php esc_html_e( 'Meta Description &amp; OG Title Override', 'schedule-collaboration-tracking' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php
                        // Home row (ID 0)
                        $home_cfg = self::get_page_config( 0, '' );
                        self::render_page_row( 0, __( 'Home Page', 'schedule-collaboration-tracking' ), home_url('/'), '', $home_cfg, $freq_options, $prio_options );

                        // All other published pages
                        foreach ( $pages as $page ) :
                            $cfg = self::get_page_config( $page->ID, $page->post_name );
                            self::render_page_row( $page->ID, $page->post_title, get_permalink( $page->ID ), $page->post_name, $cfg, $freq_options, $prio_options );
                        endforeach;
                        ?>
                    </tbody>
                </table>

                <p style="margin-top:20px;">
                    <?php submit_button( __( 'Save SEO Settings', 'schedule-collaboration-tracking' ), 'primary', 'submit', false ); ?>
                </p>
            </form>

            <!-- robots.txt preview -->
            <h2 style="margin-top:30px;"><?php esc_html_e( 'robots.txt Preview', 'schedule-collaboration-tracking' ); ?></h2>
            <p style="color:#646970;margin-top:0;">
                <?php esc_html_e( 'Read-only. This is what search engine crawlers see at ', 'schedule-collaboration-tracking' ); ?>
                <a href="<?php echo esc_url( home_url('/robots.txt') ); ?>" target="_blank"><?php echo esc_html( home_url('/robots.txt') ); ?></a>
            </p>
            <textarea readonly rows="18" style="width:100%;font-family:monospace;font-size:12px;background:#f6f7f7;border:1px solid #c3c4c7;padding:10px;resize:vertical;"><?php
                // Simulate what WordPress outputs + our filter
                $simulated = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n";
                $simulated = apply_filters( 'robots_txt', $simulated, '1' );
                echo esc_textarea( $simulated );
            ?></textarea>
        </div>
        <?php
    }

    private static function render_page_row( $id, $title, $url, $slug, $cfg, $freq_options, $prio_options ) {
        $field_prefix = 'ftt_seo[' . esc_attr( $id ) . ']';
        $rel_url      = str_replace( home_url(), '', $url );
        ?>
        <tr>
            <td class="col-page">
                <strong><?php echo esc_html( $title ); ?></strong><br>
                <span style="color:#646970;font-size:12px;"><?php echo esc_html( $rel_url ?: '/' ); ?></span>
            </td>
            <td class="col-sitemap" style="text-align:center;">
                <input type="checkbox"
                       name="<?php echo $field_prefix; ?>[in_sitemap]"
                       value="1"
                       <?php checked( $cfg['in_sitemap'] ); ?> />
            </td>
            <td class="col-noindex" style="text-align:center;">
                <input type="checkbox"
                       name="<?php echo $field_prefix; ?>[noindex]"
                       value="1"
                       <?php checked( $cfg['noindex'] ); ?> />
            </td>
            <td class="col-freq">
                <select name="<?php echo $field_prefix; ?>[changefreq]" style="width:100%;">
                    <?php foreach ( $freq_options as $f ) : ?>
                        <option value="<?php echo esc_attr( $f ); ?>" <?php selected( $cfg['changefreq'], $f ); ?>><?php echo esc_html( ucfirst( $f ) ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td class="col-prio">
                <select name="<?php echo $field_prefix; ?>[priority]" style="width:100%;">
                    <?php foreach ( $prio_options as $p ) : ?>
                        <option value="<?php echo esc_attr( $p ); ?>" <?php selected( $cfg['priority'], $p ); ?>><?php echo esc_html( $p ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td class="col-desc">
                <textarea class="ftt-seo-desc-input" rows="2"
                          name="<?php echo $field_prefix; ?>[description]"
                          placeholder="<?php esc_attr_e( '155-char description for SERP snippets and social shares…', 'schedule-collaboration-tracking' ); ?>"
                ><?php echo esc_textarea( $cfg['description'] ?? '' ); ?></textarea>
                <input type="text" class="ftt-seo-og-input"
                       name="<?php echo $field_prefix; ?>[og_title]"
                       value="<?php echo esc_attr( $cfg['og_title'] ?? '' ); ?>"
                       placeholder="<?php esc_attr_e( 'OG title override (leave blank = page title)', 'schedule-collaboration-tracking' ); ?>">
            </td>
        </tr>
        <?php
    }
}
