<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOperationalRecords;
use App\Models\Approval;
use App\Models\BookstoreLibraryLoan;
use App\Models\BookstoreOrder;
use App\Models\BookstoreOrderItem;
use App\Models\BookstoreProduct;
use App\Models\User;
use App\Models\Workflow;
use App\Services\ActivityLogger;
use App\Services\Communications\DomainNotificationService;
use App\Support\Csv;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BookstoreController extends Controller
{
    use ScopesOperationalRecords;

    public function __construct(private readonly DomainNotificationService $domainNotifications) {}

    private const PRODUCT_STATUSES = ['active', 'inactive', 'out_of_stock'];

    private const PRODUCT_FORMATS = ['hardcopy', 'ebook', 'bundle'];

    private const ORDER_STATUSES = ['paid', 'pending', 'refunded', 'cancelled'];

    private const LOAN_TYPES = ['borrow', 'rent', 'digital_access'];

    private const LOAN_STATUSES = ['pending_approval', 'active', 'returned', 'overdue', 'cancelled', 'expired'];

    private const LOAN_APPROVAL_STATUSES = ['not_required', 'pending', 'approved', 'rejected'];

    public function index(Request $request): View
    {
        $this->authorizePermission($request, 'manage bookstore');

        $productQuery = $this->scopeChurchCampus(BookstoreProduct::query(), $request);
        $this->applyProductFilters($productQuery, $request);
        $products = $productQuery->with('campus')->orderBy('name')->paginate(10, ['*'], 'products_page')->withQueryString();

        $orderQuery = $this->scopeChurchCampus(BookstoreOrder::query(), $request)->with(['member', 'campus', 'items.product']);
        $this->applyOrderFilters($orderQuery, $request);
        $orders = $orderQuery->latest('ordered_at')->paginate(10, ['*'], 'orders_page')->withQueryString();
        $baseProducts = $this->scopeChurchCampus(BookstoreProduct::query(), $request);
        $baseOrders = $this->scopeChurchCampus(BookstoreOrder::query(), $request);
        $currency = $this->currency($request);

        return view('bookstore.index', [
            'products' => $products,
            'orders' => $orders,
            'productOptions' => $this->scopeChurchCampus(BookstoreProduct::query(), $request)->with('campus')->where('status', 'active')->where('stock_quantity', '>', 0)->orderBy('name')->limit(500)->get(),
            'members' => $this->visibleMembers($request)->limit(300)->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'categories' => $this->scopeChurchCampus(BookstoreProduct::query(), $request)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'productStatuses' => self::PRODUCT_STATUSES,
            'productFormats' => self::PRODUCT_FORMATS,
            'orderStatuses' => self::ORDER_STATUSES,
            'loanTypes' => self::LOAN_TYPES,
            'loanStatuses' => self::LOAN_STATUSES,
            'currency' => $currency,
            'stats' => [
                'products' => (clone $baseProducts)->count(),
                'stock' => (clone $baseProducts)->sum('stock_quantity'),
                'low_stock' => (clone $baseProducts)->whereColumn('stock_quantity', '<=', 'reorder_level')->count(),
                'inventory_value' => Number::currency((float) (clone $baseProducts)->selectRaw('sum(stock_quantity * price) as value')->value('value'), $currency),
                'month_sales' => Number::currency((float) (clone $baseOrders)->where('status', 'paid')->whereBetween('ordered_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total_amount'), $currency),
            ],
            'orderStats' => $this->orderStats($request),
            'libraryStats' => $this->libraryStats($request),
            'activeLibraryLoans' => $this->scopeChurchCampus(BookstoreLibraryLoan::query(), $request)->with(['product', 'member', 'campus'])->whereIn('status', ['active', 'overdue'])->latest('checked_out_at')->limit(6)->get(),
            'categoryRows' => $this->categoryRows($request),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Book Store', 'url' => null],
            ],
        ]);
    }

    public function overview(Request $request): View
    {
        $this->authorizePermission($request, 'manage bookstore');

        $baseProducts = $this->scopeChurchCampus(BookstoreProduct::query(), $request);
        $baseOrders = $this->scopeChurchCampus(BookstoreOrder::query(), $request);
        $currency = $this->currency($request);

        return view('bookstore.overview', [
            'currency' => $currency,
            'stats' => [
                'products' => (clone $baseProducts)->count(),
                'stock' => (clone $baseProducts)->sum('stock_quantity'),
                'low_stock' => (clone $baseProducts)->whereColumn('stock_quantity', '<=', 'reorder_level')->count(),
                'month_sales' => Number::currency((float) (clone $baseOrders)->where('status', 'paid')->whereBetween('ordered_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total_amount'), $currency),
            ],
            'categoryRows' => $this->categoryRows($request),
            'lowStockProducts' => $this->scopeChurchCampus(BookstoreProduct::query(), $request)->with('campus')->whereColumn('stock_quantity', '<=', 'reorder_level')->orderBy('stock_quantity')->limit(8)->get(),
            'recentOrders' => $this->scopeChurchCampus(BookstoreOrder::query(), $request)->with(['member', 'campus', 'items.product'])->latest('ordered_at')->limit(8)->get(),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Book Store', 'url' => route('bookstore.index')],
                ['label' => 'Overview', 'url' => null],
            ],
        ]);
    }

    public function library(Request $request): View
    {
        $this->authorizePermission($request, 'manage bookstore');

        $catalogQuery = $this->scopeChurchCampus(BookstoreProduct::query(), $request)->with('campus');
        $this->applyProductFilters($catalogQuery, $request);

        $loanQuery = $this->scopeChurchCampus(BookstoreLibraryLoan::query(), $request)->with(['product', 'member', 'campus', 'handledBy', 'approval']);
        $this->applyLibraryLoanFilters($loanQuery, $request);

        return view('bookstore.library', [
            'catalogItems' => $catalogQuery->orderBy('name')->paginate(10, ['*'], 'catalog_page')->withQueryString(),
            'loans' => $loanQuery->latest('checked_out_at')->paginate(12, ['*'], 'loans_page')->withQueryString(),
            'libraryProductOptions' => $this->libraryProductOptions($request),
            'members' => $this->visibleMembers($request)->limit(300)->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'categories' => $this->scopeChurchCampus(BookstoreProduct::query(), $request)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'productStatuses' => self::PRODUCT_STATUSES,
            'productFormats' => self::PRODUCT_FORMATS,
            'loanTypes' => self::LOAN_TYPES,
            'loanStatuses' => self::LOAN_STATUSES,
            'approvalStatuses' => self::LOAN_APPROVAL_STATUSES,
            'currency' => $this->currency($request),
            'libraryStats' => $this->libraryStats($request),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Book Store', 'url' => route('bookstore.index')],
                ['label' => 'Church Library', 'url' => null],
            ],
        ]);
    }

    public function createProduct(Request $request): View
    {
        $this->authorizePermission($request, 'manage bookstore');

        return view('bookstore.create-product', $this->productFormData($request) + [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Book Store', 'url' => route('bookstore.index')],
                ['label' => 'Create Product', 'url' => null],
            ],
        ]);
    }

    public function editProduct(Request $request, BookstoreProduct $product): View
    {
        $this->authorizePermission($request, 'manage bookstore');
        $this->authorizeScopedRecord($request, $product);

        return view('bookstore.edit-product', $this->productFormData($request, $product) + [
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Book Store', 'url' => route('bookstore.index')],
                ['label' => $product->name, 'url' => null],
            ],
        ]);
    }

    public function createOrder(Request $request): View
    {
        $this->authorizePermission($request, 'manage bookstore');

        return view('bookstore.create-order', [
            'productOptions' => $this->scopeChurchCampus(BookstoreProduct::query(), $request)->where('status', 'active')->where('stock_quantity', '>', 0)->orderBy('name')->get(),
            'members' => $this->visibleMembers($request)->limit(300)->get(),
            'campuses' => $this->visibleCampuses($request)->get(),
            'orderStatuses' => self::ORDER_STATUSES,
            'currency' => $this->currency($request),
            'breadcrumbs' => [
                ['label' => 'Dashboard', 'url' => route('dashboard')],
                ['label' => 'Book Store', 'url' => route('bookstore.index')],
                ['label' => 'Create Order', 'url' => null],
            ],
        ]);
    }

    public function storeProduct(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage bookstore');
        $product = BookstoreProduct::query()->create($this->validatedProduct($request));

        $activityLogger->log('Bookstore', 'product_created', $product->name.' was added to the bookstore.', $product, ['resource' => 'Product', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Product added.');
    }

    public function updateProduct(Request $request, BookstoreProduct $product, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage bookstore');
        $this->authorizeScopedRecord($request, $product);
        $product->update($this->validatedProduct($request, $product));

        $activityLogger->log('Bookstore', 'product_updated', $product->name.' was updated.', $product, ['resource' => 'Product', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Product updated.');
    }

    public function destroyProduct(Request $request, BookstoreProduct $product, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage bookstore');
        $this->authorizeScopedRecord($request, $product);
        $name = $product->name;
        $product->delete();

        $activityLogger->log('Bookstore', 'product_archived', $name.' was archived.', null, ['resource' => 'Product', 'risk' => 'medium', 'status' => 'success'], $request);

        return back()->with('status', 'Product archived.');
    }

    public function storeOrder(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage bookstore');
        $validated = $request->validate([
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'bookstore_product_id' => ['required', 'integer', 'exists:bookstore_products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'status' => ['required', Rule::in(self::ORDER_STATUSES)],
            'ordered_at' => ['required', 'date'],
        ]);

        $order = DB::transaction(function () use ($request, $validated): BookstoreOrder {
            $product = $this->scopeChurchCampus(BookstoreProduct::query(), $request)->lockForUpdate()->findOrFail($validated['bookstore_product_id']);
            abort_if($product->stock_quantity < (int) $validated['quantity'], 422, 'Not enough stock for this order.');

            $quantity = (int) $validated['quantity'];
            $lineTotal = (float) $product->price * $quantity;
            $member = ! empty($validated['member_id']) ? $this->visibleMembers($request)->findOrFail($validated['member_id']) : null;
            $campusId = $this->validatedCampusId($request, $validated['campus_id'] ?? null) ?? $member?->campus_id ?? $product->campus_id;

            $order = BookstoreOrder::query()->create([
                'church_id' => $this->defaultChurchId($request),
                'campus_id' => $campusId,
                'member_id' => $member?->id,
                'order_number' => 'BK-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'total_amount' => $lineTotal,
                'currency' => $this->currency($request),
                'status' => $validated['status'],
                'ordered_at' => $validated['ordered_at'],
            ]);

            BookstoreOrderItem::query()->create([
                'bookstore_order_id' => $order->id,
                'bookstore_product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => $product->price,
                'line_total' => $lineTotal,
            ]);

            $product->decrement('stock_quantity', $quantity);
            if ($product->fresh()->stock_quantity <= 0) {
                $product->update(['status' => 'out_of_stock']);
            }

            return $order;
        });

        $activityLogger->log('Bookstore', 'order_created', 'Bookstore order '.$order->order_number.' was recorded.', $order, ['resource' => 'Bookstore Order', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Order recorded.');
    }

    public function updateOrder(Request $request, BookstoreOrder $order, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage bookstore');
        $this->authorizeScopedRecord($request, $order);

        $validated = $this->validatedOrderUpdate($request);
        abort_if(in_array($order->status, ['cancelled', 'refunded'], true) && $validated['status'] !== $order->status, 422, 'Closed orders cannot be reopened.');

        $member = ! empty($validated['member_id']) ? $this->visibleMembers($request)->findOrFail($validated['member_id']) : null;
        $campusId = $this->validatedCampusId($request, $validated['campus_id'] ?? null) ?? $member?->campus_id;

        DB::transaction(function () use ($order, $validated, $campusId, $member): void {
            if (! in_array($order->status, ['cancelled', 'refunded'], true) && in_array($validated['status'], ['cancelled', 'refunded'], true)) {
                $this->restoreOrderStock($order);
            }

            $order->update([
                'campus_id' => $campusId,
                'member_id' => $member?->id,
                'status' => $validated['status'],
                'ordered_at' => $validated['ordered_at'],
            ]);
        });

        $activityLogger->log('Bookstore', 'order_updated', 'Bookstore order '.$order->order_number.' was updated.', $order, ['resource' => 'Bookstore Order', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Order updated.');
    }

    public function destroyOrder(Request $request, BookstoreOrder $order, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage bookstore');
        $this->authorizeScopedRecord($request, $order);
        abort_if($order->status === 'refunded', 422, 'Refunded orders are already closed.');

        DB::transaction(function () use ($order): void {
            if (! in_array($order->status, ['cancelled', 'refunded'], true)) {
                $this->restoreOrderStock($order);
            }

            $order->update(['status' => 'cancelled']);
        });

        $activityLogger->log('Bookstore', 'order_cancelled', 'Bookstore order '.$order->order_number.' was cancelled.', $order, ['resource' => 'Bookstore Order', 'risk' => 'medium', 'status' => 'success'], $request);

        return back()->with('status', 'Order cancelled.');
    }

    public function storeLibraryLoan(Request $request, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage bookstore');
        $validated = $this->validatedLibraryLoan($request);
        $requiresApproval = in_array($validated['loan_type'], ['borrow', 'rent'], true);

        $loan = DB::transaction(function () use ($request, $validated, $requiresApproval): BookstoreLibraryLoan {
            $product = $this->scopeChurchCampus(BookstoreProduct::query(), $request)->lockForUpdate()->findOrFail($validated['bookstore_product_id']);
            $this->authorizeLibraryProductAction($product, $validated['loan_type']);

            if (in_array($validated['loan_type'], ['borrow', 'rent'], true)) {
                abort_if($product->stock_quantity < 1, 422, 'No physical copies are available for this library action.');
            }

            $member = $this->visibleMembers($request)->findOrFail($validated['member_id']);
            $campusId = $this->validatedCampusId($request, $validated['campus_id'] ?? null) ?? $member->campus_id ?? $product->campus_id;

            return BookstoreLibraryLoan::query()->create([
                'church_id' => $this->defaultChurchId($request),
                'campus_id' => $campusId,
                'bookstore_product_id' => $product->id,
                'member_id' => $member->id,
                'handled_by_user_id' => $request->user()?->id,
                'loan_number' => 'LIB-'.now()->format('YmdHis').'-'.random_int(100, 999),
                'loan_type' => $validated['loan_type'],
                'status' => $requiresApproval ? 'pending_approval' : 'active',
                'approval_status' => $requiresApproval ? 'pending' : 'not_required',
                'checked_out_at' => $validated['checked_out_at'],
                'due_at' => $validated['due_at'],
                'returned_at' => null,
                'rental_amount' => $validated['loan_type'] === 'rent' ? ($validated['rental_amount'] ?? $product->rental_price ?? 0) : null,
                'currency' => $validated['currency'],
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        if ($requiresApproval) {
            $approval = $this->requestLibraryLoanApproval($request, $loan);
            $this->notifyLibraryLoanRequester($loan, 'Library request submitted', 'Your '.$loan->loan_type.' request for '.$loan->product?->name.' is waiting for approval.');
            $activityLogger->log('Bookstore', 'library_loan_approval_requested', 'Library record '.$loan->loan_number.' was sent for approval.', $loan, ['resource' => 'Library Loan', 'risk' => 'medium', 'status' => 'success', 'approval_id' => $approval->id], $request);

            return back()->with('status', 'Library request sent for approval.');
        }

        $this->notifyLibraryLoanRequester($loan, 'Library access granted', 'Digital access for '.$loan->product?->name.' has been activated.');

        $activityLogger->log('Bookstore', 'library_loan_created', 'Library record '.$loan->loan_number.' was created.', $loan, ['resource' => 'Library Loan', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Library record created.');
    }

    public function updateLibraryLoan(Request $request, BookstoreLibraryLoan $loan, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage bookstore');
        $this->authorizeScopedRecord($request, $loan);

        $validated = $request->validate([
            'status' => ['required', Rule::in(self::LOAN_STATUSES)],
            'due_at' => ['nullable', 'date'],
            'returned_at' => ['nullable', 'date'],
            'rental_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($loan, $validated): void {
            abort_if($loan->approval_status === 'pending' && $validated['status'] !== 'pending_approval', 422, 'Pending library requests must be approved or rejected from Workflow & Approvals.');

            if (! in_array($loan->status, ['returned', 'cancelled', 'expired'], true) && in_array($validated['status'], ['returned', 'cancelled', 'expired'], true)) {
                $this->restoreLibraryStock($loan);
            }

            $loan->update([
                'status' => $validated['status'],
                'due_at' => $validated['due_at'] ?? null,
                'returned_at' => in_array($validated['status'], ['returned', 'cancelled', 'expired'], true)
                    ? ($validated['returned_at'] ?? now())
                    : null,
                'rental_amount' => $validated['rental_amount'] ?? null,
                'currency' => $validated['currency'],
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        $activityLogger->log('Bookstore', 'library_loan_updated', 'Library record '.$loan->loan_number.' was updated.', $loan, ['resource' => 'Library Loan', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', 'Library record updated.');
    }

    public function destroyLibraryLoan(Request $request, BookstoreLibraryLoan $loan, ActivityLogger $activityLogger): RedirectResponse
    {
        $this->authorizePermission($request, 'manage bookstore');
        $this->authorizeScopedRecord($request, $loan);
        $wasPendingApproval = $loan->approval_status === 'pending';

        DB::transaction(function () use ($loan): void {
            if (! in_array($loan->status, ['returned', 'cancelled', 'expired'], true)) {
                $this->restoreLibraryStock($loan);
            }

            if ($loan->approval_status === 'pending') {
                $loan->approval?->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                    'notes' => 'Cancelled from the library register before approval.',
                ]);
            }

            $loan->update([
                'status' => $loan->approval_status === 'pending' ? 'cancelled' : 'returned',
                'approval_status' => $loan->approval_status === 'pending' ? 'rejected' : $loan->approval_status,
                'returned_at' => now(),
            ]);
        });

        if ($wasPendingApproval) {
            $this->notifyLibraryLoanRequester($loan->fresh() ?? $loan, 'Library request cancelled', 'The '.$loan->loan_type.' request for '.$loan->product?->name.' was cancelled before approval.');
        }

        $activityLogger->log('Bookstore', $wasPendingApproval ? 'library_loan_cancelled' : 'library_loan_returned', 'Library record '.$loan->loan_number.($wasPendingApproval ? ' was cancelled.' : ' was returned.'), $loan, ['resource' => 'Library Loan', 'risk' => 'low', 'status' => 'success'], $request);

        return back()->with('status', $wasPendingApproval ? 'Library request cancelled.' : 'Library item returned.');
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizePermission($request, 'manage bookstore');

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            Csv::write($handle, ['SKU', 'Product', 'Category', 'Campus', 'Price', 'Stock', 'Reorder Level', 'Status']);
            $this->scopeChurchCampus(BookstoreProduct::query(), $request)->with('campus')->orderBy('name')->lazy(100)->each(fn (BookstoreProduct $product) => Csv::write($handle, [
                $product->sku,
                $product->name,
                $product->category,
                $product->campus?->name,
                $product->price,
                $product->stock_quantity,
                $product->reorder_level,
                $product->status,
            ]));
            fclose($handle);
        }, 'bookstore-products-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportOrders(Request $request): StreamedResponse
    {
        $this->authorizePermission($request, 'manage bookstore');

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            Csv::write($handle, ['Order Number', 'Date', 'Member', 'Campus', 'Items', 'Status', 'Total', 'Currency']);
            $query = $this->scopeChurchCampus(BookstoreOrder::query(), $request)->with(['member', 'campus', 'items'])->latest('ordered_at');
            $this->applyOrderFilters($query, $request);

            $query->lazy(100)->each(fn (BookstoreOrder $order) => Csv::write($handle, [
                $order->order_number,
                $order->ordered_at?->format('Y-m-d H:i'),
                $order->member ? $order->member->first_name.' '.$order->member->last_name : 'Walk-in',
                $order->campus?->name,
                $order->items->map(fn (BookstoreOrderItem $item): string => $item->product_name.' x'.$item->quantity)->implode('; '),
                $order->status,
                $order->total_amount,
                $order->currency,
            ]));
            fclose($handle);
        }, 'bookstore-orders-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportLibrary(Request $request): StreamedResponse
    {
        $this->authorizePermission($request, 'manage bookstore');

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            Csv::write($handle, ['Loan Number', 'Type', 'Status', 'Approval', 'Product', 'Member', 'Campus', 'Checked Out', 'Due', 'Returned', 'Rental Amount', 'Currency']);
            $query = $this->scopeChurchCampus(BookstoreLibraryLoan::query(), $request)->with(['product', 'member', 'campus'])->latest('checked_out_at');
            $this->applyLibraryLoanFilters($query, $request);

            $query->lazy(100)->each(fn (BookstoreLibraryLoan $loan) => Csv::write($handle, [
                $loan->loan_number,
                $loan->loan_type,
                $loan->status,
                $loan->approval_status,
                $loan->product?->name,
                $loan->member ? $loan->member->first_name.' '.$loan->member->last_name : '',
                $loan->campus?->name,
                $loan->checked_out_at?->format('Y-m-d H:i'),
                $loan->due_at?->format('Y-m-d H:i'),
                $loan->returned_at?->format('Y-m-d H:i'),
                $loan->rental_amount,
                $loan->currency,
            ]));
            fclose($handle);
        }, 'church-library-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function validatedProduct(Request $request, ?BookstoreProduct $product = null): array
    {
        $validated = $request->validate([
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'name' => ['required', 'string', 'max:180'],
            'sku' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:80'],
            'author' => ['nullable', 'string', 'max:180'],
            'isbn' => ['nullable', 'string', 'max:80'],
            'format' => ['required', Rule::in(self::PRODUCT_FORMATS)],
            'publisher' => ['nullable', 'string', 'max:180'],
            'digital_url' => ['nullable', 'url', 'max:500'],
            'is_library_item' => ['nullable', 'boolean'],
            'borrowable' => ['nullable', 'boolean'],
            'rentable' => ['nullable', 'boolean'],
            'rental_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:999999999.99'],
            'stock_quantity' => ['required', 'integer', 'min:0', 'max:999999'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:999999'],
            'status' => ['required', Rule::in(self::PRODUCT_STATUSES)],
        ]);

        $validated['church_id'] = $this->defaultChurchId($request);
        $validated['campus_id'] = $this->validatedCampusId($request, $validated['campus_id'] ?? null);
        $validated['is_library_item'] = (bool) ($validated['is_library_item'] ?? false);
        $validated['borrowable'] = (bool) ($validated['borrowable'] ?? false);
        $validated['rentable'] = (bool) ($validated['rentable'] ?? false);

        if (filled($validated['sku'] ?? null)) {
            $duplicate = BookstoreProduct::query()
                ->where('sku', $validated['sku'])
                ->when($product, fn (Builder $query) => $query->whereKeyNot($product->id))
                ->exists();
            abort_if($duplicate, 422, 'A product with this SKU already exists.');
        }

        return $validated;
    }

    private function validatedOrderUpdate(Request $request): array
    {
        return $request->validate([
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'member_id' => ['nullable', 'integer', 'exists:members,id'],
            'status' => ['required', Rule::in(self::ORDER_STATUSES)],
            'ordered_at' => ['required', 'date'],
        ]);
    }

    private function productFormData(Request $request, ?BookstoreProduct $product = null): array
    {
        return [
            'product' => $product,
            'campuses' => $this->visibleCampuses($request)->get(),
            'categories' => $this->scopeChurchCampus(BookstoreProduct::query(), $request)->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'),
            'productStatuses' => self::PRODUCT_STATUSES,
            'productFormats' => self::PRODUCT_FORMATS,
            'currency' => $this->currency($request),
        ];
    }

    private function applyProductFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('q'), function (Builder $query) use ($request): void {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(fn (Builder $search) => $search->where('name', 'like', $term)->orWhere('sku', 'like', $term)->orWhere('category', 'like', $term));
        });
        $query->when($request->filled('category'), fn (Builder $query) => $query->where('category', $request->string('category')));
        $query->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')));
        $query->when($request->filled('campus_id'), fn (Builder $query) => $query->where('campus_id', (int) $request->query('campus_id')));
        $query->when($request->filled('format'), fn (Builder $query) => $query->where('format', $request->string('format')));
    }

    private function applyOrderFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('order_q'), function (Builder $query) use ($request): void {
            $term = '%'.$request->string('order_q')->toString().'%';
            $query->where(fn (Builder $search) => $search
                ->where('order_number', 'like', $term)
                ->orWhereHas('member', fn (Builder $member) => $member->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('email', 'like', $term))
                ->orWhereHas('items', fn (Builder $item) => $item->where('product_name', 'like', $term)));
        });
        $query->when($request->filled('order_status'), fn (Builder $query) => $query->where('status', $request->string('order_status')));
        $query->when($request->filled('order_campus_id'), fn (Builder $query) => $query->where('campus_id', (int) $request->query('order_campus_id')));
    }

    private function applyLibraryLoanFilters(Builder $query, Request $request): void
    {
        $query->when($request->filled('loan_q'), function (Builder $query) use ($request): void {
            $term = '%'.$request->string('loan_q')->toString().'%';
            $query->where(fn (Builder $search) => $search
                ->where('loan_number', 'like', $term)
                ->orWhereHas('product', fn (Builder $product) => $product->where('name', 'like', $term)->orWhere('author', 'like', $term)->orWhere('isbn', 'like', $term))
                ->orWhereHas('member', fn (Builder $member) => $member->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('email', 'like', $term)));
        });
        $query->when($request->filled('loan_type'), fn (Builder $query) => $query->where('loan_type', $request->string('loan_type')));
        $query->when($request->filled('loan_status'), fn (Builder $query) => $query->where('status', $request->string('loan_status')));
        $query->when($request->filled('approval_status'), fn (Builder $query) => $query->where('approval_status', $request->string('approval_status')));
        $query->when($request->filled('loan_campus_id'), fn (Builder $query) => $query->where('campus_id', (int) $request->query('loan_campus_id')));
    }

    private function categoryRows(Request $request): array
    {
        return $this->scopeChurchCampus(BookstoreProduct::query(), $request)
            ->select('category', DB::raw('count(*) as products'), DB::raw('sum(stock_quantity * price) as value'))
            ->groupBy('category')
            ->orderByDesc('value')
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->category ?: 'Uncategorized',
                'products' => (int) $row->products,
                'value' => (float) $row->value,
            ])
            ->all();
    }

    private function orderStats(Request $request): array
    {
        $baseOrders = $this->scopeChurchCampus(BookstoreOrder::query(), $request);
        $currency = $this->currency($request);

        return [
            'today' => (clone $baseOrders)->whereDate('ordered_at', now()->toDateString())->count(),
            'week' => (clone $baseOrders)->whereBetween('ordered_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'pending' => (clone $baseOrders)->where('status', 'pending')->count(),
            'paid' => (clone $baseOrders)->where('status', 'paid')->count(),
            'refunded' => (clone $baseOrders)->where('status', 'refunded')->count(),
            'today_sales' => Number::currency((float) (clone $baseOrders)->where('status', 'paid')->whereDate('ordered_at', now()->toDateString())->sum('total_amount'), $currency),
            'week_sales' => Number::currency((float) (clone $baseOrders)->where('status', 'paid')->whereBetween('ordered_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('total_amount'), $currency),
        ];
    }

    private function libraryStats(Request $request): array
    {
        $baseLoans = $this->scopeChurchCampus(BookstoreLibraryLoan::query(), $request);
        $baseProducts = $this->scopeChurchCampus(BookstoreProduct::query(), $request);

        return [
            'bookstore' => (clone $baseProducts)->count(),
            'catalog' => (clone $baseProducts)->where(fn (Builder $query) => $query->where('is_library_item', true)->orWhere('borrowable', true)->orWhere('rentable', true)->orWhereIn('format', ['ebook', 'bundle']))->count(),
            'active' => (clone $baseLoans)->where('status', 'active')->count(),
            'pending' => (clone $baseLoans)->where('approval_status', 'pending')->count(),
            'overdue' => (clone $baseLoans)->where(fn (Builder $query) => $query->where('status', 'overdue')->orWhere(fn (Builder $due) => $due->where('status', 'active')->whereNotNull('due_at')->where('due_at', '<', now())))->count(),
            'digital' => (clone $baseLoans)->where('loan_type', 'digital_access')->count(),
            'rentals' => (clone $baseLoans)->where('loan_type', 'rent')->whereIn('status', ['active', 'overdue'])->count(),
        ];
    }

    private function validatedLibraryLoan(Request $request): array
    {
        $validated = $request->validate([
            'bookstore_product_id' => ['required', 'integer', 'exists:bookstore_products,id'],
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'loan_type' => ['required', Rule::in(self::LOAN_TYPES)],
            'checked_out_at' => ['required', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:checked_out_at'],
            'rental_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (empty($validated['due_at']) && $validated['loan_type'] === 'borrow') {
            $validated['due_at'] = now()->addDays(14)->format('Y-m-d H:i:s');
        }

        if (empty($validated['due_at']) && $validated['loan_type'] === 'rent') {
            $validated['due_at'] = now()->addDays(7)->format('Y-m-d H:i:s');
        }

        if (empty($validated['due_at']) && $validated['loan_type'] === 'digital_access') {
            $validated['due_at'] = now()->addYear()->format('Y-m-d H:i:s');
        }

        return $validated;
    }

    private function libraryProductOptions(Request $request)
    {
        return $this->scopeChurchCampus(BookstoreProduct::query(), $request)
            ->where('status', 'active')
            ->where(fn (Builder $query) => $query
                ->where('is_library_item', true)
                ->orWhere('borrowable', true)
                ->orWhere('rentable', true)
                ->orWhereIn('format', ['ebook', 'bundle']))
            ->orderBy('name')
            ->limit(500)
            ->get();
    }

    private function authorizeLibraryProductAction(BookstoreProduct $product, string $loanType): void
    {
        abort_unless($product->is_library_item || $product->borrowable || $product->rentable || in_array($product->format, ['ebook', 'bundle'], true), 422, 'This product is not enabled for the church library.');

        if ($loanType === 'borrow') {
            abort_unless($product->borrowable, 422, 'This product is not enabled for borrowing.');
        }

        if ($loanType === 'rent') {
            abort_unless($product->rentable, 422, 'This product is not enabled for rentals.');
        }

        if ($loanType === 'digital_access') {
            abort_unless(in_array($product->format, ['ebook', 'bundle'], true) || filled($product->digital_url), 422, 'This product is not enabled for digital access.');
        }
    }

    private function requestLibraryLoanApproval(Request $request, BookstoreLibraryLoan $loan): Approval
    {
        $loan->loadMissing(['product', 'member', 'campus']);

        $workflow = Workflow::query()->firstOrCreate(
            [
                'church_id' => $loan->church_id,
                'module' => 'bookstore_library',
                'name' => 'Library Borrow/Rent Approval',
            ],
            [
                'status' => 'active',
                'steps' => [
                    'description' => 'Borrow and rent requests must be approved before a physical copy is issued.',
                    'approval_type' => 'sequential',
                    'timeout_hours' => 72,
                    'steps' => [
                        [
                            'position' => 1,
                            'label' => 'Library Review',
                            'role' => 'Book Store Manager',
                            'mode' => 'required',
                            'required' => true,
                            'instructions' => 'Confirm member eligibility, due date, rental amount, and available stock.',
                        ],
                    ],
                ],
            ],
        );

        $approval = Approval::query()->create([
            'church_id' => $loan->church_id,
            'workflow_id' => $workflow->id,
            'approvable_type' => $loan::class,
            'approvable_id' => $loan->id,
            'action' => 'library_'.$loan->loan_type,
            'requested_by' => $request->user()?->id,
            'status' => 'pending',
            'notes' => Str::headline($loan->loan_type).' request requires approval before checkout.',
            'payload' => [
                'loan_number' => $loan->loan_number,
                'loan_type' => $loan->loan_type,
                'book' => $loan->product?->name,
                'member' => trim(($loan->member?->first_name ?? '').' '.($loan->member?->last_name ?? '')),
                'campus' => $loan->campus?->name,
                'due_at' => $loan->due_at?->toDateTimeString(),
                'rental_amount' => $loan->rental_amount,
                'currency' => $loan->currency,
            ],
            'submitted_at' => now(),
        ]);

        $this->notifyLibraryApprovers($approval, $loan);

        return $approval;
    }

    private function notifyLibraryApprovers(Approval $approval, BookstoreLibraryLoan $loan): void
    {
        $loan->loadMissing(['product', 'member', 'campus']);
        $subject = 'Library approval needed: '.$loan->product?->name;
        $message = trim(($loan->member?->first_name ?? '').' '.($loan->member?->last_name ?? '')).' requested to '.str_replace('_', ' ', $loan->loan_type).' '.$loan->product?->name.'. Review it in Workflow & Approvals.';

        User::query()
            ->where(fn (Builder $query) => $query->where('church_id', $approval->church_id)->orWhereNull('church_id'))
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', ['Super Administrator', 'Church Administrator', 'Senior Pastor', 'Book Store Manager']))
                    ->orWhereHas('roles.permissions', fn (Builder $permissions) => $permissions->whereIn('name', ['manage workflows', 'manage bookstore']));
            })
            ->get()
            ->each(fn (User $user) => $this->domainNotifications->user(
                $user,
                'LibraryLoanApprovalRequested',
                'system',
                $subject,
                $message,
                ['in_app', 'email'],
                ['url' => route('workflows.index')],
                true,
            ));
    }

    private function notifyLibraryLoanRequester(BookstoreLibraryLoan $loan, string $subject, string $message): void
    {
        $loan->loadMissing(['handledBy', 'member']);

        if ($loan->handledBy) {
            $this->domainNotifications->user($loan->handledBy, 'LibraryLoanNotification', 'system', $subject, $message, ['in_app', 'email'], ['url' => route('bookstore.index')]);
        }

        if ($loan->member) {
            $this->domainNotifications->member($loan->member, 'LibraryLoanMemberNotification', 'system', $subject, $message, ['in_app', 'email'], ['url' => route('bookstore.index')]);
        }
    }

    private function restoreLibraryStock(BookstoreLibraryLoan $loan): void
    {
        if (! in_array($loan->loan_type, ['borrow', 'rent'], true) || ! in_array($loan->status, ['active', 'overdue'], true)) {
            return;
        }

        $product = BookstoreProduct::withTrashed()->whereKey($loan->bookstore_product_id)->first();
        if (! $product) {
            return;
        }

        $product->increment('stock_quantity');
        if (! $product->trashed() && $product->fresh()?->status === 'out_of_stock') {
            $product->update(['status' => 'active']);
        }
    }

    private function restoreOrderStock(BookstoreOrder $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            $product = BookstoreProduct::withTrashed()->whereKey($item->bookstore_product_id)->first();

            if (! $product) {
                continue;
            }

            $product->increment('stock_quantity', $item->quantity);

            if (! $product->trashed() && $product->fresh()?->status === 'out_of_stock') {
                $product->update(['status' => 'active']);
            }
        }
    }

    private function currency(Request $request): string
    {
        return (string) ($request->user()?->church?->currency ?: config('church.currency', 'USD'));
    }
}
