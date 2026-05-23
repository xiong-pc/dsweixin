<template>
  <div class="app-container">
    <div class="search-container">
      <el-form :model="queryParams" :inline="true">
        <el-form-item label="商品名">
          <el-input
            v-model="queryParams.keywords"
            placeholder="商品名 / SKU 前缀"
            clearable
            @keyup.enter="handleQuery"
          />
        </el-form-item>
        <el-form-item label="类目">
          <el-select v-model="queryParams.category_id" placeholder="全部" clearable style="width: 160px">
            <el-option v-for="c in categoryOptions" :key="c.id" :label="c.name" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="品牌">
          <el-select v-model="queryParams.brand_id" placeholder="全部" clearable style="width: 160px">
            <el-option v-for="b in brandOptions" :key="b.id" :label="b.name" :value="b.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="queryParams.status" placeholder="全部" clearable style="width: 120px">
            <el-option label="已上架" :value="1" />
            <el-option label="未上架" :value="0" />
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
        <el-button type="primary" v-hasPerm="['mall:product:add']" @click="goCreate">
          <el-icon><Plus /></el-icon>新增商品
        </el-button>
      </div>

      <el-table v-loading="loading" :data="tableData" border stripe>
        <el-table-column label="封面" width="80" align="center">
          <template #default="{ row }">
            <el-image
              v-if="row.cover_image"
              :src="row.cover_image"
              fit="cover"
              style="width: 50px; height: 50px; border-radius: 4px"
            />
            <span v-else class="text-gray-400 text-xs">无</span>
          </template>
        </el-table-column>
        <el-table-column label="名称" min-width="180">
          <template #default="{ row }">
            <div class="text-sm font-medium">{{ primaryName(row) }}</div>
            <div class="text-xs text-gray-500">SKU: {{ row.sku_prefix || '-' }}</div>
          </template>
        </el-table-column>
        <el-table-column label="价格" width="140" align="right">
          <template #default="{ row }">
            <span class="font-semibold">{{ row.base_currency }} {{ Number(row.base_price).toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="销量" prop="sold_count" width="80" align="center" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-switch
              v-model="row.status"
              :active-value="1"
              :inactive-value="0"
              v-hasPerm="['mall:product:edit']"
              @change="handleStatusChange(row)"
            />
          </template>
        </el-table-column>
        <el-table-column label="创建时间" prop="created_at" min-width="160" />
        <el-table-column label="操作" width="160" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link v-hasPerm="['mall:product:edit']" @click="goEdit(row.id)">编辑</el-button>
            <el-button type="danger" link v-hasPerm="['mall:product:delete']" @click="handleDelete(row.id)"
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
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Refresh, Plus } from '@element-plus/icons-vue';
import {
  getMallProductList,
  deleteMallProduct,
  updateMallProduct,
  getMallCategoryOptions,
  getMallBrandOptions,
} from '@/api/mall/product';
import type { ProductRow, ProductListQuery, PickerOption } from '@/types/api/mall/product';

/**
 * Mall 后台商品列表（M10-PR38）。
 *
 * 关键交互：
 *   - 状态切换（el-switch）直接 PUT update（status 字段）
 *   - 删除走软删除（DELETE 端点）
 *   - 编辑跳转 /mall/product/{id}，新增跳 /mall/product/create
 */
const router = useRouter();

const queryParams = reactive<ProductListQuery>({
  keywords: '',
  category_id: undefined,
  brand_id: undefined,
  status: '',
  page: 1,
  pageSize: 20,
});

const tableData = ref<ProductRow[]>([]);
const total = ref(0);
const loading = ref(false);
const categoryOptions = ref<PickerOption[]>([]);
const brandOptions = ref<PickerOption[]>([]);

async function handleQuery() {
  loading.value = true;
  try {
    const params: ProductListQuery = {
      keywords: queryParams.keywords || undefined,
      category_id: queryParams.category_id || undefined,
      brand_id: queryParams.brand_id || undefined,
      status: queryParams.status === '' ? undefined : (queryParams.status as number),
      page: queryParams.page,
      pageSize: queryParams.pageSize,
    };
    const res = await getMallProductList(params);
    tableData.value = res.data.list;
    total.value = res.data.total;
  } catch (e) {
    ElMessage.error((e as Error).message || '获取商品失败');
  } finally {
    loading.value = false;
  }
}

function handleReset() {
  queryParams.keywords = '';
  queryParams.category_id = undefined;
  queryParams.brand_id = undefined;
  queryParams.status = '';
  queryParams.page = 1;
  handleQuery();
}

function primaryName(row: ProductRow): string {
  const tr = row.translations?.find((t) => t.locale === 'zh-CN') ?? row.translations?.[0];
  return tr?.name ?? '-';
}

async function handleStatusChange(row: ProductRow) {
  try {
    await updateMallProduct(row.id, { status: row.status });
    ElMessage.success(row.status === 1 ? '已上架' : '已下架');
  } catch (e) {
    row.status = row.status === 1 ? 0 : 1; // 回滚 UI
    ElMessage.error((e as Error).message || '状态更新失败');
  }
}

async function handleDelete(id: number) {
  try {
    await ElMessageBox.confirm('确认删除该商品？', '删除商品', {
      confirmButtonText: '删除',
      cancelButtonText: '取消',
      type: 'warning',
    });
  } catch {
    return;
  }
  try {
    await deleteMallProduct(id);
    ElMessage.success('删除成功');
    handleQuery();
  } catch (e) {
    ElMessage.error((e as Error).message || '删除失败');
  }
}

function goCreate() {
  router.push('/mall/product/create');
}

function goEdit(id: number) {
  router.push(`/mall/product/${id}`);
}

async function loadPickers() {
  try {
    const [cats, brands] = await Promise.all([getMallCategoryOptions(), getMallBrandOptions()]);
    // 类目 / 品牌 后端可能返回各自的 name；按 first translation 兜底
    categoryOptions.value = (cats.data.list || []).map((c: any) => ({
      id: c.id,
      name: c.translations?.[0]?.name || c.code || `#${c.id}`,
    }));
    brandOptions.value = (brands.data.list || []).map((b: any) => ({
      id: b.id,
      name: b.translations?.[0]?.name || b.code || `#${b.id}`,
    }));
  } catch {
    // picker 缺失不阻塞列表
  }
}

onMounted(() => {
  loadPickers();
  handleQuery();
});
</script>
