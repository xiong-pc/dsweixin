<template>
  <div class="app-container">
    <div class="search-container">
      <el-form :model="queryParams" :inline="true">
        <el-form-item label="配置名称">
          <el-input v-model="queryParams.name" placeholder="请输入配置名称" clearable @keyup.enter="handleQuery" />
        </el-form-item>
        <el-form-item label="配置键名">
          <el-input v-model="queryParams.key" placeholder="请输入配置键名" clearable @keyup.enter="handleQuery" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleQuery"><el-icon><Search /></el-icon>搜索</el-button>
          <el-button @click="handleReset"><el-icon><Refresh /></el-icon>重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="card-container">
      <div style="margin-bottom: 12px">
        <el-button type="primary" v-hasPerm="['sys:config:add']" @click="openDialog()">
          <el-icon><Plus /></el-icon>新增
        </el-button>
      </div>

      <el-table v-loading="loading" :data="tableData" border stripe>
        <el-table-column label="编号" prop="id" width="80" align="center" />
        <el-table-column label="配置名称" prop="name" min-width="140" />
        <el-table-column label="配置键名" prop="key" min-width="160" />
        <el-table-column label="配置值" prop="value" min-width="200" show-overflow-tooltip />
        <el-table-column label="类型" prop="type" width="100" align="center">
          <template #default="{ row }">
            <el-tag>{{ configTypeLabel(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="备注" prop="remark" min-width="160" show-overflow-tooltip />
        <el-table-column label="创建时间" prop="created_at" min-width="160" />
        <el-table-column label="操作" width="150" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link v-hasPerm="['sys:config:edit']" @click="openDialog(row)">编辑</el-button>
            <el-button type="danger" link v-hasPerm="['sys:config:delete']" @click="handleDelete(row.id)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container">
        <el-pagination
          v-model:current-page="queryParams.pageNum"
          v-model:page-size="queryParams.pageSize"
          :page-sizes="[10, 20, 50, 100]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleQuery"
          @current-change="handleQuery"
        />
      </div>
    </div>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="600px" @close="closeDialog">
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="100px">
        <el-form-item label="配置名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入配置名称" />
        </el-form-item>
        <el-form-item label="配置键名" prop="key">
          <el-input v-model="formData.key" placeholder="请输入配置键名" />
        </el-form-item>
        <el-form-item label="配置值" prop="value">
          <el-input v-model="formData.value" type="textarea" :rows="3" placeholder="请输入配置值" />
        </el-form-item>
        <el-form-item label="类型" prop="type">
          <el-radio-group v-model="formData.type">
            <el-radio value="Y">系统内置</el-radio>
            <el-radio value="N">自定义</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="备注" prop="remark">
          <el-input v-model="formData.remark" type="textarea" placeholder="请输入备注" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="closeDialog">取 消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确 定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox, type FormInstance } from 'element-plus';
import { getConfigList, createConfig, updateConfig, deleteConfig } from '@/api/config';

const loading = ref(false);
const submitLoading = ref(false);
const tableData = ref<any[]>([]);
const total = ref(0);
const dialogVisible = ref(false);
const dialogTitle = ref('');
const formRef = ref<FormInstance>();

const queryParams = reactive({
  pageNum: 1,
  pageSize: 10,
  name: '',
  key: '',
});

const formData = reactive({
  id: undefined as number | undefined,
  name: '',
  key: '',
  value: '',
  type: 'N',
  remark: '',
});

const formRules = {
  name: [{ required: true, message: '请输入配置名称', trigger: 'blur' }],
  key: [{ required: true, message: '请输入配置键名', trigger: 'blur' }],
  value: [{ required: true, message: '请输入配置值', trigger: 'blur' }],
};

onMounted(() => {
  handleQuery();
});

const CONFIG_TYPE_LABELS: Record<number, string> = {
  0: '字符串',
  1: '数字',
  2: '布尔',
  3: 'JSON',
};

function configTypeLabel(type: unknown): string {
  if (typeof type === 'number') {
    const label = CONFIG_TYPE_LABELS[type];
    if (label !== undefined) return label;
  }
  if (type === 'Y') return '系统内置';
  if (type === 'N') return '自定义';
  return type != null && type !== '' ? String(type) : '—';
}

async function handleQuery() {
  loading.value = true;
  try {
    const res = await getConfigList(queryParams);
    tableData.value = res.data.list;
    total.value = res.data.total;
  } finally {
    loading.value = false;
  }
}

function handleReset() {
  queryParams.pageNum = 1;
  queryParams.name = '';
  queryParams.key = '';
  handleQuery();
}

function openDialog(row?: any) {
  resetForm();
  if (row) {
    dialogTitle.value = '编辑配置';
    Object.assign(formData, row);
  } else {
    dialogTitle.value = '新增配置';
  }
  dialogVisible.value = true;
}

function closeDialog() {
  dialogVisible.value = false;
  resetForm();
}

function resetForm() {
  formData.id = undefined;
  formData.name = '';
  formData.key = '';
  formData.value = '';
  formData.type = 'N';
  formData.remark = '';
  formRef.value?.resetFields();
}

async function handleSubmit() {
  const valid = await formRef.value?.validate().catch(() => false);
  if (!valid) return;

  submitLoading.value = true;
  try {
    if (formData.id) {
      await updateConfig(formData.id, formData);
      ElMessage.success('修改成功');
    } else {
      await createConfig(formData);
      ElMessage.success('新增成功');
    }
    closeDialog();
    handleQuery();
  } finally {
    submitLoading.value = false;
  }
}

async function handleDelete(id: number) {
  await ElMessageBox.confirm('确认删除该配置？', '提示', { type: 'warning' });
  await deleteConfig(id);
  ElMessage.success('删除成功');
  handleQuery();
}
</script>
