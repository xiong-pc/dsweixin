<template>
  <div class="app-container">
    <el-page-header @back="goBack">
      <template #content>
        <span class="font-mono">{{ order?.order_no || '订单详情' }}</span>
        <el-tag v-if="order" :type="statusTagType(order.status)" size="small" class="ml-3">
          {{ statusLabel(order.status) }}
        </el-tag>
      </template>
    </el-page-header>

    <el-skeleton v-if="loading" :rows="6" animated style="margin-top: 16px" />

    <div v-else-if="order" class="grid-layout">
      <!-- 左：商品 + 地址 -->
      <div class="card-container">
        <h3 class="section-title">商品明细</h3>
        <OrderItemsTable :items="order.items || []" :currency="order.currency" />

        <div class="summary">
          <div class="summary-row">
            <span>小计</span>
            <span>{{ order.currency }} {{ Number(order.subtotal).toFixed(2) }}</span>
          </div>
          <div class="summary-row total">
            <span>合计</span>
            <span>{{ order.currency }} {{ Number(order.total).toFixed(2) }}</span>
          </div>
        </div>

        <h3 class="section-title">收货地址</h3>
        <div v-if="order.shipping_address" class="address-block">
          <p>
            <strong>{{ order.shipping_address.contact_name }}</strong>
            ·
            {{ order.shipping_address.contact_phone }}
          </p>
          <p>
            {{ order.shipping_address.country_code }} {{ order.shipping_address.province }}
            {{ order.shipping_address.city }} {{ order.shipping_address.district }}
          </p>
          <p>{{ order.shipping_address.street }} {{ order.shipping_address.postal_code }}</p>
          <p v-if="order.shipping_address.contact_email" class="text-sm text-gray-500">
            {{ order.shipping_address.contact_email }}
          </p>
        </div>
        <p v-else class="text-gray-500">无</p>
      </div>

      <!-- 右：状态时间线 + 操作面板 -->
      <div class="card-container">
        <h3 class="section-title">订单进度</h3>
        <StatusTimeline :order="order" />

        <h3 class="section-title">操作</h3>
        <div class="action-buttons">
          <el-button
            type="primary"
            v-hasPerm="['mall:order:ship']"
            :disabled="!canShip"
            @click="shipDialog.open = true"
          >
            发货
          </el-button>
          <el-button type="warning" v-hasPerm="['mall:order:refund']" :disabled="!canRefund" @click="openRefund">
            退款
          </el-button>
          <el-button
            type="danger"
            v-hasPerm="['mall:order:cancel']"
            :disabled="!canCancel"
            @click="cancelDialog.open = true"
          >
            取消订单
          </el-button>
        </div>

        <h3 v-if="order.shipments && order.shipments.length" class="section-title">物流</h3>
        <ul v-if="order.shipments && order.shipments.length" class="shipment-list">
          <li v-for="ship in order.shipments" :key="ship.id">
            {{ ship.carrier || '-' }} · {{ ship.tracking_no || '-' }}
            <el-tag size="small" type="info">{{ ship.status }}</el-tag>
          </li>
        </ul>
      </div>
    </div>

    <!-- Ship Dialog -->
    <el-dialog v-model="shipDialog.open" title="发货" width="500px">
      <el-form :model="shipDialog.form" label-width="100px">
        <el-form-item label="承运商" required>
          <el-input v-model="shipDialog.form.carrier" placeholder="如 顺丰" />
        </el-form-item>
        <el-form-item label="运单号" required>
          <el-input v-model="shipDialog.form.tracking_no" placeholder="物流单号" />
        </el-form-item>
        <el-form-item label="运费">
          <el-input-number v-model="shipDialog.form.fee" :min="0" :precision="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="shipDialog.open = false">取消</el-button>
        <el-button type="primary" :loading="shipDialog.submitting" @click="submitShip"> 确认发货 </el-button>
      </template>
    </el-dialog>

    <!-- Refund Dialog -->
    <el-dialog v-model="refundDialog.open" title="退款" width="500px">
      <el-alert
        v-if="order"
        type="info"
        :closable="false"
        :title="`订单金额：${order.currency} ${Number(order.total).toFixed(2)}`"
        style="margin-bottom: 16px"
      />
      <el-form :model="refundDialog.form" label-width="100px">
        <el-form-item label="退款金额">
          <el-input-number
            v-model="refundDialog.form.amount"
            :min="0.01"
            :max="Number(order?.total || 0)"
            :precision="2"
          />
          <span class="ml-2 text-xs text-gray-500">留空则全额退款</span>
        </el-form-item>
        <el-form-item label="退款原因">
          <el-input v-model="refundDialog.form.reason" type="textarea" :rows="3" placeholder="选填" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="refundDialog.open = false">取消</el-button>
        <el-button type="warning" :loading="refundDialog.submitting" @click="submitRefund"> 确认退款 </el-button>
      </template>
    </el-dialog>

    <!-- Cancel Dialog -->
    <el-dialog v-model="cancelDialog.open" title="取消订单" width="500px">
      <p>确定要取消该订单吗？仅 待支付 / 已支付 状态可取消，已支付订单将自动触发退款流程。</p>
      <el-form :model="cancelDialog.form" label-width="100px" style="margin-top: 16px">
        <el-form-item label="取消原因">
          <el-input v-model="cancelDialog.form.reason" type="textarea" :rows="3" placeholder="选填" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="cancelDialog.open = false">放弃</el-button>
        <el-button type="danger" :loading="cancelDialog.submitting" @click="submitCancel"> 确认取消 </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { getMallOrderDetail, shipMallOrder, refundMallOrder, cancelMallOrder } from '@/api/mall/order';
import type { OrderRow, OrderStatus } from '@/types/api/mall/order';
import StatusTimeline from './components/StatusTimeline.vue';
import OrderItemsTable from './components/OrderItemsTable.vue';

/**
 * Mall 后台订单详情（M08-PR32）。
 *
 * 后端端点：
 *   - GET    /api/v1/mall/orders/{id}
 *   - POST   /api/v1/mall/orders/{id}/ship
 *   - POST   /api/v1/mall/orders/{id}/refund
 *   - POST   /api/v1/mall/orders/{id}/cancel
 */
const route = useRoute();
const router = useRouter();

const order = ref<OrderRow | null>(null);
const loading = ref(false);

const shipDialog = reactive({
  open: false,
  submitting: false,
  form: { carrier: '', tracking_no: '', fee: 0 },
});

const refundDialog = reactive({
  open: false,
  submitting: false,
  form: { amount: undefined as number | undefined, reason: '' },
});

const cancelDialog = reactive({
  open: false,
  submitting: false,
  form: { reason: '' },
});

const canShip = computed(() => order.value?.status === 'paid');
const canRefund = computed(() => ['paid', 'shipped', 'delivered'].includes(String(order.value?.status)));
const canCancel = computed(() => ['pending', 'paid'].includes(String(order.value?.status)));

async function loadOrder() {
  loading.value = true;
  try {
    const id = Number(route.params.id);
    const res = await getMallOrderDetail(id);
    order.value = res.data;
  } catch (e) {
    ElMessage.error((e as Error).message || '加载订单失败');
  } finally {
    loading.value = false;
  }
}

function goBack() {
  router.push('/mall/order');
}

function openRefund() {
  refundDialog.form.amount = Number(order.value?.total || 0);
  refundDialog.form.reason = '';
  refundDialog.open = true;
}

async function submitShip() {
  if (!order.value) return;
  if (!shipDialog.form.carrier || !shipDialog.form.tracking_no) {
    ElMessage.warning('请填写承运商和运单号');
    return;
  }
  shipDialog.submitting = true;
  try {
    await shipMallOrder(order.value.id, {
      carrier: shipDialog.form.carrier,
      tracking_no: shipDialog.form.tracking_no,
      fee: shipDialog.form.fee,
    });
    ElMessage.success('发货成功');
    shipDialog.open = false;
    await loadOrder();
  } catch (e) {
    ElMessage.error((e as Error).message || '发货失败');
  } finally {
    shipDialog.submitting = false;
  }
}

async function submitRefund() {
  if (!order.value) return;
  refundDialog.submitting = true;
  try {
    await refundMallOrder(order.value.id, {
      amount: refundDialog.form.amount,
      reason: refundDialog.form.reason || undefined,
    });
    ElMessage.success('退款已发起');
    refundDialog.open = false;
    await loadOrder();
  } catch (e) {
    ElMessage.error((e as Error).message || '退款失败');
  } finally {
    refundDialog.submitting = false;
  }
}

async function submitCancel() {
  if (!order.value) return;
  try {
    await ElMessageBox.confirm('该操作不可撤销，确定要取消订单吗？', '取消订单', {
      confirmButtonText: '确定取消',
      cancelButtonText: '放弃',
      type: 'warning',
    });
  } catch {
    return;
  }
  cancelDialog.submitting = true;
  try {
    await cancelMallOrder(order.value.id, { reason: cancelDialog.form.reason || undefined });
    ElMessage.success('订单已取消');
    cancelDialog.open = false;
    await loadOrder();
  } catch (e) {
    ElMessage.error((e as Error).message || '取消失败');
  } finally {
    cancelDialog.submitting = false;
  }
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

onMounted(loadOrder);
</script>

<style scoped>
.grid-layout {
  display: grid;
  grid-template-columns: 1fr 380px;
  gap: 16px;
  margin-top: 16px;
}

@media (max-width: 1024px) {
  .grid-layout {
    grid-template-columns: 1fr;
  }
}

.section-title {
  margin: 16px 0 12px;
  font-size: 14px;
  font-weight: 600;
  color: var(--el-text-color-primary);
}

.section-title:first-of-type {
  margin-top: 0;
}

.summary {
  margin-top: 12px;
  border-top: 1px solid var(--el-border-color-light);
  padding-top: 12px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  padding: 4px 0;
  font-size: 14px;
}

.summary-row.total {
  margin-top: 8px;
  border-top: 1px dashed var(--el-border-color-light);
  padding-top: 8px;
  font-weight: 600;
  font-size: 16px;
}

.address-block p {
  margin: 4px 0;
  font-size: 14px;
}

.action-buttons {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.shipment-list {
  list-style: none;
  padding: 0;
  margin: 0;
}

.shipment-list li {
  padding: 6px 0;
  font-size: 13px;
  border-bottom: 1px dashed var(--el-border-color-light);
}

.shipment-list li:last-child {
  border-bottom: none;
}
</style>
