import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_ecommerce/common/basewidget/title_row_widget.dart';
import 'package:flutter_ecommerce/features/address/controllers/address_controller.dart';
import 'package:flutter_ecommerce/features/auth/controllers/auth_controller.dart';
import 'package:flutter_ecommerce/features/banner/controllers/banner_controller.dart';
import 'package:flutter_ecommerce/features/banner/widgets/banners_widget.dart';
import 'package:flutter_ecommerce/features/banner/widgets/footer_banner_slider_widget.dart';
import 'package:flutter_ecommerce/features/banner/widgets/single_banner_widget.dart';
import 'package:flutter_ecommerce/features/brand/controllers/brand_controller.dart';
import 'package:flutter_ecommerce/features/brand/screens/brands_screen.dart';
import 'package:flutter_ecommerce/features/brand/widgets/brand_list_widget.dart';
import 'package:flutter_ecommerce/features/cart/controllers/cart_controller.dart';
import 'package:flutter_ecommerce/features/category/controllers/category_controller.dart';
import 'package:flutter_ecommerce/features/category/widgets/category_list_widget.dart';
import 'package:flutter_ecommerce/features/clearance_sale/widgets/clearance_sale_list_widget.dart';
import 'package:flutter_ecommerce/features/deal/controllers/featured_deal_controller.dart';
import 'package:flutter_ecommerce/features/deal/controllers/flash_deal_controller.dart';
import 'package:flutter_ecommerce/features/deal/screens/featured_deal_screen_view.dart';
import 'package:flutter_ecommerce/features/deal/screens/flash_deal_screen_view.dart';
import 'package:flutter_ecommerce/features/deal/widgets/featured_deal_list_widget.dart';
import 'package:flutter_ecommerce/features/deal/widgets/flash_deals_list_widget.dart';
import 'package:flutter_ecommerce/features/home/shimmers/flash_deal_shimmer.dart';
import 'package:flutter_ecommerce/features/home/widgets/announcement_widget.dart';
import 'package:flutter_ecommerce/features/home/widgets/aster_theme/find_what_you_need_shimmer.dart';
import 'package:flutter_ecommerce/features/home/widgets/featured_product_widget.dart';
import 'package:flutter_ecommerce/features/home/widgets/search_home_page_widget.dart';
import 'package:flutter_ecommerce/features/notification/controllers/notification_controller.dart';
import 'package:flutter_ecommerce/features/product/controllers/product_controller.dart';
import 'package:flutter_ecommerce/features/product/enums/product_type.dart';
import 'package:flutter_ecommerce/features/product/widgets/home_category_product_widget.dart';
import 'package:flutter_ecommerce/features/product/widgets/latest_product_list_widget.dart';
import 'package:flutter_ecommerce/features/product/widgets/products_list_widget.dart';
import 'package:flutter_ecommerce/features/product/widgets/recommended_product_widget.dart';
import 'package:flutter_ecommerce/features/profile/controllers/profile_contrroller.dart';
import 'package:flutter_ecommerce/features/search_product/screens/search_product_screen.dart';
import 'package:flutter_ecommerce/features/shop/controllers/shop_controller.dart';
import 'package:flutter_ecommerce/features/shop/screens/all_shop_screen.dart';
import 'package:flutter_ecommerce/features/shop/widgets/top_seller_view.dart';
import 'package:flutter_ecommerce/features/splash/controllers/splash_controller.dart';
import 'package:flutter_ecommerce/features/splash/domain/models/config_model.dart';
import 'package:flutter_ecommerce/helper/responsive_helper.dart';
import 'package:flutter_ecommerce/localization/language_constrants.dart';
import 'package:flutter_ecommerce/main.dart';
import 'package:flutter_ecommerce/theme/controllers/theme_controller.dart';
import 'package:flutter_ecommerce/utill/custom_themes.dart';
import 'package:flutter_ecommerce/utill/dimensions.dart';
import 'package:flutter_ecommerce/utill/images.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher_string.dart';

class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();

  static Future<void> loadData(bool reload) async {
    final flashDealController =
        Provider.of<FlashDealController>(Get.context!, listen: false);
    final shopController =
        Provider.of<ShopController>(Get.context!, listen: false);
    final categoryController =
        Provider.of<CategoryController>(Get.context!, listen: false);
    final bannerController =
        Provider.of<BannerController>(Get.context!, listen: false);
    final addressController =
        Provider.of<AddressController>(Get.context!, listen: false);
    final productController =
        Provider.of<ProductController>(Get.context!, listen: false);
    final brandController =
        Provider.of<BrandController>(Get.context!, listen: false);
    final featuredDealController =
        Provider.of<FeaturedDealController>(Get.context!, listen: false);
    final notificationController =
        Provider.of<NotificationController>(Get.context!, listen: false);
    final cartController =
        Provider.of<CartController>(Get.context!, listen: false);
    final profileController =
        Provider.of<ProfileController>(Get.context!, listen: false);

    if (flashDealController.flashDealList.isEmpty || reload) {
      // await flashDealController.getFlashDealList(reload, false);
    }

    categoryController.getCategoryList(reload);

    bannerController.getBannerList(reload);

    shopController.getTopSellerList(reload, 1, type: "top");

    shopController.getAllSellerList(reload, 1, type: "all");

    if (addressController.addressList == null ||
        (addressController.addressList != null &&
            addressController.addressList!.isEmpty) ||
        reload) {
      addressController.getAddressList();
    }

    cartController.getCartData(Get.context!);

    productController.getHomeCategoryProductList(reload);

    brandController.getBrandList(reload);

    featuredDealController.getFeaturedDealList(reload);

    productController.getLProductList('1', reload: reload);

    productController.getLatestProductList(1, reload: reload);

    productController.getFeaturedProductList('1', reload: reload);

    productController.getRecommendedProduct();

    productController.getClearanceAllProductList('1');

    if (notificationController.notificationModel == null ||
        (notificationController.notificationModel != null &&
            notificationController.notificationModel!.notification!.isEmpty) ||
        reload) {
      notificationController.getNotificationList(1);
    }

    if (Provider.of<AuthController>(Get.context!, listen: false).isLoggedIn() &&
        profileController.userInfoModel == null) {
      profileController.getUserInfo(Get.context!);
    }
  }
}

class _HomePageState extends State<HomePage> {
  final ScrollController _scrollController = ScrollController();

  void passData(int index, String title) {
    index = index;
    title = title;
  }

  bool singleVendor = false;
  @override
  void initState() {
    super.initState();
    singleVendor = Provider.of<SplashController>(context, listen: false)
            .configModel!
            .businessMode ==
        "single";
  }

  @override
  Widget build(BuildContext context) {
    final ConfigModel? configModel =
        Provider.of<SplashController>(context, listen: false).configModel;

    List<String?> types = [
      getTranslated('new_arrival', context),
      getTranslated('top_product', context),
      getTranslated('best_selling', context),
      getTranslated('discounted_product', context)
    ];

    return Scaffold(
      backgroundColor: Theme.of(context).primaryColor,
      resizeToAvoidBottomInset: false,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () async {
            await HomePage.loadData(true);
          },
          child: Container(
            color: Colors.white,
            child: CustomScrollView(
              controller: _scrollController,
              slivers: [
                // SliverAppBar(
                //   floating: true,
                //   elevation: 0,
                //   centerTitle: false,
                //   automaticallyImplyLeading: false,
                //   backgroundColor: Theme.of(context).primaryColor,
                //   title: Image.asset(Images.logoImage, height: 55),
                // ),
                SliverToBoxAdapter(
                    child: Provider.of<SplashController>(context, listen: false)
                                .configModel!
                                .announcement!
                                .status ==
                            '1'
                        ? Consumer<SplashController>(
                            builder: (context, announcement, _) {
                            return (announcement.configModel!.announcement!
                                            .announcement !=
                                        null &&
                                    announcement.onOff)
                                ? AnnouncementWidget(
                                    announcement:
                                        announcement.configModel!.announcement)
                                : const SizedBox();
                          })
                        : const SizedBox()),
                SliverPersistentHeader(
                    pinned: true,
                    delegate: SliverDelegate(
                      child: Container(
                        color: Theme.of(context).primaryColor,
                        child: Row(
                          children: [
                            Expanded(
                              child: InkWell(
                                onTap: () => Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                        builder: (_) => const SearchScreen())),
                                child: const Hero(
                                    tag: 'search',
                                    child: Material(
                                        child: SearchHomePageWidget())),
                              ),
                            ),
                          ],
                        ),
                      ),
                    )),
                SliverToBoxAdapter(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const SizedBox(height: Dimensions.paddingSizeDefault),

                      const CategoryListWidget(isHomePage: true),
                      const SizedBox(height: Dimensions.paddingSizeDefault),
                      const BannersWidget(),
                      const SizedBox(height: Dimensions.paddingSizeDefault),
                      const Padding(
                          padding: EdgeInsets.only(
                              bottom: Dimensions.paddingSizeDefault),
                          child: LatestProductListWidget()),

                      Consumer<FlashDealController>(
                          builder: (context, megaDeal, child) {
                        return megaDeal.flashDeal == null
                            ? const FlashDealShimmer()
                            : megaDeal.flashDealList.isNotEmpty
                                ? Column(children: [
                                    Padding(
                                      padding: const EdgeInsets.symmetric(
                                          horizontal:
                                              Dimensions.paddingSizeDefault),
                                      child: TitleRowWidget(
                                        title:
                                            getTranslated('flash_deal', context)
                                                ?.toUpperCase(),
                                        eventDuration:
                                            megaDeal.flashDeal != null
                                                ? megaDeal.duration
                                                : null,
                                        onTap: () {
                                          Navigator.push(
                                              context,
                                              MaterialPageRoute(
                                                  builder: (_) =>
                                                      const FlashDealScreenView()));
                                        },
                                        isFlash: true,
                                      ),
                                    ),
                                    const SizedBox(
                                        height: Dimensions.paddingSizeSmall),
                                    Padding(
                                      padding: const EdgeInsets.symmetric(
                                          horizontal:
                                              Dimensions.paddingSizeDefault),
                                      child: Text(
                                        getTranslated(
                                                'hurry_up_the_offer_is_limited_grab_while_it_lasts',
                                                context) ??
                                            '',
                                        style: textRegular.copyWith(
                                            color: Provider.of<ThemeController>(
                                                        context,
                                                        listen: false)
                                                    .darkTheme
                                                ? Theme.of(context).hintColor
                                                : Theme.of(context)
                                                    .primaryColor,
                                            fontSize:
                                                Dimensions.fontSizeDefault),
                                        textAlign: TextAlign.center,
                                      ),
                                    ),
                                    const SizedBox(
                                        height: Dimensions.paddingSizeSmall),
                                    const FlashDealsListWidget()
                                  ])
                                : const SizedBox.shrink();
                      }),
                      const SizedBox(height: Dimensions.paddingSizeExtraSmall),

                      Consumer<FeaturedDealController>(
                          builder: (context, featuredDealProvider, child) {
                        return featuredDealProvider.featuredDealProductList !=
                                null
                            ? featuredDealProvider
                                    .featuredDealProductList!.isNotEmpty
                                ? Column(
                                    children: [
                                      Stack(children: [
                                        Container(
                                          width:
                                              MediaQuery.of(context).size.width,
                                          height: 150,
                                          color: Theme.of(context)
                                              .colorScheme
                                              .onTertiary,
                                        ),
                                        Column(children: [
                                          Padding(
                                            padding: const EdgeInsets.symmetric(
                                                vertical: Dimensions
                                                    .paddingSizeDefault),
                                            child: TitleRowWidget(
                                              title:
                                                  '${getTranslated('featured_deals', context)}',
                                              onTap: () => Navigator.push(
                                                  context,
                                                  MaterialPageRoute(
                                                      builder: (_) =>
                                                          const FeaturedDealScreenView())),
                                            ),
                                          ),
                                          const FeaturedDealsListWidget(),
                                        ]),
                                      ]),
                                      const SizedBox(
                                          height:
                                              Dimensions.paddingSizeDefault),
                                    ],
                                  )
                                : const SizedBox.shrink()
                            : const FindWhatYouNeedShimmer();
                      }),

                      const ClearanceListWidget(),
                      const SizedBox(height: Dimensions.paddingSizeDefault),

                      Consumer<BannerController>(
                          builder: (context, footerBannerProvider, child) {
                        return footerBannerProvider.footerBannerList != null &&
                                footerBannerProvider
                                    .footerBannerList!.isNotEmpty
                            ? Padding(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: Dimensions.paddingSizeDefault),
                                child: SingleBannersWidget(
                                    bannerModel: footerBannerProvider
                                        .footerBannerList?[0]))
                            : const SizedBox();
                      }),
                      // const SizedBox(height: Dimensions.paddingSizeDefault),

                      Consumer<ProductController>(
                          builder: (context, productController, _) {
                        return const FeaturedProductWidget();
                      }),
                      const SizedBox(height: Dimensions.paddingSizeDefault),

                      singleVendor
                          ? const SizedBox()
                          : Consumer<ShopController>(
                              builder: (context, topSellerProvider, child) {
                              return (topSellerProvider.sellerModel != null &&
                                      (topSellerProvider.sellerModel!.sellers !=
                                              null &&
                                          topSellerProvider.sellerModel!
                                              .sellers!.isNotEmpty))
                                  ? TitleRowWidget(
                                      title:
                                          getTranslated('top_seller', context),
                                      onTap: () => Navigator.push(
                                          context,
                                          MaterialPageRoute(
                                              builder: (_) =>
                                                  const AllTopSellerScreen(
                                                    title: 'top_stores',
                                                  ))))
                                  : const SizedBox();
                            }),
                      singleVendor
                          ? const SizedBox(height: 0)
                          : const SizedBox(height: Dimensions.paddingSizeSmall),

                      singleVendor
                          ? const SizedBox()
                          : Consumer<ShopController>(
                              builder: (context, topSellerProvider, child) {
                              return (topSellerProvider.sellerModel != null &&
                                      (topSellerProvider.sellerModel!.sellers !=
                                              null &&
                                          topSellerProvider.sellerModel!
                                              .sellers!.isNotEmpty))
                                  ? Padding(
                                      padding: const EdgeInsets.only(
                                          bottom:
                                              Dimensions.paddingSizeDefault),
                                      child: SizedBox(
                                          height:
                                              ResponsiveHelper.isTab(context)
                                                  ? 170
                                                  : 165,
                                          child: TopSellerView(
                                            isHomePage: true,
                                            scrollController: _scrollController,
                                          )))
                                  : const SizedBox();
                            }),

                      const Padding(
                          padding: EdgeInsets.only(
                              bottom: Dimensions.paddingSizeDefault),
                          child: RecommendedProductWidget()),

                      if (configModel?.brandSetting == "1")
                        TitleRowWidget(
                          title: getTranslated('brand', context),
                          onTap: () => Navigator.push(
                              context,
                              MaterialPageRoute(
                                  builder: (_) => const BrandsView())),
                        ),

                      SizedBox(
                          height: configModel?.brandSetting == "1"
                              ? Dimensions.paddingSizeSmall
                              : 0),

                      if (configModel!.brandSetting == "1") ...[
                        const BrandListWidget(isHomePage: true),
                        const SizedBox(height: Dimensions.paddingSizeDefault),
                      ],

                      //Branches listing
                      SizedBox(
                        height: 18,
                      ),
                      if (configModel.branches!.isNotEmpty)
                        Column(
                          children: [
                            Align(
                              alignment: Alignment.centerLeft,
                              child: Padding(
                                padding: EdgeInsets.symmetric(
                                    horizontal: 16.0, vertical: 18),
                                child: Text(
                                    getTranslated('Our Branches', context) ?? '',
                                    style: textRegular.copyWith(
                                      fontWeight: FontWeight.bold,
                                      fontSize: 16,
                                      color: Colors.black,
                                    )),
                              ),
                            ),
                            Container(
                              height: 110,
                              child: Scaffold(
                                body: Stack(
                                  clipBehavior: Clip.none,
                                  children: [
                                    Stack(
                                      children: [
                                        // GoogleMap(
                                        //   // markers: Set<Marker>.of([currentLocationMarker!]),
                                        //   onMapCreated:
                                        //       (GoogleMapController controller) {
                                        //     _controller = controller;
                                        //   },
                                        //   initialCameraPosition: CameraPosition(
                                        //     target: controller.coordinates.value[
                                        //         0], // Center the map on the first coordinate
                                        //     zoom: _currentZoom,
                                        //   ),

                                        //   zoomControlsEnabled: false,
                                        //   myLocationButtonEnabled: false,
                                        //   mapToolbarEnabled: false,
                                        //   scrollGesturesEnabled: true,
                                        //   // cameraTargetBounds: CameraTargetBounds(_bounds(_markers)),

                                        //   markers: controller.data!.branches!
                                        //       .map((branch) {
                                        //     return Marker(
                                        //       markerId: MarkerId(
                                        //           branch.latitude.toString()),
                                        //       position: LatLng(
                                        //           double.parse(
                                        //             branch.latitude.toString(),
                                        //           ),
                                        //           double.parse(branch.longitude
                                        //               .toString())),
                                        //       infoWindow: InfoWindow(
                                        //         title: 'storeLocation'.tr,
                                        //         snippet:
                                        //             '${isEnglish.value ? branch.nameEn : branch.nameAr}',
                                        //       ),
                                        //     );
                                        //   }).toSet(),
                                        // ),
                                        // Positioned(
                                        //   top: 20,
                                        //   right: 12,
                                        //   child: Column(
                                        //     children: [
                                        //       FloatingActionButton(
                                        //         onPressed: _zoomIn,
                                        //         mini: true,
                                        //         backgroundColor: themeColor,
                                        //         child: Icon(Icons.add,
                                        //             color: Colors.white),
                                        //       ),
                                        //       // SizedBox(height: 2),
                                        //       FloatingActionButton(
                                        //         onPressed: _zoomOut,
                                        //         mini: true,
                                        //         backgroundColor: themeColor,
                                        //         child: Icon(Icons.remove,
                                        //             color: Colors.white),
                                        //       ),
                                        //     ],
                                        //   ),
                                        // ),
                                      ],
                                    ),
                                    Positioned(
                                      bottom: -10,
                                      left: 0,
                                      right: 0,
                                      child: SingleChildScrollView(
                                        scrollDirection: Axis.horizontal,
                                        padding: EdgeInsets.symmetric(
                                            horizontal: 8, vertical: 16),
                                        child: Row(
                                          children: [
                                            for (int i = 0;
                                                i <
                                                    configModel
                                                        .branches!.length;
                                                i++)
                                              InkWell(
                                                onTap: () async {
                                                  if (await canLaunchUrlString(
                                                      configModel.branches![i]
                                                          .mapUrl!)) {
                                                    await launchUrlString(
                                                        configModel.branches![i]
                                                            .mapUrl!,
                                                        mode: LaunchMode
                                                            .externalApplication);
                                                  }
                                              
                                                },
                                                child: Container(
                                                  width: 279,
                                                  padding: EdgeInsets.all(8),
                                                  margin: EdgeInsets.only(
                                                      right: 16),
                                                  decoration: BoxDecoration(
                                                      color: Colors.white,
                                                      borderRadius:
                                                          BorderRadius.circular(
                                                              8),
                                                      boxShadow: [
                                                        BoxShadow(
                                                            color: Color(
                                                                    0xFF000000)
                                                                .withOpacity(
                                                                    0.04),
                                                            blurRadius: 16,
                                                            spreadRadius: 0,
                                                            offset:
                                                                Offset(0, 8))
                                                      ]),
                                                  child: Row(
                                                      crossAxisAlignment:
                                                          CrossAxisAlignment
                                                              .start,
                                                      children: [
                                                        Container(
                                                            width: 72,
                                                            height: 72,
                                                            child: ClipRRect(
                                                              borderRadius:
                                                                  BorderRadius
                                                                      .circular(
                                                                          5),
                                                              child:
                                                                  CachedNetworkImage(
                                                                imageUrl: configModel
                                                                        .branches![
                                                                            i]
                                                                        .iconFullUrl!
                                                                        .path ??
                                                                    '',
                                                                errorWidget: (context,
                                                                        url,
                                                                        error) =>
                                                                    Image.asset(
                                                                        Images
                                                                            .logoWithNameImage,
                                                                        height:
                                                                            35),
                                                                fit:
                                                                    BoxFit.fill,
                                                              ),
                                                            )),
                                                        Expanded(
                                                          child: Padding(
                                                            padding: EdgeInsets
                                                                .symmetric(
                                                                    horizontal:
                                                                        12,
                                                                    vertical:
                                                                        4),
                                                            child: Column(
                                                              crossAxisAlignment:
                                                                  CrossAxisAlignment
                                                                      .stretch,
                                                              children: [
                                                                Text(
                                                                  configModel
                                                                          .branches![
                                                                              i]
                                                                          .name_en ??
                                                                      '',
                                                                  style: TextStyle(
                                                                      color: Theme.of(
                                                                              context)
                                                                          .primaryColor,
                                                                      fontWeight:
                                                                          FontWeight
                                                                              .bold,
                                                                      fontSize:
                                                                          12),
                                                                ),
                                                                SizedBox(
                                                                  height: 2,
                                                                ),
                                                                Text(
                                                                  configModel
                                                                          .branches![
                                                                              i]
                                                                          .description ??
                                                                      'Description',
                                                                  maxLines: 2,
                                                                  overflow:
                                                                      TextOverflow
                                                                          .ellipsis,
                                                                  softWrap:
                                                                      true,
                                                                  style: TextStyle(
                                                                      color: Colors
                                                                          .grey,
                                                                      fontWeight:
                                                                          FontWeight
                                                                              .w500,
                                                                      fontSize:
                                                                          10),
                                                                ),
                                                                Padding(
                                                                  padding: EdgeInsets
                                                                      .symmetric(
                                                                          vertical:
                                                                              4),
                                                                ),
                                                                Row(
                                                                  mainAxisSize:
                                                                      MainAxisSize
                                                                          .min,
                                                                  children: [
                                                                    Icon(
                                                                      Icons
                                                                          .directions_rounded,
                                                                      size: 16,
                                                                      color: Colors
                                                                          .black,
                                                                    ),
                                                                    Padding(
                                                                      padding: EdgeInsets.symmetric(
                                                                          horizontal:
                                                                              4),
                                                                    ),
                                                                    Text(
                                                                      "direction",
                                                                      style:
                                                                          TextStyle(
                                                                        color: Theme.of(context)
                                                                            .primaryColor,
                                                                        fontSize:
                                                                            12,
                                                                      ),
                                                                    ),
                                                                  ],
                                                                )
                                                              ],
                                                            ),
                                                          ),
                                                        ),
                                                      ]),
                                                ),
                                              )
                                          ],
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ),
                      // Branches listing ends here

                      const HomeCategoryProductWidget(isHomePage: true),
                      const SizedBox(height: Dimensions.paddingSizeDefault),

                      const FooterBannerSliderWidget(),
                      const SizedBox(height: Dimensions.paddingSizeDefault),

                      Consumer<ProductController>(
                          builder: (ctx, prodProvider, child) {
                        return Container(
                            decoration: BoxDecoration(
                                color: Theme.of(context)
                                    .colorScheme
                                    .onSecondaryContainer),
                            child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Padding(
                                      padding: const EdgeInsets.fromLTRB(
                                          Dimensions.paddingSizeDefault,
                                          0,
                                          Dimensions.paddingSizeSmall,
                                          0),
                                      child: Row(children: [
                                        Expanded(
                                            child: Text(
                                                prodProvider.title == 'xyz'
                                                    ? getTranslated(
                                                        'new_arrival', context)!
                                                    : prodProvider.title!,
                                                style: titleHeader)),
                                        prodProvider.latestProductList != null
                                            ? PopupMenuButton(
                                                padding:
                                                    const EdgeInsets.all(0),
                                                itemBuilder: (context) {
                                                  return [
                                                    PopupMenuItem(
                                                      value: ProductType
                                                          .newArrival,
                                                      child: Text(
                                                          getTranslated(
                                                                  'new_arrival',
                                                                  context) ??
                                                              '',
                                                          style: textRegular
                                                              .copyWith(
                                                            color: prodProvider
                                                                        .productType ==
                                                                    ProductType
                                                                        .newArrival
                                                                ? Theme.of(
                                                                        context)
                                                                    .primaryColor
                                                                : Theme.of(
                                                                        context)
                                                                    .textTheme
                                                                    .bodyLarge
                                                                    ?.color,
                                                          )),
                                                    ),
                                                    PopupMenuItem(
                                                      value: ProductType
                                                          .topProduct,
                                                      child: Text(
                                                          getTranslated(
                                                                  'top_product',
                                                                  context) ??
                                                              '',
                                                          style: textRegular
                                                              .copyWith(
                                                            color: prodProvider
                                                                        .productType ==
                                                                    ProductType
                                                                        .topProduct
                                                                ? Theme.of(
                                                                        context)
                                                                    .primaryColor
                                                                : Theme.of(
                                                                        context)
                                                                    .textTheme
                                                                    .bodyLarge
                                                                    ?.color,
                                                          )),
                                                    ),
                                                    PopupMenuItem(
                                                      value: ProductType
                                                          .bestSelling,
                                                      child: Text(
                                                          getTranslated(
                                                                  'best_selling',
                                                                  context) ??
                                                              '',
                                                          style: textRegular
                                                              .copyWith(
                                                            color: prodProvider
                                                                        .productType ==
                                                                    ProductType
                                                                        .bestSelling
                                                                ? Theme.of(
                                                                        context)
                                                                    .primaryColor
                                                                : Theme.of(
                                                                        context)
                                                                    .textTheme
                                                                    .bodyLarge
                                                                    ?.color,
                                                          )),
                                                    ),
                                                    PopupMenuItem(
                                                      value: ProductType
                                                          .discountedProduct,
                                                      child: Text(
                                                          getTranslated(
                                                                  'discounted_product',
                                                                  context) ??
                                                              '',
                                                          style: textRegular
                                                              .copyWith(
                                                            color: prodProvider
                                                                        .productType ==
                                                                    ProductType
                                                                        .discountedProduct
                                                                ? Theme.of(
                                                                        context)
                                                                    .primaryColor
                                                                : Theme.of(
                                                                        context)
                                                                    .textTheme
                                                                    .bodyLarge
                                                                    ?.color,
                                                          )),
                                                    ),
                                                  ];
                                                },
                                                shape: RoundedRectangleBorder(
                                                    borderRadius: BorderRadius
                                                        .circular(Dimensions
                                                            .paddingSizeSmall)),
                                                child: Padding(
                                                    padding: const EdgeInsets
                                                        .fromLTRB(
                                                        Dimensions
                                                            .paddingSizeExtraSmall,
                                                        Dimensions
                                                            .paddingSizeSmall,
                                                        Dimensions
                                                            .paddingSizeExtraSmall,
                                                        Dimensions
                                                            .paddingSizeSmall),
                                                    child: Image.asset(
                                                        Images.dropdown,
                                                        scale: 3)),
                                                onSelected:
                                                    (ProductType value) {
                                                  if (value ==
                                                      ProductType.newArrival) {
                                                    Provider.of<ProductController>(
                                                            context,
                                                            listen: false)
                                                        .changeTypeOfProduct(
                                                            value, types[0]);
                                                  } else if (value ==
                                                      ProductType.topProduct) {
                                                    Provider.of<ProductController>(
                                                            context,
                                                            listen: false)
                                                        .changeTypeOfProduct(
                                                            value, types[1]);
                                                  } else if (value ==
                                                      ProductType.bestSelling) {
                                                    Provider.of<ProductController>(
                                                            context,
                                                            listen: false)
                                                        .changeTypeOfProduct(
                                                            value, types[2]);
                                                  } else if (value ==
                                                      ProductType
                                                          .discountedProduct) {
                                                    Provider.of<ProductController>(
                                                            context,
                                                            listen: false)
                                                        .changeTypeOfProduct(
                                                            value, types[3]);
                                                  }
                                                  //  Provider.of<ProductController>(context, listen: false).getLatestProductList(1, reload: true);
                                                },
                                              )
                                            : const SizedBox()
                                      ])),
                                  Padding(
                                      padding: const EdgeInsets.symmetric(
                                          horizontal:
                                              Dimensions.paddingSizeSmall),
                                      child: ProductListWidget(
                                          isHomePage: false,
                                          productType: ProductType.newArrival,
                                          scrollController: _scrollController)),
                                  const SizedBox(
                                      height: Dimensions.homePagePadding)
                                ]));
                      }),

                      // Branches slider

                      SizedBox(
                        height: 60,
                      ),
                      //Branches slider end
                    ],
                  ),
                )
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class SliverDelegate extends SliverPersistentHeaderDelegate {
  Widget child;
  double height;
  SliverDelegate({required this.child, this.height = 70});

  @override
  Widget build(
      BuildContext context, double shrinkOffset, bool overlapsContent) {
    return child;
  }

  @override
  double get maxExtent => height;

  @override
  double get minExtent => height;

  @override
  bool shouldRebuild(SliverDelegate oldDelegate) {
    return oldDelegate.maxExtent != height ||
        oldDelegate.minExtent != height ||
        child != oldDelegate.child;
  }
}
