<template>
    <div class="space-y-6">
        <div class="flex items-center space-x-4">
            <router-link to="/invitation-codes" class="text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </router-link>
            <h2 class="text-2xl font-bold text-gray-900">批量生成邀请码</h2>
        </div>

        <div class="card p-6 max-w-3xl">
            <form @submit.prevent="handleSubmit" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        关联客户分组 <span class="text-red-500">*</span>
                    </label>
                    <select
                        v-model="form.customer_group_id"
                        class="input"
                        :class="{ 'border-red-500': errors.customer_group_id }"
                    >
                        <option value="">请选择客户分组</option>
                        <option v-for="group in customerGroups" :key="group.id" :value="group.id">
                            {{ group.name }} ({{ group.code }})
                        </option>
                    </select>
                    <p v-if="errors.customer_group_id" class="mt-1 text-sm text-red-500">{{ errors.customer_group_id }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            生成数量 <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model.number="form.count"
                            type="number"
                            min="1"
                            max="100"
                            class="input"
                            placeholder="1-100"
                        />
                        <p v-if="errors.count" class="mt-1 text-sm text-red-500">{{ errors.count }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            邀请码长度
                        </label>
                        <input
                            v-model.number="form.code_length"
                            type="number"
                            min="4"
                            max="20"
                            class="input"
                            placeholder="默认8位"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        描述（所有邀请码共用）
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="2"
                        class="input"
                        placeholder="请输入邀请码描述"
                    ></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            每个邀请码最大使用次数
                        </label>
                        <input
                            v-model.number="form.max_uses"
                            type="number"
                            min="0"
                            class="input"
                            placeholder="0 表示不限制"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            过期时间
                        </label>
                        <input
                            v-model="form.expires_at"
                            type="datetime-local"
                            class="input"
                        />
                        <p v-if="errors.expires_at" class="mt-1 text-sm text-red-500">{{ errors.expires_at }}</p>
                    </div>
                </div>

                <div class="flex justify-end space-x-4 pt-4 border-t border-gray-200">
                    <router-link to="/invitation-codes" class="btn btn-secondary">
                        取消
                    </router-link>
                    <button type="submit" class="btn btn-primary" :disabled="loading">
                        <span v-if="loading" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            生成中...
                        </span>
                        <span v-else>批量生成</span>
                    </button>
                </div>
            </form>
        </div>

        <div v-if="generatedCodes.length > 0" class="card p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">生成结果</h3>
                <button @click="copyAllCodes" class="btn btn-secondary text-sm">
                    复制全部邀请码
                </button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                <div
                    v-for="code in generatedCodes"
                    :key="code.id"
                    class="flex items-center justify-between p-3 bg-indigo-50 rounded-lg border border-indigo-100"
                >
                    <code class="text-sm font-mono text-indigo-700">{{ code.code }}</code>
                    <button @click="copyCode(code.code)" class="text-indigo-500 hover:text-indigo-700 ml-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="message" class="fixed bottom-4 right-4 z-50 text-white px-6 py-3 rounded-lg shadow-lg" :class="message.type === 'success' ? 'bg-emerald-500' : 'bg-red-500'">
            {{ message.text }}
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useInvitationCodesStore } from '../../stores/invitationCodes';
import { useCustomerGroupsStore } from '../../stores/customerGroups';

const router = useRouter();
const store = useInvitationCodesStore();
const customerGroupsStore = useCustomerGroupsStore();

const loading = ref(false);
const errors = reactive({});
const customerGroups = ref([]);
const generatedCodes = ref([]);
const message = ref(null);

const form = reactive({
    customer_group_id: '',
    count: 10,
    code_length: 8,
    description: '',
    max_uses: 0,
    expires_at: '',
});

const handleSubmit = async () => {
    errors.customer_group_id = '';
    errors.count = '';

    if (!form.customer_group_id) {
        errors.customer_group_id = '请选择客户分组';
        return;
    }

    if (!form.count || form.count < 1) {
        errors.count = '请输入生成数量';
        return;
    }

    try {
        loading.value = true;
        const data = { ...form };
        if (!data.expires_at) delete data.expires_at;
        if (!data.description) delete data.description;
        const result = await store.batchGenerate(data);
        generatedCodes.value = result;
        showMessage(`成功生成 ${result.length} 个邀请码`, 'success');
    } catch (error) {
        if (error.response?.data?.errors) {
            Object.assign(errors, error.response.data.errors);
        }
        showMessage('批量生成失败', 'error');
    } finally {
        loading.value = false;
    }
};

const copyCode = async (code) => {
    try {
        await navigator.clipboard.writeText(code);
        showMessage('已复制到剪贴板', 'success');
    } catch {
        showMessage('复制失败', 'error');
    }
};

const copyAllCodes = async () => {
    try {
        const text = generatedCodes.value.map(c => c.code).join('\n');
        await navigator.clipboard.writeText(text);
        showMessage('已复制全部邀请码', 'success');
    } catch {
        showMessage('复制失败', 'error');
    }
};

const showMessage = (text, type) => {
    message.value = { text, type };
    setTimeout(() => {
        message.value = null;
    }, 3000);
};

onMounted(async () => {
    await customerGroupsStore.fetchAll();
    customerGroups.value = customerGroupsStore.allItems;
});
</script>
