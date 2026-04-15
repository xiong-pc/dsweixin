<template>
  <div class="app-container">
    <div class="search-container">
      <el-form :model="queryParams" :inline="true">
        <el-form-item label="关键词">
          <el-input
            v-model="queryParams.keywords"
            placeholder="昵称/操作行为/IP"
            clearable
            style="width: 200px"
            @keyup.enter="handleQuery"
          />
        </el-form-item>
        <el-form-item label="用户ID">
          <el-input
            v-model.number="queryParams.uid"
            placeholder="请输入用户ID"
            clearable
            style="width: 120px"
            @keyup.enter="handleQuery"
          />
        </el-form-item>
        <el-form-item label="站点ID">
          <el-input
            v-model.number="queryParams.site_id"
            placeholder="请输入站点ID"
            clearable
            style="width: 120px"
            @keyup.enter="handleQuery"
          />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleQuery"><el-icon><Search /></el-icon>搜索</el-button>
          <el-button @click="handleReset"><el-icon><Refresh /></el-icon>重置</el-button>
        </el-form-item>
      </el-form>
    </div>

    <div class="card-container">
      <el-table v-loading="loading" :data="logList" border stripe>
        <el-table-column label="ID" prop="id" width="70" align="center" />
        <el-table-column label="用户ID" prop="uid" width="80" align="center" />
        <el-table-column label="昵称" prop="username" width="120" />
        <el-table-column label="站点ID" prop="site_id" width="80" align="center" />
        <el-table-column label="操作行为" prop="action_name" width="140" />
        <el-table-column label="请求URL" prop="url" min-width="200" show-overflow-tooltip />
        <el-table-column label="IP地址" prop="ip" width="140" align="center" />
        <el-table-column label="传输数据" prop="data" min-width="160" show-overflow-tooltip>
          <template #default="{ row }">
            <span v-if="row.data">{{ row.data }}</span>
            <span v-else class="text-gray-400">—</span>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="80" align="center" fixed="right">
          <template #default="{ row }">
            <el-button type="danger" link v-hasPerm="['sys:user_log:delete']" @click="handleDelete(row.id)">
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="pagination-container">
        <el-pagination
          v-model:current-page="queryParams.pageNum"
          v-model:page-size="queryParams.pageSize"
          :page-sizes="[15, 30, 50, 100]"
          :total="total"
          layout="total, sizes, prev, pager, next"
          @size-change="handleQuery"
          @current-change="handleQuery"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getUserLogList, deleteUserLog } from '@/api/userLog'
import type { UserLogItem } from '@/types/api/userLog'

const loading = ref(false)
const logList = ref<UserLogItem[]>([])
const total = ref(0)

const queryParams = reactive({
  keywords: '',
  uid: '' as number | '',
  site_id: '' as number | '',
  pageNum: 1,
  pageSize: 15,
})

onMounted(() => {
  handleQuery()
})

async function handleQuery() {
  loading.value = true
  try {
    const res = await getUserLogList(queryParams)
    logList.value = res.data.list
    total.value = res.data.total
  } finally {
    loading.value = false
  }
}

function handleReset() {
  queryParams.keywords = ''
  queryParams.uid = ''
  queryParams.site_id = ''
  queryParams.pageNum = 1
  handleQuery()
}

async function handleDelete(id: number) {
  await ElMessageBox.confirm('确认删除该日志记录？', '提示', { type: 'warning' })
  try {
    await deleteUserLog(id)
    ElMessage.success('删除成功')
    handleQuery()
  } catch {
    // 错误已由 request 拦截器统一弹出
  }
}
</script>
