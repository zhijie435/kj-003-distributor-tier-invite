import { defineStore } from 'pinia';
import { invitationCodesApi } from '../api';

export const useInvitationCodesStore = defineStore('invitationCodes', {
    state: () => ({
        items: [],
        pagination: {
            total: 0,
            per_page: 15,
            current_page: 1,
            last_page: 1,
        },
        loading: false,
        currentItem: null,
        search: '',
        activeOnly: false,
        customerGroupId: null,
    }),

    actions: {
        async fetchList(params = {}) {
            try {
                this.loading = true;
                const queryParams = {
                    page: this.pagination.current_page,
                    per_page: this.pagination.per_page,
                    search: this.search || undefined,
                    active: this.activeOnly || undefined,
                    customer_group_id: this.customerGroupId || undefined,
                    ...params,
                };
                const response = await invitationCodesApi.index(queryParams);
                this.items = response.data.data;
                this.pagination = response.data.pagination;
            } catch (error) {
                console.error('获取邀请码列表失败:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchDetail(id) {
            try {
                this.loading = true;
                const response = await invitationCodesApi.show(id);
                this.currentItem = response.data.data;
                return response.data.data;
            } catch (error) {
                console.error('获取邀请码详情失败:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async create(data) {
            try {
                this.loading = true;
                const response = await invitationCodesApi.store(data);
                await this.fetchList();
                return response.data.data;
            } catch (error) {
                console.error('创建邀请码失败:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async update(id, data) {
            try {
                this.loading = true;
                const response = await invitationCodesApi.update(id, data);
                const updated = response.data.data;
                this._syncItemInList(updated);
                if (this.currentItem && this.currentItem.id === id) {
                    this.currentItem = { ...this.currentItem, ...updated };
                }
                await this.fetchList();
                return updated;
            } catch (error) {
                console.error('更新邀请码失败:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async delete(id) {
            try {
                this.loading = true;
                await invitationCodesApi.destroy(id);
                this._removeItemFromList(id);
                if (this.currentItem && this.currentItem.id === id) {
                    this.currentItem = null;
                }
                await this.fetchList();
            } catch (error) {
                console.error('删除邀请码失败:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async toggleActive(id) {
            try {
                const response = await invitationCodesApi.toggleActive(id);
                const updated = response.data.data;
                this._syncItemInList(updated);
                if (this.currentItem && this.currentItem.id === id) {
                    this.currentItem = { ...this.currentItem, ...updated };
                }
                return updated;
            } catch (error) {
                console.error('切换状态失败:', error);
                throw error;
            }
        },

        async batchGenerate(data) {
            try {
                this.loading = true;
                const response = await invitationCodesApi.batchGenerate(data);
                await this.fetchList();
                return response.data.data;
            } catch (error) {
                console.error('批量生成邀请码失败:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async redeem(code, userId) {
            try {
                const response = await invitationCodesApi.redeem(code, userId);
                return response.data;
            } catch (error) {
                console.error('使用邀请码失败:', error);
                throw error;
            }
        },

        setPage(page) {
            this.pagination.current_page = page;
            this.fetchList();
        },

        setSearch(search) {
            this.search = search;
            this.pagination.current_page = 1;
            this.fetchList();
        },

        setActiveOnly(value) {
            this.activeOnly = value;
            this.pagination.current_page = 1;
            this.fetchList();
        },

        setCustomerGroupId(id) {
            this.customerGroupId = id;
            this.pagination.current_page = 1;
            this.fetchList();
        },

        resetCurrentItem() {
            this.currentItem = null;
        },

        _syncItemInList(updated) {
            if (!updated || !updated.id) return;
            const index = this.items.findIndex(item => item.id === updated.id);
            if (index !== -1) {
                this.items[index] = { ...this.items[index], ...updated };
            }
        },

        _removeItemFromList(id) {
            const index = this.items.findIndex(item => item.id === id);
            if (index !== -1) {
                this.items.splice(index, 1);
            }
        },
    },
});
