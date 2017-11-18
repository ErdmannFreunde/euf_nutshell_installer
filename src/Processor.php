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


class Processor
{

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var \stdClass
     */
    private $packageJson;

    /**
     * @var string
     */
    private $gulpfileJs;

    /**
     * Processor constructor.
     *
     * @param IOInterface $io
     */
    public function __construct(IOInterface $io)
    {
        $this->io = $io;
    }

    public function handle()
    {
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

        $expectedParams = $this->expectedParams();

        $actualParams = [];
        $actualParams = $this->processParams($expectedParams, $actualParams);

        foreach ($actualParams as $param => $value) {
            switch ($param) {
                case 'package.name':
                    if ($value !== $expectedParams[$param]) {
                        $this->getPackageJson()->name = $value;
                    }
                    break;
                case 'gulpfile.themePath':
                    if ($value !== $expectedParams[$param]) {
                        rename($expectedParams[$param], $value);
                        $param = str_replace('gulpfile.', '', $param);
                        $this->replaceGulpfileJsVar($param, $value);
                    }
                    break;
                case 'gulpfile.bsProxy':
                    if ($value !== $expectedParams[$param]) {
                        $param = str_replace('gulpfile.', '', $param);
                        $this->replaceGulpfileJsVar($param, $value);
                    }
                    break;
            }
        }

        $this->persistPackageJson();
        $this->persistGulpfileJs();
    }

    /**
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
     * @return string
     */
    public function getGulpfileJs()
    {
        if (null === $this->gulpfileJs) {
            $this->gulpfileJs = file_get_contents('gulpfile.js');
        }

        return $this->gulpfileJs;
    }

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

    private function replaceGulpfileJsVar($param, $value)
    {
        $this->gulpfileJs = preg_replace($this->getJsVarRegex($param), '$1'.$value.'$3', $this->getGulpfileJs());
    }

    private function fetchGulpfileJsVar($param)
    {
        if (preg_match($this->getJsVarRegex($param), $this->getGulpfileJs(), $matches)) {
            return $matches[2];
        }

        return null;
    }

    private function fetchPackageJsonVar($param)
    {
        return $this->getPackageJson()->{$param};
    }

    private function persistPackageJson()
    {
        file_put_contents('package.json', json_encode($this->getPackageJson(), JSON_PRETTY_PRINT));
    }

    private function persistGulpfileJs()
    {
        file_put_contents('gulpfile.js', $this->getGulpfileJs());
    }

    private function processParams($expectedParams, $actualParams)
    {
        $isStarted = false;
        foreach ($expectedParams as $key => $message) {
            if (array_key_exists($key, $actualParams)) {
                continue;
            }

            if (!$isStarted) {
                $isStarted = true;
                $this->io->write('<comment>Please customize your Contao nutshell.</comment>');
            }

            $default = $message;
            $value   = $this->io->ask(
                sprintf(
                    '<question>%s</question> (<comment>%s</comment>): ',
                    $key,
                    $default
                ),
                $default
            );

            $actualParams[$key] = $value;
        }

        return $actualParams;
    }

    private function expectedParams()
    {
        return array_merge($this->expectedPackageParams(), $this->expectedGulpfileParams());
    }

    private function expectedPackageParams()
    {
        $expectedParams     = [];
        $configurableParams = ['name'];

        foreach ($configurableParams as $param) {
            $expectedParams['package.'.$param] = $this->fetchPackageJsonVar($param);
        }

        return $expectedParams;
    }

    private function expectedGulpfileParams()
    {
        $expectedParams     = [];
        $configurableParams = ['themePath', 'bsProxy'];

        foreach ($configurableParams as $param) {
            $expectedParams['gulpfile.'.$param] = $this->fetchGulpfileJsVar($param);
        }

        return $expectedParams;
    }
}
