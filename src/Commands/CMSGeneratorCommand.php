<?php

namespace Suprb\CmsGenerator\Commands;

use File;
use Illuminate\Console\Command;

class CMSGeneratorCommand extends Command
{
    protected $signature = 'cms:install';
    protected $description = 'Install Generate CMS Generator';
    private $models;

    public function __construct()
    {
        parent::__construct();
    }

    protected function getModelStub()
    {
        return __DIR__ . '/../stubs/model.stub';
    }
    protected function getMigrationStub()
    {
        return __DIR__ . '/../stubs/migration.stub';
    }

    public function handle()
    {
        $this->getJsonFile();
        // dd($this->models);
        $ModelStub = File::get($this->getModelStub());
        $MigrationStub = File::get($this->getMigrationStub());
        $this->models->each(function ($model) use ($ModelStub, $MigrationStub) {
            $this->generateModel($model, $ModelStub);
            $this->generateMigration($model, $MigrationStub);
        });
    }

    public function generateMigration($model, $MigrationStub)
    {
        $MigrationStub = str_replace("DummyClass", 'Create' . str_plural($model->name) . 'Table', $MigrationStub);
        $MigrationStub = str_replace("{{table}}", str_plural(snake_case($model->name)), $MigrationStub);
        $fields = "";
        foreach ($model->schema as $key => $schema) {
            $fields .= "\n\t\t\t\$table->" . $schema->type . "('" . $schema->field . "')";
            if ($schema->nullable) {
                $fields .= "->nullable()";
            }
            if ($schema->default) {
                $fields .= "->default(" . ($schema->default == 'NULL' ? $schema->default : "'" . $schema->default . "'") . ")";
            }

            $fields .= ";";
        }
        // $fields .= "\n\t\t$table->" . $schema->field . "('" . $schema->field . "')";

        $MigrationStub = str_replace("{{fields}}", $fields, $MigrationStub);
        $MigrationStub = str_replace("{{schema_down}}", "Schema::dropIfExists('" . str_plural(snake_case($model->name)) . "');", $MigrationStub);

        $filename = date("Y_m_d_His") . "_create_" . str_plural(snake_case($model->name)) . "_table.php";

        echo $filename;
    }
    public function generateModel($model, $ModelStub)
    {
        //GENERATE TEMPLATE
        $ModelStub = str_replace("DummyNamespace", "App\Models", $ModelStub);
        $ModelStub = str_replace("DummyClass", $model->name, $ModelStub);
        if ($model->primaryKey) {
            $ModelStub = str_replace("{{primaryKey}}", 'protected $primaryKey = "' . $model->primaryKey . '";', $ModelStub);

        } else {
            $ModelStub = str_replace("{{primaryKey}}", '', $ModelStub);
        }
        if ($model->softdelete) {
            $ModelStub = str_replace("{{softDeletes}}", "use SoftDeletes;", $ModelStub);
            $ModelStub = str_replace("{{useSoftDeletes}}", "use Illuminate\Database\Eloquent\SoftDeletes;", $ModelStub);
        } else {
            $ModelStub = str_replace("{{softDeletes}}", "", $ModelStub);
            $ModelStub = str_replace("{{useSoftDeletes}}", "", $ModelStub);
        }

        $ModelStub = str_replace("{{table}}", str_plural(snake_case($model->name)), $ModelStub);
        $fillable = "[";
        foreach ($model->schema as $key => $schema) {
            $fillable .= "\n\t\t'" . $schema->field . "',";
        }
        $fillable .= "\n\t\t]";
        $ModelStub = str_replace("{{fillable}}", $fillable, $ModelStub);
        $ModelStub = str_replace("{{hidden}}", "", $ModelStub);
        $ModelStub = str_replace("{{relationships}}", "", $ModelStub);

        //CREATE FILE
        // $this->info($ModelStub);
    }

    public function getJsonFile()
    {
        $file = File::get(base_path('cmsbuilder.json'));
        $this->models = collect(json_decode($file));
    }

}
