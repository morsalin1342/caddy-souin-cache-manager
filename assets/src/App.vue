<template>
  <!-- Main Container with Ultra-Compact Dashboard Layout -->
  <div id="cscm-plugin-container" class="tw:min-h-screen tw:bg-slate-50 tw:text-slate-600 tw:p-0 tw:font-sans tw:relative tw:overflow-hidden">

    <!-- Subtle Background Accents -->
    <div class="tw:absolute tw:top-[-10%] tw:left-[-10%] tw:w-[40%] tw:h-[40%] tw:bg-blue-500/5 tw:blur-[120px] tw:rounded-full tw:pointer-events-none"></div>
    <div class="tw:absolute tw:bottom-[-10%] tw:right-[-10%] tw:w-[30%] tw:h-[30%] tw:bg-cyan-500/5 tw:blur-[100px] tw:rounded-full tw:pointer-events-none"></div>

    <!-- Toast Notifications -->
    <div role="status" aria-live="polite" class="tw:fixed tw:top-6 tw:right-6 tw:z-[9999] tw:flex tw:flex-col tw:gap-3">
      <transition-group name="toast">
        <div v-for="toast in toasts" :key="toast.id"
             :class="[
               'tw:px-5 tw:py-4 tw:rounded-lg tw:shadow-xl tw:bg-white tw:border tw:flex tw:items-center tw:gap-4 tw:min-w-[320px] tw:relative tw:overflow-hidden',
               toast.type === 'success' ? 'tw:border-emerald-100 tw:text-emerald-600' : 'tw:border-rose-100 tw:text-rose-600'
             ]">
          <div class="tw:flex-shrink-0 tw:w-8 tw:h-8 tw:rounded-full tw:bg-current/10 tw:flex tw:items-center tw:justify-center">
            <svg v-if="toast.type === 'success'" class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            <svg v-else class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </div>
          <div class="tw:flex-1 tw:font-semibold tw:text-sm">{{ toast.message }}</div>
          <button @click="removeToast(toast.id)" class="tw:text-slate-400 tw:hover:text-slate-600 tw:transition-colors" aria-label="Close">
            <svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
          </button>
          <div class="tw:absolute tw:bottom-0 tw:left-0 tw:h-0.5 tw:bg-current tw:animate-shrink"></div>
        </div>
      </transition-group>
    </div>

    <!-- Compact Pro Header - Sticky -->
    <header class="tw:bg-white tw:border-b tw:border-slate-200 tw:px-6 tw:py-3 tw:sticky tw:top-[32px] tw:z-50 tw:shadow-sm">
      <div class="tw:flex tw:flex-col tw:lg:flex-row tw:lg:items-center tw:justify-between tw:gap-4">
        
        <!-- Logo & Main Nav -->
        <div class="tw:flex tw:items-center tw:gap-6">
          <div class="tw:flex tw:items-center tw:gap-3">
            <div class="tw:w-8 tw:h-8 tw:bg-blue-600 tw:rounded-lg tw:flex tw:items-center tw:justify-center tw:shadow-blue-600/20 tw:shadow-lg">
              <svg class="tw:w-5 tw:h-5 tw:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
            <h1 class="tw:text-lg tw:font-bold tw:text-slate-900 tw:tracking-tight tw:hidden tw:sm:block">Souin</h1>
          </div>

          <nav class="tw:flex tw:bg-slate-100 tw:p-1 tw:rounded-lg">
            <a href="admin.php?page=cscm-cache-keys"
               :class="['tw:px-4 tw:py-1.5 tw:rounded-md tw:text-xs tw:font-bold tw:transition-all', currentPage === 'keys' ? 'tw:bg-white tw:text-blue-600 tw:shadow-sm' : 'tw:text-slate-500 tw:hover:text-blue-600']">
              Dashboard
            </a>
            <a href="admin.php?page=cscm-settings"
               :class="['tw:px-4 tw:py-1.5 tw:rounded-md tw:text-xs tw:font-bold tw:transition-all', currentPage === 'settings' ? 'tw:bg-white tw:text-blue-600 tw:shadow-sm' : 'tw:text-slate-500 tw:hover:text-blue-600']">
              Settings
            </a>
          </nav>
        </div>

        <!-- Live Indicators & Global Actions -->
        <div class="tw:flex tw:flex-wrap tw:items-center tw:gap-4">
          <!-- Active Keys -->
          <div class="tw:flex tw:items-center tw:gap-2 tw:bg-slate-50 tw:px-3 tw:py-1.5 tw:rounded-lg tw:border tw:border-slate-100">
            <span class="tw:text-[10px] tw:font-bold tw:text-slate-400 tw:uppercase">Active Keys</span>
            <span class="tw:text-sm tw:font-bold tw:text-slate-900">{{ keys.length }}</span>
          </div>

          <!-- Souin Status -->
          <div class="tw:flex tw:items-center tw:gap-2 tw:bg-slate-50 tw:px-3 tw:py-1.5 tw:rounded-lg tw:border tw:border-slate-100">
            <span class="tw:text-[10px] tw:font-bold tw:text-slate-400 tw:uppercase">Souin</span>
            <div class="tw:text-[10px] tw:font-bold tw:text-emerald-500 tw:flex tw:items-center tw:gap-1.5">
              <span class="tw:w-1.5 tw:h-1.5 tw:bg-emerald-500 tw:rounded-full tw:animate-pulse"></span>
              Online
            </div>
          </div>

          <!-- Redis Status -->
          <div v-if="settings.redis_enabled === '1'" class="tw:flex tw:items-center tw:gap-2 tw:bg-slate-50 tw:px-3 tw:py-1.5 tw:rounded-lg tw:border tw:border-slate-100">
            <span class="tw:text-[10px] tw:font-bold tw:text-slate-400 tw:uppercase">Redis</span>
            <div class="tw:text-[10px] tw:font-bold tw:text-blue-500 tw:flex tw:items-center tw:gap-1.5">
              <span class="tw:w-1.5 tw:h-1.5 tw:bg-blue-500 tw:rounded-full"></span>
              Linked
            </div>
          </div>

          <!-- Global Flush Button -->
          <button @click="purgeAll" :disabled="purging"
                  class="tw:bg-rose-50 tw:text-rose-600 tw:border tw:border-rose-100 tw:hover:bg-rose-600 tw:hover:!text-white tw:font-bold tw:px-4 tw:py-1.5 tw:rounded-lg tw:text-[10px] tw:uppercase tw:tracking-widest tw:transition-all tw:disabled:opacity-50 tw:flex tw:items-center tw:gap-2">
            <svg class="tw:w-3 tw:h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
            Flush All Cache
          </button>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="tw:w-full tw:mx-auto tw:p-6 tw:md:p-8 tw:mt-8">

      <!-- Cache Keys View -->
      <div v-if="currentPage === 'keys'" class="tw:grid tw:grid-cols-1 tw:lg:grid-cols-12 tw:gap-8 tw:animate-in tw:fade-in tw:slide-in-from-bottom-2 tw:duration-500">
        
        <!-- Left Column: Wide Inventory -->
        <div class="tw:lg:col-span-8 tw:space-y-6">
          <div class="tw:bg-white tw:border tw:border-slate-200 tw:rounded-xl tw:overflow-hidden tw:shadow-sm">
            <div class="tw:px-6 tw:py-4 tw:border-b tw:border-slate-100 tw:flex tw:justify-between tw:items-center tw:bg-slate-50/30">
              <div class="tw:flex tw:items-center tw:gap-3">
                <h2 class="tw:text-sm tw:font-bold tw:text-slate-900">{{ isRedisSearchResult ? 'Redis Results' : 'Cache Inventory' }}</h2>
                <button v-if="selectedKeys.length > 0" @click="applyBulkAction"
                        class="tw:bg-blue-600 tw:!text-white tw:px-3 tw:py-1 tw:rounded-md tw:text-[10px] tw:font-bold tw:hover:bg-blue-700 tw:transition-colors">
                  Purge {{ selectedKeys.length }}
                </button>
                <button v-if="isRedisSearchResult" @click="clearRedisSearch" class="tw:text-slate-400 tw:hover:text-slate-600 tw:text-[10px] tw:font-bold">
                  Clear Search
                </button>
              </div>
              <button @click="fetchKeys" class="tw:text-blue-600 tw:hover:text-blue-800 tw:text-xs tw:font-bold tw:flex tw:items-center tw:gap-1.5 tw:transition-colors" aria-label="Sync Keys">
                <svg :class="['tw:w-3.5 tw:h-3.5', loading ? 'tw:animate-spin' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                Sync
              </button>
            </div>

            <div class="tw:overflow-x-auto">
              <div role="list" class="tw:divide-y tw:divide-slate-50">
                <!-- Header -->
                <div class="tw:flex tw:items-center tw:px-6 tw:py-2.5 tw:bg-slate-50/50">
                  <div class="tw:w-10">
                    <input type="checkbox" v-model="selectAllCurrentPage" class="tw:w-4 tw:h-4 tw:rounded tw:border-slate-300 tw:text-blue-600" aria-label="Select all keys on this page">
                  </div>
                  <div class="tw:flex-1 tw:px-4 tw:text-[9px] tw:font-bold tw:text-slate-400 tw:uppercase tw:tracking-widest">Key Reference</div>
                  <div class="tw:px-6 tw:text-right tw:text-[9px] tw:font-bold tw:text-slate-400 tw:uppercase tw:tracking-widest">Action</div>
                </div>
                <!-- Body -->
                <div v-if="loading && keys.length === 0" class="tw:px-6 tw:py-16 tw:text-center">
                  <span class="tw:text-slate-400 tw:text-xs tw:font-semibold">Retrieving...</span>
                </div>
                <div v-else-if="keys.length === 0" class="tw:px-6 tw:py-16 tw:text-center tw:text-slate-300 tw:italic tw:text-xs">
                  Zone is empty.
                </div>
                <div v-for="key in paginatedKeys" :key="key" role="listitem" class="tw:group tw:flex tw:items-center tw:px-6 hover:tw:bg-blue-50/30 tw:transition-colors">
                  <div class="tw:w-10 tw:py-1.5">
                    <input type="checkbox" v-model="selectedKeys" :value="key" class="tw:w-4 tw:h-4 tw:rounded tw:border-slate-300 tw:text-blue-600" :aria-label="`Select key: ${key}`">
                  </div>
                  <div class="tw:flex-1 tw:px-4 tw:py-1.5 tw:font-mono tw:text-[11px] tw:break-all">
                    <span class="tw:text-slate-700 tw:group-hover:text-blue-600 tw:transition-colors">{{ key }}</span>
                  </div>
                  <div class="tw:px-6 tw:py-1.5 tw:text-right">
                    <button @click="purgeKey(key)" class="tw:opacity-0 tw:group-hover:opacity-100 tw:text-rose-600 tw:hover:underline tw:text-[9px] tw:font-bold tw:uppercase tw:transition-all">
                      Purge
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Pagination -->
            <div v-if="keys.length > itemsPerPage" class="tw:px-6 tw:py-3 tw:border-t tw:border-slate-100 tw:flex tw:items-center tw:justify-between tw:bg-slate-50/30">
              <div class="tw:text-[10px] tw:text-slate-500">
                <span class="tw:font-bold tw:text-slate-700">{{ paginationStart + 1 }}-{{ Math.min(paginationEnd, keys.length) }}</span> of <span class="tw:font-bold tw:text-slate-700">{{ keys.length }}</span>
              </div>
              <div class="tw:flex tw:items-center tw:gap-2">
                <button @click="keysPage--" :disabled="keysPage === 1"
                        class="tw:p-1.5 tw:rounded-lg tw:border tw:border-slate-200 tw:bg-white tw:text-slate-600 tw:disabled:opacity-30 tw:transition-all" aria-label="Previous Page">
                  <svg class="tw:w-3.5 tw:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </button>
                <span class="tw:text-[10px] tw:font-bold tw:text-slate-600">{{ keysPage }} / {{ totalPages }}</span>
                <button @click="keysPage++" :disabled="keysPage === totalPages"
                        class="tw:p-1.5 tw:rounded-lg tw:border tw:border-slate-200 tw:bg-white tw:text-slate-600 tw:disabled:opacity-30 tw:transition-all" aria-label="Next Page">
                  <svg class="tw:w-3.5 tw:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Sidebar Controls -->
        <div class="tw:lg:col-span-4 tw:space-y-6">
          
          <!-- Operations Card -->
          <div class="tw:bg-white tw:border tw:border-slate-200 tw:p-6 tw:rounded-xl tw:shadow-sm tw:space-y-6">
            
            <!-- Pattern Purge -->
            <div class="tw:space-y-3">
              <h2 class="tw:text-xs tw:font-bold tw:text-slate-900 tw:uppercase tw:tracking-widest">Pattern Invalidation</h2>
              <form @submit.prevent="purgeRegex" class="tw:flex tw:flex-col tw:gap-2.5">
                <label for="regex-pattern" class="tw:sr-only">Purge by Regex Pattern</label>
                <div class="tw:relative">
                  <span class="tw:absolute tw:inset-y-0 tw:left-3 tw:flex tw:items-center tw:text-slate-400 tw:font-mono tw:text-xs">/</span>
                  <input type="text" v-model="regexPattern" id="regex-pattern" placeholder=".*blog.*"
                         class="tw:w-full tw:bg-slate-50 tw:border tw:border-slate-200 tw:rounded-lg tw:pl-6 tw:pr-8 tw:py-2 tw:text-slate-900 tw:focus:bg-white tw:focus:border-blue-500 tw:focus:ring-2 tw:focus:ring-blue-500/10 tw:outline-none tw:transition-all tw:font-mono tw:text-xs" required>
                  <span class="tw:absolute tw:inset-y-0 tw:right-3 tw:flex tw:items-center tw:text-slate-400 tw:font-mono tw:text-xs">/g</span>
                </div>
                <button type="submit" :disabled="purging"
                        class="tw:w-full tw:bg-slate-900 tw:hover:bg-slate-800 tw:active:scale-[0.98] tw:!text-white tw:font-bold tw:py-2 tw:rounded-lg tw:text-[10px] tw:uppercase tw:tracking-widest tw:transition-all tw:disabled:opacity-50">
                  {{ purging ? 'Executing...' : 'Purge Pattern' }}
                </button>
              </form>
            </div>

            <!-- Redis Search -->
            <div v-if="settings.redis_enabled === '1'" class="tw:pt-6 tw:border-t tw:border-slate-100 tw:space-y-3">
              <h2 class="tw:text-xs tw:font-bold tw:text-slate-900 tw:uppercase tw:tracking-widest">Redis Key Search</h2>
              <div class="tw:flex tw:flex-col tw:gap-2.5">
                <label for="redis-search" class="tw:sr-only">Search Redis Keys</label>
                <input type="text" v-model="redisSearchPattern" id="redis-search" @keyup.enter="searchRedis" placeholder="users:* or *api*"
                       class="tw:w-full tw:bg-slate-50 tw:border tw:border-slate-200 tw:rounded-lg tw:px-4 tw:py-2 tw:text-slate-900 tw:focus:bg-white tw:focus:border-blue-500 tw:focus:ring-2 tw:focus:ring-blue-500/10 tw:outline-none tw:transition-all tw:text-xs">
                <button @click="searchRedis" :disabled="searchingRedis"
                        class="tw:w-full tw:bg-blue-600 tw:hover:bg-blue-700 tw:active:scale-[0.98] tw:!text-white tw:font-bold tw:py-2 tw:rounded-lg tw:text-[10px] tw:uppercase tw:tracking-widest tw:transition-all tw:disabled:opacity-50">
                  {{ searchingRedis ? 'Searching...' : 'Scan Redis' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Documentation Hint -->
          <div class="tw:bg-blue-50/30 tw:border tw:border-blue-100 tw:p-5 tw:rounded-xl">
            <h3 class="tw:text-[10px] tw:font-bold tw:text-blue-600 tw:uppercase tw:tracking-widest tw:mb-2">Info</h3>
            <p class="tw:text-slate-500 tw:text-[11px] tw:leading-relaxed">
              Global flush immediately invalidates all items across all cache nodes. Use surgically targeting patterns for high-traffic sites.
            </p>
          </div>
        </div>
      </div>

      <!-- Settings View -->
      <div v-if="currentPage === 'settings'" class="tw:max-w-2xl tw:mx-auto tw:animate-in tw:fade-in tw:slide-in-from-bottom-2 tw:duration-500 tw:pb-20">
        <div class="tw:bg-white tw:border tw:border-slate-200 tw:rounded-xl tw:p-8 tw:shadow-sm">
          <h2 class="tw:text-lg tw:font-bold tw:text-slate-900 tw:mb-6">System Configuration</h2>

          <form @submit.prevent="saveSettings" class="tw:space-y-8">
            <div class="tw:space-y-2">
              <label class="tw:text-[10px] tw:font-bold tw:text-slate-400 tw:uppercase tw:tracking-widest">Souin Gateway URL</label>
              <input type="url" v-model="settings.api_url"
                     class="tw:w-full tw:bg-slate-50 tw:border tw:border-slate-200 tw:rounded-lg tw:px-4 tw:py-2.5 tw:text-slate-900 tw:focus:bg-white tw:focus:border-blue-500 tw:focus:ring-2 tw:focus:ring-blue-500/10 tw:outline-none tw:transition-all tw:font-mono tw:text-xs" required>
            </div>

            <div class="tw:flex tw:items-center tw:justify-between tw:bg-slate-50 tw:p-5 tw:rounded-lg tw:border tw:border-slate-200">
              <div>
                <label class="tw:text-slate-900 tw:font-bold tw:text-sm">Auto Purge</label>
                <p class="tw:text-slate-400 tw:text-[10px]">Purge on content updates.</p>
              </div>
              <button type="button" @click="settings.auto_purge = !settings.auto_purge"
                      :class="['tw:w-10 tw:h-5 tw:rounded-full tw:p-0.5 tw:transition-all', settings.auto_purge ? 'tw:bg-blue-600' : 'tw:bg-slate-300']"
                      role="switch" :aria-checked="settings.auto_purge">
                <div :class="['tw:w-4 tw:h-4 tw:bg-white tw:rounded-full tw:shadow-sm tw:transform tw:transition-all', settings.auto_purge ? 'tw:translate-x-5' : 'tw:translate-x-0']"></div>
              </button>
            </div>

            <div class="tw:pt-6 tw:border-t tw:border-slate-100 tw:space-y-6">
              <div class="tw:flex tw:items-center tw:justify-between">
                <div>
                  <h3 class="tw:text-sm tw:font-bold tw:text-slate-900">Redis Direct Access</h3>
                  <p class="tw:text-slate-400 tw:text-[10px]">For advanced search.</p>
                </div>
                <button type="button" @click="settings.redis_enabled = (settings.redis_enabled === '1' ? '0' : '1')"
                        :class="['tw:w-10 tw:h-5 tw:rounded-full tw:p-0.5 tw:transition-all', settings.redis_enabled === '1' ? 'tw:bg-blue-600' : 'tw:bg-slate-300']"
                        role="switch" :aria-checked="settings.redis_enabled === '1'">
                  <div :class="['tw:w-4 tw:h-4 tw:bg-white tw:rounded-full tw:shadow-sm tw:transform tw:transition-all', settings.redis_enabled === '1' ? 'tw:translate-x-5' : 'tw:translate-x-0']"></div>
                </button>
              </div>

              <div v-if="settings.redis_enabled === '1'" class="tw:grid tw:grid-cols-2 tw:gap-4 tw:animate-in tw:fade-in">
                <div class="tw:space-y-1.5">
                  <label class="tw:text-[9px] tw:font-bold tw:text-slate-400 tw:uppercase">Host</label>
                  <input type="text" v-model="settings.redis_host" class="tw:w-full tw:bg-slate-50 tw:border tw:border-slate-200 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-xs focus:tw:bg-white focus:tw:border-blue-500">
                </div>
                <div class="tw:space-y-1.5">
                  <label class="tw:text-[9px] tw:font-bold tw:text-slate-400 tw:uppercase">Port</label>
                  <input type="number" v-model="settings.redis_port" class="tw:w-full tw:bg-slate-50 tw:border tw:border-slate-200 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-xs focus:tw:bg-white focus:tw:border-blue-500">
                </div>
                <div class="tw:space-y-1.5">
                  <label class="tw:text-[9px] tw:font-bold tw:text-slate-400 tw:uppercase">Password</label>
                  <input type="password" v-model="settings.redis_password" autocomplete="new-password" class="tw:w-full tw:bg-slate-50 tw:border tw:border-slate-200 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-xs focus:tw:bg-white focus:tw:border-blue-500">
                  <p v-if="settings.redis_password_set" class="tw:text-slate-400 tw:text-[10px]">A password is saved. Leave it as is to keep it, or clear the field to remove it.</p>
                </div>
                <div class="tw:space-y-1.5">
                  <label class="tw:text-[9px] tw:font-bold tw:text-slate-400 tw:uppercase">DB Index</label>
                  <input type="number" v-model="settings.redis_db" class="tw:w-full tw:bg-slate-50 tw:border tw:border-slate-200 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-xs focus:tw:bg-white focus:tw:border-blue-500">
                </div>
                <button type="button" @click="testRedisConnection" :disabled="testingRedis"
                        class="tw:col-span-2 tw:mt-2 tw:bg-slate-100 tw:hover:bg-slate-200 tw:text-slate-600 tw:text-[10px] tw:font-bold tw:uppercase tw:py-2 tw:rounded-lg">
                  {{ testingRedis ? 'Testing...' : 'Test Redis' }}
                </button>
              </div>
            </div>

            <div class="tw:flex tw:gap-4 tw:pt-6">
              <button type="submit" :disabled="saving"
                      class="tw:flex-1 tw:bg-blue-600 tw:hover:bg-blue-700 tw:active:scale-[0.98] tw:!text-white tw:font-bold tw:py-2 tw:rounded-lg tw:text-xs tw:transition-all tw:disabled:opacity-50">
                {{ saving ? 'Updating...' : 'Save Settings' }}
              </button>
              <button type="button" @click="testConnection" :disabled="testing"
                      class="tw:px-6 tw:bg-slate-100 tw:hover:bg-slate-200 tw:text-slate-700 tw:font-bold tw:py-2 tw:rounded-lg tw:text-xs tw:transition-all">
                Test API
              </button>
            </div>
          </form>
        </div>
      </div>
    </main>

  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';

const currentPage = CSCM.currentPage; 
const toasts = ref([]);
const loading = ref(false);
const purging = ref(false);
const saving = ref(false);
const testing = ref(false);
const testingRedis = ref(false);
const searchingRedis = ref(false);

const keys = ref([]);
const selectedKeys = ref([]);
const regexPattern = ref('');
const redisSearchPattern = ref('');
const isRedisSearchResult = ref(false);

// Pagination State
const keysPage = ref(1);
const itemsPerPage = ref(20);

const settings = ref({
  api_url: '',
  auto_purge: true,
  redis_enabled: '0',
  redis_host: '127.0.0.1',
  redis_port: 6379,
  redis_password: '',
  redis_db: 0,
});

// Computed Pagination
const totalPages = computed(() => Math.ceil(keys.value.length / itemsPerPage.value) || 1);
const paginationStart = computed(() => (keysPage.value - 1) * itemsPerPage.value);
const paginationEnd = computed(() => paginationStart.value + itemsPerPage.value);
const paginatedKeys = computed(() => keys.value.slice(paginationStart.value, paginationEnd.value));

const selectAllCurrentPage = computed({
  get: () => paginatedKeys.value.length > 0 && paginatedKeys.value.every(k => selectedKeys.value.includes(k)),
  set: (value) => {
    if (value) {
      const newKeys = paginatedKeys.value.filter(k => !selectedKeys.value.includes(k));
      selectedKeys.value = [...selectedKeys.value, ...newKeys];
    } else {
      selectedKeys.value = selectedKeys.value.filter(k => !paginatedKeys.value.includes(k));
    }
  },
});

// Watchers
watch(keys, () => { keysPage.value = 1; });

const addToast = (message, type = 'success') => {
  const id = Date.now();
  toasts.value.push({ id, message, type });
  setTimeout(() => removeToast(id), 5000);
};

const removeToast = (id) => {
  toasts.value = toasts.value.filter(t => t.id !== id);
};

const apiCall = async (endpoint, method = 'GET', body = null) => {
  const options = {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': CSCM.nonce,
    },
  };
  if (body) options.body = JSON.stringify(body);

  const url = `${CSCM.apiUrl}/${endpoint}`;
  try {
    const response = await fetch(url, options);
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'API request failed');
    return data;
  } catch (e) {
    addToast(e.message, 'error');
    throw e;
  }
};

const fetchKeys = async () => {
  loading.value = true;
  isRedisSearchResult.value = false;
  try {
    const data = await apiCall('keys');
    keys.value = Array.isArray(data) ? data : [];
  } catch (e) {
  } finally {
    loading.value = false;
  }
};

const searchRedis = async () => {
  if (!redisSearchPattern.value) return;
  searchingRedis.value = true;
  loading.value = true;
  try {
    const pattern = encodeURIComponent(redisSearchPattern.value);
    const data = await apiCall(`redis/search?pattern=${pattern}`);
    keys.value = Array.isArray(data) ? data : [];
    isRedisSearchResult.value = true;
    addToast(`Redis search complete`);
  } catch (e) {
  } finally {
    searchingRedis.value = false;
    loading.value = false;
  }
};

const clearRedisSearch = () => {
  redisSearchPattern.value = '';
  fetchKeys();
};

const handlePurge = async (payload) => {
  purging.value = true;
  try {
    await apiCall('purge', 'POST', payload);
    addToast('Success');
    if (isRedisSearchResult.value) {
      searchRedis();
    } else {
      fetchKeys();
    }
  } catch (e) {
  } finally {
    purging.value = false;
  }
};

const purgeAll = () => {
  if (confirm('Flush entire cache?')) {
    handlePurge({ type: 'all' });
  }
};

const purgeKey = (key) => handlePurge({ type: 'keys', keys: [key] });

const purgeRegex = () => {
  if (regexPattern.value) {
    handlePurge({ type: 'regex', regex: regexPattern.value });
    regexPattern.value = '';
  }
};

const applyBulkAction = () => {
  if (selectedKeys.value.length > 0) {
    handlePurge({ type: 'keys', keys: selectedKeys.value });
    selectedKeys.value = [];
  }
};

const fetchSettings = async () => {
  try {
    const data = await apiCall('settings');
    settings.value = {
      ...data,
      auto_purge: data.auto_purge === '1'
    };
  } catch (e) {}
};

const saveSettings = async () => {
  saving.value = true;
  try {
    await apiCall('settings', 'POST', settings.value);
    addToast('Saved');
  } catch (e) {
  } finally {
    saving.value = false;
  }
};

const testConnection = async () => {
  testing.value = true;
  try {
    const data = await apiCall('test-connection', 'POST', { api_url: settings.value.api_url });
    addToast(data.message);
  } catch (e) {
  } finally {
    testing.value = false;
  }
};

const testRedisConnection = async () => {
  testingRedis.value = true;
  try {
    const data = await apiCall('redis/test-connection', 'POST', {
      redis_host: settings.value.redis_host,
      redis_port: settings.value.redis_port,
      redis_password: settings.value.redis_password,
      redis_db: settings.value.redis_db,
    });
    addToast(data.message);
  } catch (e) {
  } finally {
    testingRedis.value = false;
  }
};

const adjustHeaderPosition = () => {
  const adminBar = document.getElementById('wpadminbar');
  const notices = document.querySelectorAll('.wrap > .notice, .wrap > div.error, .wrap > div.updated');
  let totalOffset = adminBar ? adminBar.offsetHeight : 0;

  notices.forEach(notice => {
    totalOffset += notice.offsetHeight;
  });

  const header = document.querySelector('#cscm-plugin-container > header');
  if (header) {
    header.style.top = `${totalOffset}px`;
  }
};

onMounted(() => {
  if (currentPage === 'keys') fetchKeys();
  fetchSettings();

  // Adjust header on load and on window resize
  adjustHeaderPosition();
  window.addEventListener('resize', adjustHeaderPosition);

  // Use a ResizeObserver to watch for changes in the wrap element, which will
  // fire when notices are added or dismissed.
  const wrapElement = document.querySelector('.wrap');
  if (wrapElement) {
    const observer = new ResizeObserver(adjustHeaderPosition);
    observer.observe(wrapElement);
  }
});
</script>

<style>
/* Remove the hardcoded top value for the header */
#cscm-plugin-container > header {
  position: sticky;
  z-index: 50;
}

.toast-enter-active, .toast-leave-active { transition: all 0.4s ease; }
.toast-enter-from { opacity: 0; transform: translateY(-20px); }
.toast-leave-to { opacity: 0; transform: scale(0.95); }

@keyframes shrink { from { width: 100%; } to { width: 0%; } }
.tw\:animate-shrink { animation: shrink 5s linear forwards; }
</style>
