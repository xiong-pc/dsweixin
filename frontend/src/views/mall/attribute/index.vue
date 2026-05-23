<template>
  <div class="app-container">
    <div class="layout-row">
      <!-- 左：属性组列表 -->
      <div class="card-container master">
        <div style="margin-bottom: 12px; display: flex; justify-content: space-between">
          <h3 style="margin: 0">属性组</h3>
          <el-button size="small" type="primary" v-hasPerm="['mall:attribute:add']" @click="openAttrDialog()">
            <el-icon><Plus /></el-icon>新增
          </el-button>
        </div>

        <el-table v-loading="attrsLoading" :data="attrs" border highlight-current-row @row-click="selectAttr">
          <el-table-column label="属性组" min-width="160">
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
                v-hasPerm="['mall:attribute:edit']"
                @click.stop="openAttrDialog(row)"
                >编辑</el-button
              >
              <el-button
                type="danger"
                link
                size="small"
                v-hasPerm="['mall:attribute:delete']"
                @click.stop="deleteAttr(row.id)"
                >删除</el-button
              >
            </template>
          </el-table-column>
        </el-table>
      </div>

      <div class="card-container detail">
        <div v-if="!currentAttr" class="placeholder">请先选择左侧的属性组</div>
        <div v-else>
          <div style="margin-bottom: 12px; display: flex; justify-content: space-between">
            <h3 style="margin: 0">{{ primaryName(currentAttr.translations) }} 的值</h3>
            <el-button size="small" type="primary" v-hasPerm="['mall:attribute:edit']" @click="openValueDialog()">
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
                  v-hasPerm="['mall:attribute:edit']"
                  @click="openValueDialog(row)"
                  >编辑</el-button
                >
                <el-button
                  type="danger"
                  link
                  size="small"
                  v-hasPerm="['mall:attribute:delete']"
                  @click="deleteValue(row.id)"
                  >删除</el-button
                >
              </template>
            </el-table-column>
          </el-table>
        </div>
      </div>
    </div>

    <el-dialog v-model="attrDialog.open" :title="attrDialog.id ? '编辑属性组' : '新增属性组'" width="500px">
      <el-form :model="attrDialog" label-width="80px">
        <el-form-item label="编码"
          ><el-input v-model="attrDialog.code" placeholder="lowercase + hyphen"
        /></el-form-item>
        <el-form-item label="排序"><el-input-number v-model="attrDialog.sort" :min="0" /></el-form-item>
        <el-form-item label="状态"><el-switch v-model="attrDialog.statusOn" /></el-form-item>
        <el-divider>多语言（zh-CN 必填）</el-divider>
        <div v-for="(tr, idx) in attrDialog.translations" :key="idx" class="translation-row">
          <el-input v-model="tr.locale" placeholder="locale" style="width: 100px" />
          <el-input v-model="tr.name" placeholder="名称" style="flex: 1; margin: 0 8px" />
          <el-button
            v-if="attrDialog.translations.length > 1"
            type="danger"
            link
            @click="attrDialog.translations.splice(idx, 1)"
            >×</el-button
          >
        </div>
        <el-button size="small" @click="attrDialog.translations.push({ locale: '', name: '' })">+ 添加语言</el-button>
      </el-form>
      <template #footer>
        <el-button @click="attrDialog.open = false">取消</el-button>
        <el-button type="primary" @click="submitAttr">保存</el-button>
      </template>
    </el-dialog>

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
  createMallAttr,
  createMallAttrValue,
  deleteMallAttr,
  deleteMallAttrValue,
  getMallAttrList,
  getMallAttrValues,
  updateMallAttr,
  updateMallAttrValue,
} from '@/api/mall/attribute';
import type {
  AttributeRow,
  AttributeTranslation,
  AttributeValueRow,
  AttributeValueTranslation,
} from '@/types/api/mall/attribute';

/**
 * Mall 后台属性组管理（M10-PR39）：与 specification 结构对称（master/detail）。
 */

const attrs = ref<AttributeRow[]>([]);
const attrsLoading = ref(false);
const currentAttr = ref<AttributeRow | null>(null);
const values = ref<AttributeValueRow[]>([]);
const valuesLoading = ref(false);

function primaryName(translations?: { locale: string; name: string }[]): string {
  const tr = translations?.find((t) => t.locale === 'zh-CN') ?? translations?.[0];
  return tr?.name ?? '';
}

async function fetchAttrs() {
  attrsLoading.value = true;
  try {
    const res = await getMallAttrList({ pageSize: 100 });
    attrs.value = res.data.list;
  } catch (e) {
    ElMessage.error((e as Error).message || '加载失败');
  } finally {
    attrsLoading.value = false;
  }
}

async function selectAttr(row: AttributeRow) {
  currentAttr.value = row;
  valuesLoading.value = true;
  try {
    const res = await getMallAttrValues(row.id);
    values.value = res.data;
  } catch (e) {
    ElMessage.error((e as Error).message || '加载值失败');
  } finally {
    valuesLoading.value = false;
  }
}

const attrDialog = reactive({
  open: false,
  id: null as number | null,
  code: '',
  sort: 0,
  statusOn: true,
  translations: [] as AttributeTranslation[],
});

function openAttrDialog(row?: AttributeRow) {
  if (row) {
    attrDialog.id = row.id;
    attrDialog.code = row.code || '';
    attrDialog.sort = row.sort || 0;
    attrDialog.statusOn = row.status === 1;
    attrDialog.translations =
      row.translations?.length > 0 ? row.translations.map((t) => ({ ...t })) : [{ locale: 'zh-CN', name: '' }];
  } else {
    attrDialog.id = null;
    attrDialog.code = '';
    attrDialog.sort = 0;
    attrDialog.statusOn = true;
    attrDialog.translations = [{ locale: 'zh-CN', name: '' }];
  }
  attrDialog.open = true;
}

async function submitAttr() {
  const first = attrDialog.translations[0];
  if (!first?.name) {
    ElMessage.warning('请填写名称');
    return;
  }
  const payload = {
    code: attrDialog.code || undefined,
    sort: attrDialog.sort,
    status: attrDialog.statusOn ? 1 : 0,
    translations: attrDialog.translations.filter((t) => t.locale && t.name),
  };
  try {
    if (attrDialog.id) {
      await updateMallAttr(attrDialog.id, payload);
    } else {
      await createMallAttr(payload);
    }
    ElMessage.success('保存成功');
    attrDialog.open = false;
    await fetchAttrs();
  } catch (e) {
    ElMessage.error((e as Error).message || '保存失败');
  }
}

async function deleteAttr(id: number) {
  try {
    await ElMessageBox.confirm('确认删除该属性组（其下值会一并删除）？', '删除', {
      type: 'warning',
      confirmButtonText: '删除',
      cancelButtonText: '取消',
    });
  } catch {
    return;
  }
  try {
    await deleteMallAttr(id);
    ElMessage.success('删除成功');
    if (currentAttr.value?.id === id) {
      currentAttr.value = null;
      values.value = [];
    }
    fetchAttrs();
  } catch (e) {
    ElMessage.error((e as Error).message || '删除失败');
  }
}

const valueDialog = reactive({
  open: false,
  id: null as number | null,
  code: '',
  sort: 0,
  statusOn: true,
  translations: [] as AttributeValueTranslation[],
});

function openValueDialog(row?: AttributeValueRow) {
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
  if (!currentAttr.value) return;
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
      await updateMallAttrValue(valueDialog.id, payload);
    } else {
      await createMallAttrValue(currentAttr.value.id, payload);
    }
    ElMessage.success('保存成功');
    valueDialog.open = false;
    await selectAttr(currentAttr.value);
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
    await deleteMallAttrValue(id);
    ElMessage.success('删除成功');
    if (currentAttr.value) await selectAttr(currentAttr.value);
  } catch (e) {
    ElMessage.error((e as Error).message || '删除失败');
  }
}

onMounted(fetchAttrs);
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
