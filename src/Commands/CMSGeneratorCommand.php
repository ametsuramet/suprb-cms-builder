<?php

namespace Suprb\CmsGenerator\Commands;

use File;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class CMSGeneratorCommand extends Command
{
    protected $signature = 'cms:generate {--task=} {--file=}';
    protected $description = 'Install Generate CMS Generator';
    private $models;
    private $migration_path;
    private $model_path;
    private $controller_path;
    private $api_controller_path;
    private $view_path;
    private $middleware_path;

    public function __construct()
    {
        parent::__construct();
        $this->migration_path = database_path('migrations/cms/');
        $this->model_path = app_path('Models/');
        $this->controller_path = app_path('Http/Controllers/Admin/');
        $this->api_controller_path = app_path('Http/Controllers/Api/');
        $this->middleware_path = app_path('Http/Kernel.php');
        $this->view_path = resource_path('views/admin/');
        $this->middleware_string = "protected \$routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
    ];";

    }

    protected function getModelStub()
    {
        return __DIR__ . '/../stubs/model.stub';
    }
    protected function getMigrationStub()
    {
        return __DIR__ . '/../stubs/migration.stub';
    }
    protected function getMigrationAddProviderStub()
    {
        return __DIR__ . '/../stubs/add-provider.stub';
    }
    protected function getMigrationPermissionStub()
    {
        return __DIR__ . '/../stubs/permission.stub';
    }
    protected function getControllerStub()
    {
        return __DIR__ . '/../stubs/controller.stub';
    }
    protected function getApiControllerStub()
    {
        return __DIR__ . '/../stubs/api_controller.stub';
    }
    protected function getRouteWebStub()
    {
        return __DIR__ . '/../stubs/route-web.stub';
    }
    protected function getRouteApiStub()
    {
        return __DIR__ . '/../stubs/route-api.stub';
    }
    protected function getViewStub()
    {
        return [
            'index' => __DIR__ . '/../stubs/views/index.stub',
            'create' => __DIR__ . '/../stubs/views/create.stub',
            'edit' => __DIR__ . '/../stubs/views/edit.stub',
            'form' => __DIR__ . '/../stubs/views/form.stub',
        ];
    }

    public function handle()
    {
        $this->getJsonFile();
        $ModelStub = File::get($this->getModelStub());
        $MigrationStub = File::get($this->getMigrationStub());
        $ControllerStub = File::get($this->getControllerStub());

        if ($this->option('task') == "automigrate") {
            $this->Automigration();
            exec('composer dump-autoload');
            $this->alert("DONE");
            exit();
        }

        if ($this->option('task') == "update") {
            if (!$this->option('file')) {
                $this->alert("Please select file with options --file=filename");
                exit();
            }
            $this->alert('update with file :'.  $this->option('file'));
            $this->getJsonFile($this->option('file'));
            $this->mainGenerator($ModelStub, $MigrationStub, $ControllerStub);
            $this->Automigration();
            exec('composer dump-autoload');
            $this->alert("DONE");
            exit();
        }
        $this->alert("SUPRB CMS BUILDER by @ametsuramet");
        // ADMIN LTE
        // $this->info("Publish CMS-BUILDER JSON");
        // if (File::exists(base_path('cmsbuilder.json'))) {
        //     File::delete(base_path('cmsbuilder.json'));
        // }
        // $this->call('vendor:publish', [
        //     "--provider" => "Suprb\CmsGenerator\CmsGeneratorServiceProvider",
        //     "--tag" => "cmsbuilder-json",
        //     "--force" => "",
        // ]);

        $this->info("Publish Auth Page");
        $this->call('vendor:publish', [
            "--provider" => "Suprb\CmsGenerator\CmsGeneratorServiceProvider",
            "--tag" => "cmsbuilder-auth-view",
            "--force" => "",
        ]);

        $this->call('storage:link');

        $this->thirdParty();
        app()['cache']->forget('spatie.permission.cache');

        //php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="migrations"
        // $this->info("Create Permission DB");
        // $this->call('vendor:publish', [
        //     "--provider" => "Spatie\Permission\PermissionServiceProvider",
        //     "--tag" => "migrations",
        //     "--force" => "",
        // ]);
        //CREATE MODEL FOLDER
        File::makeDirectory($this->model_path, $mode = 0777, true, true);
        //CREATE MIGRATION FOLDER
        File::makeDirectory($this->migration_path, $mode = 0777, true, true);
        //CREATE CONTROLLER FOLDER
        File::makeDirectory($this->controller_path, $mode = 0777, true, true);
        //CREATE API CONTROLLER FOLDER
        File::makeDirectory($this->api_controller_path, $mode = 0777, true, true);

        // dd([$this->migration_path, $this->model_path, $this->controller_path, $this->view_path]);
        
        $this->mainGenerator($ModelStub, $MigrationStub, $ControllerStub);

        $add_provider = File::get($this->getMigrationAddProviderStub());
        $permission = File::get($this->getMigrationPermissionStub());
        file_put_contents(database_path('migrations/2018_10_27_150248_add_provider_id.php'), $add_provider);
        file_put_contents(database_path('migrations/2018_10_27_145043_create_permission_tables.php'), $permission);
        if ($this->confirm('Do Automigration?')) {
            $this->Automigration();
        }

        exec('composer dump-autoload');
        $this->alert("DONE");
    }

    public function mainGenerator($ModelStub, $MigrationStub, $ControllerStub) 
    {
        $this->models->each(function ($model) use ($ModelStub, $MigrationStub, $ControllerStub) {
            $show_menu = 1;
            if (isset($model->menu)) {
                if (!$model->menu) {
                    $show_menu = 0;
                }
            }
            $this->generateModel($model, $ModelStub);
            $this->generateMigration($model, $MigrationStub);
            $this->generateController($model, $ControllerStub);
            $this->generateRequest($model);

            if ($show_menu) {
                $this->generateView($model);
            }
        });
        if ($this->option('task') != "update") {
            $this->appendRouteWeb();
            $this->appendRouteApi();
        }
    }

    public function Automigration() 
    {
        $this->line("start migration ....");
        $this->call('migrate');
        $this->call('migrate', [
            '--path' => '/database/migrations/cms',
        ]);
        $this->info("Migration Succeed");
        \DB::table('users')->where('email', 'admin@admin')->delete();
        \App\User::create([
            'id' => 1,
            'name' => 'admin',
            'email' => 'admin@admin',
            'password' => bcrypt('admin'),
        ]);
        $this->models->each(function ($model) {
            $this->generatePermission($model);
        });
    }

    public function generatePermission($model)
    {
        Permission::create(['name' => 'create ' . str_plural(snake_case($model->name))]);
        Permission::create(['name' => 'read ' . str_plural(snake_case($model->name))]);
        Permission::create(['name' => 'update ' . str_plural(snake_case($model->name))]);
        Permission::create(['name' => 'delete ' . str_plural(snake_case($model->name))]);
        Permission::create(['name' => 'menu ' . str_plural(snake_case($model->name))]);
    }

    public function thirdParty()
    {
        // ADMIN LTE
        $this->info("Publish AdminLTE Assets");
        $this->call('vendor:publish', [
            "--provider" => "JeroenNoten\LaravelAdminLte\ServiceProvider",
            "--tag" => "assets",
            "--force" => "",
        ]);

        $this->info("Publish AdminLTE Views");
        $this->call('vendor:publish', [
            "--provider" => "JeroenNoten\LaravelAdminLte\ServiceProvider",
            "--tag" => "views",
            "--force" => "",
        ]);
        $this->info("Publish AdminLTE Config");
        $this->call('vendor:publish', [
            "--provider" => "JeroenNoten\LaravelAdminLte\ServiceProvider",
            "--tag" => "config",
            "--force" => "",
        ]);

        $adminlte = File::get(__DIR__ . '/../stubs/adminlte.stub');
        $mainNavigation = "";
        $masterNavigation = "";

        foreach ($this->models as $key => $model) {
            $show_menu = 1;
            $master_data = 0;
            if (isset($model->menu)) {
                if (!$model->menu) {
                    $show_menu = 0;
                }
            }

            if (isset($model->master_data)) {
                if ($model->master_data) {
                    $master_data = 1;
                }
            }

            if ($show_menu && !$master_data) {
                $mainNavigation .= "\t\t[\n";
                $mainNavigation .= "\t\t\t'text' => '" . $model->name . "',\n";
                $mainNavigation .= "\t\t\t'url'  => 'admin/" . str_plural(snake_case($model->name)) . "',\n";
                $mainNavigation .= "\t\t],\n";
            }

            if ($show_menu && $master_data) {
                $masterNavigation .= "\t\t[\n";
                $masterNavigation .= "\t\t\t'text' => '" . $model->name . "',\n";
                $masterNavigation .= "\t\t\t'url'  => 'admin/" . str_plural(snake_case($model->name)) . "',\n";
                $masterNavigation .= "\t\t],\n";
            }
        }
        $adminlte = str_replace("{{mainNavigation}}", $mainNavigation, $adminlte);
        $adminlte = str_replace("{{masterNavigation}}", $masterNavigation, $adminlte);
        file_put_contents(config_path('adminlte.php'), $adminlte);

        //LARACAST FLASH
        $this->info("Publish LARACAST FLASH");
        $this->call('vendor:publish', [
            "--provider" => "Laracasts\Flash\FlashServiceProvider",
            "--force" => "",
        ]);

        $this->info("Publish LARACAST PERMISSION Config");
        $this->call('vendor:publish', [
            "--provider" => "Spatie\Permission\PermissionServiceProvider",
            "--tag" => "config",
            "--force" => "",
        ]);

        $model_user = File::get(__DIR__ . '/../stubs/UserModel.stub');

        file_put_contents(app_path('User.php'), $model_user);

        $middleware_content = File::get($this->middleware_path);
        $middleware_replace = "protected \$routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'jwt.auth' => 'Tymon\JWTAuth\Middleware\GetUserFromToken',
        'jwt.refresh' => 'Tymon\JWTAuth\Middleware\RefreshToken',
    ];";
        $middleware_content = str_replace($this->middleware_string, $middleware_replace, $middleware_content);
        file_put_contents($this->middleware_path, $middleware_content);

        //SPATIE JWT AUTH
        $this->info("Config  jwt-auth secret");
        $this->call('jwt:secret');

        //SPATIE JWT AUTH
        $this->info("Publish  jwt-auth");
        $this->call('vendor:publish', [
            "--provider" => "Tymon\JWTAuth\Providers\LaravelServiceProvider",
            "--force" => "",
        ]);
    }

    public function appendRouteWeb()
    {
        $Stub = File::get($this->getRouteWebStub());
        $newRoute = "";
        foreach ($this->models as $key => $model) {
            $newRoute .= "\n\t\tRoute::resource('/admin/" . str_plural(snake_case($model->name)) . "', 'Admin\\" . $model->name . "Controller', ['as' => 'admin']);";
        }
        $Stub = str_replace("{{newRoute}}", $newRoute, $Stub);
        $this->info('Append Web Routes');
        file_put_contents(base_path('routes/web.php'), $Stub);
    }
    public function appendRouteApi()
    {
        $Stub = File::get($this->getRouteApiStub());
        $newRouteApi = "";
        foreach ($this->models as $key => $model) {
            if ($model->resource) {
                $newRouteApi .= "\n\t\tRoute::resource('/" . str_plural(snake_case($model->name)) . "', 'Api\\" . $model->name . "Controller', ['as' => 'api']);";
            }
        }
        $Stub = str_replace("{{newRouteApi}}", $newRouteApi, $Stub);
        $this->info('Append Api Routes');
        file_put_contents(base_path('routes/api.php'), $Stub);
        $api_config = File::get(app_path('Providers/RouteServiceProvider.php'));
        $api_config = str_replace("prefix('api')", "prefix('api/v1')", $api_config);
        $api_config = str_replace("->middleware('api')", "//->middleware('api')", $api_config);
        file_put_contents(app_path('Providers/RouteServiceProvider.php'), $api_config);
        $auth_controller = File::get(__DIR__ . '/../stubs/AuthenticateController.stub');
        file_put_contents(app_path('Http/Controllers/Auth/AuthenticateController.php'), $auth_controller);

    }

    public function generateView($model)
    {
        $views = $this->getViewStub();
        foreach ($views as $key => $view) {
            File::makeDirectory($this->view_path . str_plural(snake_case($model->name)) . "/", $mode = 0777, true, true);
            $Stub = File::get($view);
            $Stub = str_replace("{{Model}}", $model->name, $Stub);
            $Stub = str_replace("{{name}}", str_plural(snake_case($model->name)), $Stub);
            if ($key == 'form') {
                $formItems = "";
                foreach ($model->schema as $schema) {
                    $hide_form = false;
                    if (isset($schema->hide_form)) {
                        if ($schema->hide_form) {
                            $hide_form = true;
                        }
                    }
                    if (!$hide_form) {
                        $field = implode(" ", (explode("_", $schema->field)));
                        $field = ucwords($field);
                        if (isset($schema->label)) {
                            $field = $schema->label;
                        }
                        $formItems .= "\n\t\t\t\t<div class='form-group'>";
                        $formItems .= "\n\t\t\t\t\t\t<label for=''>" . $field . "</label>";

                        if ($schema->form_type == 'file') {
                            $formItems .= "\n\t\t\t\t\t\t<input name='" . $schema->field . "' type='" . $schema->form_type . "' />";
                            $formItems .= "\n\t\t\t\t\t\t<input name='" . $schema->field . "' type='hidden' class='form-control' value='{!! \$edit ? \$data->" . $schema->field . " : null !!}' />";
                            $formItems .= "\n\t\t\t\t\t\t@if(\$edit)";
                            $formItems .= "\n\t\t\t\t\t\t<br><label>Preview</label><br>";
                            $formItems .= "\n\t\t\t\t\t\t<img src='{!! \$edit ? Storage::url(\$data->" . $schema->field . ") : \"/images/notfound.jpeg\" !!}' width='300' onerror=\"this.src='/images/notfound.jpeg';\" />";
                            $formItems .= "\n\t\t\t\t\t\t@endif";
                        } else if ($schema->form_type == 'radio' || $schema->form_type == 'checkbox') {
                            foreach ($schema->options as $option) {
                                $formItems .= "\n\t\t\t\t\t\t<input name='" . $schema->field . "' type='" . $schema->form_type . "' value='" . $option->value . "' {!! \$edit ? \$data->" . $schema->field . " == '" . $option->value . "' ? 'CHECKED' : null : null !!} /> " . $option->label;
                            }
                        } else if ($schema->form_type == 'textarea') {
                            $formItems .= "\n\t\t\t\t\t\t<textarea name='" . $schema->field . "'  class='form-control' rows='5'>{!! \$edit ? \$data->" . $schema->field . " : null !!}</textarea>";
                        } else if ($schema->form_type == 'select') {
                            $formItems .= "\n\t\t\t\t\t\t<select name='" . $schema->field . "'  class='form-control'>";
                            $formItems .= "\n\t\t\t\t\t\t\t\t<option value='' >Select First</option>";
                            foreach ($schema->options as $option) {
                                $formItems .= "\n\t\t\t\t\t\t\t\t<option value='" . $option->value . "' {!! \$edit ? \$data->" . $schema->field . " == '" . $option->value . "' ? 'SELECTED' : null : null !!}>" . $option->label . "</option>";
                            }
                            $formItems .= "\n\t\t\t\t\t\t</select>";
                        } elseif ($schema->form_type == "datetime-local") {
                            $formItems .= "\n\t\t\t\t\t\t<input name='" . $schema->field . "' type='" . $schema->form_type . "' class='form-control' value='{!! \$edit ? \$data->" . $schema->field . " ? \$data->" . $schema->field . "->format(\"Y-m-d\TH:i\") : null : null !!}' />";
                        } else {
                            $formItems .= "\n\t\t\t\t\t\t<input name='" . $schema->field . "' type='" . $schema->form_type . "' class='form-control' value='{!! \$edit ? \$data->" . $schema->field . " : null !!}' />";
                        }
                        $formItems .= "\n\t\t\t\t</div>";
                    }
                }
                $Stub = str_replace("{{formItems}}", $formItems, $Stub);

            }
            if ($key == 'index') {
                $tableHeader = "";
                $tableBody = "";
                foreach ($model->schema as $schema) {
                    $hide_column = false;
                    if (isset($schema->hide_column)) {
                        if ($schema->hide_column) {
                            $hide_column = true;
                        }
                    }
                    if (!$hide_column) {
                    $field = implode(" ", (explode("_", $schema->field)));
                    $field = ucwords($field);
                    if (isset($schema->label)) {
                        $field = $schema->label;
                    }
                    $tableHeader .= "\n\t\t\t\t\t\t\t\t<th>" . $field . "</th>";
                        if ($schema->type == 'boolean') { 
                            $tableBody .= "\n\t\t\t\t\t\t\t\t\t<td>{!! \$d->" . $schema->field . " ? '<span class=\'glyphicon glyphicon-ok-sign text-success\'></span>' : '<span class=\'glyphicon glyphicon-remove-sign text-danger\'></span>' !!}</td>";
                        } else if ($schema->form_type == 'file') {
                            $tableBody .= "\n\t\t\t\t\t\t\t\t\t<td><img src='{!! Storage::url(\$d->" . $schema->field . ") !!}' width='150' onerror=\"this.src='/images/notfound.jpeg';\" /></td>";
                        } else {
                            if (isset($schema->relation_data)) {
                                $relation_data = explode(".", $schema->relation_data);
                                $tableBody .= "\n\t\t\t\t\t\t\t\t\t<td>{!! optional(\$d->" . current($relation_data) . ")->";
                                next($relation_data);
                                $tableBody .= current($relation_data)." !!}</td>";
                            }  else {
                                $tableBody .= "\n\t\t\t\t\t\t\t\t\t<td>{!! \$d->" . $schema->field . " !!}</td>";
                            }
                        }    
                    }
                }
                $Stub = str_replace("{{tableHeader}}", $tableHeader, $Stub);
                $Stub = str_replace("{{tableBody}}", $tableBody, $Stub);

            }
            $this->info('Create View : ' . $this->view_path . str_plural(snake_case($model->name)) . "/" . $key . ".blade.php");

            file_put_contents($this->view_path . str_plural(snake_case($model->name)) . "/" . $key . ".blade.php", $Stub);
        }
        //CREATE CONTROLLER FOLDER
        File::makeDirectory($this->view_path . "/misc/", $mode = 0777, true, true);
        $alert = File::get(__DIR__ . '/../stubs/views/alert.stub');
        file_put_contents($this->view_path . "/misc/alert.blade.php", $alert);
        $homeController = File::get(__DIR__ . '/../stubs/HomeController.stub');
        file_put_contents($this->controller_path . "HomeController.php", $homeController);
        $homeView = File::get(__DIR__ . '/../stubs/views/home.stub');
        file_put_contents(resource_path('views/home.blade.php'), $homeView);

    }

    public function generateController($model, $Stub)
    {
        $upload = "";
        foreach ($model->schema as $key => $schema) {
            if ($schema->form_type == 'file') {
                $upload .= "\n\t\t\tif(\$request->hasFile('" . $schema->field . "')) {";
                $upload .= "\n\t\t\t\t\$path = \$request->" . $schema->field . "->store('images', env('FILESYSTEM_DRIVER', 'public'));";
                $upload .= "\n\t\t\t\t\$input['" . $schema->field . "'] = \$path;";
                $upload .= "\n\t\t\t}";
            }
            if ($schema->form_type == 'datetime-local') {
                $upload .= "\n\t\t\t\$input['" . $schema->field . "'] = \Carbon\Carbon::parse(\$input['" . $schema->field . "'])->format('Y-m-d H:i:s');";
            }
            if ($schema->field == 'password') {
                $upload .= "\n\t\t\t\$input['" . $schema->field . "'] = bcrypt(\$input['" . $schema->field . "']);";
            }
        }
        $Stub = str_replace("DummyClass", $model->name . 'Controller', $Stub);
        $Stub = str_replace("DummyNamespace", 'App\Http\Controllers\Admin', $Stub);
        $Stub = str_replace("{{DummyModel}}", $model->name, $Stub);
        $Stub = str_replace("{{name}}", str_plural(snake_case($model->name)), $Stub);
        $Stub = str_replace("{{upload}}", $upload, $Stub);
        $this->info('Create Controller Admin :' . $this->controller_path . $model->name . 'Controller.php');

        file_put_contents($this->controller_path . $model->name . 'Controller.php', $Stub);

        if ($model->resource) {
            $Stub2 = File::get($this->getApiControllerStub());
            $Stub2 = str_replace("DummyClass", $model->name . 'Controller', $Stub2);
            $Stub2 = str_replace("DummyNamespace", 'App\Http\Controllers\Api', $Stub2);
            $Stub2 = str_replace("{{DummyModel}}", $model->name, $Stub2);
            $Stub2 = str_replace("{{name}}", str_plural(snake_case($model->name)), $Stub2);

            file_put_contents($this->api_controller_path . $model->name . 'Controller.php', $Stub2);

        }
    }

    public function generateRequest($model)
    {
        $path = app_path('Http/Requests');
        foreach (["Index","Create", "Update", "Delete", "ApiCreate", "ApiUpdate"] as $key => $value) {
            $this->info('Create Request :' . $model->name . $value . 'Request');
            exec('php artisan make:request ' . $model->name . $value . 'Request');
            $content = File::get($path . '/' . $model->name . $value . 'Request.php');
            $content = str_replace('return false;', 'return true;', $content);
            file_put_contents($path . '/' . $model->name . $value . 'Request.php', $content);
        }
    }

    public function generateMigration($model, $Stub)
    {
        $class_name = str_plural($model->name);
        if (isset($model->class_name)) {
            $class_name = $model->class_name;
        }
        $Stub = str_replace("DummyClass", 'Create' . $class_name . 'Table', $Stub);
        $Stub = str_replace("{{table}}", str_plural(snake_case($model->name)), $Stub);
        $fields = "";
        foreach ($model->schema as $key => $schema) {
            $type = explode(":", $schema->type);
            if(current($type) == 'float' || current($type) == 'double' || current($type) == 'decimal') {
                $length = '10, 2';
                if (isset($type[1])) {
                    $length = $type[1];
                }
                $fields .= "\n\t\t\t\$table->" . current($type) . "('" . $schema->field . "', ".$length.")";
            } else {
                $length = null;
                if (isset($type[1])) {
                    if ($type[1]) {
                        $length = $type[1];
                    }
                }
                if ($length && is_numeric($length)) {
                    $fields .= "\n\t\t\t\$table->" . current($type) . "('" . $schema->field . "', ".$length.")";
                } else {
                    $fields .= "\n\t\t\t\$table->" . current($type) . "('" . $schema->field . "')";
                }
            }
            if (isset($schema->nullable)) {
                if ($schema->nullable) {
                    $fields .= "->nullable()";
                }
            }
            if (isset($schema->default)) {
                if ($schema->default == "0" && current($type) == "boolean") {
                    $fields .= "->default(0)";
                } elseif ($schema->default == "now()" && current($type) == "dateTime") {
                    $fields .= "->default(\DB::raw('CURRENT_TIMESTAMP'))";
                } else
                if ($schema->default && current($type) != 'jsonb' && current($type) != 'text' && current($type) != 'json' && current($type) != 'geometry') {
                    $fields .= "->default(" . (strtolower($schema->default) == 'null' || strtolower($schema->default) == 'true' || strtolower($schema->default) == 'false' ? $schema->default : "'" . $schema->default . "'") . ")";
                }
            }
            if (in_array("unsigned", $type)) {
                $fields .= "->unsigned()";
            }

            $fields .= ";";
        }
        if ($model->softdelete) {
            $fields .= "\n\t\t\t\$table->softDeletes();";
        }
        // $fields .= "\n\t\t$table->" . $schema->field . "('" . $schema->field . "')";

        $Stub = str_replace("{{fields}}", $fields, $Stub);
        $Stub = str_replace("{{schema_down}}", "Schema::dropIfExists('" . str_plural(snake_case($model->name)) . "');", $Stub);
        $suffix = "_create_" . str_plural(snake_case($model->name)) . "_table.php";
        $filename = date("Y_m_d_His") . $suffix;
        $id = 123;
        $handler = opendir($this->migration_path);
        while ($file = readdir($handler)) {
            if ($file !== "." && $file !== "..") {
                if (preg_match("/{$suffix}/", $file)) {
                    unlink($this->migration_path . $file);
                }
            }
        }
        $this->info('Create Migration :' . $this->migration_path . $filename);
        file_put_contents($this->migration_path . $filename, $Stub);

        // echo $Stub;
    }

    public function generateModel($model, $Stub)
    {
        //GENERATE TEMPLATE
        $Stub = str_replace("DummyNamespace", "App\Models", $Stub);
        $Stub = str_replace("DummyClass", $model->name, $Stub);
        if (isset($model->primaryKey)) {
            $Stub = str_replace("{{primaryKey}}", 'protected $primaryKey = "' . $model->primaryKey . '";', $Stub);

        } else {
            $Stub = str_replace("{{primaryKey}}", '', $Stub);
        }
        if ($model->softdelete) {
            $Stub = str_replace("{{softDeletes}}", "use SoftDeletes;", $Stub);
            $Stub = str_replace("{{useSoftDeletes}}", "use Illuminate\Database\Eloquent\SoftDeletes;", $Stub);
        } else {
            $Stub = str_replace("{{softDeletes}}", "", $Stub);
            $Stub = str_replace("{{useSoftDeletes}}", "", $Stub);
        }
        
        $boot = "";

        if (isset($model->observer)) {
            $boot  = "\n\tpublic static function boot()";
            $boot .= "\n\t{";
            $boot .= "\n\t\tparent::boot();";
            $boot .= "\n\t\t\$class = get_called_class();";
            foreach ($model->observer as $key => $observer) {
                $boot .= "\n\t\t\$class::observe(new \App\Observers\\".$observer.");";
            }
            $boot .= "\n\t}";
        }
        $Stub = str_replace("{{boot}}", $boot, $Stub);
        $custom_function = "";
        $Stub = str_replace("{{table}}", str_plural(snake_case($model->name)), $Stub);
        if (isset($model->mongo)) {
            if ($model->mongo) {
                $Stub = str_replace("use Illuminate\Database\Eloquent\Model;", 'use Jenssegers\Mongodb\Eloquent\Model;', $Stub);
                $Stub = str_replace("{{connection}}", "protected \$connection = 'mongodb';\n", $Stub);
                if ($model->primaryKey == "_id") {
                    $custom_function .= "\n\tpublic function getIdAttribute(\$value = null)";
                    $custom_function .= "\n\t{";
                    $custom_function .= "\n\t\treturn new \MongoDB\BSON\ObjectId(\$this->attributes['_id']);";
                    $custom_function .= "\n\t}";
                }

                foreach ($model->schema as $key => $schema) {
                    if (isset($schema->mongo_id)) {
                        if ($schema->mongo_id) {
                            $custom_function .= "\n\tpublic function set".studly_case($schema->field)."Attribute(\$value)";
                            $custom_function .= "\n\t{";
                            $custom_function .= "\n\t\tif (\$this->attributes['".$schema->field."'])";
                            $custom_function .= "\n\t\t\$this->attributes['".$schema->field."'] = new \MongoDB\BSON\ObjectId(\$value);";
                            $custom_function .= "\n\t}";
                        }
                    }
                    if ($schema->type == "dateTime") {
                        $custom_function .= "\n\tpublic function set".studly_case($schema->field)."Attribute(\$value)";
                        $custom_function .= "\n\t{";
                        $custom_function .= "\n\t\t\$this->attributes['".$schema->field."'] = new \MongoDB\BSON\UTCDateTime(new \DateTime(str_replace(\"T\", \" \", \$value)));";
                        $custom_function .= "\n\t}";
                    }
                }
            }
        }
        $Stub = str_replace("{{connection}}", "\n", $Stub);
        $Stub = str_replace("{{custom_function}}", $custom_function, $Stub);
        $fillable = "[";
        $searchable = "";
        $casts = "";
        $dates = "";
        foreach ($model->schema as $key => $schema) {
            $fillable .= "\n\t\t'" . $schema->field . "',";
            switch ($schema->type) {
                case 'jsonb':
                case 'json':
                    $casts .= "\n\t\t'" . $schema->field . "' => 'collection',";
                    break;
                case 'float':
                    $casts .= "\n\t\t'" . $schema->field . "' => 'float',";
                    break;
                case 'integer':
                    $casts .= "\n\t\t'" . $schema->field . "' => 'integer',";
                    break;
                case 'boolean':
                    $casts .= "\n\t\t'" . $schema->field . "' => 'boolean',";
                    break;
                case 'dateTime':
                    $casts .= "\n\t\t'" . $schema->field . "' => 'datetime',";
                    $dates .= "\n\t\t'" . $schema->field . "',";
                    break;
                default:
                    if (isset($schema->hide_casts)) {
                        if ($schema->hide_casts) {
                            break;
                        }
                    }
                    $casts .= "\n\t\t'" . $schema->field . "' => 'string',";
                    break;
            }
            if ($schema->searchable) {
                if (!$key) {
                    $searchable .= "\n\t\t\t\$query->where('" . $schema->field . "', 'like', '%' . \$value . '%');";
                } else {
                    $searchable .= "\n\t\t\t\$query->orWhere('" . $schema->field . "', 'like', '%' . \$value . '%');";
                }
            }
        }

        $Stub = str_replace("{{searchable}}", $searchable, $Stub);
        $Stub = str_replace("{{casts}}", $casts, $Stub);
        $Stub = str_replace("{{dates}}", $dates, $Stub);

        $fillable .= "\n\t]";
        $Stub = str_replace("{{fillable}}", $fillable, $Stub);
        $Stub = str_replace("{{hidden}}", "", $Stub);

        if (count($model->relations)) {
            $relationships = "";
            foreach ($model->relations as $relation) {
                $relationships .= "\n\tpublic function ";
                $class_name = snake_case($relation->target);
                if ($relation->type == "has_many") {
                    $class_name = str_plural(snake_case($relation->target));
                }
                if (isset($relation->class_name)) {
                    $class_name = $relation->class_name;
                }
                if ($relation->type == 'belongs_to') {
                    $relation_key = "";
                    if (isset($relation->foreign_key) && isset($relation->other_key)) {
                        $relation_key = ", '" . $relation->foreign_key . "', '" . $relation->other_key . "'";
                    }
                    $relationships .= $class_name . "()";
                    $relationships .= "\n\t{";
                    $relationships .= "\n\t\treturn \$this->belongsTo(" . $relation->target . "::class" . $relation_key . ");";
                }
                $relation_key = "";
                if (isset($relation->foreign_key) && isset($relation->local_key)) {
                    $relation_key = ", '" . $relation->foreign_key . "', '" . $relation->local_key . "'";
                }
                if ($relation->type == 'has_many') {
                    $relationships .= $class_name . "()";
                    $relationships .= "\n\t{";
                    $relationships .= "\n\t\treturn \$this->hasMany(" . $relation->target . "::class" . $relation_key . ");";
                }
                if ($relation->type == 'has_one') {
                    $relationships .= $class_name . "()";
                    $relationships .= "\n\t{";
                    $relationships .= "\n\t\treturn \$this->hasOne(" . $relation->target . "::class" . $relation_key . ");";
                }
                $relationships .= "\n\t}";
            }
            $Stub = str_replace("{{relationships}}", $relationships, $Stub);
        } else {
            $Stub = str_replace("{{relationships}}", "", $Stub);
        }

        //CREATE FILE
        $this->info('Create Model :' . $this->model_path . $model->name . '.php');
        file_put_contents($this->model_path . $model->name . '.php', $Stub);
        if ($model->resource) {
            $collection = $model->name . "Collection";
            $this->info("Create Resource " . $collection);
            exec('php artisan make:resource ' . $collection);
            exec('php artisan make:resource ' . $model->name);
        }
    }

    public function getJsonFile($filename = 'cmsbuilder.json')
    {
        $file = File::get(base_path($filename));
        $this->models = collect(json_decode($file));
    }

}
