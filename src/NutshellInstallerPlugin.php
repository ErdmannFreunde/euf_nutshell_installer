<?php

/**
 * This file is part of erdmannfreunde/euf_nutshell_installer.
 *
 * Copyright (c) 2017-2018 Erdmann und Freunde
 *
 * @package   erdmannfreunde/euf_nutshell_installer
 * @author    Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 * @copyright 2017-2018 Erdmann und Freunde
 * @license   https://github.com/erdmannfreunde/euf_nutshell_installer/blob/master/LICENSE
 */

namespace EuF\Nutshell\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Util\Filesystem;


class NutshellInstallerPlugin implements PluginInterface, EventSubscriberInterface
{

    protected $composer;
    protected $io;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public static function getSubscribedEvents()
    {
        return [
            'post-update-cmd' => [
                ['initializeNutshell', 10]
            ],
            'post-install-cmd' => [
                ['initializeNutshell', 10]
            ],
        ];
    }

    /**
     * Runs all Composer tasks to initialize the Nutshell kit.
     *
     * @param Event $event
     */
    public static function initializeNutshell(Event $event)
    {
        $processor = new Processor($event->getIO(), new Filesystem());
        $processor->handle();
    }
}
