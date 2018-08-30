<?php

namespace Ecjia\App\Staff;

use Royalcms\Component\App\AppParentServiceProvider;

class StaffServiceProvider extends  AppParentServiceProvider
{
    
    public function boot()
    {
        $this->package('ecjia/app-staff', null, dirname(__DIR__));
    }
    
    public function register()
    {
        
    }
    
    
    
}