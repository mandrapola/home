<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MarketItem;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $administratorRole = Role::query()->firstOrCreate([
            'name' => 'administrator',
            'guard_name' => 'web',
        ]);

        $adminPassword = (string) config('market.admin_password', '');

        if ($adminPassword !== '') {
            $admin = User::query()->updateOrCreate(
                ['email' => 'admin@aidvor.ru'],
                [
                    'name' => 'AiDvor Market Administrator',
                    'password' => Hash::make($adminPassword),
                ],
            );

            $admin->assignRole($administratorRole);
        }

        $controllers = Category::query()->updateOrCreate(
            ['slug' => 'controllers'],
            ['name' => 'Контроллеры', 'description' => 'Готовые контроллеры и комплекты для AiDvor.', 'sort_order' => 10, 'is_active' => true],
        );

        $services = Category::query()->updateOrCreate(
            ['slug' => 'services'],
            ['name' => 'Услуги', 'description' => 'Настройка, прошивка, консультации и монтаж.', 'sort_order' => 20, 'is_active' => true],
        );

        $sensors = Category::query()->updateOrCreate(
            ['slug' => 'sensors'],
            ['name' => 'Датчики и модули', 'description' => 'Компоненты для расширения DIY-систем.', 'sort_order' => 30, 'is_active' => true],
        );

        MarketItem::query()->updateOrCreate(
            ['slug' => 'greenhouse-starter-kit'],
            [
                'category_id' => $controllers->id,
                'type' => 'bundle',
                'name' => 'Стартовый комплект теплицы',
                'summary' => 'Arduino Uno + ESP8266 proxy, базовые реле и датчики для удаленного мониторинга теплицы.',
                'description' => "Комплект для быстрого запуска AiDvor в теплице.\nПодходит для тестового стенда, демонстрации и первого рабочего контура.",
                'price_rub' => 14900,
                'stock_quantity' => 3,
                'sort_order' => 10,
                'is_active' => true,
            ],
        );

        MarketItem::query()->updateOrCreate(
            ['slug' => 'esp8266-proxy-setup'],
            [
                'category_id' => $services->id,
                'type' => 'service',
                'name' => 'Настройка ESP8266 proxy',
                'summary' => 'Прошивка, загрузка LittleFS-конфига и проверка связи с home.aidvor.ru.',
                'description' => 'Услуга включает подготовку конфигурации, прошивку и первичную диагностику подключения.',
                'price_rub' => 2500,
                'stock_quantity' => 10,
                'sort_order' => 20,
                'is_active' => true,
            ],
        );

        MarketItem::query()->updateOrCreate(
            ['slug' => 'sensor-pack-basic'],
            [
                'category_id' => $sensors->id,
                'type' => 'product',
                'name' => 'Базовый набор датчиков',
                'summary' => 'Датчики влажности почвы, освещенности и уровня для экспериментов с AiDvor.',
                'description' => 'Подборка компонентов для первого подключения к контроллеру и настройки карточек мониторинга.',
                'price_rub' => 3900,
                'stock_quantity' => 8,
                'sort_order' => 30,
                'is_active' => true,
            ],
        );
    }
}
