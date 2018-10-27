<?php

namespace Suprb\CmsGenerator\Commands;

use File;
use Illuminate\Console\Command;

class CMSGeneratorCommand extends Command
{
    protected $signature = 'cms:install';
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

        $this->getJsonFile();
        $this->thirdParty();

        //CREATE MODEL FOLDER
        File::makeDirectory($this->model_path, $mode = 0777, true, true);
        //CREATE MIGRATION FOLDER
        File::makeDirectory($this->migration_path, $mode = 0777, true, true);
        //CREATE CONTROLLER FOLDER
        File::makeDirectory($this->controller_path, $mode = 0777, true, true);
        //CREATE API CONTROLLER FOLDER
        File::makeDirectory($this->api_controller_path, $mode = 0777, true, true);

        // dd([$this->migration_path, $this->model_path, $this->controller_path, $this->view_path]);
        $ModelStub = File::get($this->getModelStub());
        $MigrationStub = File::get($this->getMigrationStub());
        $ControllerStub = File::get($this->getControllerStub());
        $this->models->each(function ($model) use ($ModelStub, $MigrationStub, $ControllerStub) {
            $this->generateModel($model, $ModelStub);
            $this->generateMigration($model, $MigrationStub);
            $this->generateController($model, $ControllerStub);
            $this->generateView($model);
            $this->generateRequest($model);
        });
        $this->appendRouteWeb();
        $this->appendRouteApi();
        if ($this->confirm('Do Automigration?')) {
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
        }

        exec('composer dump-autoload');
        $this->alert("DONE");
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

        foreach ($this->models as $key => $model) {
            # code...
            $mainNavigation .= "\t\t[\n";
            $mainNavigation .= "\t\t\t'text' => '" . $model->name . "',\n";
            $mainNavigation .= "\t\t\t'url'  => 'admin/" . str_plural(snake_case($model->name)) . "',\n";
            $mainNavigation .= "\t\t],\n";
        }
        $adminlte = str_replace("{{mainNavigation}}", $mainNavigation, $adminlte);
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
            $newRouteApi .= "\n\t\tRoute::resource('/" . str_plural(snake_case($model->name)) . "', 'Api\\" . $model->name . "Controller', ['as' => 'api']);";
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
                    $field = implode(" ", (explode("_", $schema->field)));
                    $formItems .= "\n\t\t\t\t<div class='form-group'>";
                    $formItems .= "\n\t\t\t\t\t\t<label for=''>" . ucwords($field) . "</label>";

                    if ($schema->form_type == 'radio' || $schema->form_type == 'checkbox') {
                        foreach ($schema->options as $option) {
                            $formItems .= "\n\t\t\t\t\t\t<input name='" . $schema->field . "' type='" . $schema->form_type . "' value='" . $option->value . "' /> " . $option->label;
                        }
                    } else if ($schema->form_type == 'textarea') {
                        $formItems .= "\n\t\t\t\t\t\t<textarea name='" . $schema->field . "'  class='form-control' rows='5'>{!! \$edit ? \$data->" . $schema->field . " : null !!}</textarea>";
                    } else if ($schema->form_type == 'select') {
                        $formItems .= "\n\t\t\t\t\t\t<select name='" . $schema->field . "'  class='form-control'>";
                        foreach ($schema->options as $option) {
                            $formItems .= "\n\t\t\t\t\t\t\t\t<option value='" . $option->value . "' >" . $option->label . "</option>";
                        }
                        $formItems .= "\n\t\t\t\t\t\t</select>";
                    } else {
                        $formItems .= "\n\t\t\t\t\t\t<input name='" . $schema->field . "' type='" . $schema->form_type . "' class='form-control' value='{!! \$edit ? \$data->" . $schema->field . " : null !!}' />";
                    }
                    $formItems .= "\n\t\t\t\t</div>";
                }
                $Stub = str_replace("{{formItems}}", $formItems, $Stub);

            }
            if ($key == 'index') {
                $tableHeader = "";
                $tableBody = "";
                foreach ($model->schema as $schema) {
                    $field = implode(" ", (explode("_", $schema->field)));
                    $tableHeader .= "\n\t\t\t\t\t\t\t\t<th>" . ucwords($field) . "</th>";
                    $tableBody .= "\n\t\t\t\t\t\t\t\t\t<td>{!! \$d->" . $schema->field . " !!}</td>";
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
        $Stub = str_replace("DummyClass", $model->name . 'Controller', $Stub);
        $Stub = str_replace("DummyNamespace", 'App\Http\Controllers\Admin', $Stub);
        $Stub = str_replace("{{DummyModel}}", $model->name, $Stub);
        $Stub = str_replace("{{name}}", str_plural(snake_case($model->name)), $Stub);
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
        foreach (["Create", "Edit", "ApiCreate", "ApiEdit"] as $key => $value) {
            $this->info('Create Request :' . $model->name . $value . 'Request');
            exec('php artisan make:request ' . $model->name . $value . 'Request');
            $content = File::get($path . '/' . $model->name . $value . 'Request.php');
            $content = str_replace('return false;', 'return true;', $content);
            file_put_contents($path . '/' . $model->name . $value . 'Request.php', $content);
        }
    }

    public function generateMigration($model, $Stub)
    {
        $Stub = str_replace("DummyClass", 'Create' . str_plural($model->name) . 'Table', $Stub);
        $Stub = str_replace("{{table}}", str_plural(snake_case($model->name)), $Stub);
        $fields = "";
        foreach ($model->schema as $key => $schema) {
            $type = explode(":", $schema->type);
            $fields .= "\n\t\t\t\$table->" . current($type) . "('" . $schema->field . "')";
            if ($schema->nullable) {
                $fields .= "->nullable()";
            }
            if ($schema->default) {
                $fields .= "->default(" . ($schema->default == 'NULL' || $schema->default == 'true' || $schema->default == 'false' ? $schema->default : "'" . $schema->default . "'") . ")";
            }
            if (in_array("unsigned", $type)) {
                $fields .= "->unsigned()";
            }

            $fields .= ";";
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
        if ($model->primaryKey) {
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

        $Stub = str_replace("{{table}}", str_plural(snake_case($model->name)), $Stub);
        $fillable = "[";
        $searchable = "";
        $casts = "";
        foreach ($model->schema as $key => $schema) {
            $fillable .= "\n\t\t'" . $schema->field . "',";
            if ($schema->type == 'jsonb' || $schema->type == 'json') {
                $casts .= "\n\t\t'" . $schema->field . "' => 'collection',";
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

        $fillable .= "\n\t]";
        $Stub = str_replace("{{fillable}}", $fillable, $Stub);
        $Stub = str_replace("{{hidden}}", "", $Stub);

        if (count($model->relations)) {
            $relationships = "";
            foreach ($model->relations as $relation) {
                $relationships .= "\n\tpublic function ";
                if ($relation->type == 'belongs_to') {
                    $relationships .= snake_case($relation->target) . "()";
                    $relationships .= "\n\t{";
                    $relationships .= "\n\t\treturn \$this->belongsTo(" . $relation->target . "::class);";
                }
                if ($relation->type == 'has_many') {
                    $relationships .= str_plural(snake_case($relation->target)) . "()";
                    $relationships .= "\n\t{";
                    $relationships .= "\n\t\treturn \$this->hasMany(" . $relation->target . "::class);";
                }
                if ($relation->type == 'has_one') {
                    $relationships .= snake_case($relation->target) . "()";
                    $relationships .= "\n\t{";
                    $relationships .= "\n\t\treturn \$this->hasOne(" . $relation->target . "::class);";
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

    public function getJsonFile()
    {
        $file = File::get(base_path('cmsbuilder.json'));
        $this->models = collect(json_decode($file));
    }

}
