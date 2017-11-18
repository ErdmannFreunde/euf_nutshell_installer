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
            // Distribution files are copied. Nothing to do left.
            return;
        }

        $expectedParams = $this->expectedParams();

        $actualParams = [];
        $actualParams = $this->processParams($expectedParams, $actualParams);

        foreach ($actualParams as $param => $value) {
            switch ($param) {
                case 'package.name':
                    if ($value !== $expectedParams[$param]) {
                        $json       = json_decode(file_get_contents('package.json'));
                        $json->name = $value;
                        file_put_contents('package.json', json_encode($json,JSON_PRETTY_PRINT));
                    }
                    break;
                case 'gulpfile.themePath':
                    if ($value !== $expectedParams[$param]) {
                        rename($expectedParams[$param], $value);
                        $file = file_get_contents('gulpfile.js');
                        preg_replace("/(var $param\s+?\=\s\")(.+?)(\")/", "\$1$value\$3", $file);
                        file_put_contents('gulpfile.js', $file);
                    }
                    break;
                case 'gulpfile.bsProxy':
                    if ($value !== $expectedParams[$param]) {
                        $file = file_get_contents('gulpfile.js');
                        preg_replace("/(var $param\s+?\=\s\")(.+?)(\")/", "\$1$value\$3", $file);
                        file_put_contents('gulpfile.js', $file);
                    }
                    break;
            }
        }
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
        $json               = json_decode(file_get_contents('package.json.dist'));

        foreach ($configurableParams as $param) {
            $expectedParams['package.'.$param] = $json->{$param};
        }

        return $expectedParams;
    }

    private function expectedGulpfileParams()
    {
        $expectedParams     = [];
        $configurableParams = ['themePath', 'bsProxy'];
        $js                 = file_get_contents('gulpfile.js.dist');

        foreach ($configurableParams as $param) {
            preg_match("/var $param\s+?\=\s\"(.+?)\"/", $js, $matches);
            $expectedParams['gulpfile.'.$param] = $matches[1];
        }

        return $expectedParams;
    }
}
