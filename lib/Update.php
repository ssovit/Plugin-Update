<?php
namespace Sovit;

if (!class_exists('\Sovit\Update')) {
    class Update {
        const SERVER = "https://wppress.net";

        /**
         * @param $file
         * @param $plugin_name
         * @param $itemid
         * @param $version
         * @param $license_key
         * @param $license_setting_page
         */
        public function __construct($file, $plugin_name, $itemid, $version, $license_key, $license_setting_page) {
            $this->version              = $version;
            $this->item_id              = $itemid;
            $this->basename             = \dirname(plugin_basename($file));
            $this->filename             = basename($file);
            $this->file                 = $file;
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
                add_action("after_plugin_row_" . plugin_basename($file), [$this, 'after_plugin_row'], 50, 2);

            }
            //echo $this->filename; die();
        }

        /**
         * @param $message
         * @param $type
         * @param $btn
         */
        public static function add_notice($message = "", $type = "success", $btn = false) {
            echo "<div class=\"notice $type\">";
            echo wpautop($message);
            if (!empty($btn)) {

                echo wpautop(sprintf('<a href="%s"" class="button-primary">%s</a>', $btn['url'], $btn['label']));

            }
            echo "</div>";

        }

        /**
         * @param $file
         * @param $plugin_data
         */
        public function after_plugin_row($file, $plugin_data) {

            $wp_list_table = _get_list_table('WP_Plugins_List_Table');

            printf(
                '<tr class="plugin-update-tr active" id="%s" data-slug="%s" data-plugin="%s">' .
                '<td colspan="%s" class="plugin-update colspanchange">' .
                '<div class="update-message notice inline %s notice-alt"><p>',
                esc_attr($this->basename . '-update-license-nag'),
                esc_attr($this->basename),
                esc_attr($file),
                esc_attr($wp_list_table->get_column_count()),
                "notice-warning"
            );
            echo "<a href=\"" . $this->license_setting_page . "\">" . esc_html__("Enter valid license key/purchase code to enable automatic update.") . "</a>";
            echo "</p></td></tr>";

        }

        /**
         * @param $def
         * @param $action
         * @param $arg
         * @return mixed
         */
        public function check_info($def, $action, $arg) {
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

        /**
         * @param $transient
         * @return mixed
         */
        public function check_update($transient) {
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

        /**
         * @param $give_array
         * @return mixed
         */
        public function get_update_data($give_array = false) {
            $info                      = false;
            $query                     = [];
            $query['wpp-item-id']      = $this->item_id;
            $query['wpp-item-update']  = '' != $this->license_key ? $this->license_key : '';
            $query['wpp-item-version'] = $this->version;
            $query['wpp-site-url']     = self::maybeabsolute(site_url(), 'https://' . $_SERVER['HTTP_HOST']);
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

        public function license_nag() {
            self::add_notice(sprintf(esc_html__("Enter valid license key for %s plugin", "wppress"), $this->plugin_name), "error", [
                "url"   => $this->license_setting_page,
                "label" => esc_html__("Enter License Key", "wppress"),
            ]);

        }

        /**
         * @param $rel
         * @param $base
         * @return mixed
         */
        public static function maybeabsolute($rel, $base) {
            if (parse_url($rel, PHP_URL_SCHEME) != '') {
                return $rel;
            }
            if ($rel[0] == '#' || $rel[0] == '?') {
                return $base . $rel;
            }
            $base = trailingslashit($base);
            extract(parse_url($base));

            $path = preg_replace('#/[^/]*$#', '', $path);
            if ($rel[0] == '/') {
                $path = '';
            }
            $abs = "$host$path/$rel";
            $re  = ['#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#'];
            for ($n = 1; $n > 0; $abs = preg_replace($re, '/', $abs, -1, $n)) {}
            return $scheme . '://' . $abs;
        }

        /**
         * @param $links
         * @return mixed
         */
        public function plugin_action_link($links) {
            $links[] = '<a href="' . $this->license_setting_page . '" style="font-weight:700; color:green;">' . esc_html__('Activate License') . '</a>';

            return $links;
        }
    }
}
