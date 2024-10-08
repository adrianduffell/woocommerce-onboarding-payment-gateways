<?php

namespace Composer\Installers;

class KodiCMSInstaller extends \Composer\Installers\BaseInstaller
{
    /** @var array<string, string> */
    protected $locations = array('plugin' => 'cms/plugins/{$name}/', 'media' => 'cms/media/vendor/{$name}/');
}
