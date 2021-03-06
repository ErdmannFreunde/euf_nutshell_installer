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

use Composer\Script\Event;
use Composer\Util\Filesystem;


/**
 * @deprecated To be removed, repository is composer-plugin now
 */
class ScriptHandler
{
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
