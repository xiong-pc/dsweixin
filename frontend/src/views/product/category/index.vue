<template>
  <div class="app-container">
    <div class="search-container">
      <el-form :model="queryParams" :inline="true">
        <el-form-item label="分类名称">
          <el-input v-model="queryParams.name" placeholder="请输入分类名称" clearable />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleQuery"><el-icon><Search /></el-icon>搜索</el-button>
          <el-button @click="handleReset"><el-icon><Refresh /></el-icon>重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="card-container">
      <div style="margin-bottom: 12px">
        <el-button type="primary" v-hasPerm="['product:category:add']" @click="openDialog()">
          <el-icon><Plus /></el-icon>新增
        </el-button>
        <el-button type="info" @click="toggleExpandAll">
          {{ isExpandAll ? '折叠' : '展开' }}
        </el-button>
      </div>

      <el-table
        v-if="refreshTable"
        v-loading="loading"
        :data="tableData"
        row-key="id"
        :default-expand-all="isExpandAll"
        :tree-props="{ children: 'children', hasChildren: 'hasChildren' }"
        border
      >
        <el-table-column label="分类名称" prop="name" min-width="200" />
        <el-table-column label="图标" prop="icon" width="120" align="center" />
        <el-table-column label="排序" prop="sort" width="80" align="center" />
        <el-table-column label="状态" prop="status" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'">
              {{ row.status === 1 ? '正常' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" prop="created_at" min-width="160" />
        <el-table-column label="操作" width="200" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link v-hasPerm="['product:category:add']" @click="openDialog(undefined, row.id)">新增</el-button>
            <el-button type="primary" link v-hasPerm="['product:category:edit']" @click="openDialog(row.id)">编辑</el-button>
            <el-button type="danger" link v-hasPerm="['product:category:delete']" @click="handleDelete(row.id)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="600px" @close="closeDialog">
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="80px">
        <el-form-item label="上级分类" prop="parent_id">
          <el-tree-select
            v-model="formData.parent_id"
            :data="categoryOptions"
            :props="{ label: 'name', children: 'children' }"
            value-key="id"
            placeholder="选择上级分类"
            check-strictly
            clearable
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="分类名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入分类名称" />
        </el-form-item>
        <el-form-item label="图标" prop="icon">
          <el-input v-model="formData.icon" placeholder="请输入图标" />
        </el-form-item>
        <el-row>
          <el-col :span="12">
            <el-form-item label="排序" prop="sort">
              <el-input-number v-model="formData.sort" :min="0" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="状态" prop="status">
              <el-radio-group v-model="formData.status">
                <el-radio :value="1">正常</el-radio>
                <el-radio :value="0">禁用</el-radio>
              </el-radio-group>
            </el-form-item>
          </el-col>
        </el-row>
      </el-form>
      <template #footer>
        <el-button @click="closeDialog">取 消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleSubmit">确 定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, nextTick } from 'vue';
import { ElMessage, ElMessageBox, type FormInstance } from 'element-plus';
import { getCategoryList, getCategoryDetail, createCategory, updateCategory, deleteCategory } from '@/api/category';

const loading = ref(false);
const submitLoading = ref(false);
const tableData = ref<any[]>([]);
const dialogVisible = ref(false);
const dialogTitle = ref('');
const formRef = ref<FormInstance>();
const categoryOptions = ref<any[]>([]);
const isExpandAll = ref(true);
const refreshTable = ref(true);

const queryParams = reactive({ name: '' });

const formData = reactive({
  id: undefined as number | undefined,
  parent_id: 0,
  name: '',
  icon: '',
  sort: 0,
  status: 1,
});

const formRules = {
  name: [{ required: true, message: '请输入分类名称', trigger: 'blur' }],
};

onMounted(() => {
  handleQuery();
});

async function handleQuery() {
  loading.value = true;
  try {
    const res = await getCategoryList(queryParams);
    tableData.value = res.data || [];
    categoryOptions.value = [{ id: 0, name: '顶级分类', children: res.data || [] }];
  } finally {
    loading.value = false;
  }
}

function handleReset() {
  queryParams.name = '';
  handleQuery();
}

function toggleExpandAll() {
  refreshTable.value = false;
  isExpandAll.value = !isExpandAll.value;
  nextTick(() => { refreshTable.value = true; });
}

async function openDialog(id?: number, parentId?: number) {
  resetForm();
  if (id) {
    dialogTitle.value = '编辑分类';
    const res = await getCategoryDetail(id);
    Object.assign(formData, res.data);
  } else {
    dialogTitle.value = '新增分类';
    if (parentId !== undefined) {
      formData.parent_id = parentId;
    }
  }
  dialogVisible.value = true;
}

function closeDialog() {
  dialogVisible.value = false;
  resetForm();
}

function resetForm() {
  formData.id = undefined;
  formData.parent_id = 0;
  formData.name = '';
  formData.icon = '';
  formData.sort = 0;
  formData.status = 1;
  formRef.value?.resetFields();
}

async function handleSubmit() {
  const valid = await formRef.value?.validate().catch(() => false);
  if (!valid) return;

  submitLoading.value = true;
  try {
    if (formData.id) {
      await updateCategory(formData.id, formData);
      ElMessage.success('修改成功');
    } else {
      await createCategory(formData);
      ElMessage.success('新增成功');
    }
    closeDialog();
    handleQuery();
  } finally {
    submitLoading.value = false;
  }
}

async function handleDelete(id: number) {
  await ElMessageBox.confirm('确认删除该分类？', '提示', { type: 'warning' });
  await deleteCategory(id);
  ElMessage.success('删除成功');
  handleQuery();
}
</script>
