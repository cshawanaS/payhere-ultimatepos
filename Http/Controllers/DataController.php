<?php

namespace Modules\PayHere\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

class DataController extends Controller
{
    /**
     * Adds PayHere settings to the business settings dropdown
     */
    public function modifyAdminMenu()
    {
        $business_id = session()->get('user.business_id');
        $is_admin = auth()->user()->hasRole('Admin#' . $business_id) ? true : false;

        if (auth()->user()->can('business_settings.access')) {
            Menu::modify('admin-sidebar-menu', function ($menu) {
                $menu->url(
                    action([\Modules\PayHere\Http\Controllers\PayHereController::class, 'index']),
                    'PayHere Settings',
                    ['icon' => 'fa fas fa-credit-card', 'active' => request()->segment(1) == 'payhere']
                )->order(90);
            });
        }
    }

    /**
     * Define module permissions if needed
     */
    public function user_permissions()
    {
        return [
            [
                'value' => 'payhere.access_settings',
                'label' => 'Access PayHere Settings',
                'default' => false
            ],
        ];
    }
}
