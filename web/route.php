<?php

use think\Route;
Route::domain('api','api');
return [
    ':module/:controller/:action/addon/:addon' => 'addons/AddonsExecute/run'
];

?>