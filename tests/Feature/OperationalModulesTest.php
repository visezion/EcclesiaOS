<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Asset;
use App\Models\AssetBooking;
use App\Models\AssetCategory;
use App\Models\BookstoreLibraryLoan;
use App\Models\BookstoreOrder;
use App\Models\BookstoreOrderItem;
use App\Models\BookstoreProduct;
use App\Models\CareTask;
use App\Models\ChildrenYouthRecord;
use App\Models\CommunicationDelivery;
use App\Models\CounsellingBooking;
use App\Models\Donation;
use App\Models\FinanceTransaction;
use App\Models\Fund;
use App\Models\Member;
use App\Models\Ministry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_requested_operational_modules_render_real_pages(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();

        foreach ([
            'assets.index' => 'Asset Register',
            'finance.index' => 'Donation Ledger',
            'bookstore.index' => 'Product Catalog',
            'children-youth.index' => 'Children & Youth Register',
            'counselling.index' => 'Case Register',
        ] as $route => $text) {
            $this->actingAs($admin)
                ->get(route($route))
                ->assertOk()
                ->assertSee($text, false)
                ->assertDontSee('under development');
        }
    }

    public function test_requested_operational_modules_render_multi_page_flows(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $member = Member::query()->firstOrFail();

        $asset = Asset::query()->firstOrFail();
        $donation = Donation::query()->firstOrFail();
        $product = BookstoreProduct::query()->firstOrFail();
        $record = ChildrenYouthRecord::query()->create([
            'church_id' => $member->church_id,
            'campus_id' => $member->campus_id,
            'first_name' => 'Route',
            'last_name' => 'Youth',
            'age_group' => 'middle_school',
            'consent_status' => 'approved',
            'check_in_status' => 'not_checked_in',
            'status' => 'active',
        ]);
        $case = CareTask::query()->create([
            'church_id' => $member->church_id,
            'campus_id' => $member->campus_id,
            'member_id' => $member->id,
            'assigned_user_id' => $admin->id,
            'type' => 'Counseling',
            'priority' => 'medium',
            'status' => 'assigned',
            'next_action' => 'Route coverage',
        ]);

        foreach ([
            route('assets.overview') => 'Inventory Overview',
            route('assets.create') => 'Add Asset',
            route('assets.edit', $asset) => 'Edit Asset',
            route('finance.overview') => 'Finance Overview',
            route('finance.donations.create') => 'Record Donation',
            route('finance.donations.edit', $donation) => 'Edit Donation',
            route('bookstore.overview') => 'Bookstore Overview',
            route('bookstore.library') => 'Church Library',
            route('bookstore.products.create') => 'Add Product',
            route('bookstore.products.edit', $product) => 'Edit Product',
            route('bookstore.orders.create') => 'Create Order',
            route('children-youth.overview') => 'Children & Youth Overview',
            route('children-youth.create') => 'Add Child or Youth',
            route('children-youth.edit', $record) => 'Edit Child or Youth',
            route('counselling.overview') => 'Counselling Overview',
            route('counselling.create') => 'Create Counselling Case',
            route('counselling.edit', $case) => 'Edit Counselling Case',
        ] as $url => $text) {
            $this->actingAs($admin)
                ->get($url)
                ->assertOk()
                ->assertSee($text, false);
        }
    }

    public function test_assets_module_creates_real_asset_records(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $category = AssetCategory::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('assets.store'), [
                'name' => 'Stage Monitor Test',
                'serial_number' => 'ASSET-TEST-001',
                'asset_category_id' => $category->id,
                'campus_id' => $admin->campus_id,
                'status' => 'available',
                'condition' => 'good',
                'purchased_at' => now()->toDateString(),
                'purchase_amount' => 499.99,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assets', ['name' => 'Stage Monitor Test', 'serial_number' => 'ASSET-TEST-001']);
        $this->assertTrue(Asset::query()->where('serial_number', 'ASSET-TEST-001')->firstOrFail()->category()->exists());
    }

    public function test_assets_module_reserves_assets_and_blocks_conflicts(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $asset = Asset::query()->where('status', 'available')->firstOrFail();
        $member = Member::query()->firstOrFail();
        $startsAt = now()->addDays(3)->setTime(9, 0);
        $endsAt = $startsAt->copy()->addHours(2);

        $this->actingAs($admin)
            ->post(route('assets.bookings.store'), [
                'asset_id' => $asset->opaqueId(),
                'campus_id' => $asset->campus?->opaqueId(),
                'member_id' => $member->opaqueId(),
                'assigned_user_id' => $admin->opaqueId(),
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'ends_at' => $endsAt->format('Y-m-d H:i:s'),
                'status' => 'reserved',
                'purpose' => 'Sunday service setup',
                'location' => 'Main Auditorium',
                'notes' => 'Return after service.',
            ])
            ->assertRedirect();

        $booking = AssetBooking::query()->where('asset_id', $asset->id)->firstOrFail();
        $this->assertSame($member->id, $booking->member_id);
        $this->assertSame($admin->id, $booking->assigned_user_id);
        $this->assertSame('reserved', $booking->status);

        $this->actingAs($admin)
            ->post(route('assets.bookings.store'), [
                'asset_id' => $asset->opaqueId(),
                'starts_at' => $startsAt->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
                'ends_at' => $endsAt->copy()->addMinutes(30)->format('Y-m-d H:i:s'),
                'status' => 'checked_out',
            ])
            ->assertSessionHasErrors('starts_at');
    }

    public function test_finance_module_creates_real_donation_records(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $member = Member::query()->firstOrFail();
        $fund = Fund::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('finance.donations.store'), [
                'member_id' => $member->id,
                'fund_id' => $fund->id,
                'campus_id' => $member->campus_id,
                'amount' => 125.50,
                'currency' => 'USD',
                'method' => 'card',
                'received_at' => now()->format('Y-m-d H:i:s'),
                'reference' => 'TEST-GIVING-001',
            ])
            ->assertRedirect();

        $donation = Donation::query()->where('reference', 'TEST-GIVING-001')->firstOrFail();
        $this->assertTrue($donation->member()->exists());
        $this->assertTrue($donation->fund()->exists());
    }

    public function test_finance_module_updates_donations_and_manages_funds(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $member = Member::query()->firstOrFail();
        $fund = Fund::query()->firstOrFail();
        $donation = Donation::query()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('finance.donations.update', $donation), [
                'member_id' => $member->id,
                'fund_id' => $fund->id,
                'campus_id' => $member->campus_id,
                'amount' => 222.25,
                'currency' => 'USD',
                'method' => 'online',
                'received_at' => now()->format('Y-m-d H:i:s'),
                'reference' => 'TEST-GIVING-EDIT-001',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'reference' => 'TEST-GIVING-EDIT-001',
            'method' => 'online',
        ]);

        $this->actingAs($admin)
            ->put(route('finance.funds.update', $fund), [
                'name' => 'Updated Giving Fund',
                'code' => 'UGF',
                'description' => 'Updated fund description.',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('funds', [
            'id' => $fund->id,
            'name' => 'Updated Giving Fund',
            'code' => 'UGF',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('finance.funds.destroy', $fund))
            ->assertRedirect();

        $this->assertFalse($fund->fresh()->is_active);
    }

    public function test_finance_module_tracks_campus_income_expenses_and_ministry_giving(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $member = Member::query()->firstOrFail();
        $fund = Fund::query()->firstOrFail();
        $ministry = Ministry::query()->firstOrFail();
        $campusId = $ministry->campus_id ?: $admin->campus_id;

        $this->actingAs($admin)
            ->post(route('finance.transactions.store'), [
                'campus_id' => $campusId,
                'ministry_id' => $ministry->id,
                'fund_id' => $fund->id,
                'type' => 'income',
                'category' => 'events',
                'amount' => 500,
                'currency' => 'USD',
                'method' => 'bank',
                'frequency' => 'monthly',
                'occurred_at' => now()->format('Y-m-d H:i:s'),
                'reference' => 'TEST-FIN-INCOME-001',
                'vendor_or_source' => 'Youth Department',
                'status' => 'posted',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('finance.transactions.store'), [
                'campus_id' => $campusId,
                'ministry_id' => $ministry->id,
                'fund_id' => $fund->id,
                'type' => 'expense',
                'category' => 'supplies',
                'amount' => 120,
                'currency' => 'USD',
                'method' => 'card',
                'frequency' => 'one_time',
                'occurred_at' => now()->format('Y-m-d H:i:s'),
                'reference' => 'TEST-FIN-EXPENSE-001',
                'vendor_or_source' => 'Event Supplier',
                'status' => 'posted',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('finance.donations.store'), [
                'member_id' => $member->id,
                'fund_id' => $fund->id,
                'ministry_id' => $ministry->id,
                'campus_id' => $campusId,
                'amount' => 250,
                'currency' => 'USD',
                'method' => 'online',
                'giving_source' => 'ministry',
                'giving_frequency' => 'monthly',
                'received_at' => now()->format('Y-m-d H:i:s'),
                'reference' => 'TEST-MINISTRY-GIVING-001',
                'notes' => 'Department monthly giving.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('finance_transactions', ['reference' => 'TEST-FIN-INCOME-001', 'type' => 'income', 'ministry_id' => $ministry->id]);
        $this->assertDatabaseHas('finance_transactions', ['reference' => 'TEST-FIN-EXPENSE-001', 'type' => 'expense', 'ministry_id' => $ministry->id]);
        $this->assertDatabaseHas('donations', [
            'reference' => 'TEST-MINISTRY-GIVING-001',
            'ministry_id' => $ministry->id,
            'giving_source' => 'ministry',
            'giving_frequency' => 'monthly',
        ]);
        $this->assertTrue(FinanceTransaction::query()->where('reference', 'TEST-FIN-INCOME-001')->firstOrFail()->ministry()->exists());

        $this->actingAs($admin)
            ->get(route('finance.index'))
            ->assertOk()
            ->assertSee('Campus Income & Expenses', false)
            ->assertSee('Income & Expense Ledger', false)
            ->assertSee($ministry->name, false);

        $financeOfficer = User::query()->where('email', 'michael.thompson@klgc.org')->firstOrFail();

        $this->actingAs($financeOfficer)
            ->get(route('finance.index'))
            ->assertOk()
            ->assertSee('Giving & Finance', false);
    }

    public function test_ministry_finance_permission_only_allows_limited_contribution_entry(): void
    {
        $this->seed();
        $leader = User::query()->where('email', 'emily.davis@klgc.org')->firstOrFail();
        $member = Member::query()->where('campus_id', $leader->campus_id)->firstOrFail();
        $fund = Fund::query()->firstOrFail();
        $ministry = Ministry::query()->firstOrCreate(
            [
                'church_id' => $leader->church_id,
                'campus_id' => $leader->campus_id,
                'name' => 'Finance Permission Ministry',
            ],
            ['status' => 'active'],
        );

        $this->actingAs($leader)
            ->get(route('finance.index'))
            ->assertOk()
            ->assertSee('Ministry Contributions', false)
            ->assertSee('Record Donation', false)
            ->assertDontSee('Record Income / Expense', false)
            ->assertDontSee('Export', false)
            ->assertDontSee('Add Fund', false);

        $this->actingAs($leader)
            ->post(route('finance.donations.store'), [
                'member_id' => $member->id,
                'fund_id' => $fund->id,
                'ministry_id' => $ministry->id,
                'campus_id' => $leader->campus_id,
                'amount' => 80,
                'currency' => 'USD',
                'method' => 'cash',
                'giving_source' => 'ministry',
                'giving_frequency' => 'monthly',
                'received_at' => now()->format('Y-m-d H:i:s'),
                'reference' => 'TEST-MINISTRY-LIMITED-001',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('donations', [
            'reference' => 'TEST-MINISTRY-LIMITED-001',
            'created_by_user_id' => $leader->id,
            'ministry_id' => $ministry->id,
            'giving_source' => 'ministry',
        ]);

        $this->actingAs($leader)
            ->post(route('finance.transactions.store'), [
                'campus_id' => $leader->campus_id,
                'ministry_id' => $ministry->id,
                'type' => 'expense',
                'category' => 'supplies',
                'amount' => 25,
                'currency' => 'USD',
                'method' => 'cash',
                'frequency' => 'one_time',
                'occurred_at' => now()->format('Y-m-d H:i:s'),
                'reference' => 'TEST-MINISTRY-BLOCKED-EXPENSE',
                'status' => 'posted',
            ])
            ->assertForbidden();

        $this->actingAs($leader)
            ->get(route('finance.export'))
            ->assertForbidden();

        $viewer = User::query()->where('email', 'jessica.lee@klgc.org')->firstOrFail();

        $this->actingAs($viewer)
            ->get(route('finance.index'))
            ->assertForbidden();
    }

    public function test_bookstore_module_creates_order_items_and_reduces_stock(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $product = BookstoreProduct::query()->where('stock_quantity', '>', 3)->firstOrFail();
        $originalStock = $product->stock_quantity;

        $this->actingAs($admin)
            ->post(route('bookstore.orders.store'), [
                'bookstore_product_id' => $product->id,
                'quantity' => 2,
                'status' => 'paid',
                'campus_id' => $product->campus_id,
                'ordered_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $this->assertSame($originalStock - 2, $product->fresh()->stock_quantity);
        $this->assertTrue(BookstoreOrder::query()->latest()->firstOrFail()->items()->exists());
        $this->assertTrue(BookstoreOrderItem::query()->where('bookstore_product_id', $product->id)->exists());
    }

    public function test_bookstore_module_updates_and_cancels_orders_with_stock_restoration(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $product = BookstoreProduct::query()->where('stock_quantity', '>', 3)->firstOrFail();
        $originalStock = $product->stock_quantity;

        $this->actingAs($admin)
            ->post(route('bookstore.orders.store'), [
                'bookstore_product_id' => $product->id,
                'quantity' => 2,
                'status' => 'paid',
                'campus_id' => $product->campus_id,
                'ordered_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $order = BookstoreOrder::query()->latest()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('bookstore.orders.update', $order), [
                'status' => 'refunded',
                'campus_id' => $product->campus_id,
                'ordered_at' => $order->ordered_at?->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame($originalStock, $product->fresh()->stock_quantity);

        $this->actingAs($admin)
            ->post(route('bookstore.orders.store'), [
                'bookstore_product_id' => $product->id,
                'quantity' => 1,
                'status' => 'pending',
                'campus_id' => $product->campus_id,
                'ordered_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $secondOrder = BookstoreOrder::query()->where('status', 'pending')->latest()->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('bookstore.orders.destroy', $secondOrder))
            ->assertRedirect();

        $this->assertSame('cancelled', $secondOrder->fresh()->status);
        $this->assertSame($originalStock, $product->fresh()->stock_quantity);
    }

    public function test_bookstore_module_filters_and_exports_order_register(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $order = BookstoreOrder::query()->where('status', 'paid')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('bookstore.index', [
                'order_q' => $order->order_number,
                'order_status' => 'paid',
                'order_campus_id' => $order->campus_id,
            ]))
            ->assertOk()
            ->assertSee($order->order_number, false)
            ->assertSee('Order Register', false);

        $this->actingAs($admin)
            ->get(route('bookstore.orders.export', [
                'order_q' => $order->order_number,
                'order_status' => 'paid',
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_bookstore_library_borrows_returns_and_grants_digital_access(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $member = Member::query()->firstOrFail();
        $product = BookstoreProduct::query()->where('stock_quantity', '>', 1)->firstOrFail();
        $storeOnlyProduct = BookstoreProduct::query()->whereKeyNot($product->id)->firstOrFail();
        $storeOnlyProduct->update([
            'format' => 'hardcopy',
            'is_library_item' => false,
            'borrowable' => false,
            'rentable' => false,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get(route('bookstore.library'))
            ->assertOk()
            ->assertSee('Add Library Book', false)
            ->assertSee($storeOnlyProduct->name, false);

        $this->actingAs($admin)
            ->post(route('bookstore.products.store'), [
                'campus_id' => $member->campus_id,
                'name' => 'Library Add Test Book',
                'sku' => 'LIB-ADD-001',
                'category' => 'Library',
                'author' => 'Library Add Author',
                'isbn' => 'LIB-ADD-ISBN',
                'format' => 'hardcopy',
                'publisher' => 'Ecclesia Press',
                'is_library_item' => '1',
                'borrowable' => '1',
                'rentable' => '1',
                'rental_price' => 3.50,
                'price' => 12.99,
                'stock_quantity' => 2,
                'reorder_level' => 1,
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bookstore_products', [
            'name' => 'Library Add Test Book',
            'is_library_item' => true,
            'borrowable' => true,
            'rentable' => true,
        ]);

        $product->update([
            'author' => 'Library Author',
            'isbn' => 'LIB-ISBN-001',
            'format' => 'hardcopy',
            'is_library_item' => true,
            'borrowable' => true,
            'rentable' => true,
            'rental_price' => 4.99,
            'status' => 'active',
        ]);
        $originalStock = $product->stock_quantity;

        $this->actingAs($admin)
            ->post(route('bookstore.library.loans.store'), [
                'bookstore_product_id' => $product->id,
                'member_id' => $member->id,
                'campus_id' => $member->campus_id,
                'loan_type' => 'borrow',
                'checked_out_at' => now()->format('Y-m-d H:i:s'),
                'due_at' => now()->addDays(14)->format('Y-m-d H:i:s'),
                'currency' => 'USD',
                'notes' => 'Borrow test.',
            ])
            ->assertRedirect();

        $loan = BookstoreLibraryLoan::query()->where('bookstore_product_id', $product->id)->latest()->firstOrFail();
        $this->assertSame('borrow', $loan->loan_type);
        $this->assertSame('pending_approval', $loan->status);
        $this->assertSame('pending', $loan->approval_status);
        $this->assertSame($originalStock, $product->fresh()->stock_quantity);

        $approval = Approval::query()
            ->where('approvable_type', BookstoreLibraryLoan::class)
            ->where('approvable_id', $loan->id)
            ->firstOrFail();

        $this->assertSame('pending', $approval->status);
        $this->assertDatabaseHas('communication_deliveries', [
            'event_type' => 'LibraryLoanApprovalRequested',
            'channel' => 'in_app',
            'status' => 'delivered',
        ]);
        $this->assertDatabaseHas('communication_deliveries', [
            'event_type' => 'LibraryLoanApprovalRequested',
            'channel' => 'email',
            'status' => 'delivered',
        ]);

        $this->actingAs($admin)
            ->post(route('workflows.approvals.approve', $approval))
            ->assertRedirect()
            ->assertSessionHas('status', 'Approval approved and library loan activated.');

        $loan->refresh();
        $this->assertSame('active', $loan->status);
        $this->assertSame('approved', $loan->approval_status);
        $this->assertSame($originalStock - 1, $product->fresh()->stock_quantity);
        $this->assertGreaterThan(0, CommunicationDelivery::query()->where('event_type', 'LibraryLoanMemberNotification')->count());

        $this->actingAs($admin)
            ->delete(route('bookstore.library.loans.destroy', $loan))
            ->assertRedirect();

        $this->assertSame('returned', $loan->fresh()->status);
        $this->assertSame($originalStock, $product->fresh()->stock_quantity);

        $this->actingAs($admin)
            ->post(route('bookstore.library.loans.store'), [
                'bookstore_product_id' => $product->id,
                'member_id' => $member->id,
                'campus_id' => $member->campus_id,
                'loan_type' => 'rent',
                'checked_out_at' => now()->format('Y-m-d H:i:s'),
                'due_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
                'rental_amount' => 4.99,
                'currency' => 'USD',
                'notes' => 'Rent test.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Library request sent for approval.');

        $rentLoan = BookstoreLibraryLoan::query()->where('bookstore_product_id', $product->id)->where('loan_type', 'rent')->latest()->firstOrFail();
        $this->assertSame('pending_approval', $rentLoan->status);
        $this->assertSame('pending', $rentLoan->approval_status);
        $this->assertSame($originalStock, $product->fresh()->stock_quantity);

        $ebook = BookstoreProduct::query()->create([
            'church_id' => $member->church_id,
            'campus_id' => $member->campus_id,
            'name' => 'Digital Discipleship Ebook',
            'sku' => 'EBOOK-TEST-001',
            'category' => 'Discipleship',
            'author' => 'Ebook Author',
            'format' => 'ebook',
            'digital_url' => 'https://library.example.test/ebook',
            'is_library_item' => true,
            'borrowable' => false,
            'rentable' => false,
            'price' => 9.99,
            'stock_quantity' => 0,
            'reorder_level' => 0,
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('bookstore.library.loans.store'), [
                'bookstore_product_id' => $ebook->id,
                'member_id' => $member->id,
                'campus_id' => $member->campus_id,
                'loan_type' => 'digital_access',
                'checked_out_at' => now()->format('Y-m-d H:i:s'),
                'currency' => 'USD',
                'notes' => 'Digital access test.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bookstore_library_loans', [
            'bookstore_product_id' => $ebook->id,
            'member_id' => $member->id,
            'loan_type' => 'digital_access',
            'status' => 'active',
        ]);
        $this->assertSame(0, $ebook->fresh()->stock_quantity);
    }

    public function test_children_youth_module_creates_real_guardian_records(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $guardian = Member::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('children-youth.store'), [
                'first_name' => 'Youth',
                'last_name' => 'Record',
                'date_of_birth' => now()->subYears(12)->toDateString(),
                'age_group' => 'middle_school',
                'campus_id' => $guardian->campus_id,
                'guardian_member_id' => $guardian->id,
                'guardian_name' => 'Guardian Record',
                'guardian_phone' => '+1 555 000 1111',
                'consent_status' => 'approved',
                'check_in_status' => 'checked_in',
                'pickup_code' => 'YR-123',
                'status' => 'active',
            ])
            ->assertRedirect();

        $record = ChildrenYouthRecord::query()->where('first_name', 'Youth')->where('last_name', 'Record')->firstOrFail();
        $this->assertTrue($record->guardian()->exists());
    }

    public function test_counselling_module_creates_real_care_cases_with_permission(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $member = Member::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('counselling.store'), [
                'member_id' => $member->opaqueId(),
                'campus_id' => $member->campus?->opaqueId(),
                'assigned_user_id' => $admin->opaqueId(),
                'type' => 'Counseling',
                'priority' => 'high',
                'status' => 'assigned',
                'next_action' => 'Schedule first session',
                'notes' => 'Initial counselling intake.',
                'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect();

        $case = CareTask::query()->where('type', 'Counseling')->where('next_action', 'Schedule first session')->firstOrFail();
        $this->assertSame($member->id, $case->member_id);
        $this->assertSame($admin->id, $case->assigned_user_id);
    }

    public function test_counselling_module_books_sessions_and_blocks_conflicts(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@kingdomhub.test')->firstOrFail();
        $member = Member::query()->firstOrFail();
        $case = CareTask::query()->create([
            'church_id' => $member->church_id,
            'campus_id' => $member->campus_id,
            'member_id' => $member->id,
            'assigned_user_id' => $admin->id,
            'type' => 'Counseling',
            'priority' => 'medium',
            'status' => 'assigned',
            'next_action' => 'Initial booking',
        ]);

        $startsAt = now()->addDays(2)->setTime(10, 0);
        $endsAt = $startsAt->copy()->addHour();

        $this->actingAs($admin)
            ->post(route('counselling.bookings.store'), [
                'care_task_id' => $case->opaqueId(),
                'campus_id' => $member->campus?->opaqueId(),
                'counselor_user_id' => $admin->opaqueId(),
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'ends_at' => $endsAt->format('Y-m-d H:i:s'),
                'status' => 'confirmed',
                'location_type' => 'video',
                'location' => 'Pastoral Care Office',
                'meeting_url' => 'https://meet.example.test/session',
                'notes' => 'Prepare intake questions.',
            ])
            ->assertRedirect();

        $booking = CounsellingBooking::query()->where('care_task_id', $case->id)->firstOrFail();
        $this->assertSame($member->id, $booking->member_id);
        $this->assertSame($admin->id, $booking->counselor_user_id);
        $this->assertSame('confirmed', $booking->status);

        $this->actingAs($admin)
            ->post(route('counselling.bookings.store'), [
                'care_task_id' => $case->opaqueId(),
                'counselor_user_id' => $admin->opaqueId(),
                'starts_at' => $startsAt->copy()->addMinutes(15)->format('Y-m-d H:i:s'),
                'ends_at' => $endsAt->copy()->addMinutes(15)->format('Y-m-d H:i:s'),
                'status' => 'scheduled',
                'location_type' => 'in_person',
            ])
            ->assertSessionHasErrors('starts_at');
    }
}
