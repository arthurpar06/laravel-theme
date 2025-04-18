<?php namespace Igaster\LaravelTheme;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class Theme
{
    public $name;
    public $viewsPath;
    public $assetPath;
    public $settings = [];

    /** @var Theme  */
    public $parent;

    /** @var \Igaster\LaravelTheme\Themes */
    private $themes;

    public function __construct($themeName, $assetPath = null, $viewsPath = null, ?Theme $parent = null)
    {
        $this->themes = resolve('igaster.themes');

        $this->name = $themeName;
        $this->assetPath = $assetPath === null ? $themeName : $assetPath;
        $this->viewsPath = $viewsPath === null ? $themeName : $viewsPath;
        $this->parent = $parent;

        $this->themes->add($this);
    }

    public function getViewPaths()
    {
        // Build Paths array.
        // All paths are relative to Config::get('theme.theme_path')
        $paths = [];
        $theme = $this;
        do {
            if (substr($theme->viewsPath, 0, 1) === DIRECTORY_SEPARATOR) {
                $path = base_path(substr($theme->viewsPath, 1));
            } else {
                $path = themes_path($theme->viewsPath);
            }
            if (!in_array($path, $paths)) {
                $paths[] = $path;
            }

        } while ($theme = $theme->parent);
        return $paths;
    }

    public function url($url)
    {
        // return external URLs unmodified
        if (preg_match('/^((http(s?):)?\/\/)/i', $url)) {
            return $url;
        }

        $url = ltrim($url, '/');

        // Is theme folder located on the web (ie AWS)? Dont lookup parent themes...
        if (preg_match('/^((http(s?):)?\/\/)/i', $this->assetPath)) {
            return $this->assetPath . '/' . $url;
        }

        // Check for valid {xxx} keys and replace them with the Theme's configuration value (in themes.php)
        preg_match_all('/\{(.*?)\}/', $url, $matches);
        foreach ($matches[1] as $param) {
            if (($value = $this->getSetting($param)) !== null) {
                $url = str_replace('{' . $param . '}', $value, $url);
            }
        }

        // Seperate url from url queries
        if (($position = strpos($url, '?')) !== false) {
            $baseUrl = substr($url, 0, $position);
            $params = substr($url, $position);
        } else {
            $baseUrl = $url;
            $params = '';
        }

        // Lookup asset in current's theme asset path
        $fullUrl = (empty($this->assetPath) ? '' : '/') . $this->assetPath . '/' . $baseUrl;

        if (file_exists($fullPath = public_path($fullUrl))) {
            return $fullUrl . $params;
        }

        // If not found then lookup in parent's theme asset path
        if ($parentTheme = $this->getParent()) {
            return $parentTheme->url($url);
        }
        // No parent theme? Lookup in the public folder.
        else {
            if (file_exists(public_path($baseUrl))) {
                return "/" . $baseUrl . $params;
            }
        }

        // Asset not found at all. Error handling
        $action = Config::get('themes.asset_not_found', 'LOG_ERROR');

        if ($action == 'THROW_EXCEPTION') {
            throw new Exceptions\themeException("Asset not found [$url]");
        } elseif ($action == 'LOG_ERROR') {
            Log::warning("Asset not found [$url] in Theme [" . $this->themes->current()->name . "]");
        } else {
            // themes.asset_not_found = 'IGNORE'
            return '/' . $url;
        }
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function setParent(Theme $parent)
    {
        $this->parent = $parent;
    }

    public function install($clearPaths = false)
    {
        $viewsPath = themes_path($this->viewsPath);
        $assetPath = public_path($this->assetPath);

        if ($clearPaths) {
            if (File::exists($viewsPath)) {
                File::deleteDirectory($viewsPath);
            }
            if (File::exists($assetPath)) {
                File::deleteDirectory($assetPath);
            }
        }

        File::makeDirectory($viewsPath);
        File::makeDirectory($assetPath);

        $themeJson = new \Igaster\LaravelTheme\themeManifest(array_merge($this->settings, [
            'name' => $this->name,
            'extends' => $this->parent ? $this->parent->name : null,
            'asset-path' => $this->assetPath,
        ]));
        $themeJson->saveToFile("$viewsPath/theme.json");

        $this->themes->rebuildCache();
    }

    public function uninstall()
    {
        // Calculate absolute paths
        $viewsPath = themes_path($this->viewsPath);
        $assetPath = public_path($this->assetPath);

        // Check that paths exist
        $viewsExists = File::exists($viewsPath);
        $assetExists = File::exists($assetPath);

        // Check that no other theme uses to the same paths (ie a child theme)
        foreach ($this->themes->all() as $t) {
            if ($t !== $this && $viewsExists && $t->viewsPath == $this->viewsPath) {
                throw new \Exception("Can not delete folder [$viewsPath] of theme [{$this->name}] because it is also used by theme [{$t->name}]", 1);
            }

            if ($t !== $this && $assetExists && $t->assetPath == $this->assetPath) {
                throw new \Exception("Can not delete folder [$viewsPath] of theme [{$this->name}] because it is also used by theme [{$t->name}]", 1);
            }

        }

        File::deleteDirectory($viewsPath);
        File::deleteDirectory($assetPath);

        $this->themes->rebuildCache();
    }

    /*--------------------------------------------------------------------------
    | Theme Settings
    |--------------------------------------------------------------------------*/

    public function setSetting($key, $value)
    {
        $this->settings[$key] = $value;
    }

    public function getSetting($key, $default = null)
    {
        if (Arr::has($this->settings,$key)) {
            return Arr::get($this->settings,$key);
        } elseif ($parent = $this->getParent()) {
            return $parent->getSetting($key, $default);
        } else {
            return $default;
        }
    }

    public function loadSettings($settings = [])
    {

        // $this->settings = $settings;

        $this->settings = array_diff_key((array) $settings, array_flip([
            'name',
            'extends',
            'views-path',
            'asset-path',
        ]));

    }

}
