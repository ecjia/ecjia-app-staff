<?php

namespace Ecjia\App\Staff;

use Royalcms\Component\App\AppServiceProvider;

class StaffServiceProvider extends  AppServiceProvider
{
    
    public function boot()
    {
        $this->package('ecjia/app-staff');
    }
    
    public function register()
    {
        
    }
    
    
    
}