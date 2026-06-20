import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/',
        redirect: '/customer-groups',
    },
    {
        path: '/customer-groups',
        name: 'CustomerGroups',
        component: () => import('../pages/CustomerGroups/Index.vue'),
    },
    {
        path: '/customer-groups/create',
        name: 'CustomerGroupCreate',
        component: () => import('../pages/CustomerGroups/Create.vue'),
    },
    {
        path: '/customer-groups/:id/edit',
        name: 'CustomerGroupEdit',
        component: () => import('../pages/CustomerGroups/Edit.vue'),
    },
    {
        path: '/customer-groups/:id',
        name: 'CustomerGroupShow',
        component: () => import('../pages/CustomerGroups/Show.vue'),
    },
];

const router = createRouter({
    history: createWebHistory('/customer-groups'),
    routes,
});

export default router;
