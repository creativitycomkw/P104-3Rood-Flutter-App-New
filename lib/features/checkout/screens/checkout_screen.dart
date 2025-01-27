import 'package:flutter/material.dart';
import 'package:flutter_ecommerce/features/address/controllers/address_controller.dart';
import 'package:flutter_ecommerce/features/address/screens/saved_address_list_screen.dart';
import 'package:flutter_ecommerce/features/address/screens/saved_billing_address_list_screen.dart';
import 'package:flutter_ecommerce/features/cart/domain/models/cart_model.dart';
import 'package:flutter_ecommerce/features/checkout/controllers/checkout_controller.dart';
import 'package:flutter_ecommerce/features/checkout/widgets/payment_method_bottom_sheet_widget.dart';
import 'package:flutter_ecommerce/features/offline_payment/screens/offline_payment_screen.dart';
import 'package:flutter_ecommerce/features/profile/controllers/profile_contrroller.dart';
import 'package:flutter_ecommerce/features/shipping/controllers/shipping_controller.dart';
import 'package:flutter_ecommerce/features/shipping/widgets/shipping_method_bottom_sheet_widget.dart';
import 'package:flutter_ecommerce/helper/price_converter.dart';
import 'package:flutter_ecommerce/localization/language_constrants.dart';
import 'package:flutter_ecommerce/main.dart';
import 'package:flutter_ecommerce/features/auth/controllers/auth_controller.dart';
import 'package:flutter_ecommerce/features/cart/controllers/cart_controller.dart';
import 'package:flutter_ecommerce/features/coupon/controllers/coupon_controller.dart';
import 'package:flutter_ecommerce/features/splash/controllers/splash_controller.dart';
import 'package:flutter_ecommerce/utill/custom_themes.dart';
import 'package:flutter_ecommerce/utill/dimensions.dart';
import 'package:flutter_ecommerce/common/basewidget/amount_widget.dart';
import 'package:flutter_ecommerce/common/basewidget/animated_custom_dialog_widget.dart';
import 'package:flutter_ecommerce/common/basewidget/custom_app_bar_widget.dart';
import 'package:flutter_ecommerce/common/basewidget/custom_button_widget.dart';
import 'package:flutter_ecommerce/features/checkout/widgets/order_place_dialog_widget.dart';
import 'package:flutter_ecommerce/common/basewidget/show_custom_snakbar_widget.dart';
import 'package:flutter_ecommerce/common/basewidget/custom_textfield_widget.dart';
import 'package:flutter_ecommerce/features/checkout/widgets/choose_payment_widget.dart';
import 'package:flutter_ecommerce/features/checkout/widgets/coupon_apply_widget.dart';
import 'package:flutter_ecommerce/features/checkout/widgets/shipping_details_widget.dart';
import 'package:flutter_ecommerce/features/checkout/widgets/wallet_payment_widget.dart';
import 'package:flutter_ecommerce/features/dashboard/screens/dashboard_screen.dart';
import 'package:flutter_ecommerce/utill/images.dart';
import 'package:provider/provider.dart';

class CheckoutScreen extends StatefulWidget {
  final List<CartModel> cartList;
  final bool fromProductDetails;
  final double totalOrderAmount;
  final double shippingFee;
  final double discount;
  final double tax;
  final int? sellerId;
  final bool onlyDigital;
  final bool hasPhysical;
  final int quantity;

  const CheckoutScreen(
      {super.key,
      required this.cartList,
      this.fromProductDetails = false,
      required this.discount,
      required this.tax,
      required this.totalOrderAmount,
      required this.shippingFee,
      this.sellerId,
      this.onlyDigital = false,
      required this.quantity,
      required this.hasPhysical});

  @override
  CheckoutScreenState createState() => CheckoutScreenState();
}

class CheckoutScreenState extends State<CheckoutScreen> {
  final GlobalKey<ScaffoldMessengerState> _scaffoldKey =
      GlobalKey<ScaffoldMessengerState>();
  final TextEditingController _controller = TextEditingController();
  final GlobalKey<FormState> passwordFormKey = GlobalKey<FormState>();

  final FocusNode _orderNoteNode = FocusNode();
  double _order = 0;
  late bool _billingAddress;
  double? _couponDiscount;

  double _shippingFee = 0;

  @override
  void initState() {
    super.initState();
    Provider.of<AddressController>(context, listen: false).getAddressList();
    Provider.of<CouponController>(context, listen: false)
        .removePrevCouponData();
    Provider.of<CartController>(context, listen: false).getCartData(context);
    Provider.of<CheckoutController>(context, listen: false)
        .resetPaymentMethod();
    // Provider.of<ShippingController>(context, listen: false)
    //     .getChosenShippingMethod(context);
    if (Provider.of<SplashController>(context, listen: false).configModel !=
            null &&
        Provider.of<SplashController>(context, listen: false)
                .configModel!
                .offlinePayment !=
            null) {
      Provider.of<CheckoutController>(context, listen: false)
          .getOfflinePaymentList();
    }

    if (Provider.of<AuthController>(context, listen: false).isLoggedIn()) {
      Provider.of<CouponController>(context, listen: false)
          .getAvailableCouponList();
    }

    _billingAddress = Provider.of<SplashController>(Get.context!, listen: false)
            .configModel!
            .billingInputByCustomer ==
        1;
    Provider.of<CheckoutController>(context, listen: false).clearData();

    //  if (Provider.of<SplashController>(Get.context!, listen: false)
    //         .configModel!
    //         .shippingMethod !=
    //     'sellerwise_shipping') {
    //   Provider.of<ShippingController>(Get.context!, listen: false)
    //       .getAdminShippingMethodList(Get.context!);
    // }

    //Clear selected shipping method index
  }

  @override
  Widget build(BuildContext context) {
    _order = widget.totalOrderAmount + widget.discount;

    return Scaffold(
      resizeToAvoidBottomInset: true,
      key: _scaffoldKey,
      bottomNavigationBar:
          Consumer<AddressController>(builder: (context, locationProvider, _) {
        return Consumer<CheckoutController>(
            builder: (context, orderProvider, child) {
          return Consumer<CouponController>(
              builder: (context, couponProvider, _) {
            return Consumer<CartController>(
                builder: (context, cartProvider, _) {
              return Consumer<ProfileController>(
                  builder: (context, profileProvider, _) {
                return orderProvider.isLoading
                    ? const Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                            SizedBox(
                                width: 30,
                                height: 30,
                                child: CircularProgressIndicator())
                          ])
                    : Padding(
                        padding:
                            const EdgeInsets.all(Dimensions.paddingSizeDefault),
                        child: CustomButton(
                          onTap: () async {
                            if (orderProvider.addressIndex == null &&
                                widget.hasPhysical) {
                              Navigator.of(context).push(MaterialPageRoute(
                                  builder: (BuildContext context) =>
                                      const SavedAddressListScreen()));
                              showCustomSnackBar(
                                  getTranslated(
                                      'select_a_shipping_address', context),
                                  context,
                                  isToaster: true);
                            } else if ((orderProvider.billingAddressIndex ==
                                    null &&
                                !widget.hasPhysical &&
                                !_billingAddress)) {
                              showCustomSnackBar(
                                  getTranslated(
                                      'you_cant_place_order_of_digital_product_without_billing_address',
                                      context),
                                  context,
                                  isToaster: true);
                            } else if ((orderProvider.billingAddressIndex ==
                                        null &&
                                    !widget.hasPhysical &&
                                    !orderProvider.sameAsBilling &&
                                    _billingAddress) ||
                                (orderProvider.billingAddressIndex == null &&
                                    _billingAddress &&
                                    !orderProvider.sameAsBilling)) {
                              Navigator.of(context).push(MaterialPageRoute(
                                  builder: (BuildContext context) =>
                                      const SavedBillingAddressListScreen()));
                              showCustomSnackBar(
                                  getTranslated(
                                      'select_a_billing_address', context),
                                  context,
                                  isToaster: true);
                            }

                            // else if (orderProvider.isCheckCreateAccount && orderProvider.passwordController.text.isEmpty) {
                            //   showCustomSnackBar(getTranslated('password_is_required', context), context);
                            // } else if (orderProvider.isCheckCreateAccount && orderProvider.passwordController.text.length < 8){
                            //   showCustomSnackBar(getTranslated('minimum_password_is_8_character', context), context);
                            // } else if (orderProvider.isCheckCreateAccount && orderProvider.confirmPasswordController.text.isEmpty){
                            //   showCustomSnackBar(getTranslated('confirm_password_must_be_required', context), context);
                            // }else if (orderProvider.isCheckCreateAccount && (orderProvider.passwordController.text != orderProvider.confirmPasswordController.text)) {
                            //   showCustomSnackBar(getTranslated('confirm_password_not_matched', context), context);
                            // }

                            else {
                              if (!orderProvider.isCheckCreateAccount ||
                                  (orderProvider.isCheckCreateAccount &&
                                      (passwordFormKey.currentState
                                              ?.validate() ??
                                          false))) {
                                String orderNote = orderProvider
                                    .orderNoteController.text
                                    .trim();
                                String couponCode =
                                    couponProvider.discount != null &&
                                            couponProvider.discount != 0
                                        ? couponProvider.couponCode
                                        : '';
                                String couponCodeAmount =
                                    couponProvider.discount != null &&
                                            couponProvider.discount != 0
                                        ? couponProvider.discount.toString()
                                        : '0';

                                // String addressId = !widget.onlyDigital? locationProvider.addressList![orderProvider.addressIndex!].id.toString():'';
                                // String billingAddressId = (_billingAddress)? orderProvider.sameAsBilling? addressId:
                                // locationProvider.addressList![orderProvider.billingAddressIndex!].id.toString() : '';

                                String addressId =
                                    orderProvider.addressIndex != null
                                        ? locationProvider
                                            .addressList![
                                                orderProvider.addressIndex!]
                                            .id
                                            .toString()
                                        : '';

                                String billingAddressId = (_billingAddress)
                                    ? !orderProvider.sameAsBilling
                                        ? locationProvider
                                            .addressList![orderProvider
                                                .billingAddressIndex!]
                                            .id
                                            .toString()
                                        : locationProvider
                                            .addressList![
                                                orderProvider.addressIndex!]
                                            .id
                                            .toString()
                                    : '';

                                if (orderProvider.paymentMethodIndex != -1) {
                                  orderProvider.digitalPaymentPlaceOrder(
                                      orderNote: orderNote,
                                      customerId: Provider.of<AuthController>(
                                                  context,
                                                  listen: false)
                                              .isLoggedIn()
                                          ? profileProvider.userInfoModel?.id
                                              .toString()
                                          : Provider.of<AuthController>(context,
                                                  listen: false)
                                              .getGuestToken(),
                                      addressId: addressId,
                                      billingAddressId: billingAddressId,
                                      couponCode: couponCode,
                                      couponDiscount: couponCodeAmount,
                                      paymentMethod: orderProvider
                                          .selectedDigitalPaymentMethodName);
                                } else if (orderProvider.codChecked &&
                                    !widget.onlyDigital) {
                                  orderProvider.placeOrder(
                                      callback: _callback,
                                      addressID: addressId,
                                      couponCode: couponCode,
                                      couponAmount: couponCodeAmount,
                                      billingAddressId: billingAddressId,
                                      orderNote: orderNote);
                                } else if (orderProvider.offlineChecked) {
                                  Navigator.of(context).push(MaterialPageRoute(
                                      builder: (_) => OfflinePaymentScreen(
                                          payableAmount: _order,
                                          callback: _callback)));
                                } else if (orderProvider.walletChecked) {
                                  showAnimatedDialog(
                                      context,
                                      WalletPaymentWidget(
                                          currentBalance:
                                              profileProvider.balance ?? 0,
                                          orderAmount: _order +
                                              _shippingFee -
                                              widget.discount -
                                              _couponDiscount! +
                                              widget.tax,
                                          onTap: () {
                                            if (profileProvider.balance! <
                                                (_order +
                                                    _shippingFee -
                                                    widget.discount -
                                                    _couponDiscount! +
                                                    widget.tax)) {
                                              showCustomSnackBar(
                                                  getTranslated(
                                                      'insufficient_balance',
                                                      context),
                                                  context,
                                                  isToaster: true);
                                            } else {
                                              Navigator.pop(context);
                                              orderProvider.placeOrder(
                                                  callback: _callback,
                                                  wallet: true,
                                                  addressID: addressId,
                                                  couponCode: couponCode,
                                                  couponAmount:
                                                      couponCodeAmount,
                                                  billingAddressId:
                                                      billingAddressId,
                                                  orderNote: orderNote);
                                            }
                                          }),
                                      dismissible: false,
                                      willFlip: true);
                                } else {
                                  showModalBottomSheet(
                                    context: context,
                                    isScrollControlled: true,
                                    backgroundColor: Colors.transparent,
                                    builder: (c) =>
                                        PaymentMethodBottomSheetWidget(
                                            onlyDigital: widget.onlyDigital),
                                  );
                                }
                              }
                            }
                          },
                          buttonText: '${getTranslated('proceed', context)}',
                        ),
                      );
              });
            });
          });
        });
      }),
      appBar: CustomAppBar(title: getTranslated('checkout', context)),
      body: Consumer<SplashController>(builder: (context, configProvider, _) {
        return Consumer<ShippingController>(
            builder: (context, shippingController, _) {
          _shippingFee = shippingController.chosenShippingList.isNotEmpty
              ? shippingController.chosenShippingList[0].shippingCost ?? 0
              : 0;
          return Consumer<CartController>(builder: (context, cart, child) {
            return Consumer<AuthController>(
                builder: (context, authProvider, _) {
              return Consumer<CheckoutController>(
                  builder: (context, orderProvider, _) {
                bool onlyDigital = true;
                List<CartModel> cartList = [];
                cartList.addAll(cart.cartList);

                for (CartModel cart in cartList) {
                  if (cart.productType == "physical" && cart.isChecked!) {
                    onlyDigital = false;
                  }
                }
                return Column(
                  children: [
                    Expanded(
                      child: ListView(
                          physics: const BouncingScrollPhysics(),
                          padding: const EdgeInsets.all(0),
                          children: [
                            Padding(
                                padding: const EdgeInsets.only(
                                    bottom: Dimensions.paddingSizeDefault),
                                child: ShippingDetailsWidget(
                                    hasPhysical: widget.hasPhysical,
                                    billingAddress: _billingAddress,
                                    passwordFormKey: passwordFormKey)),

                            //Shipping method widget

                            // (!onlyDigital &&
                            //         configProvider
                            //                 .configModel!.shippingMethod !=
                            //             'sellerwise_shipping' &&
                            //         configProvider.configModel!
                            //                 .inhouseSelectedShippingType ==
                            //             'order_wise')
                            //     ? InkWell(
                            //         onTap: () {
                            //           showModalBottomSheet(
                            //               context: context,
                            //               isScrollControlled: true,
                            //               backgroundColor: Colors.transparent,
                            //               builder: (context) =>
                            //                   const ShippingMethodBottomSheetWidget(
                            //                       groupId: 'all_cart_group',
                            //                       sellerIndex: 0,
                            //                       sellerId: 1));
                            //         },
                            //         child: Padding(
                            //           padding: const EdgeInsets.fromLTRB(
                            //               Dimensions.paddingSizeDefault,
                            //               Dimensions.paddingSizeSmall,
                            //               Dimensions.paddingSizeDefault,
                            //               16),
                            //           child: Container(
                            //             decoration: BoxDecoration(
                            //                 border: Border.all(
                            //                     width: 0.5, color: Colors.grey),
                            //                 borderRadius:
                            //                     const BorderRadius.all(
                            //                         Radius.circular(10))),
                            //             child: Padding(
                            //               padding: const EdgeInsets.all(8.0),
                            //               child: Row(
                            //                   mainAxisAlignment:
                            //                       MainAxisAlignment
                            //                           .spaceBetween,
                            //                   children: [
                            //                     Row(children: [
                            //                       SizedBox(
                            //                           width: 15,
                            //                           height: 15,
                            //                           child: Image.asset(
                            //                               Images.delivery,
                            //                               color:
                            //                                   Theme.of(context)
                            //                                       .textTheme
                            //                                       .bodyLarge
                            //                                       ?.color)),
                            //                       const SizedBox(
                            //                           width: Dimensions
                            //                               .paddingSizeExtraSmall),
                            //                       Text(
                            //                         getTranslated(
                            //                             'choose_shipping',
                            //                             context)!,
                            //                         style: textRegular,
                            //                         overflow:
                            //                             TextOverflow.ellipsis,
                            //                         maxLines: 1,
                            //                       ),
                            //                     ]),
                            //                     Row(
                            //                         mainAxisAlignment:
                            //                             MainAxisAlignment.end,
                            //                         children: [
                            //                           Text(
                            //                             (shippingController
                            //                                             .shippingList ==
                            //                                         null ||
                            //                                     shippingController
                            //                                         .chosenShippingList
                            //                                         .isEmpty ||
                            //                                     shippingController
                            //                                         .shippingList!
                            //                                         .isEmpty ||
                            //                                     shippingController
                            //                                             .shippingList![
                            //                                                 0]
                            //                                             .shippingMethodList ==
                            //                                         null ||
                            //                                     shippingController
                            //                                             .shippingList![
                            //                                                 0]
                            //                                             .shippingIndex ==
                            //                                         -1)
                            //                                 ? ''
                            //                                 : shippingController
                            //                                     .shippingList![
                            //                                         0]
                            //                                     .shippingMethodList![
                            //                                         shippingController
                            //                                             .shippingList![
                            //                                                 0]
                            //                                             .shippingIndex!]
                            //                                     .title
                            //                                     .toString(),
                            //                             style: titilliumSemiBold
                            //                                 .copyWith(
                            //                                     color: Theme.of(
                            //                                             context)
                            //                                         .hintColor),
                            //                             maxLines: 1,
                            //                             overflow: TextOverflow
                            //                                 .ellipsis,
                            //                           ),
                            //                           const SizedBox(
                            //                               width: Dimensions
                            //                                   .paddingSizeExtraSmall),
                            //                           Icon(
                            //                               Icons
                            //                                   .keyboard_arrow_down,
                            //                               color: Theme.of(
                            //                                       context)
                            //                                   .primaryColor),
                            //                         ]),
                            //                   ]),
                            //             ),
                            //           ),
                            //         ),
                            //       )
                            //     : const SizedBox(),

                            if (orderProvider.addressIndex != null)
                              ShippingMethodBottomSheetWidget(
                                  groupId: 'all_cart_group',
                                  sellerIndex: 0,
                                  sellerId: 1),

                            //Shipping method widget ends here

                            //branch selection

                            if (shippingController
                                    .chosenShippingList.isNotEmpty &&
                                shippingController.chosenShippingList[0]
                                        .shippingMethodId ==
                                    3)
                              Column(
                                children: [
                                  Align(
                                    alignment: Alignment.centerLeft,
                                    child: Padding(
                                      padding: const EdgeInsets.symmetric(
                                          horizontal: 16.0),
                                      child: Text(
                                        "Select branch",
                                        // getTranslated('select_shipping_method', context)!,
                                        style: textMedium.copyWith(
                                            fontSize: Dimensions.fontSizeLarge),
                                      ),
                                    ),
                                  ),
                                ],
                              ),

                            //branch selection ends here

                            if (Provider.of<AuthController>(context,
                                    listen: false)
                                .isLoggedIn())
                              Padding(
                                  padding: const EdgeInsets.only(
                                      bottom: Dimensions.paddingSizeSmall),
                                  child: CouponApplyWidget(
                                      couponController: _controller,
                                      orderAmount: _order)),

                            Padding(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: Dimensions.paddingSizeSmall),
                                child: ChoosePaymentWidget(
                                    onlyDigital: widget.onlyDigital)),

                            Padding(
                                padding: const EdgeInsets.fromLTRB(
                                    Dimensions.paddingSizeDefault,
                                    Dimensions.paddingSizeDefault,
                                    Dimensions.paddingSizeDefault,
                                    Dimensions.paddingSizeSmall),
                                child: Text(
                                    getTranslated('order_summary', context) ??
                                        '',
                                    style: textMedium.copyWith(
                                        fontSize: Dimensions.fontSizeLarge))),

                            Padding(
                                padding: const EdgeInsets.symmetric(
                                    horizontal: Dimensions.paddingSizeDefault),
                                child: Consumer<CheckoutController>(builder:
                                    (context, checkoutController, child) {
                                  _couponDiscount =
                                      Provider.of<CouponController>(context)
                                              .discount ??
                                          0;

                                  return Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        widget.quantity > 1
                                            ? AmountWidget(
                                                title:
                                                    '${getTranslated('sub_total', context)} ${' (${widget.quantity} ${getTranslated('items', context)}) '}',
                                                amount:
                                                    PriceConverter.convertPrice(
                                                        context, _order))
                                            : AmountWidget(
                                                title:
                                                    '${getTranslated('sub_total', context)} ${'(${widget.quantity} ${getTranslated('item', context)})'}',
                                                amount:
                                                    PriceConverter.convertPrice(
                                                        context, _order)),
                                        AmountWidget(
                                            title: getTranslated(
                                                'shipping_fee', context),
                                            amount: PriceConverter.convertPrice(
                                                context, _shippingFee)),
                                        AmountWidget(
                                            title: getTranslated(
                                                'discount', context),
                                            amount: PriceConverter.convertPrice(
                                                context, widget.discount)),
                                        AmountWidget(
                                            title: getTranslated(
                                                'coupon_voucher', context),
                                            amount: PriceConverter.convertPrice(
                                                context, _couponDiscount)),
                                        // AmountWidget(
                                        //     title:
                                        //         getTranslated('tax', context),
                                        //     amount: PriceConverter.convertPrice(
                                        //         context, widget.tax)),
                                        Divider(
                                            height: 5,
                                            color: Theme.of(context).hintColor),
                                        AmountWidget(
                                            title: getTranslated(
                                                'total_payable', context),
                                            amount: PriceConverter.convertPrice(
                                                context,
                                                (_order +
                                                    _shippingFee -
                                                    widget.discount -
                                                    _couponDiscount! +
                                                    widget.tax))),
                                      ]);
                                })),

                            Padding(
                                padding: const EdgeInsets.fromLTRB(
                                    Dimensions.paddingSizeDefault,
                                    Dimensions.paddingSizeDefault,
                                    Dimensions.paddingSizeDefault,
                                    0),
                                child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Row(children: [
                                        Text(
                                            '${getTranslated('order_note', context)}',
                                            style: textRegular.copyWith(
                                                fontSize:
                                                    Dimensions.fontSizeLarge))
                                      ]),
                                      const SizedBox(
                                          height: Dimensions.paddingSizeSmall),
                                      CustomTextFieldWidget(
                                          hintText: getTranslated(
                                              'enter_note', context),
                                          inputType: TextInputType.multiline,
                                          inputAction: TextInputAction.done,
                                          maxLines: 3,
                                          focusNode: _orderNoteNode,
                                          controller:
                                              orderProvider.orderNoteController)
                                    ])),
                          ]),
                    ),
                  ],
                );
              });
            });
          });
        });
      }),
    );
  }

  void _callback(bool isSuccess, String message, String orderID,
      bool createAccount) async {
    if (isSuccess) {
      Navigator.of(Get.context!).pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const DashBoardScreen()),
          (route) => false);
      showAnimatedDialog(
          context,
          OrderPlaceDialogWidget(
            icon: Icons.check,
            title: getTranslated(
                createAccount ? 'order_placed_Account_Created' : 'order_placed',
                context),
            description: getTranslated('your_order_placed', context),
            isFailed: false,
          ),
          dismissible: false,
          willFlip: true);
    } else {
      showCustomSnackBar(message, context, isToaster: true);
    }
  }
}
