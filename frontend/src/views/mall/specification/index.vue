<template>
  <div class="app-container">
    <div class="layout-row">
      <!-- 左：规格组列表 -->
      <div class="card-container master">
        <div style="margin-bottom: 12px; display: flex; justify-content: space-between">
          <h3 style="margin: 0">规格组</h3>
          <el-button size="small" type="primary" v-hasPerm="['mall:specification:add']" @click="openSpecDialog()">
            <el-icon><Plus /></el-icon>新增
          </el-button>
        </div>

        <el-table v-loading="specsLoading" :data="specs" border highlight-current-row @row-click="selectSpec">
          <el-table-column label="规格组" min-width="160">
            <template #default="{ row }">
              <span class="font-medium">{{ primaryName(row.translations) }}</span>
              <span class="text-xs text-gray-500" style="margin-left: 8px">({{ row.code || '-' }})</span>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="80" align="center">
            <template #default="{ row }">
              <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                {{ row.status === 1 ? '启用' : '禁用' }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="120" align="center">
            <template #default="{ row }">
              <el-button
                type="primary"
                link
                size="small"
                v-hasPerm="['mall:specification:edit']"
                @click.stop="openSpecDialog(row)"
                >编辑</el-button
              >
              <el-button
                type="danger"
                link
                size="small"
                v-hasPerm="['mall:specification:delete']"
                @click.stop="deleteSpec(row.id)"
                >删除</el-button
              >
            </template>
          </el-table-column>
        </el-table>
      </div>

      <!-- 右：选中规格的值列表 -->
      <div class="card-container detail">
        <div v-if="!currentSpec" class="placeholder">请先选择左侧的规格组</div>
        <div v-else>
          <div style="margin-bottom: 12px; display: flex; justify-content: space-between">
            <h3 style="margin: 0">{{ primaryName(currentSpec.translations) }} 的值</h3>
            <el-button size="small" type="primary" v-hasPerm="['mall:specification:edit']" @click="openValueDialog()">
              <el-icon><Plus /></el-icon>新增值
            </el-button>
          </div>
          <el-table v-loading="valuesLoading" :data="values" border>
            <el-table-column label="值" min-width="160">
              <template #default="{ row }">
                <span class="font-medium">{{ primaryName(row.translations) }}</span>
                <span class="text-xs text-gray-500" style="margin-left: 8px">({{ row.code || '-' }})</span>
              </template>
            </el-table-column>
            <el-table-column label="排序" prop="sort" width="80" align="center" />
            <el-table-column label="状态" width="80" align="center">
              <template #default="{ row }">
                <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">{{
                  row.status === 1 ? '启用' : '禁用'
                }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="120" align="center">
              <template #default="{ row }">
                <el-button
                  type="primary"
                  link
                  size="small"
                  v-hasPerm="['mall:specification:edit']"
                  @click="openValueDialog(row)"
                  >编辑</el-button
                >
                <el-button
                  type="danger"
                  link
                  size="small"
                  v-hasPerm="['mall:specification:delete']"
                  @click="deleteValue(row.id)"
                  >删除</el-button
                >
              </template>
            </el-table-column>
          </el-table>
        </div>
      </div>
    </div>

    <!-- Spec Dialog -->
    <el-dialog v-model="specDialog.open" :title="specDialog.id ? '编辑规格组' : '新增规格组'" width="500px">
      <el-form :model="specDialog" label-width="80px">
        <el-form-item label="编码"
          ><el-input v-model="specDialog.code" placeholder="lowercase + hyphen"
        /></el-form-item>
        <el-form-item label="排序"><el-input-number v-model="specDialog.sort" :min="0" /></el-form-item>
        <el-form-item label="状态"><el-switch v-model="specDialog.statusOn" /></el-form-item>
        <el-divider>多语言（zh-CN 必填）</el-divider>
        <div v-for="(tr, idx) in specDialog.translations" :key="idx" class="translation-row">
          <el-input v-model="tr.locale" placeholder="locale" style="width: 100px" />
          <el-input v-model="tr.name" placeholder="名称" style="flex: 1; margin: 0 8px" />
          <el-button
            v-if="specDialog.translations.length > 1"
            type="danger"
            link
            @click="specDialog.translations.splice(idx, 1)"
            >×</el-button
          >
        </div>
        <el-button size="small" @click="specDialog.translations.push({ locale: '', name: '' })">+ 添加语言</el-button>
      </el-form>
      <template #footer>
        <el-button @click="specDialog.open = false">取消</el-button>
        <el-button type="primary" @click="submitSpec">保存</el-button>
      </template>
    </el-dialog>

    <!-- Value Dialog -->
    <el-dialog v-model="valueDialog.open" :title="valueDialog.id ? '编辑值' : '新增值'" width="500px">
      <el-form :model="valueDialog" label-width="80px">
        <el-form-item label="编码"
          ><el-input v-model="valueDialog.code" placeholder="lowercase + hyphen"
        /></el-form-item>
        <el-form-item label="排序"><el-input-number v-model="valueDialog.sort" :min="0" /></el-form-item>
        <el-form-item label="状态"><el-switch v-model="valueDialog.statusOn" /></el-form-item>
        <el-divider>多语言（zh-CN 必填）</el-divider>
        <div v-for="(tr, idx) in valueDialog.translations" :key="idx" class="translation-row">
          <el-input v-model="tr.locale" placeholder="locale" style="width: 100px" />
          <el-input v-model="tr.name" placeholder="名称" style="flex: 1; margin: 0 8px" />
          <el-button
            v-if="valueDialog.translations.length > 1"
            type="danger"
            link
            @click="valueDialog.translations.splice(idx, 1)"
            >×</el-button
          >
        </div>
        <el-button size="small" @click="valueDialog.translations.push({ locale: '', name: '' })">+ 添加语言</el-button>
      </el-form>
      <template #footer>
        <el-button @click="valueDialog.open = false">取消</el-button>
        <el-button type="primary" @click="submitValue">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import {
  createMallSpec,
  createMallSpecValue,
  deleteMallSpec,
  deleteMallSpecValue,
  getMallSpecList,
  getMallSpecValues,
  updateMallSpec,
  updateMallSpecValue,
} from '@/api/mall/specification';
import type {
  SpecificationRow,
  SpecificationTranslation,
  SpecificationValueRow,
  SpecificationValueTranslation,
} from '@/types/api/mall/specification';

/**
 * Mall 后台规格组管理（M10-PR39）：左主右从布局。
 *
 * - 选中规格组 → 右侧加载其值列表
 * - dialog 共两个：spec 组 + value 值，结构对称
 */

const specs = ref<SpecificationRow[]>([]);
const specsLoading = ref(false);
const currentSpec = ref<SpecificationRow | null>(null);
const values = ref<SpecificationValueRow[]>([]);
const valuesLoading = ref(false);

function primaryName(translations?: { locale: string; name: string }[]): string {
  const tr = translations?.find((t) => t.locale === 'zh-CN') ?? translations?.[0];
  return tr?.name ?? '';
}

async function fetchSpecs() {
  specsLoading.value = true;
  try {
    const res = await getMallSpecList({ pageSize: 100 });
    specs.value = res.data.list;
  } catch (e) {
    ElMessage.error((e as Error).message || '加载失败');
  } finally {
    specsLoading.value = false;
  }
}

async function selectSpec(row: SpecificationRow) {
  currentSpec.value = row;
  valuesLoading.value = true;
  try {
    const res = await getMallSpecValues(row.id);
    values.value = res.data;
  } catch (e) {
    ElMessage.error((e as Error).message || '加载值失败');
  } finally {
    valuesLoading.value = false;
  }
}

// ===== Spec Dialog =====
const specDialog = reactive({
  open: false,
  id: null as number | null,
  code: '',
  sort: 0,
  statusOn: true,
  translations: [] as SpecificationTranslation[],
});

function openSpecDialog(row?: SpecificationRow) {
  if (row) {
    specDialog.id = row.id;
    specDialog.code = row.code || '';
    specDialog.sort = row.sort || 0;
    specDialog.statusOn = row.status === 1;
    specDialog.translations =
      row.translations?.length > 0 ? row.translations.map((t) => ({ ...t })) : [{ locale: 'zh-CN', name: '' }];
  } else {
    specDialog.id = null;
    specDialog.code = '';
    specDialog.sort = 0;
    specDialog.statusOn = true;
    specDialog.translations = [{ locale: 'zh-CN', name: '' }];
  }
  specDialog.open = true;
}

async function submitSpec() {
  const first = specDialog.translations[0];
  if (!first?.name) {
    ElMessage.warning('请填写名称');
    return;
  }
  const payload = {
    code: specDialog.code || undefined,
    sort: specDialog.sort,
    status: specDialog.statusOn ? 1 : 0,
    translations: specDialog.translations.filter((t) => t.locale && t.name),
  };
  try {
    if (specDialog.id) {
      await updateMallSpec(specDialog.id, payload);
    } else {
      await createMallSpec(payload);
    }
    ElMessage.success('保存成功');
    specDialog.open = false;
    await fetchSpecs();
  } catch (e) {
    ElMessage.error((e as Error).message || '保存失败');
  }
}

async function deleteSpec(id: number) {
  try {
    await ElMessageBox.confirm('确认删除该规格组（其下值会一并删除）？', '删除', {
      type: 'warning',
      confirmButtonText: '删除',
      cancelButtonText: '取消',
    });
  } catch {
    return;
  }
  try {
    await deleteMallSpec(id);
    ElMessage.success('删除成功');
    if (currentSpec.value?.id === id) {
      currentSpec.value = null;
      values.value = [];
    }
    fetchSpecs();
  } catch (e) {
    ElMessage.error((e as Error).message || '删除失败');
  }
}

// ===== Value Dialog =====
const valueDialog = reactive({
  open: false,
  id: null as number | null,
  code: '',
  sort: 0,
  statusOn: true,
  translations: [] as SpecificationValueTranslation[],
});

function openValueDialog(row?: SpecificationValueRow) {
  if (row) {
    valueDialog.id = row.id;
    valueDialog.code = row.code || '';
    valueDialog.sort = row.sort || 0;
    valueDialog.statusOn = row.status === 1;
    valueDialog.translations =
      row.translations?.length > 0 ? row.translations.map((t) => ({ ...t })) : [{ locale: 'zh-CN', name: '' }];
  } else {
    valueDialog.id = null;
    valueDialog.code = '';
    valueDialog.sort = 0;
    valueDialog.statusOn = true;
    valueDialog.translations = [{ locale: 'zh-CN', name: '' }];
  }
  valueDialog.open = true;
}

async function submitValue() {
  if (!currentSpec.value) return;
  const first = valueDialog.translations[0];
  if (!first?.name) {
    ElMessage.warning('请填写名称');
    return;
  }
  const payload = {
    code: valueDialog.code || undefined,
    sort: valueDialog.sort,
    status: valueDialog.statusOn ? 1 : 0,
    translations: valueDialog.translations.filter((t) => t.locale && t.name),
  };
  try {
    if (valueDialog.id) {
      await updateMallSpecValue(valueDialog.id, payload);
    } else {
      await createMallSpecValue(currentSpec.value.id, payload);
    }
    ElMessage.success('保存成功');
    valueDialog.open = false;
    await selectSpec(currentSpec.value);
  } catch (e) {
    ElMessage.error((e as Error).message || '保存失败');
  }
}

async function deleteValue(id: number) {
  try {
    await ElMessageBox.confirm('确认删除该值？', '删除', {
      type: 'warning',
      confirmButtonText: '删除',
      cancelButtonText: '取消',
    });
  } catch {
    return;
  }
  try {
    await deleteMallSpecValue(id);
    ElMessage.success('删除成功');
    if (currentSpec.value) await selectSpec(currentSpec.value);
  } catch (e) {
    ElMessage.error((e as Error).message || '删除失败');
  }
}

onMounted(fetchSpecs);
</script>

<style scoped>
.layout-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
@media (max-width: 1024px) {
  .layout-row {
    grid-template-columns: 1fr;
  }
}
.placeholder {
  text-align: center;
  color: var(--el-text-color-placeholder);
  padding: 60px 0;
}
.translation-row {
  display: flex;
  align-items: center;
  margin-bottom: 8px;
}
</style>
