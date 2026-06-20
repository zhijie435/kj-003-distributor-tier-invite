<template>
    <div class="space-y-6">
        <div class="flex items-center space-x-4">
            <router-link to="/invitation-codes" class="text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </router-link>
            <h2 class="text-2xl font-bold text-gray-900">创建邀请码</h2>
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

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        邀请码（留空自动生成）
                    </label>
                    <input
                        v-model="form.code"
                        type="text"
                        class="input"
                        :class="{ 'border-red-500': errors.code }"
                        placeholder="留空将自动生成8位邀请码"
                    />
                    <p v-if="errors.code" class="mt-1 text-sm text-red-500">{{ errors.code }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        描述
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="input"
                        placeholder="请输入邀请码描述"
                    ></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            最大使用次数
                        </label>
                        <input
                            v-model.number="form.max_uses"
                            type="number"
                            min="0"
                            class="input"
                            placeholder="0 表示不限制"
                        />
                        <p class="mt-1 text-xs text-gray-500">设为 0 表示不限制使用次数</p>
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
                        <p class="mt-1 text-xs text-gray-500">留空表示永不过期</p>
                        <p v-if="errors.expires_at" class="mt-1 text-sm text-red-500">{{ errors.expires_at }}</p>
                    </div>
                </div>

                <div class="flex items-center">
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="rounded border-gray-300 text-primary focus:ring-primary h-5 w-5"
                        />
                        <span class="text-sm text-gray-700">创建后立即启用</span>
                    </label>
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
                            创建中...
                        </span>
                        <span v-else>创建邀请码</span>
                    </button>
                </div>
            </form>
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

const form = reactive({
    customer_group_id: '',
    code: '',
    description: '',
    max_uses: 0,
    expires_at: '',
    is_active: true,
});

const handleSubmit = async () => {
    errors.customer_group_id = '';
    errors.code = '';
    errors.expires_at = '';

    if (!form.customer_group_id) {
        errors.customer_group_id = '请选择客户分组';
        return;
    }

    try {
        loading.value = true;
        const data = { ...form };
        if (!data.code.trim()) delete data.code;
        if (!data.expires_at) delete data.expires_at;
        await store.create(data);
        router.push('/invitation-codes');
    } catch (error) {
        if (error.response?.data?.errors) {
            Object.assign(errors, error.response.data.errors);
        }
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    await customerGroupsStore.fetchAll();
    customerGroups.value = customerGroupsStore.allItems;
});
</script>
