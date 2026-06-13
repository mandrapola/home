<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inquiry;
use App\Models\MarketItem;
use App\Models\Order;
use App\Models\TelegramConversation;
use App\Models\User;
use App\Models\VkConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MarketFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_displays_active_items(): void
    {
        $category = Category::query()->create([
            'name' => 'Контроллеры',
            'slug' => 'controllers',
            'is_active' => true,
        ]);

        MarketItem::query()->create([
            'category_id' => $category->id,
            'type' => 'product',
            'name' => 'AiDvor controller',
            'slug' => 'aidvor-controller',
            'summary' => 'Ready to connect controller.',
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('AiDvor controller');
    }

    public function test_customer_can_send_inquiry(): void
    {
        $category = Category::query()->create([
            'name' => 'Услуги',
            'slug' => 'services',
            'is_active' => true,
        ]);

        $item = MarketItem::query()->create([
            'category_id' => $category->id,
            'type' => 'service',
            'name' => 'Setup service',
            'slug' => 'setup-service',
            'summary' => 'Controller setup.',
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $this->withSession(['_token' => 'test-token'])->post(route('market.inquiries.store', $item), [
            '_token' => 'test-token',
            'name' => 'Ivan',
            'email' => 'ivan@example.com',
            'message' => 'Need setup.',
        ])->assertRedirect();

        $this->assertDatabaseHas(Inquiry::class, [
            'market_item_id' => $item->id,
            'name' => 'Ivan',
            'status' => 'new',
        ]);
    }

    public function test_customer_can_checkout_cart(): void
    {
        $category = Category::query()->create([
            'name' => 'Контроллеры',
            'slug' => 'controllers',
            'is_active' => true,
        ]);

        $item = MarketItem::query()->create([
            'category_id' => $category->id,
            'type' => 'product',
            'name' => 'AiDvor controller',
            'slug' => 'aidvor-controller-cart',
            'summary' => 'Ready to connect controller.',
            'price_rub' => 5000,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $this->withSession(['_token' => 'test-token'])->post(route('cart.items.add', $item), [
            '_token' => 'test-token',
            'quantity' => 2,
        ])->assertRedirect();

        $this->get(route('cart.show'))
            ->assertOk()
            ->assertSee('AiDvor controller');

        $this->withSession(['_token' => 'test-token'])->post(route('orders.store'), [
            '_token' => 'test-token',
            'customer_name' => 'Ivan',
            'customer_email' => 'ivan@example.com',
            'comment' => 'Need delivery.',
        ])->assertRedirect();

        $this->assertDatabaseHas(Order::class, [
            'customer_name' => 'Ivan',
            'customer_email' => 'ivan@example.com',
            'status' => 'new',
            'total_rub' => 10000,
        ]);

        $order = Order::query()->firstOrFail();

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'market_item_id' => $item->id,
            'quantity' => 2,
            'total_rub' => 10000,
        ]);

        $this->get(route('cart.show'))
            ->assertOk()
            ->assertSee('Корзина пуста');
    }

    public function test_out_of_stock_item_cannot_be_added_to_cart(): void
    {
        $category = Category::query()->create([
            'name' => 'Датчики',
            'slug' => 'sensors',
            'is_active' => true,
        ]);

        $item = MarketItem::query()->create([
            'category_id' => $category->id,
            'type' => 'product',
            'name' => 'Sold out sensor',
            'slug' => 'sold-out-sensor',
            'summary' => 'Temporarily unavailable.',
            'price_rub' => 1000,
            'stock_quantity' => 0,
            'is_active' => true,
        ]);

        $this->get(route('market.items.show', $item))
            ->assertOk()
            ->assertSee('Нет в наличии')
            ->assertSee('Оставить заявку')
            ->assertDontSee('Добавить в корзину');

        $this->withSession(['_token' => 'test-token'])->post(route('cart.items.add', $item), [
            '_token' => 'test-token',
            'quantity' => 1,
        ])->assertStatus(422);

        $this->assertSame(0, array_sum(session('cart.items', [])));
    }

    public function test_item_page_displays_telegram_request_link_when_enabled(): void
    {
        config(['telegram.enabled' => true, 'telegram.bot_username' => 'AiDvorSupportBot']);

        $category = Category::query()->create([
            'name' => 'Контроллеры',
            'slug' => 'controllers',
            'is_active' => true,
        ]);

        $item = MarketItem::query()->create([
            'category_id' => $category->id,
            'type' => 'product',
            'name' => 'Telegram controller',
            'slug' => 'telegram-controller',
            'summary' => 'Ready to discuss.',
            'stock_quantity' => 1,
            'is_active' => true,
        ]);

        $this->get(route('market.items.show', $item))
            ->assertOk()
            ->assertSee('Сделать заявку в Telegram')
            ->assertSee('https://t.me/AiDvorSupportBot?start=item_telegram-controller', false);
    }

    public function test_telegram_request_link_is_hidden_when_disabled(): void
    {
        config(['telegram.enabled' => false, 'telegram.bot_username' => 'AiDvorSupportBot']);

        $category = Category::query()->create([
            'name' => 'Контроллеры',
            'slug' => 'controllers',
            'is_active' => true,
        ]);

        $item = MarketItem::query()->create([
            'category_id' => $category->id,
            'type' => 'product',
            'name' => 'Hidden telegram controller',
            'slug' => 'hidden-telegram-controller',
            'summary' => 'No telegram CTA.',
            'stock_quantity' => 1,
            'is_active' => true,
        ]);

        $this->get(route('market.items.show', $item))
            ->assertOk()
            ->assertDontSee('Сделать заявку в Telegram')
            ->assertDontSee('https://t.me/AiDvorSupportBot', false);
    }

    public function test_item_page_displays_vk_request_link_when_enabled(): void
    {
        config(['vk.enabled' => true, 'vk.group_screen_name' => 'aidvor_market']);

        $category = Category::query()->create([
            'name' => 'Контроллеры',
            'slug' => 'controllers',
            'is_active' => true,
        ]);

        $item = MarketItem::query()->create([
            'category_id' => $category->id,
            'type' => 'product',
            'name' => 'VK controller',
            'slug' => 'vk-controller',
            'summary' => 'Ready to discuss in VK.',
            'stock_quantity' => 1,
            'is_active' => true,
        ]);

        $this->get(route('market.items.show', $item))
            ->assertOk()
            ->assertSee('Сделать заявку во VK')
            ->assertSee('https://vk.me/aidvor_market?ref=item_vk-controller', false);
    }

    public function test_vk_callback_confirms_server(): void
    {
        config(['vk.confirmation_code' => 'confirm-code', 'vk.secret' => 'secret']);

        $this->postJson('/api/vk/callback', [
            'type' => 'confirmation',
            'group_id' => 1,
        ])->assertOk()->assertSee('confirm-code');
    }

    public function test_vk_callback_creates_item_conversation(): void
    {
        config(['vk.access_token' => 'token', 'vk.secret' => 'secret']);

        Http::fake([
            'api.vk.com/*' => Http::response(['response' => 1], 200),
        ]);

        $category = Category::query()->create([
            'name' => 'Контроллеры',
            'slug' => 'controllers',
            'is_active' => true,
        ]);

        $item = MarketItem::query()->create([
            'category_id' => $category->id,
            'type' => 'product',
            'name' => 'VK controller',
            'slug' => 'vk-controller',
            'summary' => 'Ready to discuss in VK.',
            'stock_quantity' => 1,
            'is_active' => true,
        ]);

        $this->postJson('/api/vk/callback', [
            'type' => 'message_new',
            'secret' => 'secret',
            'object' => [
                'message' => [
                    'id' => 10,
                    'peer_id' => 777,
                    'from_id' => 777,
                    'text' => '',
                    'ref' => 'item_'.$item->slug,
                ],
            ],
        ])->assertOk()->assertSee('ok');

        $conversation = VkConversation::query()->firstOrFail();

        $this->assertSame($item->id, $conversation->market_item_id);
        $this->assertSame(777, $conversation->vk_user_id);

        $this->postJson('/api/vk/callback', [
            'type' => 'message_new',
            'secret' => 'secret',
            'object' => [
                'message' => [
                    'id' => 11,
                    'peer_id' => 777,
                    'from_id' => 777,
                    'payload' => json_encode([
                        'type' => 'intent',
                        'conversation_id' => $conversation->id,
                        'intent' => 'availability',
                    ]),
                ],
            ],
        ])->assertOk()->assertSee('ok');

        $this->assertDatabaseHas('vk_conversations', [
            'id' => $conversation->id,
            'intent' => 'availability',
        ]);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'messages.send')
            && (string) $request['peer_id'] === '777');
    }

    public function test_administrator_can_reply_to_vk_conversation(): void
    {
        config(['vk.access_token' => 'token']);

        Http::fake([
            'api.vk.com/*' => Http::response(['response' => 123], 200),
        ]);

        Role::query()->create(['name' => 'administrator', 'guard_name' => 'web']);

        $administrator = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $administrator->assignRole('administrator');

        $conversation = VkConversation::query()->create([
            'context_type' => 'item',
            'context_token' => 'vk-controller',
            'vk_user_id' => 777,
            'status' => 'open',
        ]);

        $this->actingAs($administrator)
            ->withSession(['_token' => 'test-token'])
            ->post(route('admin.vk.reply', $conversation), [
                '_token' => 'test-token',
                'message' => 'Здравствуйте. Товар есть в наличии.',
            ])
            ->assertRedirect(route('admin.vk.show', $conversation));

        $this->assertDatabaseHas('vk_messages', [
            'vk_conversation_id' => $conversation->id,
            'direction' => 'admin',
            'body' => 'Здравствуйте. Товар есть в наличии.',
            'vk_message_id' => 123,
        ]);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'messages.send')
            && (string) $request['peer_id'] === '777'
            && $request['message'] === 'Здравствуйте. Товар есть в наличии.');
    }

    public function test_telegram_webhook_creates_item_conversation_and_notifies_admin(): void
    {
        config([
            'telegram.webhook_secret' => 'secret',
            'telegram.bot_token' => 'token',
            'telegram.admin_chat_id' => '100',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 501]], 200),
        ]);

        $category = Category::query()->create([
            'name' => 'Контроллеры',
            'slug' => 'controllers',
            'is_active' => true,
        ]);

        $item = MarketItem::query()->create([
            'category_id' => $category->id,
            'type' => 'product',
            'name' => 'Telegram controller',
            'slug' => 'telegram-controller',
            'summary' => 'Ready to discuss.',
            'stock_quantity' => 1,
            'is_active' => true,
        ]);

        $this->postJson('/api/telegram/webhook/secret', [
            'message' => [
                'message_id' => 10,
                'chat' => ['id' => 777, 'first_name' => 'Ivan'],
                'from' => ['id' => 777, 'username' => 'ivan'],
                'text' => '/start item_'.$item->slug,
            ],
        ])->assertOk();

        $conversation = TelegramConversation::query()->firstOrFail();

        $this->assertSame($item->id, $conversation->market_item_id);
        $this->assertSame('ivan', $conversation->telegram_username);

        $this->postJson('/api/telegram/webhook/secret', [
            'callback_query' => [
                'id' => 'callback-1',
                'from' => ['id' => 777],
                'message' => ['chat' => ['id' => 777]],
                'data' => 'intent:'.$conversation->id.':availability',
            ],
        ])->assertOk();

        $conversation->refresh();

        $this->assertSame('availability', $conversation->intent);
        $this->assertSame(501, $conversation->admin_message_id);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/sendMessage')
            && $request['chat_id'] === '100'
            && str_contains($request['text'], 'Новая заявка из маркета'));
    }

    public function test_admin_reply_is_relayed_to_telegram_user(): void
    {
        config([
            'telegram.webhook_secret' => 'secret',
            'telegram.bot_token' => 'token',
            'telegram.admin_chat_id' => '100',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 700]], 200),
        ]);

        $conversation = TelegramConversation::query()->create([
            'context_type' => 'cart',
            'telegram_user_id' => 777,
            'telegram_username' => 'ivan',
            'admin_message_id' => 501,
        ]);

        $this->postJson('/api/telegram/webhook/secret', [
            'message' => [
                'message_id' => 20,
                'chat' => ['id' => 100],
                'text' => 'Можно доставить завтра.',
                'reply_to_message' => ['message_id' => 501],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('telegram_messages', [
            'telegram_conversation_id' => $conversation->id,
            'direction' => 'admin',
            'body' => 'Можно доставить завтра.',
        ]);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/sendMessage')
            && $request['chat_id'] === 777
            && $request['text'] === 'Можно доставить завтра.');
    }

    public function test_admin_requires_administrator_role(): void
    {
        Role::query()->create(['name' => 'administrator', 'guard_name' => 'web']);

        $regularUser = User::query()->create([
            'name' => 'Regular',
            'email' => 'regular@example.com',
            'password' => Hash::make('password'),
        ]);

        $administrator = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $administrator->assignRole('administrator');

        $this->get('/admin/items')->assertRedirect('/admin/login');

        $this->actingAs($regularUser)
            ->get('/admin/items')
            ->assertForbidden();

        $this->actingAs($administrator)
            ->get('/admin/items')
            ->assertOk();

        $this->actingAs($administrator)
            ->get('/admin/orders')
            ->assertOk();

        $this->actingAs($administrator)
            ->get('/admin/vk')
            ->assertOk();
    }

    public function test_administrator_can_log_in(): void
    {
        Role::query()->create(['name' => 'administrator', 'guard_name' => 'web']);

        $administrator = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $administrator->assignRole('administrator');

        $this->withSession(['_token' => 'test-token'])->post('/admin/login', [
            '_token' => 'test-token',
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($administrator);
    }
}
