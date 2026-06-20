import { createRouter, createWebHistory } from 'vue-router';

const routes = [
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
        path: '/invitation-codes',
        name: 'InvitationCodes',
        component: () => import('../pages/InvitationCodes/Index.vue'),
    },
    {
        path: '/invitation-codes/create',
        name: 'InvitationCodeCreate',
        component: () => import('../pages/InvitationCodes/Create.vue'),
    },
    {
        path: '/invitation-codes/batch-generate',
        name: 'InvitationCodeBatchGenerate',
        component: () => import('../pages/InvitationCodes/BatchGenerate.vue'),
    },
    {
        path: '/invitation-codes/redeem',
        name: 'InvitationCodeRedeem',
        component: () => import('../pages/InvitationCodes/Redeem.vue'),
    },
    {
        path: '/invitation-codes/:id',
        name: 'InvitationCodeShow',
        component: () => import('../pages/InvitationCodes/Show.vue'),
    },
    {
        path: '/invitation-codes/:id/edit',
        name: 'InvitationCodeEdit',
        component: () => import('../pages/InvitationCodes/Edit.vue'),
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
