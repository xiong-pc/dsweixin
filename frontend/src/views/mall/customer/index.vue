<template>
  <div class="app-container">
    <div class="search-container">
      <el-form :model="queryParams" :inline="true">
        <el-form-item label="关键词">
          <el-input
            v-model="queryParams.keywords"
            placeholder="邮箱 / 手机 / 昵称"
            clearable
            @keyup.enter="handleQuery"
          />
        </el-form-item>
        <el-form-item label="分组">
          <el-select v-model="queryParams.group_id" placeholder="全部" clearable style="width: 160px">
            <el-option v-for="g in groupOptions" :key="g.id" :label="g.name" :value="g.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="queryParams.status" placeholder="全部" clearable style="width: 120px">
            <el-option label="启用" :value="1" />
            <el-option label="禁用" :value="0" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleQuery">
            <el-icon><Search /></el-icon>搜索
          </el-button>
          <el-button @click="handleReset">
            <el-icon><Refresh /></el-icon>重置
          </el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="card-container">
      <el-table v-loading="loading" :data="tableData" border stripe>
        <el-table-column label="头像" width="70" align="center">
          <template #default="{ row }">
            <el-avatar v-if="row.avatar" :src="row.avatar" :size="36" />
            <el-avatar v-else :size="36">{{ (row.name || row.email || '#')[0] }}</el-avatar>
          </template>
        </el-table-column>
        <el-table-column label="账号" min-width="200">
          <template #default="{ row }">
            <div class="font-medium">{{ row.name || '-' }}</div>
            <div class="text-xs text-gray-500">
              {{ row.email || row.phone || `#${row.id}` }}
            </div>
          </template>
        </el-table-column>
        <el-table-column label="分组" min-width="120">
          <template #default="{ row }">
            <el-tag v-if="row.group" size="small" type="primary">{{ groupName(row.group) }}</el-tag>
            <span v-else class="text-gray-400">无</span>
          </template>
        </el-table-column>
        <el-table-column label="语言/币种" width="120" align="center">
          <template #default="{ row }"> {{ row.locale || '-' }} / {{ row.currency || '-' }} </template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-switch
              v-model="row.status"
              :active-value="1"
              :inactive-value="0"
              v-hasPerm="['mall:customer:edit']"
              @change="handleStatusChange(row)"
            />
          </template>
        </el-table-column>
        <el-table-column label="最近登录" prop="last_login_at" min-width="160" />
        <el-table-column label="注册时间" prop="created_at" min-width="160" />
        <el-table-column label="操作" width="160" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link v-hasPerm="['mall:customer:edit']" @click="openEdit(row)">编辑</el-button>
            <el-button type="danger" link v-hasPerm="['mall:customer:delete']" @click="handleDelete(row.id)"
              >删除</el-button
            >
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

    <el-dialog v-model="editDialog.open" title="编辑客户" width="500px">
      <el-form :model="editDialog" label-width="100px">
        <el-form-item label="昵称">
          <el-input v-model="editDialog.name" />
        </el-form-item>
        <el-form-item label="分组">
          <el-select v-model="editDialog.group_id" placeholder="无" clearable style="width: 100%">
            <el-option v-for="g in groupOptions" :key="g.id" :label="g.name" :value="g.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="语言">
          <el-select v-model="editDialog.locale" placeholder="默认" clearable style="width: 100%">
            <el-option label="简体中文" value="zh-CN" />
            <el-option label="English" value="en" />
            <el-option label="日本語" value="ja" />
            <el-option label="한국어" value="ko" />
          </el-select>
        </el-form-item>
        <el-form-item label="币种">
          <el-select v-model="editDialog.currency" placeholder="默认" clearable style="width: 120px">
            <el-option label="USD" value="USD" />
            <el-option label="CNY" value="CNY" />
            <el-option label="EUR" value="EUR" />
            <el-option label="JPY" value="JPY" />
            <el-option label="KRW" value="KRW" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="editDialog.statusOn" />
        </el-form-item>
        <el-alert type="info" :closable="false" style="margin-top: 8px">
          注：邮箱 / 手机 / 密码由客户前台自助流程修改，admin 不可直接改。
        </el-alert>
      </el-form>
      <template #footer>
        <el-button @click="editDialog.open = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submitEdit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Refresh } from '@element-plus/icons-vue';
import {
  deleteMallCustomer,
  getMallCustomerGroupList,
  getMallCustomerList,
  updateMallCustomer,
} from '@/api/mall/customer';
import type { CustomerGroupBrief, CustomerListQuery, CustomerRow } from '@/types/api/mall/customer';

/**
 * Mall 后台客户管理（M10-PR41）：列表 + 编辑 dialog（无 create，前台注册才能产生）。
 *
 * - 状态 el-switch 直接 PUT update
 * - 删除走软删除
 * - 邮箱/手机/密码由前台自助改，admin 不可改
 */
const queryParams = reactive<CustomerListQuery>({
  keywords: '',
  status: '',
  group_id: undefined,
  page: 1,
  pageSize: 20,
});

const tableData = ref<CustomerRow[]>([]);
const total = ref(0);
const loading = ref(false);
const submitting = ref(false);

const groupOptions = ref<{ id: number; name: string }[]>([]);

function groupName(g: CustomerGroupBrief | null): string {
  if (!g) return '';
  const tr = g.translations?.find((t) => t.locale === 'zh-CN') ?? g.translations?.[0];
  return tr?.name ?? g.code ?? `#${g.id}`;
}

async function handleQuery() {
  loading.value = true;
  try {
    const params: CustomerListQuery = {
      keywords: queryParams.keywords || undefined,
      status: queryParams.status === '' ? undefined : (queryParams.status as number),
      group_id: queryParams.group_id || undefined,
      page: queryParams.page,
      pageSize: queryParams.pageSize,
    };
    const res = await getMallCustomerList(params);
    tableData.value = res.data.list;
    total.value = res.data.total;
  } catch (e) {
    ElMessage.error((e as Error).message || '获取客户失败');
  } finally {
    loading.value = false;
  }
}

function handleReset() {
  queryParams.keywords = '';
  queryParams.status = '';
  queryParams.group_id = undefined;
  queryParams.page = 1;
  handleQuery();
}

async function handleStatusChange(row: CustomerRow) {
  try {
    await updateMallCustomer(row.id, { status: row.status });
    ElMessage.success(row.status === 1 ? '已启用' : '已禁用');
  } catch (e) {
    row.status = row.status === 1 ? 0 : 1;
    ElMessage.error((e as Error).message || '状态更新失败');
  }
}

async function handleDelete(id: number) {
  try {
    await ElMessageBox.confirm('确认删除该客户？', '删除客户', {
      confirmButtonText: '删除',
      cancelButtonText: '取消',
      type: 'warning',
    });
  } catch {
    return;
  }
  try {
    await deleteMallCustomer(id);
    ElMessage.success('删除成功');
    handleQuery();
  } catch (e) {
    ElMessage.error((e as Error).message || '删除失败');
  }
}

// ===== Edit Dialog =====
const editDialog = reactive({
  open: false,
  id: 0,
  name: '',
  group_id: null as number | null,
  locale: '',
  currency: '',
  statusOn: true,
});

function openEdit(row: CustomerRow) {
  editDialog.id = row.id;
  editDialog.name = row.name || '';
  editDialog.group_id = row.group_id;
  editDialog.locale = row.locale || '';
  editDialog.currency = row.currency || '';
  editDialog.statusOn = row.status === 1;
  editDialog.open = true;
}

async function submitEdit() {
  submitting.value = true;
  try {
    await updateMallCustomer(editDialog.id, {
      name: editDialog.name,
      group_id: editDialog.group_id,
      locale: editDialog.locale || undefined,
      currency: editDialog.currency || undefined,
      status: editDialog.statusOn ? 1 : 0,
    });
    ElMessage.success('保存成功');
    editDialog.open = false;
    handleQuery();
  } catch (e) {
    ElMessage.error((e as Error).message || '保存失败');
  } finally {
    submitting.value = false;
  }
}

async function loadGroups() {
  try {
    const res = await getMallCustomerGroupList({ pageSize: 100 });
    groupOptions.value = res.data.list.map((g) => ({
      id: g.id,
      name: groupName({
        id: g.id,
        code: g.code,
        discount_rate: g.discount_rate,
        translations: g.translations,
      }),
    }));
  } catch {
    /* ignore */
  }
}

onMounted(() => {
  loadGroups();
  handleQuery();
});
</script>
