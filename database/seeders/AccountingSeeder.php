<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class AccountingSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $accounts = [
            ['code' => '1101', 'name' => 'Caja y bancos', 'type' => 'asset', 'key' => 'cash'],
            ['code' => '1102', 'name' => 'Cuentas por cobrar', 'type' => 'asset', 'key' => 'accounts_receivable'],
            ['code' => '1103', 'name' => 'Inventarios', 'type' => 'asset', 'key' => 'inventory'],
            ['code' => '2101', 'name' => 'Impuesto sobre ventas por pagar', 'type' => 'liability', 'key' => 'sales_tax_payable'],
            ['code' => '3101', 'name' => 'Capital', 'type' => 'equity', 'key' => null],
            ['code' => '4101', 'name' => 'Ingresos por ventas', 'type' => 'revenue', 'key' => 'sales_revenue'],
            ['code' => '5101', 'name' => 'Costo de ventas', 'type' => 'expense', 'key' => 'cost_of_goods_sold'],
            ['code' => '5201', 'name' => 'Gastos operativos', 'type' => 'expense', 'key' => null],
        ];
        foreach ($accounts as $account) {
            DB::table('accounting_accounts')->updateOrInsert(['code' => $account['code']], [
                'name' => $account['name'], 'type' => $account['type'], 'parent_id' => null,
                'accepts_entries' => true, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            if ($account['key'] !== null) {
                $id = DB::table('accounting_accounts')->where('code', $account['code'])->value('id');
                DB::table('accounting_settings')->updateOrInsert(['key' => $account['key']], ['account_id' => $id, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $date = now();
        DB::table('accounting_periods')->updateOrInsert(['year' => $date->year, 'month' => $date->month], [
            'starts_on' => $date->copy()->startOfMonth()->toDateString(), 'ends_on' => $date->copy()->endOfMonth()->toDateString(),
            'status' => 'open', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
