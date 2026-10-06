<?php

namespace Tests\Feature\Api;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\ShippingMethod;
use App\Models\Shop;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression coverage for the P1 multi-shop audit findings.
 *
 * Every test here failed before the shop_id ownership work landed, because
 * order lines, stock rows, inventory ledger entries, reviews, coupons and
 * shipping methods could all be written without recording which branch owns
 * them.
 *
 * Note on authorization coverage: every /api/admin/* route is still gated by
 * the `admin` middleware, so a branch manager can never reach those
 * controllers. Branch isolation is therefore asserted against the policies
 * (Gate), and the data-integrity guarantees are asserted end to end over HTTP
 * as a super admin. See the audit report for the follow-up to expose a
 * manager-scoped admin surface.
 */
class ShopOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function managerOf(Shop $shop): User
    {
        $user = User::factory()->create();
        $user->shops()->attach($shop->id, ['role_in_shop' => 'manager', 'status' => 'active']);

        return $user;
    }

    private function productIn(Shop $shop, int $stock = 10): Product
    {
        return Product::factory()->withVariant(50.00, $stock)->create(['shop_id' => $shop->id]);
    }

    private function variantIdOf(Product $product): int
    {
        return (int) $product->variants()->first()->id;
    }

    private function productPayload(Shop $shop, array $overrides = []): array
    {
        return array_merge([
            'category_id' => Category::factory()->create()->id,
            'brand_id' => Brand::factory()->create()->id,
            'shop_id' => $shop->id,
            'price' => 25.00,
        ], $overrides);
    }

    /**
     * P1: order_items.shop_id was never persisted, so a branch could not report
     * on its own sales and a shared order could not be split by branch.
     */
    public function test_checkout_stamps_shop_id_on_every_order_line(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        $productA = $this->productIn($shopA);
        $productB = $this->productIn($shopB);

        Sanctum::actingAs(User::factory()->create());

        // The cart endpoint answers 201 for a new line and 200 when an existing
        // line for the same variant is incremented, so only success is asserted.
        $this->postJson('/api/cart', ['product_variant_id' => $this->variantIdOf($productA), 'quantity' => 1])->assertSuccessful();
        $this->postJson('/api/cart', ['product_variant_id' => $this->variantIdOf($productB), 'quantity' => 2])->assertSuccessful();

        $response = $this->postJson('/api/checkout', [
            'shipping_method_id' => ShippingMethod::factory()->create(['price' => 5.00])->id,
            'payment_method' => 'card',
            'address' => [
                'full_name' => 'Test Buyer',
                'phone' => '+1 555 0100',
                'address_line1' => '123 Test St',
                'address_line2' => '',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '73301',
                'country' => 'US',
            ],
        ])->assertStatus(201);

        $order = Order::where('order_number', $response->json('data.order_number'))->firstOrFail();

        $lines = OrderItem::where('order_id', $order->id)
            ->pluck('shop_id', 'product_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertSame($shopA->id, $lines[$productA->id], 'Product A line must be owned by shop A.');
        $this->assertSame($shopB->id, $lines[$productB->id], 'Product B line must be owned by shop B.');

        $this->assertDatabaseHas('shop_orders', [
            'order_id' => $order->id,
            'shop_id' => $shopA->id,
            'subtotal' => 50,
        ]);
        $this->assertDatabaseHas('shop_orders', [
            'order_id' => $order->id,
            'shop_id' => $shopB->id,
            'subtotal' => 100,
        ]);
        $this->assertSame(2, $order->shopOrders()->count());
    }

    /**
     * P1: InventoryService created stock rows (and logged ledger entries) with a
     * NULL shop_id whenever the caller had no shop context, e.g. guest checkout
     * or the reservation-expiry cron.
     */
    public function test_inventory_service_derives_shop_from_the_variant(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->withVariant(50.00, 0)->create(['shop_id' => $shop->id]);
        $variantId = $this->variantIdOf($product);

        $service = app(InventoryService::class);

        $this->assertSame($shop->id, $service->shopIdForVariant($variantId));

        // The factory pre-creates the stock row without a shop, mimicking the
        // pre-fix state; the service must backfill the derived shop.
        $inventory = Inventory::where('product_variant_id', $variantId)->firstOrFail();
        $inventory->update(['shop_id' => null, 'quantity' => 0]);

        $service->adjust($variantId, 25);

        $inventory->refresh();
        $this->assertSame($shop->id, (int) $inventory->shop_id);

        $transaction = InventoryTransaction::where('inventory_id', $inventory->id)->latest('id')->firstOrFail();
        $this->assertSame($shop->id, (int) $transaction->shop_id);
    }

    /**
     * P1: an inventory row created from scratch (no pre-existing row) must still
     * inherit the branch of its variant.
     */
    public function test_inventory_row_created_on_demand_inherits_the_shop(): void
    {
        $shop = Shop::factory()->create();
        $product = Product::factory()->create(['shop_id' => $shop->id]);
        $variant = $product->variants()->create([
            'name' => 'On Demand',
            'sku' => 'ON-DEMAND-'.uniqid(),
            'price' => 10.00,
            'is_active' => true,
        ]);

        $this->assertNull(Inventory::where('product_variant_id', $variant->id)->first());

        app(InventoryService::class)->adjust((int) $variant->id, 5);

        $inventory = Inventory::where('product_variant_id', $variant->id)->firstOrFail();
        $this->assertSame($shop->id, (int) $inventory->shop_id);
    }

    /**
     * P1: AdminProductRequest silently dropped shop_id, so a product could not
     * be assigned to a branch and its variants were created shop-less.
     */
    public function test_admin_can_create_a_product_in_a_chosen_shop(): void
    {
        $shop = Shop::factory()->create();

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/products', $this->productPayload($shop, [
                'name' => 'Branch Exclusive Tee',
                'variants' => [
                    ['sku' => 'TEE-M', 'name' => 'Medium', 'price' => 25.00, 'quantity' => 7],
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.shop_id', $shop->id)
            ->assertJsonPath('data.shop.id', $shop->id)
            ->assertJsonPath('data.shop.code', $shop->code);

        $product = Product::findOrFail($response->json('data.id'));

        $this->assertSame($shop->id, (int) $product->shop_id);

        $inventory = Inventory::where('product_variant_id', $this->variantIdOf($product))->firstOrFail();
        $this->assertSame($shop->id, (int) $inventory->shop_id);
        $this->assertSame(7, (int) $inventory->quantity);
    }

    public function test_product_list_can_be_filtered_by_shop(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        $this->productIn($shopA);
        $this->productIn($shopB);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/admin/products?shop_id={$shopA->id}")
            ->assertOk();

        $shopIds = array_unique(array_column(
            array_column($response->json('data'), 'shop_id'),
            null,
        ));

        $this->assertSame([$shopA->id], $shopIds);
    }

    /**
     * P1: moving a product between branches left its stock rows on the old
     * branch, so branch inventory and reports silently drifted.
     */
    public function test_reassigning_a_product_cascades_shop_id_to_inventory(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $product = $this->productIn($shopA, 12);

        $variantId = $this->variantIdOf($product);

        $inventory = Inventory::where('product_variant_id', $variantId)->firstOrFail();
        $this->assertSame($shopA->id, (int) $inventory->shop_id);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/admin/products/{$product->id}", [
                'name' => $product->name,
                'shop_id' => $shopB->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.shop_id', $shopB->id);

        $this->assertSame($shopB->id, (int) $inventory->fresh()->shop_id);
    }

    /**
     * P1: restore resolved the model without checking ownership, so a branch
     * manager could un-delete another branch's product.
     */
    public function test_restore_policy_requires_branch_ownership(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        $mine = $this->productIn($shopA);
        $theirs = $this->productIn($shopB);

        $mine->delete();
        $theirs->delete();

        $managerA = $this->managerOf($shopA);

        $this->assertTrue($managerA->can('restore', Product::withTrashed()->findOrFail($mine->id)));
        $this->assertFalse($managerA->can('restore', Product::withTrashed()->findOrFail($theirs->id)));
    }

    /**
     * A trashed product must be restorable end to end, and the response must
     * still carry branch ownership.
     */
    public function test_admin_can_restore_a_soft_deleted_product(): void
    {
        $shop = Shop::factory()->create();
        $product = $this->productIn($shop);
        $product->delete();

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson("/api/admin/products/{$product->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.shop_id', $shop->id);

        $this->assertNull($product->fresh()->deleted_at);
    }

    /**
     * P1: a manager must not be able to move a product out of their branch, nor
     * drop a product into a branch they do not manage.
     */
    public function test_change_shop_policy_blocks_moving_out_of_own_branch(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $managerA = $this->managerOf($shopA);

        $mine = $this->productIn($shopA);
        $theirs = $this->productIn($shopB);

        $gate = Gate::forUser($managerA);

        $this->assertTrue($gate->check('changeShop', [$mine, $shopA->id]));
        $this->assertFalse($gate->check('changeShop', [$mine, $shopB->id]));
        $this->assertFalse($gate->check('changeShop', [$theirs, $shopB->id]));
    }

    /**
     * P1: `product_variants.sku` had a global UNIQUE index, so a manufacturer
     * SKU could only ever be listed by one branch. Uniqueness is now scoped to
     * the owning shop.
     */
    public function test_variant_sku_is_unique_per_shop_not_globally(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $admin = $this->admin();

        $sharedSku = 'SHARED-SKU-01';

        foreach ([$shopA, $shopB] as $shop) {
            $this->actingAs($admin, 'sanctum')
                ->postJson('/api/admin/products', $this->productPayload($shop, [
                    'name' => "Product for shop {$shop->id}",
                    'variants' => [['sku' => $sharedSku, 'name' => 'Default', 'price' => 10.00, 'quantity' => 1]],
                ]))
                ->assertCreated();
        }

        $this->assertSame(2, ProductVariant::where('sku', $sharedSku)->count());

        // Reusing the same SKU inside one branch is still a conflict, and it
        // must be reported as a validation error rather than a 500.
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/products', $this->productPayload($shopA, [
                'name' => 'Clash inside shop A',
                'variants' => [['sku' => $sharedSku, 'name' => 'Default', 'price' => 10.00, 'quantity' => 1]],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('variants');
    }

    public function test_moving_a_product_releases_and_reclaims_its_sku(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $shopC = Shop::factory()->create();
        $admin = $this->admin();

        $sku = 'MOVABLE-SKU-01';

        $moving = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/products', $this->productPayload($shopA, [
                'name' => 'Movable product',
                'variants' => [['sku' => $sku, 'name' => 'Default', 'price' => 10.00, 'quantity' => 1]],
            ]))
            ->assertCreated()
            ->json('data.id');

        // Shop B is free to use the same manufacturer SKU.
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/products', $this->productPayload($shopB, [
                'name' => 'Shop B claimant',
                'variants' => [['sku' => $sku, 'name' => 'Default', 'price' => 10.00, 'quantity' => 1]],
            ]))
            ->assertCreated();

        // Moving the shop A product into shop B now collides and is rejected as
        // a validation error, not a 500 from the unique index.
        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/products/{$moving}", ['name' => 'Movable product', 'shop_id' => $shopB->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('shop_id');

        // A free destination works, and the variant's shop_id is cascaded.
        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/admin/products/{$moving}", ['name' => 'Movable product', 'shop_id' => $shopC->id])
            ->assertOk();

        $this->assertSame($shopC->id, (int) ProductVariant::where('product_id', $moving)->value('shop_id'));
    }

    /**
     * P1: a review had no branch ownership, so branch review queues could not
     * be filtered and moderation could reach across shops.
     */
    public function test_new_review_inherits_the_product_shop(): void
    {
        $shop = Shop::factory()->create();
        $user = User::factory()->create();
        $product = $this->productIn($shop);

        $order = Order::factory()->delivered()->create(['user_id' => $user->id]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $this->variantIdOf($product),
            'product_name' => $product->name,
            'variant_label' => 'Default',
            'sku' => $product->variants()->first()->sku,
            'unit_price' => 50.00,
            'quantity' => 1,
            'line_total' => 50.00,
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/reviews', [
                'product_id' => $product->id,
                'rating' => 5,
                'title' => 'Great',
            ])
            ->assertCreated()
            ->assertJsonPath('data.shop_id', $shop->id);

        $this->assertDatabaseHas('reviews', [
            'product_id' => $product->id,
            'user_id' => $user->id,
            'shop_id' => $shop->id,
        ]);
    }

    public function test_review_policy_denies_cross_branch_moderation(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        $managerA = $this->managerOf($shopA);

        $reviewA = Review::create([
            'user_id' => User::factory()->create()->id,
            'product_id' => $this->productIn($shopA)->id,
            'order_id' => Order::factory()->create()->id,
            'shop_id' => $shopA->id,
            'rating' => 4,
            'status' => Review::STATUS_PENDING,
            'verified' => true,
        ]);

        $reviewB = Review::create([
            'user_id' => User::factory()->create()->id,
            'product_id' => $this->productIn($shopB)->id,
            'order_id' => Order::factory()->create()->id,
            'shop_id' => $shopB->id,
            'rating' => 2,
            'status' => Review::STATUS_PENDING,
            'verified' => true,
        ]);

        $this->assertTrue($managerA->can('moderate', $reviewA));
        $this->assertFalse($managerA->can('moderate', $reviewB));
        $this->assertFalse($managerA->can('delete', $reviewB));
        $this->assertTrue($this->admin()->can('moderate', $reviewB));
    }

    /**
     * P1: coupons and shipping methods were global, so one branch could honour
     * another branch's promotions and rates.
     */
    public function test_coupon_and_shipping_method_can_be_scoped_to_a_shop(): void
    {
        $shop = Shop::factory()->create();
        $admin = $this->admin();

        $couponResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/coupons', [
                'shop_id' => $shop->id,
                'code' => 'branch10',
                'type' => 'percentage',
                'value' => 10,
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.shop_id', $shop->id);

        $this->assertSame($shop->id, (int) Coupon::findOrFail($couponResponse->json('data.id'))->shop_id);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/shipping-methods', [
                'shop_id' => $shop->id,
                'name' => 'Branch Delivery',
                'code' => 'branch-delivery',
                'price' => 4.50,
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.shop_id', $shop->id)
            ->assertJsonPath('data.shop.id', $shop->id);
    }

    public function test_coupon_list_can_be_filtered_by_shop(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        Coupon::factory()->create(['shop_id' => $shopA->id, 'code' => 'ONLYA']);
        Coupon::factory()->create(['shop_id' => $shopB->id, 'code' => 'ONLYB']);

        $response = $this->actingAs($this->admin(), 'sanctum')
            ->getJson("/api/admin/coupons?shop_id={$shopA->id}")
            ->assertOk();

        $codes = array_column($response->json('data'), 'code');

        $this->assertContains('ONLYA', $codes);
        $this->assertNotContains('ONLYB', $codes);
    }

    public function test_shipping_method_policy_scopes_creation_to_owned_shops(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        $managerA = $this->managerOf($shopA);
        $methodA = ShippingMethod::factory()->create(['shop_id' => $shopA->id]);
        $methodB = ShippingMethod::factory()->create(['shop_id' => $shopB->id]);

        $this->assertTrue($managerA->can('update', $methodA));
        $this->assertFalse($managerA->can('update', $methodB));
    }
}
