import { defineStore } from 'pinia';
import { customerGroupsApi } from '../api';

export const useCustomerGroupsStore = defineStore('customerGroups', {
    state: () => ({
        items: [],
        allItems: [],
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
    }),

    actions: {
        async fetchAll() {
            try {
                this.loading = true;
                const response = await customerGroupsApi.all();
                this.allItems = response.data.data;
            } catch (error) {
                console.error('获取所有客户分组失败:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchList(params = {}) {
            try {
                this.loading = true;
                const queryParams = {
                    page: this.pagination.current_page,
                    per_page: this.pagination.per_page,
                    search: this.search || undefined,
                    active: this.activeOnly || undefined,
                    ...params,
                };
                const response = await customerGroupsApi.index(queryParams);
                this.items = response.data.data;
                this.pagination = response.data.pagination;
            } catch (error) {
                console.error('获取客户分组列表失败:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async fetchDetail(id) {
            try {
                this.loading = true;
                const response = await customerGroupsApi.show(id);
                this.currentItem = response.data.data;
                this._syncItemInList(response.data.data);
                return response.data.data;
            } catch (error) {
                console.error('获取客户分组详情失败:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async create(data) {
            try {
                this.loading = true;
                const response = await customerGroupsApi.store(data);
                await Promise.all([
                    this.fetchList(),
                    this._refreshAllItems(),
                ]);
                return response.data.data;
            } catch (error) {
                console.error('创建客户分组失败:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async update(id, data) {
            try {
                this.loading = true;
                const response = await customerGroupsApi.update(id, data);
                const updated = response.data.data;
                this._syncItemInList(updated);
                if (this.currentItem && this.currentItem.id === id) {
                    this.currentItem = { ...this.currentItem, ...updated };
                }
                await Promise.all([
                    this.fetchList(),
                    this._refreshAllItems(),
                ]);
                return updated;
            } catch (error) {
                console.error('更新客户分组失败:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async delete(id) {
            try {
                this.loading = true;
                await customerGroupsApi.destroy(id);
                this._removeItemFromList(id);
                if (this.currentItem && this.currentItem.id === id) {
                    this.currentItem = null;
                }
                this._removeItemFromAll(id);
                await this.fetchList();
            } catch (error) {
                console.error('删除客户分组失败:', error);
                throw error;
            } finally {
                this.loading = false;
            }
        },

        async toggleActive(id) {
            try {
                const response = await customerGroupsApi.toggleActive(id);
                const updated = response.data.data;
                this._syncItemInList(updated);
                if (this.currentItem && this.currentItem.id === id) {
                    this.currentItem = { ...this.currentItem, ...updated };
                }
                this._syncItemInAll(updated);
                return updated;
            } catch (error) {
                console.error('切换状态失败:', error);
                throw error;
            }
        },

        async attachUsers(id, userIds) {
            try {
                const response = await customerGroupsApi.attachUsers(id, userIds);
                this.currentItem = response.data.data;
                return response.data.data;
            } catch (error) {
                console.error('添加用户失败:', error);
                throw error;
            }
        },

        async detachUsers(id, userIds) {
            try {
                const response = await customerGroupsApi.detachUsers(id, userIds);
                this.currentItem = response.data.data;
                return response.data.data;
            } catch (error) {
                console.error('移除用户失败:', error);
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

        _syncItemInAll(updated) {
            if (!updated || !updated.id) return;
            const index = this.allItems.findIndex(item => item.id === updated.id);
            if (index !== -1) {
                this.allItems[index] = { ...this.allItems[index], ...updated };
            } else if (updated.is_active) {
                this.allItems.push(updated);
            }
        },

        _removeItemFromAll(id) {
            const index = this.allItems.findIndex(item => item.id === id);
            if (index !== -1) {
                this.allItems.splice(index, 1);
            }
        },

        async _refreshAllItems() {
            try {
                const response = await customerGroupsApi.all();
                this.allItems = response.data.data;
            } catch {
                //
            }
        },
    },
});
