<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Concerns\EnsuresLocalDemoEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use EnsuresLocalDemoEnvironment;

    /**
     * Documented demo login accounts plus 50 realistic fictional Cambodian
     * customer/seller accounts for local and testing environments.
     */
    public function run(): void
    {
        $this->ensureLocalOrTestingEnvironment('Demo user accounts may only be seeded in local or testing environments.');

        $passwordHash = Hash::make('password');

        $coreAccounts = [
            ['name' => 'Super Admin', 'email' => 'admin@ekhmer.com', 'phone' => '+855 10 000 001', 'role' => User::ROLE_SUPER_ADMIN, 'newsletter' => false],
            ['name' => 'Demo Customer', 'email' => 'customer@ekhmer.com', 'phone' => '+855 10 000 002', 'role' => User::ROLE_CUSTOMER, 'newsletter' => true],
            ['name' => 'Demo Seller', 'email' => 'seller@ekhmer.com', 'phone' => '+855 10 000 003', 'role' => User::ROLE_CUSTOMER, 'newsletter' => false],
        ];

        $shopManagers = [
            ['name' => 'Vanna Heng', 'email' => 'owner.mekong@ekhmer.test', 'phone' => '+855 12 910 101'],
            ['name' => 'Sophal Nhem', 'email' => 'owner.champa@ekhmer.test', 'phone' => '+855 12 910 102'],
            ['name' => 'Ratanak Sok', 'email' => 'owner.bamboo@ekhmer.test', 'phone' => '+855 12 910 103'],
            ['name' => 'Sreyneang Ly', 'email' => 'owner.heritage@ekhmer.test', 'phone' => '+855 12 910 104'],
            ['name' => 'Kosal Chhorn', 'email' => 'owner.sovanna@ekhmer.test', 'phone' => '+855 12 910 105'],
            ['name' => 'Bopha Keo', 'email' => 'owner.bbshoes@ekhmer.test', 'phone' => '+855 12 910 106'],
            ['name' => 'Maly Touch', 'email' => 'owner.tonle@ekhmer.test', 'phone' => '+855 12 910 107'],
            ['name' => 'Pisal Noun', 'email' => 'owner.sensok@ekhmer.test', 'phone' => '+855 12 910 108'],
            ['name' => 'Socheata Ros', 'email' => 'owner.pannha@ekhmer.test', 'phone' => '+855 12 910 109'],
            ['name' => 'Visal Khim', 'email' => 'owner.takeo@ekhmer.test', 'phone' => '+855 12 910 110'],
            ['name' => 'Chanthou Prum', 'email' => 'owner.kcham@ekhmer.test', 'phone' => '+855 12 910 111'],
            ['name' => 'Narith Kong', 'email' => 'owner.snv@ekhmer.test', 'phone' => '+855 12 910 112'],
        ];

        $cambodianCustomers = [
            ['Sokha Chea', 'sokha.chea@ekhmer.test', '+855 10 901 001'],
            ['Dara Meas', 'dara.meas@ekhmer.test', '+855 10 901 002'],
            ['Bopha Phan', 'bopha.phan@ekhmer.test', '+855 10 901 003'],
            ['Visal Chhim', 'visal.chhim@ekhmer.test', '+855 10 901 004'],
            ['Chantha Keo', 'chantha.keo@ekhmer.test', '+855 10 901 005'],
            ['Sovannaroath Lim', 'sovannaroath.lim@ekhmer.test', '+855 10 901 006'],
            ['Malis Ouk', 'malis.ouk@ekhmer.test', '+855 10 901 007'],
            ['Pisey Seng', 'pisey.seng@ekhmer.test', '+855 10 901 008'],
            ['Rithy Khun', 'rithy.khun@ekhmer.test', '+855 10 901 009'],
            ['Champa Mao', 'champa.mao@ekhmer.test', '+855 10 901 010'],
            ['Kosal Prak', 'kosal.prak@ekhmer.test', '+855 10 901 011'],
            ['Kunthea Suon', 'kunthea.suon@ekhmer.test', '+855 10 901 012'],
            ['Makara Yim', 'makara.yim@ekhmer.test', '+855 10 901 013'],
            ['Neary Pich', 'neary.pich@ekhmer.test', '+855 10 901 014'],
            ['Pheap Sam', 'pheap.sam@ekhmer.test', '+855 10 901 015'],
            ['Rathana Sun', 'rathana.sun@ekhmer.test', '+855 10 901 016'],
            ['Sitha Ngov', 'sitha.ngov@ekhmer.test', '+855 10 901 017'],
            ['Sophea Um', 'sophea.um@ekhmer.test', '+855 10 901 018'],
            ['Thida Vong', 'thida.vong@ekhmer.test', '+855 10 901 019'],
            ['Vibol Yin', 'vibol.yin@ekhmer.test', '+855 10 901 020'],
            ['Kanha Bou', 'kanha.bou@ekhmer.test', '+855 16 902 021'],
            ['Narith Tep', 'narith.tep@ekhmer.test', '+855 16 902 022'],
            ['Rachana Kheng', 'rachana.kheng@ekhmer.test', '+855 16 902 023'],
            ['Bunthoeun Sim', 'bunthoeun.sim@ekhmer.test', '+855 16 902 024'],
            ['Sreyrath Chheng', 'sreyrath.chheng@ekhmer.test', '+855 16 902 025'],
            ['Kimsan Hor', 'kimsan.hor@ekhmer.test', '+855 16 902 026'],
            ['Leakhena Mean', 'leakhena.mean@ekhmer.test', '+855 16 902 027'],
            ['Monyreth Kao', 'monyreth.kao@ekhmer.test', '+855 16 902 028'],
            ['Phalla So', 'phalla.so@ekhmer.test', '+855 16 902 029'],
            ['Sopheaktra Duong', 'sopheaktra.duong@ekhmer.test', '+855 16 902 030'],
            ['Chenda Nhim', 'chenda.nhim@ekhmer.test', '+855 77 903 031'],
            ['Davit Leng', 'davit.leng@ekhmer.test', '+855 77 903 032'],
            ['Kalyan Tan', 'kalyan.tan@ekhmer.test', '+855 77 903 033'],
            ['Lina Ung', 'lina.ung@ekhmer.test', '+855 77 903 034'],
            ['Nita Huot', 'nita.huot@ekhmer.test', '+855 77 903 035'],
            ['Oudom Men', 'oudom.men@ekhmer.test', '+855 77 903 036'],
            ['Panha Long', 'panha.long@ekhmer.test', '+855 77 903 037'],
            ['Reaksmey Chum', 'reaksmey.chum@ekhmer.test', '+855 77 903 038'],
            ['Samnang Try', 'samnang.try@ekhmer.test', '+855 77 903 039'],
            ['Sreypov Eam', 'sreypov.eam@ekhmer.test', '+855 77 903 040'],
            ['Theara Krouch', 'theara.krouch@ekhmer.test', '+855 96 904 041'],
            ['Vannak Chay', 'vannak.chay@ekhmer.test', '+855 96 904 042'],
            ['Vicheka Srun', 'vicheka.srun@ekhmer.test', '+855 96 904 043'],
            ['Sovathana Pech', 'sovathana.pech@ekhmer.test', '+855 96 904 044'],
            ['Serey Mom', 'serey.mom@ekhmer.test', '+855 96 904 045'],
            ['Pichmony Loeung', 'pichmony.loeung@ekhmer.test', '+855 96 904 046'],
            ['Rathborith Touch', 'rathborith.touch@ekhmer.test', '+855 96 904 047'],
            ['Chhorvy Phin', 'chhorvy.phin@ekhmer.test', '+855 96 904 048'],
            ['Kosalith Sar', 'kosalith.sar@ekhmer.test', '+855 96 904 049'],
        ];

        $all = $coreAccounts;
        foreach ($shopManagers as $manager) {
            $all[] = $manager + ['role' => User::ROLE_CUSTOMER, 'newsletter' => false];
        }
        foreach ($cambodianCustomers as $idx => [$name, $email, $phone]) {
            $all[] = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'role' => User::ROLE_CUSTOMER,
                'newsletter' => $idx % 3 === 0,
            ];
        }

        foreach ($all as $account) {
            $user = User::firstOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'phone' => $account['phone'],
                    'password' => $passwordHash,
                    'newsletter' => $account['newsletter'],
                ]
            );

            if ($user->role === null || $user->email_verified_at === null) {
                $user->forceFill([
                    'role' => $account['role'],
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
            }
        }
    }
}
