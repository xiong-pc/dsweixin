<template>
  <el-timeline>
    <el-timeline-item
      v-for="(node, idx) in nodes"
      :key="node.key"
      :type="node.completed ? 'primary' : 'info'"
      :hollow="!node.completed"
      :timestamp="node.timestamp || ''"
    >
      <span :class="{ 'text-gray-400': !node.completed }">{{ node.label }}</span>
      <span v-if="idx === currentIndex" class="ml-2 text-xs text-primary">（{{ currentBadge }}）</span>
    </el-timeline-item>
  </el-timeline>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { OrderRow, OrderStatus } from '@/types/api/mall/order';

/**
 * 订单状态时间线（M08-PR32）。
 *
 * 把订单当前 status 投影到固定 5 节点链：pending → paid → shipped → delivered → (cancelled / refunded 终态)。
 * 完成节点用主色 + 实心；待发生用灰色虚线。时间戳从订单字段读取，缺失时省略。
 */

interface TimelineNode {
  key: string;
  label: string;
  completed: boolean;
  timestamp?: string | null;
}

const props = defineProps<{ order: OrderRow }>();

const STATUS_RANK: Record<string, number> = {
  pending: 0,
  paid: 1,
  shipped: 2,
  delivered: 3,
  cancelled: 4,
  refunded: 4,
};

const currentRank = computed(() => STATUS_RANK[props.order.status] ?? 0);

const isTerminal = computed(() => ['cancelled', 'refunded'].includes(String(props.order.status)));

const labelOf: Record<string, string> = {
  pending: '待支付',
  paid: '已支付',
  shipped: '已发货',
  delivered: '已签收',
  cancelled: '已取消',
  refunded: '已退款',
};

function labelFor(status: OrderStatus): string {
  return labelOf[String(status)] ?? String(status);
}

const currentBadge = computed(() => labelFor(props.order.status));

const nodes = computed<TimelineNode[]>(() => {
  const list: TimelineNode[] = [
    {
      key: 'pending',
      label: labelFor('pending'),
      completed: currentRank.value >= 0,
      timestamp: props.order.created_at ?? null,
    },
    {
      key: 'paid',
      label: labelFor('paid'),
      completed: currentRank.value >= 1,
      timestamp: props.order.paid_at ?? null,
    },
    {
      key: 'shipped',
      label: labelFor('shipped'),
      completed: currentRank.value >= 2,
      timestamp: props.order.shipments?.[0]?.shipped_at ?? null,
    },
    {
      key: 'delivered',
      label: labelFor('delivered'),
      completed: currentRank.value >= 3,
      timestamp: props.order.shipments?.find((s) => s.delivered_at)?.delivered_at ?? null,
    },
  ];

  if (isTerminal.value) {
    list.push({
      key: String(props.order.status),
      label: labelFor(props.order.status),
      completed: true,
      timestamp: props.order.updated_at ?? null,
    });
  }
  return list;
});

const currentIndex = computed(() => {
  if (isTerminal.value) return nodes.value.length - 1;
  return Math.min(currentRank.value, nodes.value.length - 1);
});
</script>

<style scoped>
.text-primary {
  color: var(--el-color-primary);
}
</style>
