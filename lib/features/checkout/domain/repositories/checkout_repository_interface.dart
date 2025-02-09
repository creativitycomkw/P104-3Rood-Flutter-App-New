import 'package:flutter_ecommerce/interface/repo_interface.dart';

abstract class CheckoutRepositoryInterface implements RepositoryInterface{

  Future<dynamic> cashOnDeliveryPlaceOrder(String? addressID, String? couponCode,String? couponDiscountAmount, String? billingAddressId, String? orderNote, bool? isCheckCreateAccount, String? password,String? branchId);

  Future<dynamic> offlinePaymentPlaceOrder(String? addressID, String? couponCode, String? couponDiscountAmount, String? billingAddressId, String? orderNote, List <String?> typeKey, List<String> typeValue, int? id, String name, String? paymentNote, bool? isCheckCreateAccount, String? password);

  Future<dynamic> walletPaymentPlaceOrder(String? addressID, String? couponCode,String? couponDiscountAmount, String? billingAddressId, String? orderNote, bool? isCheckCreateAccount, String? password,String? branchId);

  Future<dynamic> digitalPaymentPlaceOrder(String? orderNote, String? customerId, String? addressId, String? billingAddressId, String? couponCode, String? couponDiscount, String? paymentMethod, bool? isCheckCreateAccount, String? password,String? branchId);

  Future<dynamic> offlinePaymentList();
}