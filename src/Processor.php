<?php

/**
 * This file is part of erdmannfreunde/euf_nutshell_installer.
 *
 * Copyright (c) 2017 Erdmann und Freunde
 *
 * @package   erdmannfreunde/euf_nutshell_installer
 * @author    Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 * @copyright 2017 Erdmann und Freunde
 * @license   https://github.com/erdmannfreunde/euf_nutshell_installer/blob/master/LICENSE
 */

namespace EuF\Nutshell\Composer;

use Composer\IO\IOInterface;
use Composer\Util\Filesystem;


class Processor
{

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var \stdClass
     */
    private $packageJson;

    /**
     * @var string
     */
    private $gulpfileJs;

    /**
     * Defines the configurable parameters per file (package.json and gulpfile.js).
     * The explanation will be shown in the install process.
     *
     * @var array
     */
    private $configurableParams = [
        'package'  => [
            'name' => [
                'explanation' => 'How would you like to name your project?',
            ],
        ],
        'gulpfile' => [
            'themePath' => [
                'explanation' => 'How would you like to name the folder with all theme files?',
            ],
            'bsProxy'   => [
                'explanation' => 'For local development, what will be the uri of the installation?',
            ],
        ],
    ];

    /**
     * Processor constructor.
     *
     * @param IOInterface $io
     * @param Filesystem  $filesystem
     */
    public function __construct(IOInterface $io, Filesystem $filesystem)
    {
        $this->io         = $io;
        $this->filesystem = $filesystem;
    }

    /**
     * Run the script.
     * Ensures, that the files (package.json and gulpfile.js) are present.
     * When default values are given in these files, the new values get asked via the IO (console).
     *
     * @return void
     */
    public function handle()
    {
        if (file_exists('package.json') && file_exists('gulpfile.js')) {
            // Nothing to do here.
            return;
        }

        if (!file_exists('package.json')) {
            copy('package.json.dist', 'package.json');
        }
        if (!file_exists('gulpfile.js')) {
            copy('gulpfile.js.dist', 'gulpfile.js');
        }

        if (!$this->io->isInteractive()) {
            // Distribution files are copied. Nothing left to do.
            return;
        }

        $currentParams = $this->currentParams();

        $actualParams = [];
        $actualParams = $this->processParams($currentParams, $actualParams);

        foreach ($actualParams as $file => $params) {
            foreach ($params as $param => $actual) {
                $current = $currentParams[$file][$param];
                switch ($file.'.'.$param) {
                    case 'package.name':
                        // Set the package name var
                        if ($actual !== $current) {
                            $this->getPackageJson()->name = $actual;
                        }
                        break;

                    case 'gulpfile.themePath':
                        // Change the theme path var and rename the corresponding folder
                        $actual = $this->normalizeThemePath($actual);
                        if ($actual !== $current) {
                            $this->filesystem->rename($current, $actual);
                            $this->replaceGulpfileJsVar($param, $actual);
                        }
                        break;

                    case 'gulpfile.bsProxy':
                        // Change the bsProxy var
                        if ($actual !== $current) {
                            $this->replaceGulpfileJsVar($param, $actual);
                        }
                        break;
                }
            }
        }

        $this->persistPackageJson();
        $this->persistGulpfileJs();
    }

    /**
     * Get packageJson. Object representation of the package.json.
     *
     * @return \stdClass
     */
    public function getPackageJson()
    {
        if (null === $this->packageJson) {
            $this->packageJson = json_decode(file_get_contents('package.json'));
        }

        return $this->packageJson;
    }

    /**
     * Get gulpfileJs. Content of the gulpfile.js
     *
     * @return string
     */
    public function getGulpfileJs()
    {
        if (null === $this->gulpfileJs) {
            $this->gulpfileJs = file_get_contents('gulpfile.js');
        }

        return $this->gulpfileJs;
    }

    /**
     * Return a regex that matches a variable assignment in the gulpfile.js.
     *
     * @param string $param
     *
     * @return string
     */
    private function getJsVarRegex($param)
    {
        // Matches:
        // --------
        // #0: var bsProxy    = "nutshell.localhost";
        //
        // #1: var bsProxy    = "
        // #2: nutshell.localhost
        // #3: ";
        return sprintf('/(var %s\\s+?=\\s\\")(.+?)(\\"\\;)/', $param);
    }

    /**
     * Replace a variable in the gulpfile.js.
     *
     * @param string $param
     * @param string $value
     *
     * @return void
     */
    private function replaceGulpfileJsVar($param, $value)
    {
        $this->gulpfileJs = preg_replace($this->getJsVarRegex($param), '$1'.$value.'$3', $this->getGulpfileJs());
    }

    /**
     * Get a variable from the gulpfile.js.
     *
     * @param string $param
     *
     * @return string|null
     */
    private function fetchGulpfileJsVar($param)
    {
        if (preg_match($this->getJsVarRegex($param), $this->getGulpfileJs(), $matches)) {
            return $matches[2];
        }

        return null;
    }

    /**
     * Get one variable from the package.json.
     *
     * @param string $param
     *
     * @return string
     */
    private function fetchPackageJsonVar($param)
    {
        return $this->getPackageJson()->{$param};
    }

    /**
     * Persist the package.json by overriding the currently present file.
     */
    private function persistPackageJson()
    {
        file_put_contents('package.json', json_encode($this->getPackageJson(), JSON_PRETTY_PRINT));
    }

    /**
     * Persist the gulpfile.js by overriding the currently present file.
     */
    private function persistGulpfileJs()
    {
        file_put_contents('gulpfile.js', $this->getGulpfileJs());
    }

    /**
     * By given expected params, the actual params get asked via the IO.
     *
     * @param array $expectedParams
     * @param array $actualParams
     *
     * @return array ['gulpfile' => ['key' => 'value'], 'package' => ['key' => 'value']]
     */
    private function processParams($expectedParams, $actualParams)
    {
        $isStarted = false;
        foreach ($expectedParams as $file => $params) {
            foreach ($params as $key => $default) {
                if (array_key_exists($key, $actualParams)) {
                    continue;
                }

                if (!$isStarted) {
                    $isStarted = true;
                    $this->io->write(
                        '<comment>Please customize your Contao nutshell. Skip questions by clicking enter. Yellow values are default values.</comment>'
                    );
                }

                $value = $this->io->ask(
                    sprintf(
                        '<question>%s</question> (<comment>%s</comment>) <- %s: ',
                        $file.'.'.$key,
                        $default,
                        $this->configurableParams[$file][$key]['explanation']
                    ),
                    $default
                );

                $actualParams[$file][$key] = $value;
            }
        }

        return $actualParams;
    }

    /**
     * Return the parameters that are currently represented.
     *
     * @return array
     */
    private function currentParams()
    {
        return [
            'package'  => $this->currentPackageParams(),
            'gulpfile' => $this->currentGulpfileParams(),
        ];
    }

    /**
     * Return the parameters that are currently represented in the package.json
     *
     * @return array
     */
    private function currentPackageParams()
    {
        $currentParams      = [];
        $configurableParams = array_keys($this->configurableParams['package']);

        foreach ($configurableParams as $param) {
            $currentParams[$param] = $this->fetchPackageJsonVar($param);
        }

        return $currentParams;
    }

    /**
     * Return the parameters that are currently represented in the gulpfile.js
     *
     * @return array
     */
    private function currentGulpfileParams()
    {
        $currentParams      = [];
        $configurableParams = array_keys($this->configurableParams['gulpfile']);

        foreach ($configurableParams as $param) {
            $currentParams[$param] = $this->fetchGulpfileJsVar($param);
        }

        return $currentParams;
    }

    /**
     * Normalize the theme path. Prefix with "files/" and suffix with trailing slash.
     *
     * @param $path
     *
     * @return string
     */
    private function normalizeThemePath($path)
    {
        $path = $this->filesystem->normalizePath($path);
        if (false === strpos($path, 'files/')) {
            $path = 'files/'.$path;
        }

        return $path.'/';
    }
}
