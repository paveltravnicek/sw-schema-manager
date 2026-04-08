<?php
/**
 * Plugin Name: Strukturovaná data
 * Description: Automatická a ruční správa strukturovaných dat (JSON-LD) pro WordPress + globální data firmy + kombinovatelné presety.
 * Version: 1.0
 * Author: Smart Websites
 * Author URI: https://smart-websites.cz
 * Update URI: https://github.com/paveltravnicek/sw-schema-manager/
 * Text Domain: sw-schema-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('SW_SCHEMA_MANAGER_VERSION')) {
    define('SW_SCHEMA_MANAGER_VERSION', '1.0');
}

final class SW_Schema_Manager {
    const OPTION_GLOBAL    = 'sw_schema_global';
    const OPTION_AUTOMATIC = 'sw_schema_automatic';
    const NONCE_SETTINGS   = 'sw_schema_save_settings';
    const NONCE_META       = 'sw_schema_save_meta';
    const SUBMENU_SLUG     = 'sw-schema-manager';

    const META_MODE     = '_sw_schema_mode';
    const META_CUSTOM   = '_sw_schema_custom_json';
    const META_PRESETS  = '_sw_schema_presets';
    const META_SERVICES = '_sw_schema_services';
    const META_FAQS     = '_sw_schema_faqs';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('add_meta_boxes', [$this, 'register_meta_box']);
        add_action('save_post', [$this, 'save_post_meta']);
        add_action('wp_head', [$this, 'output_schema'], 2);

        add_filter('manage_pages_columns', [$this, 'add_schema_column']);
        add_filter('manage_posts_columns', [$this, 'add_schema_column']);
        add_action('manage_pages_custom_column', [$this, 'render_schema_column'], 10, 2);
        add_action('manage_posts_custom_column', [$this, 'render_schema_column'], 10, 2);
    }

    public function register_menu() {
        add_submenu_page(
            'edit.php?post_type=page',
            __('Strukturovaná data', 'sw-schema-manager'),
            __('Strukturovaná data', 'sw-schema-manager'),
            'manage_options',
            self::SUBMENU_SLUG,
            [$this, 'render_settings_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $screen_id = $screen && isset($screen->id) ? $screen->id : '';
        $is_plugin_screen = (
            $hook === 'page_' . self::SUBMENU_SLUG ||
            $hook === 'page_page_' . self::SUBMENU_SLUG ||
            $hook === 'page_edit-pages_' . self::SUBMENU_SLUG ||
            $hook === 'pages_page_' . self::SUBMENU_SLUG ||
            $screen_id === 'page_' . self::SUBMENU_SLUG ||
            $screen_id === 'pages_page_' . self::SUBMENU_SLUG
        );
        $is_edit_screen = $screen && in_array($screen->base, ['post', 'page'], true);

        if (!$is_plugin_screen && !$is_edit_screen) {
            return;
        }

        wp_enqueue_style(
            'sw-schema-manager-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            [],
            '1.3.2'
        );

        wp_enqueue_script(
            'sw-schema-manager-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            [],
            '1.3.2',
            true
        );

        wp_localize_script('sw-schema-manager-admin', 'swSchemaAdmin', [
            'serviceName'        => __('Název služby', 'sw-schema-manager'),
            'serviceDescription' => __('Krátký popis služby', 'sw-schema-manager'),
            'servicePrice'       => __('Cena', 'sw-schema-manager'),
            'serviceCurrency'    => __('Měna', 'sw-schema-manager'),
            'faqQuestion'        => __('Otázka', 'sw-schema-manager'),
            'faqAnswer'          => __('Odpověď', 'sw-schema-manager'),
            'removeLabel'        => __('Odebrat', 'sw-schema-manager'),
        ]);
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Nemáte oprávnění.', 'sw-schema-manager'));
        }

        if (isset($_POST['sw_schema_save_settings']) && check_admin_referer(self::NONCE_SETTINGS)) {
            $this->save_settings();
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Nastavení bylo uloženo.', 'sw-schema-manager') . '</p></div>';
        }

        $global = $this->get_global_settings();
        $automatic = $this->get_automatic_settings();
        ?>
        <div class="wrap sw-schema-admin">
            <div class="sw-plugin-header">
                <div class="sw-plugin-header__inner">
                    <div class="sw-plugin-header__top">
                        <div class="sw-plugin-header__eyebrow"><?php echo esc_html__('Smart Websites', 'sw-schema-manager'); ?></div>
                        <div class="sw-plugin-header__version"><?php echo esc_html__('Verze', 'sw-schema-manager'); ?> <?php echo esc_html(SW_SCHEMA_MANAGER_VERSION); ?></div>
                    </div>
                    <h1><?php echo esc_html__('Strukturovaná data', 'sw-schema-manager'); ?></h1>
                    <p><?php echo esc_html__('Centrální správa automatických structured data, firemních údajů a ručních doplnění pro konkrétní stránky.', 'sw-schema-manager'); ?></p>
                </div>
            </div>

            <form method="post">
                <?php wp_nonce_field(self::NONCE_SETTINGS); ?>

                <div class="sw-schema-stack">
                    <section class="sw-schema-card">
                        <div class="sw-schema-card__header">
                            <h2><?php echo esc_html__('Údaje o firmě', 'sw-schema-manager'); ?></h2>
                        </div>

                        <div class="sw-schema-fields sw-schema-fields--2">
                            <div class="sw-schema-field sw-schema-field--full">
                                <label for="sw_schema_name"><?php echo esc_html__('Název firmy', 'sw-schema-manager'); ?></label>
                                <input type="text" id="sw_schema_name" name="global[name]" value="<?php echo esc_attr($global['name']); ?>">
                            </div>

                            <div class="sw-schema-field">
                                <label for="sw_schema_url"><?php echo esc_html__('URL webu', 'sw-schema-manager'); ?></label>
                                <input type="url" id="sw_schema_url" name="global[url]" value="<?php echo esc_attr($global['url']); ?>">
                            </div>

                            <div class="sw-schema-field">
                                <label for="sw_schema_logo"><?php echo esc_html__('URL loga', 'sw-schema-manager'); ?></label>
                                <input type="url" id="sw_schema_logo" name="global[logo]" value="<?php echo esc_attr($global['logo']); ?>">
                            </div>

                            <div class="sw-schema-field">
                                <label for="sw_schema_email"><?php echo esc_html__('E-mail', 'sw-schema-manager'); ?></label>
                                <input type="email" id="sw_schema_email" name="global[email]" value="<?php echo esc_attr($global['email']); ?>">
                            </div>

                            <div class="sw-schema-field">
                                <label for="sw_schema_phone"><?php echo esc_html__('Telefon', 'sw-schema-manager'); ?></label>
                                <input type="text" id="sw_schema_phone" name="global[phone]" value="<?php echo esc_attr($global['phone']); ?>">
                            </div>

                            <div class="sw-schema-field">
                                <label for="sw_schema_ico"><?php echo esc_html__('IČ', 'sw-schema-manager'); ?></label>
                                <input type="text" id="sw_schema_ico" name="global[ico]" value="<?php echo esc_attr($global['ico']); ?>">
                            </div>

                            <div class="sw-schema-field">
                                <label for="sw_schema_dic"><?php echo esc_html__('DIČ', 'sw-schema-manager'); ?></label>
                                <input type="text" id="sw_schema_dic" name="global[dic]" value="<?php echo esc_attr($global['dic']); ?>">
                            </div>

                            <div class="sw-schema-field">
                                <label for="sw_schema_country"><?php echo esc_html__('Země', 'sw-schema-manager'); ?></label>
                                <input type="text" id="sw_schema_country" name="global[country]" value="<?php echo esc_attr($global['country']); ?>" placeholder="CZ">
                            </div>

                            <div class="sw-schema-field">
                                <label for="sw_schema_street"><?php echo esc_html__('Ulice a č.p.', 'sw-schema-manager'); ?></label>
                                <input type="text" id="sw_schema_street" name="global[street]" value="<?php echo esc_attr($global['street']); ?>">
                            </div>

                            <div class="sw-schema-field">
                                <label for="sw_schema_city"><?php echo esc_html__('Město', 'sw-schema-manager'); ?></label>
                                <input type="text" id="sw_schema_city" name="global[city]" value="<?php echo esc_attr($global['city']); ?>">
                            </div>

                            <div class="sw-schema-field">
                                <label for="sw_schema_postal"><?php echo esc_html__('PSČ', 'sw-schema-manager'); ?></label>
                                <input type="text" id="sw_schema_postal" name="global[postal]" value="<?php echo esc_attr($global['postal']); ?>">
                            </div>
                        </div>
                    </section>

                    <section class="sw-schema-card">
                        <div class="sw-schema-card__header">
                            <h2><?php echo esc_html__('Údaje o webu', 'sw-schema-manager'); ?></h2>
                        </div>

                        <div class="sw-schema-fields sw-schema-fields--2">
                            <div class="sw-schema-field">
                                <label for="sw_schema_entity_type"><?php echo esc_html__('Typ entity', 'sw-schema-manager'); ?></label>
                                <select id="sw_schema_entity_type" name="global[entity_type]">
                                    <?php
                                    $entity_types = [
                                        'Organization' => 'Organization',
                                        'LocalBusiness' => 'LocalBusiness',
                                        'ProfessionalService' => 'ProfessionalService',
                                    ];
                                    foreach ($entity_types as $value => $label) {
                                        echo '<option value="' . esc_attr($value) . '"' . selected($global['entity_type'], $value, false) . '>' . esc_html($label) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="sw-schema-field">
                                <label for="sw_schema_language"><?php echo esc_html__('Jazyk', 'sw-schema-manager'); ?></label>
                                <input type="text" id="sw_schema_language" name="global[language]" value="<?php echo esc_attr($global['language']); ?>" placeholder="cs-CZ">
                            </div>
                        </div>
                    </section>

                    <section class="sw-schema-card">
                        <div class="sw-schema-card__header">
                            <h2><?php echo esc_html__('Sociální sítě', 'sw-schema-manager'); ?></h2>
                        </div>

                        <div class="sw-schema-fields sw-schema-fields--2">
                            <div class="sw-schema-field">
                                <label for="sw_schema_facebook"><?php echo esc_html__('Facebook URL', 'sw-schema-manager'); ?></label>
                                <input type="url" id="sw_schema_facebook" name="global[facebook]" value="<?php echo esc_attr($global['facebook']); ?>">
                            </div>

                            <div class="sw-schema-field">
                                <label for="sw_schema_instagram"><?php echo esc_html__('Instagram URL', 'sw-schema-manager'); ?></label>
                                <input type="url" id="sw_schema_instagram" name="global[instagram]" value="<?php echo esc_attr($global['instagram']); ?>">
                            </div>

                            <div class="sw-schema-field">
                                <label for="sw_schema_linkedin"><?php echo esc_html__('LinkedIn URL', 'sw-schema-manager'); ?></label>
                                <input type="url" id="sw_schema_linkedin" name="global[linkedin]" value="<?php echo esc_attr($global['linkedin']); ?>">
                            </div>
                        </div>
                    </section>

                    <section class="sw-schema-card">
                        <div class="sw-schema-card__header">
                            <h2><?php echo esc_html__('Automatické schéma', 'sw-schema-manager'); ?></h2>
                            <p><?php echo esc_html__('Výběr automaticky generovaných structured data. Na konkrétní stránce lze chování samostatně upravit.', 'sw-schema-manager'); ?></p>
                        </div>

                        <div class="sw-schema-checklist">
                            <label><input type="checkbox" name="automatic[homepage_website]" value="1" <?php checked($automatic['homepage_website'], 1); ?>> <strong>WebSite na homepage</strong><span>Výchozí schéma pro titulní stránku webu.</span></label>
                            <label><input type="checkbox" name="automatic[homepage_org]" value="1" <?php checked($automatic['homepage_org'], 1); ?>> <strong>Organization / LocalBusiness na homepage</strong><span>Firma nebo značka postavená z globálních údajů.</span></label>
                            <label><input type="checkbox" name="automatic[single_article]" value="1" <?php checked($automatic['single_article'], 1); ?>> <strong>BlogPosting u příspěvků</strong><span>Automatické schéma pro články a blogové příspěvky.</span></label>
                            <label><input type="checkbox" name="automatic[page_webpage]" value="1" <?php checked($automatic['page_webpage'], 1); ?>> <strong>WebPage u stránek</strong><span>Základní schéma pro běžné obsahové stránky.</span></label>
                            <label><input type="checkbox" name="automatic[archives_collection]" value="1" <?php checked($automatic['archives_collection'], 1); ?>> <strong>CollectionPage u archivů</strong><span>Blog, kategorie, štítky a další archivy.</span></label>
                        </div>
                    </section>

                    <section class="sw-schema-card">
                        <div class="sw-schema-card__header">
                            <h2><?php echo esc_html__('Jak plugin funguje', 'sw-schema-manager'); ?></h2>
                        </div>

                        <div class="sw-schema-info-grid">
                            <div class="sw-schema-info">
                                <h3><?php echo esc_html__('Automaticky', 'sw-schema-manager'); ?></h3>
                                <p><?php echo esc_html__('Použijí se jen automatická schémata pluginu.', 'sw-schema-manager'); ?></p>
                            </div>
                            <div class="sw-schema-info">
                                <h3><?php echo esc_html__('Automaticky + vlastní', 'sw-schema-manager'); ?></h3>
                                <p><?php echo esc_html__('Automatika zůstane a navíc se přidají vybrané presety a případné vlastní JSON-LD.', 'sw-schema-manager'); ?></p>
                            </div>
                            <div class="sw-schema-info">
                                <h3><?php echo esc_html__('Jen vlastní', 'sw-schema-manager'); ?></h3>
                                <p><?php echo esc_html__('Vypne se automatika a použijí se jen ručně zadané presety a vlastní JSON-LD.', 'sw-schema-manager'); ?></p>
                            </div>
                            <div class="sw-schema-info">
                                <h3><?php echo esc_html__('Vypnuto', 'sw-schema-manager'); ?></h3>
                                <p><?php echo esc_html__('Plugin pro danou stránku nevypíše žádná structured data.', 'sw-schema-manager'); ?></p>
                            </div>
                        </div>
                    </section>
                </div>

                <p class="submit">
                    <button type="submit" name="sw_schema_save_settings" class="button button-primary button-large"><?php echo esc_html__('Uložit nastavení', 'sw-schema-manager'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    private function save_settings() {
        $global = isset($_POST['global']) && is_array($_POST['global']) ? wp_unslash($_POST['global']) : [];
        $automatic = isset($_POST['automatic']) && is_array($_POST['automatic']) ? wp_unslash($_POST['automatic']) : [];

        $clean_global = [
            'name'        => sanitize_text_field($global['name'] ?? ''),
            'entity_type' => sanitize_text_field($global['entity_type'] ?? 'Organization'),
            'url'         => esc_url_raw($global['url'] ?? home_url('/')),
            'language'    => sanitize_text_field($global['language'] ?? 'cs-CZ'),
            'logo'        => esc_url_raw($global['logo'] ?? ''),
            'email'       => sanitize_email($global['email'] ?? ''),
            'phone'       => sanitize_text_field($global['phone'] ?? ''),
            'ico'         => sanitize_text_field($global['ico'] ?? ''),
            'dic'         => sanitize_text_field($global['dic'] ?? ''),
            'street'      => sanitize_text_field($global['street'] ?? ''),
            'city'        => sanitize_text_field($global['city'] ?? ''),
            'postal'      => sanitize_text_field($global['postal'] ?? ''),
            'country'     => sanitize_text_field($global['country'] ?? ''),
            'facebook'    => esc_url_raw($global['facebook'] ?? ''),
            'instagram'   => esc_url_raw($global['instagram'] ?? ''),
            'linkedin'    => esc_url_raw($global['linkedin'] ?? ''),
        ];

        $clean_automatic = [
            'homepage_website'    => !empty($automatic['homepage_website']) ? 1 : 0,
            'homepage_org'        => !empty($automatic['homepage_org']) ? 1 : 0,
            'single_article'      => !empty($automatic['single_article']) ? 1 : 0,
            'page_webpage'        => !empty($automatic['page_webpage']) ? 1 : 0,
            'archives_collection' => !empty($automatic['archives_collection']) ? 1 : 0,
        ];

        update_option(self::OPTION_GLOBAL, $clean_global);
        update_option(self::OPTION_AUTOMATIC, $clean_automatic);
    }

    private function get_global_settings() {
        $defaults = [
            'name'        => get_bloginfo('name'),
            'entity_type' => 'Organization',
            'url'         => home_url('/'),
            'language'    => 'cs-CZ',
            'logo'        => '',
            'email'       => '',
            'phone'       => '',
            'ico'         => '',
            'dic'         => '',
            'street'      => '',
            'city'        => '',
            'postal'      => '',
            'country'     => 'CZ',
            'facebook'    => '',
            'instagram'   => '',
            'linkedin'    => '',
        ];

        $stored = get_option(self::OPTION_GLOBAL, []);
        return wp_parse_args(is_array($stored) ? $stored : [], $defaults);
    }

    private function get_automatic_settings() {
        $defaults = [
            'homepage_website'    => 1,
            'homepage_org'        => 1,
            'single_article'      => 1,
            'page_webpage'        => 1,
            'archives_collection' => 1,
        ];
        $stored = get_option(self::OPTION_AUTOMATIC, []);
        return wp_parse_args(is_array($stored) ? $stored : [], $defaults);
    }

    public function register_meta_box() {
        foreach (['post', 'page'] as $post_type) {
            add_meta_box(
                'sw-schema-manager',
                __('Strukturovaná data', 'sw-schema-manager'),
                [$this, 'render_meta_box'],
                $post_type,
                'normal',
                'high'
            );
        }
    }

    public function render_meta_box($post) {
        wp_nonce_field(self::NONCE_META, 'sw_schema_meta_nonce');

        $mode = get_post_meta($post->ID, self::META_MODE, true);
        $mode = $mode ?: 'auto';

        $presets = get_post_meta($post->ID, self::META_PRESETS, true);
        $presets = is_array($presets) ? $presets : [];

        $services = get_post_meta($post->ID, self::META_SERVICES, true);
        $services = is_array($services) ? $services : [];

        $faqs = get_post_meta($post->ID, self::META_FAQS, true);
        $faqs = is_array($faqs) ? $faqs : [];

        $custom_json = (string) get_post_meta($post->ID, self::META_CUSTOM, true);
        ?>
        <div class="sw-schema-meta">
            <section class="sw-schema-card sw-schema-card--meta">
                <div class="sw-schema-card__header">
                    <h2><?php echo esc_html__('Režim výstupu', 'sw-schema-manager'); ?></h2>
                    <p><?php echo esc_html__('Výběr režimu, ve kterém se mají structured data pro tuto stránku generovat.', 'sw-schema-manager'); ?></p>
                </div>

                <div class="sw-schema-mode-grid">
                    <label class="sw-schema-choice">
                        <input type="radio" name="sw_schema_mode" value="auto" <?php checked($mode, 'auto'); ?>>
                        <span>
                            <strong><?php echo esc_html__('Automaticky', 'sw-schema-manager'); ?></strong>
                            <small><?php echo esc_html__('Použijí se jen automatická schémata pluginu.', 'sw-schema-manager'); ?></small>
                        </span>
                    </label>

                    <label class="sw-schema-choice">
                        <input type="radio" name="sw_schema_mode" value="extend" <?php checked($mode, 'extend'); ?>>
                        <span>
                            <strong><?php echo esc_html__('Automaticky + vlastní', 'sw-schema-manager'); ?></strong>
                            <small><?php echo esc_html__('Automatika zůstane a navíc se přidají vybrané presety a vlastní JSON-LD.', 'sw-schema-manager'); ?></small>
                        </span>
                    </label>

                    <label class="sw-schema-choice">
                        <input type="radio" name="sw_schema_mode" value="custom" <?php checked($mode, 'custom'); ?>>
                        <span>
                            <strong><?php echo esc_html__('Jen vlastní', 'sw-schema-manager'); ?></strong>
                            <small><?php echo esc_html__('Použijí se jen vybrané presety a vlastní JSON-LD bez automatiky.', 'sw-schema-manager'); ?></small>
                        </span>
                    </label>

                    <label class="sw-schema-choice">
                        <input type="radio" name="sw_schema_mode" value="off" <?php checked($mode, 'off'); ?>>
                        <span>
                            <strong><?php echo esc_html__('Vypnuto', 'sw-schema-manager'); ?></strong>
                            <small><?php echo esc_html__('Tento plugin pro tuto stránku nevypíše žádná structured data.', 'sw-schema-manager'); ?></small>
                        </span>
                    </label>
                </div>
            </section>

            <section class="sw-schema-card sw-schema-card--meta">
                <div class="sw-schema-card__header">
                    <h2><?php echo esc_html__('Presety', 'sw-schema-manager'); ?></h2>
                    <p><?php echo esc_html__('Presety lze kombinovat. Na jedné stránce tedy může být například Služby + ceny i FAQ.', 'sw-schema-manager'); ?></p>
                </div>

                <div class="sw-schema-preset-grid">
                    <label class="sw-schema-choice">
                        <input type="checkbox" name="sw_schema_presets[]" value="service_catalog" <?php checked(in_array('service_catalog', $presets, true)); ?>>
                        <span>
                            <strong><?php echo esc_html__('Služby + ceny', 'sw-schema-manager'); ?></strong>
                            <small><?php echo esc_html__('Vhodné pro stránky s více službami, jejich popisy a cenami.', 'sw-schema-manager'); ?></small>
                        </span>
                    </label>

                    <label class="sw-schema-choice">
                        <input type="checkbox" name="sw_schema_presets[]" value="faq" <?php checked(in_array('faq', $presets, true)); ?>>
                        <span>
                            <strong><?php echo esc_html__('FAQ', 'sw-schema-manager'); ?></strong>
                            <small><?php echo esc_html__('Otázky a odpovědi ze sekce nejčastějších dotazů.', 'sw-schema-manager'); ?></small>
                        </span>
                    </label>

                    <label class="sw-schema-choice">
                        <input type="checkbox" name="sw_schema_presets[]" value="business" <?php checked(in_array('business', $presets, true)); ?>>
                        <span>
                            <strong><?php echo esc_html__('Firma / provozovna', 'sw-schema-manager'); ?></strong>
                            <small><?php echo esc_html__('Použije globální údaje firmy včetně IČ, DIČ, e-mailu, telefonu a sociálních sítí.', 'sw-schema-manager'); ?></small>
                        </span>
                    </label>
                </div>
            </section>

            <section class="sw-schema-card sw-schema-card--meta">
                <div class="sw-schema-card__header">
                    <h2><?php echo esc_html__('Služby a ceny', 'sw-schema-manager'); ?></h2>
                    <p><?php echo esc_html__('Každá položka může mít vlastní název, popis, cenu i měnu. Použije se pouze při aktivním presetu „Služby + ceny“.', 'sw-schema-manager'); ?></p>
                </div>

                <div id="sw-schema-services" class="sw-schema-repeater">
                    <?php
                    if (empty($services)) {
                        $services[] = ['name' => '', 'description' => '', 'price' => '', 'currency' => 'CZK'];
                    }
                    foreach ($services as $index => $service) {
                        $this->render_service_row($index, $service);
                    }
                    ?>
                </div>

                <p><button type="button" class="button sw-schema-add-row" data-target="services"><?php echo esc_html__('+ Přidat službu', 'sw-schema-manager'); ?></button></p>
            </section>

            <section class="sw-schema-card sw-schema-card--meta">
                <div class="sw-schema-card__header">
                    <h2><?php echo esc_html__('Otázky a odpovědi', 'sw-schema-manager'); ?></h2>
                    <p><?php echo esc_html__('Otázky a odpovědi se použijí pouze při aktivním presetu „FAQ“.', 'sw-schema-manager'); ?></p>
                </div>

                <div id="sw-schema-faqs" class="sw-schema-repeater">
                    <?php
                    if (empty($faqs)) {
                        $faqs[] = ['question' => '', 'answer' => ''];
                    }
                    foreach ($faqs as $index => $faq) {
                        $this->render_faq_row($index, $faq);
                    }
                    ?>
                </div>

                <p><button type="button" class="button sw-schema-add-row" data-target="faqs"><?php echo esc_html__('+ Přidat FAQ', 'sw-schema-manager'); ?></button></p>
            </section>

            <section class="sw-schema-card sw-schema-card--meta">
                <div class="sw-schema-card__header">
                    <h2><?php echo esc_html__('Vlastní JSON-LD', 'sw-schema-manager'); ?></h2>
                    <p><?php echo esc_html__('Pro speciální situace lze vložit vlastní JSON-LD. V režimu „Automaticky + vlastní“ se přidá k automatice a presetům. V režimu „Jen vlastní“ poběží bez automatiky.', 'sw-schema-manager'); ?></p>
                </div>

                <div class="sw-schema-field">
                    <textarea name="sw_schema_custom_json" rows="12" class="sw-schema-code" placeholder='{"@context":"https://schema.org","@type":"Thing","name":"Ukázka"}'><?php echo esc_textarea($custom_json); ?></textarea>
                </div>
            </section>
        </div>
        <?php
    }

    private function render_service_row($index, $service) {
        $name = isset($service['name']) ? (string) $service['name'] : '';
        $description = isset($service['description']) ? (string) $service['description'] : '';
        $price = isset($service['price']) ? (string) $service['price'] : '';
        $currency = isset($service['currency']) ? (string) $service['currency'] : 'CZK';
        ?>
        <div class="sw-schema-repeat-item">
            <div class="sw-schema-fields sw-schema-fields--3">
                <div class="sw-schema-field">
                    <label><?php echo esc_html__('Název služby', 'sw-schema-manager'); ?></label>
                    <input type="text" name="sw_schema_services[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($name); ?>">
                </div>
                <div class="sw-schema-field">
                    <label><?php echo esc_html__('Cena', 'sw-schema-manager'); ?></label>
                    <input type="text" name="sw_schema_services[<?php echo esc_attr($index); ?>][price]" value="<?php echo esc_attr($price); ?>">
                </div>
                <div class="sw-schema-field">
                    <label><?php echo esc_html__('Měna', 'sw-schema-manager'); ?></label>
                    <input type="text" name="sw_schema_services[<?php echo esc_attr($index); ?>][currency]" value="<?php echo esc_attr($currency); ?>" placeholder="CZK">
                </div>
            </div>

            <div class="sw-schema-field">
                <label><?php echo esc_html__('Popis služby', 'sw-schema-manager'); ?></label>
                <textarea name="sw_schema_services[<?php echo esc_attr($index); ?>][description]" rows="3"><?php echo esc_textarea($description); ?></textarea>
            </div>

            <p class="sw-schema-item-actions"><button type="button" class="button sw-schema-remove-row"><?php echo esc_html__('Odebrat službu', 'sw-schema-manager'); ?></button></p>
        </div>
        <?php
    }

    private function render_faq_row($index, $faq) {
        $question = isset($faq['question']) ? (string) $faq['question'] : '';
        $answer = isset($faq['answer']) ? (string) $faq['answer'] : '';
        ?>
        <div class="sw-schema-repeat-item">
            <div class="sw-schema-field">
                <label><?php echo esc_html__('Otázka', 'sw-schema-manager'); ?></label>
                <input type="text" name="sw_schema_faqs[<?php echo esc_attr($index); ?>][question]" value="<?php echo esc_attr($question); ?>">
            </div>

            <div class="sw-schema-field">
                <label><?php echo esc_html__('Odpověď', 'sw-schema-manager'); ?></label>
                <textarea name="sw_schema_faqs[<?php echo esc_attr($index); ?>][answer]" rows="4"><?php echo esc_textarea($answer); ?></textarea>
            </div>

            <p class="sw-schema-item-actions"><button type="button" class="button sw-schema-remove-row"><?php echo esc_html__('Odebrat FAQ', 'sw-schema-manager'); ?></button></p>
        </div>
        <?php
    }

    public function save_post_meta($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!isset($_POST['sw_schema_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sw_schema_meta_nonce'])), self::NONCE_META)) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $mode = isset($_POST['sw_schema_mode']) ? sanitize_text_field(wp_unslash($_POST['sw_schema_mode'])) : 'auto';
        $allowed_modes = ['auto', 'extend', 'custom', 'off'];
        if (!in_array($mode, $allowed_modes, true)) {
            $mode = 'auto';
        }
        update_post_meta($post_id, self::META_MODE, $mode);

        $presets = isset($_POST['sw_schema_presets']) && is_array($_POST['sw_schema_presets']) ? array_map('sanitize_text_field', wp_unslash($_POST['sw_schema_presets'])) : [];
        $allowed_presets = ['service_catalog', 'faq', 'business'];
        $presets = array_values(array_intersect($presets, $allowed_presets));
        update_post_meta($post_id, self::META_PRESETS, $presets);

        $services = [];
        if (isset($_POST['sw_schema_services']) && is_array($_POST['sw_schema_services'])) {
            foreach (wp_unslash($_POST['sw_schema_services']) as $service) {
                if (!is_array($service)) {
                    continue;
                }
                $item = [
                    'name'        => sanitize_text_field($service['name'] ?? ''),
                    'description' => sanitize_textarea_field($service['description'] ?? ''),
                    'price'       => sanitize_text_field($service['price'] ?? ''),
                    'currency'    => sanitize_text_field($service['currency'] ?? 'CZK'),
                ];
                if ($item['name'] !== '' || $item['description'] !== '' || $item['price'] !== '') {
                    $services[] = $item;
                }
            }
        }
        update_post_meta($post_id, self::META_SERVICES, $services);

        $faqs = [];
        if (isset($_POST['sw_schema_faqs']) && is_array($_POST['sw_schema_faqs'])) {
            foreach (wp_unslash($_POST['sw_schema_faqs']) as $faq) {
                if (!is_array($faq)) {
                    continue;
                }
                $item = [
                    'question' => sanitize_text_field($faq['question'] ?? ''),
                    'answer'   => sanitize_textarea_field($faq['answer'] ?? ''),
                ];
                if ($item['question'] !== '' || $item['answer'] !== '') {
                    $faqs[] = $item;
                }
            }
        }
        update_post_meta($post_id, self::META_FAQS, $faqs);

        $custom_json = isset($_POST['sw_schema_custom_json']) ? wp_unslash($_POST['sw_schema_custom_json']) : '';
        $custom_json = is_string($custom_json) ? trim($custom_json) : '';
        update_post_meta($post_id, self::META_CUSTOM, $custom_json);
    }

    public function output_schema() {
        if (is_admin()) {
            return;
        }

        $post_id = get_queried_object_id();
        $mode = $post_id ? get_post_meta($post_id, self::META_MODE, true) : 'auto';
        $mode = $mode ?: 'auto';

        if ($mode === 'off') {
            return;
        }

        $items = [];

        if ($mode !== 'custom') {
            $items = array_merge($items, $this->get_automatic_schema());
        }

        if ($post_id) {
            $presets = get_post_meta($post_id, self::META_PRESETS, true);
            $presets = is_array($presets) ? $presets : [];
            foreach ($presets as $preset) {
                $items = array_merge($items, $this->get_preset_schema($preset, $post_id));
            }

            $custom_json = trim((string) get_post_meta($post_id, self::META_CUSTOM, true));
            if ($custom_json !== '') {
                $decoded = json_decode($custom_json, true);
                if (json_last_error() === JSON_ERROR_NONE && !empty($decoded)) {
                    $items[] = $decoded;
                }
            }
        }

        if (empty($items)) {
            return;
        }

        echo "\n<!-- SW Schema Manager start -->\n";
        foreach ($items as $item) {
            $this->print_jsonld($item);
        }
        echo "<!-- SW Schema Manager end -->\n";
    }

    private function get_automatic_schema() {
        $automatic = $this->get_automatic_settings();
        $global = $this->get_global_settings();
        $schemas = [];

        if (is_front_page()) {
            if (!empty($automatic['homepage_website'])) {
                $schemas[] = [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebSite',
                    '@id' => trailingslashit(home_url('/')) . '#website',
                    'name' => get_bloginfo('name'),
                    'url' => home_url('/'),
                    'inLanguage' => $global['language'],
                ];
            }

            if (!empty($automatic['homepage_org'])) {
                $org = $this->build_business_schema();
                if (!empty($org)) {
                    $schemas[] = $org;
                }
            }
        }

        if (is_single() && !empty($automatic['single_article'])) {
            global $post;
            if ($post instanceof WP_Post) {
                $url = get_permalink($post);
                $image = get_the_post_thumbnail_url($post, 'full');

                $article = [
                    '@context' => 'https://schema.org',
                    '@type' => 'BlogPosting',
                    '@id' => $url . '#article',
                    'mainEntityOfPage' => [
                        '@type' => 'WebPage',
                        '@id' => $url,
                    ],
                    'headline' => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
                    'description' => wp_strip_all_tags(get_the_excerpt($post)),
                    'datePublished' => get_the_date('c', $post),
                    'dateModified' => get_the_modified_date('c', $post),
                    'author' => [
                        '@type' => 'Person',
                        'name' => get_the_author_meta('display_name', $post->post_author),
                    ],
                    'inLanguage' => $global['language'],
                    'url' => $url,
                ];

                if ($image) {
                    $article['image'] = $image;
                }

                $publisher = $this->build_business_schema();
                if (!empty($publisher)) {
                    $article['publisher'] = [
                        '@type' => $publisher['@type'],
                        'name' => $publisher['name'] ?? get_bloginfo('name'),
                        'url' => $publisher['url'] ?? home_url('/'),
                    ];
                    if (!empty($global['logo'])) {
                        $article['publisher']['logo'] = [
                            '@type' => 'ImageObject',
                            'url' => $global['logo'],
                        ];
                    }
                }

                $schemas[] = array_filter($article);
            }
        }

        if (is_page() && !is_front_page() && !empty($automatic['page_webpage'])) {
            global $post;
            if ($post instanceof WP_Post) {
                $schemas[] = [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebPage',
                    '@id' => get_permalink($post),
                    'name' => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
                    'url' => get_permalink($post),
                    'inLanguage' => $global['language'],
                ];
            }
        }

        if ((is_home() || is_archive()) && !empty($automatic['archives_collection'])) {
            $title = is_home() ? get_bloginfo('name') : wp_strip_all_tags(get_the_archive_title());
            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $title,
                'url' => home_url(add_query_arg([], $_SERVER['REQUEST_URI'] ?? '/')),
                'inLanguage' => $global['language'],
            ];
        }

        return $schemas;
    }

    private function get_preset_schema($preset, $post_id) {
        $schemas = [];

        if ($preset === 'service_catalog') {
            $post = get_post($post_id);
            if ($post instanceof WP_Post) {
                $services = get_post_meta($post_id, self::META_SERVICES, true);
                $services = is_array($services) ? $services : [];

                $offers = [];
                foreach ($services as $service) {
                    $offer = [
                        '@type' => 'Offer',
                        'name' => $service['name'] ?? '',
                        'description' => $service['description'] ?? '',
                        'price' => $service['price'] ?? '',
                        'priceCurrency' => $service['currency'] ?? 'CZK',
                        'url' => get_permalink($post),
                    ];
                    $offers[] = array_filter($offer);
                }

                $service_schema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'Service',
                    'name' => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
                    'description' => wp_strip_all_tags(get_the_excerpt($post)),
                    'url' => get_permalink($post),
                ];

                if (!empty($offers)) {
                    $service_schema['offers'] = $offers;
                }

                $provider = $this->build_business_schema();
                if (!empty($provider)) {
                    $service_schema['provider'] = [
                        '@type' => $provider['@type'],
                        'name' => $provider['name'] ?? '',
                        'url' => $provider['url'] ?? '',
                    ];
                }

                $schemas[] = array_filter($service_schema);
            }
        }

        if ($preset === 'faq') {
            $faqs = get_post_meta($post_id, self::META_FAQS, true);
            $faqs = is_array($faqs) ? $faqs : [];
            $main_entities = [];

            foreach ($faqs as $faq) {
                if (empty($faq['question']) || empty($faq['answer'])) {
                    continue;
                }
                $main_entities[] = [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $faq['answer'],
                    ],
                ];
            }

            if (!empty($main_entities)) {
                $schemas[] = [
                    '@context' => 'https://schema.org',
                    '@type' => 'FAQPage',
                    'mainEntity' => $main_entities,
                ];
            }
        }

        if ($preset === 'business') {
            $business = $this->build_business_schema();
            if (!empty($business)) {
                $schemas[] = $business;
            }
        }

        return $schemas;
    }

    private function build_business_schema() {
        $global = $this->get_global_settings();
        if (empty($global['name'])) {
            return [];
        }

        $type = $global['entity_type'] ?: 'Organization';
        $data = [
            '@context' => 'https://schema.org',
            '@type' => $type,
            '@id' => trailingslashit($global['url']) . '#organization',
            'name' => $global['name'],
            'url' => $global['url'],
            'email' => $global['email'],
            'telephone' => $global['phone'],
            'taxID' => $global['dic'],
            'vatID' => $global['dic'],
            'identifier' => $global['ico'],
            'inLanguage' => $global['language'],
        ];

        if (!empty($global['logo'])) {
            $data['logo'] = [
                '@type' => 'ImageObject',
                'url' => $global['logo'],
            ];
        }

        $same_as = array_values(array_filter([
            $global['facebook'],
            $global['instagram'],
            $global['linkedin'],
        ]));
        if (!empty($same_as)) {
            $data['sameAs'] = $same_as;
        }

        $has_address = !empty($global['street']) || !empty($global['city']) || !empty($global['postal']) || !empty($global['country']);
        if ($has_address) {
            $data['address'] = array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $global['street'],
                'addressLocality' => $global['city'],
                'postalCode' => $global['postal'],
                'addressCountry' => $global['country'],
            ]);
        }

        return array_filter($data);
    }

    private function print_jsonld($data) {
        if (empty($data) || !is_array($data)) {
            return;
        }
        echo '<script type="application/ld+json">';
        echo wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo '</script>' . "\n";
    }

    public function add_schema_column($columns) {
        $columns['sw_schema'] = __('Schema', 'sw-schema-manager');
        return $columns;
    }

    public function render_schema_column($column, $post_id) {
        if ($column !== 'sw_schema') {
            return;
        }

        $mode = get_post_meta($post_id, self::META_MODE, true);
        $mode = $mode ?: 'auto';

        $labels = [
            'auto' => __('Automatika', 'sw-schema-manager'),
            'extend' => __('Auto + vlastní', 'sw-schema-manager'),
            'custom' => __('Jen vlastní', 'sw-schema-manager'),
            'off' => __('Vypnuto', 'sw-schema-manager'),
        ];

        echo '<span class="sw-schema-admin-badge sw-schema-admin-badge--' . esc_attr($mode) . '">' . esc_html($labels[$mode] ?? $mode) . '</span>';
    }
}

new SW_Schema_Manager();
