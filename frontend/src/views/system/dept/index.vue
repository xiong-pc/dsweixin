<template>
  <div class="app-container">
    <div class="search-container">
      <el-form :model="queryParams" :inline="true">
        <el-form-item label="部门名称">
          <el-input v-model="queryParams.keywords" placeholder="请输入部门名称" clearable />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleQuery"><el-icon><Search /></el-icon>搜索</el-button>
          <el-button @click="handleReset"><el-icon><Refresh /></el-icon>重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="card-container">
      <div style="margin-bottom: 12px">
        <el-button type="primary" v-hasPerm="['sys:dept:add']" @click="openDialog()">
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
        <el-table-column label="部门名称" prop="name" min-width="200" />
        <el-table-column label="负责人" prop="leader" width="120" align="center" />
        <el-table-column label="联系电话" prop="phone" width="140" align="center" />
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
            <el-button type="primary" link v-hasPerm="['sys:dept:add']" @click="openDialog(undefined, row.id)">新增</el-button>
            <el-button type="primary" link v-hasPerm="['sys:dept:edit']" @click="openDialog(row.id)">编辑</el-button>
            <el-button type="danger" link v-hasPerm="['sys:dept:delete']" @click="handleDelete(row.id)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="600px" @close="closeDialog">
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="80px">
        <el-form-item label="上级部门" prop="parent_id">
          <el-tree-select
            v-model="formData.parent_id"
            :data="deptOptions"
            :props="{ label: 'name', children: 'children' }"
            value-key="id"
            placeholder="选择上级部门"
            check-strictly
            clearable
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="部门名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入部门名称" />
        </el-form-item>
        <el-form-item label="负责人" prop="leader">
          <el-input v-model="formData.leader" placeholder="请输入负责人" />
        </el-form-item>
        <el-form-item label="联系电话" prop="phone">
          <el-input v-model="formData.phone" placeholder="请输入联系电话" />
        </el-form-item>
        <el-form-item label="邮箱" prop="email">
          <el-input v-model="formData.email" placeholder="请输入邮箱" />
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
import { getDeptList, getDeptDetail, createDept, updateDept, deleteDept } from '@/api/dept';

const loading = ref(false);
const submitLoading = ref(false);
const tableData = ref<any[]>([]);
const dialogVisible = ref(false);
const dialogTitle = ref('');
const formRef = ref<FormInstance>();
const deptOptions = ref<any[]>([]);
const isExpandAll = ref(true);
const refreshTable = ref(true);

const queryParams = reactive({ keywords: '' });

const formData = reactive({
  id: undefined as number | undefined,
  parent_id: 0,
  name: '',
  leader: '',
  phone: '',
  email: '',
  sort: 0,
  status: 1,
});

const formRules = {
  name: [{ required: true, message: '请输入部门名称', trigger: 'blur' }],
};

onMounted(() => {
  handleQuery();
});

async function handleQuery() {
  loading.value = true;
  try {
    const res = await getDeptList(queryParams);
    tableData.value = res.data || [];
    deptOptions.value = [{ id: 0, name: '顶级部门', children: res.data || [] }];
  } finally {
    loading.value = false;
  }
}

function handleReset() {
  queryParams.keywords = '';
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
    dialogTitle.value = '编辑部门';
    const res = await getDeptDetail(id);
    Object.assign(formData, res.data);
  } else {
    dialogTitle.value = '新增部门';
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
  formData.leader = '';
  formData.phone = '';
  formData.email = '';
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
      await updateDept(formData.id, formData);
      ElMessage.success('修改成功');
    } else {
      await createDept(formData);
      ElMessage.success('新增成功');
    }
    closeDialog();
    handleQuery();
  } finally {
    submitLoading.value = false;
  }
}

async function handleDelete(id: number) {
  await ElMessageBox.confirm('确认删除该部门？', '提示', { type: 'warning' });
  await deleteDept(id);
  ElMessage.success('删除成功');
  handleQuery();
}
</script>
