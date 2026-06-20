import axios from 'axios';

const apiClient = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    withCredentials: true,
});

apiClient.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 401) {
            console.error('未授权，请重新登录');
        }
        return Promise.reject(error);
    }
);

export const customerGroupsApi = {
    all: () => apiClient.get('/customer-groups/all'),
    index: (params = {}) => apiClient.get('/customer-groups', { params }),
    show: (id) => apiClient.get(`/customer-groups/${id}`),
    store: (data) => apiClient.post('/customer-groups', data),
    update: (id, data) => apiClient.put(`/customer-groups/${id}`, data),
    destroy: (id) => apiClient.delete(`/customer-groups/${id}`),
    toggleActive: (id) => apiClient.patch(`/customer-groups/${id}/toggle-active`),
    attachUsers: (id, userIds) => apiClient.post(`/customer-groups/${id}/attach-users`, { user_ids: userIds }),
    detachUsers: (id, userIds) => apiClient.post(`/customer-groups/${id}/detach-users`, { user_ids: userIds }),
    invitationCodes: (id, params = {}) => apiClient.get(`/customer-groups/${id}/invitation-codes`, { params }),
};

export const invitationCodesApi = {
    index: (params = {}) => apiClient.get('/invitation-codes', { params }),
    show: (id) => apiClient.get(`/invitation-codes/${id}`),
    store: (data) => apiClient.post('/invitation-codes', data),
    update: (id, data) => apiClient.put(`/invitation-codes/${id}`, data),
    destroy: (id) => apiClient.delete(`/invitation-codes/${id}`),
    toggleActive: (id) => apiClient.patch(`/invitation-codes/${id}/toggle-active`),
    batchGenerate: (data) => apiClient.post('/invitation-codes/batch-generate', data),
    redeem: (code, userId) => apiClient.post('/invitation-codes/redeem', { code, user_id: userId }),
};

export default apiClient;
