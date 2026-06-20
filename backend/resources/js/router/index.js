import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/',
        redirect: '/',
    },
    {
        path: '/',
        name: 'CustomerGroups',
        component: () => import('../pages/CustomerGroups/Index.vue'),
    },
    {
        path: '/create',
        name: 'CustomerGroupCreate',
        component: () => import('../pages/CustomerGroups/Create.vue'),
    },
    {
        path: '/:id/edit',
        name: 'CustomerGroupEdit',
        component: () => import('../pages/CustomerGroups/Edit.vue'),
    },
    {
        path: '/:id',
        name: 'CustomerGroupShow',
        component: () => import('../pages/CustomerGroups/Show.vue'),
    },
];

const router = createRouter({
    history: createWebHistory('/customer-groups'),
    routes,
});

export default router;
