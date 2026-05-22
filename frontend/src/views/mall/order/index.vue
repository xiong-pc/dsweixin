<template>
  <div class="app-container">
    <div class="search-container">
      <el-form :model="queryParams" :inline="true">
        <el-form-item label="订单号">
          <el-input
            v-model="queryParams.keywords"
            placeholder="订单号 / 客户邮箱"
            clearable
            @keyup.enter="handleQuery"
          />
        </el-form-item>
        <el-form-item label="状态">
          <el-select v-model="queryParams.status" placeholder="全部" clearable style="width: 140px">
            <el-option label="待支付" value="pending" />
            <el-option label="已支付" value="paid" />
            <el-option label="已发货" value="shipped" />
            <el-option label="已签收" value="delivered" />
            <el-option label="已取消" value="cancelled" />
            <el-option label="已退款" value="refunded" />
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
        <el-table-column label="订单号" prop="order_no" min-width="180">
          <template #default="{ row }">
            <span class="font-mono">{{ row.order_no }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" prop="status" width="110" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTagType(row.status)">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="客户" prop="customer_id" width="100" align="center">
          <template #default="{ row }">
            {{ row.customer_id ? `#${row.customer_id}` : '游客' }}
          </template>
        </el-table-column>
        <el-table-column label="金额" prop="total" min-width="120" align="right">
          <template #default="{ row }">
            <span class="font-semibold">{{ row.currency }} {{ Number(row.total).toFixed(2) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="支付方式" prop="pay_method" width="100" align="center">
          <template #default="{ row }">
            {{ row.pay_method || '-' }}
          </template>
        </el-table-column>
        <el-table-column label="下单时间" prop="created_at" min-width="160" />
        <el-table-column label="操作" width="120" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" link @click="goDetail(row.id)">详情</el-button>
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
import { ElMessage } from 'element-plus';
import { Search, Refresh } from '@element-plus/icons-vue';
import { getMallOrderList } from '@/api/mall/order';
import type { OrderRow, OrderStatus, OrderListQuery } from '@/types/api/mall/order';

/**
 * Mall 后台订单列表（M08-PR32）。
 *
 * 后端端点：GET /api/v1/mall/orders（受 tenant 中间件保护，仅展示当前管理员所属租户）。
 */
const router = useRouter();

const queryParams = reactive<OrderListQuery>({
  keywords: '',
  status: '',
  page: 1,
  pageSize: 20,
});

const tableData = ref<OrderRow[]>([]);
const total = ref(0);
const loading = ref(false);

async function handleQuery() {
  loading.value = true;
  try {
    const params: OrderListQuery = {
      keywords: queryParams.keywords || undefined,
      status: queryParams.status || undefined,
      page: queryParams.page,
      pageSize: queryParams.pageSize,
    };
    const res = await getMallOrderList(params);
    tableData.value = res.data.list;
    total.value = res.data.total;
  } catch (e) {
    ElMessage.error((e as Error).message || '获取订单失败');
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

function goDetail(id: number) {
  router.push(`/mall/order/${id}`);
}

function statusLabel(status: OrderStatus): string {
  const map: Record<string, string> = {
    pending: '待支付',
    paid: '已支付',
    shipped: '已发货',
    delivered: '已签收',
    cancelled: '已取消',
    refunded: '已退款',
  };
  return map[String(status)] ?? String(status);
}

function statusTagType(status: OrderStatus): 'success' | 'warning' | 'info' | 'danger' {
  switch (status) {
    case 'paid':
    case 'shipped':
    case 'delivered':
      return 'success';
    case 'pending':
      return 'warning';
    case 'cancelled':
    case 'refunded':
      return 'danger';
    default:
      return 'info';
  }
}

onMounted(handleQuery);
</script>
