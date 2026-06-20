<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use App\Models\InvitationCode;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::factory(10)->create();

        $groups = $this->seedCustomerGroups();

        $this->seedInvitationCodes($groups, $users);

        $this->attachUsersToGroups($groups, $users);
    }

    protected function seedCustomerGroups(): array
    {
        $groupConfigs = [
            [
                'name' => '普通经销商',
                'code' => config('invitation-codes.customer_groups.normal', 'NORMAL'),
                'description' => '基础级经销商，享受标准折扣',
                'sort_order' => 1,
                'settings' => [
                    'discount_rate' => 0.95,
                    'min_order_amount' => 1000,
                ],
            ],
            [
                'name' => '银牌经销商',
                'code' => config('invitation-codes.customer_groups.silver', 'SILVER'),
                'description' => '进阶级经销商，享受银牌折扣和优先服务',
                'sort_order' => 2,
                'settings' => [
                    'discount_rate' => 0.88,
                    'min_order_amount' => 5000,
                ],
            ],
            [
                'name' => '金牌经销商',
                'code' => config('invitation-codes.customer_groups.gold', 'GOLD'),
                'description' => '高级经销商，享受金牌折扣和专属服务',
                'sort_order' => 3,
                'settings' => [
                    'discount_rate' => 0.80,
                    'min_order_amount' => 20000,
                ],
            ],
            [
                'name' => '钻石经销商',
                'code' => config('invitation-codes.customer_groups.diamond', 'DIAMOND'),
                'description' => '顶级经销商，享受钻石折扣和VIP专属服务',
                'sort_order' => 4,
                'settings' => [
                    'discount_rate' => 0.70,
                    'min_order_amount' => 100000,
                ],
            ],
        ];

        $groups = [];
        foreach ($groupConfigs as $config) {
            $groups[$config['code']] = CustomerGroup::firstOrCreate(
                ['code' => $config['code']],
                $config
            );
        }

        $this->command->info('客户分组种子数据创建完成：'.count($groups).' 个分组');

        return $groups;
    }

    protected function seedInvitationCodes(array $groups, $users): void
    {
        $count = 0;

        foreach ($groups as $code => $group) {
            InvitationCode::create([
                'customer_group_id' => $group->id,
                'code' => strtoupper($code).'2026',
                'description' => "{$group->name}通用邀请码",
                'max_uses' => 0,
                'is_active' => true,
            ]);
            $count++;

            InvitationCode::factory()
                ->forGroup($group)
                ->limitedUses(100)
                ->notExpired()
                ->count(3)
                ->create();
            $count += 3;

            InvitationCode::factory()
                ->forGroup($group)
                ->limitedUses(10)
                ->count(2)
                ->create();
            $count += 2;
        }

        $this->command->info('邀请码种子数据创建完成：'.$count.' 个邀请码');
    }

    protected function attachUsersToGroups(array $groups, $users): void
    {
        if ($users->isEmpty()) {
            return;
        }

        $normalGroup = $groups[config('invitation-codes.customer_groups.normal', 'NORMAL')] ?? null;
        $silverGroup = $groups[config('invitation-codes.customer_groups.silver', 'SILVER')] ?? null;
        $goldGroup = $groups[config('invitation-codes.customer_groups.gold', 'GOLD')] ?? null;

        $userArray = $users->all();

        if ($normalGroup) {
            $normalUsers = array_slice($userArray, 0, 5);
            $normalGroup->models()->syncWithoutDetaching(
                collect($normalUsers)->pluck('id')->toArray()
            );
        }

        if ($silverGroup) {
            $silverUsers = array_slice($userArray, 5, 3);
            $silverGroup->models()->syncWithoutDetaching(
                collect($silverUsers)->pluck('id')->toArray()
            );
        }

        if ($goldGroup && count($userArray) > 8) {
            $goldUsers = array_slice($userArray, 8, 2);
            $goldGroup->models()->syncWithoutDetaching(
                collect($goldUsers)->pluck('id')->toArray()
            );
        }

        $this->command->info('用户关联客户分组完成');
    }
}
