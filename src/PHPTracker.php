<?php
namespace Itgalaxy\SentryIntegration;

/**
 * WordPress Sentry PHP Tracker.
 */

final class PHPTracker extends TrackerAbstract
{
    /**
     * Holds an instance to the sentry client.
     *
     * @var \Raven_Client
     */
    protected $client;

    /**
     * Holds the class instance.
     *
     * @var PHPTracker
     */
    private static $instance;

    /**
     * Get the sentry tracker instance.
     *
     * @return PHPTracker
     */
    public static function get_instance()
    {
        return self::$instance ?: self::$instance = new self();
    }

    /**
     * Execute login on client send data.
     *
     * @access private
     *
     * @param array $data A reference to the data being sent.
     *
     * @return bool True to send data; Otherwise false.
     */
    public function on_send_data(array &$data)
    {
        if (has_filter('sentry_integration_send_data')) {
            $filtered = apply_filters('sentry_integration_send_data', $data);

            if (is_array($filtered)) {
                $data = array_merge($data, $filtered);
            } else {
                return (bool) $filtered;
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function set_dsn($dsn)
    {
        parent::set_dsn($dsn);

        if (is_string($dsn)) {
            // Update Raven client
            $options = array_merge(
                $this->get_options(),
                \Raven_Client::parseDSN($dsn)
            );
            $client  = $this->get_client();

            foreach ($options as $key => $value) {
                $client->$key = $value;
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get_dsn()
    {
        $dsn = parent::get_dsn();

        if (has_filter('sentry_integration_dsn')) {
            $dsn = (string) apply_filters('sentry_integration_dsn', $dsn);
        }

        return $dsn;
    }

    /**
     * {@inheritDoc}
     */
    public function get_options()
    {
        $options = parent::get_options();

        if (has_filter('sentry_integration_options')) {
            $options = (array) apply_filters(
                'sentry_integration_options',
                $options
            );
        }

        return $options;
    }

    /**
     * {@inheritDoc}
     */
    public function get_default_options()
    {
        global $wpdb;

        $options = [
            'release' => SENTRY_INTEGRATION_RELEASE,
            'environment' => defined('SENTRY_INTEGRATION_ENV') ? SENTRY_INTEGRATION_ENV : 'unspecified',
            'tags' => [
                'php' => phpversion(),
                'mysql' => $wpdb->db_version(),
                'wordpress' => get_bloginfo('version')
            ]
        ];

        if (defined('SENTRY_INTEGRATION_ERROR_TYPES')) {
            $options['error_types'] = SENTRY_INTEGRATION_ERROR_TYPES;
        }

        return $options;
    }

    /**
     * Get the sentry client.
     *
     * @return \Raven_Client
     */
    public function get_client()
    {
        return $this->client ?: $this->client = new \Raven_Client(
            $this->get_dsn(),
            $this->get_options()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function get_context()
    {
        return (array) $this->get_client()->context;
    }

    /**
     * {@inheritDoc}
     */
    public function set_user_context(array $data)
    {
        $this->get_client()->user_context($data);
    }

    /**
     * {@inheritDoc}
     */
    public function get_user_context()
    {
        return $this->get_context()['user'];
    }

    /**
     * {@inheritDoc}
     */
    public function set_tags_context(array $data)
    {
        $this->get_client()->tags_context($data);
    }

    /**
     * {@inheritDoc}
     */
    public function get_tags_context()
    {
        return $this->get_context()['tags'];
    }

    /**
     * {@inheritDoc}
     */
    public function set_extra_context(array $data)
    {
        $this->get_client()->extra_context($data);
    }

    /**
     * {@inheritDoc}
     */
    public function get_extra_context()
    {
        return $this->get_context()['extra'];
    }

    /**
     * {@inheritDoc}
     */
    protected function bootstrap()
    {
        // Instantiate the client and install.
        $this->get_client()->install()->setSendCallback([$this, 'on_send_data']);

        $themes = wp_get_themes([
            'errors' => null,
            'allowed' => null
        ]);
        $sendingThemes = [];

        foreach ($themes as $themeName => $theme) {
            $sendingThemes[$themeName] = [
                'Name' => $theme->display('Name'),
                'ThemeURI' => $theme->display('ThemeURI'),
                'Description' => $theme->display('Description'),
                'Author' => $theme->display('Author'),
                'Version' => $theme->display('Version'),
                'AuthorURI' => $theme->display('AuthorURI'),
                'Status' => $theme->display('Status'),
                'Tags' => $theme->display('Tags')
            ];
        }

        $this->set_extra_context([
            'loaded_extensions' => get_loaded_extensions(),
            'ini' => ini_get_all(),
            'uname' => php_uname(),
            'apache_modules' => function_exists('apache_get_modules')
                ? apache_get_modules()
                : [],
            'stream_wrappers' => stream_get_wrappers(),
            'stream_transports' => stream_get_transports(),
            'stream_filters' => stream_get_filters(),
            'usage' => getrusage(),
            'memory_usage' => memory_get_usage(),
            'real_memory_usage' => memory_get_usage(true),
            'memory_peak_usage' => memory_get_peak_usage(),
            'real_memory_peak_usage' => memory_get_peak_usage(true),
            'is_cron' => defined('DOING_CRON'),
            'multisite' => is_multisite(),
            'blog_id' => get_current_blog_id(),
            'mu_plugins' => wp_get_mu_plugins(),
            'plugins' => get_plugins(),
            'themes' => $sendingThemes,
            'active_theme' => (string) wp_get_theme(),
            'language' => get_bloginfo('language')
        ]);

        // After the theme was setup reset the options
        add_action('after_setup_theme', function () {
            if (has_filter('sentry_integration_options')) {
                $this->set_dsn($this->get_dsn());
            }
        });
    }
}
