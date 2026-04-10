<template>
  <div class="app-container">
    <div class="search-container">
      <el-form :model="queryParams" :inline="true">
        <el-form-item label="公告标题">
          <el-input v-model="queryParams.title" placeholder="请输入公告标题" clearable @keyup.enter="handleQuery" />
        </el-form-item>
        <el-form-item label="公告类型">
          <el-select v-model="queryParams.type" placeholder="全部" clearable>
            <el-option label="通知" :value="1" />
            <el-option label="公告" :value="2" />
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
        <el-button type="primary" v-hasPerm="['sys:notice:add']" @click="openDialog()">
          <el-icon><Plus /></el-icon>新增
        </el-button>
      </div>

      <el-table v-loading="loading" :data="tableData" border stripe>
        <el-table-column label="编号" prop="id" width="80" align="center" />
        <el-table-column label="公告标题" prop="title" min-width="200" show-overflow-tooltip />
        <el-table-column label="公告类型" prop="type" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.type === 1 ? '' : 'success'">
              {{ row.type === 1 ? '通知' : '公告' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="状态" prop="status" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : row.status === 0 ? 'info' : 'danger'">
              {{ row.status === 1 ? '已发布' : row.status === 0 ? '草稿' : '已撤回' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建人" prop="creator" width="100" align="center" />
        <el-table-column label="创建时间" prop="created_at" min-width="160" />
        <el-table-column label="操作" width="250" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link v-hasPerm="['sys:notice:edit']" @click="openDialog(row)">编辑</el-button>
            <el-button v-if="row.status === 0" type="success" link v-hasPerm="['sys:notice:publish']" @click="handlePublish(row.id)">发布</el-button>
            <el-button v-if="row.status === 1" type="warning" link v-hasPerm="['sys:notice:revoke']" @click="handleRevoke(row.id)">撤回</el-button>
            <el-button type="danger" link v-hasPerm="['sys:notice:delete']" @click="handleDelete(row.id)">删除</el-button>
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

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="700px" @close="closeDialog">
      <el-form ref="formRef" :model="formData" :rules="formRules" label-width="80px">
        <el-form-item label="公告标题" prop="title">
          <el-input v-model="formData.title" placeholder="请输入公告标题" />
        </el-form-item>
        <el-form-item label="公告类型" prop="type">
          <el-radio-group v-model="formData.type">
            <el-radio :value="1">通知</el-radio>
            <el-radio :value="2">公告</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="公告内容" prop="content">
          <el-input v-model="formData.content" type="textarea" :rows="6" placeholder="请输入公告内容" />
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
import { getNoticeList, createNotice, updateNotice, deleteNotice, publishNotice, revokeNotice } from '@/api/notice';

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
  title: '',
  type: undefined as number | undefined,
});

const formData = reactive({
  id: undefined as number | undefined,
  title: '',
  type: 1,
  content: '',
  remark: '',
});

const formRules = {
  title: [{ required: true, message: '请输入公告标题', trigger: 'blur' }],
  type: [{ required: true, message: '请选择公告类型', trigger: 'change' }],
  content: [{ required: true, message: '请输入公告内容', trigger: 'blur' }],
};

onMounted(() => {
  handleQuery();
});

async function handleQuery() {
  loading.value = true;
  try {
    const res = await getNoticeList(queryParams);
    tableData.value = res.data.list;
    total.value = res.data.total;
  } finally {
    loading.value = false;
  }
}

function handleReset() {
  queryParams.pageNum = 1;
  queryParams.title = '';
  queryParams.type = undefined;
  handleQuery();
}

function openDialog(row?: any) {
  resetForm();
  if (row) {
    dialogTitle.value = '编辑公告';
    Object.assign(formData, row);
  } else {
    dialogTitle.value = '新增公告';
  }
  dialogVisible.value = true;
}

function closeDialog() {
  dialogVisible.value = false;
  resetForm();
}

function resetForm() {
  formData.id = undefined;
  formData.title = '';
  formData.type = 1;
  formData.content = '';
  formData.remark = '';
  formRef.value?.resetFields();
}

async function handleSubmit() {
  const valid = await formRef.value?.validate().catch(() => false);
  if (!valid) return;

  submitLoading.value = true;
  try {
    if (formData.id) {
      await updateNotice(formData.id, formData);
      ElMessage.success('修改成功');
    } else {
      await createNotice(formData);
      ElMessage.success('新增成功');
    }
    closeDialog();
    handleQuery();
  } finally {
    submitLoading.value = false;
  }
}

async function handleDelete(id: number) {
  await ElMessageBox.confirm('确认删除该公告？', '提示', { type: 'warning' });
  await deleteNotice(id);
  ElMessage.success('删除成功');
  handleQuery();
}

async function handlePublish(id: number) {
  await ElMessageBox.confirm('确认发布该公告？', '提示', { type: 'info' });
  await publishNotice(id);
  ElMessage.success('发布成功');
  handleQuery();
}

async function handleRevoke(id: number) {
  await ElMessageBox.confirm('确认撤回该公告？', '提示', { type: 'warning' });
  await revokeNotice(id);
  ElMessage.success('撤回成功');
  handleQuery();
}
</script>
