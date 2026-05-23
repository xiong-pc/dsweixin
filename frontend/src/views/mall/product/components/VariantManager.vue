<template>
  <div>
    <div style="margin-bottom: 12px; display: flex; justify-content: space-between">
      <h3 style="margin: 0">变体（SKU）</h3>
      <el-button size="small" type="primary" v-hasPerm="['mall:product:edit']" @click="openCreateDialog()">
        <el-icon><Plus /></el-icon>新增变体
      </el-button>
    </div>

    <el-table v-loading="loading" :data="variants" border>
      <el-table-column label="SKU" prop="sku" min-width="160" />
      <el-table-column label="规格" min-width="200">
        <template #default="{ row }">
          <el-tag v-for="sv in row.specification_values || []" :key="sv.id" size="small" style="margin-right: 4px">{{
            specName(sv)
          }}</el-tag>
          <span v-if="!row.specification_values || row.specification_values.length === 0" class="text-gray-400 text-xs">
            无
          </span>
        </template>
      </el-table-column>
      <el-table-column label="价格" prop="price" width="120" align="right">
        <template #default="{ row }">{{ Number(row.price).toFixed(2) }}</template>
      </el-table-column>
      <el-table-column label="库存" prop="stock" width="80" align="center" />
      <el-table-column label="状态" width="80" align="center">
        <template #default="{ row }">
          <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
            {{ row.status === 1 ? '启用' : '禁用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="140" align="center" fixed="right">
        <template #default="{ row }">
          <el-button type="primary" link size="small" v-hasPerm="['mall:product:edit']" @click="openEditDialog(row)"
            >编辑</el-button
          >
          <el-button type="danger" link size="small" v-hasPerm="['mall:product:edit']" @click="handleDelete(row.id)"
            >删除</el-button
          >
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="dialog.open" :title="dialog.id ? '编辑变体' : '新增变体'" width="600px">
      <el-form :model="dialog" label-width="100px">
        <el-form-item label="SKU" required>
          <el-input v-model="dialog.sku" placeholder="如 ITEM-001-RED-M" />
        </el-form-item>
        <el-form-item label="价格" required>
          <el-input-number v-model="dialog.price" :min="0" :precision="2" />
        </el-form-item>
        <el-form-item label="划线价">
          <el-input-number v-model="dialog.compare_at_price" :min="0" :precision="2" />
        </el-form-item>
        <el-form-item label="库存" required>
          <el-input-number v-model="dialog.stock" :min="0" :precision="0" />
        </el-form-item>
        <el-form-item label="重量">
          <el-input-number v-model="dialog.weight" :min="0" :precision="3" />
          <el-select v-model="dialog.weight_unit" style="width: 80px; margin-left: 8px">
            <el-option label="g" value="g" />
            <el-option label="kg" value="kg" />
            <el-option label="oz" value="oz" />
            <el-option label="lb" value="lb" />
          </el-select>
        </el-form-item>
        <el-form-item label="变体图">
          <el-input v-model="dialog.image" placeholder="https://..." />
        </el-form-item>
        <el-form-item label="规格值">
          <el-cascader
            v-model="dialog.specification_value_ids"
            :options="specOptions"
            :props="{ multiple: true, expandTrigger: 'hover' }"
            collapse-tags
            collapse-tags-tooltip
            placeholder="选择规格组下的值"
            style="width: 100%"
          />
          <div class="text-xs text-gray-500" style="margin-top: 4px">可多选；同一规格组只允许选一个值</div>
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="dialog.statusOn" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialog.open = false">取消</el-button>
        <el-button type="primary" :loading="submitting" @click="submit">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import { createVariant, deleteVariant, listVariants, updateVariant } from '@/api/mall/variant';
import { getMallSpecList, getMallSpecValues } from '@/api/mall/specification';
import type { VariantRow, VariantSpecValue } from '@/types/api/mall/variant';
import type { SpecificationRow, SpecificationValueRow } from '@/types/api/mall/specification';

/**
 * 变体（SKU）管理（M03-PR13）。
 *
 * - 嵌入到 views/mall/product/edit.vue 的"变体"区块（仅编辑态可见）
 * - 列表展示已有变体；点 + 新增 / 行内编辑、删除
 * - 规格值用 el-cascader 多选（每组只选一个值）
 *
 * 笛卡尔积矩阵生成留作后续优化（PR14 spec 已经标记 demoted），
 * 当前 P0 用单条增删足够覆盖小规模 SPU 管理。
 */
const props = defineProps<{
  productId: number;
}>();

const variants = ref<VariantRow[]>([]);
const loading = ref(false);
const submitting = ref(false);

interface SpecOption {
  value: number;
  label: string;
  children?: { value: number; label: string }[];
}
const specOptions = ref<SpecOption[]>([]);

function specName(sv: VariantSpecValue): string {
  const tr = sv.translations?.find((t) => t.locale === 'zh-CN') ?? sv.translations?.[0];
  return tr?.name ?? sv.code ?? `#${sv.id}`;
}

function primaryName(translations?: { locale: string; name: string }[]): string {
  const tr = translations?.find((t) => t.locale === 'zh-CN') ?? translations?.[0];
  return tr?.name ?? '';
}

async function fetchVariants() {
  loading.value = true;
  try {
    const res = await listVariants(props.productId);
    variants.value = res.data.list;
  } catch (e) {
    ElMessage.error((e as Error).message || '加载变体失败');
  } finally {
    loading.value = false;
  }
}

async function loadSpecOptions() {
  try {
    const res = await getMallSpecList({ pageSize: 100 });
    const specs: SpecificationRow[] = res.data.list;
    const all: SpecOption[] = [];
    for (const s of specs) {
      const sv = await getMallSpecValues(s.id);
      all.push({
        value: s.id,
        label: primaryName(s.translations) || s.code || `#${s.id}`,
        children: (sv.data as SpecificationValueRow[]).map((v) => ({
          value: v.id,
          label: primaryName(v.translations) || v.code || `#${v.id}`,
        })),
      });
    }
    specOptions.value = all;
  } catch {
    /* ignore */
  }
}

// ===== Dialog =====
const dialog = reactive({
  open: false,
  id: null as number | null,
  sku: '',
  price: 0,
  compare_at_price: undefined as number | undefined,
  stock: 0,
  weight: undefined as number | undefined,
  weight_unit: 'g' as 'g' | 'kg' | 'oz' | 'lb',
  image: '',
  specification_value_ids: [] as number[][],
  statusOn: true,
});

function flattenCascader(values: number[][]): number[] {
  return values.map((arr) => arr[arr.length - 1]).filter((v): v is number => typeof v === 'number');
}

function specValuesToCascaderPath(svs: VariantSpecValue[]): number[][] {
  return svs.filter((sv) => sv.specification_id != null).map((sv) => [sv.specification_id as number, sv.id]);
}

function openCreateDialog() {
  dialog.id = null;
  dialog.sku = '';
  dialog.price = 0;
  dialog.compare_at_price = undefined;
  dialog.stock = 0;
  dialog.weight = undefined;
  dialog.weight_unit = 'g';
  dialog.image = '';
  dialog.specification_value_ids = [];
  dialog.statusOn = true;
  dialog.open = true;
}

function openEditDialog(row: VariantRow) {
  dialog.id = row.id;
  dialog.sku = row.sku;
  dialog.price = Number(row.price) || 0;
  dialog.compare_at_price = row.compare_at_price ? Number(row.compare_at_price) : undefined;
  dialog.stock = row.stock || 0;
  dialog.weight = row.weight ? Number(row.weight) : undefined;
  dialog.weight_unit = (row.weight_unit as 'g') || 'g';
  dialog.image = row.image || '';
  dialog.specification_value_ids = specValuesToCascaderPath(row.specification_values || []);
  dialog.statusOn = row.status === 1;
  dialog.open = true;
}

async function submit() {
  if (!dialog.sku) {
    ElMessage.warning('请填写 SKU');
    return;
  }
  submitting.value = true;
  try {
    const payload = {
      sku: dialog.sku,
      price: dialog.price,
      compare_at_price: dialog.compare_at_price,
      stock: dialog.stock,
      weight: dialog.weight,
      weight_unit: dialog.weight_unit,
      image: dialog.image || undefined,
      status: dialog.statusOn ? 1 : 0,
      specification_value_ids: flattenCascader(dialog.specification_value_ids),
    };
    if (dialog.id) {
      await updateVariant(dialog.id, payload);
    } else {
      await createVariant(props.productId, payload);
    }
    ElMessage.success('保存成功');
    dialog.open = false;
    await fetchVariants();
  } catch (e) {
    ElMessage.error((e as Error).message || '保存失败');
  } finally {
    submitting.value = false;
  }
}

async function handleDelete(id: number) {
  try {
    await ElMessageBox.confirm('确认删除该变体？', '删除变体', {
      confirmButtonText: '删除',
      cancelButtonText: '取消',
      type: 'warning',
    });
  } catch {
    return;
  }
  try {
    await deleteVariant(id);
    ElMessage.success('删除成功');
    fetchVariants();
  } catch (e) {
    ElMessage.error((e as Error).message || '删除失败');
  }
}

onMounted(async () => {
  await loadSpecOptions();
  await fetchVariants();
});
</script>
