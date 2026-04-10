<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class CodeGenerator extends Command
{
    protected $signature = 'gen:code {table : 数据库表名} {--module=system : 前端模块目录}';
    protected $description = '根据数据库表自动生成前后端CRUD代码';

    public function handle(): int
    {
        $table = $this->argument('table');
        $module = $this->option('module');
        $columns = DB::select("SHOW FULL COLUMNS FROM {$table}");

        if (empty($columns)) {
            $this->error("表 {$table} 不存在或没有字段");
            return 1;
        }

        $modelName = Str::studly(Str::singular($table));
        $controllerName = "{$modelName}Controller";
        $variableName = Str::camel(Str::singular($table));
        $routeName = Str::kebab(Str::plural(Str::singular($table)));

        $this->info("开始生成代码: 表={$table}, Model={$modelName}");

        $this->generateModel($modelName, $table, $columns);
        $this->generateController($controllerName, $modelName, $variableName, $columns);
        $this->generateFrontendApi($routeName, $modelName);
        $this->generateFrontendView($module, $routeName, $modelName, $columns);

        $this->info('代码生成完成！');
        $this->info("后端: app/Models/{$modelName}.php, app/Http/Controllers/Api/{$controllerName}.php");
        $this->info("前端 API: 请手动添加到 frontend/src/api/{$routeName}.ts");
        $this->info("前端页面: 请查看 frontend/src/views/{$module}/{$routeName}/index.vue");
        $this->newLine();
        $this->warn("请手动在 routes/api.php 中添加路由:");
        $this->line("Route::apiResource('{$routeName}', \\App\\Http\\Controllers\\Api\\{$controllerName}::class);");

        return 0;
    }

    private function generateModel(string $modelName, string $table, array $columns): void
    {
        $fillable = [];
        $casts = [];

        foreach ($columns as $col) {
            $field = $col->Field;
            if (in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at'])) continue;
            $fillable[] = "'{$field}'";

            if (Str::contains($col->Type, 'json')) {
                $casts[$field] = 'array';
            } elseif (Str::contains($col->Type, ['datetime', 'timestamp'])) {
                $casts[$field] = 'datetime';
            }
        }

        $fillableStr = implode(', ', $fillable);
        $castsStr = '';
        if (!empty($casts)) {
            $castLines = [];
            foreach ($casts as $k => $v) {
                $castLines[] = "            '{$k}' => '{$v}'";
            }
            $castsStr = "\n\n    protected function casts(): array\n    {\n        return [\n" . implode(",\n", $castLines) . ",\n        ];\n    }";
        }

        $hasTenant = collect($columns)->contains(fn($c) => $c->Field === 'tenant_id');
        $useTraits = $hasTenant ? "\n    use \\App\\Models\\Traits\\BelongsToTenant;\n" : '';
        $imports = $hasTenant ? "use App\\Models\\Traits\\BelongsToTenant;\n" : '';

        $content = <<<PHP
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
{$imports}
class {$modelName} extends Model
{{$useTraits}
    protected \$table = '{$table}';

    protected \$fillable = [{$fillableStr}];{$castsStr}
}
PHP;

        $path = app_path("Models/{$modelName}.php");
        File::put($path, $content);
        $this->info("  Created: {$path}");
    }

    private function generateController(string $controllerName, string $modelName, string $variableName, array $columns): void
    {
        $searchableFields = collect($columns)
            ->filter(fn($c) => Str::contains($c->Type, ['varchar', 'char', 'text']))
            ->filter(fn($c) => !in_array($c->Field, ['password', 'remember_token', 'deleted_at']))
            ->take(3)
            ->pluck('Field')
            ->toArray();

        $searchLogic = '';
        if (!empty($searchableFields)) {
            $conditions = array_map(
                fn($f) => "\$q->orWhere('{$f}', 'like', \"%{\$kw}%\")",
                $searchableFields
            );
            $first = array_shift($conditions);
            $rest = !empty($conditions) ? "\n                      ->" . implode("\n                      ->", $conditions) : '';
            $searchLogic = <<<PHP

        if (\$request->filled('keywords')) {
            \$kw = \$request->keywords;
            \$query->where(function (\$q) use (\$kw) {
                {$first}{$rest};
            });
        }
PHP;
        }

        $fillableFields = collect($columns)
            ->filter(fn($c) => !in_array($c->Field, ['id', 'created_at', 'updated_at', 'deleted_at']))
            ->pluck('Field')
            ->map(fn($f) => "'{$f}'")
            ->implode(', ');

        $content = <<<PHP
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\\{$modelName};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class {$controllerName} extends Controller
{
    public function index(Request \$request): JsonResponse
    {
        \$query = {$modelName}::query();{$searchLogic}

        \$pageSize = \$request->input('pageSize', 10);
        \$list = \$query->orderBy('id', 'desc')->paginate(\$pageSize);

        return response()->json([
            'code' => 200,
            'data' => ['list' => \$list->items(), 'total' => \$list->total()],
        ]);
    }

    public function store(Request \$request): JsonResponse
    {
        \${$variableName} = {$modelName}::create(\$request->only([{$fillableFields}]));
        return response()->json(['code' => 200, 'msg' => '创建成功', 'data' => \${$variableName}]);
    }

    public function show({$modelName} \${$variableName}): JsonResponse
    {
        return response()->json(['code' => 200, 'data' => \${$variableName}]);
    }

    public function update(Request \$request, {$modelName} \${$variableName}): JsonResponse
    {
        \${$variableName}->update(\$request->only([{$fillableFields}]));
        return response()->json(['code' => 200, 'msg' => '更新成功']);
    }

    public function destroy({$modelName} \${$variableName}): JsonResponse
    {
        \${$variableName}->delete();
        return response()->json(['code' => 200, 'msg' => '删除成功']);
    }
}
PHP;

        $path = app_path("Http/Controllers/Api/{$controllerName}.php");
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);
        $this->info("  Created: {$path}");
    }

    private function generateFrontendApi(string $routeName, string $modelName): void
    {
        $content = <<<TS
import request from '@/utils/request';

export function get{$modelName}List(params: any) {
  return request<any, ApiResponse>({ url: '/{$routeName}', method: 'get', params });
}

export function create{$modelName}(data: any) {
  return request<any, ApiResponse>({ url: '/{$routeName}', method: 'post', data });
}

export function update{$modelName}(id: number, data: any) {
  return request<any, ApiResponse>({ url: `/{$routeName}/\${id}`, method: 'put', data });
}

export function delete{$modelName}(id: number) {
  return request<any, ApiResponse>({ url: `/{$routeName}/\${id}`, method: 'delete' });
}
TS;

        $this->info("  Frontend API template for {$routeName}:");
        $this->line($content);
    }

    private function generateFrontendView(string $module, string $routeName, string $modelName, array $columns): void
    {
        $tableColumns = collect($columns)
            ->filter(fn($c) => !in_array($c->Field, ['password', 'remember_token', 'deleted_at']))
            ->take(8);

        $elColumns = '';
        foreach ($tableColumns as $col) {
            $label = $col->Comment ?: $col->Field;
            $elColumns .= "        <el-table-column prop=\"{$col->Field}\" label=\"{$label}\" />\n";
        }

        $content = <<<VUE
<template>
  <div class="app-container">
    <div class="search-container">
      <el-form :inline="true" :model="queryParams">
        <el-form-item>
          <el-input v-model="queryParams.keywords" placeholder="搜索关键字" clearable @keyup.enter="handleQuery" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleQuery"><el-icon><Search /></el-icon>搜索</el-button>
          <el-button @click="handleReset"><el-icon><Refresh /></el-icon>重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="card-container">
      <div style="margin-bottom: 12px">
        <el-button type="primary" @click="handleAdd"><el-icon><Plus /></el-icon>新增</el-button>
      </div>

      <el-table v-loading="loading" :data="tableData" border>
{$elColumns}        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="handleEdit(row)">编辑</el-button>
            <el-button type="danger" link @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container">
        <el-pagination
          v-model:current-page="queryParams.pageNum"
          v-model:page-size="queryParams.pageSize"
          :total="total"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleQuery"
          @current-change="handleQuery"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';

const loading = ref(false);
const tableData = ref<any[]>([]);
const total = ref(0);
const queryParams = reactive({ pageNum: 1, pageSize: 10, keywords: '' });

function handleQuery() {
  // TODO: Call API
}

function handleReset() {
  queryParams.keywords = '';
  queryParams.pageNum = 1;
  handleQuery();
}

function handleAdd() {
  // TODO: Open dialog
}

function handleEdit(row: any) {
  // TODO: Open dialog with data
}

function handleDelete(row: any) {
  ElMessageBox.confirm('确认删除？', '提示', { type: 'warning' }).then(() => {
    // TODO: Call delete API
    ElMessage.success('删除成功');
  });
}

onMounted(() => {
  handleQuery();
});
</script>
VUE;

        $this->info("  Frontend view template generated for {$module}/{$routeName}");
        $this->line("  (Template printed to console - copy to frontend/src/views/{$module}/{$routeName}/index.vue)");
    }
}
