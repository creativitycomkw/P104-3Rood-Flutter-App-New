<?php
namespace App\Http\Controllers;

ini_set('max_execution_time', 600000);
ini_set('memory_limit', '-1');

use App\Brand;
use App\Category;
use App\CategoryTable;
use App\Currency;
use App\DiamondClarity;
use App\DiamondColor;
use App\DiamondShape;
use App\MaterialColor;
use App\Payment;
use App\Product;
use App\ProductBatch;
use App\ProductKarat;
use App\ProductMaterial;
use App\ProductPurchase;
use App\ProductReturn;
use App\ProductStatus;
use App\ProductVariant;
use App\Product_Sale;
use App\Product_Warehouse;
use App\Purchase;
use App\SecondCategory;
use App\StoneShape;
use App\Supplier;
use App\TableValue;
use App\Tax;
use App\ThirdCategory;
use App\Traits\CacheForget;
use App\Traits\TenantInfo;
use App\Unit;
use App\Variant;
use App\Warehouse;
use Auth;
use DB;
use DNS1D;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Keygen;
use Spatie\Permission\Models\Role;

class ProductController extends Controller
{
    use CacheForget;
    use TenantInfo;

    public function index(Request $request)
    { //session::destroy();
        $role = Role::find(Auth::user()->role_id);
        if ($role->hasPermissionTo('products-index')) {
            $permissions = Role::findByName($role->name)->permissions;
            foreach ($permissions as $permission) {
                $all_permission[] = $permission->name;
            }

            if (empty($all_permission)) {
                $all_permission[] = 'dummy text';
            }

            $role_id            = $role->id;
            $numberOfProduct    = Product::where('is_active', true)->count();
            $lims_supplier_list = Supplier::where('is_active', true)->get();
            $categories         = SecondCategory::where('is_active', true)->get();
            $sub_categories     = ThirdCategory::where('is_active', true)->get();
            $suppliers          = Supplier::where('is_active', true)->get();
            $status             = ProductStatus::where('is_active', true)->get();
            $lims_currency_list = Currency::where('is_active', true)->where('code', '!=', 'KD')->get();
            $route              = route('products.index') . '?status=0';
            $category_id        = null;
            $subcategory_id     = null;
            $supplier_id        = null;
            $min_price          = null;
            $max_price          = null;
            $current_status     = null;
            $gem_type           = null;
            if (isset($request->query()['category']) && ! empty($request->query()['category'])) {
                $category_id = $request->query()['category'];
            }
            if (isset($request->query()['sub_category']) && ! empty($request->query()['sub_category'])) {
                $subcategory_id = $request->query()['sub_category'];
            }
            if (isset($request->query()['supplier']) && ! empty($request->query()['supplier'])) {
                $supplier_id = $request->query()['supplier'];
            }
            if (isset($request->query()['min_price']) && ! empty($request->query()['min_price'])) {
                $min_price = $request->query()['min_price'];
            }
            if (isset($request->query()['max_price']) && ! empty($request->query()['max_price'])) {
                $max_price = $request->query()['max_price'];
            }
            if (isset($request->query()['status']) && ! empty($request->query()['status'])) {
                $current_status = $request->query()['status'];
            }
            if (isset($request->query()['gem_type']) && ! empty($request->query()['gem_type'])) {
                $gem_type = $request->query()['gem_type'];
            }

            return view('backend.product.index', compact('lims_supplier_list', 'gem_type', 'current_status', 'min_price', 'max_price', 'supplier_id', 'category_id', 'subcategory_id', 'route', 'status', 'all_permission', 'suppliers', 'role_id', 'numberOfProduct', 'lims_currency_list', 'categories', 'sub_categories'));
        } else {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

    }

    public function productData(Request $request)
    {
        $columns = [
            2 => 'name',
            3 => 'category_id',
            4 => 'qty',
            5 => 'supplier_id',
        ];
        $marged_products = Product::where('type', 2)
            ->whereNotNull('product_list')
            ->pluck('product_list')
            ->toArray();
        $marged_product_id = [];
//        if (!empty($marged_products)) {
//            foreach ($marged_products as $marged_product) {
//                $explode_id = explode(',', $marged_product);
//                if (!empty($explode_id)) {
//                    foreach ($explode_id as $explode) {
//                        $marged_product_id[] = $explode;
//                    }
//                }
//            }
//        }

        $totalData = Product::where('is_active', true)
            ->when($request->query('status'), function ($query) use ($request) {
                return $query->where('item_status', $request->query('status'));
            })
            ->when(! $request->query('status'), function ($query) use ($request) {
                return $query->whereNotIn('item_status', [1, 2]);
            })
            ->when($request->input('category'), function ($query) use ($request) {
                return $query->where('sub_category_id', $request->input('category'));
            })
            ->when($request->input('sub_category'), function ($query) use ($request) {
                return $query->where('third_category_id', $request->input('sub_category'));
            })
            ->when($request->input('supplier'), function ($query) use ($request) {
                return $query->where('supplier_id', $request->input('supplier'));
            })
            ->when($request->input('all_status'), function ($query) use ($request) {
                return $query->where('item_status', $request->input('all_status'));
            })
            ->when($request->input('gem_type'), function ($query) use ($request) {
                $gem_type = $request->input('gem_type');
                if ($gem_type == 'colored_stone') {
                    return $query->whereNotNull('stone_type');
                } elseif ($gem_type == 'diamond') {
                    return $query->whereNotNull('diamond_shape_id');
                } else {
                    return $query->where('product_material_id', $gem_type);
                }
            })
            ->when($request->input('min_price'), function ($query) use ($request) {
                return $query->whereBetween('tag_price', [$request->input('min_price'), $request->input('max_price')]);
            })
            ->whereNotIn('id', $marged_product_id)
            ->count();
        $totalFiltered = $totalData;

        if ($request->input('length') != -1) {
            $limit = $request->input('length');
        } else {
            $limit = $totalData;
        }

        $start = $request->input('start');
//        $order = 'products.' . $columns[$request->input('order.0.column')];
        $dir   = $request->input('order.0.dir');
        $order = 'id';
        $dir   = 'desc';

        if (empty($request->input('search.value'))) {
            $products = Product::with('category')->offset($start)
                ->where('is_active', true)
                ->when($request->query('status'), function ($query) use ($request) {
                    return $query->where('item_status', $request->query('status'));
                })
                ->when(! $request->query('status'), function ($query) use ($request) {
                    return $query->whereNotIn('item_status', [1, 2]);
                })
                ->when($request->input('category'), function ($query) use ($request) {
                    return $query->where('sub_category_id', $request->input('category'));
                })
                ->when($request->input('sub_category'), function ($query) use ($request) {
                    return $query->where('third_category_id', $request->input('sub_category'));
                })
                ->when($request->input('supplier'), function ($query) use ($request) {
                    return $query->where('supplier_id', $request->input('supplier'));
                })
                ->when($request->input('min_price'), function ($query) use ($request) {
                    return $query->whereBetween('tag_price', [(int) $request->input('min_price'), (int) $request->input('max_price')]);
                })
                ->when($request->input('all_status'), function ($query) use ($request) {
                    return $query->where('item_status', $request->input('all_status'));
                })
                ->when($request->input('gem_type'), function ($query) use ($request) {
                    $gem_type = $request->input('gem_type');
                    if ($gem_type == 'colored_stone') {
                        return $query->whereNotNull('stone_type')->where('stone_type', '!=', 'NULL')->where('stone_weight', '>', 0);
                    } elseif ($gem_type == 'diamond') {
                        return $query->whereNotNull('diamond_carat_id')->where('diamond_carat_id', '!=', 'NULL')->where('diamond_carat_id', '>', 0);
                    } else {
                        return $query->where('product_material_id', $gem_type);
                    }
                })
                ->where('is_global', 0)
                ->whereNotIn('id', $marged_product_id)
                ->limit($limit)
            // ->orderBy($order, $dir)
                ->orderBy('rh_reference_id', 'ASC')
            // ->orderByRaw('RIGHT(rh_reference_id, 3)')
                ->get();
        } else {
            $search   = $request->input('search.value');
            $products = Product::select('products.*')
                ->with('category', 'status')
                ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
                ->where(function ($query) use ($search) {
                    return $query->where([
                        ['products.rh_reference_id', 'LIKE', "%{$search}%"],
                        ['products.is_active', true],
                    ])
                        ->orWhere([
                            ['products.sup_item_ref', 'LIKE', "%{$search}%"],
                            ['products.is_active', true],
                        ])
                        ->orWhere([
                            ['products.serial_number', 'LIKE', "%{$search}%"],
                            ['products.is_active', true],
                        ])
                        ->orWhere([
                            ['categories.name', 'LIKE', "%{$search}%"],
                            ['categories.is_active', true],
                            ['products.is_active', true],
                        ])
                        ->orWhere([
                            ['products.parcel_number', 'LIKE', "%{$search}%"],
                            ['products.is_active', true],
                        ]);
                })
                ->when($request->input('category'), function ($query) use ($request) {
                    return $query->where('sub_category_id', $request->input('category'));
                })
                ->when($request->input('sub_category'), function ($query) use ($request) {
                    return $query->where('third_category_id', $request->input('sub_category'));
                })
                ->when($request->input('supplier'), function ($query) use ($request) {
                    return $query->where('supplier_id', $request->input('supplier'));
                })
                ->when($request->input('min_price'), function ($query) use ($request) {
                    return $query->whereBetween('tag_price', [(int) $request->input('min_price'), (int) $request->input('max_price')]);
                })
                ->when($request->input('all_status'), function ($query) use ($request) {
                    return $query->where('item_status', $request->input('all_status'));
                })
                ->when($request->input('gem_type'), function ($query) use ($request) {
                    $gem_type = $request->input('gem_type');
                    if ($gem_type == 'colored_stone') {
                        return $query->whereNotNull('stone_type')->where('stone_type', '!=', 'NULL');
                    } elseif ($gem_type == 'diamond') {
                        return $query->whereNotNull('diamond_shape_id');
                    } else {
                        return $query->where('product_material_id', $gem_type);
                    }
                })
//                ->orWhere([
//                    ['products.sup_item_ref', 'LIKE', "%{$search}%"],
//                    ['products.is_active', true]
//                ])
//                ->orWhere([
//                    ['products.serial_number', 'LIKE', "%{$search}%"],
//                    ['products.is_active', true]
//                ])
//                ->orWhere([
//                    ['categories.name', 'LIKE', "%{$search}%"],
//                    ['categories.is_active', true],
//                    ['products.is_active', true]
//                ])
//                ->orWhere([
//                    ['products.parcel_number', 'LIKE', "%{$search}%"],
//                    ['products.is_active', true]
//                ])
                ->where('is_global', 0)
                ->whereNotIn('id', $marged_product_id)
                ->offset($start)
                ->limit($limit)
            // ->orderBy($order, $dir)
                ->orderBy('rh_reference_id', 'ASC')
            // ->orderByRaw('RIGHT(rh_reference_id, 3)')
                ->get();

            $totalFiltered = Product::join('categories', 'products.category_id', '=', 'categories.id')
                ->where([
                    ['products.rh_reference_id', 'LIKE', "%{$search}%"],
                    ['products.is_active', true],
                ])
                ->orWhere([
                    ['products.serial_number', 'LIKE', "%{$search}%"],
                    ['products.is_active', true],
                ])
                ->orWhere([
                    ['categories.name', 'LIKE', "%{$search}%"],
                    ['categories.is_active', true],
                    ['products.is_active', true],
                ])
                ->orWhere([
                    ['products.parcel_number', 'LIKE', "%{$search}%"],
                    ['products.is_active', true],
                ])
                ->whereNotIn('id', $marged_product_id)
                ->count();
        }
        $data = [];
        if (! empty($products)) {
            foreach ($products as $key => $product) {
                $nestedData['id']  = $product->id;
                $nestedData['key'] = $key;
                $product_image     = explode(",", $product->image);
                $product_image     = htmlspecialchars($product_image[0]);
                // if ($product_image)
                //     $nestedData['image'] = '<img src="' . url('public/images/product', $product_image) . '" height="80" width="80">';
                // else
                //     $nestedData['image'] = '<img src="public/images/zummXD2dvAtI.png" height="80" width="80">';
                //  $ext = null;
                $ext = '.png';
                //$sup_item_ref = $product->rh_reference_id ?? null;

                // Extract the third and fourth characters
                $substr = substr($product->rh_reference_id, 2, 2);

                // Check if the extracted substring is numeric and less than 20
                if (is_numeric($substr) && (int) $substr < 20) {
                    $sup_item_ref = $product->rh_reference_id;
                } else {
                    $sup_item_ref = $product->sup_item_ref;
                }

                $png_image = public_path() . '/storage/photos/1/Product/' . $sup_item_ref . '.png';
                if (file_exists($png_image) == true) {
                    $ext = '.png';
                }
                $jpg_image = public_path() . '/storage/photos/1/Product/' . $sup_item_ref . '.jpg';
                if (file_exists($jpg_image) == true) {
                    $ext = '.jpg';
                }
                //png jpg
                $nestedData['image'] = '<img src="' . url('/') . '/storage/photos/1/Product/' . $sup_item_ref . '' . $ext . '" height="80" width="80">';

                $nestedData['name'] = $product->rh_reference_id;
                //$nestedData['code'] = 'Code';

                //$nestedData['brand'] = "N/A";
                $nestedData['product_type'] = $product->category->name ?? '-';
                $category                   = SecondCategory::find($product->sub_category_id);
                $sub_category               = ThirdCategory::find($product->third_category_id);
                $nestedData['category']     = $category['name'] ?? '-';
                $nestedData['sub_category'] = $sub_category['name'] ?? '-';
                $nestedData['sup_item_ref'] = $product['sup_item_ref'] ?? '-';
                $nestedData['amount']       = $product['amount'] ?? '-';

                $sale = Product_Sale::join('sales', 'sales.id', 'product_sales.sale_id')
                    ->where('product_sales.product_id', $product['id'])
                    ->orderBy('product_sales.created_at', 'DESC')
                    ->first();

                $returned_amount = 0;
                $sale_total      = 0;
                $reference_no    = '-';
                if (! empty($sale)) {
                    $returned_amount = DB::table('returns')->where('sale_id', $sale->id)->sum('grand_total');
                }
                if (! empty($sale) && $returned_amount == 0) {
                    $sale_total   = $sale['total'];
                    $reference_no = $sale['reference_no'];
                }

                $product_list_check = Product::whereRaw("find_in_set($product->id,product_list)")->first();
                if ($reference_no == '-' && $product->item_status != 11) {
                    if (! empty($product_list_check)) {
                        $return = ProductReturn::where('product_id', $product_list_check['id'])->first();
                        if (empty($return)) {
                            $sale = Product_Sale::join('sales', 'sales.id', 'product_sales.sale_id')
                                ->where('product_sales.product_id', $product_list_check['id'])
                                ->orderBy('product_sales.created_at', 'DESC')
                                ->first();
                            if (! empty($sale) && $returned_amount == 0) {
                                $sale_total   = $sale['total'];
                                $reference_no = $sale['reference_no'];
                            }
                        }
                    }
                }

                if ($product->item_status == 3) {
                    $reference_no = '-';
                }

                $nestedData['sale_amount']    = ($returned_amount == 0) ? $sale_total : 0;
                $nestedData['invoice_numner'] = $reference_no;
                $pro_status                   = ProductStatus::where('id', $product->item_status)->first();
                if (! empty($pro_status)) {
                    $nestedData['status'] = '<span class="badge text-uppercase" style="background-color: ' . $pro_status['bg_color'] . '; color: ' . $pro_status['font_color'] . '">' . $pro_status['name'] . '</span>';
                } else {
                    $nestedData['status'] = null;
                }

                $nestedData['qty'] = '1';

                $nestedData['supplier'] = $product->supplier->supplier_name ?? null;

                $nestedData['amount']    = $product->amount;
                $nestedData['tag_price'] = $product->tag_price;

                //$nestedData['stock_worth'] = (1 * 100).' '.config('currency').' / '.(2 * 100).' '.config('currency');
                if ($product->type == 2) {
                    $text = 'merged product';
                } else {
                    $text = 'Product View';
                }

                $list_type          = 0;
                $product_list_check = Product::whereRaw("find_in_set($product->id,product_list)")->first();
                if (! empty($product_list_check)) {
                    $list_type = 1;
                }
                $nestedData['options'] = '<div class="btn-group">
                            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">' . trans("file.action") . '
                              <span class="caret"></span>
                              <span class="sr-only">Toggle Dropdown</span>
                            </button>
                            <ul class="dropdown-menu edit-options dropdown-menu-right dropdown-default" user="menu">
                            <li>
                                <button="type" class="btn btn-link view"><i class="fa fa-eye"></i> ' . trans('file.View') . '</button>
                            </li>';
                if ($product->type == 2 || $product->type == 1) {
                    $nestedData['options'] .= '<li>
                                <button="type" class="btn btn-link" onclick="productView(' . $product->id . ')"><i class="fa fa-eye"></i>' . $text . '</button>
                            </li>';
                }
                if ($list_type == 1) {
                    $nestedData['options'] .= '<li>
                                <button="type" class="btn btn-link" onclick="productShowOnView(' . $product->id . ')"><i class="fa fa-eye"></i>View Product</button>
                            </li>';
                }

                if (in_array("products-edit", $request['all_permission']))
                // $nestedData['options'] .= '<li>
                //             <button="type" class="btn btn-link view" onclick="productDetails(' . json_encode($product) . ')"><i class="fa fa-eye"></i> View
                //         </button>
                //         </li>';
                {
                    $nestedData['options'] .= '<li>
                            <a href="' . route('products.edit', $product->id) . '" class="btn btn-link"><i class="fa fa-edit"></i> ' . trans('file.edit') . '</a>
                        </li>';
                }

                if (in_array("product_history", $request['all_permission'])) {
                    $nestedData['options'] .= \Form::open(["route" => "products.history", "method" => "GET"]) . '
                            <li>
                                <input type="hidden" name="product_id" value="' . $product->id . '" />
                                <button type="submit" class="btn btn-link"><i class="dripicons-checklist"></i> ' . trans("file.Product History") . '</button>
                            </li>' . \Form::close();
                }

                if (in_array("print_barcode", $request['all_permission'])) {
                    $product_info = 'code' . ' (' . $product->category_id . ')';
                    $nestedData['options'] .= \Form::open(["route" => "product.printBarcode", "method" => "GET"]) . '
                        <li>
                            <input type="hidden" name="data" value="' . $product->rh_reference_id . '" />
                            <button type="submit" class="btn btn-link"><i class="dripicons-print"></i> ' . trans("file.print_barcode") . '</button>
                        </li>' . \Form::close();
                }
                if (in_array("products-delete", $request['all_permission'])) {
                    $nestedData['options'] .= \Form::open(["route" => ["products.destroy", $product->id], "method" => "DELETE"]) . '
                            <li>
                              <button type="submit" class="btn btn-link" onclick="return confirmDelete()"><i class="fa fa-trash"></i> ' . trans("file.delete") . '</button>
                            </li>' . \Form::close() . '
                        </ul>
                    </div>';
                }

                // data for product details by one click

                $tax = "N/A";

                $tax_method = trans('file.Inclusive');

                // $nestedData['product'] = array(
                //     '[ "' . $product->catgory_id . '"', ' "' . $product->catgory_id . '"', ' "' . $nestedData['product_type'] . '"', ' "' . $nestedData['supplier'] . '"', ' "' . $tax . '"', ' "' . $tax_method . '"', ' "' . $product->alert_quantity . '"', ' "' . preg_replace('/\s+/S', " ", $product->catgory_id) . '"', ' "' . $product->id . '"', ' "' . $product->catgory_id . '"', ' "' . $product->catgory_id . '"', ' "' . $product->catgory_id . '"', ' "' . $product->catgory_id . '"', ' "' . $nestedData['qty'] . '"', ' "' . $product->image . '"', ' "' . $product->catgory_id . '"]'
                // );
                $type                  = $product->category->name ?? null;
                $category_name         = $category['name'] ?? null;
                $sub_category_name     = $sub_category['name'] ?? null;
                $image                 = url('/') . '/storage/photos/1/Product/' . $sup_item_ref . '' . $ext;
                $diamond_shape         = DiamondShape::where('id', explode(',', $product->diamond_shape_id))->pluck('name')->toArray();
                $diamond_clarity       = DiamondClarity::where('id', explode(',', $product->diamond_clarity_id))->pluck('name')->toArray();
                $diamond_color         = DiamondColor::where('id', explode(',', $product->diamond_color_id))->pluck('name')->toArray();
                $nestedData['product'] = [
                    '["' . $product->rh_reference_id .
                    '","' . $type .
                    '","' . $category_name .
                    '","' . $sub_category_name .
                    '","' . $product->sup_item_ref .
                    '","' . $product->amount .
                    '","' . $product->tag_price .
                    '","' . $sale_total .
                    '","' . $reference_no .
                    '","' . $image .
                    '","' . $product->id .
                    '","' . $product->material_weight .
                    '","' . $product->gross_weight .
                    '","' . $product->diamond_carat_id .
                    '","' . implode(',', $diamond_shape) .
                    '","' . implode(',', $diamond_clarity) .
                    '","' . implode(',', $diamond_color) .
                    '","' . $product->stone_weight .
                    '","' . $product->stone_type .
                    '","' . $product->stone_shape .
                    '","' . $product->stone_spec .
                    '","' . $product->id . '"]',
                ];
                //$nestedData['imagedata'] = DNS1D::getBarcodePNG($product->code, $product->barcode_symbology);
                $data[] = $nestedData;
            }
        }
        $json_data = [
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data,
        ];

        echo json_encode($json_data);
    }

    public function create()
    {
        $role        = Role::firstOrCreate(['id' => Auth::user()->role_id]);
        $tablevalues = ['30', '31', '32', '33'];
        if ($role->hasPermissionTo('products-add')) {
            // $lims_product_list_without_variant = $this->productWithoutVariant();
            $lims_product_list_with_variant    = $this->productWithVariant();
            $lims_product_list_without_variant = Product::where('is_active', true)->get();
            $lims_brand_list                   = Brand::where('is_active', true)->get();
            $lims_category_list                = Category::where('is_active', true)->get();
            $lims_supplier_list                = Supplier::where('is_active', true)->get();
            $lims_pro_material_list            = ProductMaterial::where('is_active', true)->get();
            $lims_pro_karat_list               = ProductKarat::where('is_active', true)->get();
            $lims_pro_scat_list                = SecondCategory::where('is_active', true)->get();
            $lims_pro_sscat_list               = SecondCategory::whereIn("id", $tablevalues)->where('is_active', true)->get();
            $lims_pro_mcolor_list              = MaterialColor::where('is_active', true)->get();
            $lims_pro_dshape_list              = DiamondShape::where('is_active', true)->get();
            $lims_pro_dcolor_list              = DiamondColor::where('is_active', true)->get();
            $lims_pro_dclarity_list            = DiamondClarity::where('is_active', true)->get();
            $lims_pro_sshape_list              = StoneShape::where('is_active', true)->get();
            $lims_unit_list                    = Unit::where('is_active', true)->get();
            $lims_tax_list                     = Tax::where('is_active', true)->get();
            $lims_warehouse_list               = Warehouse::where('is_active', true)->get();
            $lims_currency_list                = Currency::where('is_active', true)->where('code', '!=', 'KD')->get();
            $numberOfProduct                   = Product::where('is_active', true)->count();
            $product_statuses                  = ProductStatus::where('is_active', 1)->get();
            // dd($lims_product_list_without_variant);
            return view('backend.product.create', compact('lims_currency_list', 'lims_supplier_list', 'lims_product_list_without_variant', 'lims_product_list_with_variant', 'lims_brand_list', 'lims_category_list', 'lims_unit_list', 'lims_tax_list', 'lims_warehouse_list', 'numberOfProduct', 'lims_pro_sshape_list', 'lims_pro_dclarity_list', 'lims_pro_dcolor_list', 'lims_pro_mcolor_list', 'lims_pro_dshape_list', 'lims_pro_scat_list', 'lims_pro_karat_list', 'lims_pro_material_list', 'product_statuses'));
        } else {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

    }

    public function product_details(Request $request)
    {
        $product          = Product::where('rh_reference_id', $request['pro'])->where('is_active', true)->first();
        $rh_reference_ids = (isset($request->rh_reference_ids) && count($request->rh_reference_ids) > 0) ? $request->rh_reference_ids : [];
        if (isset($request->rh_reference_ids) && count($request->rh_reference_ids) > 0) {
            $rh_reference_id[] = $request['pro'];
            $rh_reference_ids  = array_merge($rh_reference_ids, $rh_reference_id);
        } else {
            $rh_reference_ids[] = $request['pro'];
        }
        $data['rh_reference_ids'] = array_unique($rh_reference_ids);

        $data['diamond']['diamond_shape_id']     = explode(',', $product->diamond_shape_id);
        $data['diamond']['number_of_stones']     = $product->number_of_stones;
        $data['diamond']['diamond_carat_id']     = (float) $product->diamond_carat_id + (float) $request->diamond_carat_id;
        $data['diamond']['diamond_clarity_id']   = $product->diamond_clarity_id;
        $data['diamond']['diamond_color_id']     = $product->diamond_color_id;
        $data['diamond']['d_certificate_number'] = $product->d_certificate_number;
        $data['diamond']['d_cerftificate_id']    = $product->d_cerftificate_id;
        $data['diamond']['category_id']          = $product->category_id;
        $data['diamond']['diamond_carats']       = number_format($product->diamond_carat_id, 2, '.', '') + number_format($request->diamond_carats, 2, '.', '');

        $data['stone']['stone_type']    = $product->stone_type;
        $data['stone']['f_stone_shape'] = $product->f_stone_shape;
        $data['stone']['stone_weight']  = number_format($product->stone_weight, 2, '.', '') + number_format($request->total_stone_weight, 2, '.', '');
        $data['stone']['stone_spec']    = $product->stone_spec;
        $allCategories                  = $request->allCategories;
        $allCategories[]                = $product->category_id;
        $all_unit_id                    = (isset($request->all_unit_id) && count($request->all_unit_id) > 0) ? $request->all_unit_id : [];
        $all_clarity_id                 = (isset($request->all_clarity_id) && count($request->all_clarity_id) > 0) ? $request->all_clarity_id : [];
        $all_color_id                   = (isset($request->all_color_id) && count($request->all_color_id) > 0) ? $request->all_color_id : [];

        // Diamond Shape
        $unitTable                  = CategoryTable::whereIn("cat_id", array_unique($allCategories))->where('table_id', 5)->pluck('id')->toArray();
        $unitValues                 = TableValue::whereIn("category_table_id", $unitTable)->pluck('value_id')->toArray();
        $data['diamond']['unit']    = DiamondShape::whereIn("id", array_unique($unitValues))->pluck('name', 'id');
        $data['diamond']['unit_id'] = DiamondShape::whereIn("id", array_unique($unitValues))->pluck('id');
        if (count($all_unit_id) > 0) {
            $data['diamond']['all_unit_id'] = array_merge($all_unit_id, $data['diamond']['diamond_shape_id']);
        } else {
            $data['diamond']['all_unit_id'] = $data['diamond']['diamond_shape_id'];
        }

        // Diamond Clarity
        $clarityTable                  = CategoryTable::whereIn("cat_id", array_unique($allCategories))->where('table_id', 6)->pluck('id')->toArray();
        $clarityValues                 = TableValue::whereIn("category_table_id", $clarityTable)->pluck('value_id')->toArray();
        $data['diamond']['clarity']    = DiamondClarity::whereIn("id", array_unique($clarityValues))->pluck('name', 'id');
        $data['diamond']['clarity_id'] = DiamondClarity::whereIn("id", array_unique($clarityValues))->pluck('id');
        if (count($all_clarity_id) > 0) {
            $data['diamond']['all_clarity_id'] = array_merge($all_clarity_id, explode(',', $data['diamond']['diamond_clarity_id']));
        } else {
            $data['diamond']['all_clarity_id'] = explode(',', $data['diamond']['diamond_clarity_id']);
        }

        // Diamond Color
        $colorTable                  = CategoryTable::whereIn("cat_id", array_unique($allCategories))->where('table_id', 7)->pluck('id')->toArray();
        $colorValues                 = TableValue::whereIn("category_table_id", $colorTable)->pluck('value_id')->toArray();
        $data['diamond']['color']    = DiamondColor::whereIn("id", array_unique($colorValues))->pluck('name', 'id');
        $data['diamond']['color_id'] = DiamondColor::whereIn("id", array_unique($colorValues))->pluck('id');
        if (count($all_color_id) > 0) {
            $data['diamond']['all_color_id'] = array_merge($all_color_id, explode(',', $data['diamond']['diamond_color_id']));
        } else {
            $data['diamond']['all_color_id'] = explode(',', $data['diamond']['diamond_color_id']);
        }
        return $data;
    }

    public function get_rh_reference_id($supplier_id, $serial_number, $parcel_number)
    {

        $supplier      = Supplier::findOrFail($supplier_id);
        $supplier_code = $supplier->supplier_code;
        $year          = date("y");
        $new_rhrfid    = $supplier_code . $year . $parcel_number . $serial_number;
        $productn      = Product::where('rh_reference_id', $new_rhrfid)->where('is_active', true)->first();
        if (isset($productn) && $productn->rh_reference_id != '') {
            $this->get_rh_reference_id($supplier_id);
        } else {
            return strtoupper($new_rhrfid);
        }
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'code' => [
                'max:255',
                Rule::unique('products')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
            'name' => [
                'max:255',
                Rule::unique('products')->where(function ($query) {
                    return $query->where('is_active', 1);
                }),
            ],
        ]);
        $data = $request->except('image', 'file');

        if ($request->type == 'marged') {
            $rh_reference_id = $data['rh_reference_id'];
        } else {
//            $rh_reference_id = $this->get_rh_reference_id($data['supplier_id'], $data['serial_number'], $data['parcel_number'], $data['alphabatic_character']);

            $supplier        = Supplier::findOrFail($data['supplier_id']);
            $supplier_code   = $supplier->supplier_code;
            $year            = date("y");
            $rh_reference_id = $supplier_code . $year . $data['alphabatic_character'] . $data['parcel_number'] . $data['serial_number'];
        }
        $data['rh_reference_id'] = $rh_reference_id;

        //        $currency = Currency::find($data['currency_type']);
        //        if (isset($currency) || !empty($data['amountfc'])) {
        //            if (!empty($data['amountfc'] && $data['amountfc'] != 'null')) {
        //                $new_cur_value = round($currency->exchange_rate * $data['amountfc'], 2);
        //                $data['amount'] = $new_cur_value;
        //            } else {
        //                $data['amount'] = '';
        //            }
        //        }

        $data['is_active'] = true;
        $images            = $request->image;
        $image_names       = [];
        if ($images) {
            foreach ($images as $key => $image) {
                $ext       = pathinfo($image->getClientOriginalName(), PATHINFO_EXTENSION);
                $imageName = date("Ymdhis") . ($key + 1);
                if (! config('database.connections.saleprosaas_landlord')) {
                    $imageName = $imageName . '.' . $ext;
                    $image->move('public/images/product', $imageName);
                } else {
                    $imageName = $this->getTenantId() . '_' . $imageName . '.' . $ext;
                    $image->move('public/images/product', $imageName);
                }
                $image_names[] = $imageName;
            }
            $data['image'] = implode(",", $image_names);
        } else {
            $data['image'] = 'zummXD2dvAtI.png';
        }

        $data['diamond_shape_id']    = $request->input('diamond_shape_id') ? implode(',', $request->input('diamond_shape_id')) : null;
        $data['diamond_clarity_id']  = $request->input('diamond_clarity_id') ? implode(',', $request->input('diamond_clarity_id')) : null;
        $data['diamond_color_id']    = $request->input('diamond_color_id') ? implode(',', $request->input('diamond_color_id')) : null;
        $data['product_material_id'] = $request->input('product_material_id') ? implode(',', $request->input('product_material_id')) : null;
        $data['product_karat_id']    = $request->input('product_karat_id') ? implode(',', $request->input('product_karat_id')) : null;
        $status                      = $data['item_status'];
//        if ($request->type == 'marged') {
//            $data['item_status'] = 3;
//        }
        $lims_product_data      = Product::create($data);
        $product_warehouse_data = Product_Warehouse::select('id', 'qty')
            ->where([
                ['product_id', $lims_product_data->id],
                ['warehouse_id', $data['warehouse_id']],
            ])->first();
        if ($product_warehouse_data) {
            $product_warehouse_data->qty = 1;
            $product_warehouse_data->save();
        } else {
            $lims_product_warehouse_data               = new Product_Warehouse();
            $lims_product_warehouse_data->product_id   = $lims_product_data->id;
            $lims_product_warehouse_data->warehouse_id = $data['warehouse_id'];
            $lims_product_warehouse_data->qty          = 1;
            $lims_product_warehouse_data->save();
        }

        $this->cacheForget('product_list');
        // $this->cacheForget('product_list_with_variant');
        if ($request->type == 'combo' || $request->type == 'marged') {
            $procuct_count        = Product::count();
            $update               = Product::find($lims_product_data->id);
            $update->type         = ($request->type == 'combo') ? 1 : 2;
            $update->product_list = implode(",", $request->product_id);
//            $update->serial_number = $procuct_count + 1;
            $update->save();
        }
        if ($request->type == 'combo') {
            if (! empty($request->product_id)) {
                foreach ($request->product_id as $products) {
                    $update              = Product::find($products);
                    $update->item_status = 3;
                    $update->save();
                }
            }
        }
        if ($request->type == 'marged') {
            foreach ($request->product_id as $product_id) {
                $update_status              = Product::find($product_id);
                $update_status->item_status = 10; //$status;
                $update_status->save();
            }
        }
        \Session::flash('create_message', 'Product created successfully');
    }

    public function autoPurchase($product_data, $warehouse_id, $stock)
    {
        $data['reference_no']   = 'pr-' . date("Ymd") . '-' . date("his");
        $data['user_id']        = Auth::id();
        $data['warehouse_id']   = $warehouse_id;
        $data['item']           = 1;
        $data['total_qty']      = $stock;
        $data['total_discount'] = 0;
        $data['status']         = 1;
        $data['payment_status'] = 2;
        if ($product_data->tax_id) {
            $tax_data = DB::table('taxes')->select('rate')->find($product_data->tax_id);
            if ($product_data->tax_method == 1) {
                $net_unit_cost = number_format($product_data->cost, 2, '.', '');
                $tax           = number_format($product_data->cost * $stock * ($tax_data->rate / 100), 2, '.', '');
                $cost          = number_format(($product_data->cost * $stock) + $tax, 2, '.', '');
            } else {
                $net_unit_cost = number_format((100 / (100 + $tax_data->rate)) * $product_data->cost, 2, '.', '');
                $tax           = number_format(($product_data->cost - $net_unit_cost) * $stock, 2, '.', '');
                $cost          = number_format($product_data->cost * $stock, 2, '.', '');
            }
            $tax_rate           = $tax_data->rate;
            $data['total_tax']  = $tax;
            $data['total_cost'] = $cost;
        } else {
            $data['total_tax']  = 0.00;
            $data['total_cost'] = number_format($product_data->cost * $stock, 2, '.', '');
            $net_unit_cost      = number_format($product_data->cost, 2, '.', '');
            $tax_rate           = 0.00;
            $tax                = 0.00;
            $cost               = number_format($product_data->cost * $stock, 2, '.', '');
        }

        $product_warehouse_data = Product_Warehouse::select('id', 'qty')
            ->where([
                ['product_id', $product_data->id],
                ['warehouse_id', $warehouse_id],
            ])->first();
        if ($product_warehouse_data) {
            $product_warehouse_data->qty += $stock;
            $product_warehouse_data->save();
        } else {
            $lims_product_warehouse_data               = new Product_Warehouse();
            $lims_product_warehouse_data->product_id   = $product_data->id;
            $lims_product_warehouse_data->warehouse_id = $warehouse_id;
            $lims_product_warehouse_data->qty          = $stock;
            $lims_product_warehouse_data->save();
        }
        $data['order_tax']   = 0;
        $data['grand_total'] = $data['total_cost'];
        $data['paid_amount'] = $data['grand_total'];
        //insetting data to purchase table
        $purchase_data = Purchase::create($data);
        //inserting data to product_purchases table
        ProductPurchase::create([
            'purchase_id'      => $purchase_data->id,
            'product_id'       => $product_data->id,
            'qty'              => $stock,
            'recieved'         => $stock,
            'purchase_unit_id' => $product_data->unit_id,
            'net_unit_cost'    => $net_unit_cost,
            'discount'         => 0,
            'tax_rate'         => $tax_rate,
            'tax'              => $tax,
            'total'            => $cost,
        ]);
        //inserting data to payments table
        $maxId             = Payment::max('id') + 1;
        $payment_reference = str_pad($maxId, 5, '0', STR_PAD_LEFT);
        Payment::create([
            'payment_reference' => $payment_reference,
            // 'payment_reference' => 'ppr-' . date("Ymd") . '-' . date("his"),
            'user_id'           => Auth::id(),
            'purchase_id'       => $purchase_data->id,
            'account_id'        => 0,
            'amount'            => $data['grand_total'],
            'change'            => 0,
            'paying_method'     => 'Cash',
        ]);
    }

    public function history(Request $request)
    {
        $role = Role::find(Auth::user()->role_id);
        if ($role->hasPermissionTo('product_history')) {
            if ($request->input('warehouse_id')) {
                $warehouse_id = $request->input('warehouse_id');
            } else {
                $warehouse_id = 0;
            }

            if ($request->input('starting_date')) {
                $starting_date = $request->input('starting_date');
                $ending_date   = $request->input('ending_date');
            } else {
                $starting_date = date("Y-m-d", strtotime(date('Y-m-d', strtotime('-1 year', strtotime(date('Y-m-d'))))));
                $ending_date   = date("Y-m-d");
            }
            $product_id          = $request->input('product_id');
            $product_data        = Product::select('name', 'code')->find($product_id);
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            return view('backend.product.history', compact('starting_date', 'ending_date', 'warehouse_id', 'product_id', 'product_data', 'lims_warehouse_list'));
        } else {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

    }

    public function saleHistoryData(Request $request)
    {
        $columns = [
            1 => 'created_at',
            2 => 'reference_no',
        ];

        $product_id   = $request->input('product_id');
        $warehouse_id = $request->input('warehouse_id');

        $q = DB::table('sales')
            ->join('product_sales', 'sales.id', '=', 'product_sales.sale_id')
            ->where('product_sales.product_id', $product_id);
        //            ->whereDate('sales.created_at', '>=', $request->input('starting_date'))
        //            ->whereDate('sales.created_at', '<=', $request->input('ending_date'));
        if ($warehouse_id) {
            $q = $q->where('warehouse_id', $warehouse_id);
        }

        if (Auth::user()->role_id > 2 && config('staff_access') == 'own') {
            $q = $q->where('sales.user_id', Auth::id());
        }

        $totalData     = $q->count();
        $totalFiltered = $totalData;

        if ($request->input('length') != -1) {
            $limit = $request->input('length');
        } else {
            $limit = $totalData;
        }

        $start = $request->input('start');
        $order = 'sales.' . $columns[$request->input('order.0.column')];
        $dir   = $request->input('order.0.dir');
        $q     = $q->leftJoin('customers', 'sales.customer_id', '=', 'customers.id')
            ->select('sales.id', 'sales.reference_no', 'sales.created_at', 'customers.name_english as customer_name', 'customers.phone_number as customer_number', 'product_sales.qty', 'product_sales.sale_unit_id', 'product_sales.total')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir);
        if (empty($request->input('search.value'))) {
            $sales = $q->get();
        } else {
            $search = $request->input('search.value');
            $q      = $q->whereDate('sales.created_at', '=', date('Y-m-d', strtotime(str_replace('/', '-', $search))));
            if (Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $sales = $q->orwhere([
                    ['sales.reference_no', 'LIKE', "%{$search}%"],
                    ['sales.user_id', Auth::id()],
                ])
                    ->get();
                $totalFiltered = $q->orwhere([
                    ['sales.reference_no', 'LIKE', "%{$search}%"],
                    ['sales.user_id', Auth::id()],
                ])
                    ->count();
            } else {
                $sales         = $q->orwhere('sales.reference_no', 'LIKE', "%{$search}%")->get();
                $totalFiltered = $q->orwhere('sales.reference_no', 'LIKE', "%{$search}%")->count();
            }
        }
        $data = [];
        if (! empty($sales)) {
            foreach ($sales as $key => $sale) {
                $nestedData['id']           = $sale->id;
                $nestedData['key']          = $key;
                $nestedData['date']         = date(config('date_format'), strtotime($sale->created_at));
                $nestedData['reference_no'] = $sale->reference_no;
                $nestedData['customer']     = $sale->customer_name ?? '-';
                $nestedData['qty']          = number_format($sale->qty, config('decimal'));
                if ($sale->sale_unit_id) {
                    $unit_data = DB::table('units')->select('unit_code')->find($sale->sale_unit_id);
                    $nestedData['qty'] .= ' ' . $unit_data->unit_code;
                }
                $nestedData['unit_price'] = number_format(($sale->total / $sale->qty), config('decimal'));
                $nestedData['sub_total']  = number_format($sale->total, config('decimal'));
                $data[]                   = $nestedData;
            }
        }
        $json_data = [
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data,
        ];
        echo json_encode($json_data);
    }

    public function purchaseHistoryData(Request $request)
    {
        $columns = [
            1 => 'created_at',
            2 => 'reference_no',
        ];

        $product_id   = $request->input('product_id');
        $warehouse_id = $request->input('warehouse_id');

        $q = DB::table('purchases')
            ->join('product_purchases', 'purchases.id', '=', 'product_purchases.purchase_id')
            ->where('product_purchases.product_id', $product_id);
        //            ->whereDate('purchases.created_at', '>=', $request->input('starting_date'))
        //            ->whereDate('purchases.created_at', '<=', $request->input('ending_date'));
        if ($warehouse_id) {
            $q = $q->where('warehouse_id', $warehouse_id);
        }

        if (Auth::user()->role_id > 2 && config('staff_access') == 'own') {
            $q = $q->where('purchases.user_id', Auth::id());
        }

        $totalData     = $q->count();
        $totalFiltered = $totalData;

        if ($request->input('length') != -1) {
            $limit = $request->input('length');
        } else {
            $limit = $totalData;
        }

        $start = $request->input('start');
        $order = 'purchases.' . $columns[$request->input('order.0.column')];
        $dir   = $request->input('order.0.dir');
        $q     = $q->leftJoin('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir);
        if (empty($request->input('search.value'))) {
            $purchases = $q->select('purchases.id', 'purchases.reference_no', 'purchases.created_at', 'purchases.supplier_id', 'suppliers.supplier_name as supplier_name', 'suppliers.phone_number as supplier_number', 'product_purchases.qty', 'product_purchases.purchase_unit_id', 'product_purchases.total')->get();
        } else {
            $search = $request->input('search.value');
            $q      = $q->whereDate('purchases.created_at', '=', date('Y-m-d', strtotime(str_replace('/', '-', $search))));
            if (Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $purchases = $q->select('purchases.id', 'purchases.reference_no', 'purchases.created_at', 'purchases.supplier_id', 'suppliers.supplier_name as supplier_name', 'suppliers.phone_number as supplier_number', 'product_purchases.qty', 'product_purchases.purchase_unit_id', 'product_purchases.total')
                    ->orwhere([
                        ['purchases.reference_no', 'LIKE', "%{$search}%"],
                        ['purchases.user_id', Auth::id()],
                    ])->get();
                $totalFiltered = $q->orwhere([
                    ['purchases.reference_no', 'LIKE', "%{$search}%"],
                    ['purchases.user_id', Auth::id()],
                ])->count();
            } else {
                $purchases = $q->select('purchases.id', 'purchases.reference_no', 'purchases.created_at', 'purchases.supplier_id', 'suppliers.name as supplier_name', 'suppliers.phone_number as supplier_number', 'warehouses.name as warehouse_name', 'product_purchases.qty', 'product_purchases.purchase_unit_id', 'product_purchases.total')
                    ->orwhere('purchases.reference_no', 'LIKE', "%{$search}%")
                    ->get();
                $totalFiltered = $q->orwhere('purchases.reference_no', 'LIKE', "%{$search}%")->count();
            }
        }
        $data = [];
        if (! empty($purchases)) {
            foreach ($purchases as $key => $purchase) {
                $nestedData['id']           = $purchase->id;
                $nestedData['key']          = $key;
                $nestedData['date']         = date(config('date_format'), strtotime($purchase->created_at));
                $nestedData['reference_no'] = $purchase->reference_no;
                if ($purchase->supplier_id) {
                    $nestedData['supplier'] = $purchase->supplier_name;
                } else {
                    $nestedData['supplier'] = 'N/A';
                }

                $nestedData['qty'] = number_format($purchase->qty, config('decimal'));
                if ($purchase->purchase_unit_id) {
                    $unit_data = DB::table('units')->select('unit_code')->find($purchase->purchase_unit_id);
                    $nestedData['qty'] .= ' ' . $unit_data->unit_code;
                }
                $nestedData['unit_cost'] = number_format(($purchase->total / $purchase->qty), config('decimal'));
                $nestedData['sub_total'] = number_format($purchase->total, config('decimal'));
                $data[]                  = $nestedData;
            }
        }
        $json_data = [
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data,
        ];
        echo json_encode($json_data);
    }

    public function saleReturnHistoryData(Request $request)
    {
        $columns = [
            1 => 'created_at',
            2 => 'reference_no',
        ];

        $product_id   = $request->input('product_id');
        $warehouse_id = $request->input('warehouse_id');

        $q = DB::table('returns')
            ->join('product_returns', 'returns.id', '=', 'product_returns.return_id')
            ->where('product_returns.product_id', $product_id);
        //            ->whereDate('returns.created_at', '>=', $request->input('starting_date'))
        //            ->whereDate('returns.created_at', '<=', $request->input('ending_date'));
        if ($warehouse_id) {
            $q = $q->where('warehouse_id', $warehouse_id);
        }

        if (Auth::user()->role_id > 2 && config('staff_access') == 'own') {
            $q = $q->where('returns.user_id', Auth::id());
        }

        $totalData     = $q->count();
        $totalFiltered = $totalData;

        if ($request->input('length') != -1) {
            $limit = $request->input('length');
        } else {
            $limit = $totalData;
        }

        $start = $request->input('start');
        $order = 'returns.' . $columns[$request->input('order.0.column')];
        $dir   = $request->input('order.0.dir');
        $q     = $q->leftJoin('customers', 'returns.customer_id', '=', 'customers.id')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir);
        if (empty($request->input('search.value'))) {
            $returnss = $q->select('returns.id', 'returns.reference_no', 'returns.created_at', 'customers.name_english as customer_name', 'customers.phone_number as customer_number', 'product_returns.qty', 'product_returns.sale_unit_id', 'product_returns.total')->get();
        } else {
            $search = $request->input('search.value');
            $q      = $q->whereDate('returns.created_at', '=', date('Y-m-d', strtotime(str_replace('/', '-', $search))));
            if (Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $returnss = $q->select('returns.id', 'returns.reference_no', 'returns.created_at', 'customers.name_english as customer_name', 'customers.phone_number as customer_number', 'product_returns.qty', 'product_returns.sale_unit_id', 'product_returns.total')
                    ->orwhere([
                        ['returns.reference_no', 'LIKE', "%{$search}%"],
                        ['returns.user_id', Auth::id()],
                    ])
                    ->get();
                $totalFiltered = $q->orwhere([
                    ['returns.reference_no', 'LIKE', "%{$search}%"],
                    ['returns.user_id', Auth::id()],
                ])
                    ->count();
            } else {
                $returnss = $q->select('returns.id', 'returns.reference_no', 'returns.created_at', 'customers.name_english as customer_name', 'customers.phone_number as customer_number', 'warehouses.name as warehouse_name', 'product_returns.qty', 'product_returns.sale_unit_id', 'product_returns.total')
                    ->orwhere('returns.reference_no', 'LIKE', "%{$search}%")
                    ->get();
                $totalFiltered = $q->orwhere('returns.reference_no', 'LIKE', "%{$search}%")->count();
            }
        }
        $data = [];
        if (! empty($returnss)) {
            foreach ($returnss as $key => $returns) {
                $nestedData['id']           = $returns->id;
                $nestedData['key']          = $key;
                $nestedData['date']         = date(config('date_format'), strtotime($returns->created_at));
                $nestedData['reference_no'] = $returns->reference_no;
                $nestedData['customer']     = $returns->customer_name ?? '-';
                $nestedData['qty']          = number_format($returns->qty, config('decimal'));
                if ($returns->sale_unit_id) {
                    $unit_data = DB::table('units')->select('unit_code')->find($returns->sale_unit_id);
                    $nestedData['qty'] .= ' ' . $unit_data->unit_code;
                }
                $nestedData['unit_price'] = number_format(($returns->total / $returns->qty), config('decimal'));
                $nestedData['sub_total']  = number_format($returns->total, config('decimal'));
                $data[]                   = $nestedData;
            }
        }
        $json_data = [
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data,
        ];
        echo json_encode($json_data);
    }

    public function purchaseReturnHistoryData(Request $request)
    {
        $columns = [
            1 => 'created_at',
            2 => 'reference_no',
        ];

        $product_id   = $request->input('product_id');
        $warehouse_id = $request->input('warehouse_id');

        $q = DB::table('return_purchases')
            ->join('purchase_product_return', 'return_purchases.id', '=', 'purchase_product_return.return_id')
            ->where('purchase_product_return.product_id', $product_id);
        //            ->whereDate('return_purchases.created_at', '>=', $request->input('starting_date'))
        //            ->whereDate('return_purchases.created_at', '<=', $request->input('ending_date'));
        if ($warehouse_id) {
            $q = $q->where('warehouse_id', $warehouse_id);
        }

        if (Auth::user()->role_id > 2 && config('staff_access') == 'own') {
            $q = $q->where('return_purchases.user_id', Auth::id());
        }

        $totalData     = $q->count();
        $totalFiltered = $totalData;

        if ($request->input('length') != -1) {
            $limit = $request->input('length');
        } else {
            $limit = $totalData;
        }

        $start = $request->input('start');
        $order = 'return_purchases.' . $columns[$request->input('order.0.column')];
        $dir   = $request->input('order.0.dir');
        $q     = $q->leftJoin('suppliers', 'return_purchases.supplier_id', '=', 'suppliers.id')
            ->select('return_purchases.id', 'return_purchases.reference_no', 'return_purchases.created_at', 'return_purchases.supplier_id', 'suppliers.supplier_name as supplier_name', 'suppliers.mobile as supplier_number', 'purchase_product_return.qty', 'purchase_product_return.purchase_unit_id', 'purchase_product_return.total')
            ->offset($start)
            ->limit($limit)
            ->orderBy($order, $dir);
        if (empty($request->input('search.value'))) {
            $return_purchases = $q->get();
        } else {
            $search = $request->input('search.value');
            $q      = $q->whereDate('return_purchases.created_at', '=', date('Y-m-d', strtotime(str_replace('/', '-', $search))));

            if (Auth::user()->role_id > 2 && config('staff_access') == 'own') {
                $return_purchases = $q->orwhere([
                    ['return_purchases.reference_no', 'LIKE', "%{$search}%"],
                    ['return_purchases.user_id', Auth::id()],
                ])
                    ->get();
                $totalFiltered = $q->orwhere([
                    ['return_purchases.reference_no', 'LIKE', "%{$search}%"],
                    ['return_purchases.user_id', Auth::id()],
                ])
                    ->count();
            } else {
                $return_purchases = $q->orwhere('return_purchases.reference_no', 'LIKE', "%{$search}%")->get();
                $totalFiltered    = $q->orwhere('return_purchases.reference_no', 'LIKE', "%{$search}%")->count();
            }
        }
        $data = [];
        if (! empty($return_purchases)) {
            foreach ($return_purchases as $key => $return_purchase) {
                $nestedData['id']           = $return_purchase->id;
                $nestedData['key']          = $key;
                $nestedData['date']         = date(config('date_format'), strtotime($return_purchase->created_at));
                $nestedData['reference_no'] = $return_purchase->reference_no;
                if ($return_purchase->supplier_id) {
                    $nestedData['supplier'] = $return_purchase->supplier_name . ' [' . ($return_purchase->supplier_number) . ']';
                } else {
                    $nestedData['supplier'] = 'N/A';
                }

                $nestedData['qty'] = number_format($return_purchase->qty, config('decimal'));
                if ($return_purchase->purchase_unit_id) {
                    $unit_data = DB::table('units')->select('unit_code')->find($return_purchase->purchase_unit_id);
                    $nestedData['qty'] .= ' ' . $unit_data->unit_code;
                }
                $nestedData['unit_cost'] = number_format(($return_purchase->total / $return_purchase->qty), config('decimal'));
                $nestedData['sub_total'] = number_format($return_purchase->total, config('decimal'));
                $data[]                  = $nestedData;
            }
        }
        $json_data = [
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data"            => $data,
        ];
        echo json_encode($json_data);
    }

    public function variantData($id)
    {
        if (Auth::user()->role_id > 2) {
            return ProductVariant::join('variants', 'product_variants.variant_id', '=', 'variants.id')
                ->join('product_warehouse', function ($join) {
                    $join->on('product_variants.product_id', '=', 'product_warehouse.product_id');
                    $join->on('product_variants.variant_id', '=', 'product_warehouse.variant_id');
                })
                ->select('variants.name', 'product_variants.item_code', 'product_variants.additional_cost', 'product_variants.additional_price', 'product_warehouse.qty')
                ->where([
                    ['product_warehouse.product_id', $id],
                    ['product_warehouse.warehouse_id', Auth::user()->warehouse_id],
                ])
                ->orderBy('product_variants.position')
                ->get();
        } else {
            return ProductVariant::join('variants', 'product_variants.variant_id', '=', 'variants.id')
                ->select('variants.name', 'product_variants.item_code', 'product_variants.additional_cost', 'product_variants.additional_price', 'product_variants.qty')
                ->orderBy('product_variants.position')
                ->where('product_id', $id)
                ->get();
        }
    }

    public function edit($id)
    {
        $role        = Role::firstOrCreate(['id' => Auth::user()->role_id]);
        $tablevalues = ['30', '31', '32', '33'];
        if ($role->hasPermissionTo('products-edit')) {
            // $lims_product_list_without_variant = $this->productWithoutVariant();
            $lims_product_list_with_variant    = $this->productWithVariant();
            $lims_product_list_without_variant = Product::where('is_active', true)->get();
            $lims_brand_list                   = Brand::where('is_active', true)->get();
            $lims_category_list                = Category::where('is_active', true)->get();
            $lims_pro_material_list            = ProductMaterial::where('is_active', true)->get();
            $lims_pro_karat_list               = ProductKarat::where('is_active', true)->get();
            $lims_pro_sscat_list               = SecondCategory::whereIn("id", $tablevalues)->where('is_active', true)->get();
            $lims_pro_mcolor_list              = MaterialColor::where('is_active', true)->get();
            $lims_pro_dshape_list              = DiamondShape::where('is_active', true)->get();
            $lims_pro_dcolor_list              = DiamondColor::where('is_active', true)->get();
            $lims_pro_dclarity_list            = DiamondClarity::where('is_active', true)->get();
            $lims_pro_sshape_list              = StoneShape::where('is_active', true)->get();
            $lims_unit_list                    = Unit::where('is_active', true)->get();
            $lims_tax_list                     = Tax::where('is_active', true)->get();
            $lims_product_data                 = Product::where('id', $id)->first();

            $categorytable = CategoryTable::where("cat_id", $lims_product_data->category_id)->Where('table_id', 3)->first();
            if (! empty($categorytable)) {
                $tablevalues = TableValue::where("category_table_id", $categorytable->id)->pluck('value_id');
            } else {
                $tablevalues = [];
            }

            $lims_pro_scat_list  = SecondCategory::where('is_active', true)->whereIn("id", $tablevalues)->get();
            $lims_pro_tcat_list  = ThirdCategory::where('is_active', true)->where('parent_id', $lims_product_data->sub_category_id)->get();
            $lims_supplier_list  = Supplier::where('is_active', true)->get();
            $lims_warehouse_list = Warehouse::where('is_active', true)->get();
            $lims_currency_list  = Currency::where('is_active', true)->where('code', '!=', 'KD')->get();
            $product_statuses    = ProductStatus::where('is_active', 1)->get();

            if ($lims_product_data->variant_option) {
                $lims_product_data->variant_option = json_decode($lims_product_data->variant_option);
                $lims_product_data->variant_value  = json_decode($lims_product_data->variant_value);
            }
            $lims_product_variant_data = $lims_product_data->variant()->orderBy('position')->get();
            $lims_warehouse_list       = Warehouse::where('is_active', true)->get();
            $noOfVariantValue          = 0;

            $product_list    = explode(',', $lims_product_data->product_list);
            $product_data    = Product::whereIn('id', $product_list)->get();
            $merged_products = Product::whereIn('id', $product_list)->pluck('rh_reference_id')->toArray();

            return view('backend.product.edit', compact('product_statuses', 'merged_products', 'product_data', 'lims_pro_tcat_list', 'lims_warehouse_list', 'lims_currency_list', 'lims_supplier_list', 'lims_pro_sscat_list', 'lims_product_list_without_variant', 'lims_product_list_with_variant', 'lims_brand_list', 'lims_category_list', 'lims_unit_list', 'lims_tax_list', 'lims_product_data', 'lims_product_variant_data', 'lims_warehouse_list', 'noOfVariantValue', 'lims_pro_sshape_list', 'lims_pro_dclarity_list', 'lims_pro_dcolor_list', 'lims_pro_mcolor_list', 'lims_pro_dshape_list', 'lims_pro_scat_list', 'lims_pro_karat_list', 'lims_pro_material_list', 'id'));
        } else {
            return redirect()->back()->with('not_permitted', 'Sorry! You are not allowed to access this module');
        }

    }

    public function updateProduct(Request $request)
    {
        /*  if(!env('USER_VERIFIED')) {
             return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');
         }
         else {
             $this->validate($request, [
                 'name' => [
                     'max:255',
                     Rule::unique('products')->ignore($request->input('id'))->where(function ($query) {
                         return $query->where('is_active', 1);
                     }),
                 ],

                 'code' => [
                     'max:255',
                     Rule::unique('products')->ignore($request->input('id'))->where(function ($query) {
                         return $query->where('is_active', 1);
                     }),
                 ]

             ]); */

        $lims_product_data = Product::findOrFail($request->input('id'));
        $data              = $request->except('image', 'file', 'prev_img');

        if (isset($request->product_id) && ! empty($request->product_id) && count($request->product_id) > 0) {
            $lims_product_data->product_list = implode(",", $request->product_id);
        }
        // $data['name'] = htmlspecialchars(trim($data['name']));

        /* if($data['type'] == 'combo') {
            $data['product_list'] = implode(",", $data['product_id']);
            $data['variant_list'] = implode(",", $data['variant_id']);
            $data['qty_list'] = implode(",", $data['product_qty']);
            $data['price_list'] = implode(",", $data['unit_price']);
            $data['cost'] = $data['unit_id'] = $data['purchase_unit_id'] = $data['sale_unit_id'] = 0;
        }
        elseif($data['type'] == 'digital' || $data['type'] == 'service')
            $data['cost'] = $data['unit_id'] = $data['purchase_unit_id'] = $data['sale_unit_id'] = 0;
*/
        /*  if(!isset($data['featured']))
             $data['featured'] = 0;

         if(!isset($data['is_embeded']))
             $data['is_embeded'] = 0;

         if(!isset($data['promotion']))
             $data['promotion'] = null;

         if(!isset($data['is_batch']))
             $data['is_batch'] = null;

         if(!isset($data['is_imei']))
             $data['is_imei'] = null;

         if(!isset($data['is_sync_disable']) && \Schema::hasColumn('products', 'is_sync_disable'))
             $data['is_sync_disable'] = null; */

        //$data['product_details'] = str_replace('"', '@', $data['product_details']);
        // $data['product_details'] = $data['product_details'];
        /*   if($data['starting_date'])
              $data['starting_date'] = date('Y-m-d', strtotime($data['starting_date']));
          if($data['last_date'])
              $data['last_date'] = date('Y-m-d', strtotime($data['last_date']));
*/
        $previous_images = [];
        //dealing with previous images
        if ($request->prev_img) {
            foreach ($request->prev_img as $key => $prev_img) {
                if (! in_array($prev_img, $previous_images)) {
                    $previous_images[] = $prev_img;
                }

            }
            $lims_product_data->image = implode(",", $previous_images);
            $lims_product_data->save();
        } else {
            $lims_product_data->image = null;
            $lims_product_data->save();
        }

        //dealing with new images
        if ($request->image) {
            $images      = $request->image;
            $image_names = [];
            $length      = count(explode(",", $lims_product_data->image));
            foreach ($images as $key => $image) {
                $ext = pathinfo($image->getClientOriginalName(), PATHINFO_EXTENSION);
                /*$image = Image::make($image)->resize(512, 512);*/
                $imageName = date("Ymdhis") . ($length + $key + 1) . '.' . $ext;
                $image->move('public/images/product', $imageName);
                $image_names[] = $imageName;
            }
            if ($lims_product_data->image) {
                $data['image'] = $lims_product_data->image . ',' . implode(",", $image_names);
            } else {
                $data['image'] = implode(",", $image_names);
            }

        } else {
            $data['image'] = $lims_product_data->image;
        }

        $file = $request->file;
        if ($file) {
            $ext      = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
            $fileName = strtotime(date('Y-m-d H:i:s'));
            $fileName = $fileName . '.' . $ext;
            $file->move('public/product/files', $fileName);
            $data['file'] = $fileName;
        }

        //$old_product_variant_ids = ProductVariant::where('product_id', $request->input('id'))->pluck('id')->toArray();
        //$new_product_variant_ids = [];
        //dealing with product variant
        /* if(isset($data['is_variant'])) {
            if(isset($data['variant_option']) && isset($data['variant_value'])) {
                $data['variant_option'] = json_encode($data['variant_option']);
                $data['variant_value'] = json_encode($data['variant_value']);
            }
            foreach ($data['variant_name'] as $key => $variant_name) {
                $lims_variant_data = Variant::firstOrCreate(['name' => $data['variant_name'][$key]]);
                $lims_product_variant_data = ProductVariant::where([
                                                ['product_id', $lims_product_data->id],
                                                ['variant_id', $lims_variant_data->id]
                                            ])->first();
                if($lims_product_variant_data) {
                    $lims_product_variant_data->update([
                        'position' => $key+1,
                        'item_code' => $data['item_code'][$key],
                        'additional_cost' => $data['additional_cost'][$key],
                        'additional_price' => $data['additional_price'][$key]
                    ]);
                }
                else {
                    $lims_product_variant_data = new ProductVariant();
                    $lims_product_variant_data->product_id = $lims_product_data->id;
                    $lims_product_variant_data->variant_id = $lims_variant_data->id;
                    $lims_product_variant_data->position = $key + 1;
                    $lims_product_variant_data->item_code = $data['item_code'][$key];
                    $lims_product_variant_data->additional_cost = $data['additional_cost'][$key];
                    $lims_product_variant_data->additional_price = $data['additional_price'][$key];
                    $lims_product_variant_data->qty = 0;
                    $lims_product_variant_data->save();
                }
                $new_product_variant_ids[] = $lims_product_variant_data->id;
            }
        }
        else {
            $data['is_variant'] = null;
            $data['variant_option'] = null;
            $data['variant_value'] = null;
        } */
        //deleting old product variant if not exist
        /* foreach ($old_product_variant_ids as $key => $product_variant_id) {
            if (!in_array($product_variant_id, $new_product_variant_ids))
                ProductVariant::find($product_variant_id)->delete();
        } */
        /*  if(isset($data['is_diffPrice'])) {
             foreach ($data['diff_price'] as $key => $diff_price) {
                 if($diff_price) {
                     $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($lims_product_data->id, $data['warehouse_id'][$key])->first();
                     if($lims_product_warehouse_data) {
                         $lims_product_warehouse_data->price = $diff_price;
                         $lims_product_warehouse_data->save();
                     }
                     else {
                         Product_Warehouse::create([
                             "product_id" => $lims_product_data->id,
                             "warehouse_id" => $data["warehouse_id"][$key],
                             "qty" => 0,
                             "price" => $diff_price
                         ]);
                     }
                 }
             }
         }
         else {
             $data['is_diffPrice'] = false;
             foreach ($data['warehouse_id'] as $key => $warehouse_id) {
                 $lims_product_warehouse_data = Product_Warehouse::FindProductWithoutVariant($lims_product_data->id, $warehouse_id)->first();
                 if($lims_product_warehouse_data) {
                     $lims_product_warehouse_data->price = null;
                     $lims_product_warehouse_data->save();
                 }
             }
         } */
        $currency = Currency::find($data['currency_type']);

        if (isset($currency) && ! empty($data['amountfc'])) {
            if (! empty($data['amountfc'] && $data['amountfc'] != 'null')) {
                $new_cur_value  = round($currency->exchange_rate * $data['amountfc'], 2);
                $data['amount'] = $new_cur_value;
            } else {
                $data['amount'] = '';
            }
        }

        if ($data['sub_category_id'] != 12) {
            $data['psub_category_id'] = null;
        }

        $product_warehouse_data = Product_Warehouse::select('id', 'qty')
            ->where([
                ['product_id', $lims_product_data->id],
            ])->first();
        if ($product_warehouse_data) {
            $product_warehouse_data->qty          = 1;
            $product_warehouse_data->warehouse_id = $data['warehouse_id'];
            $product_warehouse_data->save();
        } else {
            $lims_product_warehouse_data               = new Product_Warehouse();
            $lims_product_warehouse_data->product_id   = $lims_product_data->id;
            $lims_product_warehouse_data->warehouse_id = $data['warehouse_id'];
            $lims_product_warehouse_data->qty          = 1;
            $lims_product_warehouse_data->save();
        }
        $data['diamond_shape_id']    = $request->input('diamond_shape_id') ? implode(',', $request->input('diamond_shape_id')) : null;
        $data['diamond_clarity_id']  = $request->input('diamond_clarity_id') ? implode(',', $request->input('diamond_clarity_id')) : null;
        $data['diamond_color_id']    = $request->input('diamond_color_id') ? implode(',', $request->input('diamond_color_id')) : null;
        $data['product_material_id'] = $request->input('product_material_id') ? implode(',', $request->input('product_material_id')) : null;
        $data['product_karat_id']    = $request->input('product_karat_id') ? implode(',', $request->input('product_karat_id')) : null;
        $data['diamond_color']       = $data['diamond_color_id'];

        $lims_product_data->update($data);

        $lims_product_data                     = Product::findOrFail($request->input('id'));
        $lims_product_data->stone_type         = $request->input('stone_type');
        $lims_product_data->stone_weight       = $request->input('stone_weight');
        $lims_product_data->diamond_color_id   = $request->input('diamond_color');
        $lims_product_data->diamond_clarity_id = $request->input('diamond_clarity');
        $lims_product_data->diamond_shape_id   = $request->input('diamond_shape');
        $lims_product_data->diamond_color_id   = $data['diamond_color_id'];
        $lims_product_data->diamond_clarity_id = $data['diamond_clarity_id'];
        $lims_product_data->diamond_shape_id   = $data['diamond_shape_id'];
        $lims_product_data->save();

        if ($lims_product_data->type == 2) {
            $product_list = explode(',', $lims_product_data->product_list);
            foreach ($product_list as $prod_list) {
                $listPro              = Product::where('id', $prod_list)->first();
                $listPro->item_status = 10;
                $listPro->save();
            }
        }

        return json_encode($data);
        $this->cacheForget('product_list');
        $this->cacheForget('product_list_with_variant');
        \Session::flash('edit_message', 'Product updated successfully');
    }

    public function generateCode()
    {
        $id = Keygen::numeric(8)->generate();
        return $id;
    }

    public function search(Request $request)
    {
        $product_code      = explode(" ", $request['data']);
        $lims_product_data = Product::where('code', $product_code[0])->first();

        $product[] = $lims_product_data->name;
        $product[] = $lims_product_data->code;
        $product[] = $lims_product_data->qty;
        $product[] = $lims_product_data->price;
        $product[] = $lims_product_data->id;
        return $product;
    }

    public function saleUnit($id)
    {
        $unit = Unit::where("base_unit", $id)->orWhere('id', $id)->pluck('unit_name', 'id');
        return json_encode($unit);
    }

    public function getsubcategories($id)
    {

        $tablevalues   = ['30', '31', '32', '33'];
        $categorytable = SecondCategory::whereIn("id", $tablevalues)->pluck('name', 'id');
        if ($categorytable) {

            return json_encode($categorytable);
        }
    }

    public function productValueFilelds(Request $request)
    {
        $id         = $request->id ?? 0;
        $value_id   = $request->value_id ?? 0;
        $product_id = $request->product_id ?? 0;

        $categorytable = CategoryTable::where("cat_id", $id)->Where('table_id', $value_id)->first();
        if ($value_id == 9) {
            $unit = ThirdCategory::where('parent_id', $id)->where("is_active", '1')->pluck('name', 'id', 'parent_id');
            return json_encode($unit);
        }
        if ($categorytable) {

            $tablevalues = TableValue::where("category_table_id", $categorytable->id)->pluck('value_id');
            if ($value_id == 1) {
                $unit = ProductMaterial::whereIn("id", $tablevalues)->pluck('name', 'id');
            } else if ($value_id == 2) {
                $unit = ProductKarat::whereIn("id", $tablevalues)->pluck('name', 'id');
            } else if ($value_id == 3) {
                $unit = SecondCategory::whereIn("id", $tablevalues)->pluck('name', 'id');
            } else if ($value_id == 4) {
                $unit = MaterialColor::whereIn("id", $tablevalues)->pluck('name', 'id');
            } else if ($value_id == 5) {
                $unit = DiamondShape::whereIn("id", $tablevalues)->pluck('name', 'id');
            } else if ($value_id == 6) {
                $unit = DiamondClarity::whereIn("id", $tablevalues)->pluck('name', 'id');
            } else if ($value_id == 7) {
                $unit = DiamondColor::whereIn("id", $tablevalues)->pluck('name', 'id');
            } else if ($value_id == 8) {
                $unit = StoneShape::whereIn("id", $tablevalues)->pluck('name', 'id');
            }

            return json_encode($unit);
        }
    }

    public function getData($id, $variant_id)
    {
        if ($variant_id) {
            $data = Product::join('product_variants', 'products.id', 'product_variants.product_id')
                ->select('products.name', 'product_variants.item_code')
                ->where([
                    ['products.id', $id],
                    ['product_variants.variant_id', $variant_id],
                ])->first();
            $data->code = $data->item_code;
        } else {
            $data = Product::select('name', 'code')->find($id);
        }

        return $data;
    }

    public function productWarehouseData($id)
    {
        $warehouse                 = [];
        $qty                       = [];
        $batch                     = [];
        $expired_date              = [];
        $imei_number               = [];
        $warehouse_name            = [];
        $variant_name              = [];
        $variant_qty               = [];
        $product_warehouse         = [];
        $product_variant_warehouse = [];
        $lims_product_data         = Product::select('id', 'is_variant')->find($id);
        if ($lims_product_data->is_variant) {
            $lims_product_variant_warehouse_data = Product_Warehouse::where('product_id', $lims_product_data->id)->orderBy('warehouse_id')->get();
            $lims_product_warehouse_data         = Product_Warehouse::select('warehouse_id', DB::raw('sum(qty) as qty'))->where('product_id', $id)->groupBy('warehouse_id')->get();
            foreach ($lims_product_variant_warehouse_data as $key => $product_variant_warehouse_data) {
                $lims_warehouse_data = Warehouse::find($product_variant_warehouse_data->warehouse_id);
                $lims_variant_data   = Variant::find($product_variant_warehouse_data->variant_id);
                $warehouse_name[]    = $lims_warehouse_data->name;
                $variant_name[]      = $lims_variant_data->name;
                $variant_qty[]       = $product_variant_warehouse_data->qty;
            }
        } else {
            $lims_product_warehouse_data = Product_Warehouse::where('product_id', $id)->orderBy('warehouse_id', 'asc')->get();
        }
        foreach ($lims_product_warehouse_data as $key => $product_warehouse_data) {
            $lims_warehouse_data = Warehouse::find($product_warehouse_data->warehouse_id);
            if ($product_warehouse_data->product_batch_id) {
                $product_batch_data = ProductBatch::select('batch_no', 'expired_date')->find($product_warehouse_data->product_batch_id);
                $batch_no           = $product_batch_data->batch_no;
                $expiredDate        = date(config('date_format'), strtotime($product_batch_data->expired_date));
            } else {
                $batch_no    = 'N/A';
                $expiredDate = 'N/A';
            }
            $warehouse[]    = $lims_warehouse_data->name;
            $batch[]        = $batch_no;
            $expired_date[] = $expiredDate;
            $qty[]          = $product_warehouse_data->qty;
            if ($product_warehouse_data->imei_number) {
                $imei_number[] = $product_warehouse_data->imei_number;
            } else {
                $imei_number[] = 'N/A';
            }

        }

        $product_warehouse         = [$warehouse, $qty, $batch, $expired_date, $imei_number];
        $product_variant_warehouse = [$warehouse_name, $variant_name, $variant_qty];
        return ['product_warehouse' => $product_warehouse, 'product_variant_warehouse' => $product_variant_warehouse];
    }

    public function printBarcode(Request $request)
    {
        if ($request->input('data')) {
            $preLoadedproduct = $this->limsProductSearch($request);
        } else {
            $preLoadedproduct = null;
        }

        $lims_product_list_without_variant = $this->productWithoutVariant();
        $lims_product_list_with_variant    = $this->productWithVariant();

        $lims_product_list_without_variant = Product::select('products.id', 'products.name', 'rh_reference_id as item_code')
            ->orderBy('id')->get();

        //dd($lims_product_list_without_variant);

        return view('backend.product.print_barcode', compact('lims_product_list_without_variant', 'lims_product_list_with_variant', 'preLoadedproduct'));
    }

    public function printLabel(Request $request)
    {
        // Path to Bartender executable
        $bartenderPath = '"C:\Program Files\Seagull\BarTender 2022\bartend.exe"';

        // Path to label file
        $labelPath = '"C:\Users\Serve\Desktop\Diamond.btw"';

        // Printer name (ensure it matches your system's printer name)
        $printerName = '"Zebra S4M (203 dpi) - ZPL"';

        // all .txt file paths

        // $dataFilePath = 'C:\Users\Serve\Desktop\data.txt';
        $tag_price_file       = 'C:\Users\Serve\Desktop\tag_price.txt';
        $rh_reference_id_file = 'C:\Users\Serve\Desktop\rh_reference_id.txt';
        $material_weight_file = 'C:\Users\Serve\Desktop\material_weight.txt';
        $carat_file           = 'C:\Users\Serve\Desktop\carat.txt';
        $diamond_carat_file   = 'C:\Users\Serve\Desktop\diamond_carat.txt';
        $color_file           = 'C:\Users\Serve\Desktop\color.txt';
        $clarity_file         = 'C:\Users\Serve\Desktop\clarity.txt';
        $stone_type_file      = 'C:\Users\Serve\Desktop\stone_type.txt';
        $stone_weight_file    = 'C:\Users\Serve\Desktop\stone_weight.txt';

        $command = '"C:\Program Files\Seagull\BarTender 2022\bartend.exe" /AF="C:\Users\Serve\Desktop\dynamic.btw" /P /X ';

        $rowsData = $request->rows ?? [];

       

        // Execute the command
        try {

            foreach ($rowsData as $key => $value) {

                $tag_price       = $value['tag_price'];
                $rh_reference_id = $value['rh_reference_id'];
                $material_weight = $value['material_weight'];
                $carat           = $value['carat'];
                $diamond_carat   = $value['diamond_carat'];
                $color           = $value['color'];
                $clarity         = $value['clarity'];
                $stone_type      = $value['stone_type'];
                $stone_weight    = $value['stone_weight'];
    
                file_put_contents($tag_price_file, $tag_price);
                file_put_contents($rh_reference_id_file, $rh_reference_id);
                file_put_contents($material_weight_file, $material_weight);
                file_put_contents($carat_file, $carat);
                file_put_contents($diamond_carat_file, $diamond_carat);
                file_put_contents($color_file, $color);
                file_put_contents($clarity_file, $clarity);
                file_put_contents($stone_type_file, $stone_type);
                file_put_contents($stone_weight_file, $stone_weight);

                exec($command, $output, $returnVar);

                if ($returnVar !== 0) {
                    Log::error("Bartender command failed with return code $returnVar, Output: " . implode("\n", $output));
                    return response()->json(['status' => 'error', 'message' => 'Failed to print label.'], 500);
                }
    
    
            }
        
            return response()->json(['status' => 'success', 'message' => 'Label printed successfully.']);
        } catch (\Exception $e) {
            Log::error("Exception when printing label: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'An error occurred while printing label.'], 500);
        }

    }
    public function productWithoutVariant()
    {
        return Product::ActiveStandard()->select('id', 'name', 'code')
            ->whereNull('is_variant')->get();
    }

    public function productWithVariant()
    {
        return Product::join('product_variants', 'products.id', 'product_variants.product_id')
            ->ActiveStandard()
            ->whereNotNull('is_variant')
            ->select('products.id', 'products.name', 'product_variants.item_code')
            ->orderBy('position')->get();
    }

    public function limsProductSearch(Request $request)
    {
        $product_code      = explode("(", $request['data']);
        $product_code[0]   = rtrim($product_code[0], " ");
        $lims_product_data = Product::where([
            ['rh_reference_id', $product_code[0]],
            ['is_active', true],
        ])->first();
        if (! $lims_product_data) {
            $lims_product_data = Product::select('products.*', 'product_variants.item_code', 'product_variants.variant_id', 'product_variants.additional_price')
                ->where('product_variants.item_code', $product_code[0])
                ->first();

            $variant_id       = $lims_product_data->variant_id;
            $additional_price = $lims_product_data->additional_price;
        } else {
            $variant_id       = '';
            $additional_price = 0;
        }
        // $lims_product_data_color = DiamondColor::where('id', $lims_product_data->diamond_color_id)->first();
        // $lims_product_data_clarity = DiamondClarity::where('id', $lims_product_data->diamond_clarity_id)->first();
        // $lims_product_data_shape = DiamondShape::where('id', $lims_product_data->diamond_shape_id)->first();
        // $lims_product_data_carat = ProductKarat::where('id', $lims_product_data->product_karat_id)->first();
        // $lims_product_data_material = ProductMaterial::where('id', $lims_product_data->product_material_id)->first();

        $lims_product_data_color    = DiamondColor::whereIn('id', explode(',', $lims_product_data->diamond_color_id))->pluck('name')->toArray();
        $lims_product_data_clarity  = DiamondClarity::whereIn('id', explode(',', $lims_product_data->diamond_clarity_id))->pluck('name')->toArray();
        $lims_product_data_shape    = DiamondShape::whereIn('id', explode(',', $lims_product_data->diamond_shape_id))->first();
        $lims_product_data_carat    = ProductKarat::where('id', $lims_product_data->product_karat_id)->first();
        $lims_product_data_material = ProductMaterial::where('id', $lims_product_data->product_material_id)->first();

        $product[] = DNS1D::getBarcodePNG($lims_product_data->rh_reference_id, 'C128');
        $product[] = $lims_product_data->rh_reference_id;
        $product[] = number_format($lims_product_data->tag_price, config('decimal'));
        $product[] = $lims_product_data->material_weight;
        $product[] = $lims_product_data_carat->name ?? '-';
        $product[] = $lims_product_data->diamond_carat_id ?? '-';
        $product[] = implode(',', $lims_product_data_color) ?? '-';
        $product[] = implode(',', $lims_product_data_clarity) ?? '-';
        $product[] = $lims_product_data->stone_type;
        $product[] = $lims_product_data->stone_weight;
        $product[] = $lims_product_data->note;
        // $product[] = $lims_product_data->rh_reference_id;
        $product[] = $lims_product_data->id;
        return $product;
    }

    public function limsProductSearchNew(Request $request)
    {
        $product_code      = explode("(", $request['data']);
        $product_code[0]   = rtrim($product_code[0], " ");
        $lims_product_data = Product::where([
            ['rh_reference_id', $product_code[0]],
            ['is_active', true],
        ])->first();
        if (! $lims_product_data) {
            $lims_product_data = Product::select('products.*', 'product_variants.item_code', 'product_variants.variant_id', 'product_variants.additional_price')
                ->where('product_variants.item_code', $product_code[0])
                ->first();

            $variant_id       = $lims_product_data->variant_id;
            $additional_price = $lims_product_data->additional_price;
        } else {
            $variant_id       = '';
            $additional_price = 0;
        }
        $lims_product_data_color    = DiamondColor::where('id', $lims_product_data->diamond_color_id)->first();
        $lims_product_data_clarity  = DiamondClarity::where('id', $lims_product_data->diamond_clarity_id)->first();
        $lims_product_data_shape    = DiamondShape::where('id', $lims_product_data->diamond_shape_id)->first();
        $lims_product_data_carat    = ProductKarat::where('id', $lims_product_data->product_karat_id)->first();
        $lims_product_data_material = ProductMaterial::where('id', $lims_product_data->product_material_id)->first();

        // $product[] = DNS1D::getBarcodePNG($lims_product_data->rh_reference_id, 'C128');
        // $product[] = $lims_product_data->rh_reference_id;
        // $product[] = number_format($lims_product_data->tag_price, config('decimal'));
        // $product[] = $lims_product_data->material_weight;
        // $product[] = $lims_product_data_carat->name ?? '-';
        // $product[] = $lims_product_data->diamond_carat_id ?? '-';
        // $product[] = $lims_product_data_color->name ?? '-';
        // $product[] = $lims_product_data_clarity->name ?? '-';
        // $product[] = $lims_product_data->stone_type;
        // $product[] = $lims_product_data->stone_weight;
        // $product[] = $lims_product_data->note;
        $product[]          = $lims_product_data->rh_reference_id;
        $product[]          = $lims_product_data->id;
        $product[]          = $lims_product_data->material_weight;
        $product[]          = $lims_product_data->gross_weight;
        $product[]          = $lims_product_data->product_material_id;
        $amount_total_fc    = (float) $request->amount_total_fc + (float) $lims_product_data->amountfc;
        $amount_in_kd_total = (float) $request->amount_in_kd_total + (float) $lims_product_data->amount;
        $tag_total          = (float) $request->tag_total + (float) $lims_product_data->tag_price;
        $product[]          = number_format($amount_total_fc, 3, '.', '');
        $product[]          = number_format($amount_in_kd_total, 3, '.', '');
        $product[]          = number_format($tag_total, 3, '.', '');
//        $product[] = number_format($request->$lims_product_data->stone_weight, 2, '.', '') + number_format($request->total_stone_weight, 2, '.', '');
        $product[] = null;
        if (isset($request->product_materials)) {
            if (! in_array($lims_product_data->product_material_id, array_keys($request->product_materials))) {
                $product[] = 'material_not_found';
            } else {
                $product[] = 'material_found';
            }
        } else {
            $product[] = '';
        }
        $product[] = $lims_product_data->currency_type;
        if (empty($request->product_material_id)) {
            $product_material_id = $lims_product_data->product_material_id;
        } else {
            $product_material_id = $request->product_material_id . ',' . $lims_product_data->product_material_id;
        }
        if (empty($request->product_karat_id)) {
            $product_karat_id = $lims_product_data->product_karat_id;
        } else {
            $product_karat_id = $request->product_karat_id . ',' . $lims_product_data->product_karat_id;
        }
        $product[]        = $product_material_id;
        $product[]        = $product_karat_id;
        $diamond_carat_id = (float) $request->diamond_carat_id + (float) $lims_product_data->diamond_carat_id;
        $product[]        = $diamond_carat_id;
        return $product;
    }

    public function limsProductRemove(Request $request)
    {
        $lims_product_data = Product::where([
            ['rh_reference_id', $request['rh_reference_id']],
        ])->first();

        if ($request->gross_weight == "NaN") {
            $gro_wei = 0;
        } else {
            $gro_wei = $request->gross_weight;
        }
        $product[] = $lims_product_data->rh_reference_id;
        $product[] = $lims_product_data->id;
        $product[] = $lims_product_data->material_weight;
        $product[] = $lims_product_data->gross_weight;

        $amount_total_fc    = (float) $request->amount_total_fc - (float) $lims_product_data->amountfc;
        $amount_in_kd_total = (float) $request->amount_in_kd_total - (float) $lims_product_data->amount;
        $tag_total          = (float) $request->tag_total - (float) $lims_product_data->tag_price;

        $product[] = number_format($request->material_weight, 2, '.', '') - number_format($lims_product_data->material_weight, 2, '.', '');
        $product[] = number_format($gro_wei, 2, '.', '') - number_format($lims_product_data->gross_weight, 2, '.', '');
        $product[] = number_format($request->diamond_carats, 2, '.', '') - number_format($lims_product_data->diamond_carat_id, 2, '.', '');
        $product[] = number_format($amount_total_fc, 3, '.', '');
        $product[] = number_format($amount_in_kd_total, 3, '.', '');
        $product[] = number_format($amount_in_kd_total, 3, '.', '');
        $product[] = number_format($tag_total, 3, '.', '');

        $product_material_id  = explode(',', $request->product_material_id);
        $product_material_ids = [];
        foreach ($product_material_id as $product_material) {
            if (! empty($product_material)) {
                if (in_array($product_material, explode(',', $lims_product_data->product_material_id))) {
                    $product_material_ids[] = $product_material;
                }
            }
        }
        $product_karat_id  = explode(',', $request->product_karat_id);
        $product_karat_ids = [];
        foreach ($product_karat_id as $product_karat) {
            if (! empty($product_karat)) {
                if (in_array($product_karat, explode(',', $lims_product_data->product_karat_id))) {
                    $product_karat_ids[] = $product_karat;
                }
            }
        }
//        dd($product_material_ids, $product_karat_ids);
//        $product_material_id = $request->product_material_id . ',' . $lims_product_data->product_material_id;
//        $product_karat_id = $request->product_karat_id . ',' . $lims_product_data->product_karat_id;

        $product[]          = implode(',', $product_material_ids);
        $product[]          = implode(',', $product_karat_ids);
        $total_stone_weight = (float) $request->total_stone_weight - (float) $lims_product_data->stone_weight;
        $product[]          = $total_stone_weight;
        return $product;
    }

    //•        diamond carat (if available)
    //•        diamond color  (if available)
    //•        diamond clarity (if available)
    //•        stone type (if available)
    //•        stone weight (if available)
    //•        notes

    /*public function getBarcode()
    {
        return DNS1D::getBarcodePNG('72782608', 'C128');
    }*/

    public function checkBatchAvailability($product_id, $batch_no, $warehouse_id)
    {
        $product_batch_data = ProductBatch::where([
            ['product_id', $product_id],
            ['batch_no', $batch_no],
        ])->first();
        if ($product_batch_data) {
            $product_warehouse_data = Product_Warehouse::select('qty')
                ->where([
                    ['product_batch_id', $product_batch_data->id],
                    ['warehouse_id', $warehouse_id],
                ])->first();
            if ($product_warehouse_data) {
                $data['qty']              = $product_warehouse_data->qty;
                $data['product_batch_id'] = $product_batch_data->id;
                $data['expired_date']     = date(config('date_format'), strtotime($product_batch_data->expired_date));
                $data['message']          = 'ok';
            } else {
                $data['qty']     = 0;
                $data['message'] = 'This Batch does not exist in the selected warehouse!';
            }
        } else {
            $data['message'] = 'Wrong Batch Number!';
        }
        return $data;
    }

    public function importProduct(Request $request)
    {
        $supplier_id   = $request->supplier_id;
        $parcel_number = $request->parcel_number;
        //dd($supplier_id);
        //get file
        $upload = $request->file('file');
        $ext    = pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION);
        if ($ext != 'csv') {
            return redirect()->back()->with('message', 'Please upload a CSV file');
        }

        $filePath = $upload->getRealPath();
        //open and read
        $file          = fopen($filePath, 'r');
        $header        = fgetcsv($file);
        $escapedHeader = [];
        //validate
        foreach ($header as $key => $value) {
            $lheader     = strtolower($value);
            $escapedItem = preg_replace('/[^a-z]/', '', $lheader);
            array_push($escapedHeader, $escapedItem);
        }

        $rejected_products = [];
        while ($columns = fgetcsv($file)) {
            foreach ($columns as $key => $value) {
                $value = preg_replace('/\D/', '', $value);
            }
            $data = array_combine($escapedHeader, $columns);

            $error_note = '';

            $lims_category_data = Category::where(['name' => $data['category'], 'is_active' => true])->first();
            if (! $lims_category_data) {
                $error_note = $error_note . ' | Type';
            }

            $productmaterialid = ProductMaterial::where('name', $data['productmaterialid'])->first();
            if (! $productmaterialid) {
                $error_note = $error_note . ' | Product Material';
            }

            $productkaratid = ProductKarat::where('name', $data['productkaratid'])->first();
            if (! $productkaratid) {
                $error_note = $error_note . ' | Product karat';
            }

            $subcategoryid = SecondCategory::where("name", $data['subcategoryid'])->first();
            if (! $subcategoryid) {
                $error_note = $error_note . ' | Category';
            }

            $psubcategoryid = ThirdCategory::where('name', $data['psubcategoryid'])->first();
            if (! $psubcategoryid) {
                $error_note = $error_note . ' | Sub Category';
            }

            $materialcolorid = MaterialColor::where('name', $data['materialcolorid'])->first();
            if (! $materialcolorid) {
                $error_note = $error_note . ' | Material Color';
            }

            //            $currencytype = Currency::where('name', $data['currencytype'])->first();
            //            if (!$currencytype) {
            //                $error_note = $error_note . ' | Currency';
            //            }

            $lims_product_data = Product::where('serial_number', $data['serialnumber'])->first();
            if ($lims_product_data) {
                $error_note = $error_note . ' | Serial Number';
            }

            $clarityNames = explode(',', $data['diaclarityid']);
            foreach ($clarityNames as $clarityName) {
                $diamondClarity = DiamondClarity::where('name', $clarityName)->first();
                if (! $diamondClarity) {
                    $error_note = $error_note . ' | Dia Clarity:' . $clarityName;
                }
            }

            $colorNames = explode(',', $data['diamondcolorid']);
            foreach ($colorNames as $colorName) {
                $diamondColor = DiamondColor::where('name', $colorName)->first();
                if (! $diamondColor) {
                    $error_note = $error_note . ' | Dia Color:' . $colorName;
                }
            }

            $shapeNames = explode(',', $data['diashapeid']);
            foreach ($shapeNames as $shapeName) {
                $diamondShape = DiamondShape::where('name', $shapeName)->first();
                if (! $diamondShape) {
                    $error_note = $error_note . ' | Dia Shape:' . $shapeName;
                }
            }

            if ($error_note) {
                $rejected_products[] = [
                    'category'      => $data['category'],
                    'serial_number' => $data['serialnumber'],
                    'sup_item_ref'  => $data['supitemref'],
                    'amount'        => $data['amountfc'],
                    'error_note'    => $error_note,
                ];
            }

            //$data['productmaterialid']; // product_material_id
            //$data['productkaratid']; // product_karat_id
            //$data['subcategoryid']; // sub_category_id
            //$data['psubcategoryid'] ?? null; //psub_category_id
            //$data['psubcategoryid'];
            //$data['materialcolorid']; // material_color_id
            //$data['materialweight']; // material_weight
            //$data['grossweight']; // gross_weight
            //$data['diacarat']; // dia_carat
            //$data['diaclarityid']; // dia_clarity_id
            //$data['diamondcolorid']; // diamond_color_id
            //$data['diashapeid']; // dia_shape_id
            //$data['stonetype']; // stone_type
            //$data['stoneweight']; // stone_weight
            //$product->brand = $data['brand']; // brand
            //$product->model = $data['model']; // model
            //$product->currency_type = $data['currencytype']; // currency_type
        }

        $rejected_products = array_map(function ($item) {
            return (object) $item;
        }, $rejected_products);

        if (count($rejected_products) > 0) {
            return view('backend.product.import_error', compact('rejected_products'));
        }

        //create purchase bill
        $maxId                           = Purchase::max('id') + 1;
        $bill_no                         = 'pr-' . date('dmY') . '-' . str_pad($maxId, 5, '0', STR_PAD_LEFT);
        $purchase_data                   = [];
        $purchase_data['reference_no']   = $bill_no;
        $purchase_data['user_id']        = Auth::id();
        $purchase_data['warehouse_id']   = 1;
        $purchase_data['supplier_id']    = $request->supplier_id;
        $purchase_data['currency_id']    = $request->currency_type;
        $purchase_data['exchange_rate']  = 1;
        $purchase_data['item']           = 1;
        $purchase_data['total_qty']      = 1;
        $purchase_data['total_discount'] = 0;
        $purchase_data['total_tax']      = 0;
        $purchase_data['order_tax_rate'] = 0;
        $purchase_data['order_tax']      = $request->other_charges;
        $purchase_data['order_discount'] = 0;
        $purchase_data['shipping_cost']  = $request->custom_charges;
        $purchase_data['amount_fc']      = $request->amount;

        $grand_total = 0;
        $currency    = Currency::where('id', $request->currency_type)->first();
        if (isset($currency) || ! empty($data['amountfc'])) {
            if (! empty($request->amount && $request->amount != 'null')) {
                $grand_total = round($currency->exchange_rate * $request->amount, 2);
            }
        }
        $purchase_data['grand_total'] = $grand_total;
        $purchase_data['total_cost']  = $grand_total;

        $purchase_data['paid_amount']    = 0;
        $purchase_data['status']         = 1;
        $purchase_data['payment_status'] = 1;
        $purchase_data['document']       = null;
        $purchase_data['note']           = $request->note;
        $purchase_data['parcel_number']  = $request->parcel_number;
        $purchase_data['currency_type']  = $request->currency_type;
        $purchase_data['bill_no']        = $bill_no; //$request->bill_no;
        $purchase_data['bill_date']      = $request->bill_date;
        $purchase_data['payment_type']   = $request->payment_type;
        $purchase_data['remarks']        = $request->remarks;
        $purchase_data['created_at']     = Carbon::now();
        $lims_purchase_data              = Purchase::create($purchase_data);

        $upload = $request->file('file');
        $ext    = pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION);
        if ($ext != 'csv') {
            return redirect()->back()->with('message', 'Please upload a CSV file');
        }

        $filePath = $upload->getRealPath();
        //open and read
        $file          = fopen($filePath, 'r');
        $header        = fgetcsv($file);
        $escapedHeader = [];
        //validate
        foreach ($header as $key => $value) {
            $lheader     = strtolower($value);
            $escapedItem = preg_replace('/[^a-z]/', '', $lheader);
            array_push($escapedHeader, $escapedItem);
        }
        //looping through other columns
        while ($columns = fgetcsv($file)) {
            foreach ($columns as $key => $value) {
                $value = preg_replace('/\D/', '', $value);
            }
            $data = array_combine($escapedHeader, $columns);

            $lims_category_data = Category::firstOrCreate(['name' => $data['category'], 'is_active' => true]);

            $lims_product_data = Product::where('serial_number', $data['serialnumber'])->first();
            if ($lims_product_data) {
                return redirect()->back()->with('not_permitted', 'Serial number of the product is already exist in the database.');
            }

            $product = Product::firstOrNew(['name' => $data['serialnumber'], 'is_active' => true]);

            $rh_reference_id = $this->get_rh_reference_code($supplier_id, $data['serialnumber'], $parcel_number);
            //$currency = Currency::where('name', $data['currencytype'])->first();
            $currency = Currency::where('id', $request->currency_type)->first();
            if (isset($currency) || ! empty($data['amountfc'])) {
                if (! empty($data['amountfc'] && $data['amountfc'] != 'null')) {
                    $new_cur_value  = round($currency->exchange_rate * $data['amountfc'], 2);
                    $data['amount'] = $new_cur_value;
                } else {
                    $data['amount'] = '';
                }
            }

                                                                 //$product->name = htmlspecialchars(trim($data['name']));
            $product->item_status     = 1;                       //1: new
            $product->category_id     = $lims_category_data->id; // category
            $product->rh_reference_id = $rh_reference_id;
            $product->supplier_id     = $supplier_id;
            $product->parcel_number   = $parcel_number;
            $product->serial_number   = $data['serialnumber']; // serial_number
            $product->sup_item_ref    = $data['supitemref'];   // sup_item_ref

            $productmaterialid            = ProductMaterial::where('name', $data['productmaterialid'])->first();
            $product->product_material_id = $productmaterialid ? $productmaterialid->id : null; // product_material_id

            $productkaratid            = ProductKarat::where('name', $data['productkaratid'])->first();
            $product->product_karat_id = $productkaratid ? $productkaratid->id : null; // product_karat_id

            $subcategoryid            = SecondCategory::where("name", $data['subcategoryid'])->first();
            $product->sub_category_id = $subcategoryid ? $subcategoryid->id : null; // sub_category_id

            $psubcategoryid             = ThirdCategory::where("name", $data['psubcategoryid'])->first();
            $product->third_category_id = $psubcategoryid ? $psubcategoryid->id : null; //psub_category_id
            $product->psub_category_id  = $psubcategoryid ? $psubcategoryid->id : null;

            $materialcolorid            = MaterialColor::where('name', $data['materialcolorid'])->first();
            $product->material_color_id = $materialcolorid ? $materialcolorid->id : null; // material_color_id

            $clarityNames = explode(',', $data['diaclarityid']);
            $clarityIds   = [];
            foreach ($clarityNames as $clarityName) {
                $diamondClarity = DiamondClarity::where('name', $clarityName)->first();
                if ($diamondClarity) {
                    $clarityIds[] = $diamondClarity->id;
                }
            }
            $commaSeparatedIds           = implode(',', $clarityIds);
            $product->diamond_clarity_id = $commaSeparatedIds; // dia_clarity_id

            $colorNames = explode(',', $data['diamondcolorid']);
            $colorIds   = [];
            foreach ($colorNames as $colorName) {
                $diamondColor = DiamondColor::where('name', $colorName)->first();
                if ($diamondColor) {
                    $colorIds[] = $diamondColor->id;
                }
            }
            $commaSeparatedIds         = implode(',', $colorIds);
            $product->diamond_color_id = $commaSeparatedIds; // diamond_color_id

            $shapeNames = explode(',', $data['diashapeid']);
            $shapeIds   = [];
            foreach ($shapeNames as $shapeName) {
                $diamondShape = DiamondShape::where('name', $shapeName)->first();
                if ($diamondShape) {
                    $shapeIds[] = $diamondShape->id;
                }
            }
            $commaSeparatedIds         = implode(',', $shapeIds);
            $product->diamond_shape_id = $commaSeparatedIds; // dia_shape_id

            //$currencytype = Currency::where('name', $data['currencytype'])->first();
            //$product->currency_type = $currencytype ? $currencytype->id : null; // currency_type
            $product->currency_type = $request->currency_type;

            $product->material_weight  = $data['materialweight'];     // material_weight
            $product->gross_weight     = $data['grossweight'];        // gross_weight
            $product->diamond_carat_id = $data['diacarat'];           // dia_carat
            $product->stone_type       = $data['stonetype'];          // stone_type
            $product->stone_weight     = $data['stoneweight'];        // stone_weight
            $product->stone_shape      = $data['stoneshape'] ?? null; // stone_weight
            $product->stone_spec       = $data['stonespec'] ?? null;  // stone_weight
            $product->description      = $data['description'];        // description
            $product->note             = $data['note'];               // note
//            $product->brand = $data['brand']; // brand
//            $product->model = $data['model']; // model
            $product->labor_charge = $data['laborcharge']; // labor_charge
            $product->consignment  = $data['consignment']; // consignment
            $product->amountfc     = $data['amountfc'];    // amountfc
            $product->warehouse_id = 1;                    // note
            $product->is_active    = true;
            //            dd($product->toArray());
            $product->save();

            $lims_product_warehouse_data               = new Product_Warehouse();
            $lims_product_warehouse_data->product_id   = $product->id;
            $lims_product_warehouse_data->warehouse_id = 1;
            $lims_product_warehouse_data->qty          = 1;
            $lims_product_warehouse_data->save();

            $new_cur_value = round($currency->exchange_rate * $product->amountfc, 2);

            $tag_price = 0;
            if ($new_cur_value && $new_cur_value > 0) {
                if ($new_cur_value < 100) {
                    $factor = 3.7 - (($new_cur_value - 1) * 0.008);
                } elseif ($new_cur_value >= 100 && $new_cur_value < 200) {
                    $factor = 2.9 - (($new_cur_value - 100) * 0.002);
                } elseif ($new_cur_value >= 200 && $new_cur_value < 500) {
                    $factor = 2.7 - (($new_cur_value - 200) * 0.0005);
                } elseif ($new_cur_value >= 500 && $new_cur_value < 3000) {
                    $factor = 2.55 - (($new_cur_value - 500) * 0.0001);
                } else {
                    $factor = 2.3;
                }
                $tag_price = $factor * $new_cur_value;
                $tag_price = round($tag_price);
                $tag_price = number_format($tag_price, 2, '.', '');
            }
            $product->amount    = $new_cur_value;
            $product->tag_price = $tag_price;
            $product->save();

            $product_purchase                     = [];
            $product_purchase['variant_id']       = 1;
            $product_purchase['purchase_id']      = $lims_purchase_data->id;
            $product_purchase['product_id']       = $product->id;
            $product_purchase['imei_number']      = 1;
            $product_purchase['qty']              = 1;
            $product_purchase['recieved']         = 1;
            $product_purchase['purchase_unit_id'] = 1;
            $product_purchase['net_unit_cost']    = $data['amountfc'];
            $product_purchase['discount']         = 0;
            $product_purchase['tax_rate']         = 0;
            $product_purchase['tax']              = $new_cur_value;
            $product_purchase['total']            = $tag_price;
            ProductPurchase::create($product_purchase);
        }

        $this->cacheForget('product_list');
        $this->cacheForget('product_list_with_variant');
        return redirect()->back()->with('import_message', 'Product imported successfully');
    }

    public function importProduct1(Request $request)
    {
        //get file
        $upload = $request->file('file');
        $ext    = pathinfo($upload->getClientOriginalName(), PATHINFO_EXTENSION);
        if ($ext != 'csv') {
            return redirect()->back()->with('message', 'Please upload a CSV file');
        }

        $filePath = $upload->getRealPath();
        //open and read
        $file          = fopen($filePath, 'r');
        $header        = fgetcsv($file);
        $escapedHeader = [];
        //validate
        foreach ($header as $key => $value) {
            $lheader     = strtolower($value);
            $escapedItem = preg_replace('/[^a-z]/', '', $lheader);
            array_push($escapedHeader, $escapedItem);
        }
        //looping through other columns
        while ($columns = fgetcsv($file)) {
            foreach ($columns as $key => $value) {
                $value = preg_replace('/\D/', '', $value);
            }
            $data = array_combine($escapedHeader, $columns);

//            if ($data['brand'] != 'N/A' && $data['brand'] != '') {
//                $lims_brand_data = Brand::firstOrCreate(['title' => $data['brand'], 'is_active' => true]);
//                $brand_id = $lims_brand_data->id;
//            } else
            $brand_id = null;

            $lims_category_data = Category::firstOrCreate(['name' => $data['category'], 'is_active' => true]);

            $lims_unit_data = Unit::where('unit_code', $data['unitcode'])->first();
            if (! $lims_unit_data) {
                return redirect()->back()->with('not_permitted', 'Unit code does not exist in the database.');
            }

            $product = Product::firstOrNew(['name' => $data['name'], 'is_active' => true]);
            if ($data['image']) {
                $product->image = $data['image'];
            } else {
                $product->image = 'zummXD2dvAtI.png';
            }

            $product->name              = htmlspecialchars(trim($data['name']));
            $product->code              = $data['code'];
            $product->type              = strtolower($data['type']);
            $product->barcode_symbology = 'C128';
            $product->brand_id          = $brand_id;
            $product->category_id       = $lims_category_data->id;
            $product->unit_id           = $lims_unit_data->id;
            $product->purchase_unit_id  = $lims_unit_data->id;
            $product->sale_unit_id      = $lims_unit_data->id;
            $product->cost              = str_replace(",", "", $data['cost']);
            $product->price             = str_replace(",", "", $data['price']);
            $product->tax_method        = 1;
            $product->qty               = 0;
            $product->product_details   = $data['productdetails'];
            $product->is_active         = true;
            $product->save();
            //dealing with variants
            if ($data['variantvalue'] && $data['variantname']) {
                $variantInfo = explode(",", $data['variantvalue']);
                foreach ($variantInfo as $key => $info) {
                    $variant_option[] = strtok($info, "[");
                    $variant_value[]  = str_replace("/", ",", substr($info, strpos($info, "[") + 1, (strpos($info, "]") - strpos($info, "[") - 1)));
                }
                $product->variant_option = json_encode($variant_option);
                $product->variant_value  = json_encode($variant_value);
                $product->is_variant     = true;
                $product->save();

                $variant_names     = explode(",", $data['variantname']);
                $item_codes        = explode(",", $data['itemcode']);
                $additional_costs  = explode(",", $data['additionalcost']);
                $additional_prices = explode(",", $data['additionalprice']);
                foreach ($variant_names as $key => $variant_name) {
                    $variant = Variant::firstOrCreate(['name' => $variant_name]);
                    if ($data['itemcode']) {
                        $item_code = $item_codes[$key];
                    } else {
                        $item_code = $variant_name . '-' . $data['code'];
                    }

                    if ($data['additionalcost']) {
                        $additional_cost = $additional_costs[$key];
                    } else {
                        $additional_cost = 0;
                    }

                    if ($data['additionalprice']) {
                        $additional_price = $additional_prices[$key];
                    } else {
                        $additional_price = 0;
                    }

                    ProductVariant::create([
                        'product_id'       => $product->id,
                        'variant_id'       => $variant->id,
                        'position'         => $key + 1,
                        'item_code'        => $item_code,
                        'additional_cost'  => $additional_cost,
                        'additional_price' => $additional_price,
                        'qty'              => 0,
                    ]);
                }
            }
        }
        $this->cacheForget('product_list');
        $this->cacheForget('product_list_with_variant');
        return redirect('products')->with('import_message', 'Product imported successfully');
    }

    public function deleteBySelection(Request $request)
    {
        $product_id = $request['productIdArray'];
        foreach ($product_id as $id) {
            $lims_product_data            = Product::findOrFail($id);
            $lims_product_data->is_active = false;
            $lims_product_data->save();
        }
        $this->cacheForget('product_list');
        $this->cacheForget('product_list_with_variant');
        return 'Product deleted successfully!';
    }

    public function scrapedBySelection(Request $request)
    {
        $product_id = $request['productIdArray'];
        foreach ($product_id as $id) {
            $lims_product_data              = Product::findOrFail($id);
            $lims_product_data->item_status = 6;
            $lims_product_data->save();
        }
        return 'Product deleted successfully!';
    }

    public function activatebyselection(Request $request)
    {
        $product_id = $request['productIdArray'];
        foreach ($product_id as $id) {
            $lims_product_data              = Product::findOrFail($id);
            $lims_product_data->item_status = 3;
            $lims_product_data->save();
        }
        $this->cacheForget('product_list');
        $this->cacheForget('product_list_with_variant');
        return 'Product status changed successfully!';
    }

    public function holdbyselection(Request $request)
    {
        $product_id = $request['productIdArray'];
        foreach ($product_id as $id) {
            $lims_product_data              = Product::findOrFail($id);
            $lims_product_data->item_status = 2;
            $lims_product_data->save();
        }
        $this->cacheForget('product_list');
        $this->cacheForget('product_list_with_variant');
        return 'Product status changed successfully!';
    }

    public function destroy($id)
    {
        /*  if(!env('USER_VERIFIED')) {
             return redirect()->back()->with('not_permitted', 'This feature is disable for demo!');
         }
         else { */
        $lims_product_data            = Product::findOrFail($id);
        $lims_product_data->is_active = false;
        if ($lims_product_data->image != 'zummXD2dvAtI.png') {
            $images = explode(",", $lims_product_data->image);
            foreach ($images as $key => $image) {
                /* if(file_exists('public/images/product/'.$image))
                    unlink('public/images/product/'.$image); */
            }
        }
        $lims_product_data->save();

        if (! empty($lims_product_data->product_list)) {
            $main_product_list = explode(',', $lims_product_data->product_list);
            if (! empty($main_product_list)) {
                foreach ($main_product_list as $main_product) {
                    $pro              = Product::find($main_product);
                    $pro->item_status = 3;
                    $pro->save();
                }
            }
        }

        $this->cacheForget('product_list');
        $this->cacheForget('product_list_with_variant');
        return redirect('products')->with('message', 'Product deleted successfully');
        //}
    }

    public function productalphabatic($serial_number, $parcel_number, $supplier_id, Request $request)
    {

        $character    = $request->query()['character'];
        $stringLength = Strlen($character);
        if ($stringLength != 1) {
            $message = "Alphabatic character length should be 4 digits";
        } else if (is_numeric($character)) {
            $message = "Alphabatic character should be in alphabatic format";
        } else {
            $message = "";
        }

        if ($serial_number != '' && $parcel_number != '' && $supplier_id != '') {

            $rh_reference_id = $this->get_rh_reference_code($supplier_id, $serial_number, $parcel_number, $character);
        }

        if (isset($request->query()['type']) && ! empty($request->query()['type']) && $request->query()['type'] == 'marged') {
            $rh_reference_id = 'RD24';
            if (! empty($parcel_number)) {
                $rh_reference_id = $rh_reference_id . $parcel_number;
            }
            if ($serial_number != "null") {
                $rh_reference_id = $rh_reference_id . $serial_number;
            }
        }

        $json_data = [
            "message" => $message,
            "rhid"    => $rh_reference_id,

        ];
        echo json_encode($json_data);
    }

    public function productserialnumber($serial_number, $parcel_number, $supplier_id, Request $request)
    {

        $character = ($request->query()['character']) ? $request->query()['character'] : null;
        $query     = Product::where('serial_number', $serial_number)->where('parcel_number', $parcel_number)->where('is_active', true);
        if ($supplier_id && ! empty($supplier_id) && $supplier_id != 'null') {
            $query = $query->where('supplier_id', $supplier_id);
        } else {
            $query = $query->whereNull('supplier_id');
        }
        $qserialnumber = $query->first();

        $stringLength = Strlen($serial_number);
        if (isset($request->query()['type']) && ! empty($request->query()['type']) && $request->query()['type'] == 'marged') {
            $number = 4;
        } elseif (isset($request->query()['type']) && ! empty($request->query()['type']) && $request->query()['type'] == 'global') {
            $number    = 4;
            $character = null;
        } else {
            $number = 3;
        }
        if ($stringLength != $number) {
            $message = "Serial number length should be $number digits";
        } else if (! is_numeric($serial_number)) {
            $message = "Serial number should be in number format";
        } else if (isset($qserialnumber) && $qserialnumber->serial_number != '') {
            $message = "Serial number exists";
        } else {
            $message = "";
        }

        if ($serial_number != '' && $parcel_number != '' && $supplier_id != '') {

            $rh_reference_id = $this->get_rh_reference_code($supplier_id, $serial_number, $parcel_number, $character);
        }

        if (isset($request->query()['type']) && ! empty($request->query()['type']) && $request->query()['type'] == 'marged') {
            $rh_reference_id = 'RD24';
            if (! empty($parcel_number)) {
                $rh_reference_id = $rh_reference_id . $parcel_number;
            }
            if ($serial_number != "null") {
                $rh_reference_id = $rh_reference_id . $serial_number;
            }
        }

        $json_data = [
            "message" => $message,
            "rhid"    => $rh_reference_id,

        ];
        echo json_encode($json_data);
    }

    public function get_rh_reference_code($supplier_id, $serial_number, $parcel_number, $character = null)
    {

        $new_rhrfid = '';
        $supplier   = Supplier::find($supplier_id);
        if (isset($supplier)) {
            $supplier_code = $supplier->supplier_code;
            $new_rhrfid .= $supplier_code;
        }
        $new_rhrfid .= date("y");
        if (! empty($character) && $character != 'null') {
            $new_rhrfid .= $character;
        }
        if (! empty($parcel_number) && $parcel_number != 'null') {
            $new_rhrfid .= $parcel_number;
        }
        if (! empty($serial_number) && $serial_number != 'null') {
            $new_rhrfid .= $serial_number;
        }
        //$new_rhrfid= $supplier_code.$year.$parcel_number.$serial_number;
        $productn = Product::where('rh_reference_id', $new_rhrfid)->where('is_active', true)->first();
        if ($new_rhrfid != '') {
            return strtoupper($new_rhrfid);
        } else {
            return '';
        }
    }

    public function productparcelnumber($serial_number, $parcel_number, $supplier_id, Request $request)
    {
        $character = ($request->query()['character']) ? $request->query()['character'] : null;

        $query = Product::where('serial_number', $serial_number)->where('parcel_number', $parcel_number)->where('is_active', true);
        if ($supplier_id && ! empty($supplier_id) && $supplier_id != 'null') {
            $query = $query->where('supplier_id', $supplier_id);
        } else {
            $query = $query->whereNull('supplier_id');
        }
        $qparcelnumber = $query->first();

        $stringLength = Strlen($parcel_number);
        if ($stringLength != 2) {
            $message = "Parcel number length should be 2 digits";
        } /* else if(ctype_alnum($parcel_number)){
            $message="Parcel number should be in alphanumeric format";
        } */else if (isset($qparcelnumber) && $qparcelnumber->serial_number != '') {
            $message = "Parcel number exists";
        } else {
            $message = "";
        }

        //if($serial_number!='' && $parcel_number!='' && $supplier_id!=''){

        $rh_reference_id = $this->get_rh_reference_code($supplier_id, $serial_number, $parcel_number, $character);
        if (isset($request->query()['type']) && ! empty($request->query()['type']) && $request->query()['type'] == 'marged') {
            $rh_reference_id = 'RD24';
            if (! empty($parcel_number)) {
                $rh_reference_id = $rh_reference_id . $parcel_number;
            }
            if ($serial_number != "null") {
                $rh_reference_id = $rh_reference_id . $serial_number;
            }
        }
        //}
        $json_data = [
            "message" => $message,
            "rhid"    => $rh_reference_id,
        ];
        echo json_encode($json_data);
    }

    public function productsuppliernumber($serial_number, $parcel_number, $supplier_id, Request $request)
    {
        $character = ($request->query()['character']) ? $request->query()['character'] : null;
        $supplier  = Supplier::find($supplier_id);
        if (isset($supplier)) {
            $stringLength = Strlen($parcel_number);
            if (! is_numeric($supplier_id)) {
                $message = "Supplier code should be an integer";
            } else {
                $message = "";
            }
        } else {
            $message = "Invalid Supplier code";
        }
        //if($serial_number!='' && $parcel_number!='' && $supplier_id!=''){
        $rh_reference_id = $this->get_rh_reference_code($supplier_id, $serial_number, $parcel_number, $character);
        //}
        $json_data = [
            "message" => $message,
            "rhid"    => $rh_reference_id,
        ];
        echo json_encode($json_data);
    }

    public function productCurrencyConvertor($currency_type, $f_currency, $amount = null)
    {

        $new_cur_value = '';
        $message       = '';
        if (! $amount) {
            $currency = Currency::find($currency_type);
            if (isset($currency) || ! empty($f_currency)) {
                if (! empty($f_currency && $f_currency != 'null')) {
                    if (! is_numeric($f_currency)) {
                        $message = "Currency should be an integer";
                    } else if (isset($currency)) {
                        $new_cur_value = round($currency->exchange_rate * $f_currency, 2);
                    }
                } else {
                    $message = "";
                }
            }
        } else {
            $new_cur_value = $amount;
        }

        $tag_price = 0;
        if ($new_cur_value && $new_cur_value > 0) {
            if ($new_cur_value < 100) {
                $factor = 3.7 - (($new_cur_value - 1) * 0.008);
            } elseif ($new_cur_value >= 100 && $new_cur_value < 200) {
                $factor = 2.9 - (($new_cur_value - 100) * 0.002);
            } elseif ($new_cur_value >= 200 && $new_cur_value < 500) {
                $factor = 2.7 - (($new_cur_value - 200) * 0.0005);
            } elseif ($new_cur_value >= 500 && $new_cur_value < 3000) {
                $factor = 2.55 - (($new_cur_value - 500) * 0.0001);
            } else {
                $factor = 2.3;
            }
            $tag_price = $factor * $new_cur_value;
            $tag_price = round($tag_price);
            $tag_price = number_format($tag_price, 2, '.', '');
        }

        $json_data = [
            "message"   => $message,
            "fcurvalue" => $new_cur_value,
            "tagprice"  => $tag_price,
        ];
        echo json_encode($json_data);
    }

    public function productCsv()
    {
        return view('backend.product.csv');
    }

    public function productCsvUpload(Request $request)
    {
        if (! empty($request->csv)) {
            $implode = [];
            $handle  = fopen($request->csv, "r");
            $headers = fgetcsv($handle, 1000, ",");
            while (($dataCsv = fgetcsv($handle, 1000, ",")) !== false) {
                $implode[] = implode(',', $dataCsv);
            }
            fclose($handle);
            if (! empty($implode)) {
                foreach ($implode as $imp) {
                    $data          = explode(',', $imp);
                    $item_code     = $data[0];
                    $item_code_len = strlen($item_code);
                    $remaining     = $item_code_len - 6;
                    $supplier_code = substr($item_code, 0, 2);
                    $parcel_number = substr($item_code, 4, 2);
                    $serial_number = substr($item_code, 6, $remaining);

                    $supplier = Supplier::where('supplier_code', $supplier_code)->first();

                    $caregory         = Category::where('name', $data[2])->first();
                    $second_caregory  = SecondCategory::where('name', $data[3])->first();
                    $third_caregory   = ThirdCategory::where('name', $data[4])->first();
                    $product_material = ProductMaterial::where('name', $data[5])->first();
                    $material_color   = MaterialColor::where('name', $data[7])->first();
                    //$colors = [];

//                    if (strpos($data[13], '-+') == true) {
//                        $colors = explode('-+', $data[13]);
//                    } elseif (strpos($data[13], '+') == true) {
//                        $colors = explode('+', $data[13]);
//                    } elseif (strpos($data[13], '=Princess Shape') == true) {
//                        $color = explode('=', $data[13]);
//                        if (strlen($color[0]) == 4) {
//                            $colors[] = substr($color[0], 0, 2);
//                            $colors[] = substr($color[0], 2);
//                        }
//                    } else {
//                        $colors[] = $data[13];
//                    }
                    // dd($data[12]);

//                    $diamond_shape = DiamondShape::where('name', $data[13])->first();
//                    $diamond_shape = DiamondShape::whereIn('name', $colors)->pluck('id')->toArray();
//                    $diamond_color = DiamondColor::where('name', $data[12])->first();

                    // $save['diamond_color_id'] = implode(',', $diamond_shape);
                    // $save['sub_category_id'] = $second_caregory->id ?? null;
                    // $save['csv_item_code'] = $data[0];
                    // $save['csv_supplier_product_id'] = $data[1];
                    // $save['csv_type'] = $data[2];
                    // $save['csv_category'] = $data[3];
                    // $save['csv_subcategory'] = $data[4];
                    // $save['csv_metal_type'] = $data[5];
                    // $save['csv_gold_karat'] = $data[6];
                    // $save['csv_color'] = $data[7];
                    // $save['csv_weight'] = $data[8];
                    // $save['csv_gross_weight'] = $data[9];
                    // $save['csv_diamon_carat'] = $data[10];
                    // $save['csv_diamon_clarity'] = $data[11];
                    // $save['csv_diamon_color'] = $data[12];
                    // $save['csv_diamon_cut'] = $data[13];
                    // $save['csv_stone_weight'] = $data[14];
                    // $save['csv_stone_type'] = $data[15];
                    // $save['csv_cost_price'] = $data[16];
                    // $save['csv_item_status'] = $data[17];

                    // if (Product::where('rh_reference_id', $item_code)->exists()) {
                    //     Product::where('rh_reference_id', $item_code)->update($save);
                    // }

//                    if ($data[6] == 750) {
//                        $product_karat = 1;
//                    } elseif ($data[6] == 875) {
//                        $product_karat = 2;
//                    } elseif ($data[6] == 917) {
//                        $product_karat = 3;
//                    } elseif ($data[6] == 9999) {
//                        $product_karat = 4;
//                    } else {
//                        $product_karat = null;
//                    }
                    $product_karat = 1;

                    $save['rh_reference_id']     = $item_code;
                    $save['sup_item_ref']        = $data[1] ? preg_replace('/\s+/', '', $data[1]) : null;
                    $save['supplier_id']         = $supplier->id ?? null;
                    $save['serial_number']       = $serial_number;
                    $save['parcel_number']       = $parcel_number;
                    $save['category_id']         = $caregory->id ?? null;
                    $save['sub_category_id']     = $second_caregory->id ?? null;
                    $save['third_category_id']   = $third_caregory->id ?? null;
                    $save['product_material_id'] = $product_material->id ?? null;
                    $save['material_color_id']   = $material_color->id ?? null;
                    $save['product_karat_id']    = $product_karat;
                    $save['material_weight']     = $data[8];
                    $save['gross_weight']        = $data[9];

                    $save['diamond_carat_id'] = $data[10] ?? null;

                    $names_array = array_map('trim', explode('+', $data[11]));
                    $names_ids   = DiamondClarity::whereIn('name', $names_array)
                        ->pluck('id')
                        ->implode(',');
                    $save['diamond_clarity_id'] = $names_ids ?? null;

                    $names_array = array_map('trim', explode('+', $data[12]));
                    $names_ids   = DiamondColor::whereIn('name', $names_array)
                        ->pluck('id')
                        ->implode(',');
                    $save['diamond_color_id'] = $names_ids ?? null;

                    $names_array = array_map('trim', explode('+', $data[13]));
                    $names_ids   = DiamondShape::whereIn('name', $names_array)
                        ->pluck('id')
                        ->implode(',');
                    $save['diamond_shape_id'] = $names_ids ?? null;

                    $save['stone_weight']            = $data[14] ?? null;
                    $save['stone_type']              = $data[15] ?? null;
                    $save['f_stone_shape']           = null;
                    $save['amount']                  = $data[16] ?? null;
                    $save['is_active']               = true;
                    $save['csv_item_code']           = $data[0];
                    $save['csv_supplier_product_id'] = $data[1];
                    $save['csv_type']                = $data[2];
                    $save['csv_category']            = $data[3];
                    $save['csv_subcategory']         = $data[4];
                    $save['csv_metal_type']          = $data[5];
                    $save['csv_gold_karat']          = $data[6];
                    $save['csv_color']               = $data[7];
                    $save['csv_weight']              = $data[8];
                    $save['csv_gross_weight']        = $data[9];
                    $save['csv_diamon_carat']        = $data[10];
                    $save['csv_diamon_clarity']      = $data[11];
                    $save['csv_diamon_color']        = $data[12];
                    $save['csv_diamon_cut']          = $data[13];
                    $save['csv_stone_weight']        = $data[14];
                    $save['csv_stone_type']          = $data[15];
                    $save['csv_cost_price']          = $data[16];

                    $new_cur_value = $save['amount'];
                    if ($new_cur_value && $new_cur_value > 0) {
                        if ($new_cur_value < 100) {
                            $factor = 3.7 - (($new_cur_value - 1) * 0.008);
                        } elseif ($new_cur_value >= 100 && $new_cur_value < 200) {
                            $factor = 2.9 - (($new_cur_value - 100) * 0.002);
                        } elseif ($new_cur_value >= 200 && $new_cur_value < 500) {
                            $factor = 2.7 - (($new_cur_value - 200) * 0.0005);
                        } elseif ($new_cur_value >= 500 && $new_cur_value < 3000) {
                            $factor = 2.55 - (($new_cur_value - 500) * 0.0001);
                        } else {
                            $factor = 2.3;
                        }
                        $tag_price = $factor * $new_cur_value;
                        $tag_price = round($tag_price);
                        $tag_price = number_format($tag_price, 2, '.', '');
                    } else {
                        dd($data);
                    }
                    $save['tag_price'] = $tag_price;

                    if (! empty($item_code) && Product::where('rh_reference_id', $item_code)->where('is_active', true)->doesntExist()) {
                        $save['item_status'] = ($data[17] == 'Active') ? 3 : 2;
                        Product::create($save);
                    } elseif (! empty($item_code)) {
                        Product::where('rh_reference_id', $item_code)->update($save);
                    }

                    // $product = new Product();
                    // $product->rh_reference_id = $item_code;
                    // $product->sup_item_ref = $data[1];
                    // $product->supplier_id = $supplier->id ?? null;
                    // $product->serial_number = $serial_number;
                    // $product->parcel_number = $parcel_number;
                    // $product->category_id = $caregory->id ?? null;
                    // $product->sub_category_id = $second_caregory->id ?? null;
                    // $product->third_category_id = $third_caregory->id ?? null;
                    // $product->product_material_id = $product_material->id ?? null;
                    // $product->material_color_id = $material_color->id ?? null;
                    // $product->product_karat_id = $product_karat;
                    // $product->material_weight = $data[8];
                    // $product->gross_weight = $data[9];
                    // $product->diamond_shape_id = $diamond_shape->id ?? null;
                    // $product->diamond_carat_id = $data[10];
                    // $product->diamond_clarity_id = $data[11];
                    // $product->diamond_color_id = $diamond_color->id ?? null;
                    // $product->stone_type = $data[15];
                    // $product->stone_weight = $data[14];
                    // $product->amount = $data[16];
                    // $product->is_active = true;
                    // if ($data[17] == 'Active') {
                    //     $product->item_status = 3;
                    // } else {
                    //     $product->item_status = 2;
                    // }
                    // $product->save();
                }
            }
        }
        return redirect()->back()->with('not_permitted', 'Data created successfully');
    }

    public function product_remove_merged(Request $request)
    {
        $product_id      = $request->id;
        $rh_reference_id = $request->pro;
        $product         = Product::find($product_id);
        $merged          = explode(',', $product->product_list);

        $rh_reference_id_pro[] = Product::where('rh_reference_id', $rh_reference_id)->where('is_active', true)->value('id');
        $implode               = implode(',', array_diff($merged, $rh_reference_id_pro));
        $product->product_list = $implode;
        $product->save();

        $update              = Product::where('rh_reference_id', $rh_reference_id)->where('is_active', true)->first();
        $update->item_status = 3;
        $update->save();

        $main_product        = Product::where('id', $product_id)->first();
        $main_product_list   = explode(',', $main_product->product_list);
        $product_material_id = null;
        $product_karat_id    = null;
        $diamond_shape_id    = null;
        $diamond_clarity_id  = null;
        $diamond_color_id    = null;
        $stone_type          = null;
        $material_weight     = 0;
        $gross_weight        = 0;
        $diamond_carat_id    = 0;
        $stone_weight        = 0;
        $amount              = 0;
        $amountfc            = 0;
        $tag_price           = 0;
        if (! empty($main_product_list)) {
            foreach ($main_product_list as $main_product) {
                $pro = Product::find($main_product);
                if (! empty($product_material_id)) {
                    $product_material_id = $product_material_id . ',' . $pro->product_material_id ?? null;
                } else {
                    $product_material_id = $pro->product_material_id ?? null;
                }
                if (! empty($product_karat_id)) {
                    $product_karat_id = $product_karat_id . ',' . $pro->product_karat_id ?? null;
                } else {
                    $product_karat_id = $pro->product_karat_id ?? null;
                }
                if (! empty($diamond_shape_id)) {
                    $diamond_shape_id = $diamond_shape_id . ',' . $pro->diamond_shape_id ?? null;
                } else {
                    $diamond_shape_id = $pro->diamond_shape_id ?? null;
                }
                if (! empty($diamond_clarity_id)) {
                    $diamond_clarity_id = $diamond_clarity_id . ',' . $pro->diamond_clarity_id ?? null;
                } else {
                    $diamond_clarity_id = $pro->diamond_clarity_id ?? null;
                }
                if (! empty($diamond_color_id)) {
                    $diamond_color_id = $diamond_color_id . ',' . $pro->diamond_color_id;
                } else {
                    $diamond_color_id = $pro->diamond_color_id;
                }
                if ($pro->stone_type != null) {
                    if (! empty($stone_type)) {
                        $stone_type = $stone_type . ',' . $pro->stone_type;
                    } else {
                        $stone_type = $pro->stone_type;
                    }
                }
                $material_weight  = (float) $material_weight + (float) $pro->material_weight;
                $gross_weight     = (float) $gross_weight + (float) $pro->gross_weight;
                $diamond_carat_id = (float) $diamond_carat_id + (float) $pro->diamond_carat_id;
                $amount           = (float) $amount + (float) $pro->amount;
                $amountfc         = (float) $amountfc + (float) $pro->amountfc;
                $tag_price        = (float) $tag_price + (float) $pro->tag_price;
                $stone_weight     = (float) $stone_weight + (float) $pro->stone_weight;
            }
        }

        $main_product1                      = Product::where('id', $product_id)->first();
        $main_product1->product_material_id = (string) $product_material_id;
        $main_product1->product_karat_id    = $product_karat_id;
        $main_product1->diamond_shape_id    = $diamond_shape_id;
        $main_product1->diamond_clarity_id  = $diamond_clarity_id;
        $main_product1->diamond_color_id    = $diamond_color_id;
        $main_product1->stone_type          = $stone_type;
        $main_product1->material_weight     = $material_weight;
        $main_product1->gross_weight        = $gross_weight;
        $main_product1->diamond_carat_id    = $diamond_carat_id;
        $main_product1->amount              = $amount;
        $main_product1->amountfc            = $amountfc;
        $main_product1->tag_price           = number_format(round($tag_price), 2, '.', '');
        $main_product1->stone_weight        = $stone_weight;
        $main_product1->save();

//        $product_list = explode(',', $product->product_list);
//        $amountFC = 0;
//        $amountKD = 0;
//        $tagPrice = 0;
//        if (!empty($product_list)) {
//            foreach ($product_list as $pro_list) {
//                $pro = Product::find($pro_list);
//                $amount_fc = $pro->amountfc ?? 0;
//                $amount = $pro->amount ?? 0;
//                $tag_price = $pro->tag_price ?? 0;
//                $amountFC = $amountFC + $amount_fc;
//                $amountKD = $amountKD + $amount;
//                $tagPrice = $tagPrice + $tag_price;
//            }
//        }
//        $data['amount_fc'] = $amountFC;
//        $data['amount_kd'] = $amountKD;
//        $data['tag_price'] = $tagPrice;

        return response()->json(['status' => 'success', 'data' => null]);
    }

    public function productCsvStatus(Request $request)
    {
        if (! empty($request->csv)) {
            $implode = [];
            $handle  = fopen($request->csv, "r");
            $headers = fgetcsv($handle, 1000, ",");
            while (($dataCsv = fgetcsv($handle, 1000, ",")) !== false) {
                $implode[] = implode(',', $dataCsv);
            }
            fclose($handle);
            $item_code = [];
            if (! empty($implode)) {
                foreach ($implode as $imp) {
                    $data = explode(',', $imp);

                    $item_code = $data[0];
                    if (! empty($item_code) && Product::where('rh_reference_id', $item_code)->where('is_active', true)->doesntExist()) {
                        $item_code_len = strlen($item_code);
                        $remaining     = $item_code_len - 6;
                        $supplier_code = substr($item_code, 0, 2);
                        $parcel_number = substr($item_code, 4, 2);
                        $serial_number = substr($item_code, 6, $remaining);

                        $supplier = Supplier::where('supplier_code', $supplier_code)->first();

                        $caregory = Category::where('name', $data[2])->first();

                        $second_caregory = SecondCategory::where('name', $data[3])->first();

                        $third_caregory = ThirdCategory::where('name', $data[4])->first();

                        $product_material = ProductMaterial::where('name', $data[5])->first();
                        $material_color   = MaterialColor::where('name', $data[7])->first();
                        $diamond_shape    = DiamondShape::where('name', $data[13])->first();
                        $diamond_color    = DiamondColor::where('name', $data[12])->first();

                        if ($data[6] == 750) {
                            $product_karat = 1;
                        } elseif ($data[6] == 875) {
                            $product_karat = 2;
                        } elseif ($data[6] == 917) {
                            $product_karat = 3;
                        } elseif ($data[6] == 9999) {
                            $product_karat = 4;
                        } else {
                            $product_karat = null;
                        }
                        $save['rh_reference_id']     = $item_code;
                        $save['sup_item_ref']        = $data[1];
                        $save['supplier_id']         = $supplier->id ?? null;
                        $save['serial_number']       = $serial_number;
                        $save['parcel_number']       = $parcel_number;
                        $save['category_id']         = $caregory->id ?? null;
                        $save['sub_category_id']     = $second_caregory->id ?? null;
                        $save['third_category_id']   = $third_caregory->id ?? null;
                        $save['product_material_id'] = $product_material->id ?? null;
                        $save['material_color_id']   = $material_color->id ?? null;
                        $save['product_karat_id']    = $product_karat;
                        $save['material_weight']     = $data[8];
                        $save['gross_weight']        = $data[9];
                        $save['diamond_shape_id']    = $diamond_shape->id ?? null;
                        $save['diamond_carat_id']    = $data[10];
                        $save['diamond_clarity_id']  = $data[11];
                        $save['diamond_color_id']    = $diamond_color->id ?? null;
                        $save['stone_type']          = $data[15];
                        $save['stone_weight']        = $data[14];
                        $save['amount']              = $data[16];
                        $save['is_active']           = true;
                        $save['item_status']         = 7;

                        Product::create($save);
                    } elseif (Product::where('rh_reference_id', $item_code)->where('is_active', true)->exists()) {
                        Product::where('rh_reference_id', $item_code)->where('is_active', true)->update(['item_status' => 7]);
                    }
                }
            }
        }
        return redirect()->back()->with('not_permitted', 'Data created successfully');
    }

    public function productCsvCategory(Request $request)
    {
        if (! empty($request->csv)) {
            $implode = [];
            $handle  = fopen($request->csv, "r");
            $headers = fgetcsv($handle, 1000, ",");
            while (($dataCsv = fgetcsv($handle, 1000, ",")) !== false) {
                $implode[] = implode(',', $dataCsv);
            }
            fclose($handle);
            $item_code = [];
            if (! empty($implode)) {
                foreach ($implode as $imp) {
                    $data      = explode(',', $imp);
                    $item_code = $data[0];

                    $product = Product::where('rh_reference_id', $item_code)->where('is_active', true)->first();
                    if (! empty($product)) {
                        $type = Category::where('name', 'like', '%' . $data[1] . '%')->first();
                        if (! empty($type)) {
                            $product->category_id = $type->id;
                        }
                        $product->save();
                    }
                }
            }
        }
        return redirect()->back()->with('not_permitted', 'Data created successfully');
    }

    public function productCsvSold(Request $request)
    {
        if (! empty($request->csv)) {
            $implode = [];
            $handle  = fopen($request->csv, "r");
            $headers = fgetcsv($handle, 1000, ",");
            while (($dataCsv = fgetcsv($handle, 1000, ",")) !== false) {
                $implode[] = implode(',', $dataCsv);
            }
            fclose($handle);
            if (! empty($implode)) {
                foreach ($implode as $imp) {
                    $data      = explode(',', $imp);
                    $item_code = $data[1];
                    $status    = (isset($data[2]) && ! empty($data[2])) ? $data[2] : null;
                    if (! empty($status) && $status == 'INSTOCK') {

                        if (! empty($item_code) && Product::where('rh_reference_id', $item_code)->where('is_active', true)->doesntExist()) {

                        } elseif (! empty($item_code)) {
                            $product              = Product::where('rh_reference_id', $item_code)->where('is_active', true)->first();
                            $product->item_status = 3;
                            $product->save();
                        }
                    }
                }
            }
        }
        return redirect()->back()->with('not_permitted', 'Data created successfully');
    }

    public function product_merged_lists(Request $request)
    {
        $products = Product::find($request->id);
        $html     = null;
        if (! empty($products->product_list)) {
            $html .= '<tr>';
            foreach (explode(',', $products->product_list) as $product_list) {
                $product       = Product::find($product_list);
                $product_image = explode(",", $product->image);
                $product_image = htmlspecialchars($product_image[0]);
                $ext           = '.png';
                $substr        = substr($product->rh_reference_id, 2, 2);
                if (is_numeric($substr) && (int) $substr < 20) {
                    $sup_item_ref = $product->rh_reference_id;
                } else {
                    $sup_item_ref = $product->sup_item_ref;
                }

                $png_image = public_path() . '/storage/photos/1/Product/' . $sup_item_ref . '.png';
                if (file_exists($png_image) == true) {
                    $ext = '.png';
                }
                $jpg_image = public_path() . '/storage/photos/1/Product/' . $sup_item_ref . '.jpg';
                if (file_exists($jpg_image) == true) {
                    $ext = '.jpg';
                }
                //png jpg
                $image = '<img src="' . url('/') . '/storage/photos/1/Product/' . $sup_item_ref . '' . $ext . '" height="80" width="80">';

                $category     = SecondCategory::find($product->sub_category_id);
                $sub_category = ThirdCategory::find($product->third_category_id);
                $sale         = Product_Sale::join('sales', 'sales.id', 'product_sales.sale_id')
                    ->where('product_sales.product_id', $product['id'])
                    ->orderBy('product_sales.created_at', 'DESC')
                    ->first();

                $returned_amount = 0;
                $sale_total      = 0;
                if (! empty($sale)) {
                    $returned_amount = DB::table('returns')->where('sale_id', $sale->id)->sum('grand_total');
                }
                if (! empty($sale) && $returned_amount == 0) {
                    $sale_total = $sale['total'];
                }

                $status       = '<span class="badge text-uppercase" style="background-color: ' . $product->status->bg_color . '; color: ' . $product->status->font_color . '">' . $product->status->name . '</span>';
                $amount       = $product->amount;
                $tag_price    = $product->tag_price;
                $sale_amount  = ($returned_amount == 0) ? $sale_total : 0;
                $product_type = $product->category->name ?? '-';
                $category     = $category['name'] ?? '-';
                $sub_category = $sub_category['name'] ?? '-';
                $supplier     = $product->supplier->supplier_name ?? null;
                $html .= '<tr>
                            <td>' . $image . '</td>
                            <td>' . $product->rh_reference_id . '</td>
                            <td>' . $product_type . '</td>
                            <td>' . $category . '</td>
                            <td>' . $sub_category . '</td>
                            <td>' . $supplier . '</td>
                            <td>' . $amount . '</td>
                            <td>' . $tag_price . '</td>
                            <td>' . $sale_amount . '</td>
                            <td>' . $status . '</td>
                            <td><button class="btn btn-danger btn-sm" onclick="removeProduct(' . $product->id . ',' . $products->id . ')">Remove</button></td>
                        </tr>';
            }
            $html .= '</tr>';
        }
        $data['html'] = $html;
        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function product_merged_remove(Request $request)
    {
        $remove_pro_id       = $request->remove_pro_id;
        $rh_reference_id_pro = Product::where('id', $request->parant_id)->first();
        $product_list        = explode(',', $rh_reference_id_pro->product_list);
        unset($product_list[array_search($remove_pro_id, $product_list)]);
        $implode                           = implode(',', $product_list);
        $rh_reference_id_pro->product_list = $implode;
        $rh_reference_id_pro->save();

        $product              = Product::where('id', $remove_pro_id)->first();
        $product->item_status = 3;
        $product->save();

        $main_product        = Product::where('id', $request->parant_id)->first();
        $main_product_list   = explode(',', $main_product->product_list);
        $product_material_id = null;
        $product_karat_id    = null;
        $diamond_shape_id    = null;
        $diamond_clarity_id  = null;
        $diamond_color_id    = null;
        $stone_type          = null;
        $material_weight     = 0;
        $gross_weight        = 0;
        $diamond_carat_id    = 0;
        $stone_weight        = 0;
        $amount              = 0;
        $amountfc            = 0;
        $tag_price           = 0;
        if (! empty($main_product_list)) {
            foreach ($main_product_list as $main_product) {
                $pro = Product::find($main_product);
                if (! empty($product_material_id)) {
                    $product_material_id = $product_material_id . ',' . $pro->product_material_id ?? null;
                } else {
                    $product_material_id = $pro->product_material_id ?? null;
                }
                if (! empty($product_karat_id)) {
                    $product_karat_id = $product_karat_id . ',' . $pro->product_karat_id ?? null;
                } else {
                    $product_karat_id = $pro->product_karat_id ?? null;
                }
                if (! empty($diamond_shape_id)) {
                    $diamond_shape_id = $diamond_shape_id . ',' . $pro->diamond_shape_id ?? null;
                } else {
                    $diamond_shape_id = $pro->diamond_shape_id ?? null;
                }
                if (! empty($diamond_clarity_id)) {
                    $diamond_clarity_id = $diamond_clarity_id . ',' . $pro->diamond_clarity_id ?? null;
                } else {
                    $diamond_clarity_id = $pro->diamond_clarity_id ?? null;
                }
                if (! empty($diamond_color_id)) {
                    $diamond_color_id = $diamond_color_id . ',' . $pro->diamond_color_id;
                } else {
                    $diamond_color_id = $pro->diamond_color_id;
                }
                if ($pro->stone_type != null) {
                    if (! empty($stone_type)) {
                        $stone_type = $stone_type . ',' . $pro->stone_type;
                    } else {
                        $stone_type = $pro->stone_type;
                    }
                }
                $material_weight  = (float) $material_weight + (float) $pro->material_weight;
                $gross_weight     = (float) $gross_weight + (float) $pro->gross_weight;
                $diamond_carat_id = (float) $diamond_carat_id + (float) $pro->diamond_carat_id;
                $amount           = (float) $amount + (float) $pro->amount;
                $amountfc         = (float) $amountfc + (float) $pro->amountfc;
                $tag_price        = (float) $tag_price + (float) $pro->tag_price;
                $stone_weight     = (float) $stone_weight + (float) $pro->stone_weight;
            }
        }

        $main_product1                      = Product::where('id', $request->parant_id)->first();
        $main_product1->product_material_id = (string) $product_material_id;
        $main_product1->product_karat_id    = $product_karat_id;
        $main_product1->diamond_shape_id    = $diamond_shape_id;
        $main_product1->diamond_clarity_id  = $diamond_clarity_id;
        $main_product1->diamond_color_id    = $diamond_color_id;
        $main_product1->stone_type          = $stone_type;
        $main_product1->material_weight     = $material_weight;
        $main_product1->gross_weight        = $gross_weight;
        $main_product1->diamond_carat_id    = $diamond_carat_id;
        $main_product1->stone_weight        = $stone_weight;
        $main_product1->amount              = $amount;
        $main_product1->amountfc            = $amountfc;
        $main_product1->tag_price           = number_format(round($tag_price), 2, '.', '');
        $main_product1->save();

        return response()->json(['status' => 'success']);
    }

    public function product_show_lists(Request $request)
    {
        $product_fi = Product::whereRaw("find_in_set($request->id,product_list)")->first();
        $productids = explode(',', $product_fi->product_list);
        $products   = Product::whereIn('id', $productids)->where('id', '!=', $request->id)->get();
        $html       = '';
        $html .= '<tr>';
        foreach ($products as $product) {
            $product_image = explode(",", $product->image);
            $product_image = htmlspecialchars($product_image[0]);
            $ext           = '.png';
            $substr        = substr($product->rh_reference_id, 2, 2);
            if (is_numeric($substr) && (int) $substr < 20) {
                $sup_item_ref = $product->rh_reference_id;
            } else {
                $sup_item_ref = $product->sup_item_ref;
            }

            $png_image = public_path() . '/storage/photos/1/Product/' . $sup_item_ref . '.png';
            if (file_exists($png_image) == true) {
                $ext = '.png';
            }
            $jpg_image = public_path() . '/storage/photos/1/Product/' . $sup_item_ref . '.jpg';
            if (file_exists($jpg_image) == true) {
                $ext = '.jpg';
            }
            //png jpg
            $image = '<img src="' . url('/') . '/storage/photos/1/Product/' . $sup_item_ref . '' . $ext . '" height="80" width="80">';

            $category     = SecondCategory::find($product->sub_category_id);
            $sub_category = ThirdCategory::find($product->third_category_id);
            $sale         = Product_Sale::join('sales', 'sales.id', 'product_sales.sale_id')
                ->where('product_sales.product_id', $product['id'])
                ->orderBy('product_sales.created_at', 'DESC')
                ->first();

            $returned_amount = 0;
            $sale_total      = 0;
            if (! empty($sale)) {
                $returned_amount = DB::table('returns')->where('sale_id', $sale->id)->sum('grand_total');
            }
            if (! empty($sale) && $returned_amount == 0) {
                $sale_total = $sale['total'];
            }

            $status       = '<span class="badge text-uppercase" style="background-color: ' . $product->status->bg_color . '; color: ' . $product->status->font_color . '">' . $product->status->name . '</span>';
            $amount       = $product->amount;
            $tag_price    = $product->tag_price;
            $sale_amount  = ($returned_amount == 0) ? $sale_total : 0;
            $product_type = $product->category->name ?? '-';
            $category     = $category['name'] ?? '-';
            $sub_category = $sub_category['name'] ?? '-';
            $supplier     = $product->supplier->supplier_name ?? null;
            $html .= '<tr>
                            <td>' . $image . '</td>
                            <td>' . $product->rh_reference_id . '</td>
                            <td>' . $product_type . '</td>
                            <td>' . $category . '</td>
                            <td>' . $sub_category . '</td>
                            <td>' . $supplier . '</td>
                            <td>' . $amount . '</td>
                            <td>' . $tag_price . '</td>
                            <td>' . $sale_amount . '</td>
                            <td>' . $status . '</td>
                        </tr>';
        }
        $html .= '</tr>';
        $data['html'] = $html;
        return response()->json(['status' => 'success', 'data' => $data]);
    }
}
