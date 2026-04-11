<template>
  <div class="app-container">
    <div class="search-container">
      <el-form :model="queryParams" :inline="true">
        <el-form-item label="用户名">
          <el-input v-model="queryParams.keywords" placeholder="请输入用户名" clearable @keyup.enter="handleQuery" />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="queryParams.status" placeholder="全部" clearable>
            <el-option label="正常" :value="1" />
            <el-option label="禁用" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleQuery"><el-icon><Search /></el-icon>搜索</el-button>
          <el-button @click="handleReset"><el-icon><Refresh /></el-icon>重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="card-container">
      <div style="margin-bottom: 12px">
        <el-button type="primary" v-hasPerm="['sys:user:add']" @click="openDialog()">
          <el-icon><Plus /></el-icon>新增
        </el-button>
        <el-button type="danger" :disabled="!selectedIds.length" v-hasPerm="['sys:user:delete']" @click="handleBatchDelete">
          <el-icon><Delete /></el-icon>删除
        </el-button>
      </div>

      <el-table v-loading="loading" :data="tableData" @selection-change="handleSelectionChange" border stripe>
        <el-table-column type="selection" width="50" align="center" />
        <el-table-column label="用户名" prop="username" min-width="100" />
        <el-table-column label="昵称" prop="nickname" min-width="100" />
        <el-table-column label="性别" prop="gender" width="80" align="center">
          <template #default="{ row }">
            {{ row.gender === 1 ? '男' : row.gender === 2 ? '女' : '未知' }}
          </template>
        </el-table-column>
        <el-table-column label="手机号码" prop="phone" min-width="120" />
        <el-table-column label="部门" prop="dept_name" min-width="120" />
        <el-table-column label="状态" prop="status" width="80" align="center">
          <template #default="{ row }">
            <el-switch
              v-model="row.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleStatusChange(row)"
            />
          </template>
        </el-table-column>
        <el-table-column label="创建时间" prop="created_at" min-width="160" />
        <el-table-column label="操作" width="220" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link v-hasPerm="['sys:user:edit']" @click="openDialog(row.id)">编辑</el-button>
            <el-button type="warning" link v-hasPerm="['sys:user:reset-pwd']" @click="handleResetPassword(row.id)">重置密码</el-button>
            <el-button type="danger" link v-hasPerm="['sys:user:delete']" @click="handleDelete(row.id)">删除</el-button>
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

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="600px" @close="closeDialog">
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="80px">
        <el-form-item label="用户名" prop="username">
          <el-input v-model="formData.username" placeholder="请输入用户名" :disabled="!!formData.id" />
        </el-form-item>
        <el-form-item label="昵称" prop="nickname">
          <el-input v-model="formData.nickname" placeholder="请输入昵称" />
        </el-form-item>
        <el-form-item v-if="!formData.id" label="密码" prop="password">
          <el-input v-model="formData.password" type="password" placeholder="请输入密码" show-password />
        </el-form-item>
        <el-form-item label="手机号码" prop="phone">
          <el-input v-model="formData.phone" placeholder="请输入手机号码" />
        </el-form-item>
        <el-form-item label="邮箱" prop="email">
          <el-input v-model="formData.email" placeholder="请输入邮箱" />
        </el-form-item>
        <el-form-item label="性别" prop="gender">
          <el-radio-group v-model="formData.gender">
            <el-radio :value="1">男</el-radio>
            <el-radio :value="2">女</el-radio>
            <el-radio :value="0">未知</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="角色" prop="role_ids">
          <el-select v-model="formData.role_ids" multiple placeholder="请选择角色" style="width: 100%">
            <el-option v-for="item in roleOptions" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="formData.status">
            <el-radio :value="1">正常</el-radio>
            <el-radio :value="0">禁用</el-radio>
          </el-radio-group>
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
import { getUserList, getUserDetail, createUser, updateUser, deleteUser, updateUserStatus, resetPassword } from '@/api/user';
import { getRoleList } from '@/api/role';

const loading = ref(false);
const submitLoading = ref(false);
const tableData = ref<any[]>([]);
const total = ref(0);
const selectedIds = ref<number[]>([]);
const dialogVisible = ref(false);
const dialogTitle = ref('');
const formRef = ref<FormInstance>();
const roleOptions = ref<OptionType[]>([]);

const queryParams = reactive({
  page: 1,
  pageSize: 10,
  keywords: '',
  status: undefined as number | undefined,
});

const formData = reactive({
  id: undefined as number | undefined,
  username: '',
  nickname: '',
  password: '',
  phone: '',
  email: '',
  gender: 1,
  dept_id: undefined as number | undefined,
  role_ids: [] as number[],
  status: 1,
});

const formRules = {
  username: [{ required: true, message: '请输入用户名', trigger: 'blur' }],
  nickname: [{ required: true, message: '请输入昵称', trigger: 'blur' }],
  password: [{ required: true, message: '请输入密码', trigger: 'blur' }],
};

onMounted(() => {
  handleQuery();
  loadRoleOptions();
});

async function handleQuery() {
  loading.value = true;
  try {
    const res = await getUserList(queryParams);
    tableData.value = res.data.list;
    total.value = res.data.total;
  } finally {
    loading.value = false;
  }
}

function handleReset() {
  queryParams.page = 1;
  queryParams.keywords = '';
  queryParams.status = undefined;
  handleQuery();
}

async function loadRoleOptions() {
  const res = await getRoleList({ page: 1, pageSize: 100 });
  roleOptions.value = (res.data.list || []).map((r: any) => ({ label: r.name, value: r.id }));
}

function handleSelectionChange(rows: any[]) {
  selectedIds.value = rows.map((r) => r.id);
}

async function openDialog(id?: number) {
  resetForm();
  if (id) {
    dialogTitle.value = '编辑用户';
    const res = await getUserDetail(id);
    const d = res.data;
    formData.id       = d.id;
    formData.username = d.username;
    formData.nickname = d.nickname;
    formData.phone    = d.phone ?? '';
    formData.email    = d.email ?? '';
    formData.gender   = d.gender ?? 1;
    formData.status   = d.status ?? 1;
    // dept_id=0 视为无部门，不传给后端
    formData.dept_id  = d.dept_id || undefined;
    // roles 是对象数组，取出 id 列表
    formData.role_ids = (d.roles ?? []).map((r: any) => r.id);
  } else {
    dialogTitle.value = '新增用户';
  }
  dialogVisible.value = true;
}

function closeDialog() {
  dialogVisible.value = false;
  resetForm();
}

function resetForm() {
  formData.id       = undefined;
  formData.username = '';
  formData.nickname = '';
  formData.password = '';
  formData.phone    = '';
  formData.email    = '';
  formData.gender   = 1;
  formData.dept_id  = undefined;
  formData.role_ids = [];
  formData.status   = 1;
  formRef.value?.resetFields();
}

async function handleSubmit() {
  const valid = await formRef.value?.validate().catch(() => false);
  if (!valid) return;

  submitLoading.value = true;
  try {
    if (formData.id) {
      await updateUser(formData.id, formData);
      ElMessage.success('修改成功');
    } else {
      await createUser(formData);
      ElMessage.success('新增成功');
    }
    closeDialog();
    handleQuery();
  } finally {
    submitLoading.value = false;
  }
}

async function handleDelete(id: number) {
  await ElMessageBox.confirm('确认删除该用户？', '提示', { type: 'warning' });
  await deleteUser(id);
  ElMessage.success('删除成功');
  handleQuery();
}

async function handleBatchDelete() {
  await ElMessageBox.confirm('确认删除选中的用户？', '提示', { type: 'warning' });
  await Promise.all(selectedIds.value.map((id) => deleteUser(id)));
  ElMessage.success('删除成功');
  handleQuery();
}

async function handleStatusChange(row: any) {
  await updateUserStatus(row.id, row.status);
  ElMessage.success('状态更新成功');
}

async function handleResetPassword(id: number) {
  await ElMessageBox.confirm('确认重置该用户密码？', '提示', { type: 'warning' });
  await resetPassword(id);
  ElMessage.success('密码重置成功');
}
</script>
