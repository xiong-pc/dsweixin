<template>
  <div class="app-container">
    <div class="search-container">
      <el-form :model="queryParams" :inline="true">
        <el-form-item label="租户名称">
          <el-input v-model="queryParams.keywords" placeholder="请输入租户名称" clearable @keyup.enter="handleQuery" />
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
        <el-button type="primary" v-hasPerm="['sys:tenant:add']" @click="openDialog()">
          <el-icon><Plus /></el-icon>新增
        </el-button>
      </div>

      <el-table v-loading="loading" :data="tableData" border stripe>
        <el-table-column label="编号" prop="id" width="80" align="center" />
        <el-table-column label="租户名称" prop="name" min-width="160" />
        <el-table-column label="联系人" prop="contact_name" min-width="100" />
        <el-table-column label="联系电话" prop="contact_phone" min-width="120" />
        <el-table-column label="域名" prop="domain" min-width="160" show-overflow-tooltip />
        <el-table-column label="套餐" prop="package_name" min-width="100" />
        <el-table-column label="账号额度" prop="account_limit" width="100" align="center" />
        <el-table-column label="过期时间" prop="expire_time" min-width="160" />
        <el-table-column label="状态" prop="status" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'">
              {{ row.status === 1 ? '正常' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" prop="created_at" min-width="160" />
        <el-table-column label="操作" width="150" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link v-hasPerm="['sys:tenant:edit']" @click="openDialog(row)">编辑</el-button>
            <el-button type="danger" link v-hasPerm="['sys:tenant:delete']" @click="handleDelete(row.id)">删除</el-button>
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

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="700px" @close="closeDialog">
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="100px">
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="租户名称" prop="name">
              <el-input v-model="formData.name" placeholder="请输入租户名称" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="联系人" prop="contact_name">
              <el-input v-model="formData.contact_name" placeholder="请输入联系人" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="联系电话" prop="contact_phone">
              <el-input v-model="formData.contact_phone" placeholder="请输入联系电话" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="域名" prop="domain">
              <el-input v-model="formData.domain" placeholder="请输入域名" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="20">
          <el-col :span="12">
            <el-form-item label="账号额度" prop="account_limit">
              <el-input-number v-model="formData.account_limit" :min="1" style="width: 100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="过期时间" prop="expire_time">
              <el-date-picker
                v-model="formData.expire_time"
                type="datetime"
                placeholder="选择过期时间"
                value-format="YYYY-MM-DD HH:mm:ss"
                style="width: 100%"
              />
            </el-form-item>
          </el-col>
        </el-row>
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
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox, type FormInstance } from 'element-plus';
import { getTenantList, createTenant, updateTenant, deleteTenant } from '@/api/tenant';

const loading = ref(false);
const submitLoading = ref(false);
const tableData = ref<any[]>([]);
const total = ref(0);
const dialogVisible = ref(false);
const dialogTitle = ref('');
const formRef = ref<FormInstance>();

const queryParams = reactive({
  page: 1,
  pageSize: 10,
  keywords: '',
  status: undefined as number | undefined,
});

const formData = reactive({
  id: undefined as number | undefined,
  name: '',
  contact_name: '',
  contact_phone: '',
  domain: '',
  account_limit: 10,
  expire_time: '',
  status: 1,
  remark: '',
});

const formRules = {
  name: [{ required: true, message: '请输入租户名称', trigger: 'blur' }],
  contact_name: [{ required: true, message: '请输入联系人', trigger: 'blur' }],
};

onMounted(() => {
  handleQuery();
});

async function handleQuery() {
  loading.value = true;
  try {
    const res = await getTenantList(queryParams);
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

function openDialog(row?: any) {
  resetForm();
  if (row) {
    dialogTitle.value = '编辑租户';
    Object.assign(formData, row);
  } else {
    dialogTitle.value = '新增租户';
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
  formData.contact_name = '';
  formData.contact_phone = '';
  formData.domain = '';
  formData.account_limit = 10;
  formData.expire_time = '';
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
      await updateTenant(formData.id, formData);
      ElMessage.success('修改成功');
    } else {
      await createTenant(formData);
      ElMessage.success('新增成功');
    }
    closeDialog();
    handleQuery();
  } finally {
    submitLoading.value = false;
  }
}

async function handleDelete(id: number) {
  await ElMessageBox.confirm('确认删除该租户？', '提示', { type: 'warning' });
  await deleteTenant(id);
  ElMessage.success('删除成功');
  handleQuery();
}
</script>
