<template>
  <div class="p-4">
    <div class="mb-4">
      <h1 class="text-xl font-bold">Sync Monitoring</h1>
      <p class="text-gray-500 text-sm">Monitor PO Deposit sync status to Accounting system</p>
    </div>

    <div class="bg-white rounded-lg shadow">
      <div class="p-3 border-b flex justify-between items-center">
        <div class="flex gap-3">
          <input
            type="checkbox"
            @change="toggleSelectAll"
            :checked="allSelected"
            :indeterminate.prop="selectedItems.length > 0 && selectedItems.length < items.length"
            class="mr-2"
          />
          <button
            @click="showClearModal = true"
            class="bg-red-500 text-white px-3 py-1.5 rounded hover:bg-red-600 text-sm mr-2"
          >
            Clear Data
          </button>
          <button
            @click="bulkRetrySync"
            :disabled="selectedItems.length === 0 || bulkRetrying"
            class="bg-orange-500 text-white px-3 py-1.5 rounded hover:bg-orange-600 disabled:opacity-50 text-sm mr-2"
          >
            {{ bulkRetrying ? 'Syncing...' : `Bulk Re-sync (${selectedItems.length})` }}
          </button>
          <select v-model="filter.sync_status" @change="loadData" class="border rounded px-2 py-1 text-sm">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="syncing">Syncing</option>
            <option value="success">Success</option>
            <option value="failed">Failed</option>
          </select>
          <input v-model="filter.search" @input="debounceSearch" placeholder="Search..." class="border rounded px-2 py-1 w-48 text-sm" />
        </div>
        <button @click="loadData" class="bg-blue-500 text-white px-3 py-1.5 rounded hover:bg-blue-600 text-sm">
          Refresh
        </button>
      </div>

      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-2 text-left text-xs w-10">
              <input
                type="checkbox"
                @change="toggleSelectAll"
                :checked="allSelected"
                :indeterminate.prop="selectedItems.length > 0 && selectedItems.length < items.length"
              />
            </th>
            <th class="px-3 py-2 text-left text-xs">Job Number</th>
            <th class="px-3 py-2 text-left text-xs">Client</th>
            <th class="px-3 py-2 text-center text-xs">Type</th>
            <th class="px-3 py-2 text-center text-xs">Projects</th>
            <th class="px-3 py-2 text-center text-xs">Status</th>
            <th class="px-3 py-2 text-center text-xs">Last Synced</th>
            <th class="px-3 py-2 text-center text-xs">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in items" :key="item.id" class="border-b hover:bg-gray-50">
            <td class="px-3 py-2 text-sm">
              <input
                type="checkbox"
                :value="item.id"
                v-model="selectedItems"
                @change="updateSelectAllState"
              />
            </td>
            <td class="px-3 py-2 text-sm">{{ item.job_number }}</td>
            <td class="px-3 py-2 text-sm">
              <div class="font-medium">{{ item.client_company }}</div>
              <div class="text-xs text-gray-500">{{ item.client_code }}</div>
            </td>
            <td class="px-3 py-2 text-center text-sm">
              <span class="px-2 py-1 text-xs rounded" :class="item.is_po_deposit ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'">
                {{ item.is_po_deposit ? 'Deposit' : 'Non-Deposit' }}
              </span>
            </td>
            <td class="px-3 py-2 text-center text-sm">{{ item.project_count }}</td>
            <td class="px-3 py-2 text-center text-sm">
              <span class="px-2 py-1 text-xs rounded font-medium" :class="getStatusClass(item.sync_status)">
                {{ item.sync_status || 'Not Synced' }}
              </span>
            </td>
            <td class="px-3 py-2 text-center text-xs">
              {{ item.last_synced_at ? formatDate(item.last_synced_at) : '-' }}
            </td>
            <td class="px-3 py-2 text-center text-sm">
              <button
                @click="retrySync(item.id)"
                :disabled="retrying[item.id]"
                class="bg-orange-500 text-white px-2 py-1 rounded text-xs hover:bg-orange-600 disabled:opacity-50"
              >
                {{ retrying[item.id] ? 'Syncing...' : 'Re-sync' }}
              </button>
              <button
                v-if="item.sync_error"
                @click="viewDetails(item.id)"
                class="ml-1 bg-gray-500 text-white px-2 py-1 rounded text-xs hover:bg-gray-600"
              >
                Details
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="loading" class="p-4 text-center text-gray-500 text-sm">
        Loading...
      </div>

      <div v-if="!loading && items.length === 0" class="p-4 text-center text-gray-500 text-sm">
        No data found
      </div>

      <div v-if="meta.total > 0" class="p-3 border-t flex justify-between items-center">
        <div class="text-xs text-gray-600">
          Showing {{ items.length }} of {{ meta.total }} items
        </div>
        <div class="flex gap-1">
          <button
            @click="prevPage"
            :disabled="meta.current_page === 1"
            class="px-2 py-1 border rounded disabled:opacity-50 text-xs"
          >
            Prev
          </button>
          <button
            @click="nextPage"
            :disabled="meta.current_page * meta.per_page >= meta.total"
            class="px-2 py-1 border rounded disabled:opacity-50 text-xs"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Error Modal -->
    <div v-if="errorModal.show" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="errorModal.show = false">
      <div class="bg-white rounded-lg p-4 max-w-2xl w-full mx-4" @click.stop>
        <h3 class="text-base font-bold mb-3">Sync Error Details</h3>
        <pre class="bg-gray-100 p-3 rounded text-xs overflow-auto max-h-64">{{ errorModal.content }}</pre>
        <button @click="errorModal.show = false" class="mt-3 bg-gray-500 text-white px-3 py-1 rounded text-sm">Close</button>
      </div>
    </div>

    <!-- Clear Data Modal -->
    <div v-if="showClearModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click="showClearModal = false">
      <div class="bg-white rounded-lg p-4 max-w-md w-full mx-4" @click.stop>
        <h3 class="text-base font-bold mb-3">Clear Old Data</h3>
        <div class="mb-3">
          <label class="block text-sm mb-1">Days to keep (default: 7)</label>
          <input 
            v-model="clearDataForm.days" 
            type="number" 
            min="1" 
            max="365" 
            class="w-full border rounded px-2 py-1 text-sm"
            placeholder="Number of days (default: 7)"
          />
        </div>
        <p class="text-sm text-gray-600 mb-4">
          This will reset sync status for PO Deposit records older than the specified number of days.
          This action cannot be undone.
        </p>
        <div class="bg-red-50 border border-red-200 rounded p-3 mb-4">
          <p class="text-sm text-red-700 font-medium">Warning: This operation cannot be reversed!</p>
        </div>
        <div class="flex justify-end gap-2">
          <button @click="showClearModal = false" class="bg-gray-500 text-white px-3 py-1 rounded text-sm">Cancel</button>
          <button 
            @click="performClearData" 
            :disabled="clearingData"
            class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600 disabled:opacity-50"
          >
            {{ clearingData ? 'Clearing...' : 'Clear Data' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { api, clearSyncData } from '../services/service';

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
      selectedItems: [],
      bulkRetrying: false,
      showClearModal: false,
      clearDataForm: { days: 7 },
      clearingData: false,
    };
  },
  computed: {
    allSelected() {
      return this.items.length > 0 && this.selectedItems.length === this.items.length;
    }
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
        // Clear selections when data reloads
        this.selectedItems = [];
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
    async bulkRetrySync() {
      if (this.selectedItems.length === 0) return;

      if (!confirm(`Are you sure you want to re-sync ${this.selectedItems.length} items?`)) {
        return;
      }

      this.bulkRetrying = true;
      try {
        // Process each selected item
        for (const id of this.selectedItems) {
          try {
            await api('retrySyncPoDeposit', { route: `/${id}/retry` });
          } catch (error) {
            console.error(`Failed to sync item ${id}:`, error);
          }
        }

        alert(`Successfully initiated re-sync for ${this.selectedItems.length} items`);
        this.selectedItems = []; // Clear selections after successful bulk sync
        await this.loadData();
      } catch (error) {
        alert('Bulk sync failed: ' + (error.message || 'Unknown error'));
      } finally {
        this.bulkRetrying = false;
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
    toggleSelectAll() {
      if (this.allSelected) {
        this.selectedItems = [];
      } else {
        this.selectedItems = this.items.map(item => item.id);
      }
    },
    updateSelectAllState() {
      // This method is called when individual checkboxes change
      // It ensures the select-all checkbox state is consistent
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
    async performClearData() {
      const days = parseInt(this.clearDataForm.days) || 7;
      
      if (days < 1 || days > 365) {
        alert('Please enter a number between 1 and 365');
        return;
      }

      if (!confirm(`Are you sure you want to reset sync status for PO Deposit records older than ${days} days? This cannot be undone.`)) {
        return;
      }

      this.clearingData = true;
      try {
        // Call the clear data API using the service
        const result = await clearSyncData({ days });

        if (result.code === 200) {
          alert(result.message || `Successfully cleared sync status for PO Deposit records older than ${days} days`);
          this.showClearModal = false;
          this.clearDataForm.days = 7; // Reset to default
          await this.loadData(); // Refresh the data
        } else {
          alert(result.message || 'Failed to clear data');
        }
      } catch (error) {
        console.error('Error clearing data:', error);
        alert('Error clearing data: ' + error.message);
      } finally {
        this.clearingData = false;
      }
    },
  },
};
</script>
