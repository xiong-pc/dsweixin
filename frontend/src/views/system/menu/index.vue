<template>
  <div class="app-container">
    <div class="search-container">
      <el-form :model="queryParams" :inline="true">
        <el-form-item label="菜单名称">
          <el-input v-model="queryParams.keywords" placeholder="请输入菜单名称" clearable />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleQuery"><el-icon><Search /></el-icon>搜索</el-button>
          <el-button @click="handleReset"><el-icon><Refresh /></el-icon>重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="card-container">
      <div style="margin-bottom: 12px">
        <el-button type="primary" v-hasPerm="['sys:menu:add']" @click="openDialog()">
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
        <el-table-column label="菜单名称" prop="name" min-width="160" />
        <el-table-column label="图标" prop="icon" width="80" align="center">
          <template #default="{ row }">
            <el-icon v-if="row.icon"><component :is="row.icon" /></el-icon>
          </template>
        </el-table-column>
        <el-table-column label="类型" prop="type" width="80" align="center">
          <template #default="{ row }">
            <el-tag v-if="row.type === 1">目录</el-tag>
            <el-tag v-else-if="row.type === 2" type="success">菜单</el-tag>
            <el-tag v-else-if="row.type === 3" type="warning">按钮</el-tag>
            <el-tag v-else type="info">链接</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="路由路径" prop="path" min-width="140" />
        <el-table-column label="组件路径" prop="component" min-width="160" />
        <el-table-column label="权限标识" prop="permission" min-width="140" />
        <el-table-column label="排序" prop="sort" width="70" align="center" />
        <el-table-column label="状态" prop="status" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'">
              {{ row.status === 1 ? '显示' : '隐藏' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="200" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link v-hasPerm="['sys:menu:add']" @click="openDialog(undefined, row.id)">新增</el-button>
            <el-button type="primary" link v-hasPerm="['sys:menu:edit']" @click="openDialog(row.id)">编辑</el-button>
            <el-button type="danger" link v-hasPerm="['sys:menu:delete']" @click="handleDelete(row.id)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="700px" @close="closeDialog">
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="100px">
        <el-form-item label="上级菜单" prop="parent_id">
          <el-tree-select
            v-model="formData.parent_id"
            :data="menuOptions"
            :props="{ label: 'name', children: 'children' }"
            value-key="id"
            placeholder="选择上级菜单"
            check-strictly
            clearable
            style="width: 100%"
          />
        </el-form-item>
        <el-form-item label="菜单类型" prop="type">
          <el-radio-group v-model="formData.type">
            <el-radio :value="1">目录</el-radio>
            <el-radio :value="2">菜单</el-radio>
            <el-radio :value="3">按钮</el-radio>
            <el-radio :value="4">链接</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="菜单名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入菜单名称" />
        </el-form-item>
        <el-form-item v-if="formData.type !== 3" label="图标" prop="icon">
          <el-input v-model="formData.icon" placeholder="请输入图标名称" />
        </el-form-item>
        <el-form-item v-if="formData.type !== 3" label="路由路径" prop="path">
          <el-input v-model="formData.path" placeholder="请输入路由路径" />
        </el-form-item>
        <el-form-item v-if="formData.type === 2" label="组件路径" prop="component">
          <el-input v-model="formData.component" placeholder="请输入组件路径，如 system/user/index" />
        </el-form-item>
        <el-form-item v-if="formData.type !== 1" label="权限标识" prop="permission">
          <el-input v-model="formData.permission" placeholder="请输入权限标识，如 sys:user:list" />
        </el-form-item>
        <el-form-item v-if="formData.type === 2" label="路由名称" prop="route_name">
          <el-input v-model="formData.route_name" placeholder="请输入路由名称" />
        </el-form-item>
        <el-form-item v-if="formData.type === 4" label="链接地址" prop="redirect">
          <el-input v-model="formData.redirect" placeholder="请输入链接地址" />
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
                <el-radio :value="1">显示</el-radio>
                <el-radio :value="0">隐藏</el-radio>
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
import { getMenuList, getMenuDetail, createMenu, updateMenu, deleteMenu } from '@/api/menu';

const loading = ref(false);
const submitLoading = ref(false);
const tableData = ref<any[]>([]);
const dialogVisible = ref(false);
const dialogTitle = ref('');
const formRef = ref<FormInstance>();
const menuOptions = ref<any[]>([]);
const isExpandAll = ref(true);
const refreshTable = ref(true);

const queryParams = reactive({ keywords: '' });

const formData = reactive({
  id: undefined as number | undefined,
  parent_id: 0,
  type: 1 as 1 | 2 | 3 | 4,
  name: '',
  icon: '',
  path: '',
  component: '',
  permission: '',
  route_name: '',
  redirect: '',
  sort: 0,
  status: 1,
});

const formRules = {
  name: [{ required: true, message: '请输入菜单名称', trigger: 'blur' }],
  type: [{ required: true, message: '请选择菜单类型', trigger: 'change' }],
};

onMounted(() => {
  handleQuery();
});

async function handleQuery() {
  loading.value = true;
  try {
    const res = await getMenuList(queryParams);
    tableData.value = res.data || [];
    menuOptions.value = [{ id: 0, name: '顶级菜单', children: res.data || [] }];
  } finally {
    loading.value = false;
  }
}

async function handleReset() {
  queryParams.keywords = '';
  await handleQuery();
}

function toggleExpandAll() {
  refreshTable.value = false;
  isExpandAll.value = !isExpandAll.value;
  nextTick(() => { refreshTable.value = true; });
}

async function openDialog(id?: number, parentId?: number) {
  resetForm();
  if (id) {
    dialogTitle.value = '编辑菜单';
    const res = await getMenuDetail(id);
    Object.assign(formData, res.data);
  } else {
    dialogTitle.value = '新增菜单';
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
  formData.type = 1;
  formData.name = '';
  formData.icon = '';
  formData.path = '';
  formData.component = '';
  formData.permission = '';
  formData.route_name = '';
  formData.redirect = '';
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
      await updateMenu(formData.id, formData);
      ElMessage.success('修改成功');
    } else {
      await createMenu(formData);
      ElMessage.success('新增成功');
    }
    closeDialog();
    handleQuery();
  } finally {
    submitLoading.value = false;
  }
}

async function handleDelete(id: number) {
  await ElMessageBox.confirm('确认删除该菜单？', '提示', { type: 'warning' });
  await deleteMenu(id);
  ElMessage.success('删除成功');
  await handleQuery();
}
</script>
