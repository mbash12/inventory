<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="text-2xl font-bold">Sync Monitoring</h1>
      <p class="text-gray-600">Monitor PO Deposit sync status to Accounting system</p>
    </div>

    <div class="bg-white rounded-lg shadow">
      <div class="p-4 border-b flex justify-between items-center">
        <div class="flex gap-4">
          <select v-model="filter.sync_status" @change="loadData" class="border rounded px-3 py-2">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="syncing">Syncing</option>
            <option value="success">Success</option>
            <option value="failed">Failed</option>
          </select>
          <input v-model="filter.search" @input="debounceSearch" placeholder="Search..." class="border rounded px-3 py-2 w-64" />
        </div>
        <button @click="loadData" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
          Refresh
        </button>
      </div>

      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-3 text-left">Job Number</th>
            <th class="px-4 py-3 text-left">Client</th>
            <th class="px-4 py-3 text-center">Type</th>
            <th class="px-4 py-3 text-center">Projects</th>
            <th class="px-4 py-3 text-center">Status</th>
            <th class="px-4 py-3 text-center">Last Synced</th>
            <th class="px-4 py-3 text-center">Retries</th>
            <th class="px-4 py-3 text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id" class="border-b hover:bg-gray-50">
            <td class="px-4 py-3">{{ item.job_number }}</td>
            <td class="px-4 py-3">
              <div class="font-medium">{{ item.client_company }}</div>
              <div class="text-sm text-gray-500">{{ item.client_code }}</div>
            </td>
            <td class="px-4 py-3 text-center">
              <span class="px-2 py-1 text-xs rounded" :class="item.is_po_deposit ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'">
                {{ item.is_po_deposit ? 'Deposit' : 'Non-Deposit' }}
              </span>
            </td>
            <td class="px-4 py-3 text-center">{{ item.project_count }}</td>
            <td class="px-4 py-3 text-center">
              <span class="px-2 py-1 text-xs rounded font-medium" :class="getStatusClass(item.sync_status)">
                {{ item.sync_status || 'Not Synced' }}
              </span>
            </td>
            <td class="px-4 py-3 text-center text-sm">
              {{ item.last_synced_at ? formatDate(item.last_synced_at) : '-' }}
            </td>
            <td class="px-4 py-3 text-center">{{ item.sync_retry_count }}</td>
            <td class="px-4 py-3 text-center">
              <button 
                @click="retrySync(item.id)"
                :disabled="retrying[item.id]"
                class="bg-orange-500 text-white px-3 py-1 rounded text-sm hover:bg-orange-600 disabled:opacity-50"
              >
                {{ retrying[item.id] ? 'Syncing...' : 'Re-sync' }}
              </button>
              <button 
                v-if="item.sync_error"
                @click="viewDetails(item.id)"
                class="ml-2 bg-gray-500 text-white px-3 py-1 rounded text-sm hover:bg-gray-600"
              >
                Details
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="loading" class="p-8 text-center text-gray-500">
        Loading...
      </div>

      <div v-if="!loading && items.length === 0" class="p-8 text-center text-gray-500">
        No data found
      </div>

      <div v-if="meta.total > 0" class="p-4 border-t flex justify-between items-center">
        <div class="text-sm text-gray-600">
          Showing {{ items.length }} of {{ meta.total }} items
        </div>
        <div class="flex gap-2">
          <button 
            @click="prevPage" 
            :disabled="meta.current_page === 1"
            class="px-3 py-1 border rounded disabled:opacity-50"
          >
            Previous
          </button>
          <button 
            @click="nextPage" 
            :disabled="meta.current_page * meta.per_page >= meta.total"
            class="px-3 py-1 border rounded disabled:opacity-50"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Error Modal -->
    <div v-if="errorModal.show" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="errorModal.show = false">
      <div class="bg-white rounded-lg p-6 max-w-2xl w-full mx-4" @click.stop>
        <h3 class="text-lg font-bold mb-4">Sync Error Details</h3>
        <pre class="bg-gray-100 p-4 rounded text-sm overflow-auto max-h-96">{{ errorModal.content }}</pre>
        <button @click="errorModal.show = false" class="mt-4 bg-gray-500 text-white px-4 py-2 rounded">Close</button>
      </div>
    </div>
  </div>
</template>

<script>
import { api } from '../services/service';

export default {
  name: 'SyncMonitoring',
  data() {
    return {
      items: [],
      meta: { current_page: 1, total: 0, per_page: 20 },
      filter: { sync_status: '', search: '' },
      loading: false,
      retrying: {},
      errorModal: { show: false, content: '' },
      searchTimeout: null,
    };
  },
  mounted() {
    this.loadData();
    // Auto-refresh every 30 seconds
    this.refreshInterval = setInterval(() => this.loadData(), 30000);
  },
  beforeUnmount() {
    if (this.refreshInterval) clearInterval(this.refreshInterval);
  },
  methods: {
    async loadData() {
      this.loading = true;
      try {
        const params = {
          page: this.meta.current_page,
          per_page: this.meta.per_page,
          ...this.filter
        };
        const data = await api('getSyncPoDeposits', { params });
        this.items = data.data;
        this.meta = data.meta;
      } catch (error) {
        console.error('Failed to load data:', error);
      } finally {
        this.loading = false;
      }
    },
    async retrySync(id) {
      this.retrying[id] = true;
      try {
        const data = await api('retrySyncPoDeposit', { route: `/${id}/retry` });
        alert(data.message);
        await this.loadData();
      } catch (error) {
        alert('Retry failed: ' + (error.message || 'Unknown error'));
      } finally {
        this.retrying[id] = false;
      }
    },
    async viewDetails(id) {
      try {
        const data = await api('getSyncPoDepositStatus', { route: `/${id}` });
        if (data.data.sync_error) {
          this.errorModal.content = JSON.stringify(data.data.sync_error, null, 2);
          this.errorModal.show = true;
        } else {
          alert('No error details available');
        }
      } catch (error) {
        alert('Failed to load details');
      }
    },
    debounceSearch() {
      clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => this.loadData(), 500);
    },
    prevPage() {
      if (this.meta.current_page > 1) {
        this.meta.current_page--;
        this.loadData();
      }
    },
    nextPage() {
      if (this.meta.current_page * this.meta.per_page < this.meta.total) {
        this.meta.current_page++;
        this.loadData();
      }
    },
    getStatusClass(status) {
      const classes = {
        pending: 'bg-yellow-100 text-yellow-800',
        syncing: 'bg-blue-100 text-blue-800',
        success: 'bg-green-100 text-green-800',
        failed: 'bg-red-100 text-red-800',
      };
      return classes[status] || 'bg-gray-100 text-gray-800';
    },
    formatDate(date) {
      return new Date(date).toLocaleString();
    },
  },
};
</script>
