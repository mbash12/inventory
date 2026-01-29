<template>
  <div class="p-6 max-w-7xl mx-auto">
    <div class="mb-6 flex justify-between items-center">
      <h1 class="text-2xl font-bold">Projects Sync Status</h1>
      <button @click="loadProjects" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
        Refresh
      </button>
    </div>

    <!-- Filters -->
    <div class="mb-6 bg-white p-4 rounded shadow">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Search</label>
          <input v-model="filters.search" @input="loadProjects" type="text" 
                 class="w-full border rounded px-3 py-2" placeholder="Job number or client name">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Per Page</label>
          <select v-model="filters.per_page" @change="loadProjects" class="w-full border rounded px-3 py-2">
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
          </select>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-8">
      <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
    </div>

    <!-- Projects Table -->
    <div v-else class="bg-white rounded shadow overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Job Number</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client Code</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Sync</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="project in projects.data" :key="project.id" class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">{{ project.job_number }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ project.client_company }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ project.client_code }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm">
              <span :class="project.is_po_deposit ? 'text-blue-600' : 'text-green-600'">
                {{ project.is_po_deposit ? 'Deposit' : 'Non-Deposit' }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
              {{ project.last_sync ? formatDate(project.last_sync.created_at) : '-' }}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
              <span v-if="project.last_sync" :class="statusClass(project.last_sync.status)" 
                    class="px-2 py-1 text-xs rounded-full">
                {{ project.last_sync.status }}
              </span>
              <span v-else class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                Not Synced
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
              <button @click="syncProject(project.id)" 
                      :disabled="syncing[project.id]"
                      class="text-blue-600 hover:text-blue-800 disabled:opacity-50">
                {{ syncing[project.id] ? 'Syncing...' : 'Sync' }}
              </button>
              <button v-if="project.last_sync" @click="viewSyncDetails(project.last_sync.id)" 
                      class="text-gray-600 hover:text-gray-800">
                Details
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="projects.data && projects.data.length > 0" class="px-6 py-4 border-t flex justify-between items-center">
        <div class="text-sm text-gray-700">
          Showing {{ projects.from }} to {{ projects.to }} of {{ projects.total }} results
        </div>
        <div class="flex space-x-2">
          <button @click="goToPage(projects.current_page - 1)" 
                  :disabled="!projects.prev_page_url"
                  class="px-3 py-1 border rounded disabled:opacity-50">
            Previous
          </button>
          <button @click="goToPage(projects.current_page + 1)" 
                  :disabled="!projects.next_page_url"
                  class="px-3 py-1 border rounded disabled:opacity-50">
            Next
          </button>
        </div>
      </div>

      <!-- Empty State -->
      <div v-if="!projects.data || projects.data.length === 0" class="text-center py-8 text-gray-500">
        No projects with client code found
      </div>
    </div>

    <!-- Sync Details Modal -->
    <div v-if="selectedSync" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="selectedSync = null">
      <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <div class="flex justify-between items-start mb-4">
            <h2 class="text-xl font-bold">Sync Details</h2>
            <button @click="selectedSync = null" class="text-gray-400 hover:text-gray-600">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="text-sm font-medium text-gray-500">Status</label>
                <p :class="statusClass(selectedSync.status)" class="inline-block px-2 py-1 text-xs rounded-full mt-1">
                  {{ selectedSync.status }}
                </p>
              </div>
              <div>
                <label class="text-sm font-medium text-gray-500">Created At</label>
                <p class="text-sm">{{ formatDate(selectedSync.created_at) }}</p>
              </div>
            </div>

            <div v-if="selectedSync.error_message">
              <label class="text-sm font-medium text-gray-500">Error Message</label>
              <div class="mt-1 p-3 bg-red-50 border border-red-200 rounded text-sm text-red-800">
                {{ selectedSync.error_message }}
              </div>
            </div>

            <div v-if="selectedSync.result">
              <label class="text-sm font-medium text-gray-500">Result</label>
              <pre class="mt-1 p-3 bg-gray-50 border rounded text-xs overflow-x-auto">{{ JSON.stringify(selectedSync.result, null, 2) }}</pre>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { api, syncPoDeposit, getSyncJob } from '../services/service';

const loading = ref(false);
const projects = ref({ data: [] });
const selectedSync = ref(null);
const syncing = reactive({});
const filters = ref({
  search: '',
  per_page: 20,
  page: 1
});

const loadProjects = async () => {
  loading.value = true;
  try {
    const result = await api('getPodepositList', { 
      params: {
        search: filters.value.search,
        limit: filters.value.per_page,
        page: filters.value.page,
        has_client_code: 1
      }
    });
    if (result.code === 200) {
      projects.value = {
        data: result.data,
        total: result.meta.total_records,
        from: ((result.meta.current_page - 1) * result.meta.limit_per_page) + 1,
        to: Math.min(result.meta.current_page * result.meta.limit_per_page, result.meta.total_records),
        current_page: result.meta.current_page,
        prev_page_url: result.meta.current_page > 1 ? true : null,
        next_page_url: result.meta.current_page < result.meta.total_pages ? true : null
      };
    }
  } catch (error) {
    console.error('Failed to load projects:', error);
  } finally {
    loading.value = false;
  }
};

const syncProject = async (poDepositId) => {
  syncing[poDepositId] = true;
  try {
    const result = await syncPoDeposit({
      po_deposit_id: poDepositId,
      async: true
    });
    
    if (result.code === 200 || result.code === 202) {
      alert('Sync started successfully');
      loadProjects();
    } else {
      alert(result.message || 'Failed to start sync');
    }
  } catch (error) {
    console.error('Failed to sync:', error);
    alert('Failed to start sync');
  } finally {
    syncing[poDepositId] = false;
  }
};

const viewSyncDetails = async (syncId) => {
  try {
    const result = await getSyncJob(syncId);
    if (result.code === 200) {
      selectedSync.value = result.data;
    }
  } catch (error) {
    console.error('Failed to load sync details:', error);
  }
};

const goToPage = (page) => {
  filters.value.page = page;
  loadProjects();
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
  loadProjects();
});
</script>
