<?php
namespace MartinSotirov\CorePress;

class Theme
{
    protected $filesystem = null;
    public $dir = '';
    public $uri = '';

    public function __construct()
    {
        require_once(dirname(__FILE__) . '/../vendor/autoload.php');

        /**
         * If debug mode is enabled, boot the whoops error page handler
         */
        if (WP_DEBUG === true && WHOOPS === true) {
            $this->bootWhoops();
        }

        $this->dir = get_stylesheet_directory();
        $this->uri = get_stylesheet_directory_uri();
        $this->filesystem = $this->initFilesystem();

        // subscribe subclass init method to the init hook
        if (method_exists($this, 'init')) {
            add_action('init', [$this, 'init']);
        }

        $this->loadShortcodes();
        add_action('wp_enqueue_scripts', [$this, 'loadAssets']);
    }

    /**
     * Boot the whoops error page handler
     */
    public function bootWhoops()
    {
        $whoops = new \Whoops\Run;
        $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
        $whoops->register();
    }

    /**
     * Init the filesystem reader Flysystem
     */
    public function initFilesystem()
    {

        $adapter = new \League\Flysystem\Adapter\Local($this->dir);
        return new \League\Flysystem\Filesystem($adapter);
    }

    /**
     * Register the shortcodes
     */
    public function loadShortcodes()
    {
        if ($this->filesystem->has('inc/Shortcodes')) {

            foreach ($this->filesystem->listContents('inc/Shortcodes') as $file) {

                if ($file['extension'] === 'php') {
                    // read contents of file
                    $sourceCode = $this->filesystem->read($file['path']);

                    // parse code and get namespaced class name
                    $parsedClass = Utils\ClassParser::parse($sourceCode);
                    $namespacedClassName = $parsedClass->getNamespacedClassName();

                    // get shortcode tag or make one from the class name
                    if (defined($namespacedClassName . '::tag')) {
                        $tag = $namespacedClassName::tag;
                    } else {
                        $tag = mb_strtolower($parsedClass->className);
                    }

                    // register shortcode
                    add_shortcode($tag, [$namespacedClassName, 'output']);
                }

            }
        }
    }

    public function loadAssets()
    {
        if ($this->filesystem->has('assets')) {

            foreach ($this->filesystem->listContents('assets', true) as $file) {
                //echo '<pre>' . print_r($file, 1) . '</pre>';

                if ($file['dirname'] === 'assets/css' && $file['extension'] === 'css') {
                    wp_enqueue_style(rtrim($file['basename'], '.css'), $this->uri . '/' . $file['path']);
                } elseif ($file['dirname'] === 'assets/js' && $file['extension'] === 'js') {
                    wp_enqueue_script(rtrim($file['basename'], '.js'), $this->uri . '/' . $file['path'], [], '', true);
                }
            }
        }
    }
}
