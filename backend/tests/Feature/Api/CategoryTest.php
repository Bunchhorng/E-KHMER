<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * Depth-first search for a node anywhere in a serialised tree.
     */
    protected function findNode(array $nodes, int $id): ?array
    {
        foreach ($nodes as $node) {
            if ($node['id'] === $id) {
                return $node;
            }

            if ($found = $this->findNode($node['children'] ?? [], $id)) {
                return $found;
            }
        }

        return null;
    }

    // -----------------------------------------------------------------
    // Authorization
    // -----------------------------------------------------------------

    public function test_admin_category_endpoints_require_the_admin_role(): void
    {
        $category = Category::factory()->create();

        $this->getJson('/api/admin/categories')->assertStatus(401);
        $this->postJson('/api/admin/categories', ['name' => 'X'])->assertStatus(401);

        $customer = User::factory()->create();

        $this->actingAs($customer, 'sanctum')->getJson('/api/admin/categories')->assertStatus(403);
        $this->actingAs($customer, 'sanctum')->postJson('/api/admin/categories', ['name' => 'X'])->assertStatus(403);
        $this->actingAs($customer, 'sanctum')->putJson("/api/admin/categories/{$category->id}", ['name' => 'X'])->assertStatus(403);
        $this->actingAs($customer, 'sanctum')->deleteJson("/api/admin/categories/{$category->id}")->assertStatus(403);
    }

    public function test_public_category_tree_is_open_to_guests(): void
    {
        Category::factory()->create(['name' => 'Open']);

        $this->getJson('/api/categories')->assertOk()->assertJsonCount(1, 'data');
    }

    // -----------------------------------------------------------------
    // Admin tree
    // -----------------------------------------------------------------

    public function test_admin_index_returns_the_full_tree_beyond_two_levels(): void
    {
        $root = Category::factory()->create(['name' => 'Root']);
        $mid = Category::factory()->create(['name' => 'Mid', 'parent_id' => $root->id]);
        $leaf = Category::factory()->create(['name' => 'Leaf', 'parent_id' => $mid->id]);

        $data = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/categories')
            ->assertOk()
            ->json('data');

        $found = $this->findNode($data, $leaf->id);

        $this->assertNotNull($found, 'A third-level category was dropped from the admin tree.');
        $this->assertSame('Leaf', $found['name']);
    }

    public function test_admin_index_includes_inactive_categories(): void
    {
        $root = Category::factory()->create();
        Category::factory()->inactive()->create(['parent_id' => $root->id]);

        $data = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/categories')
            ->assertOk()
            ->json('data');

        $this->assertCount(
            1,
            $data[0]['children'] ?? [],
            'An admin cannot see its own draft sub-category, so it can neither edit nor delete it.'
        );
    }

    public function test_admin_index_exposes_the_product_count(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(2)->create(['category_id' => $category->id]);
        Product::factory()->create(['category_id' => $category->id, 'is_active' => false]);

        $node = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/categories')
            ->assertOk()
            ->json('data.0');

        // The admin tree view renders this chip; without the key it is stuck on 0.
        $this->assertArrayHasKey('products_count', $node);
        $this->assertSame(3, $node['products_count']);
    }

    public function test_admin_index_orders_roots_by_sort_order(): void
    {
        Category::factory()->create(['name' => 'Second', 'sort_order' => 5]);
        Category::factory()->create(['name' => 'First', 'sort_order' => 1]);

        $data = $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/categories')
            ->assertOk()
            ->json('data');

        $this->assertSame(['First', 'Second'], array_column($data, 'name'));
    }

    // -----------------------------------------------------------------
    // Public tree
    // -----------------------------------------------------------------

    public function test_public_tree_hides_inactive_categories(): void
    {
        $active = Category::factory()->create(['name' => 'Active']);
        Category::factory()->inactive()->create(['name' => 'Hidden']);
        Category::factory()->create(['name' => 'Child', 'parent_id' => $active->id]);

        $data = $this->getJson('/api/categories')->assertOk()->json('data');

        $this->assertSame(['Active'], array_column($data, 'name'));
        $this->assertSame(['Child'], array_column($data[0]['children'], 'name'));
    }

    public function test_public_tree_is_cached_and_invalidated_on_write(): void
    {
        Category::factory()->create(['name' => 'First']);

        $this->getJson('/api/categories')->assertOk()->assertJsonCount(1, 'data');

        // Served from cache on the second call.
        $this->getJson('/api/categories')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/categories', ['name' => 'Second'])
            ->assertStatus(201);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_toggling_a_category_off_removes_it_from_the_public_tree(): void
    {
        $category = Category::factory()->create();

        $this->getJson('/api/categories')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/admin/categories/{$category->id}", ['is_active' => false])
            ->assertOk();

        $this->getJson('/api/categories')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_uploading_a_category_image_stores_a_real_file(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create();

        $image = $this->actingAs($this->admin(), 'sanctum')
            ->post("/api/admin/categories/{$category->id}/image", [
                'image' => UploadedFile::fake()->image('c.jpg', 10, 10),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('data.image');

        // A bare disk URL means the write failed and url(false) degraded to the
        // base path, which stored an image reference that can never resolve.
        $this->assertStringContainsString('/images/categories/', $image);

        $relative = str_replace(Storage::disk('public')->url(''), '', $image);

        Storage::disk('public')->assertExists($relative);
        $this->assertSame($image, $category->fresh()->image);
    }

    public function test_a_failed_image_write_is_reported_and_not_persisted(): void
    {
        $category = Category::factory()->create(['image' => 'https://cdn.test/keep.jpg']);

        // FilesystemAdapter::putFileAs() answers false rather than throwing when
        // the target directory is not writable by the FPM user, which is how this
        // reached a live admin: url(false) degraded to the bare disk URL and the
        // category silently ended up pointing at a path with no filename.
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->andReturn(false);
        // Stubbed so the run reaches the assertion instead of dying inside the mock.
        $disk->shouldReceive('url')->andReturn('/storage/');
        Storage::shouldReceive('disk')->andReturn($disk);

        $this->actingAs($this->admin(), 'sanctum')
            ->post("/api/admin/categories/{$category->id}/image", [
                'image' => UploadedFile::fake()->image('c.jpg', 10, 10),
            ], ['Accept' => 'application/json'])
            ->assertStatus(500);

        $this->assertSame(
            'https://cdn.test/keep.jpg',
            $category->fresh()->image,
            'A failed upload must neither persist a broken path nor drop the image already there.'
        );
    }

    public function test_uploading_a_category_image_refreshes_the_public_tree(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create();

        $this->getJson('/api/categories')->assertOk();

        $path = $this->actingAs($this->admin(), 'sanctum')
            ->post("/api/admin/categories/{$category->id}/image", [
                'image' => UploadedFile::fake()->image('c.jpg', 10, 10),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('data.image');

        $this->assertSame(
            $path,
            $this->getJson('/api/categories')->assertOk()->json('data.0.image'),
            'The cached public tree still serves the pre-upload image.'
        );
    }

    public function test_a_category_image_can_be_removed(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create();

        $path = $this->actingAs($this->admin(), 'sanctum')
            ->post("/api/admin/categories/{$category->id}/image", [
                'image' => UploadedFile::fake()->image('c.jpg', 10, 10),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->json('data.image');

        $this->getJson('/api/categories')->assertOk();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/admin/categories/{$category->id}/image")
            ->assertOk()
            ->assertJsonPath('data.message', 'Image removed.');

        $this->assertNull($category->fresh()->image);
        Storage::disk('public')->assertMissing($path);

        $this->assertNull(
            $this->getJson('/api/categories')->assertOk()->json('data.0.image'),
            'The cached public tree still serves the removed image.'
        );
    }

    public function test_removing_a_category_image_requires_the_admin_role(): void
    {
        $category = Category::factory()->create();

        $this->deleteJson("/api/admin/categories/{$category->id}/image")->assertStatus(401);
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->deleteJson("/api/admin/categories/{$category->id}/image")
            ->assertStatus(403);
    }

    public function test_removing_a_category_image_is_idempotent(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/admin/categories/{$category->id}/image")
            ->assertOk();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/admin/categories/{$category->id}/image")
            ->assertOk();
    }

    // -----------------------------------------------------------------
    // Hierarchy integrity
    // -----------------------------------------------------------------

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/admin/categories/{$category->id}", ['parent_id' => $category->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');

        $this->assertNull($category->fresh()->parent_id);
    }

    public function test_a_category_cannot_be_moved_under_its_own_descendant(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);
        $grandchild = Category::factory()->create(['parent_id' => $child->id]);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/admin/categories/{$root->id}", ['parent_id' => $grandchild->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');

        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_a_category_can_still_be_reparented_to_an_unrelated_branch(): void
    {
        $root = Category::factory()->create();
        $other = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/admin/categories/{$child->id}", ['parent_id' => $other->id])
            ->assertOk();

        $this->assertSame($other->id, $child->fresh()->parent_id);
    }

    // -----------------------------------------------------------------
    // Slug handling
    // -----------------------------------------------------------------

    public function test_store_normalises_an_unsafe_slug(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/categories', ['name' => 'Phones & Tablets', 'slug' => 'Phones  &  TABLETS/x'])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'phones-tabletsx');
    }

    public function test_store_generates_a_slug_from_the_name(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/categories', ['name' => 'Home Appliances'])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'home-appliances');
    }

    public function test_store_deduplicates_a_slug_that_is_already_taken(): void
    {
        Category::factory()->create(['slug' => 'taken']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/categories', ['name' => 'Taken'])
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'taken-1');
    }

    public function test_update_keeps_its_own_slug(): void
    {
        $category = Category::factory()->create(['slug' => 'keep-me']);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/admin/categories/{$category->id}", ['name' => 'Renamed', 'slug' => 'keep-me'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'keep-me');
    }

    public function test_renaming_a_category_keeps_its_slug_until_one_is_sent(): void
    {
        $category = Category::factory()->create(['name' => 'Phones', 'slug' => 'phones']);

        // The storefront URL is a live link, so a rename alone must not repoint it.
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/admin/categories/{$category->id}", ['name' => 'Smartphones'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'phones')
            ->assertJsonPath('data.name', 'Smartphones');

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/admin/categories/{$category->id}", ['slug' => 'smartphones'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'smartphones');
    }

    public function test_update_deduplicates_a_slug_belonging_to_another_category(): void
    {
        Category::factory()->create(['slug' => 'first']);
        $second = Category::factory()->create(['slug' => 'second']);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/admin/categories/{$second->id}", ['slug' => 'first'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'first-1');
    }

    // -----------------------------------------------------------------
    // Column bounds
    // -----------------------------------------------------------------

    public function test_store_rejects_a_negative_sort_order(): void
    {
        // categories.sort_order is unsigned; a negative value used to reach MySQL.
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/categories', ['name' => 'Bad Order', 'sort_order' => -1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('sort_order');
    }

    public function test_store_rejects_an_over_long_image_path(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/categories', ['name' => 'Long Image', 'image' => str_repeat('a', 300)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    public function test_store_rejects_an_over_long_description(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/categories', ['name' => 'Long Text', 'description' => str_repeat('a', 70000)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('description');
    }

    public function test_store_returns_the_persisted_defaults(): void
    {
        // The columns default in MySQL, so an omitted key is absent on the in-memory
        // model and used to be echoed back as null/false.
        $response = $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/categories', ['name' => 'Defaults'])
            ->assertStatus(201);

        $response->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.sort_order', 0)
            ->assertJsonPath('data.parent_id', null);
    }

    public function test_store_requires_a_name(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/categories', ['slug' => 'no-name'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_store_rejects_an_unknown_parent(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/admin/categories', ['name' => 'Orphan', 'parent_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    // -----------------------------------------------------------------
    // Deletion
    // -----------------------------------------------------------------

    public function test_destroy_blocks_a_category_that_still_has_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/admin/categories/{$category->id}")
            ->assertStatus(422)
            ->assertJsonPath('data.message', 'Move its 1 product to another category first.');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['category_id' => $category->id]);
    }

    public function test_destroy_blocks_a_category_that_still_has_children(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/admin/categories/{$parent->id}")
            ->assertStatus(422)
            ->assertJsonPath('data.message', 'Contains sub-categories; delete those first.');

        $this->assertDatabaseHas('categories', ['id' => $parent->id]);
    }

    public function test_destroy_removes_an_unused_leaf_and_returns_the_standard_envelope(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/admin/categories/{$category->id}")
            ->assertOk()
            ->assertJsonPath('data.message', 'Category deleted.');

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_destroy_refreshes_the_public_tree(): void
    {
        $category = Category::factory()->create();

        $this->getJson('/api/categories')->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/admin/categories/{$category->id}")
            ->assertOk();

        $this->getJson('/api/categories')->assertOk()->assertJsonCount(0, 'data');
    }

    // -----------------------------------------------------------------
    // Catalog integration
    // -----------------------------------------------------------------

    public function test_catalog_filter_includes_products_in_child_categories(): void
    {
        $parent = Category::factory()->create(['slug' => 'electronics']);
        $child = Category::factory()->create(['slug' => 'phones', 'parent_id' => $parent->id]);

        Product::factory()->withVariant()->create(['category_id' => $parent->id]);
        Product::factory()->withVariant()->create(['category_id' => $child->id]);

        $response = $this->getJson('/api/catalog/products?category=electronics&perPage=10')
            ->assertOk();

        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_facet_count_includes_products_in_child_categories(): void
    {
        $parent = Category::factory()->create(['slug' => 'electronics', 'name' => 'Electronics']);
        $child = Category::factory()->create(['slug' => 'phones', 'parent_id' => $parent->id]);

        Product::factory()->withVariant()->create(['category_id' => $child->id]);

        $facets = $this->getJson('/api/catalog/facets')->assertOk()->json('data.categories');

        $parentFacet = collect($facets)->firstWhere('slug', 'electronics');

        $this->assertNotNull($parentFacet);
        $this->assertSame(1, $parentFacet['count'], 'The sidebar count disagrees with the listing it filters.');
    }

    public function test_catalog_filter_still_matches_a_leaf_category_exactly(): void
    {
        $parent = Category::factory()->create(['slug' => 'electronics']);
        $child = Category::factory()->create(['slug' => 'phones', 'parent_id' => $parent->id]);

        Product::factory()->withVariant()->create(['category_id' => $parent->id]);
        Product::factory()->withVariant()->create(['category_id' => $child->id]);

        $this->getJson('/api/catalog/products?category=phones&perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_catalog_filter_ignores_an_unknown_category(): void
    {
        Product::factory()->withVariant()->create();

        $this->getJson('/api/catalog/products?category=nope&perPage=10')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_a_category_hidden_behind_an_inactive_parent_is_not_offered_as_a_filter(): void
    {
        $hidden = Category::factory()->inactive()->create(['slug' => 'retired']);
        Category::factory()->create(['slug' => 'phones', 'parent_id' => $hidden->id]);

        $slugs = collect($this->getJson('/api/catalog/facets')->assertOk()->json('data.categories'))
            ->pluck('slug');

        $this->assertFalse($slugs->contains('phones'), 'A category under a retired parent is still offered in the sidebar.');
    }
}