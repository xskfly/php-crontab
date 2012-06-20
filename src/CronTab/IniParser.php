<?php

namespace CronTab;

class IniParser
{
    public $config;

    /**
     * Set file and parse ini
     *
     * @param $file
     */
    public function __construct($file)
    {
        $ini = parse_ini_file($file);

        foreach ($ini as $key => $value) {
            $config = &$this->config;
            $namespaces = explode('.', $key);
            foreach ($namespaces as $namespace) {
                if (!isset($config[$namespace])) $config[$namespace] = array();
                $config = & $config[$namespace];
            }
            $config = $value;
        }
    }


    /**
     * Get config
     *
     * @param bool|string $key
     * @param null        $default
     * @return null
     */
    public function get($key = false, $default = null)
    {
        if ($key === false) return $this->config;

        $tmp = $default;
        if (strpos($key, '.') !== false) {
            $ks = explode('.', $key);
            $tmp = &$this->config;
            foreach ($ks as $k) {
                if (!array_key_exists($k, $tmp)) return $default;

                $tmp = & $tmp[$k];
            }
        } else {
            if (isset($this->config[$key])) {
                $tmp = $this->config[$key];
            }
        }
        return $tmp;
    }
}
