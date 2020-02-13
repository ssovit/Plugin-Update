<?php
namespace WPPress;

if (!class_exists('\WPPress\Update')) {
    class Update
    {
        const SERVER = "https://wppress.net";

        public function __construct($file, $plugin_name, $itemid, $version, $license_key, $license_setting_page)
        {
            $this->version              = $version;
            $this->item_id              = $itemid;
            $this->basename             = \dirname(plugin_basename($file));
            $this->filename             = basename($file);
            $this->license_key          = $license_key;
            $this->license_setting_page = $license_setting_page;
            $this->plugin_name          = $plugin_name;
            add_filter('pre_set_site_transient_update_plugins', [
                $this,
                'check_update',
            ]);
            add_filter('plugins_api', [
                $this,
                'check_info',
            ], 10, 3);
            if ('' == $license_key) {
                add_filter('plugin_action_links_' . plugin_basename($file), [$this, 'plugin_action_link']);
                add_action('admin_notices', [$this,
                    'license_nag',
                ]);
            }
        }

        public function check_info($def, $action, $arg)
        {
            if (!isset($arg->slug) || $arg->slug != $this->basename) {
                return false;
            }
            if ('' == $this->license_key) {
                return new \WP_Error('plugins_api_failed', 'License key missing or invalid.</p> <p><a href="' . $this->license_setting_page . '">Enter valid license key and try again.</a>', $request->get_error_message());
            }
            $info = $this->get_update_data();
            if (\is_object($info) && !empty($info)) {
                $def = $info;
            }

            return $def;
        }

        public function check_update($transient)
        {
            if (empty($transient->checked)) {
                return $transient;
            }
            $info = $this->get_update_data();
            if (!$info) {
                return $transient;
            }
            if (\is_object($info) && !empty($info)) {
                if (version_compare($info->version, $this->version, '<=')) {
                    return $transient;
                }
                $info->new_version                                            = $info->version;
                $info->package                                                = $info->download_link;
                $transient->response[$this->basename . '/' . $this->filename] = $info;
            }

            return $transient;
        }

        public function get_update_data($give_array = false)
        {
            $info                      = false;
            $query                     = [];
            $query['wpp-item-id']      = $this->item_id;
            $query['wpp-item-update']  = '' != $this->license_key ? $this->license_key : '';
            $query['wpp-item-version'] = $this->version;
            $query['wpp-site-url']     = Helper::maybeabsolute(site_url(), 'https://' . $_SERVER['HTTP_HOST']);
            $url                       = add_query_arg($query, self::SERVER);

            // Get the remote info
            $request = wp_remote_get($url);
            if (!is_wp_error($request) || 200 === wp_remote_retrieve_response_code($request)) {
                $info = maybe_unserialize($request['body']);
                if (\is_object($info)) {
                    $info->slug = $this->basename;
                }
            }

            return $info;
        }

        public function license_nag()
        {
            Helper::add_notice(sprintf(esc_html__("Enter valid license key for %s plugin", "facebook-events"), $this->plugin_name), "error", [
                "url"   => $this->license_setting_page,
                "label" => esc_html__("Enter License Key", "facebook-events"),
            ]);

        }

        public function plugin_action_link($links)
        {
            $links[] = '<a href="' . $this->license_setting_page . '" style="font-weight:700; color:green;">' . esc_html__('Activate License') . '</a>';

            return $links;
        }
    }
}