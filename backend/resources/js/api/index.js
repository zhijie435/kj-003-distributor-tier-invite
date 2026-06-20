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
};

export default apiClient;
