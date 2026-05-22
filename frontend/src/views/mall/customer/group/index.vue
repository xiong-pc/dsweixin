<template>
  <div class="app-container">
    <div class="search-container">
      <el-form :model="queryParams" :inline="true">
        <el-form-item label="关键词">
          <el-input v-model="queryParams.keywords" placeholder="名称 / 编码" clearable @keyup.enter="handleQuery" />
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
      <div style="margin-bottom: 12px">
        <el-button type="primary" v-hasPerm="['mall:customer_group:add']" @click="openDialog()">
          <el-icon><Plus /></el-icon>新增分组
        </el-button>
      </div>

      <el-table v-loading="loading" :data="tableData" border stripe>
        <el-table-column label="名称" min-width="180">
          <template #default="{ row }">
            <span class="font-medium">{{ primaryName(row.translations) }}</span>
            <span class="text-xs text-gray-500" style="margin-left: 8px">({{ row.code || '-' }})</span>
          </template>
        </el-table-column>
        <el-table-column label="折扣率" prop="discount_rate" width="120" align="right">
          <template #default="{ row }">
            <span class="font-semibold">{{ Number(row.discount_rate).toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="客户数" prop="customer_count" width="100" align="center">
          <template #default="{ row }">
            {{ row.customer_count ?? '-' }}
          </template>
        </el-table-column>
        <el-table-column label="排序" prop="sort" width="80" align="center" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
              {{ row.status === 1 ? '启用' : '禁用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="创建时间" prop="created_at" min-width="160" />
        <el-table-column label="操作" width="160" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link v-hasPerm="['mall:customer_group:edit']" @click="openDialog(row)"
              >编辑</el-button
            >
            <el-button type="danger" link v-hasPerm="['mall:customer_group:delete']" @click="handleDelete(row.id)"
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

    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑分组' : '新增分组'" width="600px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="编码">
          <el-input v-model="form.code" placeholder="lowercase + hyphen" />
        </el-form-item>
        <el-form-item label="折扣率">
          <el-input-number v-model="form.discount_rate" :min="0" :max="1" :step="0.01" :precision="2" />
          <span class="text-xs text-gray-500" style="margin-left: 8px"> 0.00 ~ 1.00（如 0.90 = 9 折） </span>
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="form.sort" :min="0" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="statusSwitch" />
        </el-form-item>
        <el-divider>多语言（zh-CN 必填）</el-divider>
        <div v-for="(tr, idx) in form.translations" :key="idx" class="translation-block">
          <el-row :gutter="8" style="margin-bottom: 8px">
            <el-col :span="8"><el-input v-model="tr.locale" placeholder="locale (zh-CN)" /></el-col>
            <el-col :span="14"><el-input v-model="tr.name" placeholder="名称" /></el-col>
            <el-col :span="2">
              <el-button
                v-if="form.translations.length > 1"
                type="danger"
                link
                @click="form.translations.splice(idx, 1)"
                >×</el-button
              >
            </el-col>
          </el-row>
          <el-input v-model="tr.description" placeholder="描述（可选）" />
        </div>
        <el-button size="small" @click="addTranslation">+ 添加语言</el-button>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Search, Refresh } from '@element-plus/icons-vue';
import {
  createMallCustomerGroup,
  deleteMallCustomerGroup,
  getMallCustomerGroupList,
  updateMallCustomerGroup,
} from '@/api/mall/customer';
import type { CustomerGroupListQuery, CustomerGroupRow, CustomerGroupTranslation } from '@/types/api/mall/customer';

/**
 * Mall 后台客户分组管理（M10-PR41）。
 * 删除分组前后端会检查是否仍有客户绑定，避免误删。
 */

const queryParams = reactive<CustomerGroupListQuery>({
  keywords: '',
  status: '',
  page: 1,
  pageSize: 20,
});

const tableData = ref<CustomerGroupRow[]>([]);
const total = ref(0);
const loading = ref(false);
const dialogVisible = ref(false);
const submitting = ref(false);
const statusSwitch = ref(true);

interface FormState {
  id: number | null;
  code: string;
  discount_rate: number;
  sort: number;
  translations: CustomerGroupTranslation[];
}

const form = reactive<FormState>({
  id: null,
  code: '',
  discount_rate: 1.0,
  sort: 0,
  translations: [{ locale: 'zh-CN', name: '', description: '' }],
});

function primaryName(translations: CustomerGroupTranslation[]): string {
  const tr = translations?.find((t) => t.locale === 'zh-CN') ?? translations?.[0];
  return tr?.name ?? '';
}

async function handleQuery() {
  loading.value = true;
  try {
    const params: CustomerGroupListQuery = {
      keywords: queryParams.keywords || undefined,
      status: queryParams.status === '' ? undefined : (queryParams.status as number),
      page: queryParams.page,
      pageSize: queryParams.pageSize,
    };
    const res = await getMallCustomerGroupList(params);
    tableData.value = res.data.list;
    total.value = res.data.total;
  } catch (e) {
    ElMessage.error((e as Error).message || '获取分组失败');
  } finally {
    loading.value = false;
  }
}

function handleReset() {
  queryParams.keywords = '';
  queryParams.status = '';
  queryParams.page = 1;
  handleQuery();
}

function openDialog(row?: CustomerGroupRow) {
  if (row) {
    form.id = row.id;
    form.code = row.code || '';
    form.discount_rate = Number(row.discount_rate) || 1.0;
    form.sort = row.sort || 0;
    statusSwitch.value = row.status === 1;
    form.translations =
      row.translations?.length > 0
        ? row.translations.map((t) => ({ ...t }))
        : [{ locale: 'zh-CN', name: '', description: '' }];
  } else {
    form.id = null;
    form.code = '';
    form.discount_rate = 1.0;
    form.sort = 0;
    statusSwitch.value = true;
    form.translations = [{ locale: 'zh-CN', name: '', description: '' }];
  }
  dialogVisible.value = true;
}

function addTranslation() {
  form.translations.push({ locale: '', name: '', description: '' });
}

async function submit() {
  const first = form.translations[0];
  if (!first || !first.name) {
    ElMessage.warning('至少填写一条翻译，且首条 name 不能为空');
    return;
  }
  submitting.value = true;
  try {
    const payload = {
      code: form.code || undefined,
      discount_rate: form.discount_rate,
      sort: form.sort,
      status: statusSwitch.value ? 1 : 0,
      translations: form.translations.filter((t) => t.locale && t.name),
    };
    if (form.id) {
      await updateMallCustomerGroup(form.id, payload);
    } else {
      await createMallCustomerGroup(payload);
    }
    ElMessage.success('保存成功');
    dialogVisible.value = false;
    await handleQuery();
  } catch (e) {
    ElMessage.error((e as Error).message || '保存失败');
  } finally {
    submitting.value = false;
  }
}

async function handleDelete(id: number) {
  try {
    await ElMessageBox.confirm('确认删除该分组？分组下仍有客户时后端会拒绝。', '删除分组', {
      confirmButtonText: '删除',
      cancelButtonText: '取消',
      type: 'warning',
    });
  } catch {
    return;
  }
  try {
    await deleteMallCustomerGroup(id);
    ElMessage.success('删除成功');
    handleQuery();
  } catch (e) {
    ElMessage.error((e as Error).message || '删除失败');
  }
}

onMounted(handleQuery);
</script>

<style scoped>
.translation-block {
  padding: 8px 0;
  border-bottom: 1px dashed var(--el-border-color-light);
}
.translation-block:last-of-type {
  border-bottom: none;
}
</style>
