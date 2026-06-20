<template>
    <div class="space-y-6">
        <div class="flex items-center space-x-4">
            <router-link to="/" class="text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </router-link>
            <h2 class="text-2xl font-bold text-gray-900">编辑客户分组</h2>
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
        </div>

        <div v-else-if="store.currentItem" class="card p-6 max-w-3xl">
            <form @submit.prevent="handleSubmit" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            分组名称 <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="input"
                            :class="{ 'border-red-500': errors.name }"
                            placeholder="请输入分组名称"
                        />
                        <p v-if="errors.name" class="mt-1 text-sm text-red-500">{{ errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            分组代码 <span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.code"
                            type="text"
                            class="input"
                            :class="{ 'border-red-500': errors.code }"
                            placeholder="请输入分组代码（英文唯一标识）"
                        />
                        <p v-if="errors.code" class="mt-1 text-sm text-red-500">{{ errors.code }}</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        描述
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="input"
                        placeholder="请输入分组描述"
                    ></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            排序
                        </label>
                        <input
                            v-model.number="form.sort_order"
                            type="number"
                            min="0"
                            class="input"
                            placeholder="数字越小越靠前"
                        />
                    </div>

                    <div class="flex items-center pt-8">
                        <label class="flex items-center space-x-3 cursor-pointer">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-gray-300 text-primary focus:ring-primary h-5 w-5"
                            />
                            <span class="text-sm text-gray-700">启用此分组</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        配置参数
                    </label>
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-sm text-gray-600">自定义设置（JSON 格式）</span>
                            <button type="button" @click="addSetting" class="text-sm text-primary hover:text-primary-hover">
                                + 添加参数
                            </button>
                        </div>
                        <div class="space-y-3">
                            <div v-for="(setting, index) in settings" :key="index" class="flex items-center space-x-3">
                                <input
                                    v-model="setting.key"
                                    type="text"
                                    class="input flex-1"
                                    placeholder="参数名"
                                />
                                <input
                                    v-model="setting.value"
                                    type="text"
                                    class="input flex-1"
                                    placeholder="参数值"
                                />
                                <button
                                    type="button"
                                    @click="removeSetting(index)"
                                    class="text-red-500 hover:text-red-700 p-1"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <p v-if="settings.length === 0" class="text-sm text-gray-400 text-center py-4">
                                暂无配置参数
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-4 pt-4 border-t border-gray-200">
                    <router-link to="/" class="btn btn-secondary">
                        取消
                    </router-link>
                    <button type="submit" class="btn btn-primary" :disabled="submitting">
                        <span v-if="submitting" class="flex items-center">
                            <svg class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            保存中...
                        </span>
                        <span v-else>保存修改</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCustomerGroupsStore } from '../../stores/customerGroups';

const route = useRoute();
const router = useRouter();
const store = useCustomerGroupsStore();

const loading = ref(true);
const submitting = ref(false);
const errors = reactive({});

const form = reactive({
    name: '',
    code: '',
    description: '',
    sort_order: 0,
    is_active: true,
});

const settings = ref([]);

const addSetting = () => {
    settings.value.push({ key: '', value: '' });
};

const removeSetting = (index) => {
    settings.value.splice(index, 1);
};

const getSettingsJson = () => {
    const obj = {};
    settings.value.forEach(s => {
        if (s.key.trim()) {
            obj[s.key.trim()] = s.value;
        }
    });
    return Object.keys(obj).length > 0 ? obj : null;
};

const loadFormData = () => {
    const item = store.currentItem;
    if (item) {
        form.name = item.name;
        form.code = item.code;
        form.description = item.description || '';
        form.sort_order = item.sort_order;
        form.is_active = item.is_active;

        settings.value = [];
        if (item.settings && typeof item.settings === 'object') {
            Object.entries(item.settings).forEach(([key, value]) => {
                settings.value.push({ key, value: String(value) });
            });
        }
    }
};

const validate = () => {
    errors.name = '';
    errors.code = '';

    if (!form.name.trim()) {
        errors.name = '请输入分组名称';
    }
    if (!form.code.trim()) {
        errors.code = '请输入分组代码';
    }

    return !errors.name && !errors.code;
};

const handleSubmit = async () => {
    if (!validate()) return;

    try {
        submitting.value = true;
        const data = {
            ...form,
            settings: getSettingsJson(),
        };
        await store.update(route.params.id, data);
        router.push('/');
    } catch (error) {
        if (error.response?.data?.errors) {
            Object.assign(errors, error.response.data.errors);
        }
    } finally {
        submitting.value = false;
    }
};

onMounted(async () => {
    try {
        await store.fetchDetail(route.params.id);
        loadFormData();
    } catch (error) {
        router.push('/');
    } finally {
        loading.value = false;
    }
});
</script>
