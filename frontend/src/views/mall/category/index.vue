<template>
  <div class="app-container">
    <div class="card-container">
      <div style="margin-bottom: 12px">
        <el-button type="primary" v-hasPerm="['mall:category:add']" @click="openDialog()">
          <el-icon><Plus /></el-icon>新增类目
        </el-button>
      </div>

      <el-table
        v-loading="loading"
        :data="treeData"
        row-key="id"
        :default-expand-all="true"
        :tree-props="{ children: 'children' }"
        border
        stripe
      >
        <el-table-column label="名称" min-width="200">
          <template #default="{ row }">
            <span class="font-medium">{{ primaryName(row) }}</span>
            <span class="text-xs text-gray-500" style="margin-left: 8px">({{ row.code || '-' }})</span>
          </template>
        </el-table-column>
        <el-table-column label="封面" width="80" align="center">
          <template #default="{ row }">
            <el-image
              v-if="row.cover_image"
              :src="row.cover_image"
              fit="cover"
              style="width: 36px; height: 36px; border-radius: 4px"
            />
            <span v-else class="text-gray-400 text-xs">无</span>
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
        <el-table-column label="操作" width="160" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link v-hasPerm="['mall:category:edit']" @click="openDialog(row)">编辑</el-button>
            <el-button type="danger" link v-hasPerm="['mall:category:delete']" @click="handleDelete(row.id)"
              >删除</el-button
            >
          </template>
        </el-table-column>
      </el-table>
    </div>

    <el-dialog v-model="dialogVisible" :title="form.id ? '编辑类目' : '新增类目'" width="600px">
      <el-form :model="form" label-width="100px">
        <el-form-item label="父类目">
          <el-select v-model="form.parent_id" placeholder="无（顶级）" clearable style="width: 100%">
            <el-option v-for="opt in parentOptions" :key="opt.id" :label="opt.name" :value="opt.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="编码">
          <el-input v-model="form.code" placeholder="lowercase + hyphen" />
        </el-form-item>
        <el-form-item label="封面 URL">
          <el-input v-model="form.cover_image" placeholder="https://..." />
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
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import { createMallCategory, deleteMallCategory, getMallCategoryList, updateMallCategory } from '@/api/mall/category';
import type { CategoryRow, CategoryTranslation } from '@/types/api/mall/category';

/**
 * Mall 后台类目管理（M10-PR39）：树状列表 + 行内 dialog 表单。
 *
 * 后端按 sort + parent_id 排序；前端把扁平 list 重构为 tree。
 */

const flatList = ref<CategoryRow[]>([]);
const loading = ref(false);
const dialogVisible = ref(false);
const submitting = ref(false);
const statusSwitch = ref(true);

interface FormState {
  id: number | null;
  parent_id: number | null;
  code: string;
  cover_image: string;
  sort: number;
  translations: CategoryTranslation[];
}

const form = reactive<FormState>({
  id: null,
  parent_id: null,
  code: '',
  cover_image: '',
  sort: 0,
  translations: [{ locale: 'zh-CN', name: '', description: '' }],
});

const treeData = computed(() => buildTree(flatList.value, 0));

const parentOptions = computed(() =>
  flatList.value
    .filter((c) => c.id !== form.id)
    .map((c) => ({ id: c.id, name: primaryName(c) || c.code || `#${c.id}` })),
);

function buildTree(rows: CategoryRow[], parent: number): CategoryRow[] {
  return rows
    .filter((r) => r.parent_id === parent)
    .map((r) => ({
      ...r,
      children: buildTree(rows, r.id),
    }))
    .filter((r) => r.children!.length > 0 || true)
    .map((r) => (r.children!.length === 0 ? { ...r, children: undefined } : r));
}

function primaryName(row: CategoryRow): string {
  const tr = row.translations?.find((t) => t.locale === 'zh-CN') ?? row.translations?.[0];
  return tr?.name ?? '';
}

async function fetchList() {
  loading.value = true;
  try {
    const res = await getMallCategoryList();
    flatList.value = res.data.list;
  } catch (e) {
    ElMessage.error((e as Error).message || '加载类目失败');
  } finally {
    loading.value = false;
  }
}

function openDialog(row?: CategoryRow) {
  if (row) {
    form.id = row.id;
    form.parent_id = row.parent_id || null;
    form.code = row.code || '';
    form.cover_image = row.cover_image || '';
    form.sort = row.sort || 0;
    statusSwitch.value = row.status === 1;
    form.translations =
      row.translations?.length > 0
        ? row.translations.map((t) => ({ ...t }))
        : [{ locale: 'zh-CN', name: '', description: '' }];
  } else {
    form.id = null;
    form.parent_id = null;
    form.code = '';
    form.cover_image = '';
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
      parent_id: form.parent_id ?? 0,
      code: form.code || undefined,
      cover_image: form.cover_image || undefined,
      sort: form.sort,
      status: statusSwitch.value ? 1 : 0,
      translations: form.translations.filter((t) => t.locale && t.name),
    };
    if (form.id) {
      await updateMallCategory(form.id, payload);
    } else {
      await createMallCategory(payload);
    }
    ElMessage.success('保存成功');
    dialogVisible.value = false;
    await fetchList();
  } catch (e) {
    ElMessage.error((e as Error).message || '保存失败');
  } finally {
    submitting.value = false;
  }
}

async function handleDelete(id: number) {
  try {
    await ElMessageBox.confirm('确认删除该类目？', '删除类目', {
      confirmButtonText: '删除',
      cancelButtonText: '取消',
      type: 'warning',
    });
  } catch {
    return;
  }
  try {
    await deleteMallCategory(id);
    ElMessage.success('删除成功');
    fetchList();
  } catch (e) {
    ElMessage.error((e as Error).message || '删除失败');
  }
}

onMounted(fetchList);
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
