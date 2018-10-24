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
    private $view_path;

    public function __construct()
    {
        parent::__construct();
        $this->migration_path = database_path('migrations/cms/');
        $this->model_path = app_path('Models/');
        $this->controller_path = app_path('Http/Controllers/Admin/');
        $this->view_path = resource_path('views/admin/');

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
    protected function getRouteWebStub()
    {
        return __DIR__ . '/../stubs/route-web.stub';
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
        //CREATE MODEL FOLDER
        File::makeDirectory($this->model_path, $mode = 0777, true, true);
        //CREATE MIGRATION FOLDER
        File::makeDirectory($this->migration_path, $mode = 0777, true, true);
        //CREATE CONTROLLER FOLDER
        File::makeDirectory($this->controller_path, $mode = 0777, true, true);

        // dd([$this->migration_path, $this->model_path, $this->controller_path, $this->view_path]);
        $ModelStub = File::get($this->getModelStub());
        $MigrationStub = File::get($this->getMigrationStub());
        $ControllerStub = File::get($this->getControllerStub());
        $this->models->each(function ($model) use ($ModelStub, $MigrationStub, $ControllerStub) {
            $this->generateModel($model, $ModelStub);
            $this->generateMigration($model, $MigrationStub);
            $this->generateController($model, $ControllerStub);
            $this->generateView($model);
        });
        $this->appendRouteWeb();

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
    }

    public function generateController($model, $Stub)
    {
        $Stub = str_replace("DummyClass", $model->name . 'Controller', $Stub);
        $Stub = str_replace("DummyNamespace", 'App\Http\Controllers\Admin', $Stub);
        $Stub = str_replace("{{DummyModel}}", $model->name, $Stub);
        $Stub = str_replace("{{name}}", str_plural(snake_case($model->name)), $Stub);
        $this->info('Create Controller :' . $this->controller_path . $model->name . 'Controller.php');

        file_put_contents($this->controller_path . $model->name . 'Controller.php', $Stub);
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
        foreach ($model->schema as $key => $schema) {
            $fillable .= "\n\t\t'" . $schema->field . "',";
        }
        $fillable .= "\n\t\t]";
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
    }

    public function getJsonFile()
    {
        $file = File::get(base_path('cmsbuilder.json'));
        $this->models = collect(json_decode($file));
    }

}
