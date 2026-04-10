<template>
  <div class="app-container">
    <div class="search-container">
      <el-form :model="queryParams" :inline="true">
        <el-form-item label="字典名称">
          <el-input v-model="queryParams.name" placeholder="请输入字典名称" clearable @keyup.enter="handleQuery" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleQuery"><el-icon><Search /></el-icon>搜索</el-button>
          <el-button @click="handleReset"><el-icon><Refresh /></el-icon>重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <el-row :gutter="12">
      <el-col :span="14">
        <div class="card-container">
          <div style="margin-bottom: 12px">
            <el-button type="primary" v-hasPerm="['sys:dict:add']" @click="openDictDialog()">
              <el-icon><Plus /></el-icon>新增字典
            </el-button>
          </div>

          <el-table v-loading="loading" :data="dictList" border stripe highlight-current-row @current-change="handleDictSelect">
            <el-table-column label="字典编号" prop="id" width="80" align="center" />
            <el-table-column label="字典名称" prop="name" min-width="120" />
            <el-table-column label="字典编码" prop="code" min-width="120" />
            <el-table-column label="状态" prop="status" width="80" align="center">
              <template #default="{ row }">
                <el-tag :type="row.status === 1 ? 'success' : 'danger'">
                  {{ row.status === 1 ? '正常' : '禁用' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="150" align="center">
              <template #default="{ row }">
                <el-button type="primary" link v-hasPerm="['sys:dict:edit']" @click="openDictDialog(row.id)">编辑</el-button>
                <el-button type="danger" link v-hasPerm="['sys:dict:delete']" @click="handleDeleteDict(row.id)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>

          <div class="pagination-container">
            <el-pagination
              v-model:current-page="queryParams.pageNum"
              v-model:page-size="queryParams.pageSize"
              :page-sizes="[10, 20, 50]"
              :total="total"
              layout="total, sizes, prev, pager, next"
              @size-change="handleQuery"
              @current-change="handleQuery"
            />
          </div>
        </div>
      </el-col>

      <el-col :span="10">
        <div class="card-container">
          <div style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center">
            <span style="font-weight: 600">{{ currentDict?.name || '字典项' }}</span>
            <el-button type="primary" :disabled="!currentDict" v-hasPerm="['sys:dict:add']" @click="openItemDialog()">
              <el-icon><Plus /></el-icon>新增项
            </el-button>
          </div>

          <el-table v-loading="itemLoading" :data="dictItems" border stripe>
            <el-table-column label="标签" prop="label" min-width="100" />
            <el-table-column label="值" prop="value" min-width="80" />
            <el-table-column label="排序" prop="sort" width="60" align="center" />
            <el-table-column label="状态" prop="status" width="70" align="center">
              <template #default="{ row }">
                <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
                  {{ row.status === 1 ? '启用' : '禁用' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="120" align="center">
              <template #default="{ row }">
                <el-button type="primary" link size="small" @click="openItemDialog(row)">编辑</el-button>
                <el-button type="danger" link size="small" @click="handleDeleteItem(row.id)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
        </div>
      </el-col>
    </el-row>

    <el-dialog v-model="dictDialogVisible" :title="dictDialogTitle" width="500px" @close="closeDictDialog">
      <el-form ref="dictFormRef" :model="dictForm" :rules="dictFormRules" label-width="80px">
        <el-form-item label="字典名称" prop="name">
          <el-input v-model="dictForm.name" placeholder="请输入字典名称" />
        </el-form-item>
        <el-form-item label="字典编码" prop="code">
          <el-input v-model="dictForm.code" placeholder="请输入字典编码" />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="dictForm.status">
            <el-radio :value="1">正常</el-radio>
            <el-radio :value="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="备注" prop="remark">
          <el-input v-model="dictForm.remark" type="textarea" placeholder="请输入备注" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="closeDictDialog">取 消</el-button>
        <el-button type="primary" :loading="submitLoading" @click="handleDictSubmit">确 定</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="itemDialogVisible" :title="itemDialogTitle" width="500px" @close="closeItemDialog">
      <el-form ref="itemFormRef" :model="itemForm" :rules="itemFormRules" label-width="80px">
        <el-form-item label="标签" prop="label">
          <el-input v-model="itemForm.label" placeholder="请输入标签" />
        </el-form-item>
        <el-form-item label="值" prop="value">
          <el-input v-model="itemForm.value" placeholder="请输入值" />
        </el-form-item>
        <el-form-item label="排序" prop="sort">
          <el-input-number v-model="itemForm.sort" :min="0" />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="itemForm.status">
            <el-radio :value="1">启用</el-radio>
            <el-radio :value="0">禁用</el-radio>
          </el-radio-group>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="closeItemDialog">取 消</el-button>
        <el-button type="primary" :loading="itemSubmitLoading" @click="handleItemSubmit">确 定</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox, type FormInstance } from 'element-plus';
import { getDictList, createDict, updateDict, deleteDict, getDictItems, createDictItem, updateDictItem, deleteDictItem } from '@/api/dict';

const loading = ref(false);
const itemLoading = ref(false);
const submitLoading = ref(false);
const itemSubmitLoading = ref(false);
const dictList = ref<any[]>([]);
const dictItems = ref<any[]>([]);
const total = ref(0);
const currentDict = ref<any>(null);
const dictDialogVisible = ref(false);
const itemDialogVisible = ref(false);
const dictDialogTitle = ref('');
const itemDialogTitle = ref('');
const dictFormRef = ref<FormInstance>();
const itemFormRef = ref<FormInstance>();

const queryParams = reactive({ pageNum: 1, pageSize: 10, name: '' });

const dictForm = reactive({
  id: undefined as number | undefined,
  name: '',
  code: '',
  status: 1,
  remark: '',
});

const dictFormRules = {
  name: [{ required: true, message: '请输入字典名称', trigger: 'blur' }],
  code: [{ required: true, message: '请输入字典编码', trigger: 'blur' }],
};

const itemForm = reactive({
  id: undefined as number | undefined,
  dict_id: undefined as number | undefined,
  label: '',
  value: '',
  sort: 0,
  status: 1,
});

const itemFormRules = {
  label: [{ required: true, message: '请输入标签', trigger: 'blur' }],
  value: [{ required: true, message: '请输入值', trigger: 'blur' }],
};

onMounted(() => {
  handleQuery();
});

async function handleQuery() {
  loading.value = true;
  try {
    const res = await getDictList(queryParams);
    dictList.value = res.data.list;
    total.value = res.data.total;
  } finally {
    loading.value = false;
  }
}

function handleReset() {
  queryParams.pageNum = 1;
  queryParams.name = '';
  handleQuery();
}

async function handleDictSelect(row: any) {
  if (!row) return;
  currentDict.value = row;
  itemLoading.value = true;
  try {
    const res = await getDictItems(row.id);
    dictItems.value = res.data || [];
  } finally {
    itemLoading.value = false;
  }
}

function openDictDialog(id?: number) {
  dictForm.id = undefined;
  dictForm.name = '';
  dictForm.code = '';
  dictForm.status = 1;
  dictForm.remark = '';
  if (id) {
    dictDialogTitle.value = '编辑字典';
    const dict = dictList.value.find((d) => d.id === id);
    if (dict) Object.assign(dictForm, dict);
  } else {
    dictDialogTitle.value = '新增字典';
  }
  dictDialogVisible.value = true;
}

function closeDictDialog() {
  dictDialogVisible.value = false;
  dictFormRef.value?.resetFields();
}

async function handleDictSubmit() {
  const valid = await dictFormRef.value?.validate().catch(() => false);
  if (!valid) return;

  submitLoading.value = true;
  try {
    if (dictForm.id) {
      await updateDict(dictForm.id, dictForm);
      ElMessage.success('修改成功');
    } else {
      await createDict(dictForm);
      ElMessage.success('新增成功');
    }
    closeDictDialog();
    handleQuery();
  } finally {
    submitLoading.value = false;
  }
}

async function handleDeleteDict(id: number) {
  await ElMessageBox.confirm('确认删除该字典？', '提示', { type: 'warning' });
  await deleteDict(id);
  ElMessage.success('删除成功');
  if (currentDict.value?.id === id) {
    currentDict.value = null;
    dictItems.value = [];
  }
  handleQuery();
}

function openItemDialog(row?: any) {
  itemForm.id = undefined;
  itemForm.dict_id = currentDict.value?.id;
  itemForm.label = '';
  itemForm.value = '';
  itemForm.sort = 0;
  itemForm.status = 1;
  if (row) {
    itemDialogTitle.value = '编辑字典项';
    Object.assign(itemForm, row);
  } else {
    itemDialogTitle.value = '新增字典项';
  }
  itemDialogVisible.value = true;
}

function closeItemDialog() {
  itemDialogVisible.value = false;
  itemFormRef.value?.resetFields();
}

async function handleItemSubmit() {
  const valid = await itemFormRef.value?.validate().catch(() => false);
  if (!valid) return;

  itemSubmitLoading.value = true;
  try {
    if (itemForm.id) {
      await updateDictItem(itemForm.id, itemForm);
      ElMessage.success('修改成功');
    } else {
      await createDictItem(itemForm);
      ElMessage.success('新增成功');
    }
    closeItemDialog();
    if (currentDict.value) handleDictSelect(currentDict.value);
  } finally {
    itemSubmitLoading.value = false;
  }
}

async function handleDeleteItem(id: number) {
  await ElMessageBox.confirm('确认删除该字典项？', '提示', { type: 'warning' });
  await deleteDictItem(id);
  ElMessage.success('删除成功');
  if (currentDict.value) handleDictSelect(currentDict.value);
}
</script>
