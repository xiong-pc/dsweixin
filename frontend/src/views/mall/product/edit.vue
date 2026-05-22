<template>
  <div class="app-container">
    <el-page-header @back="goBack">
      <template #content>
        {{ isCreate ? '新增商品' : `编辑商品 #${productId}` }}
      </template>
    </el-page-header>

    <el-skeleton v-if="loadingDetail" :rows="6" animated style="margin-top: 16px" />

    <el-form v-else ref="formRef" :model="form" :rules="rules" label-width="120px" style="margin-top: 16px">
      <el-card class="card-section">
        <template #header>基本信息</template>

        <el-form-item label="类目" prop="category_id">
          <el-select v-model="form.category_id" placeholder="请选择类目" clearable style="width: 100%">
            <el-option v-for="c in categoryOptions" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>

        <el-form-item label="品牌" prop="brand_id">
          <el-select v-model="form.brand_id" placeholder="请选择品牌" clearable style="width: 100%">
            <el-option v-for="b in brandOptions" :key="b.id" :label="b.name" :value="b.id" />
          </el-select>
        </el-form-item>

        <el-form-item label="封面图 URL" prop="cover_image">
          <el-input v-model="form.cover_image" placeholder="https://..." clearable />
        </el-form-item>

        <el-form-item label="货币" prop="base_currency">
          <el-select v-model="form.base_currency" style="width: 120px">
            <el-option label="CNY" value="CNY" />
            <el-option label="USD" value="USD" />
            <el-option label="EUR" value="EUR" />
            <el-option label="JPY" value="JPY" />
            <el-option label="KRW" value="KRW" />
          </el-select>
        </el-form-item>

        <el-form-item label="基础售价" prop="base_price" v-if="!isCreate">
          <el-input-number v-model="form.base_price" :min="0" :precision="2" />
          <span class="text-xs text-gray-500" style="margin-left: 8px">
            （创建时使用首个 SKU 的价格作为基础售价）
          </span>
        </el-form-item>

        <el-form-item label="状态">
          <el-switch v-model="statusSwitch" />
          <span class="text-xs text-gray-500" style="margin-left: 8px"> 打开为上架 / 关闭为下架 </span>
        </el-form-item>
      </el-card>

      <el-card class="card-section">
        <template #header>多语言（zh-CN 必填）</template>

        <div v-for="(tr, idx) in form.translations" :key="idx" class="translation-block">
          <div class="translation-header">
            <span>语言代码</span>
            <el-input v-model="tr.locale" style="width: 100px" />
            <el-button
              v-if="form.translations.length > 1"
              type="danger"
              link
              size="small"
              @click="removeTranslation(idx)"
              >删除</el-button
            >
          </div>
          <el-form-item label="名称">
            <el-input v-model="tr.name" placeholder="商品名称" />
          </el-form-item>
          <el-form-item label="Slug（可选）">
            <el-input v-model="tr.slug" placeholder="lowercase + hyphen，留空自动生成" />
          </el-form-item>
          <el-form-item label="简介">
            <el-input v-model="tr.short_description" maxlength="500" />
          </el-form-item>
          <el-form-item label="详情">
            <el-input v-model="tr.description" type="textarea" :rows="4" />
          </el-form-item>
        </div>
        <el-button @click="addTranslation">+ 添加语言版本</el-button>
      </el-card>

      <el-card v-if="isCreate" class="card-section">
        <template #header>首个 SKU（创建必填）</template>

        <el-form-item label="SKU" prop="sku">
          <el-input v-model="form.sku" placeholder="如 ITEM-001" />
        </el-form-item>
        <el-form-item label="售价" prop="price">
          <el-input-number v-model="form.price" :min="0" :precision="2" />
        </el-form-item>
        <el-form-item label="划线价">
          <el-input-number v-model="form.compare_at_price" :min="0" :precision="2" />
        </el-form-item>
        <el-form-item label="库存" prop="stock">
          <el-input-number v-model="form.stock" :min="0" :precision="0" />
        </el-form-item>
        <el-form-item label="重量">
          <el-input-number v-model="form.weight" :min="0" :precision="3" />
          <el-select v-model="form.weight_unit" style="width: 80px; margin-left: 8px">
            <el-option label="g" value="g" />
            <el-option label="kg" value="kg" />
            <el-option label="oz" value="oz" />
            <el-option label="lb" value="lb" />
          </el-select>
        </el-form-item>
      </el-card>

      <el-card v-if="!isCreate" class="card-section">
        <template #header>变体管理（M03-PR13）</template>
        <VariantManager :product-id="productId" />
      </el-card>

      <div style="margin-top: 16px">
        <el-button type="primary" :loading="submitting" @click="submit">
          {{ isCreate ? '创建商品' : '保存修改' }}
        </el-button>
        <el-button @click="goBack">取消</el-button>
      </div>
    </el-form>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, type FormInstance, type FormRules } from 'element-plus';
import {
  getMallProductDetail,
  quickCreateMallProduct,
  updateMallProduct,
  getMallCategoryOptions,
  getMallBrandOptions,
} from '@/api/mall/product';
import type { PickerOption, ProductTranslation, QuickCreateProductPayload } from '@/types/api/mall/product';
import VariantManager from './components/VariantManager.vue';

/**
 * Mall 后台商品创建 / 编辑（M10-PR38）。
 *
 * - URL `/mall/product/create` → isCreate=true，调 quickCreate（含首 SKU）
 * - URL `/mall/product/{id}` → isCreate=false，调 PUT update（不改 SKU；变体由后续 PR 接入）
 * - 多语言行内编辑，至少一条翻译（zh-CN）
 */
const route = useRoute();
const router = useRouter();
const formRef = ref<FormInstance>();

const productId = computed(() => {
  const raw = route.params.id;
  return raw ? Number(raw) : 0;
});
const isCreate = computed(() => !productId.value);

const loadingDetail = ref(false);
const submitting = ref(false);
const categoryOptions = ref<PickerOption[]>([]);
const brandOptions = ref<PickerOption[]>([]);

interface FormState {
  category_id: number | null;
  brand_id: number | null;
  cover_image: string;
  base_currency: string;
  base_price: number;
  translations: ProductTranslation[];
  // SKU 字段，仅创建态使用
  sku: string;
  price: number;
  compare_at_price?: number;
  stock: number;
  weight?: number;
  weight_unit: 'g' | 'kg' | 'oz' | 'lb';
}

const form = reactive<FormState>({
  category_id: null,
  brand_id: null,
  cover_image: '',
  base_currency: 'CNY',
  base_price: 0,
  translations: [{ locale: 'zh-CN', name: '', slug: '', short_description: '', description: '' }],
  sku: '',
  price: 0,
  compare_at_price: undefined,
  stock: 0,
  weight: undefined,
  weight_unit: 'g',
});

const statusSwitch = ref(true);

const rules: FormRules = {
  sku: [{ required: true, message: '请输入 SKU', trigger: 'blur' }],
  price: [{ required: true, type: 'number', message: '请输入售价', trigger: 'blur' }],
  stock: [{ required: true, type: 'number', message: '请输入库存', trigger: 'blur' }],
};

function addTranslation() {
  form.translations.push({
    locale: '',
    name: '',
    slug: '',
    short_description: '',
    description: '',
  });
}

function removeTranslation(idx: number) {
  form.translations.splice(idx, 1);
}

async function loadPickers() {
  try {
    const [cats, brands] = await Promise.all([getMallCategoryOptions(), getMallBrandOptions()]);
    categoryOptions.value = (cats.data.list || []).map((c: any) => ({
      id: c.id,
      name: c.translations?.[0]?.name || c.code || `#${c.id}`,
    }));
    brandOptions.value = (brands.data.list || []).map((b: any) => ({
      id: b.id,
      name: b.translations?.[0]?.name || b.code || `#${b.id}`,
    }));
  } catch {
    /* ignore */
  }
}

async function loadDetail() {
  if (isCreate.value) return;
  loadingDetail.value = true;
  try {
    const { data } = await getMallProductDetail(productId.value);
    form.category_id = data.category_id ?? null;
    form.brand_id = data.brand_id ?? null;
    form.cover_image = data.cover_image ?? '';
    form.base_currency = data.base_currency || 'CNY';
    form.base_price = Number(data.base_price) || 0;
    statusSwitch.value = data.status === 1;
    form.translations =
      data.translations && data.translations.length > 0
        ? data.translations.map((t) => ({
            locale: t.locale,
            name: t.name,
            slug: t.slug || '',
            short_description: t.short_description || '',
            description: t.description || '',
            seo_title: t.seo_title,
            seo_keywords: t.seo_keywords,
            seo_description: t.seo_description,
          }))
        : [{ locale: 'zh-CN', name: '', slug: '', short_description: '', description: '' }];
  } catch (e) {
    ElMessage.error((e as Error).message || '加载商品失败');
  } finally {
    loadingDetail.value = false;
  }
}

async function submit() {
  const first = form.translations[0];
  if (!first || !first.name) {
    ElMessage.warning('至少填写一条翻译，且首条 name 不能为空');
    return;
  }
  submitting.value = true;
  try {
    if (isCreate.value) {
      const payload: QuickCreateProductPayload = {
        category_id: form.category_id ?? undefined,
        brand_id: form.brand_id ?? undefined,
        cover_image: form.cover_image || undefined,
        base_currency: form.base_currency,
        status: statusSwitch.value ? 1 : 0,
        translations: form.translations.filter((t) => t.locale && t.name),
        sku: form.sku,
        price: form.price,
        compare_at_price: form.compare_at_price,
        stock: form.stock,
        weight: form.weight,
        weight_unit: form.weight_unit,
      };
      const { data } = await quickCreateMallProduct(payload);
      ElMessage.success('商品创建成功');
      router.replace(`/mall/product/${data.id}`);
    } else {
      await updateMallProduct(productId.value, {
        category_id: form.category_id ?? undefined,
        brand_id: form.brand_id ?? undefined,
        cover_image: form.cover_image,
        base_currency: form.base_currency,
        base_price: form.base_price,
        status: statusSwitch.value ? 1 : 0,
        translations: form.translations.filter((t) => t.locale && t.name),
      });
      ElMessage.success('保存成功');
      router.push('/mall/product');
    }
  } catch (e) {
    ElMessage.error((e as Error).message || '提交失败');
  } finally {
    submitting.value = false;
  }
}

function goBack() {
  router.push('/mall/product');
}

onMounted(async () => {
  await loadPickers();
  await loadDetail();
});
</script>

<style scoped>
.card-section {
  margin-bottom: 16px;
}

.translation-block {
  margin-bottom: 16px;
  padding-bottom: 12px;
  border-bottom: 1px dashed var(--el-border-color-light);
}

.translation-block:last-of-type {
  border-bottom: none;
}

.translation-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  font-size: 13px;
  color: var(--el-text-color-secondary);
}
</style>
