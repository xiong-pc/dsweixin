<template>
  <el-table :data="rows" border stripe size="small" style="width: 100%">
    <el-table-column label="商品" min-width="220">
      <template #default="{ row }">
        <div>
          <div class="text-sm font-medium">{{ row.name_snapshot || row.sku }}</div>
          <div class="text-xs text-gray-500">SKU: {{ row.sku || '-' }}</div>
        </div>
      </template>
    </el-table-column>
    <el-table-column label="单价" prop="unit_price" width="120" align="right">
      <template #default="{ row }"> {{ row.currency }} {{ formatAmount(row.unit_price) }} </template>
    </el-table-column>
    <el-table-column label="数量" prop="quantity" width="80" align="center" />
    <el-table-column label="小计" prop="line_total" width="140" align="right">
      <template #default="{ row }">
        <span class="font-semibold">{{ row.currency }} {{ formatAmount(row.line_total) }}</span>
      </template>
    </el-table-column>
  </el-table>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { OrderItemRow } from '@/types/api/mall/order';

/**
 * 订单商品行表（M08-PR32）：详情页内嵌使用。
 * 不做小计加总（那部分由父组件展示订单合计 / 减运费 / 税费等）。
 */
const props = defineProps<{
  items: OrderItemRow[];
  currency: string;
}>();

const rows = computed(() =>
  props.items.map((it) => ({
    ...it,
    currency: it.currency || props.currency,
  })),
);

function formatAmount(v: string | number): string {
  const num = Number(v);
  if (!Number.isFinite(num)) return '0.00';
  return num.toFixed(2);
}
</script>
