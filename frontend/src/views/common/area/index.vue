<template>
  <div class="app-container">
    <div class="search-container">
      <el-form :model="queryParams" :inline="true">
        <el-form-item label="名称/简称">
          <el-input
            v-model="queryParams.keywords"
            placeholder="请输入名称或简称"
            clearable
            style="width: 200px"
            @keyup.enter="handleQuery"
          />
        </el-form-item>
        <el-form-item label="级别">
          <el-select v-model="queryParams.level" placeholder="全部" clearable style="width: 120px">
            <el-option label="省/直辖市" :value="1" />
            <el-option label="市" :value="2" />
            <el-option label="区/县" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="queryParams.status" placeholder="全部" clearable style="width: 100px">
            <el-option label="有效" :value="1" />
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
        <el-button type="primary" v-hasPerm="['sys:area:add']" @click="openDialog()">
          <el-icon><Plus /></el-icon>新增地区
        </el-button>
      </div>

      <el-table v-loading="loading" :data="areaList" border stripe>
        <el-table-column label="ID" prop="id" width="80" align="center" />
        <el-table-column label="名称" prop="name" min-width="140" />
        <el-table-column label="简称" prop="shortname" width="80" align="center" />
        <el-table-column label="级别" prop="level" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="levelTagType(row.level)" size="small">
              {{ levelLabel(row.level) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="父级 ID" prop="pid" width="90" align="center" />
        <el-table-column label="经度" prop="longitude" width="110" align="center" />
        <el-table-column label="纬度" prop="latitude" width="110" align="center" />
        <el-table-column label="排序" prop="sort" width="70" align="center" />
        <el-table-column label="状态" prop="status" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
              {{ row.status === 1 ? '有效' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="140" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link v-hasPerm="['sys:area:edit']" @click="openDialog(row.id)">
              编辑
            </el-button>
            <el-button type="danger" link v-hasPerm="['sys:area:delete']" @click="handleDelete(row.id)">
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container">
        <el-pagination
          v-model:current-page="queryParams.pageNum"
          v-model:page-size="queryParams.pageSize"
          :page-sizes="[15, 30, 50, 100]"
          :total="total"
          layout="total, sizes, prev, pager, next"
          @size-change="handleQuery"
          @current-change="handleQuery"
        />
      </div>
    </div>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="520px" @close="closeDialog">
      <el-form ref="formRef" :model="form" :rules="formRules" label-width="80px">
        <el-form-item label="名称" prop="name">
          <el-input v-model="form.name" placeholder="请输入地区名称" maxlength="50" />
        </el-form-item>
        <el-form-item label="简称" prop="shortname">
          <el-input v-model="form.shortname" placeholder="请输入简称" maxlength="30" />
        </el-form-item>
        <el-form-item label="父级 ID" prop="pid">
          <el-input-number v-model="form.pid" :min="0" style="width: 100%" />
        </el-form-item>
        <el-form-item label="级别" prop="level">
          <el-select v-model="form.level" placeholder="请选择级别" style="width: 100%">
            <el-option label="省/直辖市" :value="1" />
            <el-option label="市" :value="2" />
            <el-option label="区/县" :value="3" />
          </el-select>
        </el-form-item>
        <el-form-item label="经度" prop="longitude">
          <el-input v-model="form.longitude" placeholder="请输入经度" maxlength="30" />
        </el-form-item>
        <el-form-item label="纬度" prop="latitude">
          <el-input v-model="form.latitude" placeholder="请输入纬度" maxlength="30" />
        </el-form-item>
        <el-form-item label="排序" prop="sort">
          <el-input-number v-model="form.sort" :min="0" style="width: 100%" />
        </el-form-item>
        <el-form-item label="状态" prop="status">
          <el-radio-group v-model="form.status">
            <el-radio :value="1">有效</el-radio>
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
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox, type FormInstance } from 'element-plus'
import { getAreaList, getAreaDetail, createArea, updateArea, deleteArea } from '@/api/area'
import type { AreaItem } from '@/types/api/area'

const loading = ref(false)
const submitLoading = ref(false)
const areaList = ref<AreaItem[]>([])
const total = ref(0)
const dialogVisible = ref(false)
const dialogTitle = ref('')
const formRef = ref<FormInstance>()

const queryParams = reactive({
  keywords: '',
  level: '' as number | '',
  status: '' as number | '',
  pageNum: 1,
  pageSize: 15,
})

const form = reactive({
  id: undefined as number | undefined,
  pid: 0,
  name: '',
  shortname: '',
  longitude: '',
  latitude: '',
  level: undefined as number | undefined,
  sort: 0,
  status: 1,
})

const formRules = {
  name: [{ required: true, message: '请输入地区名称', trigger: 'blur' }],
  level: [{ required: true, message: '请选择级别', trigger: 'change' }],
}

function levelLabel(level: number): string {
  const map: Record<number, string> = { 1: '省/直辖市', 2: '市', 3: '区/县' }
  return map[level] ?? `L${level}`
}

function levelTagType(level: number): undefined | 'success' | 'warning' {
  const map: Record<number, undefined | 'success' | 'warning'> = { 1: undefined, 2: 'success', 3: 'warning' }
  return map[level]
}

onMounted(() => {
  handleQuery()
})

async function handleQuery() {
  loading.value = true
  try {
    const res = await getAreaList(queryParams)
    areaList.value = res.data.list
    total.value = res.data.total
  } finally {
    loading.value = false
  }
}

function handleReset() {
  queryParams.keywords = ''
  queryParams.level = ''
  queryParams.status = ''
  queryParams.pageNum = 1
  handleQuery()
}

function resetForm() {
  form.id = undefined
  form.pid = 0
  form.name = ''
  form.shortname = ''
  form.longitude = ''
  form.latitude = ''
  form.level = undefined
  form.sort = 0
  form.status = 1
}

async function openDialog(id?: number) {
  resetForm()
  if (id) {
    dialogTitle.value = '编辑地区'
    const res = await getAreaDetail(id)
    Object.assign(form, res.data)
  } else {
    dialogTitle.value = '新增地区'
  }
  dialogVisible.value = true
}

function closeDialog() {
  dialogVisible.value = false
  formRef.value?.resetFields()
}

async function handleSubmit() {
  const valid = await formRef.value?.validate().catch(() => false)
  if (!valid) return

  submitLoading.value = true
  try {
    if (form.id) {
      await updateArea(form.id, form)
      ElMessage.success('修改成功')
    } else {
      await createArea(form)
      ElMessage.success('新增成功')
    }
    closeDialog()
    handleQuery()
  } finally {
    submitLoading.value = false
  }
}

async function handleDelete(id: number) {
  await ElMessageBox.confirm('确认删除该地区？若存在子级将无法删除。', '提示', { type: 'warning' })
  try {
    await deleteArea(id)
    ElMessage.success('删除成功')
    handleQuery()
  } catch {
    // 错误已由 request 拦截器统一弹出
  }
}
</script>
