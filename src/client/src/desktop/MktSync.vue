<template>
  <div class="p-6 max-w-7xl mx-auto">
    <div class="mb-6 flex justify-between items-center">
      <h1 class="text-2xl font-bold">Sync Monitor</h1>
      <div class="flex space-x-2">
        <button @click="showSyncModal = true" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
          New Sync
        </button>
        <button @click="refresh" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
          Refresh
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white p-4 rounded shadow">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Status</label>
          <select v-model="filters.status" @change="loadJobs" class="w-full border rounded px-3 py-2">
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="processing">Processing</option>
            <option value="completed">Completed</option>
            <option value="failed">Failed</option>
            <option value="retrying">Retrying</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">PO Deposit ID</label>
          <input v-model="filters.po_deposit_id" @input="loadJobs" type="number" 
                 class="w-full border rounded px-3 py-2" placeholder="Filter by PO Deposit">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Per Page</label>
          <select v-model="filters.per_page" @change="loadJobs" class="w-full border rounded px-3 py-2">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
            <option value="100">100</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-8">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      <p class="mt-2 text-gray-600">Loading sync jobs...</p>
    </div>

    <!-- Jobs Table -->
    <div v-else class="bg-white rounded shadow overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">PO Deposit</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Retries</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="job in jobs.data" :key="job.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ job.id }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              <span v-if="job.po_deposit">{{ job.po_deposit.job_number }}</span>
              <span v-else class="text-gray-400">-</span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span :class="statusClass(job.status)" class="px-2 py-1 text-xs rounded-full">
                {{ job.status }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              {{ formatDate(job.created_at) }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              {{ job.execution_time || '-' }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              {{ job.retry_count }} / {{ job.max_retries }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
              <button @click="viewDetails(job.id)" 
                      class="text-blue-600 hover:text-blue-800">
                View
              </button>
              <button v-if="job.status === 'failed' && job.can_retry" 
                      @click="retryJob(job.id)"
                      class="text-green-600 hover:text-green-800">
                Retry
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="jobs.data && jobs.data.length > 0" class="px-6 py-4 border-t flex justify-between items-center">
        <div class="text-sm text-gray-700">
          Showing {{ jobs.from }} to {{ jobs.to }} of {{ jobs.total }} results
        </div>
        <div class="flex space-x-2">
          <button @click="goToPage(jobs.current_page - 1)" 
                  :disabled="!jobs.prev_page_url"
                  class="px-3 py-1 border rounded disabled:opacity-50">
            Previous
          </button>
          <button @click="goToPage(jobs.current_page + 1)" 
                  :disabled="!jobs.next_page_url"
                  class="px-3 py-1 border rounded disabled:opacity-50">
            Next
          </button>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!jobs.data || jobs.data.length === 0" class="text-center py-8 text-gray-500">
        No sync jobs found
      </div>
    </div>

    <!-- Job Detail Modal -->
    <div v-if="selectedJob" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="selectedJob = null">
      <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <div class="flex justify-between items-start mb-4">
            <h2 class="text-xl font-bold">Sync Job #{{ selectedJob.id }}</h2>
            <button @click="selectedJob = null" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium text-gray-500">Status</label>
                <p :class="statusClass(selectedJob.status)" class="inline-block px-2 py-1 text-xs rounded-full mt-1">
                  {{ selectedJob.status }}
                </p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Sync Type</label>
                <p class="text-sm">{{ selectedJob.sync_type }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">PO Deposit</label>
                <p class="text-sm">{{ selectedJob.po_deposit?.job_number || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Company ID</label>
                <p class="text-sm">{{ selectedJob.company_id || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Created At</label>
                <p class="text-sm">{{ formatDate(selectedJob.created_at) }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Execution Time</label>
                <p class="text-sm">{{ selectedJob.execution_time || '-' }}</p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Retries</label>
                <p class="text-sm">{{ selectedJob.retry_count }} / {{ selectedJob.max_retries }}</p>
              </div>
            </div>

            <div v-if="selectedJob.error_message">
              <label class="text-sm font-medium text-gray-500">Error Message</label>
              <div class="mt-1 p-3 bg-red-50 border border-red-200 rounded text-sm text-red-800">
                {{ selectedJob.error_message }}
              </div>
            </div>

            <div v-if="selectedJob.result">
              <label class="text-sm font-medium text-gray-500">Result</label>
              <pre class="mt-1 p-3 bg-gray-50 border rounded text-xs overflow-x-auto">{{ JSON.stringify(selectedJob.result, null, 2) }}</pre>
            </div>

            <div v-if="selectedJob.payload">
              <label class="text-sm font-medium text-gray-500">Payload</label>
              <pre class="mt-1 p-3 bg-gray-50 border rounded text-xs overflow-x-auto">{{ JSON.stringify(selectedJob.payload, null, 2) }}</pre>
            </div>

            <div v-if="selectedJob.can_retry" class="pt-4 border-t">
              <button @click="retryJob(selectedJob.id)" 
                      class="w-full px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                Retry Sync Job
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- New Sync Modal -->
    <div v-if="showSyncModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showSyncModal = false">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6">
          <div class="flex justify-between items-start mb-4">
            <h2 class="text-xl font-bold">Trigger New Sync</h2>
            <button @click="showSyncModal = false" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium mb-1">PO Deposit ID (Optional)</label>
              <input v-model="syncForm.po_deposit_id" type="number" 
                     class="w-full border rounded px-3 py-2" 
                     placeholder="Leave empty to sync all">
              <p class="text-xs text-gray-500 mt-1">Leave empty to sync all pending PO Deposits</p>
            </div>

            <div class="flex items-center">
              <input v-model="syncForm.async" type="checkbox" id="async" class="mr-2">
              <label for="async" class="text-sm">Run asynchronously (queued)</label>
            </div>

            <button @click="triggerSync" 
                    :disabled="syncing"
                    class="w-full px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 disabled:opacity-50">
              {{ syncing ? 'Syncing...' : 'Start Sync' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { getSyncJobs, getSyncJob, retrySyncJob, syncPoDeposit } from '../services/service';

const loading = ref(false);
const syncing = ref(false);
const jobs = ref({ data: [] });
const selectedJob = ref(null);
const showSyncModal = ref(false);
const syncForm = ref({
  po_deposit_id: '',
  async: true
});
const filters = ref({
  status: '',
  po_deposit_id: '',
  per_page: 20,
  page: 1
});

const loadJobs = async () => {
  loading.value = true;
  try {
    const result = await getSyncJobs(filters.value);
    if (result.code === 200) {
      jobs.value = result.data;
    }
  } catch (error) {
    console.error('Failed to load sync jobs:', error);
  } finally {
    loading.value = false;
  }
};

const viewDetails = async (jobId) => {
  try {
    const result = await getSyncJob(jobId);
    if (result.code === 200) {
      selectedJob.value = result.data;
    }
  } catch (error) {
    console.error('Failed to load job details:', error);
  }
};

const retryJob = async (jobId) => {
  if (!confirm('Are you sure you want to retry this sync job?')) return;
  
  try {
    const result = await retrySyncJob(jobId);
    if (result.code === 202) {
      alert('Sync job has been queued for retry');
      selectedJob.value = null;
      loadJobs();
    } else {
      alert(result.message || 'Failed to retry sync job');
    }
  } catch (error) {
    console.error('Failed to retry job:', error);
    alert('Failed to retry sync job');
  }
};

const triggerSync = async () => {
  syncing.value = true;
  try {
    const payload = {
      async: syncForm.value.async
    };
    
    if (syncForm.value.po_deposit_id) {
      payload.po_deposit_id = parseInt(syncForm.value.po_deposit_id);
    }

    const result = await syncPoDeposit(payload);
    
    if (result.code === 200 || result.code === 202) {
      alert(result.message || 'Sync started successfully');
      showSyncModal.value = false;
      syncForm.value = { po_deposit_id: '', async: true };
      loadJobs();
    } else {
      alert(result.message || 'Failed to start sync');
    }
  } catch (error) {
    console.error('Failed to trigger sync:', error);
    alert('Failed to trigger sync');
  } finally {
    syncing.value = false;
  }
};

const goToPage = (page) => {
  filters.value.page = page;
  loadJobs();
};

const refresh = () => {
  loadJobs();
};

const statusClass = (status) => {
  const classes = {
    pending: 'bg-yellow-100 text-yellow-800',
    processing: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-800',
    retrying: 'bg-orange-100 text-orange-800'
  };
  return classes[status] || 'bg-gray-100 text-gray-800';
};

const formatDate = (dateString) => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  return date.toLocaleString();
};

onMounted(() => {
  loadJobs();
});
</script>
