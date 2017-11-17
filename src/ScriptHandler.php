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

use Composer\Script\Event;
use Composer\Util\Filesystem;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;


class ScriptHandler
{
    /**
     * Runs all Composer tasks to initialize the Nutshell kit.
     *
     * @param Event $event
     */
    public static function initializeNutshell(Event $event)
    {
        $io = $event->getIO()->write('Hello World');
    }
}
