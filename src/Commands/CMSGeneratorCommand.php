<?php

namespace Suprb\CmsGenerator\Commands;

use Illuminate\Console\Command;

class CMSGeneratorCommand extends Command
{
    protected $signature = 'cms:install';
    protected $description = 'Install Generate CMS Generator';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {

    }

}
