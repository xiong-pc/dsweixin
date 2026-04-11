<template>
  <div class="app-container">
    <div class="search-container">
      <el-form :model="queryParams" :inline="true">
        <el-form-item label="角色名称">
          <el-input v-model="queryParams.keywords" placeholder="请输入角色名称" clearable @keyup.enter="handleQuery" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleQuery"><el-icon><Search /></el-icon>搜索</el-button>
          <el-button @click="handleReset"><el-icon><Refresh /></el-icon>重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="card-container">
      <div style="margin-bottom: 12px">
        <el-button type="primary" v-hasPerm="['sys:role:add']" @click="openDialog()">
          <el-icon><Plus /></el-icon>新增
        </el-button>
      </div>

      <el-table v-loading="loading" :data="tableData" border stripe>
        <el-table-column label="角色编号" prop="id" width="80" align="center" />
        <el-table-column label="角色名称" prop="name" min-width="120" />
        <el-table-column label="角色标识" prop="code" min-width="120" />
        <el-table-column label="排序" prop="sort" width="80" align="center" />
        <el-table-column label="状态" prop="status" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'">
              {{ row.status === 1 ? '正常' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" prop="created_at" min-width="160" />
        <el-table-column label="操作" width="260" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link v-hasPerm="['sys:role:edit']" @click="openDialog(row.id)">编辑</el-button>
            <el-button type="success" link v-hasPerm="['sys:role:menu']" @click="openMenuDialog(row.id)">分配菜单</el-button>
            <el-button type="danger" link v-hasPerm="['sys:role:delete']" @click="handleDelete(row.id)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container">
        <el-pagination
          v-model:current-page="queryParams.page"
          v-model:page-size="queryParams.pageSize"
          :page-sizes="[10, 20, 50, 100]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleQuery"
          @current-change="handleQuery"
        />
      </div>
    </div>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="500px" @close="closeDialog">
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="80px">
        <el-form-item label="角色名称" prop="name">
          <el-input v-model="formData.name" placeholder="请输入角色名称" />
        </el-form-item>
        <el-form-item label="角色标识" prop="code">
          <el-input v-model="formData.code" placeholder="请输入角色标识" />
        </el-form-item>
        <el-form-item label="排序" prop="sort">
          <el-input-number v-model="formData.sort" :min="0" />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="formData.status">
            <el-radio :value="1">正常</el-radio>
            <el-radio :value="0">禁用</el-radio>
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

    <el-dialog v-model="menuDialogVisible" title="分配菜单" width="500px">
      <el-tree
        ref="menuTreeRef"
        :data="menuTreeData"
        :props="{ label: 'name', children: 'children' }"
        node-key="id"
        show-checkbox
        check-strictly
        default-expand-all
      />
      <template #footer>
        <el-button @click="menuDialogVisible = false">取 消</el-button>
        <el-button type="primary" :loading="menuSubmitLoading" @click="handleMenuSubmit">确 定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox, type FormInstance } from 'element-plus';
import type { ElTree } from 'element-plus';
import { getRoleList, getRoleDetail, createRole, updateRole, deleteRole, getRoleMenus, updateRoleMenus } from '@/api/role';
import { getMenuList } from '@/api/menu';

const loading = ref(false);
const submitLoading = ref(false);
const menuSubmitLoading = ref(false);
const tableData = ref<any[]>([]);
const total = ref(0);
const dialogVisible = ref(false);
const menuDialogVisible = ref(false);
const dialogTitle = ref('');
const formRef = ref<FormInstance>();
const menuTreeRef = ref<InstanceType<typeof ElTree>>();
const menuTreeData = ref<any[]>([]);
const currentRoleId = ref(0);

const queryParams = reactive({
  page: 1,
  pageSize: 10,
  keywords: '',
});

const formData = reactive({
  id: undefined as number | undefined,
  name: '',
  code: '',
  sort: 0,
  status: 1,
  remark: '',
});

const formRules = {
  name: [{ required: true, message: '请输入角色名称', trigger: 'blur' }],
  code: [{ required: true, message: '请输入角色标识', trigger: 'blur' }],
};

onMounted(() => {
  handleQuery();
});

async function handleQuery() {
  loading.value = true;
  try {
    const res = await getRoleList(queryParams);
    tableData.value = res.data.list;
    total.value = res.data.total;
  } finally {
    loading.value = false;
  }
}

function handleReset() {
  queryParams.page = 1;
  queryParams.keywords = '';
  handleQuery();
}

async function openDialog(id?: number) {
  resetForm();
  if (id) {
    dialogTitle.value = '编辑角色';
    const res = await getRoleDetail(id);
    Object.assign(formData, res.data);
  } else {
    dialogTitle.value = '新增角色';
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
  formData.code = '';
  formData.sort = 0;
  formData.status = 1;
  formData.remark = '';
  formRef.value?.resetFields();
}

async function handleSubmit() {
  const valid = await formRef.value?.validate().catch(() => false);
  if (!valid) return;

  submitLoading.value = true;
  try {
    if (formData.id) {
      await updateRole(formData.id, formData);
      ElMessage.success('修改成功');
    } else {
      await createRole(formData);
      ElMessage.success('新增成功');
    }
    closeDialog();
    handleQuery();
  } finally {
    submitLoading.value = false;
  }
}

async function handleDelete(id: number) {
  await ElMessageBox.confirm('确认删除该角色？', '提示', { type: 'warning' });
  await deleteRole(id);
  ElMessage.success('删除成功');
  handleQuery();
}

async function openMenuDialog(roleId: number) {
  currentRoleId.value = roleId;
  const [menuRes, roleMenuRes] = await Promise.all([
    getMenuList(),
    getRoleMenus(roleId),
  ]);
  menuTreeData.value = menuRes.data || [];
  menuDialogVisible.value = true;
  setTimeout(() => {
    const checkedIds = roleMenuRes.data || [];
    menuTreeRef.value?.setCheckedKeys(checkedIds);
  }, 100);
}

async function handleMenuSubmit() {
  menuSubmitLoading.value = true;
  try {
    const checkedKeys = menuTreeRef.value?.getCheckedKeys(false) as number[];
    const halfCheckedKeys = menuTreeRef.value?.getHalfCheckedKeys() as number[];
    const menuIds = [...checkedKeys, ...halfCheckedKeys];
    await updateRoleMenus(currentRoleId.value, menuIds);
    ElMessage.success('分配成功');
    menuDialogVisible.value = false;
  } finally {
    menuSubmitLoading.value = false;
  }
}
</script>
