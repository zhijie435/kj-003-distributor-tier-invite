<template>
    <div class="space-y-6">
        <div class="flex items-center space-x-4">
            <router-link to="/invitation-codes" class="text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </router-link>
            <h2 class="text-2xl font-bold text-gray-900">使用邀请码</h2>
        </div>

        <div class="card p-6 max-w-lg">
            <form @submit.prevent="handleRedeem" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        邀请码 <span class="text-red-500">*</span>
                    </label>
                    <input
                        v-model="code"
                        type="text"
                        class="input text-center text-lg font-mono tracking-widest"
                        :class="{ 'border-red-500': error }"
                        placeholder="请输入邀请码"
                        style="text-transform: uppercase"
                    />
                    <p v-if="error" class="mt-1 text-sm text-red-500">{{ error }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        用户 ID
                    </label>
                    <input
                        v-model.number="userId"
                        type="number"
                        class="input"
                        placeholder="请输入用户ID"
                    />
                    <p class="mt-1 text-xs text-gray-500">邀请码将应用到该用户对应的客户分组</p>
                </div>

                <button type="submit" class="btn btn-primary w-full" :disabled="loading">
                    <span v-if="loading" class="flex items-center justify-center">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        验证中...
                    </span>
                    <span v-else>使用邀请码</span>
                </button>
            </form>
        </div>

        <div v-if="result" class="card p-6 max-w-lg">
            <div class="flex items-center space-x-3 mb-4">
                <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="text-lg font-medium text-gray-900">邀请码使用成功</h3>
            </div>
            <dl class="space-y-3">
                <div class="flex justify-between">
                    <dt class="text-sm text-gray-500">邀请码</dt>
                    <dd class="text-sm font-mono text-indigo-700">{{ result.invitation_code?.code }}</dd>
                </div>
                <div v-if="result.customer_group" class="flex justify-between">
                    <dt class="text-sm text-gray-500">分配到分组</dt>
                    <dd class="text-sm text-gray-900">{{ result.customer_group.name }} ({{ result.customer_group.code }})</dd>
                </div>
            </dl>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useInvitationCodesStore } from '../../stores/invitationCodes';

const store = useInvitationCodesStore();

const code = ref('');
const userId = ref('');
const loading = ref(false);
const error = ref('');
const result = ref(null);

const handleRedeem = async () => {
    error.value = '';
    result.value = null;

    if (!code.value.trim()) {
        error.value = '请输入邀请码';
        return;
    }

    try {
        loading.value = true;
        const data = await store.redeem(code.value.trim().toUpperCase(), userId.value || null);
        result.value = data.data;
        code.value = '';
        userId.value = '';
    } catch (err) {
        error.value = err.response?.data?.message || '邀请码使用失败';
    } finally {
        loading.value = false;
    }
};
</script>
