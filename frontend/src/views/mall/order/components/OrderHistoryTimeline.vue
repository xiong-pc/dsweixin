<template>
  <div v-if="histories.length > 0">
    <el-timeline>
      <el-timeline-item
        v-for="h in histories"
        :key="h.id"
        :timestamp="h.created_at || ''"
        :type="typeFor(h.to_status)"
        placement="top"
      >
        <div class="history-entry">
          <span class="font-medium"> {{ labelOf(h.from_status) || '—' }} → {{ labelOf(h.to_status) || '—' }} </span>
          <span v-if="h.operator_type" class="text-xs text-gray-500" style="margin-left: 8px">
            [{{ formatOperator(h) }}]
          </span>
        </div>
        <p v-if="h.reason" class="text-xs text-gray-600" style="margin-top: 4px">原因：{{ h.reason }}</p>
        <p v-if="h.note" class="text-xs text-gray-500">备注：{{ h.note }}</p>
      </el-timeline-item>
    </el-timeline>
  </div>
  <p v-else class="text-xs text-gray-400">暂无历史记录</p>
</template>

<script setup lang="ts">
import type { OrderHistoryRow } from '@/types/api/mall/order';

/**
 * 订单历史轨迹（M10-PR40）：渲染 order_histories 全量日志。
 *
 * 与 StatusTimeline（PR32 推断式五节点链）互补：
 *   - StatusTimeline 展示宏观生命周期 / 期望节点
 *   - OrderHistoryTimeline 展示每一次实际状态变更（含 operator + reason）
 */
defineProps<{
  histories: OrderHistoryRow[];
}>();

const labelMap: Record<string, string> = {
  pending: '待支付',
  paid: '已支付',
  shipped: '已发货',
  delivered: '已签收',
  cancelled: '已取消',
  refunded: '已退款',
  partially_refunded: '部分退款',
};

function labelOf(status: string | null | undefined): string {
  if (!status) return '';
  return labelMap[status] ?? status;
}

function typeFor(toStatus: string | null): 'primary' | 'success' | 'warning' | 'danger' | 'info' {
  switch (toStatus) {
    case 'paid':
    case 'shipped':
    case 'delivered':
      return 'success';
    case 'pending':
      return 'warning';
    case 'cancelled':
    case 'refunded':
    case 'partially_refunded':
      return 'danger';
    default:
      return 'info';
  }
}

function formatOperator(h: OrderHistoryRow): string {
  const type = h.operator_type || 'system';
  const id = h.operator_id ? `#${h.operator_id}` : '';
  return `${type}${id}`.trim();
}
</script>

<style scoped>
.history-entry {
  font-size: 14px;
}
</style>
