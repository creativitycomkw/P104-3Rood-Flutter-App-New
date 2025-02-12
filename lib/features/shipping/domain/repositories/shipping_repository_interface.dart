import 'package:flutter_ecommerce/interface/repo_interface.dart';

abstract class ShippingRepositoryInterface<T> implements RepositoryInterface{

  Future<dynamic> getShippingMethod(int? sellerId, String? type,String? countryId);

  Future<dynamic> addShippingMethod(int? id, String? cartGroupId);

  Future<dynamic> getChosenShippingMethod();

}