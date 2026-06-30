<?php

use App\Providers\AppServiceProvider;
use Module\Actions\Providers\ActionsServiceProvider;
use Module\Categories\Providers\CategoriesServiceProvider;
use Module\Directories\Providers\DirectoriesServiceProvider;
use Module\Groups\Providers\GroupsServiceProvider;
use Module\Projects\Providers\ProjectsServiceProvider;
use Module\Proxy\Providers\ProxyServiceProvider;
use Module\Scenario\Providers\ScenarioServiceProvider;
use Module\Users\Providers\UsersServiceProvider;

return [
    AppServiceProvider::class,
    ActionsServiceProvider::class,
    CategoriesServiceProvider::class,
    DirectoriesServiceProvider::class,
    GroupsServiceProvider::class,
    ProxyServiceProvider::class,
    ProjectsServiceProvider::class,
    ScenarioServiceProvider::class,
    UsersServiceProvider::class,
];
